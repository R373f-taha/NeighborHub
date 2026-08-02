<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\app\Models\User;
use Modules\Auth\app\Enums\UserRole;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ============================================================
        // 1. RESET CACHED ROLES AND PERMISSIONS
        // ============================================================
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ============================================================
        // 2. CREATE PERMISSIONS
        // ============================================================

        $allPermissions = [
            // Community
            'view_communities',
            'create_community',
            'update_community',
            'delete_community',
            'view_community_stats',
            // Residents
            'view_residents',
            'approve_resident',
            'reject_resident',
            'suspend_resident',
            'join_community',
            // Posts
            'view_posts',
            'create_post',
            'update_post',
            'delete_post',
            'pin_post',
            // Issues
            'view_issues',
            'create_issue',
            'update_issue',
            'assign_issue',
            'resolve_issue',
            // Polls
            'view_polls',
            'create_poll',
            'vote_poll',
            'close_poll',
            //Announcement
            'view_announcements',
            'create_announcement',
            'update_announcement',
            'delete_announcement',
            'react_announcement',
            // Role management
            'assign_role',
        ];

        foreach ($allPermissions as $permission) {
            Permission::updateOrCreate(['name' => $permission]);
        }

        // ============================================================
        // 3. CREATE ROLES
        // ============================================================

        // 👑 Super Admin
        $roleSuperAdmin = Role::updateOrCreate(['name' => 'super_admin']);
        $roleSuperAdmin->givePermissionTo($allPermissions);

        // 🏢 Manager
        $roleManager = Role::updateOrCreate(['name' => 'manager']);
        $roleManager->givePermissionTo([
            'view_communities',
            'update_community',
            'view_community_stats',
            'view_residents',
            'approve_resident',
            'reject_resident',
            'suspend_resident',
            'view_posts',
            'pin_post',
            'delete_post',
            'view_issues',
            'assign_issue',
            'resolve_issue',
            'view_polls',
            'create_poll',
            'close_poll',
            'view_announcements',
            'create_announcement',
            'update_announcement',
            'delete_announcement',
        ]);

        // 🏠 Resident
        $roleResident = Role::updateOrCreate(['name' => 'resident']);
        $roleResident->givePermissionTo([
            'view_communities',
            'view_posts',
            'create_post',
            'update_post',
            'view_issues',
            'create_issue',
            'view_polls',
            'vote_poll',
            'join_community',
            'view_announcements',
            'react_announcement',
        ]);

        // 🔧 Provider
        $roleProvider = Role::updateOrCreate(['name' => 'provider']);
        $roleProvider->givePermissionTo([
            'view_issues',
            'update_issue',
            'resolve_issue',
        ]);

        // ============================================================
        // 4. CREATE USERS AND ASSIGN ROLES
        // ============================================================

        // 👑 Super Admin
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@neighborhub.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password123'),
                'role' => UserRole::SuperAdmin,
                'phone' => '+966500000001',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->assignRole('super_admin');

        // 🏢 Manager
        $manager = User::updateOrCreate(
            ['email' => 'manager@neighborhub.com'],
            [
                'name' => 'Manager User',
                'password' => Hash::make('password123'),
                'role' => UserRole::Manager,
                'phone' => '+966500000002',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $manager->assignRole('manager');

        // 🏠 Resident
        $resident = User::updateOrCreate(
            ['email' => 'resident@neighborhub.com'],
            [
                'name' => 'Resident User',
                'password' => Hash::make('password123'),
                'role' => UserRole::Resident,
                'phone' => '+966500000003',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $resident->assignRole('resident');

        // 🔧 Provider
        $provider = User::updateOrCreate(
            ['email' => 'provider@neighborhub.com'],
            [
                'name' => 'Provider User',
                'password' => Hash::make('password123'),
                'role' => UserRole::Provider,
                'phone' => '+966500000004',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $provider->assignRole('provider');

        // ============================================================
        // 5. LOG SEEDING RESULTS
        // ============================================================

        $this->command->info('✅ Permissions seeded: ' . Permission::count());
        $this->command->info('✅ Roles seeded: ' . Role::count());
        $this->command->info('✅ Users seeded: ' . User::count());


    }
}
