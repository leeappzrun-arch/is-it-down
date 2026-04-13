<?php

namespace App\Support\AiAssistant;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Concerns\RecipientValidation;
use App\Concerns\ServiceValidation;
use App\Mail\MailConfigurationTestMail;
use App\Models\Recipient;
use App\Models\Service;
use App\Models\ServiceDowntime;
use App\Models\ServiceTemplate;
use App\Models\User;
use App\Support\Monitoring\OutageAnalyzer;
use App\Support\Monitoring\ServiceMonitor;
use App\Support\Recipients\RecipientData;
use App\Support\Services\ServiceData;
use App\Support\Services\ServiceTemplateData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
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

    public function __construct(
        private readonly ServiceMonitor $serviceMonitor,
        private readonly OutageAnalyzer $outageAnalyzer,
    ) {}

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
            [
                'type' => 'function',
                'function' => [
                    'name' => 'inspect_downtime_history',
                    'description' => 'Inspect the recent downtime history and uptime percentage for a service by id, exact name, or exact URL.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'identifier' => [
                                'type' => 'string',
                                'description' => 'The service id, exact service name, or exact service URL.',
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'minimum' => 1,
                                'maximum' => 10,
                            ],
                        ],
                        'required' => ['identifier'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'check_website_status',
                    'description' => 'Perform a live HTTP check against any website URL and explain the result, including Dave analysis when it appears down.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'url' => ['type' => 'string'],
                            'expect_type' => ['type' => 'string', 'enum' => array_keys(Service::expectTypes())],
                            'expect_value' => ['type' => 'string'],
                        ],
                        'required' => ['url'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'send_test_email',
                    'description' => 'Send a test email using the application mail settings. Standard users may send to their own email address; admins may specify another recipient email.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'recipient_email' => ['type' => 'string'],
                        ],
                        'required' => [],
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
                            'additional_headers' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'value' => ['type' => 'string'],
                                    ],
                                    'required' => ['name', 'value'],
                                    'additionalProperties' => false,
                                ],
                            ],
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
                            'additional_headers' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'value' => ['type' => 'string'],
                                    ],
                                    'required' => ['name', 'value'],
                                    'additionalProperties' => false,
                                ],
                            ],
                            'ssl_expiry_notifications_enabled' => ['type' => 'boolean'],
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
            'inspect_downtime_history' => $this->inspectDowntimeHistory($user, (string) Arr::get($arguments, 'identifier', ''), (int) Arr::get($arguments, 'limit', 5)),
            'check_website_status' => $this->checkWebsiteStatus(
                (string) Arr::get($arguments, 'url', ''),
                (string) Arr::get($arguments, 'expect_type', Service::EXPECT_NONE),
                (string) Arr::get($arguments, 'expect_value', ''),
            ),
            'send_test_email' => $this->sendTestEmail($user, Arr::get($arguments, 'recipient_email')),
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
            'currentDowntime',
            'downtimes',
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
                'last_response_headers' => $service->lastResponseHeaders(),
                'last_checked_at' => $service->last_checked_at?->toIso8601String(),
                'latest_screenshot_url' => $service->latestScreenshotUrl(),
                'next_check_at' => $service->next_check_at?->toIso8601String(),
                'next_check_summary' => $service->nextCheckSummary(),
                'status_duration' => $service->statusDurationSummary(),
                'uptime_percentage_last_30_days' => $service->uptimePercentageForDays(30),
                'current_downtime' => $service->currentDowntime === null ? null : $this->downtimeSummary($service->currentDowntime),
                'recent_downtimes' => $service->recentDowntimes()
                    ->map(fn (ServiceDowntime $downtime): array => $this->downtimeSummary($downtime))
                    ->all(),
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
     * Inspect the recent downtime history for a service.
     *
     * @return array<string, mixed>
     */
    private function inspectDowntimeHistory(User $user, string $identifier, int $limit = 5): array
    {
        try {
            $service = $this->resolveService($identifier);
        } catch (ValidationException $exception) {
            return $this->validationFailure($exception);
        }

        $service->load('downtimes');
        $limit = max(1, min(10, $limit));

        return [
            'ok' => true,
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'url' => $service->url,
                'uptime_percentage_last_24_hours' => $service->uptimePercentageForDays(1),
                'uptime_percentage_last_7_days' => $service->uptimePercentageForDays(7),
                'uptime_percentage_last_30_days' => $service->uptimePercentageForDays(30),
                'recent_downtimes' => $service->recentDowntimes($limit)
                    ->map(fn (ServiceDowntime $downtime): array => $this->downtimeSummary($downtime))
                    ->all(),
            ],
        ];
    }

    /**
     * Perform a live website check.
     *
     * @return array<string, mixed>
     */
    private function checkWebsiteStatus(string $url, string $expectType, string $expectValue): array
    {
        $normalizedUrl = ServiceData::normalizeUrl($url);

        if ($normalizedUrl === '' || ! filter_var($normalizedUrl, FILTER_VALIDATE_URL)) {
            return $this->failure('Provide a valid website URL.');
        }

        $temporaryService = new Service([
            'name' => 'Manual website check',
            'url' => $normalizedUrl,
            'interval_seconds' => Service::INTERVAL_1_MINUTE,
            'expect_type' => $expectType === Service::EXPECT_NONE ? null : $expectType,
            'expect_value' => $expectType === Service::EXPECT_NONE ? null : $expectValue,
            'additional_headers' => [],
            'ssl_expiry_notifications_enabled' => false,
        ]);

        $result = $this->serviceMonitor->check($temporaryService);
        $analysis = $result->status === Service::STATUS_DOWN
            ? $this->outageAnalyzer->analyze($temporaryService, $result)
            : null;

        return [
            'ok' => true,
            'status' => $result->status,
            'url' => $normalizedUrl,
            'reason' => $result->reason,
            'response_code' => $result->responseCode,
            'attempt_count' => $result->attemptCount,
            'response_excerpt' => $result->bodyExcerpt,
            'response_headers' => $result->responseHeaders,
            'analysis' => $analysis,
        ];
    }

    /**
     * Send a test email using the configured mailer.
     *
     * @return array<string, mixed>
     */
    private function sendTestEmail(User $user, mixed $recipientEmail): array
    {
        $targetEmail = is_string($recipientEmail) && trim($recipientEmail) !== ''
            ? trim($recipientEmail)
            : (string) $user->email;

        if ($targetEmail === '' || ! filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->failure('Provide a valid recipient email address.');
        }

        if (! $user->isAdmin() && $targetEmail !== $user->email) {
            return $this->failure('You may only send a test email to your own address.');
        }

        Mail::to($targetEmail)->send(new MailConfigurationTestMail(now()));

        return [
            'ok' => true,
            'message' => 'Test email sent successfully.',
            'recipient_email' => $targetEmail,
            'mailer' => config('mail.default'),
        ];
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
                'additional_headers' => $recipient->configuredAdditionalHeaders(),
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
            'additionalHeaders' => ServiceData::normalizeAdditionalHeaders(Arr::get($arguments, 'additional_headers', [])),
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
            'additionalHeaders' => ServiceData::normalizeAdditionalHeaders(
                Arr::get($arguments, 'additional_headers', $recipient->configuredAdditionalHeaders())
            ),
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
            'additionalHeaders' => ServiceData::normalizeAdditionalHeaders(Arr::get($arguments, 'additional_headers', Arr::get($templateDefaults, 'additionalHeaders', []))),
            'sslExpiryNotificationsEnabled' => (bool) Arr::get($arguments, 'ssl_expiry_notifications_enabled', Arr::get($templateDefaults, 'sslExpiryNotificationsEnabled', false)),
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
            'additionalHeaders' => ServiceData::normalizeAdditionalHeaders(Arr::get($arguments, 'additional_headers', $service->configuredAdditionalHeaders())),
            'sslExpiryNotificationsEnabled' => (bool) Arr::get($arguments, 'ssl_expiry_notifications_enabled', $service->ssl_expiry_notifications_enabled),
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
                return mb_strtolower($recipient->name) === mb_strtolower($trimmedIdentifier)
                    || mb_strtolower($recipient->endpointTarget()) === mb_strtolower($trimmedIdentifier);
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
                ->whereRaw('LOWER('.$query->getModel()->qualifyColumn($column).') = ?', [mb_strtolower($trimmedIdentifier)])
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
            'additional_headers' => $recipient->configuredAdditionalHeaders(),
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
            'uptime_percentage_last_30_days' => $service->uptimePercentageForDays(30),
            'service_groups' => $service->groups->pluck('name')->all(),
            'recipient_groups' => $service->recipientGroups->pluck('name')->all(),
            'recipients' => $service->recipients->pluck('name')->all(),
        ];
    }

    /**
     * Build a downtime summary for tool results.
     *
     * @return array<string, mixed>
     */
    private function downtimeSummary(ServiceDowntime $downtime): array
    {
        return [
            'id' => $downtime->id,
            'started_at' => $downtime->started_at?->toIso8601String(),
            'ended_at' => $downtime->ended_at?->toIso8601String(),
            'is_ongoing' => $downtime->isOngoing(),
            'duration_human' => $downtime->durationSummary($downtime->ended_at),
            'started_reason' => $downtime->started_reason,
            'latest_reason' => $downtime->latest_reason,
            'recovery_reason' => $downtime->recovery_reason,
            'screenshot_url' => $downtime->screenshotUrl(),
            'latest_response_headers' => $downtime->latestResponseHeaders(),
            'ai_summary' => $downtime->ai_summary,
        ];
    }
}
