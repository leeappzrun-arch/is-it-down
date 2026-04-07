<?php

namespace App\Support\AiAssistant;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Concerns\RecipientValidation;
use App\Concerns\ServiceValidation;
use App\Models\Recipient;
use App\Models\Service;
use App\Models\ServiceTemplate;
use App\Models\User;
use App\Support\Recipients\RecipientData;
use App\Support\Services\ServiceData;
use App\Support\Services\ServiceTemplateData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AiAssistantToolExecutor
{
    use PasswordValidationRules;
    use ProfileValidationRules;
    use RecipientValidation;
    use ServiceValidation;

    /**
     * Get the tool definitions made available to the provider.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toolDefinitions(User $user): array
    {
        $definitions = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'inspect_service',
                    'description' => 'Inspect a monitored service by id, name, or URL to review its status, checks, and routing.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'identifier' => [
                                'type' => 'string',
                                'description' => 'The service id, exact service name, or exact service URL.',
                            ],
                        ],
                        'required' => ['identifier'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];

        if (! $user->isAdmin()) {
            return $definitions;
        }

        return [
            ...$definitions,
            [
                'type' => 'function',
                'function' => [
                    'name' => 'manage_user',
                    'description' => 'Create, update, or delete a user account.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'action' => ['type' => 'string', 'enum' => ['create', 'update', 'delete']],
                            'target' => ['type' => 'string', 'description' => 'Required for update and delete. Use the user id, exact email, or exact name.'],
                            'name' => ['type' => 'string'],
                            'email' => ['type' => 'string'],
                            'role' => ['type' => 'string', 'enum' => User::roles()],
                            'password' => ['type' => 'string'],
                            'password_confirmation' => ['type' => 'string'],
                        ],
                        'required' => ['action'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'manage_recipient',
                    'description' => 'Create, update, or delete a recipient.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'action' => ['type' => 'string', 'enum' => ['create', 'update', 'delete']],
                            'target' => ['type' => 'string', 'description' => 'Required for update and delete. Use the recipient id, exact name, or exact endpoint target.'],
                            'name' => ['type' => 'string'],
                            'endpoint_type' => ['type' => 'string', 'enum' => [Recipient::TYPE_MAIL, Recipient::TYPE_WEBHOOK]],
                            'endpoint_target' => ['type' => 'string'],
                            'webhook_auth_type' => ['type' => 'string', 'enum' => Recipient::webhookAuthTypes()],
                            'webhook_auth_username' => ['type' => 'string'],
                            'webhook_auth_password' => ['type' => 'string'],
                            'webhook_auth_token' => ['type' => 'string'],
                            'webhook_auth_header_name' => ['type' => 'string'],
                            'webhook_auth_header_value' => ['type' => 'string'],
                            'group_ids' => [
                                'type' => 'array',
                                'items' => ['type' => 'integer'],
                            ],
                        ],
                        'required' => ['action'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'manage_service',
                    'description' => 'Create, update, or delete a monitored service.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'action' => ['type' => 'string', 'enum' => ['create', 'update', 'delete']],
                            'target' => ['type' => 'string', 'description' => 'Required for update and delete. Use the service id, exact name, or exact URL.'],
                            'template' => ['type' => 'string', 'description' => 'Optional for create. Use a service template id or exact template name to prefill the new service before applying any overrides.'],
                            'name' => ['type' => 'string'],
                            'url' => ['type' => 'string'],
                            'interval_seconds' => [
                                'type' => 'integer',
                                'enum' => array_keys(Service::intervalOptions()),
                            ],
                            'expect_type' => [
                                'type' => 'string',
                                'enum' => array_keys(Service::expectTypes()),
                            ],
                            'expect_value' => ['type' => 'string'],
                            'service_group_ids' => [
                                'type' => 'array',
                                'items' => ['type' => 'integer'],
                            ],
                            'recipient_group_ids' => [
                                'type' => 'array',
                                'items' => ['type' => 'integer'],
                            ],
                            'recipient_ids' => [
                                'type' => 'array',
                                'items' => ['type' => 'integer'],
                            ],
                        ],
                        'required' => ['action'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'inspect_recipient',
                    'description' => 'Inspect a recipient by id, name, or endpoint target to review delivery configuration and linked routing.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'identifier' => [
                                'type' => 'string',
                                'description' => 'The recipient id, exact name, or exact endpoint target.',
                            ],
                        ],
                        'required' => ['identifier'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];
    }

    /**
     * Execute one tool call and return a structured result.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(User $user, string $toolName, array $arguments): array
    {
        return match ($toolName) {
            'inspect_service' => $this->inspectService($user, (string) Arr::get($arguments, 'identifier', '')),
            'manage_user' => $this->manageUser($user, $arguments),
            'manage_recipient' => $this->manageRecipient($user, $arguments),
            'manage_service' => $this->manageService($user, $arguments),
            'inspect_recipient' => $this->inspectRecipient($user, (string) Arr::get($arguments, 'identifier', '')),
            default => $this->failure('Unknown tool requested.'),
        };
    }

    /**
     * Inspect a monitored service.
     *
     * @return array<string, mixed>
     */
    private function inspectService(User $user, string $identifier): array
    {
        try {
            $service = $this->resolveService($identifier);
        } catch (ValidationException $exception) {
            return $this->validationFailure($exception);
        }

        $service->load([
            'groups:id,name',
            'recipientGroups:id,name',
            'recipientGroups.recipients:id,name,endpoint',
            'recipients:id,name,endpoint',
        ]);

        $payload = [
            'ok' => true,
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'url' => $service->url,
                'status' => $service->current_status ?? 'pending',
                'status_label' => $service->monitoringStatusLabel(),
                'interval' => $service->intervalLabel(),
                'expectation' => $service->expectSummary(),
                'latest_reason' => $service->monitoringReasonSummary(),
                'last_response_code' => $service->last_response_code,
                'last_checked_at' => $service->last_checked_at?->toIso8601String(),
                'next_check_at' => $service->next_check_at?->toIso8601String(),
                'next_check_summary' => $service->nextCheckSummary(),
                'status_duration' => $service->statusDurationSummary(),
            ],
        ];

        if ($user->isAdmin()) {
            $payload['service']['service_groups'] = $service->groups->pluck('name')->all();
            $payload['service']['recipient_groups'] = $service->recipientGroups->pluck('name')->all();
            $payload['service']['direct_recipients'] = $service->recipients->pluck('name')->all();
            $payload['service']['effective_recipients'] = $service->effectiveRecipientRoutes()
                ->map(fn (array $route): array => [
                    'name' => $route['recipient']->name,
                    'endpoint' => $route['recipient']->endpointTarget(),
                    'sources' => $route['sources'],
                ])
                ->all();
        }

        return $payload;
    }

    /**
     * Inspect a recipient and its routing links.
     *
     * @return array<string, mixed>
     */
    private function inspectRecipient(User $user, string $identifier): array
    {
        if (! $user->isAdmin()) {
            return $this->failure('You do not have permission to inspect recipient configuration.');
        }

        try {
            $recipient = $this->resolveRecipient($identifier);
        } catch (ValidationException $exception) {
            return $this->validationFailure($exception);
        }

        $recipient->load([
            'groups:id,name',
            'services:id,name',
            'serviceGroups:id,name',
        ]);

        return [
            'ok' => true,
            'recipient' => [
                'id' => $recipient->id,
                'name' => $recipient->name,
                'endpoint_type' => $recipient->endpointType(),
                'endpoint_target' => $recipient->endpointTarget(),
                'authentication' => $recipient->webhookAuthenticationSummary(),
                'groups' => $recipient->groups->pluck('name')->all(),
                'services' => $recipient->services->pluck('name')->all(),
                'service_groups' => $recipient->serviceGroups->pluck('name')->all(),
            ],
        ];
    }

    /**
     * Create, update, or delete a user account.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function manageUser(User $user, array $arguments): array
    {
        if (! $user->isAdmin()) {
            return $this->failure('You do not have permission to manage users.');
        }

        $action = (string) Arr::get($arguments, 'action', '');

        return match ($action) {
            'create' => $this->createUser($arguments),
            'update' => $this->updateUser($arguments),
            'delete' => $this->deleteUser($arguments),
            default => $this->failure('Choose a valid user action: create, update, or delete.'),
        };
    }

    /**
     * Create, update, or delete a recipient.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function manageRecipient(User $user, array $arguments): array
    {
        if (! $user->isAdmin()) {
            return $this->failure('You do not have permission to manage recipients.');
        }

        $action = (string) Arr::get($arguments, 'action', '');

        return match ($action) {
            'create' => $this->createRecipient($arguments),
            'update' => $this->updateRecipient($arguments),
            'delete' => $this->deleteRecipient($arguments),
            default => $this->failure('Choose a valid recipient action: create, update, or delete.'),
        };
    }

    /**
     * Create, update, or delete a service.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function manageService(User $user, array $arguments): array
    {
        if (! $user->isAdmin()) {
            return $this->failure('You do not have permission to manage services.');
        }

        $action = (string) Arr::get($arguments, 'action', '');

        return match ($action) {
            'create' => $this->createService($arguments),
            'update' => $this->updateService($arguments),
            'delete' => $this->deleteService($arguments),
            default => $this->failure('Choose a valid service action: create, update, or delete.'),
        };
    }

    /**
     * Create a user account.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createUser(array $arguments): array
    {
        $payload = [
            'name' => Arr::get($arguments, 'name'),
            'email' => Arr::get($arguments, 'email'),
            'password' => Arr::get($arguments, 'password'),
            'password_confirmation' => Arr::get($arguments, 'password_confirmation', Arr::get($arguments, 'password')),
            'role' => Arr::get($arguments, 'role', User::ROLE_USER),
        ];

        $validator = Validator::make($payload, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'role' => ['required', Rule::in(User::roles())],
        ]);

        if ($validator->fails()) {
            return $this->failureFromValidator($validator);
        }

        $validated = $validator->validated();

        $createdUser = User::query()->create([
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'password' => (string) $validated['password'],
            'role' => (string) $validated['role'],
        ]);

        return [
            'ok' => true,
            'message' => 'User created successfully.',
            'user' => $this->userSummary($createdUser),
        ];
    }

    /**
     * Update a user account.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateUser(array $arguments): array
    {
        try {
            $targetUser = $this->resolveUser((string) Arr::get($arguments, 'target', ''));
        } catch (ValidationException $exception) {
            return $this->validationFailure($exception);
        }

        $payload = [
            'name' => Arr::get($arguments, 'name', $targetUser->name),
            'email' => Arr::get($arguments, 'email', $targetUser->email),
            'password' => Arr::get($arguments, 'password'),
            'password_confirmation' => Arr::get($arguments, 'password_confirmation', Arr::get($arguments, 'password')),
            'role' => Arr::get($arguments, 'role', $targetUser->role),
        ];

        $validator = Validator::make($payload, [
            ...$this->profileRules($targetUser->id),
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'role' => ['required', Rule::in(User::roles())],
        ]);

        if ($validator->fails()) {
            return $this->failureFromValidator($validator);
        }

        $validated = $validator->validated();
        $newRole = (string) $validated['role'];

        if (
            $targetUser->isAdmin()
            && $newRole !== User::ROLE_ADMIN
            && User::query()->where('role', User::ROLE_ADMIN)->count() === 1
        ) {
            return $this->failure('The last admin must remain an admin.');
        }

        $targetUser->update(array_filter([
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'role' => $newRole,
            'password' => filled($validated['password'] ?? null) ? (string) $validated['password'] : null,
        ], fn (mixed $value): bool => $value !== null));

        return [
            'ok' => true,
            'message' => 'User updated successfully.',
            'user' => $this->userSummary($targetUser->fresh()),
        ];
    }

    /**
     * Delete a user account.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function deleteUser(array $arguments): array
    {
        try {
            $targetUser = $this->resolveUser((string) Arr::get($arguments, 'target', ''));
        } catch (ValidationException $exception) {
            return $this->validationFailure($exception);
        }

        if ($targetUser->isAdmin()) {
            return $this->failure('Admin accounts cannot be deleted through the assistant.');
        }

        $summary = $this->userSummary($targetUser);

        $targetUser->delete();

        return [
            'ok' => true,
            'message' => 'User deleted successfully.',
            'user' => $summary,
        ];
    }

    /**
     * Create a recipient.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createRecipient(array $arguments): array
    {
        $endpointType = (string) Arr::get($arguments, 'endpoint_type', Recipient::TYPE_MAIL);
        $webhookAuthType = (string) Arr::get($arguments, 'webhook_auth_type', Recipient::WEBHOOK_AUTH_NONE);

        $payload = [
            'name' => Arr::get($arguments, 'name'),
            'endpointType' => $endpointType,
            'endpointTarget' => Arr::get($arguments, 'endpoint_target'),
            'selectedGroupIds' => $this->normalizeIntegerList(Arr::get($arguments, 'group_ids', [])),
            'webhookAuthType' => $webhookAuthType,
            'webhookAuthUsername' => Arr::get($arguments, 'webhook_auth_username', ''),
            'webhookAuthPassword' => Arr::get($arguments, 'webhook_auth_password', ''),
            'webhookAuthToken' => Arr::get($arguments, 'webhook_auth_token', ''),
            'webhookAuthHeaderName' => Arr::get($arguments, 'webhook_auth_header_name', ''),
            'webhookAuthHeaderValue' => Arr::get($arguments, 'webhook_auth_header_value', ''),
        ];

        $validator = Validator::make($payload, $this->recipientValidationRules($endpointType, $webhookAuthType));

        if ($validator->fails()) {
            return $this->failureFromValidator($validator);
        }

        $validated = $validator->validated();

        $recipient = Recipient::query()->create(
            RecipientData::payload($validated)
        );

        $recipient->groups()->sync($validated['selectedGroupIds'] ?? []);

        return [
            'ok' => true,
            'message' => 'Recipient created successfully.',
            'recipient' => $this->recipientSummary($recipient->fresh('groups')),
        ];
    }

    /**
     * Update a recipient.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateRecipient(array $arguments): array
    {
        try {
            $recipient = $this->resolveRecipient((string) Arr::get($arguments, 'target', ''));
        } catch (ValidationException $exception) {
            return $this->validationFailure($exception);
        }

        ['type' => $currentEndpointType, 'target' => $currentEndpointTarget] = RecipientData::parseEndpoint($recipient->endpoint);

        $endpointType = (string) Arr::get($arguments, 'endpoint_type', $currentEndpointType);
        $webhookAuthType = (string) Arr::get($arguments, 'webhook_auth_type', $recipient->webhook_auth_type);

        $payload = [
            'name' => Arr::get($arguments, 'name', $recipient->name),
            'endpointType' => $endpointType,
            'endpointTarget' => Arr::get($arguments, 'endpoint_target', $currentEndpointTarget),
            'selectedGroupIds' => $this->normalizeIntegerList(
                Arr::get($arguments, 'group_ids', $recipient->groups()->pluck('recipient_groups.id')->all())
            ),
            'webhookAuthType' => $webhookAuthType,
            'webhookAuthUsername' => Arr::get($arguments, 'webhook_auth_username', $recipient->webhook_auth_username ?? ''),
            'webhookAuthPassword' => Arr::get($arguments, 'webhook_auth_password', $recipient->webhook_auth_password ?? ''),
            'webhookAuthToken' => Arr::get($arguments, 'webhook_auth_token', $recipient->webhook_auth_token ?? ''),
            'webhookAuthHeaderName' => Arr::get($arguments, 'webhook_auth_header_name', $recipient->webhook_auth_header_name ?? ''),
            'webhookAuthHeaderValue' => Arr::get($arguments, 'webhook_auth_header_value', $recipient->webhook_auth_header_value ?? ''),
        ];

        $validator = Validator::make($payload, $this->recipientValidationRules($endpointType, $webhookAuthType));

        if ($validator->fails()) {
            return $this->failureFromValidator($validator);
        }

        $validated = $validator->validated();

        $recipient->update(
            RecipientData::payload($validated)
        );

        $recipient->groups()->sync($validated['selectedGroupIds'] ?? []);

        return [
            'ok' => true,
            'message' => 'Recipient updated successfully.',
            'recipient' => $this->recipientSummary($recipient->fresh('groups')),
        ];
    }

    /**
     * Delete a recipient.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function deleteRecipient(array $arguments): array
    {
        try {
            $recipient = $this->resolveRecipient((string) Arr::get($arguments, 'target', ''));
        } catch (ValidationException $exception) {
            return $this->validationFailure($exception);
        }

        $summary = $this->recipientSummary($recipient->load('groups'));

        $recipient->delete();

        return [
            'ok' => true,
            'message' => 'Recipient deleted successfully.',
            'recipient' => $summary,
        ];
    }

    /**
     * Create a monitored service.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createService(array $arguments): array
    {
        try {
            $templateDefaults = $this->serviceTemplateDefaults(Arr::get($arguments, 'template'));
        } catch (ValidationException $exception) {
            return $this->validationFailure($exception);
        }

        $expectType = (string) Arr::get($arguments, 'expect_type', Arr::get($templateDefaults, 'expectType', Service::EXPECT_NONE));

        $payload = [
            'name' => Arr::get($arguments, 'name', Arr::get($templateDefaults, 'name')),
            'url' => Arr::get($arguments, 'url'),
            'intervalSeconds' => Arr::get($arguments, 'interval_seconds', Arr::get($templateDefaults, 'intervalSeconds', Service::INTERVAL_1_MINUTE)),
            'expectType' => $expectType,
            'expectValue' => Arr::get($arguments, 'expect_value', Arr::get($templateDefaults, 'expectValue', '')),
            'selectedServiceGroupIds' => $this->normalizeIntegerList(Arr::get($arguments, 'service_group_ids', Arr::get($templateDefaults, 'selectedServiceGroupIds', []))),
            'selectedRecipientGroupIds' => $this->normalizeIntegerList(Arr::get($arguments, 'recipient_group_ids', Arr::get($templateDefaults, 'selectedRecipientGroupIds', []))),
            'selectedRecipientIds' => $this->normalizeIntegerList(Arr::get($arguments, 'recipient_ids', Arr::get($templateDefaults, 'selectedRecipientIds', []))),
        ];

        $validator = Validator::make(
            $payload,
            $this->serviceValidationRules($expectType, requiresName: ! filled(Arr::get($arguments, 'template')))
        );

        if ($validator->fails()) {
            return $this->failureFromValidator($validator);
        }

        $validated = $validator->validated();

        $service = Service::query()->create(
            ServiceData::payload($validated)
        );

        $service->groups()->sync($validated['selectedServiceGroupIds'] ?? []);
        $service->recipientGroups()->sync($validated['selectedRecipientGroupIds'] ?? []);
        $service->recipients()->sync($validated['selectedRecipientIds'] ?? []);

        return [
            'ok' => true,
            'message' => 'Service created successfully.',
            'service' => $this->serviceSummary($service->fresh(['groups', 'recipientGroups', 'recipients'])),
        ];
    }

    /**
     * Update a monitored service.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateService(array $arguments): array
    {
        try {
            $service = $this->resolveService((string) Arr::get($arguments, 'target', ''));
        } catch (ValidationException $exception) {
            return $this->validationFailure($exception);
        }

        $expectType = (string) Arr::get(
            $arguments,
            'expect_type',
            $service->expect_type ?? Service::EXPECT_NONE,
        );

        $payload = [
            'name' => Arr::get($arguments, 'name', $service->name),
            'url' => Arr::get($arguments, 'url', $service->url),
            'intervalSeconds' => Arr::get($arguments, 'interval_seconds', $service->interval_seconds),
            'expectType' => $expectType,
            'expectValue' => Arr::get($arguments, 'expect_value', $service->expect_value ?? ''),
            'selectedServiceGroupIds' => $this->normalizeIntegerList(
                Arr::get($arguments, 'service_group_ids', $service->groups()->pluck('service_groups.id')->all())
            ),
            'selectedRecipientGroupIds' => $this->normalizeIntegerList(
                Arr::get($arguments, 'recipient_group_ids', $service->recipientGroups()->pluck('recipient_groups.id')->all())
            ),
            'selectedRecipientIds' => $this->normalizeIntegerList(
                Arr::get($arguments, 'recipient_ids', $service->recipients()->pluck('recipients.id')->all())
            ),
        ];

        $validator = Validator::make($payload, $this->serviceValidationRules($expectType));

        if ($validator->fails()) {
            return $this->failureFromValidator($validator);
        }

        $validated = $validator->validated();

        $service->update(
            ServiceData::payload($validated)
        );

        $service->groups()->sync($validated['selectedServiceGroupIds'] ?? []);
        $service->recipientGroups()->sync($validated['selectedRecipientGroupIds'] ?? []);
        $service->recipients()->sync($validated['selectedRecipientIds'] ?? []);

        return [
            'ok' => true,
            'message' => 'Service updated successfully.',
            'service' => $this->serviceSummary($service->fresh(['groups', 'recipientGroups', 'recipients'])),
        ];
    }

    /**
     * Delete a monitored service.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function deleteService(array $arguments): array
    {
        try {
            $service = $this->resolveService((string) Arr::get($arguments, 'target', ''));
        } catch (ValidationException $exception) {
            return $this->validationFailure($exception);
        }

        $summary = $this->serviceSummary($service->load(['groups', 'recipientGroups', 'recipients']));

        $service->delete();

        return [
            'ok' => true,
            'message' => 'Service deleted successfully.',
            'service' => $summary,
        ];
    }

    /**
     * Resolve a user by id, email, or name.
     */
    private function resolveUser(string $identifier): User
    {
        /** @var User $user */
        $user = $this->resolveModel(
            User::query(),
            $identifier,
            ['email', 'name'],
            'user'
        );

        return $user;
    }

    /**
     * Resolve a recipient by id, name, or endpoint target.
     */
    private function resolveRecipient(string $identifier): Recipient
    {
        $recipientQuery = Recipient::query()->with('groups:id,name');

        if (ctype_digit($identifier)) {
            $recipient = $recipientQuery->find((int) $identifier);

            if ($recipient instanceof Recipient) {
                return $recipient;
            }
        }

        $trimmedIdentifier = trim($identifier);

        if ($trimmedIdentifier === '') {
            throw ValidationException::withMessages([
                'target' => 'Provide a recipient id, exact name, or exact endpoint target.',
            ]);
        }

        $candidates = $recipientQuery
            ->get()
            ->filter(function (Recipient $recipient) use ($trimmedIdentifier): bool {
                return $recipient->name === $trimmedIdentifier
                    || $recipient->endpointTarget() === $trimmedIdentifier;
            })
            ->values();

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        if ($candidates->count() > 1) {
            throw ValidationException::withMessages([
                'target' => 'Multiple recipients matched that identifier. Use the numeric recipient id instead.',
            ]);
        }

        throw ValidationException::withMessages([
            'target' => 'No recipient matched that identifier.',
        ]);
    }

    /**
     * Resolve a service by id, name, or URL.
     */
    private function resolveService(string $identifier): Service
    {
        /** @var Service $service */
        $service = $this->resolveModel(
            Service::query(),
            $identifier,
            ['name', 'url'],
            'service'
        );

        return $service;
    }

    /**
     * Resolve a service template by id or exact name.
     */
    private function resolveServiceTemplate(string $identifier): ServiceTemplate
    {
        /** @var ServiceTemplate $template */
        $template = $this->resolveModel(
            ServiceTemplate::query(),
            $identifier,
            ['name'],
            'service template'
        );

        return $template;
    }

    /**
     * Resolve the template defaults used for service creation.
     *
     * @return array<string, mixed>
     */
    private function serviceTemplateDefaults(mixed $templateIdentifier): array
    {
        if (! is_string($templateIdentifier) && ! is_int($templateIdentifier) && ! is_float($templateIdentifier)) {
            return [];
        }

        $trimmedIdentifier = trim((string) $templateIdentifier);

        if ($trimmedIdentifier === '') {
            return [];
        }

        $template = $this->resolveServiceTemplate($trimmedIdentifier);

        return ServiceTemplateData::serviceFormState($template->serviceConfiguration());
    }

    /**
     * Resolve a model by identifier.
     */
    private function resolveModel(Builder $query, string $identifier, array $columns, string $resourceLabel): Model
    {
        $trimmedIdentifier = trim($identifier);

        if ($trimmedIdentifier === '') {
            throw ValidationException::withMessages([
                'target' => "Provide a {$resourceLabel} id or exact identifier.",
            ]);
        }

        if (ctype_digit($trimmedIdentifier)) {
            $model = (clone $query)->find((int) $trimmedIdentifier);

            if ($model instanceof Model) {
                return $model;
            }
        }

        foreach ($columns as $column) {
            $matches = (clone $query)
                ->where($column, $trimmedIdentifier)
                ->get();

            if ($matches->count() === 1) {
                return $matches->first();
            }

            if ($matches->count() > 1) {
                throw ValidationException::withMessages([
                    'target' => "Multiple {$resourceLabel} records matched that identifier. Use the numeric id instead.",
                ]);
            }
        }

        throw ValidationException::withMessages([
            'target' => "No {$resourceLabel} matched that identifier.",
        ]);
    }

    /**
     * Normalize a list of ids to integers.
     *
     * @return array<int, int>
     */
    private function normalizeIntegerList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $item): int => (int) $item, $value),
            fn (int $item): bool => $item > 0,
        ));
    }

    /**
     * Build a consistent failure payload from a validator instance.
     *
     * @return array<string, mixed>
     */
    private function failureFromValidator(\Illuminate\Contracts\Validation\Validator $validator): array
    {
        return [
            'ok' => false,
            'message' => (string) $validator->errors()->first(),
            'errors' => $validator->errors()->toArray(),
        ];
    }

    /**
     * Build a consistent failure payload.
     *
     * @return array<string, mixed>
     */
    private function failure(string $message): array
    {
        return [
            'ok' => false,
            'message' => $message,
        ];
    }

    /**
     * Build a consistent failure payload from a validation exception.
     *
     * @return array<string, mixed>
     */
    private function validationFailure(ValidationException $exception): array
    {
        return [
            'ok' => false,
            'message' => (string) collect($exception->errors())->flatten()->first(),
            'errors' => $exception->errors(),
        ];
    }

    /**
     * Build a user summary for tool results.
     *
     * @return array<string, mixed>
     */
    private function userSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }

    /**
     * Build a recipient summary for tool results.
     *
     * @return array<string, mixed>
     */
    private function recipientSummary(Recipient $recipient): array
    {
        return [
            'id' => $recipient->id,
            'name' => $recipient->name,
            'endpoint_type' => $recipient->endpointType(),
            'endpoint_target' => $recipient->endpointTarget(),
            'authentication' => $recipient->webhookAuthenticationSummary(),
            'groups' => $recipient->groups->pluck('name')->all(),
        ];
    }

    /**
     * Build a service summary for tool results.
     *
     * @return array<string, mixed>
     */
    private function serviceSummary(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'url' => $service->url,
            'interval' => $service->intervalLabel(),
            'expectation' => $service->expectSummary(),
            'service_groups' => $service->groups->pluck('name')->all(),
            'recipient_groups' => $service->recipientGroups->pluck('name')->all(),
            'recipients' => $service->recipients->pluck('name')->all(),
        ];
    }
}
