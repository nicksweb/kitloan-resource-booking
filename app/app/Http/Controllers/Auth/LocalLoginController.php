<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LocalLoginRequest;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class LocalLoginController extends Controller
{
    public function show(): View
    {
        abort_unless(config('auth.local_login.enabled'), 404);

        return view('auth.local-login');
    }

    public function login(LocalLoginRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless(config('auth.local_login.enabled'), 404);

        $request->authenticate($auditLogger);
        $request->session()->regenerate();

        return redirect()->route('home');
    }
}
