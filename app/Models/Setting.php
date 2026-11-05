<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public const CACHE_PREFIX = 'onestall_setting_';
    public const ALL_CACHE_KEY = 'onestall_settings_all';

    protected static ?array $runtimeCache = null;

    public static function clearRuntimeCache(): void
    {
        static::$runtimeCache = null;
    }

    protected static function booted(): void
    {
        static::saved(function (Setting $setting) {
            static::clearRuntimeCache();
            Cache::forget(self::CACHE_PREFIX . $setting->key);
            Cache::forget(self::ALL_CACHE_KEY);
        });

        static::deleted(function (Setting $setting) {
            static::clearRuntimeCache();
            Cache::forget(self::CACHE_PREFIX . $setting->key);
            Cache::forget(self::ALL_CACHE_KEY);
        });
    }

    /**
     * Preload and cache all settings in memory for zero-database overhead.
     *
     * @return array<string, mixed>
     */
    public static function loadAll(): array
    {
        if (static::$runtimeCache !== null) {
            return static::$runtimeCache;
        }

        try {
            $records = Cache::remember(self::ALL_CACHE_KEY, 3600, function () {
                return static::all()->keyBy('key');
            });

            static::$runtimeCache = [];
            foreach ($records as $key => $record) {
                static::$runtimeCache[$key] = $record instanceof Setting ? $record->castValue() : $record;
            }
        } catch (\Throwable) {
            static::$runtimeCache = [];
        }

        return static::$runtimeCache;
    }

    /**
     * Get a setting value by key with cache and type casting.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (static::$runtimeCache === null) {
            static::loadAll();
        }

        if (array_key_exists($key, static::$runtimeCache)) {
            return static::$runtimeCache[$key];
        }

        // Check fallback in config/onestall.php or return provided default
        return config("onestall.{$key}", $default);
    }

    /**
     * Set a setting value and persist to DB & cache.
     */
    public static function set(string $key, mixed $value, ?string $type = null, ?string $group = null, ?string $label = null): static
    {
        static::clearRuntimeCache();

        if (is_array($value)) {
            if ($type !== null && !in_array($type, ['array', 'json'])) {
                $group = $group ?? $type;
            }
            $type = 'array';
            $serializedValue = json_encode($value);
        } else {
            $type = $type ?? static::detectType($value);
            $serializedValue = match ($type) {
                'json', 'array' => is_string($value) ? $value : json_encode($value),
                'boolean' => $value ? '1' : '0',
                default => (string) $value,
            };
        }

        $setting = static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $serializedValue,
                'type' => $type,
                'group' => $group ?? 'general',
                'label' => $label,
            ]
        );

        Cache::forget(self::CACHE_PREFIX . $key);
        Cache::forget(self::ALL_CACHE_KEY);

        return $setting;
    }

    /**
     * Cast the stored raw string value into its native PHP type.
     */
    public function castValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            'boolean', 'bool' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer', 'int' => (int) $this->value,
            'float', 'decimal', 'double' => (float) $this->value,
            'json', 'array' => json_decode($this->value, true) ?? [],
            default => $this->value,
        };
    }

    /**
     * Detect setting value type.
     */
    protected static function detectType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'float';
        }
        if (is_array($value)) {
            return 'array';
        }

        return 'string';
    }
}
