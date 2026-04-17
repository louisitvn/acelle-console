<?php

namespace Acelle\Console\Services;

use App\Model\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SupportBundleService
{
    public const LOG_TAIL_LINES = 200;

    public function build(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'app' => $this->app(),
            'php' => $this->php(),
            'db' => $this->db(),
            'license' => $this->license(),
            'queue' => $this->queue(),
            'logs' => $this->logs(),
            'disk' => $this->disk(),
            'plugins' => $this->plugins(),
            'settings' => $this->settings(),
        ];
    }

    private function app(): array
    {
        $versionPath = base_path('VERSION');
        $version = is_readable($versionPath) ? trim((string) file_get_contents($versionPath)) : null;

        return [
            'version' => $version,
            'laravel_version' => app()->version(),
            'env' => config('app.env'),
            'debug' => (bool) config('app.debug'),
            'url' => config('app.url'),
            'installed_at' => Setting::get('installed_at'),
            'timezone' => config('app.timezone'),
        ];
    }

    private function php(): array
    {
        $extensions = [
            'pdo_mysql', 'mbstring', 'openssl', 'curl', 'zip', 'gd',
            'intl', 'imap', 'gmp', 'xml', 'fileinfo', 'bcmath', 'exif',
            'mailparse', 'sqlite3', 'opcache',
        ];
        $loaded = [];
        foreach ($extensions as $ext) {
            $loaded[$ext] = extension_loaded($ext);
        }

        return [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'extensions' => $loaded,
        ];
    }

    private function db(): array
    {
        try {
            $connection = DB::connection();
            $pdo = $connection->getPdo();
            $version = (string) $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
            $host = config('database.connections.' . $connection->getName() . '.host');
            $prefix = $connection->getTablePrefix();

            $tableCount = null;
            $sizeMb = null;
            try {
                $dbName = $connection->getDatabaseName();
                $rows = DB::select(
                    'SELECT COUNT(*) AS c, COALESCE(SUM(data_length + index_length), 0) AS s FROM information_schema.tables WHERE table_schema = ?',
                    [$dbName]
                );
                if (!empty($rows)) {
                    $tableCount = (int) $rows[0]->c;
                    $sizeMb = round(((int) $rows[0]->s) / 1024 / 1024, 2);
                }
            } catch (Throwable $e) {
                // ignore — may not be MySQL
            }

            return [
                'driver' => $connection->getDriverName(),
                'version' => $version,
                'host' => $this->redactHost($host),
                'database' => $connection->getDatabaseName(),
                'prefix' => $prefix,
                'charset' => config('database.connections.' . $connection->getName() . '.charset'),
                'table_count' => $tableCount,
                'size_mb' => $sizeMb,
            ];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function license(): array
    {
        $supportedUntil = Setting::get('license_supported_until');
        $isExpired = null;
        $daysLeft = null;

        if ($supportedUntil) {
            try {
                $date = \Carbon\Carbon::parse($supportedUntil);
                $isExpired = $date->isPast();
                $daysLeft = (int) now()->diffInDays($date, false);
            } catch (Throwable $e) {
                // leave null
            }
        }

        return [
            'license' => Setting::get('license'),
            'type' => Setting::get('license_type'),
            'supported_until' => $supportedUntil,
            'company' => Setting::get('license_company'),
            'is_expired' => $isExpired,
            'days_left' => $daysLeft,
        ];
    }

    private function queue(): array
    {
        $out = ['driver' => config('queue.default')];
        try {
            if (Schema::hasTable('jobs')) {
                $out['pending'] = (int) DB::table('jobs')->count();
            }
            if (Schema::hasTable('failed_jobs')) {
                $out['failed_total'] = (int) DB::table('failed_jobs')->count();
                $out['failed_24h'] = (int) DB::table('failed_jobs')
                    ->where('failed_at', '>=', now()->subDay())
                    ->count();
            }
        } catch (Throwable $e) {
            $out['error'] = $e->getMessage();
        }
        return $out;
    }

    private function logs(): array
    {
        $logsDir = storage_path('logs');
        if (!is_dir($logsDir)) {
            return ['laravel' => null, 'note' => 'logs directory missing'];
        }

        $files = glob($logsDir . '/laravel*.log') ?: [];
        if (!$files) {
            return ['laravel' => null, 'note' => 'no laravel log files'];
        }

        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        $latest = $files[0];

        return [
            'laravel' => [
                'file' => basename($latest),
                'size_bytes' => filesize($latest),
                'modified_at' => date('c', filemtime($latest)),
                'tail' => $this->tailFile($latest, self::LOG_TAIL_LINES),
            ],
        ];
    }

    private function disk(): array
    {
        $appPath = base_path();
        $storagePath = storage_path();

        $read = function (string $path): array {
            if (!is_dir($path)) {
                return ['path' => $path, 'error' => 'not a directory'];
            }
            $total = @disk_total_space($path);
            $free = @disk_free_space($path);
            $used = ($total !== false && $free !== false) ? $total - $free : null;
            return [
                'path' => $path,
                'total_bytes' => $total !== false ? (int) $total : null,
                'used_bytes' => $used,
                'free_bytes' => $free !== false ? (int) $free : null,
            ];
        };

        return [
            'app' => $read($appPath),
            'storage' => $read($storagePath),
        ];
    }

    private function plugins(): array
    {
        $pluginsDir = storage_path('app/plugins');
        if (!is_dir($pluginsDir)) {
            return [];
        }

        $indexPath = $pluginsDir . '/index.json';
        $index = [];
        if (is_readable($indexPath)) {
            $raw = file_get_contents($indexPath);
            if ($raw !== false) {
                $parsed = json_decode($raw, true);
                if (is_array($parsed)) {
                    $index = $parsed;
                }
            }
        }

        $vendors = array_filter(
            glob($pluginsDir . '/*', GLOB_ONLYDIR) ?: [],
            fn($p) => !str_ends_with($p, '/.') && !str_ends_with($p, '/..')
        );

        $plugins = [];
        foreach ($vendors as $vendor) {
            $pluginDirs = glob($vendor . '/*', GLOB_ONLYDIR) ?: [];
            foreach ($pluginDirs as $dir) {
                $plugins[] = basename($vendor) . '/' . basename($dir);
            }
        }

        return [
            'count' => count($plugins),
            'installed' => $plugins,
            'index' => $index,
        ];
    }

    private function settings(): array
    {
        $whitelist = [
            'site_name', 'installed_at', 'default_language', 'captcha_engine',
            'login_recaptcha', 'mailer_host', 'mailer_port', 'mailer_encryption',
            'support_debug_enabled',
        ];
        $out = [];
        foreach ($whitelist as $key) {
            $val = Setting::get($key);
            if ($val !== null && str_contains(strtolower($key), 'password')) {
                $val = '***REDACTED***';
            }
            $out[$key] = $val;
        }
        return $out;
    }

    private function tailFile(string $path, int $lines): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $buffer = [];
        $fp = fopen($path, 'rb');
        if (!$fp) {
            return [];
        }

        fseek($fp, 0, SEEK_END);
        $pos = ftell($fp);
        $chunk = '';
        $lineCount = 0;

        while ($pos > 0 && $lineCount <= $lines) {
            $read = min(4096, $pos);
            $pos -= $read;
            fseek($fp, $pos);
            $chunk = fread($fp, $read) . $chunk;
            $lineCount = substr_count($chunk, "\n");
        }
        fclose($fp);

        $all = explode("\n", $chunk);
        $out = array_slice($all, -($lines + 1));
        return array_values(array_filter($out, fn($l) => $l !== ''));
    }

    private function redactHost(?string $host): ?string
    {
        if (!$host) {
            return $host;
        }
        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)) {
            return $host;
        }
        $parts = explode('.', $host);
        if (count($parts) < 2) {
            return '***';
        }
        return str_repeat('*', strlen($parts[0])) . '.' . implode('.', array_slice($parts, 1));
    }
}
