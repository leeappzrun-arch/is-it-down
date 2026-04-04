<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpsertRecipientRequest;
use App\Http\Resources\V1\RecipientResource;
use App\Models\Recipient;
use App\Support\Recipients\RecipientData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class RecipientController extends Controller
{
    /**
     * Display a listing of recipients.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'endpoint_type' => ['nullable', Rule::in([Recipient::TYPE_MAIL, Recipient::TYPE_WEBHOOK])],
            'group_id' => ['nullable', 'integer', Rule::exists('recipient_groups', 'id')],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = $validated['search'] ?? null;
        $endpointType = $validated['endpoint_type'] ?? null;
        $groupId = $validated['group_id'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = Recipient::query()
            ->with('groups:id,name')
            ->orderBy('name')
            ->orderBy('endpoint')
            ->when($search !== null && $search !== '', function ($recipientQuery) use ($search): void {
                $recipientQuery->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('endpoint', 'like', '%'.$search.'%')
                        ->orWhere('webhook_auth_type', 'like', '%'.$search.'%')
                        ->orWhereHas('groups', function ($groupQuery) use ($search): void {
                            $groupQuery->where('name', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($endpointType !== null, function ($recipientQuery) use ($endpointType): void {
                $recipientQuery->where(
                    'endpoint',
                    'like',
                    $endpointType === Recipient::TYPE_MAIL ? 'mailto://%' : 'webhook://%'
                );
            })
            ->when($groupId !== null, function ($recipientQuery) use ($groupId): void {
                $recipientQuery->whereHas('groups', function ($groupQuery) use ($groupId): void {
                    $groupQuery->whereKey($groupId);
                });
            });

        return RecipientResource::collection(
            $query->paginate($perPage)->withQueryString()
        );
    }

    /**
     * Store a newly created recipient.
     */
    public function store(UpsertRecipientRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $recipient = Recipient::query()->create(
            RecipientData::payload($validated)
        );

        $recipient->groups()->sync($validated['selectedGroupIds'] ?? []);

        return (new RecipientResource(
            $recipient->load('groups:id,name')
        ))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified recipient.
     */
    public function show(Recipient $recipient): RecipientResource
    {
        return new RecipientResource(
            $recipient->load('groups:id,name')
        );
    }

    /**
     * Update the specified recipient.
     */
    public function update(UpsertRecipientRequest $request, Recipient $recipient): RecipientResource
    {
        $validated = $request->validated();

        $recipient->update(
            RecipientData::payload($validated)
        );

        $recipient->groups()->sync($validated['selectedGroupIds'] ?? []);

        return new RecipientResource(
            $recipient->load('groups:id,name')
        );
    }

    /**
     * Remove the specified recipient.
     */
    public function destroy(Recipient $recipient): Response
    {
        $recipient->delete();

        return response()->noContent();
    }
}
