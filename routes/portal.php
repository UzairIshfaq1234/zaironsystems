<?php

use App\Http\Controllers\Portal\AuthController;
use App\Http\Controllers\Portal\NotificationController;
use App\Http\Controllers\Portal\SelfServiceController;
use App\Http\Controllers\Portal\ShellController;
use App\Http\Middleware\AdminResponseHeaders;
use App\Http\Middleware\EnsureEmployeeIsActive;
use Illuminate\Support\Facades\Route;

/*
| Employee portal: a separate React app at /portal with its own JSON API at /portal/api, signed in with
| the employee guard. Employees can only reach their own records (see SelfServiceController).
*/

Route::prefix('portal')->middleware(AdminResponseHeaders::class)->group(function () {
    Route::prefix('api')->group(function () {
        Route::middleware('throttle:portal-auth')->group(function () {
            Route::post('login', [AuthController::class, 'login']);
            Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
            Route::post('reset-password', [AuthController::class, 'resetPassword']);
        });

        Route::middleware(['auth:employee', EnsureEmployeeIsActive::class])->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('home', [SelfServiceController::class, 'home']);
            Route::post('attendance/check-in', [SelfServiceController::class, 'checkIn']);
            Route::post('attendance/check-out', [SelfServiceController::class, 'checkOut']);
            Route::get('attendance', [SelfServiceController::class, 'attendance']);
            Route::get('leave', [SelfServiceController::class, 'leave']);
            Route::post('leave', [SelfServiceController::class, 'requestLeave']);
            Route::post('leave/{leave}/cancel', [SelfServiceController::class, 'cancelLeave']);
            Route::get('slips', [SelfServiceController::class, 'slips']);
            Route::get('slips/{slip}/pdf', [SelfServiceController::class, 'slipPdf']);
            Route::get('reviews', [SelfServiceController::class, 'reviews']);
            Route::post('reviews/{review}/reply', [SelfServiceController::class, 'replyToReview']);
            Route::put('password', [SelfServiceController::class, 'changePassword']);
            Route::get('notifications', [NotificationController::class, 'index']);
            Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
            Route::post('notifications/{id}/read', [NotificationController::class, 'read']);
        });

        Route::any('{any}', fn () => abort(404))->where('any', '.*');
    });

    Route::get('{path?}', ShellController::class)->where('path', '.*')->name('portal');
});
