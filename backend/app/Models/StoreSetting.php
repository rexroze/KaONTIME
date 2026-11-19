<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Read a setting value, falling back to the given default when absent.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    /**
     * Persist a setting value, storing booleans as "1"/"0".
     */
    public static function putValue(string $key, mixed $value): void
    {
        $stored = match (true) {
            is_bool($value) => $value ? '1' : '0',
            is_null($value) => null,
            default => (string) $value,
        };

        static::query()->updateOrCreate(['key' => $key], ['value' => $stored]);
    }
}
