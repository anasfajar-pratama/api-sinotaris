<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = ['super-admin', 'notaris', 'staff', 'klien', 'kurir'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Each module groups several permissions so the UI can render them hierarchically.
        $modules = [
            'documents'      => ['view', 'create', 'edit', 'delete', 'approve'],
            'clients'        => ['view', 'create', 'edit', 'delete'],
            'ajb'            => ['view', 'create', 'edit'],
            'reports'        => ['view', 'export'],
            'users'          => ['view', 'create', 'edit', 'delete'],
            'settings'       => ['view', 'edit'],
            'notifications'  => ['manage'],
            'rbac'           => ['manage'],
        ];

        $all = [];
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $all[] = "{$module}.{$action}";
            }
        }

        foreach ($all as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Default permission matrix per role.
        $matrix = [
            'super-admin' => $all,
            'notaris'     => [
                'documents.view', 'documents.create', 'documents.edit', 'documents.delete', 'documents.approve',
                'clients.view', 'clients.create', 'clients.edit', 'clients.delete',
                'ajb.view', 'ajb.create', 'ajb.edit',
                'reports.view', 'reports.export',
                'notifications.manage',
            ],
            'staff'       => [
                'documents.view', 'documents.create', 'documents.edit', 'documents.delete', 'documents.approve',
                'clients.view', 'clients.create', 'clients.edit',
                'ajb.view', 'ajb.create', 'ajb.edit',
                'reports.view', 'reports.export',
                'notifications.manage',
            ],
            'klien' => [],
            'kurir' => [
                'documents.view',
                'clients.view',
            ],
        ];

        foreach ($matrix as $role => $permissions) {
            $roleModel = Role::findByName($role, 'web');
            $roleModel->syncPermissions($permissions);
        }
    }
}