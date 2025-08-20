<?php

namespace App\Traits;

use Carbon\Carbon;

trait HasCorrectTimestamps
{
    /**
     * Boot the trait and add event listeners for timestamps
     */
    protected static function bootHasCorrectTimestamps()
    {
        static::creating(function ($model) {
            if ($model->usesTimestamps()) {
                $timezone = config('app.timezone', 'Asia/Jakarta');
                
                if (is_null($model->created_at)) {
                    $model->created_at = Carbon::now()->setTimezone($timezone);
                }
                
                if (is_null($model->updated_at)) {
                    $model->updated_at = Carbon::now()->setTimezone($timezone);
                }
            }
        });

        static::updating(function ($model) {
            if ($model->usesTimestamps()) {
                $timezone = config('app.timezone', 'Asia/Jakarta');
                $model->updated_at = Carbon::now()->setTimezone($timezone);
            }
        });
    }

    /**
     * Get the current timestamp in the correct timezone
     */
    public function getCurrentTimestamp(): Carbon
    {
        $timezone = config('app.timezone', 'Asia/Jakarta');
        return Carbon::now()->setTimezone($timezone);
    }

    /**
     * Get created_at attribute in the correct timezone
     */
    public function getCreatedAtAttribute($value)
    {
        if ($value) {
            $timezone = config('app.timezone', 'Asia/Jakarta');
            return Carbon::parse($value)->setTimezone($timezone);
        }
        return $value;
    }

    /**
     * Get updated_at attribute in the correct timezone
     */
    public function getUpdatedAtAttribute($value)
    {
        if ($value) {
            $timezone = config('app.timezone', 'Asia/Jakarta');
            return Carbon::parse($value)->setTimezone($timezone);
        }
        return $value;
    }
}
