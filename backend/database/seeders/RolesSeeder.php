<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Роли и права сотрудников. Безопасно запускать повторно. */
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $name) {
            Permission::findOrCreate($name, 'staff');
        }
        foreach (Permissions::ROLES as $name => $role) {
            Role::findOrCreate($name, 'staff')->syncPermissions($role['permissions']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
