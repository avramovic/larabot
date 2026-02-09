<?php

namespace App\Models;

use Cron\CronExpression;
use Illuminate\Database\Eloquent\Model;

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
