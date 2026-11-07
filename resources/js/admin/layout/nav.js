/** Sidebar entries; each shows only when the user holds its permission. Also feeds the search's "Go to" list. */
export const NAV = [
    { to: '/', label: 'Overview', icon: 'grid', permission: 'dashboard.view', end: true },
    { to: '/enquiries', label: 'Enquiries', icon: 'inbox', permission: 'enquiries.view' },
    { to: '/subscribers', label: 'Subscribers', icon: 'mail', permission: 'subscribers.view' },
    { to: '/careers/openings', label: 'Job openings', icon: 'spark', permission: 'careers.view' },
    { to: '/careers/applications', label: 'Applications', icon: 'users', permission: 'careers.view' },
    { section: 'Billing' },
    { to: '/billing/clients', label: 'Clients', icon: 'building', permission: 'billing.view' },
    { to: '/billing/quotes', label: 'Quotes', icon: 'edit', permission: 'billing.view' },
    { to: '/billing/invoices', label: 'Invoices', icon: 'file', permission: 'billing.view' },
    { to: '/billing/settings', label: 'Billing settings', icon: 'key', permission: 'billing.view' },
    { section: 'Money' },
    { to: '/accounting', label: 'Money overview', icon: 'trend', permission: 'accounting.view', end: true },
    { to: '/accounting/expenses', label: 'Bills & spending', icon: 'receipt', permission: 'accounting.view' },
    { to: '/accounting/vendors', label: 'Vendors', icon: 'store', permission: 'accounting.view' },
    { to: '/accounting/categories', label: 'Expense categories', icon: 'layers', permission: 'accounting.view' },
    { to: '/accounting/reports', label: 'Reports', icon: 'scale', permission: 'accounting.view' },
    { to: '/accounting/settings', label: 'Money settings', icon: 'lock', permission: 'accounting.view' },
    { section: 'People' },
    { to: '/people', label: 'Employees', icon: 'users', permission: 'hr.view', end: true },
    { to: '/people/departments', label: 'Departments', icon: 'building', permission: 'hr.manage' },
    { to: '/people/job-titles', label: 'Job titles', icon: 'key', permission: 'hr.manage' },
    { to: '/people/allowances', label: 'Allowances', icon: 'wallet', permission: 'hr.manage' },
    { to: '/attendance', label: 'Attendance', icon: 'calendar', permission: 'hr.view' },
    { to: '/leave', label: 'Leave requests', icon: 'edit', permission: 'hr.view' },
    { to: '/reviews', label: 'Performance', icon: 'spark', permission: 'hr.view' },
    { to: '/payroll', label: 'Payroll', icon: 'wallet', permission: 'payroll.view' },
    { section: 'Access' },
    { to: '/users', label: 'Users', icon: 'users', permission: 'users.view' },
    { to: '/roles', label: 'Roles & permissions', icon: 'shield', permission: 'roles.view' },
    { to: '/activity', label: 'Activity log', icon: 'activity', permission: 'activity.view' },
];

/** The entries the signed-in user may open, with section headings kept only when they have something under them. */
export function visibleNav(can) {
    return NAV.filter((item, i) => {
        if (!item.section) return can(item.permission);
        return NAV.slice(i + 1).some((next) => !next.section && can(next.permission));
    });
}
