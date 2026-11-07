<?php

/*
| Every permission the admin portal understands, grouped for the role editor.
| Roles store a subset of these keys; routes check them with ->can('key').
| The "super-admin" role bypasses all checks and cannot be edited or deleted.
*/

return [

    'super_admin_role' => 'super-admin',

    'groups' => [
        'Dashboard' => [
            'dashboard.view' => 'View the overview and statistics',
        ],
        'Enquiries' => [
            'enquiries.view' => 'View enquiries and download attachments',
            'enquiries.update' => 'Change status and add internal notes',
            'enquiries.delete' => 'Delete enquiries',
        ],
        'Billing' => [
            'billing.view' => 'View clients, quotes, invoices and revenue',
            'billing.manage' => 'Create, edit and send quotes and invoices; record payments',
            'billing.delete' => 'Delete drafts and void invoices',
            'settings.manage' => 'Edit company and billing settings',
        ],
        'Accounting' => [
            'accounting.view' => 'View the ledger, expenses and financial reports',
            'accounting.manage' => 'Record expenses and vendors, post journal entries, edit the chart of accounts',
            'accounting.close' => 'Change accounting settings and close or reopen periods',
        ],
        'Careers' => [
            'careers.view' => 'View job openings and applications, and download CVs',
            'careers.manage' => 'Create, edit and close job openings; change application status and notes',
            'careers.delete' => 'Delete job openings and applications',
        ],
        'Subscribers' => [
            'subscribers.view' => 'View newsletter subscribers',
            'subscribers.export' => 'Export subscribers as CSV',
            'subscribers.delete' => 'Remove subscribers',
        ],
        'Users' => [
            'users.view' => 'View admin users',
            'users.create' => 'Invite new users',
            'users.update' => 'Edit users, roles and access',
            'users.delete' => 'Delete users',
        ],
        'Roles' => [
            'roles.view' => 'View roles and their permissions',
            'roles.create' => 'Create roles',
            'roles.update' => 'Edit roles and their permissions',
            'roles.delete' => 'Delete roles',
        ],
        'Activity' => [
            'activity.view' => 'View the activity log',
        ],
        'People' => [
            'hr.view' => 'View employees, attendance, leave and reviews',
            'hr.manage' => 'Add employees, approve leave, record attendance and write reviews',
        ],
        'Payroll' => [
            'payroll.view' => 'View salaries and salary slips',
            'payroll.manage' => 'Run payroll and post it to the books',
        ],
    ],

];
