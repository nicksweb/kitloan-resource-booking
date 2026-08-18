<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Session-based "become this user" impersonation. While impersonating, the
 * session is genuinely authenticated as the target — they see exactly what
 * that user sees, with that user's permissions (not the admin's). The one
 * thing that's tracked separately is who the *real* actor is
 * ({@see actor()}), so actions taken while impersonating (e.g. creating a
 * booking) still record the true admin as the actor in the audit trail and
 * as created_by_user_id, while booked_by_user_id is the impersonated user.
 */
class ImpersonationManager
{
    private const SESSION_KEY = 'impersonator_id';

    public function start(User $target, User $admin): void
    {
        Session::put(self::SESSION_KEY, $admin->id);
        Auth::login($target);
    }

    /**
     * Ends impersonation and restores the original admin's session. Returns
     * the restored admin, or null if the session wasn't impersonating anyone.
     */
    public function stop(): ?User
    {
        $adminId = Session::pull(self::SESSION_KEY);

        if (! $adminId) {
            return null;
        }

        $admin = User::find($adminId);

        if ($admin) {
            Auth::login($admin);
        }

        return $admin;
    }

    public function isImpersonating(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    public function impersonator(): ?User
    {
        $id = Session::get(self::SESSION_KEY);

        return $id ? User::find($id) : null;
    }

    /**
     * The real actor for audit/attribution purposes — the impersonating
     * admin if impersonating, otherwise whoever is actually logged in.
     */
    public function actor(): ?User
    {
        return $this->isImpersonating() ? $this->impersonator() : Auth::user();
    }
}
