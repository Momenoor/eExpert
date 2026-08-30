<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    public const CACHE_KEY = 'system_settings_all';

    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
        'description',
    ];

    /**
     * In-memory runtime cache for the current PHP lifecycle / request.
     *
     * @var array<string, mixed>|null
     */
    protected static ?array $runtimeCache = null;

    protected static function booted(): void
    {
        static::saved(function () {
            static::clearCache();
        });

        static::deleted(function () {
            static::clearCache();
        });
    }

    public static function clearCache(): void
    {
        static::$runtimeCache = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get all cached settings.
     *
     * @return array<string, mixed>
     */
    public static function allCached(): array
    {
        if (static::$runtimeCache !== null) {
            return static::$runtimeCache;
        }

        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return static::$runtimeCache = Cache::rememberForever(self::CACHE_KEY, function () {
                return static::query()
                    ->get()
                    ->mapWithKeys(function (Setting $setting) {
                        return [$setting->key => $setting->casted_value];
                    })
                    ->all();
            });
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Get a setting by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::allCached();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $default;
    }

    /**
     * Check if a setting exists.
     */
    public static function has(string $key): bool
    {
        $all = static::allCached();

        return array_key_exists($key, $all);
    }

    /**
     * Set a setting value.
     */
    public static function set(string $key, mixed $value, ?string $group = null, ?string $type = null, ?string $description = null): self
    {
        $detectedType = $type ?? static::detectType($value);
        $rawValue = static::formatValueForStorage($value, $detectedType);

        $attributes = [
            'value' => $rawValue,
            'type' => $detectedType,
        ];

        if ($group !== null) {
            $attributes['group'] = $group;
        }

        if ($description !== null) {
            $attributes['description'] = $description;
        }

        $setting = static::updateOrCreate(
            ['key' => $key],
            $attributes
        );

        static::clearCache();

        return $setting;
    }

    /**
     * Remove a setting by key.
     */
    public static function forget(string $key): bool
    {
        $deleted = (bool) static::where('key', $key)->delete();
        static::clearCache();

        return $deleted;
    }

    /**
     * Get settings belonging to a group.
     *
     * @return array<string, mixed>
     */
    public static function getGroup(string $group): array
    {
        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return static::where('group', $group)
                ->get()
                ->mapWithKeys(fn (Setting $s) => [$s->key => $s->casted_value])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Check whether the application is marked offline.
     */
    public static function isOffline(): bool
    {
        return (bool) static::get('app_offline', false);
    }

    /**
     * Get the offline notification message.
     */
    public static function getOfflineMessage(): string
    {
        $message = static::get('offline_message');

        return filled($message)
            ? (string) $message
            : __('System is currently undergoing scheduled maintenance. Please check back later.');
    }

    /**
     * Dynamically apply mail settings to runtime config.
     */
    public static function applyMailConfig(): void
    {
        $mailer = static::get('mail_mailer');
        $host = static::get('mail_host');
        $port = static::get('mail_port');
        $username = static::get('mail_username');
        $password = static::get('mail_password');
        $encryption = static::get('mail_encryption');
        $fromAddress = static::get('mail_from_address');
        $fromName = static::get('mail_from_name');

        if (! empty($mailer)) {
            Config::set('mail.default', $mailer);
        }

        if (! empty($host)) {
            Config::set('mail.mailers.smtp.host', $host);
        }

        if (! empty($port)) {
            Config::set('mail.mailers.smtp.port', (int) $port);
        }

        if (! empty($username)) {
            Config::set('mail.mailers.smtp.username', $username);
        }

        if (! empty($password)) {
            Config::set('mail.mailers.smtp.password', $password);
        }

        if ($encryption !== null) {
            Config::set('mail.mailers.smtp.encryption', $encryption === 'none' ? null : $encryption);
        }

        if (! empty($fromAddress)) {
            Config::set('mail.from.address', $fromAddress);
        }

        if (! empty($fromName)) {
            Config::set('mail.from.name', $fromName);
        }
    }

    /**
     * Accessor for casted value.
     */
    public function getCastedValueAttribute(): mixed
    {
        return static::castValue($this->value, $this->type);
    }

    /**
     * Cast raw value to its appropriate PHP type.
     */
    public static function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer', 'int' => (int) $value,
            'float', 'double' => (float) $value,
            'json', 'array' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Format PHP value for database storage.
     */
    public static function formatValueForStorage(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean', 'bool' => $value ? '1' : '0',
            'json', 'array' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };
    }

    /**
     * Detect setting type based on PHP variable type.
     */
    public static function detectType(mixed $value): string
    {
        return match (gettype($value)) {
            'boolean' => 'boolean',
            'integer' => 'integer',
            'double' => 'float',
            'array' => 'json',
            default => 'string',
        };
    }
}
