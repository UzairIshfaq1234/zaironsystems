<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/** The system Super Admin role plus a few sensible starter roles. Safe to run repeatedly. */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::updateOrCreate(['slug' => config('permissions.super_admin_role')], [
            'name' => 'Super Admin',
            'description' => 'Full access to everything. Cannot be edited or deleted.',
            'permissions' => [],
            'is_system' => true,
        ]);

        $starters = [
            'manager' => ['Manager', 'Runs day-to-day operations and the team.', [
                'dashboard.view', 'enquiries.view', 'enquiries.update', 'enquiries.delete',
                'subscribers.view', 'subscribers.export', 'users.view', 'activity.view',
                'billing.view', 'billing.manage', 'accounting.view', 'hr.view', 'hr.manage',
            ]],
            'sales' => ['Sales', 'Follows up on project enquiries.', ['dashboard.view', 'enquiries.view', 'enquiries.update']],
            'marketing' => ['Marketing', 'Manages the newsletter audience.', ['dashboard.view', 'subscribers.view', 'subscribers.export']],
            'accountant' => ['Accountant', 'Handles quotes, invoices, payments, expenses and the books.', [
                'dashboard.view', 'enquiries.view', 'billing.view', 'billing.manage', 'billing.delete', 'settings.manage',
                'accounting.view', 'accounting.manage', 'accounting.close', 'hr.view', 'payroll.view', 'payroll.manage',
            ]],
        ];

        foreach ($starters as $slug => [$name, $description, $permissions]) {
            Role::firstOrCreate(['slug' => $slug], compact('name', 'description', 'permissions'));
        }
    }
}
