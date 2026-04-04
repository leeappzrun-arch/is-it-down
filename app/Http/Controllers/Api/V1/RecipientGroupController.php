<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpsertRecipientGroupRequest;
use App\Http\Resources\V1\RecipientGroupResource;
use App\Models\RecipientGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RecipientGroupController extends Controller
{
    /**
     * Display a listing of recipient groups.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = $validated['search'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = RecipientGroup::query()
            ->withCount('recipients')
            ->orderBy('name')
            ->when($search !== null && $search !== '', function ($groupQuery) use ($search): void {
                $groupQuery->where('name', 'like', '%'.$search.'%');
            });

        return RecipientGroupResource::collection(
            $query->paginate($perPage)->withQueryString()
        );
    }

    /**
     * Store a newly created recipient group.
     */
    public function store(UpsertRecipientGroupRequest $request): JsonResponse
    {
        $group = RecipientGroup::query()->create([
            'name' => trim((string) $request->validated('name')),
        ]);

        return (new RecipientGroupResource(
            $group->loadCount('recipients')
        ))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified recipient group.
     */
    public function show(RecipientGroup $recipientGroup): RecipientGroupResource
    {
        return new RecipientGroupResource(
            $recipientGroup
                ->load('recipients:id,name,endpoint')
                ->loadCount('recipients')
        );
    }

    /**
     * Update the specified recipient group.
     */
    public function update(UpsertRecipientGroupRequest $request, RecipientGroup $recipientGroup): RecipientGroupResource
    {
        $recipientGroup->update([
            'name' => trim((string) $request->validated('name')),
        ]);

        return new RecipientGroupResource(
            $recipientGroup->loadCount('recipients')
        );
    }

    /**
     * Remove the specified recipient group.
     */
    public function destroy(RecipientGroup $recipientGroup): Response
    {
        $recipientGroup->delete();

        return response()->noContent();
    }
}
