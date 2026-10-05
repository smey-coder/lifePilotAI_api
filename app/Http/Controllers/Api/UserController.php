<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    /**
     * Display all users with roles and permissions
     */
    public function index(Request $request)
    {
        try {
            $search = $request->search;

            $query = User::with(['roles', 'permissions'])
                ->when($search, function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
                })->orderBy('id', 'desc');

            if ($request->boolean('all')) {
                $users = $query->get();
            } else {
                $perPage = $request->input('per_page', 10);
                $users = $query->paginate($perPage);
            }

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully',
                'data' => $users
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store new user with Strong Password & Confirmation
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => [
                    'required',
                    'string',
                    'confirmed', // តម្រូវឱ្យមាន field password_confirmation
                    Password::min(8)
                        ->letters()
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                ],
                'roles' => 'nullable|array',
                'roles.*' => 'exists:roles,name',
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            if (!empty($validated['roles'])) {
                $user->assignRole($validated['roles']);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => $user->load(['roles', 'permissions'])
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Create user failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display specific user
     */
    public function show($id)
    {
        try {
            $user = User::with(['roles', 'permissions'])->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $user
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Get user failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user with Optional Strong Password & Confirmation
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($user->id)
                ],
                'password' => [
                    'nullable',
                    'string',
                    'confirmed', // បើវាយបញ្ចូល password ត្រូវតែមាន password_confirmation
                    Password::min(8)
                        ->letters()
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                ],
                'roles' => 'nullable|array',
                'roles.*' => 'exists:roles,name',
            ]);

            $userData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
            ];

            if (!empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $user->update($userData);

            if (isset($validated['roles'])) {
                $user->syncRoles($validated['roles']);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $user->load(['roles', 'permissions'])
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Update user failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user
     */
    public function destroy($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $user->syncRoles([]);
            $user->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delete user failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}