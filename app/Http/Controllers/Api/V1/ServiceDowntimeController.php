<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ServiceDowntimeResource;
use App\Models\Service;
use App\Models\ServiceDowntime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ServiceDowntimeController extends Controller
{
    /**
     * Display a listing of downtime incidents for a service.
     */
    public function index(Request $request, Service $service): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['ongoing', 'resolved'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = $validated['status'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = $service->downtimes()
            ->with('service:id,name,url')
            ->when($status === 'ongoing', fn ($downtimeQuery) => $downtimeQuery->whereNull('ended_at'))
            ->when($status === 'resolved', fn ($downtimeQuery) => $downtimeQuery->whereNotNull('ended_at'));

        return ServiceDowntimeResource::collection(
            $query->paginate($perPage)->withQueryString()
        );
    }

    /**
     * Display a single downtime incident.
     */
    public function show(ServiceDowntime $serviceDowntime): ServiceDowntimeResource
    {
        return new ServiceDowntimeResource(
            $serviceDowntime->load('service:id,name,url')
        );
    }
}
