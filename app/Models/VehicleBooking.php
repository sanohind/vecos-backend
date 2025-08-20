<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use App\Traits\HasCorrectTimestamps;

class VehicleBooking extends Model
{
    use HasFactory, HasCorrectTimestamps;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'vehicle_id',
        'user_id',
        'start_time',
        'end_time',
        'destination',
        'status',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'status' => 'string',
    ];

    /**
     * Get the vehicle that this booking belongs to.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the user who made this booking.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if booking has conflict with other bookings.
     */
    public function hasConflict(): bool
    {
        return VehicleBooking::where('vehicle_id', $this->vehicle_id)
                           ->where('id', '!=', $this->id ?? 0)
                           ->whereIn('status', ['pending', 'approved'])
                           ->where(function ($query) {
                               $query->whereBetween('start_time', [$this->start_time, $this->end_time])
                                     ->orWhereBetween('end_time', [$this->start_time, $this->end_time])
                                     ->orWhere(function ($q) {
                                         $q->where('start_time', '<=', $this->start_time)
                                           ->where('end_time', '>=', $this->end_time);
                                     });
                           })
                           ->exists();
    }

    /**
     * Get duration in hours.
     */
    public function getDurationAttribute(): float
    {
        return $this->start_time->diffInHours($this->end_time, false);
    }

    /**
     * Check if booking is in the past.
     */
    public function isPast(): bool
    {
        return $this->end_time->isPast();
    }

    /**
     * Check if booking is currently active.
     */
    public function isActive(): bool
    {
        $now = Carbon::now();
        return $this->start_time->lte($now) && $this->end_time->gte($now);
    }

    /**
     * Scope to filter pending bookings.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to filter approved bookings.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to filter rejected bookings.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope to filter bookings for specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter bookings within date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->where('start_time', '>=', $startDate)
                    ->where('end_time', '<=', $endDate);
    }
}