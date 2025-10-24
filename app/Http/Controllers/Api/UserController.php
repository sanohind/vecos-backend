<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::with('roles')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('nik', 'like', "%{$search}%")
                      ->orWhere('department', 'like', "%{$search}%");
                });
            })
            ->when($request->role, function ($query, $role) {
                $query->whereHas('roles', function ($q) use ($role) {
                    $q->where('name', $role);
                });
            })
            ->when($request->department, function ($query, $department) {
                $query->where('department', $department);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'code' => 200,
            'message' => 'Users retrieved successfully',
            'data' => $users
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(UserStoreRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        $validatedData['password'] = bcrypt($validatedData['password']);
        
        $user = User::create($validatedData);

        // Assign roles
        $user->assignRole($request->roles);

        return response()->json([
            'code' => 201,
            'message' => 'User created successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'department' => $user->department,
                    'nik' => $user->nik,
                    'roles' => $user->getRoleNames(),
                ]
            ]
        ], 201);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        $user->load('roles', 'permissions');

        return response()->json([
            'code' => 200,
            'message' => 'User retrieved successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'department' => $user->department,
                    'nik' => $user->nik,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ]
            ]
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(UserUpdateRequest $request, User $user): JsonResponse
    {
        $validatedData = $request->validated();
        
        if (isset($validatedData['password'])) {
            $validatedData['password'] = bcrypt($validatedData['password']);
        }

        $user->update($validatedData);

        // Update roles if provided
        if ($request->has('roles')) {
            $user->syncRoles($request->roles);
        }

        $user->load('roles');

        return response()->json([
            'code' => 200,
            'message' => 'User updated successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'department' => $user->department,
                    'nik' => $user->nik,
                    'roles' => $user->getRoleNames(),
                ]
            ]
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): JsonResponse
    {
        // Prevent superadmin from deleting themselves
        if ($user->hasRole('Superadmin') && $user->id === auth()->id()) {
            throw ValidationException::withMessages([
                'user' => ['You cannot delete your own superadmin account.'],
            ]);
        }

        // Prevent deletion of the last superadmin
        if ($user->hasRole('Superadmin')) {
            $superadminCount = User::role('Superadmin')->count();
            if ($superadminCount <= 1) {
                throw ValidationException::withMessages([
                    'user' => ['Cannot delete the last superadmin user.'],
                ]);
            }
        }

        $user->delete();

        return response()->json([
            'code' => 200,
            'message' => 'User deleted successfully',
            'data' => null
        ]);
    }

    /**
     * Get available roles for assignment.
     */
    public function getRoles(): JsonResponse
    {
        $roles = \Spatie\Permission\Models\Role::all()->pluck('name');

        return response()->json([
            'code' => 200,
            'message' => 'Roles retrieved successfully',
            'data' => $roles
        ]);
    }

    /**
     * Get available departments.
     */
    public function getDepartments(): JsonResponse
    {
        $departments = [
            'Accounting',
            'Marketing', 
            'Purchasing',
            'QC & Engineering',
            'Maintenance',
            'HR & GA',
            'Brazing',
            'Chassis',
            'Nylon',
            'PPIC',
            'Jishuken'
        ];

        return response()->json([
            'code' => 200,
            'message' => 'Departments retrieved successfully',
            'data' => $departments
        ]);
    }
}
