<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property string $key
 * @property array<int|string, mixed> $value
 */
class Setting extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * @param  array<int|string, mixed>  $default
     * @return array<int|string, mixed>
     */
    public static function getValue(string $key, array $default = []): array
    {
        return Cache::rememberForever("setting.{$key}", function () use ($key, $default) {
            $setting = static::query()->find($key);

            return $setting?->value ?? $default;
        });
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    public static function putValue(string $key, array $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        Cache::forget("setting.{$key}");
    }
}
