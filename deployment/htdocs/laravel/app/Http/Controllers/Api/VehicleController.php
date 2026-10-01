<?php

namespace App\Http\Controllers\Api;

use App\Enums\TripStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\VehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vehicles = $request->user()->vehicles()->withCount('trips')->latest()->get();

        return $this->success(VehicleResource::collection($vehicles), __('messages.ok'));
    }

    public function store(VehicleRequest $request): JsonResponse
    {
        $this->authorize('create', Vehicle::class);

        $vehicle = $request->user()->vehicles()->create($request->validated());

        return $this->created(new VehicleResource($vehicle), __('messages.vehicle_created'));
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $vehicle->update($request->validated());

        return $this->updated(new VehicleResource($vehicle), __('messages.vehicle_updated'));
    }

    /** Soft delete; refused while the vehicle is assigned to upcoming trips. */
    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('delete', $vehicle);

        $inUse = $vehicle->trips()
            ->whereIn('status', [TripStatus::Draft, TripStatus::Published, TripStatus::Full, TripStatus::Started])
            ->exists();
        if ($inUse) {
            throw BusinessRuleException::make('vehicle_in_use', 409);
        }

        $vehicle->delete();

        return $this->deleted(__('messages.vehicle_deleted'));
    }
}
