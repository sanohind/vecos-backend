<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\VehicleBookingController;

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

// Protected Routes (Require Authentication)
Route::middleware('auth:sanctum')->group(function () {
    
    // Authentication Routes
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });

    // Vehicle Routes
    Route::prefix('vehicles')->group(function () {
        // Public vehicle routes (all authenticated users)
        Route::get('/', [VehicleController::class, 'index']);
        Route::get('/available', [VehicleController::class, 'available']);
        Route::get('/{vehicle}', [VehicleController::class, 'show']);
        
        // Admin-only vehicle routes
        Route::middleware(['role:Admin'])->group(function () {
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
        Route::get('/{booking}', [VehicleBookingController::class, 'show']);
        Route::put('/{booking}', [VehicleBookingController::class, 'update']);
        Route::patch('/{booking}', [VehicleBookingController::class, 'update']);
        Route::delete('/{booking}', [VehicleBookingController::class, 'destroy']);
        
        // Admin-only booking routes
        Route::middleware(['role:Admin'])->group(function () {
            Route::post('/{booking}/approve', [VehicleBookingController::class, 'approve']);
            Route::post('/{booking}/reject', [VehicleBookingController::class, 'reject']);
        });
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

// API Documentation Route (Optional)
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
                    'POST /api/vehicles' => 'Create vehicle (Admin only)',
                    'PUT /api/vehicles/{id}' => 'Update vehicle (Admin only)',
                    'DELETE /api/vehicles/{id}' => 'Delete vehicle (Admin only)',
                ],
                'Bookings' => [
                    'GET /api/bookings' => 'List user bookings (or all for Admin)',
                    'GET /api/bookings/stats' => 'Get booking statistics',
                    'POST /api/bookings' => 'Create new booking',
                    'GET /api/bookings/{id}' => 'Get specific booking',
                    'PUT /api/bookings/{id}' => 'Update booking',
                    'DELETE /api/bookings/{id}' => 'Delete booking',
                    'POST /api/bookings/{id}/approve' => 'Approve booking (Admin only)',
                    'POST /api/bookings/{id}/reject' => 'Reject booking (Admin only)',
                ],
            ],
            'authentication' => 'Bearer Token (Laravel Sanctum)',
            'base_url' => url('/api'),
        ]
    ]);
});