<?php

namespace Acelle\Console\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class SupportDebugService
{
    public const OUTPUT_CAP_BYTES = 262144; // 256KB per stream
    public const TIMEOUT_DEFAULT = 30;
    public const TIMEOUT_MAX = 120;

    private const REDACT_PATTERNS = [
        '/(?i)(password\s*[=:]\s*)(\S+)/',
        '/(?i)(api[_-]?key\s*[=:]\s*)(\S+)/',
        '/(?i)(secret\s*[=:]\s*)(\S+)/',
        '/(?i)(token\s*[=:]\s*)(\S+)/',
        '/(?i)(\bAKIA[0-9A-Z]{16}\b)/',
        '/(?i)(sk-[a-zA-Z0-9]{20,})/',
    ];

    /**
     * Execute based on type. Returns structured result array.
     */
    public function execute(string $type, string $command, int $timeout = self::TIMEOUT_DEFAULT, bool $redact = false): array
    {
        $timeout = max(1, min($timeout, self::TIMEOUT_MAX));

        $start = microtime(true);
        switch ($type) {
            case 'shell':
                $result = $this->execShell($command, $timeout);
                break;
            case 'tinker':
                $result = $this->execTinker($command);
                break;
            case 'artisan':
                $result = $this->execArtisan($command);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported type: {$type}");
        }
        $result['duration_ms'] = (int) round((microtime(true) - $start) * 1000);
        $result['type'] = $type;
        $result['command'] = $command;

        if ($redact) {
            $result['stdout'] = $this->redact($result['stdout']);
            $result['stderr'] = $this->redact($result['stderr']);
        }

        [$result['stdout'], $stdoutTrunc] = $this->cap($result['stdout']);
        [$result['stderr'], $stderrTrunc] = $this->cap($result['stderr']);
        $result['truncated'] = $stdoutTrunc || $stderrTrunc;
        $result['output_bytes'] = strlen($result['stdout']) + strlen($result['stderr']);

        return $result;
    }

    private function execShell(string $command, int $timeout): array
    {
        $process = Process::fromShellCommandline($command, base_path());
        $process->setTimeout($timeout);

        try {
            $process->run();
            return [
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
                'exit_code' => $process->getExitCode(),
            ];
        } catch (ProcessTimedOutException $e) {
            return [
                'stdout' => $process->getOutput(),
                'stderr' => "[timeout after {$timeout}s] " . $process->getErrorOutput(),
                'exit_code' => 124,
            ];
        }
    }

    private function execTinker(string $command): array
    {
        // Auto-import common facades so commands can be short:
        // `Setting::get('license')` instead of `\App\Model\Setting::get(...)`
        $prelude = <<<'PHP'
use App\Model\Setting;
use App\Model\User;
use App\Model\Admin;
use App\Model\Customer;
use App\Model\Campaign;
use App\Model\Plugin;
use App\Model\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
PHP;

        $code = trim($command);
        $hasReturn = (bool) preg_match('/^\s*return\b/i', $code);
        if (!$hasReturn && !str_contains($code, ';')) {
            $code = 'return ' . rtrim($code, "; \t\n") . ';';
        } elseif (!$hasReturn) {
            $stmts = array_values(array_filter(array_map('trim', explode(';', $code))));
            if ($stmts) {
                $last = array_pop($stmts);
                if (!preg_match('/^\s*return\b/i', $last)) {
                    $last = 'return ' . $last;
                }
                $stmts[] = $last;
                $code = implode(';', $stmts) . ';';
            }
        } elseif (!str_ends_with($code, ';')) {
            $code .= ';';
        }

        ob_start();
        try {
            $result = eval($prelude . "\n" . $code);
            $echoed = ob_get_clean();
            return [
                'stdout' => $echoed . $this->serializeResult($result),
                'stderr' => '',
                'exit_code' => 0,
            ];
        } catch (Throwable $e) {
            $echoed = ob_get_clean() ?: '';
            return [
                'stdout' => $echoed,
                'stderr' => get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString(),
                'exit_code' => 1,
            ];
        }
    }

    private function execArtisan(string $command): array
    {
        $parts = preg_split('/\s+/', trim($command));
        if (!$parts || $parts[0] === '') {
            return [
                'stdout' => '',
                'stderr' => 'Empty artisan command',
                'exit_code' => 2,
            ];
        }

        $name = array_shift($parts);
        $params = [];
        foreach ($parts as $part) {
            if (str_starts_with($part, '--')) {
                $kv = substr($part, 2);
                if (str_contains($kv, '=')) {
                    [$k, $v] = explode('=', $kv, 2);
                    $params['--' . $k] = $v;
                } else {
                    $params['--' . $kv] = true;
                }
            } else {
                $params[] = $part;
            }
        }

        try {
            $exit = Artisan::call($name, $params);
            return [
                'stdout' => Artisan::output(),
                'stderr' => '',
                'exit_code' => $exit,
            ];
        } catch (Throwable $e) {
            return [
                'stdout' => '',
                'stderr' => get_class($e) . ': ' . $e->getMessage(),
                'exit_code' => 1,
            ];
        }
    }

    private function serializeResult(mixed $result): string
    {
        if ($result === null) {
            return "null\n";
        }
        if (is_scalar($result)) {
            return (string) $result . "\n";
        }
        if (is_array($result) || $result instanceof \JsonSerializable || $result instanceof \stdClass) {
            $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                return $json . "\n";
            }
        }
        if (is_object($result) && method_exists($result, 'toArray')) {
            $json = json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                return $json . "\n";
            }
        }
        return print_r($result, true);
    }

    private function cap(string $output): array
    {
        if (strlen($output) <= self::OUTPUT_CAP_BYTES) {
            return [$output, false];
        }
        return [
            substr($output, 0, self::OUTPUT_CAP_BYTES) . "\n\n[... output truncated at " . self::OUTPUT_CAP_BYTES . " bytes ...]",
            true,
        ];
    }

    public function redact(string $text): string
    {
        foreach (self::REDACT_PATTERNS as $pattern) {
            $text = preg_replace_callback($pattern, function ($m) {
                if (count($m) >= 3) {
                    return $m[1] . '***REDACTED***';
                }
                return '***REDACTED***';
            }, $text);
        }
        return $text;
    }
}
