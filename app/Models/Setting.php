<?php

namespace App\Models;

use App\Enums\SettingType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class Setting extends Model
{
    protected $guarded = [];

    public static Collection $settings;

    protected static function loadSettings(): void
    {
        static::$settings = static::all();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (empty(static::$settings)) {
            static::loadSettings();
        }

        $setting = static::$settings->firstWhere('key', $key);
        if (!$setting) {
            return $default;
        }

        return match ($setting->type) {
            SettingType::TYPE_STRING->value => (string) $setting->value,
            SettingType::TYPE_INTEGER->value => (int) $setting->value,
            SettingType::TYPE_BOOLEAN->value => (bool) $setting->value,
            SettingType::TYPE_FLOAT->value => (float) $setting->value,
            SettingType::TYPE_ARRAY->value => (array) json_decode($setting->value, true),
        };
    }

    public static function set(string $key, mixed $value, ?string $type = null): Setting
    {
        $setting = Setting::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : (string) $value, 'type' => $type ?? get_debug_type($value)]
        );
        if ($old_setting = static::$settings->firstWhere('key', $key)) {
            $old_setting->value = $setting->value;
            $old_setting->type = $setting->type;
        } else {
            static::$settings->push($setting);
        }

        return $setting;
    }
}
