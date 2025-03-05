<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::create(['name' => 'fetch from wordpress']);
        Permission::create(['name' => 'resend to shopify']);

        // Create Roles and Assign Permissions
        $admin = Role::create(['name' => 'admin']);
        $admin->givePermissionTo(['fetch from wordpress', 'resend to shopify']);

        $editor = Role::create(['name' => 'editor']);
        $editor->givePermissionTo(['fetch from wordpress']);
    }
}
