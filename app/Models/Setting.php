<?php

namespace App\Models;

use App\Enums\SettingType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $value
 * @property string $type
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Setting extends Model
{
    protected $guarded = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
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
        return Setting::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : (string) $value, 'type' => $type ?? get_debug_type($value)]
        );
    }
}
