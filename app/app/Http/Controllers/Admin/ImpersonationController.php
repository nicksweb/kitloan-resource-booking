<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\ImpersonationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function start(Request $request, User $user, ImpersonationManager $impersonation, AuditLogger $auditLogger): RedirectResponse
    {
        $admin = $request->user();

        abort_if($user->is($admin), 400, "You can't impersonate yourself.");
        abort_if($user->hasRole('administrator'), 403, 'Administrators cannot be impersonated.');
        abort_if($impersonation->isImpersonating(), 400, 'Already impersonating — stop first.');

        if (! $user->enabled) {
            return back()->with('error', "{$user->name}'s account is disabled.");
        }

        $impersonation->start($user, $admin);

        $auditLogger->log(
            'auth.impersonation_started',
            "{$admin->name} started impersonating {$user->name} ({$user->email})",
            $admin,
        );

        return redirect()->route('home')->with('success', "You are now signed in as {$user->name}.");
    }

    public function stop(ImpersonationManager $impersonation, AuditLogger $auditLogger): RedirectResponse
    {
        $impersonated = auth()->user();
        $admin = $impersonation->stop();

        if (! $admin) {
            return redirect()->route('home');
        }

        $auditLogger->log(
            'auth.impersonation_stopped',
            "{$admin->name} stopped impersonating {$impersonated?->name}",
            $admin,
        );

        return redirect()->route('admin.users.index')->with('success', 'Returned to your own account.');
    }
}
