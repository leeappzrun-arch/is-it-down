<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpsertServiceTemplateRequest;
use App\Http\Resources\V1\ServiceTemplateResource;
use App\Models\ServiceTemplate;
use App\Support\Services\ServiceTemplateData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class ServiceTemplateController extends Controller
{
    /**
     * Display a listing of service templates.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'service_group_id' => ['nullable', 'integer', Rule::exists('service_groups', 'id')],
            'recipient_group_id' => ['nullable', 'integer', Rule::exists('recipient_groups', 'id')],
            'recipient_id' => ['nullable', 'integer', Rule::exists('recipients', 'id')],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = $validated['search'] ?? null;
        $serviceGroupId = $validated['service_group_id'] ?? null;
        $recipientGroupId = $validated['recipient_group_id'] ?? null;
        $recipientId = $validated['recipient_id'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 15);

        $templates = ServiceTemplate::query()
            ->orderBy('name')
            ->get()
            ->filter(function (ServiceTemplate $template) use ($search, $serviceGroupId, $recipientGroupId, $recipientId): bool {
                $configuration = $template->serviceConfiguration();

                if ($search !== null && $search !== '') {
                    $haystack = strtolower(implode(' ', array_filter([
                        $template->name,
                        $configuration['name'],
                        $template->intervalLabel(),
                        $template->expectSummary(),
                    ])));

                    if (! str_contains($haystack, strtolower($search))) {
                        return false;
                    }
                }

                if ($serviceGroupId !== null && ! in_array((int) $serviceGroupId, $configuration['service_group_ids'], true)) {
                    return false;
                }

                if ($recipientGroupId !== null && ! in_array((int) $recipientGroupId, $configuration['recipient_group_ids'], true)) {
                    return false;
                }

                if ($recipientId !== null && ! in_array((int) $recipientId, $configuration['recipient_ids'], true)) {
                    return false;
                }

                return true;
            })
            ->values();

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $paginated = new LengthAwarePaginator(
            $templates->forPage($currentPage, $perPage)->values(),
            $templates->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return ServiceTemplateResource::collection(
            $paginated
        );
    }

    /**
     * Store a newly created service template.
     */
    public function store(UpsertServiceTemplateRequest $request): JsonResponse
    {
        $serviceTemplate = ServiceTemplate::query()->create(
            ServiceTemplateData::payload($request->validated())
        );

        return (new ServiceTemplateResource($serviceTemplate))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified service template.
     */
    public function show(ServiceTemplate $serviceTemplate): ServiceTemplateResource
    {
        return new ServiceTemplateResource($serviceTemplate);
    }

    /**
     * Update the specified service template.
     */
    public function update(UpsertServiceTemplateRequest $request, ServiceTemplate $serviceTemplate): ServiceTemplateResource
    {
        $serviceTemplate->update(
            ServiceTemplateData::payload($request->validated())
        );

        return new ServiceTemplateResource($serviceTemplate->fresh());
    }

    /**
     * Remove the specified service template.
     */
    public function destroy(ServiceTemplate $serviceTemplate): Response
    {
        $serviceTemplate->delete();

        return response()->noContent();
    }
}
