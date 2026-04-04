<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpsertUserRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = $validated['search'] ?? null;
        $role = $validated['role'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = User::query()
            ->orderBy('name')
            ->orderBy('email')
            ->when($search !== null && $search !== '', function ($userQuery) use ($search): void {
                $userQuery->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('role', 'like', '%'.$search.'%');
                });
            })
            ->when($role !== null && $role !== '', function ($userQuery) use ($role): void {
                $userQuery->where('role', $role);
            });

        return UserResource::collection(
            $query->paginate($perPage)->withQueryString()
        );
    }

    /**
     * Store a newly created user.
     */
    public function store(UpsertUserRequest $request): JsonResponse
    {
        $user = User::query()->create([
            'name' => (string) $request->validated('name'),
            'email' => (string) $request->validated('email'),
            'password' => (string) $request->validated('password'),
            'role' => (string) $request->validated('role'),
        ]);

        return (new UserResource($user))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    /**
     * Update the specified user.
     */
    public function update(UpsertUserRequest $request, User $user): UserResource
    {
        $validated = $request->validated();
        $newRole = (string) $validated['role'];

        if (
            $user->isAdmin()
            && $newRole !== User::ROLE_ADMIN
            && User::query()->where('role', User::ROLE_ADMIN)->count() === 1
        ) {
            throw ValidationException::withMessages([
                'role' => 'The last admin must remain an admin.',
            ]);
        }

        $user->update(array_filter([
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'role' => $newRole,
            'password' => filled($validated['password'] ?? null) ? (string) $validated['password'] : null,
        ], fn (mixed $value): bool => $value !== null));

        return new UserResource($user->fresh());
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): Response
    {
        if ($user->isAdmin()) {
            throw ValidationException::withMessages([
                'user' => 'Admin accounts cannot be deleted through the API.',
            ]);
        }

        $user->delete();

        return response()->noContent();
    }
}
