<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Create roles (agar already exist hai to firstOrCreate use karo)
        $adminRole   = Role::firstOrCreate(['name' => 'admin']);
        $studentRole = Role::firstOrCreate(['name' => 'student']);

        // Create default permissions
        $manageGroupsPermission = Permission::firstOrCreate(['name' => 'manage groups']);

        // 🔥 New permission for updater
        $systemUpdatePermission = Permission::firstOrCreate(['name' => 'system.update']);

        // Assign permissions to roles
        $adminRole->givePermissionTo($manageGroupsPermission);

        // 🔥 Give update permission only to admin
        $adminRole->givePermissionTo($systemUpdatePermission);
    }
}
