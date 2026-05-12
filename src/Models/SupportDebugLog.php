<?php

namespace Acelle\Console\Models;

use App\Model\User;
use Illuminate\Database\Eloquent\Model;

class SupportDebugLog extends Model
{
    public const TYPE_SHELL = 'shell';
    public const TYPE_TINKER = 'tinker';
    public const TYPE_ARTISAN = 'artisan';
    public const TYPE_BUNDLE = 'bundle';

    public const COMMAND_CAP_BYTES = 60000;

    public $timestamps = false;

    protected $table = 'support_debug_logs';

    protected $fillable = [
        'user_id',
        'ip',
        'user_agent',
        'type',
        'command',
        'exit_code',
        'duration_ms',
        'output_bytes',
        'truncated',
        'created_at',
    ];

    protected $casts = [
        'truncated' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $log) {
            if (!$log->created_at) {
                $log->created_at = now();
            }

            if (is_string($log->command) && strlen($log->command) > self::COMMAND_CAP_BYTES) {
                $log->command = substr($log->command, 0, self::COMMAND_CAP_BYTES) . "\n[... command truncated ...]";
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
