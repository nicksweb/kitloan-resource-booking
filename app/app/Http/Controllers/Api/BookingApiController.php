<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BookingConflictException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Booking\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BookingApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Booking::query()->with(['resourcePool', 'location', 'bookingType']);

        if (! $request->user()->hasAnyRole(['administrator', 'it_operator'])) {
            $query->where('booked_by_user_id', $request->user()->id);
        }

        return response()->json($query->orderByDesc('start_at')->paginate(25));
    }

    public function show(Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        return response()->json($booking->load(['items.allocations.resource', 'students', 'location', 'bookingType', 'resourcePool']));
    }

    public function store(Request $request, BookingService $bookingService): JsonResponse
    {
        $this->authorize('create', Booking::class);

        $data = $request->validate([
            'resource_pool_id' => ['required', 'exists:resource_pools,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'booking_type_id' => ['nullable', 'exists:booking_types,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'quantity' => ['required_without:resource_ids', 'integer', 'min:1'],
            'resource_ids' => ['required_without:quantity', 'array'],
        ]);

        try {
            $booking = $bookingService->create([
                'resource_pool_id' => $data['resource_pool_id'],
                'location_id' => $data['location_id'] ?? null,
                'booking_type_id' => $data['booking_type_id'] ?? null,
                'start_at' => Carbon::parse($data['start_at']),
                'end_at' => Carbon::parse($data['end_at']),
                'notes' => $data['notes'] ?? null,
                'students' => [],
                'items' => [[
                    'resource_pool_id' => $data['resource_pool_id'],
                    'quantity' => $data['quantity'] ?? count($data['resource_ids'] ?? []),
                    'resource_ids' => $data['resource_ids'] ?? null,
                ]],
            ], $request->user(), $request->user());
        } catch (BookingConflictException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($booking, 201);
    }
}
