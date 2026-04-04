<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpsertServiceGroupRequest;
use App\Http\Resources\V1\ServiceGroupResource;
use App\Models\ServiceGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ServiceGroupController extends Controller
{
    /**
     * Display a listing of service groups.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'recipient_group_id' => ['nullable', 'integer', Rule::exists('recipient_groups', 'id')],
            'recipient_id' => ['nullable', 'integer', Rule::exists('recipients', 'id')],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = $validated['search'] ?? null;
        $serviceId = $validated['service_id'] ?? null;
        $recipientGroupId = $validated['recipient_group_id'] ?? null;
        $recipientId = $validated['recipient_id'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = ServiceGroup::query()
            ->withCount(['services', 'recipients', 'recipientGroups'])
            ->orderBy('name')
            ->when($search !== null && $search !== '', function ($groupQuery) use ($search): void {
                $groupQuery->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhereHas('services', function ($serviceQuery) use ($search): void {
                            $serviceQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('url', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('recipientGroups', function ($recipientGroupQuery) use ($search): void {
                            $recipientGroupQuery->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('recipients', function ($recipientQuery) use ($search): void {
                            $recipientQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('endpoint', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($serviceId !== null, function ($groupQuery) use ($serviceId): void {
                $groupQuery->whereHas('services', function ($serviceQuery) use ($serviceId): void {
                    $serviceQuery->whereKey($serviceId);
                });
            })
            ->when($recipientGroupId !== null, function ($groupQuery) use ($recipientGroupId): void {
                $groupQuery->whereHas('recipientGroups', function ($recipientGroupQuery) use ($recipientGroupId): void {
                    $recipientGroupQuery->whereKey($recipientGroupId);
                });
            })
            ->when($recipientId !== null, function ($groupQuery) use ($recipientId): void {
                $groupQuery->whereHas('recipients', function ($recipientQuery) use ($recipientId): void {
                    $recipientQuery->whereKey($recipientId);
                });
            });

        return ServiceGroupResource::collection(
            $query->paginate($perPage)->withQueryString()
        );
    }

    /**
     * Store a newly created service group.
     */
    public function store(UpsertServiceGroupRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $serviceGroup = ServiceGroup::query()->create([
            'name' => trim((string) $validated['groupName']),
        ]);

        $serviceGroup->recipientGroups()->sync($validated['groupSelectedRecipientGroupIds'] ?? []);
        $serviceGroup->recipients()->sync($validated['groupSelectedRecipientIds'] ?? []);

        return (new ServiceGroupResource(
            $serviceGroup->loadCount(['services', 'recipients', 'recipientGroups'])
        ))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified service group.
     */
    public function show(ServiceGroup $serviceGroup): ServiceGroupResource
    {
        return new ServiceGroupResource(
            $serviceGroup
                ->load([
                    'services:id,name,url',
                    'recipientGroups:id,name',
                    'recipients:id,name,endpoint',
                ])
                ->loadCount(['services', 'recipients', 'recipientGroups'])
        );
    }

    /**
     * Update the specified service group.
     */
    public function update(UpsertServiceGroupRequest $request, ServiceGroup $serviceGroup): ServiceGroupResource
    {
        $validated = $request->validated();

        $serviceGroup->update([
            'name' => trim((string) $validated['groupName']),
        ]);

        $serviceGroup->recipientGroups()->sync($validated['groupSelectedRecipientGroupIds'] ?? []);
        $serviceGroup->recipients()->sync($validated['groupSelectedRecipientIds'] ?? []);

        return new ServiceGroupResource(
            $serviceGroup->loadCount(['services', 'recipients', 'recipientGroups'])
        );
    }

    /**
     * Remove the specified service group.
     */
    public function destroy(ServiceGroup $serviceGroup): Response
    {
        $serviceGroup->delete();

        return response()->noContent();
    }
}
