<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    /**
     * Display a listing of vehicles.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Vehicle::query();

        // Filter by status if provided
        if ($request->has('status') && in_array($request->status, ['active', 'inactive'])) {
            $query->where('status', $request->status);
        }

        // Search by brand, model, or plat_no
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('brand', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%")
                  ->orWhere('plat_no', 'like', "%{$search}%")
                  ->orWhere('vehicle_id', 'like', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $vehicles = $query->with(['bookings' => function ($query) {
            $query->whereIn('status', ['pending', 'approved'])
                  ->where('end_time', '>=', now())
                  ->orderBy('start_time');
        }])->paginate($perPage);

        return response()->json([
            'code' => 200,
            'message' => 'Vehicles retrieved successfully',
            'data' => $vehicles
        ]);
    }

    /**
     * Store a newly created vehicle.
     */
    public function store(Request $request): JsonResponse
    {
        // Check permission
        if (!$request->user()->can('create vehicles')) {
            return response()->json([
                'code' => 403,
                'message' => 'Access denied. You do not have permission to create vehicles.',
                'data' => null
            ], 403);
        }

        $request->validate([
            'vehicle_id' => 'required|string|unique:vehicles,vehicle_id|max:255',
            'plat_no' => 'required|string|unique:vehicles,plat_no|max:255',
            'brand' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $vehicle = Vehicle::create([
            'vehicle_id' => $request->vehicle_id,
            'plat_no' => strtoupper($request->plat_no), // Normalize to uppercase
            'brand' => $request->brand,
            'model' => $request->model,
            'status' => $request->get('status', 'active'),
        ]);

        return response()->json([
            'code' => 201,
            'message' => 'Vehicle created successfully',
            'data' => $vehicle
        ], 201);
    }

    /**
     * Display the specified vehicle.
     */
    public function show(Vehicle $vehicle): JsonResponse
    {
        $vehicle->load(['bookings' => function ($query) {
            $query->with('user:id,name,email')
                  ->orderBy('start_time', 'desc');
        }]);

        return response()->json([
            'code' => 200,
            'message' => 'Vehicle retrieved successfully',
            'data' => $vehicle
        ]);
    }

    /**
     * Update the specified vehicle.
     */
    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        // Check permission
        if (!$request->user()->can('update vehicles')) {
            return response()->json([
                'code' => 403,
                'message' => 'Access denied. You do not have permission to update vehicles.',
                'data' => null
            ], 403);
        }

        $request->validate([
            'vehicle_id' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('vehicles', 'vehicle_id')->ignore($vehicle->id)
            ],
            'plat_no' => [
                'sometimes',
                'string', 
                'max:255',
                Rule::unique('vehicles', 'plat_no')->ignore($vehicle->id)
            ],
            'brand' => 'sometimes|string|max:255',
            'model' => 'sometimes|string|max:255',
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $updateData = $request->only(['vehicle_id', 'plat_no', 'brand', 'model', 'status']);
        
        // Normalize plat_no to uppercase if provided
        if (isset($updateData['plat_no'])) {
            $updateData['plat_no'] = strtoupper($updateData['plat_no']);
        }

        $vehicle->update($updateData);

        return response()->json([
            'code' => 200,
            'message' => 'Vehicle updated successfully',
            'data' => $vehicle->fresh()
        ]);
    }

    /**
     * Remove the specified vehicle.
     */
    public function destroy(Request $request, Vehicle $vehicle): JsonResponse
    {
        // Check permission
        if (!$request->user()->can('delete vehicles')) {
            return response()->json([
                'code' => 403,
                'message' => 'Access denied. You do not have permission to delete vehicles.',
                'data' => null
            ], 403);
        }

        // Check if vehicle has active bookings
        $activeBookings = $vehicle->bookings()
            ->whereIn('status', ['pending', 'approved'])
            ->where('end_time', '>=', now())
            ->count();

        if ($activeBookings > 0) {
            return response()->json([
                'code' => 400,
                'message' => 'Cannot delete vehicle. It has active bookings.',
                'data' => [
                    'active_bookings_count' => $activeBookings
                ]
            ], 400);
        }

        $vehicle->delete();

        return response()->json([
            'code' => 200,
            'message' => 'Vehicle deleted successfully',
            'data' => null
        ]);
    }

    /**
     * Get available vehicles for booking in specified time range.
     */
    public function available(Request $request): JsonResponse
    {
        $request->validate([
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
        ]);

        $startTime = $request->start_time;
        $endTime = $request->end_time;

        $availableVehicles = Vehicle::active()
            ->whereDoesntHave('bookings', function ($query) use ($startTime, $endTime) {
                $query->whereIn('status', ['pending', 'approved'])
                      ->where(function ($q) use ($startTime, $endTime) {
                          $q->whereBetween('start_time', [$startTime, $endTime])
                            ->orWhereBetween('end_time', [$startTime, $endTime])
                            ->orWhere(function ($qq) use ($startTime, $endTime) {
                                $qq->where('start_time', '<=', $startTime)
                                   ->where('end_time', '>=', $endTime);
                            });
                      });
            })
            ->get();

        return response()->json([
            'code' => 200,
            'message' => 'Available vehicles retrieved successfully',
            'data' => [
                'vehicles' => $availableVehicles,
                'time_range' => [
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ],
                'count' => $availableVehicles->count()
            ]
        ]);
    }
}