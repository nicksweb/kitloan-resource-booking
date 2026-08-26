<?php

use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\SnipeItIntegrationController;
use App\Http\Controllers\Auth\LocalLoginController;
use App\Http\Controllers\Auth\OidcController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\BookingApprovalController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PublicBookingViewController;
use App\Livewire\Admin\AuditLogIndex;
use App\Livewire\Admin\BookingTypesIndex;
use App\Livewire\Admin\LocationsIndex;
use App\Livewire\Admin\MessageTemplatesIndex;
use App\Livewire\Admin\ReportsIndex;
use App\Livewire\Admin\ResourcePoolResources;
use App\Livewire\Admin\ResourcePoolsIndex;
use App\Livewire\Admin\SchedulePeriodsIndex;
use App\Livewire\Admin\SettingsIndex;
use App\Livewire\Admin\UsersIndex;
use App\Livewire\AllBookings;
use App\Livewire\Booking\BookingEdit;
use App\Livewire\Booking\BookingWizard;
use App\Livewire\BookingDetail;
use App\Livewire\It\Dashboard as ItDashboard;
use App\Livewire\It\LogisticsView;
use App\Livewire\MyBookings;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

// The "magic link" from booking notification emails — signed, no auth
// middleware, read-only. See PublicBookingViewController's docblock.
Route::get('/bookings/{booking:reference}/view', [PublicBookingViewController::class, 'show'])
    ->name('bookings.public-view')->middleware('signed');

Route::middleware('throttle:20,1')->group(function () {
    Route::get('/auth/login', [OidcController::class, 'showLogin'])->name('auth.login');
    Route::get('/auth/redirect', [OidcController::class, 'redirect'])->name('auth.redirect');
    Route::get('/auth/silent', [OidcController::class, 'silent'])->name('auth.silent');
    Route::get('/auth/callback', [OidcController::class, 'callback'])->name('auth.callback');
});
Route::post('/auth/logout', [OidcController::class, 'logout'])->name('auth.logout');

Route::middleware('throttle:10,1')->group(function () {
    Route::get('/auth/local', [LocalLoginController::class, 'show'])->name('auth.local.show');
    Route::post('/auth/local', [LocalLoginController::class, 'login'])->name('auth.local');

    // Second-factor challenge: reached with a correct password but not yet
    // logged in — the pending user is held in the session, not the auth guard.
    Route::get('/auth/two-factor/challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/auth/two-factor/challenge', [TwoFactorController::class, 'verifyChallenge'])->name('two-factor.challenge.verify');
});

Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('home');

    Route::get('/auth/two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/auth/two-factor/setup', [TwoFactorController::class, 'confirmSetup'])->name('two-factor.setup.confirm');

    Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])->name('impersonation.stop');

    Route::get('/book/{resourcePool:slug}', BookingWizard::class)->name('booking.wizard');
    Route::get('/bookings/{booking:reference}/edit', BookingEdit::class)->name('bookings.edit');

    Route::get('/my-bookings', MyBookings::class)->name('bookings.mine');
    Route::get('/bookings', AllBookings::class)->name('bookings.index');
    Route::get('/bookings/{booking:reference}', BookingDetail::class)->name('bookings.show');

    Route::get('/bookings/{booking:reference}/approve', [BookingApprovalController::class, 'approve'])
        ->name('bookings.approve')->middleware('signed');
    Route::get('/bookings/{booking:reference}/reject', [BookingApprovalController::class, 'showReject'])
        ->name('bookings.reject.show')->middleware('signed');
    Route::post('/bookings/{booking:reference}/reject', [BookingApprovalController::class, 'reject'])
        ->name('bookings.reject');

    Route::middleware('can:operate-bookings')->prefix('it')->name('it.')->group(function () {
        Route::get('/dashboard', ItDashboard::class)->name('dashboard');
        Route::get('/logistics', LogisticsView::class)->name('logistics');
    });

    Route::middleware('can:manage-catalog')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/resource-pools', ResourcePoolsIndex::class)->name('resource-pools.index');
        Route::get('/resource-pools/{resourcePool:slug}/resources', ResourcePoolResources::class)->name('resource-pools.resources');
        Route::get('/locations', LocationsIndex::class)->name('locations.index');
        Route::get('/booking-types', BookingTypesIndex::class)->name('booking-types.index');
        Route::get('/periods', SchedulePeriodsIndex::class)->name('periods.index');

        Route::get('/integrations/snipeit', [SnipeItIntegrationController::class, 'show'])->name('integrations.snipeit');
        Route::post('/integrations/snipeit/test', [SnipeItIntegrationController::class, 'test'])->name('integrations.snipeit.test');
        Route::post('/integrations/snipeit/sync', [SnipeItIntegrationController::class, 'sync'])->name('integrations.snipeit.sync');
    });

    Route::middleware('can:manage-users')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', UsersIndex::class)->name('users.index');
        Route::post('/users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('users.impersonate');
    });

    Route::middleware('can:manage-settings')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/settings', SettingsIndex::class)->name('settings.index');
        Route::get('/message-templates', MessageTemplatesIndex::class)->name('message-templates.index');
    });

    Route::middleware('can:view-audit-log')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/audit-log', AuditLogIndex::class)->name('audit-log.index');
    });

    Route::middleware('can:view-reports')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/reports', ReportsIndex::class)->name('reports.index');
    });
});
