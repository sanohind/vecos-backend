<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasCorrectTimestamps;

class Vehicle extends Model
{
    use HasFactory, HasCorrectTimestamps;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'plat_no',
        'brand',
        'model',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Get all bookings for this vehicle.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(VehicleBooking::class);
    }

    /**
     * Get active bookings for this vehicle.
     */
    public function activeBookings(): HasMany
    {
        return $this->hasMany(VehicleBooking::class)
                    ->whereIn('status', ['pending', 'approved']);
    }

    /**
     * Check if vehicle is available for booking in given time range.
     */
    public function isAvailable($startTime, $endTime, $excludeBookingId = null): bool
    {
        $query = $this->bookings()
                     ->whereIn('status', ['pending', 'approved'])
                     ->where(function ($q) use ($startTime, $endTime) {
                         $q->whereBetween('start_time', [$startTime, $endTime])
                           ->orWhereBetween('end_time', [$startTime, $endTime])
                           ->orWhere(function ($qq) use ($startTime, $endTime) {
                               $qq->where('start_time', '<=', $startTime)
                                  ->where('end_time', '>=', $endTime);
                           });
                     });

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return $query->count() === 0;
    }

    /**
     * Scope to filter active vehicles only.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter inactive vehicles only.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }
}