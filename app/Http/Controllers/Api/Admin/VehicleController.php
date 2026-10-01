<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\TripStatus;
use App\Enums\VehicleType;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'vehicle_type' => ['nullable', Rule::enum(VehicleType::class)],
            'deleted' => ['nullable', 'boolean'],
        ]);

        $vehicles = Vehicle::query()
            ->with('user')
            ->withCount('trips')
            ->when($request->boolean('deleted'), fn ($q) => $q->onlyTrashed())
            ->when($request->input('vehicle_type'), fn ($q, $type) => $q->where('vehicle_type', $type))
            ->when($request->input('search'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('plate_number', 'like', "%{$term}%")
                ->orWhere('model', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($q) => $q->search($term))))
            ->latest('id')
            ->paginate($this->perPage(20));

        return $this->paginated($vehicles, __('messages.ok'), VehicleResource::class);
    }

    /** Remove a fake / inappropriate vehicle (soft delete, history is kept). */
    public function destroy(Vehicle $vehicle, ActivityLogger $activity): JsonResponse
    {
        $this->authorize('moderate', $vehicle);

        $inUse = $vehicle->trips()
            ->whereIn('status', [TripStatus::Draft, TripStatus::Published, TripStatus::Full, TripStatus::Started])
            ->exists();
        if ($inUse) {
            throw BusinessRuleException::make('vehicle_in_use', 409);
        }

        $vehicle->delete();
        $activity->log('vehicle.deleted', $vehicle, ['plate' => $vehicle->plate_number]);

        return $this->deleted(__('messages.vehicle_deleted'));
    }
}
