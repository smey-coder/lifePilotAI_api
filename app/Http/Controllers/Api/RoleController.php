<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    /**
     * Display all roles with permissions count / items.
     */
    public function index(Request $request)
    {
        try {
            $search = $request->search;

            $query = Role::with('permissions')->when($search, function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%");
            })->orderBy('id', 'desc');

            if ($request->boolean('all')) {
                $roles = $query->get();
            } else {
                $perPage = $request->input('per_page', 10);
                $roles = $query->paginate($perPage);
            }

            return response()->json([
                'success' => true,
                'message' => 'Roles retrieved successfully',
                'data' => $roles
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve roles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store new role with permissions
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:100',
                    'unique:roles,name'
                ],
                'guard_name' => [
                    'nullable',
                    'string'
                ],
                'permissions' => [
                    'nullable',
                    'array'
                ],
                'permissions.*' => [
                    'exists:permissions,name'
                ]
            ]);

            $role = Role::create([
                'name' => $validated['name'],
                'guard_name' => $validated['guard_name'] ?? 'web'
            ]);

            if (!empty($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }

            Cache::forget('spatie.permission.cache');

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'data' => $role->load('permissions')
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
                'message' => 'Create role failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a specific role with its permissions
     */
    public function show($id)
    {
        try {
            $role = Role::with('permissions')->find($id);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $role
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Get role failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update role and sync permissions
     */
    public function update(Request $request, $id)
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found'
                ], 404);
            }

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('roles', 'name')->ignore($role->id)
                ],
                'permissions' => [
                    'nullable',
                    'array'
                ],
                'permissions.*' => [
                    'exists:permissions,name'
                ]
            ]);

            $role->update([
                'name' => $validated['name']
            ]);

            if (isset($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }

            Cache::forget('spatie.permission.cache');

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'data' => $role->load('permissions')
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
                'message' => 'Update role failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete role
     */
    public function destroy($id)
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found'
                ], 404);
            }

            // ប្រើ Transaction ដើម្បីធានាថា Pivot tables ទាំងអស់ត្រូវបានលុបស្អាត
            DB::transaction(function () use ($role) {
                // 1. លុប Pivot Table Records ចេញពី Database ដោយផ្ទាល់
                DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
                DB::table('model_has_roles')->where('role_id', $role->id)->delete();

                // 2. លុប Role
                DB::table('roles')->where('id', $role->id)->delete();
            });

            // 3. Clear Spatie Permission Cache
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delete role failed',
                'error' => $e->getMessage()
            ], 500);
        }

    }
}