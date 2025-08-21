<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VehicleBooking;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class VehicleBookingController extends Controller
{
    /**
     * Display a listing of bookings.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = VehicleBooking::with(['vehicle', 'user:id,name,email,department,nik']);

        // Admin can see all bookings, Users only see their own
        if (!$user->can('view bookings') || $user->hasRole('User')) {
            $query->where('user_id', $user->id);
        }

        // Filter by status
        if ($request->has('status') && in_array($request->status, ['pending', 'approved', 'rejected'])) {
            $query->where('status', $request->status);
        }

        // Filter by vehicle
        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                    $vehicleQuery->where('brand', 'like', "%{$search}%")
                                 ->orWhere('model', 'like', "%{$search}%")
                                 ->orWhere('plat_no', 'like', "%{$search}%");
                })
                ->orWhere('destination', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            
            $query->where(function ($q) use ($startDate, $endDate) {
                // Include bookings that overlap with our date range
                $q->whereBetween('start_time', [$startDate, $endDate])
                  ->orWhereBetween('end_time', [$startDate, $endDate])
                  ->orWhere(function ($qq) use ($startDate, $endDate) {
                      $qq->where('start_time', '<=', $startDate)
                         ->where('end_time', '>=', $endDate);
                  });
            });
        }

        // Sort by start_time
        $query->orderBy('start_time', 'desc');

        $perPage = $request->get('per_page', 10);
        $bookings = $query->paginate($perPage);

        return response()->json([
            'code' => 200,
            'message' => 'Bookings retrieved successfully',
            'data' => $bookings
        ]);
    }

    /**
     * Get approved bookings schedule for today and tomorrow.
     * This endpoint allows all users to see when vehicles are booked.
     */
    public function schedule(Request $request): JsonResponse
    {
        $request->validate([
            // 'vehicle_id' => 'nullable|exists:vehicles,id',
            'date' => 'nullable|date|date_format:Y-m-d',
            'days' => 'nullable|integer|min:1|max:7', // Allow checking up to 7 days ahead
        ]);

        $startDate = $request->has('date') 
            ? Carbon::parse($request->date)->startOfDay()
            : Carbon::today();
        
        $days = $request->get('days', 2); // Default: today and tomorrow
        $endDate = $startDate->copy()->addDays($days - 1)->endOfDay();

        $query = VehicleBooking::with(['vehicle:id,plat_no,brand,model', 'user:id,name,department'])
            ->whereIn('status', ['approved', 'pending'])
            ->where(function ($q) use ($startDate, $endDate) {
                // Include bookings that overlap with our date range
                $q->whereBetween('start_time', [$startDate, $endDate])
                  ->orWhereBetween('end_time', [$startDate, $endDate])
                  ->orWhere(function ($qq) use ($startDate, $endDate) {
                      $qq->where('start_time', '<=', $startDate)
                         ->where('end_time', '>=', $endDate);
                  });
            });

        // Filter by specific vehicle if requested
        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        $bookings = $query->orderBy('start_time')->get();

        // Group bookings by date for better organization
        $scheduleByDate = [];
        $currentDate = $startDate->copy();

        for ($i = 0; $i < $days; $i++) {
            $dateKey = $currentDate->format('Y-m-d');
            $scheduleByDate[$dateKey] = [
                'date' => $dateKey,
                'day_name' => $currentDate->format('l'),
                'is_today' => $currentDate->isToday(),
                'is_tomorrow' => $currentDate->isTomorrow(),
                'bookings' => []
            ];
            
            // Filter bookings for this specific date
            $dayBookings = $bookings->filter(function ($booking) use ($currentDate) {
                $bookingStart = Carbon::parse($booking->start_time);
                $bookingEnd = Carbon::parse($booking->end_time);
                
                // Check if booking overlaps with this day
                return $bookingStart->format('Y-m-d') <= $currentDate->format('Y-m-d') &&
                       $bookingEnd->format('Y-m-d') >= $currentDate->format('Y-m-d');
            });

            foreach ($dayBookings as $booking) {
                $scheduleByDate[$dateKey]['bookings'][] = [
                    'id' => $booking->id,
                    'status' => $booking->status, // Add status field
                    'vehicle' => [
                        'id' => $booking->vehicle->id,
                        'plat_no' => $booking->vehicle->plat_no,
                        'brand' => $booking->vehicle->brand,
                        'model' => $booking->vehicle->model,
                        'display_name' => $booking->vehicle->brand . ' ' . $booking->vehicle->model,
                    ],
                    'user' => [
                        'name' => $booking->user->name,
                        'department' => $booking->user->department,
                    ],
                    'start_time' => $booking->start_time,
                    'end_time' => $booking->end_time,
                    'destination' => $booking->destination,
                    'duration_hours' => $booking->duration,
                    'time_display' => Carbon::parse($booking->start_time)->format('H:i') . ' - ' . 
                                    Carbon::parse($booking->end_time)->format('H:i'),
                ];
            }

            $currentDate->addDay();
        }

        return response()->json([
            'code' => 200,
            'message' => 'Booking schedule retrieved successfully',
            'data' => [
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'days' => $days,
                ],
                'schedule' => array_values($scheduleByDate),
                'total_bookings' => $bookings->count(),
            ]
        ]);
    }

    /**
     * Get available time slots for a specific vehicle and date.
     * This helps users find available times for booking.
     */
    public function availableSlots(Request $request): JsonResponse
    {
        $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'date' => 'required|date|date_format:Y-m-d|after_or_equal:today',
            'working_hours_start' => 'nullable|date_format:H:i',
            'working_hours_end' => 'nullable|date_format:H:i',
            'slot_duration' => 'nullable|integer|min:1|max:24', // hours
        ]);

        $vehicle = Vehicle::find($request->vehicle_id);
        $date = Carbon::parse($request->date);
        $workingStart = $request->get('working_hours_start', '00:00');
        $workingEnd = $request->get('working_hours_end', '23:59');
        $slotDuration = $request->get('slot_duration', 2); // Default 2 hours

        // Get approved bookings for this vehicle on this date
        $existingBookings = VehicleBooking::where('vehicle_id', $request->vehicle_id)
            ->where('status', 'approved')
            ->where(function ($q) use ($date) {
                $dayStart = $date->copy()->startOfDay();
                $dayEnd = $date->copy()->endOfDay();
                
                $q->whereBetween('start_time', [$dayStart, $dayEnd])
                  ->orWhereBetween('end_time', [$dayStart, $dayEnd])
                  ->orWhere(function ($qq) use ($dayStart, $dayEnd) {
                      $qq->where('start_time', '<=', $dayStart)
                         ->where('end_time', '>=', $dayEnd);
                  });
            })
            ->orderBy('start_time')
            ->get();

        // Generate time slots
        $availableSlots = [];
        $currentSlot = $date->copy()->setTimeFromTimeString($workingStart);
        $endOfDay = $date->copy()->setTimeFromTimeString($workingEnd);

        while ($currentSlot->copy()->addHours($slotDuration)->lte($endOfDay)) {
            $slotEnd = $currentSlot->copy()->addHours($slotDuration);
            
            // Check if this slot conflicts with existing bookings
            $hasConflict = false;
            foreach ($existingBookings as $booking) {
                $bookingStart = Carbon::parse($booking->start_time);
                $bookingEnd = Carbon::parse($booking->end_time);
                
                if ($currentSlot->lt($bookingEnd) && $slotEnd->gt($bookingStart)) {
                    $hasConflict = true;
                    break;
                }
            }

            // Skip slots in the past
            if ($currentSlot->isPast()) {
                $currentSlot->addHour();
                continue;
            }

            $availableSlots[] = [
                'start_time' => $currentSlot->toISOString(),
                'end_time' => $slotEnd->toISOString(),
                'start_display' => $currentSlot->format('H:i'),
                'end_display' => $slotEnd->format('H:i'),
                'duration_hours' => $slotDuration,
                'is_available' => !$hasConflict,
                'is_past' => $currentSlot->isPast(),
            ];

            $currentSlot->addHour();
        }

        return response()->json([
            'code' => 200,
            'message' => 'Available time slots retrieved successfully',
            'data' => [
                'vehicle' => [
                    'id' => $vehicle->id,
                    'plat_no' => $vehicle->plat_no,
                    'brand' => $vehicle->brand,
                    'model' => $vehicle->model,
                    'display_name' => $vehicle->brand . ' ' . $vehicle->model,
                ],
                'date' => $date->format('Y-m-d'),
                'working_hours' => [
                    'start' => $workingStart,
                    'end' => $workingEnd,
                ],
                'slot_duration_hours' => $slotDuration,
                'slots' => $availableSlots,
                'available_slots_count' => collect($availableSlots)->where('is_available', true)->count(),
                'existing_bookings' => $existingBookings->map(function ($booking) {
                    return [
                        'start_time' => $booking->start_time,
                        'end_time' => $booking->end_time,
                        'user_name' => $booking->user->name,
                        'destination' => $booking->destination,
                    ];
                }),
            ]
        ]);
    }

    /**
     * Store a newly created booking.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
            'destination' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Check if vehicle is active
        $vehicle = Vehicle::find($request->vehicle_id);
        if ($vehicle->status !== 'active') {
            return response()->json([
                'code' => 400,
                'message' => 'Cannot book inactive vehicle',
                'data' => null
            ], 400);
        }

        // Check if vehicle is available in the requested time range
        if (!$vehicle->isAvailable($request->start_time, $request->end_time)) {
            return response()->json([
                'code' => 409,
                'message' => 'Vehicle is not available in the requested time range',
                'data' => [
                    'conflicts' => $this->getConflictingBookings($vehicle, $request->start_time, $request->end_time)
                ]
            ], 409);
        }

        // Create booking
        $booking = VehicleBooking::create([
            'vehicle_id' => $request->vehicle_id,
            'user_id' => $request->user()->id,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'destination' => $request->destination,
            'notes' => $request->notes,
            'status' => 'pending', // Default status
        ]);

        $booking->load(['vehicle', 'user:id,name,email,department,nik']);

        return response()->json([
            'code' => 201,
            'message' => 'Booking created successfully',
            'data' => $booking
        ], 201);
    }

    /**
     * Display the specified booking.
     */
    public function show(Request $request, VehicleBooking $booking): JsonResponse
    {
        $user = $request->user();

        // Users can only see their own bookings unless they're admin
        if (!$user->can('view bookings') && $booking->user_id !== $user->id) {
            return response()->json([
                'code' => 403,
                'message' => 'Access denied. You can only view your own bookings.',
                'data' => null
            ], 403);
        }

        $booking->load(['vehicle', 'user:id,name,email,department,nik']);

        return response()->json([
            'code' => 200,
            'message' => 'Booking retrieved successfully',
            'data' => $booking
        ]);
    }

    /**
     * Update the specified booking.
     */
    public function update(Request $request, VehicleBooking $booking): JsonResponse
    {
        $user = $request->user();

        // Users can only update their own bookings, and only if pending
        if (!$user->can('update bookings') && ($booking->user_id !== $user->id || $booking->status !== 'pending')) {
            return response()->json([
                'code' => 403,
                'message' => 'Access denied. You can only update your own pending bookings.',
                'data' => null
            ], 403);
        }

        $request->validate([
            'vehicle_id' => 'sometimes|exists:vehicles,id',
            'start_time' => 'sometimes|date|after:now',
            'end_time' => 'sometimes|date|after:start_time',
            'destination' => 'sometimes|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'status' => [
                'sometimes',
                Rule::in(['pending', 'approved', 'rejected']),
                function ($attribute, $value, $fail) use ($user) {
                    // Only admin can change status
                    if (!$user->can('approve bookings') && in_array($value, ['approved', 'rejected'])) {
                        $fail('Only administrators can approve or reject bookings.');
                    }
                },
            ],
        ]);

        // If time is being updated, check availability
        $newStartTime = $request->get('start_time', $booking->start_time);
        $newEndTime = $request->get('end_time', $booking->end_time);
        $newVehicleId = $request->get('vehicle_id', $booking->vehicle_id);

        if ($request->has(['start_time', 'end_time', 'vehicle_id'])) {
            $vehicle = Vehicle::find($newVehicleId);

            if (!$vehicle->isAvailable($newStartTime, $newEndTime, $booking->id)) {
                return response()->json([
                    'code' => 409,
                    'message' => 'Vehicle is not available in the requested time range',
                    'data' => [
                        'conflicts' => $this->getConflictingBookings($vehicle, $newStartTime, $newEndTime, $booking->id)
                    ]
                ], 409);
            }
        }

        $updateData = $request->only(['vehicle_id', 'start_time', 'end_time', 'destination', 'notes', 'status']);
        $booking->update($updateData);

        $booking->load(['vehicle', 'user:id,name,email,department,nik']);

        return response()->json([
            'code' => 200,
            'message' => 'Booking updated successfully',
            'data' => $booking->fresh(['vehicle', 'user'])
        ]);
    }

    /**
     * Remove the specified booking.
     */
    public function destroy(Request $request, VehicleBooking $booking): JsonResponse
    {
        $user = $request->user();

        // Users can only delete their own bookings, and only if pending
        if (!$user->can('delete bookings') && ($booking->user_id !== $user->id || $booking->status !== 'pending')) {
            return response()->json([
                'code' => 403,
                'message' => 'Access denied. You can only delete your own pending bookings.',
                'data' => null
            ], 403);
        }

        $booking->delete();

        return response()->json([
            'code' => 200,
            'message' => 'Booking deleted successfully',
            'data' => null
        ]);
    }

    /**
     * Approve a booking (Admin only).
     */
    public function approve(Request $request, VehicleBooking $booking): JsonResponse
    {
        if (!$request->user()->can('approve bookings')) {
            return response()->json([
                'code' => 403,
                'message' => 'Access denied. Only administrators can approve bookings.',
                'data' => null
            ], 403);
        }

        if ($booking->status !== 'pending') {
            return response()->json([
                'code' => 400,
                'message' => 'Only pending bookings can be approved',
                'data' => null
            ], 400);
        }

        // Check if still available (in case other bookings were approved meanwhile)
        if ($booking->hasConflict()) {
            return response()->json([
                'code' => 409,
                'message' => 'Cannot approve booking due to scheduling conflict',
                'data' => null
            ], 409);
        }

        $booking->update(['status' => 'approved']);
        $booking->load(['vehicle', 'user:id,name,email,department,nik']);

        return response()->json([
            'code' => 200,
            'message' => 'Booking approved successfully',
            'data' => $booking
        ]);
    }

    /**
     * Reject a booking (Admin only).
     */
    public function reject(Request $request, VehicleBooking $booking): JsonResponse
    {
        if (!$request->user()->can('reject bookings')) {
            return response()->json([
                'code' => 403,
                'message' => 'Access denied. Only administrators can reject bookings.',
                'data' => null
            ], 403);
        }

        if ($booking->status !== 'pending') {
            return response()->json([
                'code' => 400,
                'message' => 'Only pending bookings can be rejected',
                'data' => null
            ], 400);
        }

        $booking->update(['status' => 'rejected']);
        $booking->load(['vehicle', 'user:id,name,email']);

        return response()->json([
            'code' => 200,
            'message' => 'Booking rejected successfully',
            'data' => $booking
        ]);
    }

    /**
     * Get user's booking statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = VehicleBooking::query();

        // Admin sees all stats, users see only their stats
        if (!$user->can('view bookings') || $user->hasRole('User')) {
            $query->where('user_id', $user->id);
        }

        $stats = [
            'total' => $query->count(),
            'pending' => $query->clone()->where('status', 'pending')->count(),
            'approved' => $query->clone()->where('status', 'approved')->count(),
            'rejected' => $query->clone()->where('status', 'rejected')->count(),
            'this_month' => $query->clone()->whereMonth('created_at', now()->month)->count(),
        ];

        return response()->json([
            'code' => 200,
            'message' => 'Booking statistics retrieved successfully',
            'data' => $stats
        ]);
    }

    /**
     * Get conflicting bookings for a vehicle in time range.
     */
    private function getConflictingBookings(Vehicle $vehicle, $startTime, $endTime, $excludeId = null): array
    {
        $query = $vehicle->bookings()
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('start_time', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    ->orWhere(function ($qq) use ($startTime, $endTime) {
                        $qq->where('start_time', '<=', $startTime)
                            ->where('end_time', '>=', $endTime);
                    });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->with('user:id,name,email,department,nik')->get()->toArray();
    }
}