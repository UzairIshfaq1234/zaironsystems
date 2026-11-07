<?php

use App\Http\Controllers\CareerController;
use App\Http\Controllers\DocumentViewController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SubscriberController;
use App\Http\Middleware\RejectHoneypot;
use Illuminate\Support\Facades\Route;

// Admin portal (separate React app + API).
require __DIR__.'/admin.php';
require __DIR__.'/portal.php';

// Open roles for the careers page (public, read-only).
Route::get('api/careers/openings', [CareerController::class, 'openings'])->name('careers.openings');

// Form endpoints for the React app. Same-origin only: CSRF-protected (web group), rate-limited, honeypot-guarded.
Route::prefix('api')->middleware(['throttle:forms', RejectHoneypot::class])->group(function () {
    Route::post('contact', [EnquiryController::class, 'store'])->name('enquiries.store');
    Route::post('newsletter', [SubscriberController::class, 'store'])->name('subscribers.store');
    Route::post('careers/apply', [CareerController::class, 'apply'])->name('careers.apply');
});

// Client-facing quotes and invoices (unguessable token links from emails).
Route::middleware('throttle:billing')->group(function () {
    Route::get('billing/{kind}/{token}', [DocumentViewController::class, 'show'])->whereIn('kind', ['quote', 'invoice'])->where('token', '[A-Za-z0-9]{48}')->name('billing.show');
    Route::get('billing/{kind}/{token}/pdf', [DocumentViewController::class, 'pdf'])->whereIn('kind', ['quote', 'invoice'])->where('token', '[A-Za-z0-9]{48}')->name('billing.pdf');
    Route::post('billing/quote/{token}/respond', [DocumentViewController::class, 'respond'])->where('token', '[A-Za-z0-9]{48}')->name('billing.respond');
});

Route::get('sitemap.xml', [PageController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [PageController::class, 'robots'])->name('robots');

// Everything else is a page of the public React app (React Router renders it).
Route::get('{path?}', [PageController::class, 'show'])
    ->where('path', '^(?!api(?:/|$)|admin(?:/|$)|portal(?:/|$)|billing(?:/|$)).*$')
    ->name('page');
