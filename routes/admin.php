<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AccountingController;
use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EnquiryController;
use App\Http\Controllers\Admin\ExpenseCategoryController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\HrListController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\JobApplicationController;
use App\Http\Controllers\Admin\JobOpeningController;
use App\Http\Controllers\Admin\JournalController;
use App\Http\Controllers\Admin\LeaveController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OverviewController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\QuoteController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ShellController;
use App\Http\Controllers\Admin\SubscriberController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Middleware\AdminResponseHeaders;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

/*
| Admin portal: a separate React app at /admin with its own JSON API at /admin/api.
| Every API route except sign-in/reset needs an active, signed-in user, and each
| feature checks its permission with ->can('…') (see config/permissions.php).
*/

Route::prefix('admin')->middleware(AdminResponseHeaders::class)->group(function () {
    Route::prefix('api')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::middleware('throttle:admin-auth')->group(function () {
            Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
            Route::post('reset-password', [AuthController::class, 'resetPassword']);
        });

        Route::middleware(['auth:web', EnsureUserIsActive::class])->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::put('profile', [ProfileController::class, 'update']);
            Route::put('profile/password', [ProfileController::class, 'password']);

            // Top bar: search (results filtered by permission) and the user's own notifications.
            Route::get('search', SearchController::class)->middleware('throttle:60,1');
            Route::get('notifications', [NotificationController::class, 'index']);
            Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
            Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->whereUuid('id');

            Route::get('overview', OverviewController::class)->can('dashboard.view');

            Route::get('enquiries', [EnquiryController::class, 'index'])->can('enquiries.view');
            Route::get('enquiries/{enquiry}', [EnquiryController::class, 'show'])->can('enquiries.view');
            Route::get('enquiries/{enquiry}/brief', [EnquiryController::class, 'brief'])->can('enquiries.view');
            Route::patch('enquiries/{enquiry}', [EnquiryController::class, 'update'])->can('enquiries.update');
            Route::delete('enquiries/{enquiry}', [EnquiryController::class, 'destroy'])->can('enquiries.delete');

            Route::get('careers/openings', [JobOpeningController::class, 'index'])->can('careers.view');
            Route::post('careers/openings', [JobOpeningController::class, 'store'])->can('careers.manage');
            Route::put('careers/openings/{jobOpening}', [JobOpeningController::class, 'update'])->can('careers.manage');
            Route::delete('careers/openings/{jobOpening}', [JobOpeningController::class, 'destroy'])->can('careers.delete');

            Route::get('careers/applications', [JobApplicationController::class, 'index'])->can('careers.view');
            Route::get('careers/applications/{jobApplication}', [JobApplicationController::class, 'show'])->can('careers.view');
            Route::get('careers/applications/{jobApplication}/cv', [JobApplicationController::class, 'cv'])->can('careers.view');
            Route::patch('careers/applications/{jobApplication}', [JobApplicationController::class, 'update'])->can('careers.manage');
            Route::delete('careers/applications/{jobApplication}', [JobApplicationController::class, 'destroy'])->can('careers.delete');

            Route::get('subscribers', [SubscriberController::class, 'index'])->can('subscribers.view');
            Route::get('subscribers/export', [SubscriberController::class, 'export'])->can('subscribers.export');
            Route::delete('subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->can('subscribers.delete');

            Route::get('users', [UserController::class, 'index'])->can('users.view');
            Route::post('users', [UserController::class, 'store'])->can('users.create');
            Route::patch('users/{user}', [UserController::class, 'update'])->can('users.update');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->can('users.delete');
            Route::post('users/{user}/resend-invite', [UserController::class, 'resendInvite'])->can('users.create');

            // Also readable by anyone who invites/edits users, so they can pick roles (checked in the controller).
            Route::get('roles', [RoleController::class, 'index']);
            Route::get('roles/permissions', [RoleController::class, 'permissions'])->can('roles.view');
            Route::post('roles', [RoleController::class, 'store'])->can('roles.create');
            Route::put('roles/{role}', [RoleController::class, 'update'])->can('roles.update');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->can('roles.delete');

            Route::get('activity', [ActivityController::class, 'index'])->can('activity.view');

            // Billing
            Route::get('settings/billing', [SettingsController::class, 'billing'])->can('billing.view');
            Route::put('settings/billing', [SettingsController::class, 'updateBilling'])->can('settings.manage');

            Route::get('clients', [ClientController::class, 'index'])->can('billing.view');
            Route::get('clients/{client}', [ClientController::class, 'show'])->can('billing.view');
            Route::post('clients', [ClientController::class, 'store'])->can('billing.manage');
            Route::put('clients/{client}', [ClientController::class, 'update'])->can('billing.manage');
            Route::delete('clients/{client}', [ClientController::class, 'destroy'])->can('billing.delete');
            Route::post('enquiries/{enquiry}/client', [ClientController::class, 'fromEnquiry'])->can('billing.manage');

            Route::get('quotes', [QuoteController::class, 'index'])->can('billing.view');
            Route::get('quotes/{quote}', [QuoteController::class, 'show'])->can('billing.view');
            Route::get('quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->can('billing.view');
            Route::middleware('can:billing.manage')->group(function () {
                Route::post('quotes', [QuoteController::class, 'store']);
                Route::put('quotes/{quote}', [QuoteController::class, 'update']);
                Route::post('quotes/{quote}/send', [QuoteController::class, 'send']);
                Route::post('quotes/{quote}/respond', [QuoteController::class, 'respond']);
                Route::post('quotes/{quote}/convert', [QuoteController::class, 'convert']);
            });
            Route::delete('quotes/{quote}', [QuoteController::class, 'destroy'])->can('billing.delete');

            Route::get('invoices', [InvoiceController::class, 'index'])->can('billing.view');
            Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->can('billing.view');
            Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->can('billing.view');
            Route::middleware('can:billing.manage')->group(function () {
                Route::post('invoices', [InvoiceController::class, 'store']);
                Route::put('invoices/{invoice}', [InvoiceController::class, 'update']);
                Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send']);
                Route::post('invoices/{invoice}/remind', [InvoiceController::class, 'remind']);
                Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'addPayment']);
                Route::delete('invoices/{invoice}/payments/{payment}', [InvoiceController::class, 'removePayment']);
            });
            Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->can('billing.delete');
            Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->can('billing.delete');

            // People: employees, attendance, leave, reviews
            Route::middleware('can:hr.view')->group(function () {
                Route::get('hr/employees', [EmployeeController::class, 'index']);
                Route::get('hr/employees/{employee}', [EmployeeController::class, 'show']);
                Route::get('hr/attendance', [AttendanceController::class, 'index']);
                Route::get('hr/attendance/report', [AttendanceController::class, 'report']);
                Route::get('hr/leave', [LeaveController::class, 'index']);
                Route::get('hr/reviews', [ReviewController::class, 'index']);
                Route::get('hr/lists', [HrListController::class, 'index']);
            });
            Route::middleware('can:hr.manage')->group(function () {
                Route::post('hr/employees', [EmployeeController::class, 'store']);
                Route::put('hr/employees/{employee}', [EmployeeController::class, 'update']);
                Route::post('hr/employees/{employee}/invite', [EmployeeController::class, 'invite']);
                Route::post('hr/attendance', [AttendanceController::class, 'store']);
                Route::post('hr/leave/{leave}/decide', [LeaveController::class, 'decide']);
                Route::post('hr/reviews', [ReviewController::class, 'store']);
                Route::post('hr/lists/{list}', [HrListController::class, 'store'])->whereIn('list', ['departments', 'job-titles', 'allowance-types']);
                Route::put('hr/lists/{list}/{id}', [HrListController::class, 'update'])->whereIn('list', ['departments', 'job-titles', 'allowance-types'])->whereNumber('id');
                Route::delete('hr/lists/{list}/{id}', [HrListController::class, 'destroy'])->whereIn('list', ['departments', 'job-titles', 'allowance-types'])->whereNumber('id');
            });

            // Payroll: salaries and slips (salary figures need payroll.view; running and posting need payroll.manage)
            Route::middleware('can:payroll.view')->group(function () {
                Route::get('payroll/runs', [PayrollController::class, 'index']);
                Route::get('payroll/runs/{run}', [PayrollController::class, 'show']);
                Route::get('payroll/slips/{slip}/pdf', [PayrollController::class, 'slipPdf']);
            });
            Route::middleware('can:payroll.manage')->group(function () {
                Route::post('payroll/runs', [PayrollController::class, 'store']);
                Route::post('payroll/runs/{run}/generate', [PayrollController::class, 'generate']);
                Route::post('payroll/runs/{run}/post', [PayrollController::class, 'post']);
                Route::delete('payroll/runs/{run}', [PayrollController::class, 'destroy']);
            });
            // Accounting
            Route::prefix('accounting')->group(function () {
                Route::middleware('can:accounting.view')->group(function () {
                    Route::get('overview', [AccountingController::class, 'overview']);
                    Route::get('settings', [AccountingController::class, 'settings']);
                    Route::get('accounts', [AccountController::class, 'index']);
                    Route::get('accounts/{account}/ledger', [AccountController::class, 'ledger']);
                    Route::get('journal', [JournalController::class, 'index']);
                    Route::get('journal/{entry}', [JournalController::class, 'show']);
                    Route::get('vendors', [VendorController::class, 'index']);
                    Route::get('categories', [ExpenseCategoryController::class, 'index']);
                    Route::get('expenses', [ExpenseController::class, 'index']);
                    Route::get('expenses/options', [ExpenseController::class, 'options']);
                    Route::get('expenses/{expense}', [ExpenseController::class, 'show']);
                    Route::get('expenses/{expense}/receipt', [ExpenseController::class, 'receipt']);
                    Route::get('reports/trial-balance', [ReportController::class, 'trialBalance']);
                    Route::get('reports/profit-and-loss', [ReportController::class, 'profitAndLoss']);
                    Route::get('reports/balance-sheet', [ReportController::class, 'balanceSheet']);
                    Route::get('reports/receivables', [ReportController::class, 'receivables']);
                    Route::get('reports/payables', [ReportController::class, 'payables']);
                });
                Route::middleware('can:accounting.manage')->group(function () {
                    Route::post('accounts', [AccountController::class, 'store']);
                    Route::put('accounts/{account}', [AccountController::class, 'update']);
                    Route::delete('accounts/{account}', [AccountController::class, 'destroy']);
                    Route::post('journal', [JournalController::class, 'store']);
                    Route::put('journal/{entry}', [JournalController::class, 'update']);
                    Route::delete('journal/{entry}', [JournalController::class, 'destroy']);
                    Route::post('categories', [ExpenseCategoryController::class, 'store']);
                    Route::put('categories/{account}', [ExpenseCategoryController::class, 'update']);
                    Route::post('vendors', [VendorController::class, 'store']);
                    Route::put('vendors/{vendor}', [VendorController::class, 'update']);
                    Route::delete('vendors/{vendor}', [VendorController::class, 'destroy']);
                    // POST for updates too: the form may carry a receipt file (multipart).
                    Route::post('expenses', [ExpenseController::class, 'store']);
                    Route::post('expenses/{expense}', [ExpenseController::class, 'update']);
                    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy']);
                });
                Route::put('settings', [AccountingController::class, 'updateSettings'])->can('accounting.close');
            });
        });

        Route::any('{any}', fn () => abort(404))->where('any', '.*');
    });

    Route::get('{path?}', ShellController::class)->where('path', '.*')->name('admin');
});
