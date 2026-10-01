<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\TripRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TripRequestResource;
use App\Models\TripRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TripRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(TripRequestStatus::class)],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $requests = TripRequest::query()
            ->with('user')
            ->withCount('matchedTrips')
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('search'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->fromPlace($term)
                ->orWhere(fn ($q) => $q->toPlace($term))))
            ->latest('id')
            ->paginate($this->perPage(20));

        return $this->paginated($requests, __('messages.ok'), TripRequestResource::class);
    }
}
