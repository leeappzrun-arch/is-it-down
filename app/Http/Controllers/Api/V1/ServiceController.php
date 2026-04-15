<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpsertServiceRequest;
use App\Http\Resources\V1\ServiceResource;
use App\Models\Service;
use App\Support\Services\ServiceData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    /**
     * Display a listing of services.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([Service::STATUS_UP, Service::STATUS_DOWN, 'pending'])],
            'service_group_id' => ['nullable', 'integer', Rule::exists('service_groups', 'id')],
            'recipient_group_id' => ['nullable', 'integer', Rule::exists('recipient_groups', 'id')],
            'recipient_id' => ['nullable', 'integer', Rule::exists('recipients', 'id')],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? null;
        $serviceGroupId = $validated['service_group_id'] ?? null;
        $recipientGroupId = $validated['recipient_group_id'] ?? null;
        $recipientId = $validated['recipient_id'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = Service::query()
            ->with([
                'groups:id,name',
                'recipientGroups:id,name',
                'recipients:id,name,endpoint',
                'currentDowntime',
            ])
            ->orderBy('name')
            ->orderBy('url')
            ->when($search !== null && $search !== '', function ($serviceQuery) use ($search): void {
                $serviceQuery->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('url', 'like', '%'.$search.'%')
                        ->orWhere('expect_value', 'like', '%'.$search.'%')
                        ->orWhere('monitoring_method', 'like', '%'.$search.'%')
                        ->orWhere('additional_headers', 'like', '%'.$search.'%')
                        ->orWhere('current_status', 'like', '%'.$search.'%')
                        ->orWhereHas('groups', function ($groupQuery) use ($search): void {
                            $groupQuery->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('recipientGroups', function ($groupQuery) use ($search): void {
                            $groupQuery->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('recipients', function ($recipientQuery) use ($search): void {
                            $recipientQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('endpoint', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($status !== null, function ($serviceQuery) use ($status): void {
                if ($status === 'pending') {
                    $serviceQuery->whereNull('current_status');

                    return;
                }

                $serviceQuery->where('current_status', $status);
            })
            ->when($serviceGroupId !== null, function ($serviceQuery) use ($serviceGroupId): void {
                $serviceQuery->whereHas('groups', function ($groupQuery) use ($serviceGroupId): void {
                    $groupQuery->whereKey($serviceGroupId);
                });
            })
            ->when($recipientGroupId !== null, function ($serviceQuery) use ($recipientGroupId): void {
                $serviceQuery->whereHas('recipientGroups', function ($groupQuery) use ($recipientGroupId): void {
                    $groupQuery->whereKey($recipientGroupId);
                });
            })
            ->when($recipientId !== null, function ($serviceQuery) use ($recipientId): void {
                $serviceQuery->whereHas('recipients', function ($recipientQuery) use ($recipientId): void {
                    $recipientQuery->whereKey($recipientId);
                });
            });

        return ServiceResource::collection(
            $query->paginate($perPage)->withQueryString()
        );
    }

    /**
     * Store a newly created service.
     */
    public function store(UpsertServiceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $service = Service::query()->create(
            ServiceData::payload($validated)
        );

        $service->groups()->sync($validated['selectedServiceGroupIds'] ?? []);
        $service->recipientGroups()->sync($validated['selectedRecipientGroupIds'] ?? []);
        $service->recipients()->sync($validated['selectedRecipientIds'] ?? []);

        return (new ServiceResource(
            $service->load(['groups:id,name', 'recipientGroups:id,name', 'recipients:id,name,endpoint', 'currentDowntime', 'downtimes'])
        ))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified service.
     */
    public function show(Service $service): ServiceResource
    {
        return new ServiceResource(
            $service->load(['groups:id,name', 'recipientGroups:id,name', 'recipients:id,name,endpoint', 'currentDowntime', 'downtimes'])
        );
    }

    /**
     * Update the specified service.
     */
    public function update(UpsertServiceRequest $request, Service $service): ServiceResource
    {
        $validated = $request->validated();

        $service->update(
            ServiceData::payload($validated)
        );

        $service->groups()->sync($validated['selectedServiceGroupIds'] ?? []);
        $service->recipientGroups()->sync($validated['selectedRecipientGroupIds'] ?? []);
        $service->recipients()->sync($validated['selectedRecipientIds'] ?? []);

        return new ServiceResource(
            $service->load(['groups:id,name', 'recipientGroups:id,name', 'recipients:id,name,endpoint', 'currentDowntime', 'downtimes'])
        );
    }

    /**
     * Remove the specified service.
     */
    public function destroy(Service $service): Response
    {
        $service->delete();

        return response()->noContent();
    }
}
