<?php

namespace App\Models;

use Cron\CronExpression;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $schedule
 * @property string $prompt
 * @property int $repeat
 * @property bool $enabled
 * @property string $destination
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Task extends Model
{
    //
    protected $guarded = [];

    public function isDue(): bool
    {
        $cron = new CronExpression($this->schedule);
        return $cron->isDue();
    }
}
