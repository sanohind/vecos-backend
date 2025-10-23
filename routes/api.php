<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\VehicleBookingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PublicController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// Public Routes (No Authentication Required)
Route::prefix('public')->group(function () {
    Route::get('/schedule', [PublicController::class, 'schedule']);
    Route::get('/vehicles', [PublicController::class, 'vehicles']);
});

// Protected Routes (Require Authentication)
Route::middleware('auth:sanctum')->group(function () {
    
    // Authentication Routes
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });

    // Vehicle Routes
    Route::prefix('vehicles')->group(function () {
        // Public vehicle routes (all authenticated users)
        Route::get('/', [VehicleController::class, 'index']);
        Route::get('/available', [VehicleController::class, 'available']);
        Route::get('/{vehicle}', [VehicleController::class, 'show']);
        
        // Admin and Superadmin vehicle routes
        Route::middleware(['role:Admin|Superadmin'])->group(function () {
            Route::post('/', [VehicleController::class, 'store']);
            Route::put('/{vehicle}', [VehicleController::class, 'update']);
            Route::patch('/{vehicle}', [VehicleController::class, 'update']);
            Route::delete('/{vehicle}', [VehicleController::class, 'destroy']);
        });
    });

    // Vehicle Booking Routes
    Route::prefix('bookings')->group(function () {
        // All authenticated users
        Route::get('/', [VehicleBookingController::class, 'index']);
        Route::post('/', [VehicleBookingController::class, 'store']);
        Route::get('/stats', [VehicleBookingController::class, 'stats']);
        
        // NEW: Schedule and availability endpoints
        Route::get('/schedule', [VehicleBookingController::class, 'schedule']);
        Route::get('/available-slots', [VehicleBookingController::class, 'availableSlots']);
        
        Route::get('/{booking}', [VehicleBookingController::class, 'show']);
        Route::put('/{booking}', [VehicleBookingController::class, 'update']);
        Route::patch('/{booking}', [VehicleBookingController::class, 'update']);
        Route::delete('/{booking}', [VehicleBookingController::class, 'destroy']);
        
        // Admin and Superadmin booking routes
        Route::middleware(['role:Admin|Superadmin'])->group(function () {
            Route::post('/{booking}/approve', [VehicleBookingController::class, 'approve']);
            Route::post('/{booking}/reject', [VehicleBookingController::class, 'reject']);
            Route::post('/{booking}/complete', [VehicleBookingController::class, 'complete']); // NEW!
        });
    });

    // User Management Routes (Superadmin only)
    Route::prefix('users')->middleware(['role:Superadmin'])->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/roles', [UserController::class, 'getRoles']);
        Route::get('/departments', [UserController::class, 'getDepartments']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::patch('/{user}', [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
    });

    // API Resource Routes (Alternative approach - commented for reference)
    /*
    Route::apiResource('vehicles', VehicleController::class);
    Route::apiResource('bookings', VehicleBookingController::class);
    */
});

// Health Check Route
Route::get('/health', function () {
    return response()->json([
        'code' => 200,
        'message' => 'Vehicle Control System API is running',
        'data' => [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
        ]
    ]);
});

// API Documentation Route (Enhanced)
Route::get('/docs', function () {
    return response()->json([
        'code' => 200,
        'message' => 'API Documentation',
        'data' => [
            'endpoints' => [
                'Authentication' => [
                    'POST /api/auth/login' => 'Login user',
                    'POST /api/auth/register' => 'Register new user',
                    'GET /api/auth/me' => 'Get current user info',
                    'POST /api/auth/logout' => 'Logout current session',
                    'POST /api/auth/logout-all' => 'Logout all sessions',
                ],
                'Vehicles' => [
                    'GET /api/vehicles' => 'List all vehicles (with search & filter)',
                    'GET /api/vehicles/available' => 'Get available vehicles for booking',
                    'GET /api/vehicles/{id}' => 'Get specific vehicle',
                    'POST /api/vehicles' => 'Create vehicle (Admin/Superadmin only)',
                    'PUT /api/vehicles/{id}' => 'Update vehicle (Admin/Superadmin only)',
                    'DELETE /api/vehicles/{id}' => 'Delete vehicle (Admin/Superadmin only)',
                ],
                'Bookings' => [
                    'GET /api/bookings' => 'List user bookings (or all for Admin/Superadmin)',
                    'GET /api/bookings/stats' => 'Get booking statistics',
                    'GET /api/bookings/schedule' => 'Get approved and pending bookings schedule (today & tomorrow by default)',
                    'GET /api/bookings/available-slots' => 'Get available time slots for a vehicle',
                    'POST /api/bookings' => 'Create new booking',
                    'GET /api/bookings/{id}' => 'Get specific booking',
                    'PUT /api/bookings/{id}' => 'Update booking',
                    'DELETE /api/bookings/{id}' => 'Delete booking',
                    'POST /api/bookings/{id}/approve' => 'Approve booking (Admin/Superadmin only)',
                    'POST /api/bookings/{id}/reject' => 'Reject booking (Admin/Superadmin only)',
                ],
                'User Management (Superadmin only)' => [
                    'GET /api/users' => 'List all users (with search & filter)',
                    'POST /api/users' => 'Create new user',
                    'GET /api/users/{id}' => 'Get specific user',
                    'PUT /api/users/{id}' => 'Update user',
                    'DELETE /api/users/{id}' => 'Delete user',
                    'GET /api/users/roles' => 'Get available roles',
                    'GET /api/users/departments' => 'Get available departments',
                ],
            ],
            'schedule_endpoints' => [
                'GET /api/bookings/schedule' => [
                    'description' => 'Get approved and pending bookings for specific date range',
                    'parameters' => [
                        'vehicle_id' => 'optional - vehicles.id (PK) to filter a specific vehicle',
                        'date' => 'optional - start date (Y-m-d format, defaults to today)',
                        'days' => 'optional - number of days to show (1-7, defaults to 2)',
                    ],
                    'example' => '/api/bookings/schedule?vehicle_id=1&date=2025-08-20&days=3'
                ],
                'GET /api/bookings/available-slots' => [
                    'description' => 'Get available time slots for booking a vehicle (24/7 by default)',
                    'parameters' => [
                        'vehicle_id' => 'required - vehicles.id (PK) to check',
                        'date' => 'required - date to check (Y-m-d format)',
                        'working_hours_start' => 'optional - start time window (H:i format, defaults to 00:00)',
                        'working_hours_end' => 'optional - end time window (H:i format, defaults to 23:59)',
                        'slot_duration' => 'optional - slot duration in hours (defaults to 2)',
                    ],
                    'example' => '/api/bookings/available-slots?vehicle_id=1&date=2025-08-21&slot_duration=3'
                ]
            ],
            'user_management_endpoints' => [
                'GET /api/users' => [
                    'description' => 'List all users with search, filter, and pagination',
                    'parameters' => [
                        'search' => 'optional - search in name, email, nik, or department',
                        'role' => 'optional - filter by role name',
                        'department' => 'optional - filter by department',
                        'per_page' => 'optional - items per page (defaults to 15)',
                    ],
                    'example' => '/api/users?search=john&role=Admin&per_page=20'
                ],
                'POST /api/users' => [
                    'description' => 'Create new user with role assignment',
                    'required_fields' => ['name', 'email', 'password', 'password_confirmation', 'department', 'nik', 'roles'],
                    'roles' => 'array of role names (e.g., ["Admin", "User"])',
                ],
            ],
            'authentication' => 'Bearer Token (Laravel Sanctum)',
            'base_url' => url('/api'),
        ]
    ]);
});