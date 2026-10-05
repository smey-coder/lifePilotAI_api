<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;


class PermissionController extends Controller
{
    /**
     * Display all permissions (Supports pagination & search)
     */
    public function index(Request $request)
    {
        try {
            $search = $request->search;
            
            $query = Permission::when($search, function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%");
            })->orderBy('id', 'desc');

            // ប្រសិនបើផ្ញើ query param ?all=true វានឹងទាញយកទាំងអស់ មិនបាច់ធ្វើ Pagination ឡើយ
            if ($request->boolean('all')) {
                $permissions = $query->get();
            } else {
                $perPage = $request->input('per_page', 10);
                $permissions = $query->paginate($perPage);
            }

            return response()->json([
                'success' => true,
                'message' => 'Permissions retrieved successfully',
                'data' => $permissions
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store new permission
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:100',
                    'unique:permissions,name'
                ],
                'guard_name' => [
                    'nullable',
                    'string'
                ]
            ]);

            $permission = Permission::create([
                'name' => $validated['name'],
                'guard_name' => $validated['guard_name'] ?? 'web'
            ]);

            // Clear Spatie Permission Cache immediately.
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return response()->json([
                'success' => true,
                'message' => 'Permission created successfully',
                'data' => $permission
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
                'message' => 'Create permission failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display one permission
     */
    public function show($id)
    {
        try {
            $permission = Permission::find($id);

            if (!$permission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $permission
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Get permission failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update permission
     */
    public function update(Request $request, $id)
    {
        try {
            $permission = Permission::find($id);

            if (!$permission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission not found'
                ], 404);
            }

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('permissions', 'name')->ignore($permission->id)
                ]
            ]);

            $permission->update([
                'name' => $validated['name']
            ]);

            // Clear Spatie Permission Cache immediately.
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return response()->json([
                'success' => true,
                'message' => 'Permission updated successfully',
                'data' => $permission
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
                'message' => 'Update permission failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Delete permission safely with Database Transaction and Cache Clear
     */
    public function destroy($id)
    {
        try {
            $permission = Permission::find($id);

            if (!$permission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission not found'
                ], 404);
            }

            // ប្រើ Transaction ដើម្បីលុបទិន្នន័យចេញពី Pivot Tables ទាំងអស់ជាមុន
            DB::transaction(function () use ($permission) {
                // 1. លុបចេញពី Pivot Tables (role_has_permissions និង model_has_permissions)
                DB::table('role_has_permissions')->where('permission_id', $permission->id)->delete();
                DB::table('model_has_permissions')->where('permission_id', $permission->id)->delete();

                // 2. លុប Permission ចេញពី Table permissions
                DB::table('permissions')->where('id', $permission->id)->delete();
            });

            // 3. Clear Spatie Permission Cache
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return response()->json([
                'success' => true,
                'message' => 'Permission deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delete permission failed',
                'error' => $e->getMessage()
            ], 500);
        }
    
    }
}