<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VehicleBooking;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class PublicController extends Controller
{
    /**
     * Get public vehicle booking schedule
     */
    public function schedule(Request $request): JsonResponse
    {
        try {
            // Get parameters
            $days = $request->get('days', 2); // Default to 2 days
            $date = $request->get('date', Carbon::today()->format('Y-m-d'));
            
            // Validate days parameter
            if ($days < 1 || $days > 7) {
                return response()->json([
                    'code' => 400,
                    'message' => 'Days parameter must be between 1 and 7',
                    'data' => null
                ], 400);
            }

            $startDate = Carbon::parse($date);
            $endDate = $startDate->copy()->addDays($days - 1);

            // Get approved and pending bookings for the date range
            $bookings = VehicleBooking::with(['vehicle', 'user'])
                ->whereIn('status', ['approved', 'pending'])
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_time', [
                        $startDate->startOfDay()->toDateTimeString(),
                        $endDate->endOfDay()->toDateTimeString()
                    ])
                    ->orWhereBetween('end_time', [
                        $startDate->startOfDay()->toDateTimeString(),
                        $endDate->endOfDay()->toDateTimeString()
                    ])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_time', '<=', $startDate->startOfDay()->toDateTimeString())
                          ->where('end_time', '>=', $endDate->endOfDay()->toDateTimeString());
                    });
                })
                ->orderBy('start_time')
                ->get();

            // Group bookings by date
            $schedule = [];
            $today = Carbon::today();
            $tomorrow = Carbon::tomorrow();

            for ($i = 0; $i < $days; $i++) {
                $currentDate = $startDate->copy()->addDays($i);
                $dayName = $currentDate->locale('id')->dayName;
                
                // Filter bookings for this specific date
                $dayBookings = $bookings->filter(function ($booking) use ($currentDate) {
                    $bookingStart = Carbon::parse($booking->start_time);
                    $bookingEnd = Carbon::parse($booking->end_time);
                    
                    return $bookingStart->isSameDay($currentDate) || 
                           $bookingEnd->isSameDay($currentDate) ||
                           ($bookingStart->isBefore($currentDate) && $bookingEnd->isAfter($currentDate));
                });

                // Format bookings for this day
                $formattedBookings = $dayBookings->map(function ($booking) {
                    $startTime = Carbon::parse($booking->start_time);
                    $endTime = Carbon::parse($booking->end_time);
                    $duration = $startTime->diffInHours($endTime);

                    return [
                        'id' => $booking->id,
                        'start_time' => $booking->start_time,
                        'end_time' => $booking->end_time,
                        'time_display' => $startTime->format('H:i') . ' - ' . $endTime->format('H:i'),
                        'duration_hours' => $duration,
                        'status' => $booking->status,
                        'destination' => $booking->destination,
                        'vehicle' => [
                            'id' => $booking->vehicle->id,
                            'brand' => $booking->vehicle->brand,
                            'model' => $booking->vehicle->model,
                            'plat_no' => $booking->vehicle->plat_no,
                            'display_name' => $booking->vehicle->brand . ' ' . $booking->vehicle->model,
                        ],
                        'user' => [
                            'id' => $booking->user->id,
                            'name' => $booking->user->name,
                            'department' => $booking->user->department,
                        ],
                    ];
                });

                $schedule[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'day_name' => $dayName,
                    'is_today' => $currentDate->isSameDay($today),
                    'is_tomorrow' => $currentDate->isSameDay($tomorrow),
                    'bookings' => $formattedBookings->values()->toArray(),
                ];
            }

            return response()->json([
                'code' => 200,
                'message' => 'Public schedule retrieved successfully',
                'data' => [
                    'schedule' => $schedule,
                    'date_range' => [
                        'start' => $startDate->format('Y-m-d'),
                        'end' => $endDate->format('Y-m-d'),
                    ],
                    'total_bookings' => $bookings->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error retrieving public schedule: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Get public vehicle list
     */
    public function vehicles(Request $request): JsonResponse
    {
        try {
            $vehicles = Vehicle::where('status', 'active')
                ->select('id', 'brand', 'model', 'plat_no', 'status')
                ->orderBy('brand')
                ->orderBy('model')
                ->get();

            $formattedVehicles = $vehicles->map(function ($vehicle) {
                return [
                    'id' => $vehicle->id,
                    'brand' => $vehicle->brand,
                    'model' => $vehicle->model,
                    'plat_no' => $vehicle->plat_no,
                    'display_name' => $vehicle->brand . ' ' . $vehicle->model,
                    'status' => $vehicle->status,
                ];
            });

            return response()->json([
                'code' => 200,
                'message' => 'Public vehicles retrieved successfully',
                'data' => [
                    'vehicles' => $formattedVehicles,
                    'total' => $vehicles->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error retrieving public vehicles: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
