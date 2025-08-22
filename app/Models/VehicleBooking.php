<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use App\Traits\HasCorrectTimestamps;

/**
 * @method static Builder shouldBeCompleted()
 * @method static Builder expired()
 * @method static Builder pending()
 * @method static Builder approved()
 * @method static Builder rejected()
 * @method static Builder completed()
 * @method static Builder forUser($userId)
 * @method static Builder inDateRange($startDate, $endDate)
 * @property-read bool $is_expired
 * @property-read string $actual_status
 * @property-read float $duration
 */
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
     * The attributes that should be appended to arrays.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'is_expired',
        'actual_status',
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
        return static::query()
                   ->where('vehicle_id', $this->vehicle_id)
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
     * Check if booking is expired (end time has passed).
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->status === 'approved' && $this->end_time->isPast();
    }

    /**
     * Get the actual status considering expiration.
     * This will show 'expired' for approved bookings that have passed their end time.
     */
    public function getActualStatusAttribute(): string
    {
        if ($this->is_expired) {
            return 'expired';
        }
        
        return $this->status;
    }

    /**
     * Check if booking should be automatically completed.
     */
    public function shouldBeCompleted(): bool
    {
        return $this->status === 'approved' && $this->end_time->isPast();
    }

    /**
     * Auto-complete this booking if it should be completed.
     */
    public function autoComplete(): bool
    {
        if ($this->shouldBeCompleted()) {
            $this->update(['status' => 'completed']);
            return true;
        }
        
        return false;
    }

    /**
     * Scope to filter pending bookings.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to filter approved bookings.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to filter rejected bookings.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope to filter completed bookings.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to filter expired bookings (approved but past end_time).
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'approved')
                    ->where('end_time', '<', Carbon::now());
    }

    /**
     * Scope to filter bookings that should be auto-completed.
     */
    public function scopeShouldBeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'approved')
                    ->where('end_time', '<', Carbon::now());
    }

    /**
     * Scope to filter bookings for specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter bookings within date range.
     */
    public function scopeInDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->where('start_time', '>=', $startDate)
                    ->where('end_time', '<=', $endDate);
    }

    /**
     * Check if booking is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if booking is expired.
     */
    public function isExpired(): bool
    {
        return $this->is_expired;
    }

    /**
     * Boot method to handle model events.
     */
    protected static function boot()
    {
        parent::boot();
        
        // Optionally auto-complete when retrieving model
        static::retrieved(function ($booking) {
            // Uncomment the following line if you want to auto-complete on every retrieval
            // This might cause performance issues with large datasets
            // $booking->autoComplete();
        });
    }
}