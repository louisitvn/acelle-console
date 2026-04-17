<?php

namespace Acelle\Console\Support;

use App\Model\Setting;

/**
 * Single source of truth for the support-debug feature flag.
 * Wraps the underlying Setting so every read/write (middleware,
 * controllers, tests) goes through the same key + yes/no encoding.
 */
class DebugFlag
{
    public const KEY = 'support_debug_enabled';

    public static function isEnabled(): bool
    {
        try {
            return Setting::isYes(self::KEY);
        } catch (\Throwable $e) {
            // Default to enabled if setting missing (first-install path).
            return true;
        }
    }

    public static function set(bool $enabled): bool
    {
        Setting::set(self::KEY, $enabled ? 'yes' : 'no');
        return $enabled;
    }
}
