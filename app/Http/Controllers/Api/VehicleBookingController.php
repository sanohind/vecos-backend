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
        $query = VehicleBooking::with(['vehicle', 'user:id,name,email']);

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

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('start_time', [$request->start_date, $request->end_date]);
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
     * Store a newly created booking.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
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
            'notes' => $request->notes,
            'status' => 'pending', // Default status
        ]);

        $booking->load(['vehicle', 'user:id,name,email']);

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

        $booking->load(['vehicle', 'user:id,name,email']);

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

        $updateData = $request->only(['vehicle_id', 'start_time', 'end_time', 'notes', 'status']);
        $booking->update($updateData);

        $booking->load(['vehicle', 'user:id,name,email']);

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
        $booking->load(['vehicle', 'user:id,name,email']);

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

        return $query->with('user:id,name,email')->get()->toArray();
    }
}