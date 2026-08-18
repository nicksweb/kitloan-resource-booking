<?php

namespace App\Http\Controllers;

use App\Models\ResourcePool;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $pools = ResourcePool::enabled()->ordered()->get();

        $upcomingBookings = Auth::user()
            ->bookingsOwned()
            ->with(['resourcePool', 'location', 'items'])
            ->upcoming()
            ->orderBy('start_at')
            ->limit(5)
            ->get();

        return view('home', [
            'pools' => $pools,
            'upcomingBookings' => $upcomingBookings,
        ]);
    }
}
