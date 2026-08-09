<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->orderBy('name')->get()->map(function (Role $role) {
            return [
                'id'          => $role->id,
                'name'        => $role->name,
                'guard_name'  => $role->guard_name,
                'permissions' => $role->permissions->pluck('name')->sort()->values(),
            ];
        });

        return response()->json(['data' => $roles]);
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::orderBy('name')->get()->pluck('name');

        $modules = [];
        foreach ($permissions as $permission) {
            [$module, $action] = array_pad(explode('.', $permission, 2), 2, $permission);
            $modules[$module][] = [
                'name'   => $permission,
                'action' => $action,
            ];
        }

        return response()->json(['data' => $modules]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:60|regex:/^[a-z0-9-_]+$/',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        if (Role::where('name', $request->name)->where('guard_name', 'web')->exists()) {
            return response()->json(['message' => 'Role dengan nama tersebut sudah ada'], 422);
        }

        $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);
        $role->syncPermissions($request->permissions ?? []);

        return response()->json(['message' => 'Role berhasil ditambahkan', 'data' => $role], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:60|regex:/^[a-z0-9-_]+$/',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $exists = Role::where('name', $request->name)
            ->where('guard_name', 'web')
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Role dengan nama tersebut sudah ada'], 422);
        }

        $role->update(['name' => $request->name]);

        if (is_array($request->permissions)) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json(['message' => 'Role berhasil diperbarui']);
    }

    public function syncPermissions(Request $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'permissions'   => 'required|array',
            'permissions.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        if ($role->name === 'super-admin') {
            $role->syncPermissions(Permission::pluck('name')->all());
        } else {
            $role->syncPermissions($request->permissions);
        }

        return response()->json(['message' => 'Permission role diperbarui', 'permissions' => $role->permissions->pluck('name')->sort()->values()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if ($role->name === 'super-admin') {
            return response()->json(['message' => 'Role super-admin tidak dapat dihapus'], 403);
        }

        $role->delete();

        return response()->json(['message' => 'Role berhasil dihapus']);
    }
}
