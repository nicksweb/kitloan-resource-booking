<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResourcePool;
use App\Services\Booking\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ResourcePoolApiController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            ResourcePool::enabled()->ordered()->get(['id', 'name', 'slug', 'icon', 'allocation_mode'])
        );
    }

    public function availability(Request $request, ResourcePool $resourcePool, AvailabilityService $availability): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ]);

        $start = Carbon::parse($data['start']);
        $end = Carbon::parse($data['end']);

        if ($resourcePool->isQuantityTracked()) {
            return response()->json([
                'mode' => 'quantity',
                'available_quantity' => $availability->availableQuantity($resourcePool, $start, $end),
                'total_quantity' => $resourcePool->quantity_total,
            ]);
        }

        return response()->json([
            'mode' => 'individual',
            'available_resource_ids' => $availability->availableResourceIds($resourcePool, $start, $end)->values(),
        ]);
    }
}
