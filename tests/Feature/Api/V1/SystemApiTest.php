<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiKey;
use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\Service;
use App\Models\ServiceDowntime;
use App\Models\ServiceGroup;
use App\Models\ServiceTemplate;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_a_valid_active_api_key(): void
    {
        $user = User::factory()->admin()->create();

        $this->getJson('/api/v1/recipients')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'A valid bearer token is required.',
            ]);

        $expiredToken = $this->issueApiKey($user, ['recipients:read'], now()->subMinute());

        $this->withToken($expiredToken)
            ->getJson('/api/v1/recipients')
            ->assertUnauthorized();

        $revokedToken = $this->issueApiKey($user, ['recipients:read'], now()->addDay(), now());

        $this->withToken($revokedToken)
            ->getJson('/api/v1/recipients')
            ->assertUnauthorized();
    }

    public function test_recipient_read_endpoints_support_search_filtering_permissions_and_last_used_tracking(): void
    {
        $admin = User::factory()->admin()->create();
        $group = RecipientGroup::factory()->create([
            'name' => 'Operations',
        ]);
        $matchingRecipient = Recipient::factory()->create([
            'name' => 'Operations webhook',
            'endpoint' => 'webhook://https://example.com/hooks/ops',
        ]);
        $matchingRecipient->groups()->sync([$group->id]);

        Recipient::factory()->create([
            'name' => 'Finance inbox',
            'endpoint' => 'mailto://finance@example.com',
        ]);

        $token = $this->issueApiKey($admin, ['recipients:read']);

        $response = $this->withToken($token)
            ->getJson('/api/v1/recipients?search=Operations&endpoint_type=webhook&group_id='.$group->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Operations webhook');

        $this->assertNotNull(ApiKey::query()->where('token_hash', ApiKey::hashToken($token))->first()?->last_used_at);

        $forbiddenToken = $this->issueApiKey($admin, ['services:read']);

        $this->withToken($forbiddenToken)
            ->getJson('/api/v1/recipients')
            ->assertForbidden()
            ->assertJson([
                'message' => 'This API key does not have the required permission [recipients:read].',
            ]);
    }

    public function test_recipient_write_endpoints_reuse_livewire_validation_rules(): void
    {
        $admin = User::factory()->admin()->create();
        $group = RecipientGroup::factory()->create();
        $token = $this->issueApiKey($admin, ['recipients:write']);

        $this->withToken($token)
            ->postJson('/api/v1/recipients', [
                'name' => 'Broken email',
                'endpoint_type' => 'mail',
                'endpoint_target' => 'not-an-email',
                'group_ids' => [$group->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endpointTarget']);

        $response = $this->withToken($token)
            ->postJson('/api/v1/recipients', [
                'name' => 'Operations webhook',
                'endpoint_type' => 'webhook',
                'endpoint_target' => 'example.com/hooks/ops',
                'additional_headers' => [
                    ['name' => 'X-Environment', 'value' => 'production'],
                ],
                'webhook_auth_type' => 'bearer',
                'webhook_auth_token' => 'secret-token',
                'group_ids' => [$group->id],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Operations webhook');
        $response->assertJsonPath('data.endpoint_type', 'webhook');
        $response->assertJsonPath('data.additional_headers.0.name', 'X-Environment');

        $recipientId = $response->json('data.id');

        $this->assertDatabaseHas('recipients', [
            'id' => $recipientId,
            'name' => 'Operations webhook',
            'webhook_auth_type' => Recipient::WEBHOOK_AUTH_BEARER,
        ]);

        $this->withToken($token)
            ->patchJson('/api/v1/recipients/'.$recipientId, [
                'name' => 'Operations inbox',
                'endpoint_type' => 'mail',
                'endpoint_target' => 'ops@example.com',
                'group_ids' => [$group->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.endpoint_type', 'mail');
    }

    public function test_downtime_history_endpoints_require_history_permissions_and_return_records(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create([
            'name' => 'Billing API',
            'url' => 'https://billing.example.com/status',
        ]);

        $downtime = ServiceDowntime::factory()->create([
            'service_id' => $service->id,
            'started_at' => now()->subMinutes(10),
            'ended_at' => now()->subMinutes(5),
            'latest_response_headers' => [
                ['name' => 'Content-Type', 'value' => 'text/plain'],
            ],
            'ai_summary' => 'The upstream returned 503 responses.',
        ]);

        $token = $this->issueApiKey($admin, ['history:read']);

        $this->withToken($token)
            ->getJson('/api/v1/services/'.$service->id.'/downtimes?status=resolved')
            ->assertOk()
            ->assertJsonPath('data.0.id', $downtime->id)
            ->assertJsonPath('data.0.latest_response_headers.0.name', 'Content-Type')
            ->assertJsonPath('data.0.ai_summary', 'The upstream returned 503 responses.');

        $this->withToken($token)
            ->getJson('/api/v1/service-downtimes/'.$downtime->id)
            ->assertOk()
            ->assertJsonPath('data.service.name', 'Billing API')
            ->assertJsonPath('data.latest_response_headers.0.value', 'text/plain');

        $forbiddenToken = $this->issueApiKey($admin, ['services:read']);

        $this->withToken($forbiddenToken)
            ->getJson('/api/v1/services/'.$service->id.'/downtimes')
            ->assertForbidden()
            ->assertJson([
                'message' => 'This API key does not have the required permission [history:read].',
            ]);
    }

    public function test_recipient_group_endpoints_support_crud(): void
    {
        $admin = User::factory()->admin()->create();
        $recipient = Recipient::factory()->create([
            'name' => 'On-call inbox',
            'endpoint' => 'mailto://oncall@example.com',
        ]);
        $token = $this->issueApiKey($admin, ['recipients:read', 'recipients:write']);

        $createResponse = $this->withToken($token)
            ->postJson('/api/v1/recipient-groups', [
                'name' => 'Operations',
            ]);

        $createResponse->assertCreated();
        $groupId = $createResponse->json('data.id');

        $recipient->groups()->sync([$groupId]);

        $this->withToken($token)
            ->getJson('/api/v1/recipient-groups/'.$groupId)
            ->assertOk()
            ->assertJsonPath('data.recipients.0.name', 'On-call inbox');

        $this->withToken($token)
            ->patchJson('/api/v1/recipient-groups/'.$groupId, [
                'name' => 'Leadership',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Leadership');

        $this->withToken($token)
            ->deleteJson('/api/v1/recipient-groups/'.$groupId)
            ->assertNoContent();
    }

    public function test_service_endpoints_support_search_filters_crud_and_shared_validation(): void
    {
        $admin = User::factory()->admin()->create();
        $recipient = Recipient::factory()->create([
            'name' => 'Operations webhook',
            'endpoint' => 'webhook://https://example.com/hooks/ops',
        ]);
        $recipientGroup = RecipientGroup::factory()->create([
            'name' => 'Operations',
        ]);
        $serviceGroup = ServiceGroup::factory()->create([
            'name' => 'Production',
        ]);

        $service = Service::factory()->create([
            'name' => 'Marketing Site',
            'url' => 'https://example.com/status',
            'expect_type' => Service::EXPECT_TEXT,
            'expect_value' => 'Healthy',
            'last_response_headers' => [
                ['name' => 'Content-Type', 'value' => 'text/plain'],
            ],
            'last_screenshot_disk' => 'public',
            'last_screenshot_path' => 'service-screenshots/service-1.png',
        ]);
        $service->groups()->sync([$serviceGroup->id]);
        $service->recipientGroups()->sync([$recipientGroup->id]);
        $service->recipients()->sync([$recipient->id]);
        $service->forceFill([
            'current_status' => Service::STATUS_DOWN,
        ])->save();

        $token = $this->issueApiKey($admin, ['services:read', 'services:write']);

        $serviceTemplate = ServiceTemplate::factory()->create([
            'name' => 'Marketing site starter',
            'configuration' => [
                'name' => 'Marketing Site Template',
                'interval_seconds' => Service::INTERVAL_3_MINUTES,
                'expect_type' => Service::EXPECT_TEXT,
                'expect_value' => 'Healthy',
                'additional_headers' => [
                    ['name' => 'X-Monitor', 'value' => 'is-it-down'],
                ],
                'ssl_expiry_notifications_enabled' => true,
                'service_group_ids' => [$serviceGroup->id],
                'recipient_group_ids' => [$recipientGroup->id],
                'recipient_ids' => [$recipient->id],
            ],
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/services?search=Marketing&status=down&service_group_id='.$serviceGroup->id.'&recipient_group_id='.$recipientGroup->id.'&recipient_id='.$recipient->id)
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Marketing Site')
            ->assertJsonPath('data.0.last_response_headers.0.name', 'Content-Type')
            ->assertJsonPath('data.0.latest_screenshot_url', Storage::disk('public')->url('service-screenshots/service-1.png'));

        $this->withToken($token)
            ->postJson('/api/v1/services', [
                'name' => 'Broken service',
                'url' => 'example.com/status',
                'interval_seconds' => 60,
                'expect_type' => 'regex',
                'expect_value' => 'not-a-regex',
                'service_group_ids' => [$serviceGroup->id],
                'recipient_group_ids' => [$recipientGroup->id],
                'recipient_ids' => [$recipient->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expectValue']);

        $createResponse = $this->withToken($token)
            ->postJson('/api/v1/services', [
                'template' => $serviceTemplate->name,
                'url' => 'https://example.com/api',
                'name' => 'API Service',
            ]);

        $createResponse->assertCreated();
        $createdServiceId = $createResponse->json('data.id');
        $createResponse->assertJsonPath('data.interval_seconds', Service::INTERVAL_3_MINUTES);
        $createResponse->assertJsonPath('data.expect_type', Service::EXPECT_TEXT);
        $createResponse->assertJsonPath('data.additional_headers.0.name', 'X-Monitor');
        $createResponse->assertJsonPath('data.ssl_expiry_notifications_enabled', true);

        $this->withToken($token)
            ->patchJson('/api/v1/services/'.$createdServiceId, [
                'name' => 'API Service',
                'url' => 'https://example.com/api',
                'interval_seconds' => 300,
                'expect_type' => 'none',
                'expect_value' => '',
                'additional_headers' => [
                    ['name' => 'X-Environment', 'value' => 'production'],
                ],
                'ssl_expiry_notifications_enabled' => true,
                'service_group_ids' => [$serviceGroup->id],
                'recipient_group_ids' => [],
                'recipient_ids' => [],
            ])
            ->assertOk()
            ->assertJsonPath('data.interval_seconds', 300)
            ->assertJsonPath('data.additional_headers.0.name', 'X-Environment')
            ->assertJsonPath('data.ssl_expiry_notifications_enabled', true);

        $this->withToken($token)
            ->deleteJson('/api/v1/services/'.$createdServiceId)
            ->assertNoContent();
    }

    public function test_service_template_endpoints_support_search_permissions_and_crud(): void
    {
        $admin = User::factory()->admin()->create();
        $recipient = Recipient::factory()->create();
        $recipientGroup = RecipientGroup::factory()->create(['name' => 'Operations']);
        $serviceGroup = ServiceGroup::factory()->create(['name' => 'Production']);

        $existingTemplate = ServiceTemplate::factory()->create([
            'name' => 'Website starter',
            'configuration' => [
                'name' => 'Marketing Site',
                'interval_seconds' => Service::INTERVAL_1_MINUTE,
                'expect_type' => Service::EXPECT_TEXT,
                'expect_value' => 'Healthy',
                'additional_headers' => [
                    ['name' => 'X-Monitor', 'value' => 'is-it-down'],
                ],
                'ssl_expiry_notifications_enabled' => true,
                'service_group_ids' => [$serviceGroup->id],
                'recipient_group_ids' => [$recipientGroup->id],
                'recipient_ids' => [$recipient->id],
            ],
        ]);

        $token = $this->issueApiKey($admin, ['templates:read', 'templates:write']);

        $this->withToken($token)
            ->getJson('/api/v1/service-templates?search=Website&service_group_id='.$serviceGroup->id.'&recipient_group_id='.$recipientGroup->id.'&recipient_id='.$recipient->id)
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Website starter');

        $forbiddenToken = $this->issueApiKey($admin, ['services:read']);

        $this->withToken($forbiddenToken)
            ->getJson('/api/v1/service-templates')
            ->assertForbidden()
            ->assertJson([
                'message' => 'This API key does not have the required permission [templates:read].',
            ]);

        $this->withToken($token)
            ->postJson('/api/v1/service-templates', [
                'name' => 'Broken template',
                'service_name' => '',
                'interval_seconds' => Service::INTERVAL_1_MINUTE,
                'expect_type' => Service::EXPECT_NONE,
                'expect_value' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['serviceName']);

        $createResponse = $this->withToken($token)
            ->postJson('/api/v1/service-templates', [
                'name' => 'API service template',
                'service_name' => 'Billing API',
                'interval_seconds' => Service::INTERVAL_5_MINUTES,
                'expect_type' => Service::EXPECT_REGEX,
                'expect_value' => '/healthy/i',
                'additional_headers' => [
                    ['name' => 'X-Environment', 'value' => 'production'],
                ],
                'ssl_expiry_notifications_enabled' => true,
                'service_group_ids' => [$serviceGroup->id],
                'recipient_group_ids' => [$recipientGroup->id],
                'recipient_ids' => [$recipient->id],
            ]);

        $createResponse->assertCreated();
        $createdTemplateId = $createResponse->json('data.id');
        $createResponse->assertJsonPath('data.service_name', 'Billing API');
        $createResponse->assertJsonPath('data.additional_headers.0.name', 'X-Environment');
        $createResponse->assertJsonPath('data.ssl_expiry_notifications_enabled', true);

        $this->withToken($token)
            ->getJson('/api/v1/service-templates/'.$createdTemplateId)
            ->assertOk()
            ->assertJsonPath('data.name', 'API service template');

        $this->withToken($token)
            ->patchJson('/api/v1/service-templates/'.$createdTemplateId, [
                'name' => 'API service template',
                'service_name' => 'Billing API',
                'interval_seconds' => Service::INTERVAL_10_MINUTES,
                'expect_type' => Service::EXPECT_NONE,
                'expect_value' => '',
                'additional_headers' => [],
                'ssl_expiry_notifications_enabled' => false,
                'service_group_ids' => [$serviceGroup->id],
                'recipient_group_ids' => [],
                'recipient_ids' => [],
            ])
            ->assertOk()
            ->assertJsonPath('data.interval_seconds', Service::INTERVAL_10_MINUTES)
            ->assertJsonPath('data.additional_headers_count', 0)
            ->assertJsonPath('data.ssl_expiry_notifications_enabled', false);

        $this->withToken($token)
            ->deleteJson('/api/v1/service-templates/'.$existingTemplate->id)
            ->assertNoContent();
    }

    public function test_service_group_endpoints_support_crud(): void
    {
        $admin = User::factory()->admin()->create();
        $recipient = Recipient::factory()->create([
            'endpoint' => 'mailto://ops@example.com',
        ]);
        $recipientGroup = RecipientGroup::factory()->create();
        $service = Service::factory()->create();
        $token = $this->issueApiKey($admin, ['services:read', 'services:write']);

        $createResponse = $this->withToken($token)
            ->postJson('/api/v1/service-groups', [
                'name' => 'Production',
                'recipient_group_ids' => [$recipientGroup->id],
                'recipient_ids' => [$recipient->id],
            ]);

        $createResponse->assertCreated();
        $groupId = $createResponse->json('data.id');

        ServiceGroup::query()->findOrFail($groupId)->services()->sync([$service->id]);

        $this->withToken($token)
            ->getJson('/api/v1/service-groups/'.$groupId)
            ->assertOk()
            ->assertJsonPath('data.services.0.id', $service->id);

        $this->withToken($token)
            ->patchJson('/api/v1/service-groups/'.$groupId, [
                'name' => 'Production Core',
                'recipient_group_ids' => [$recipientGroup->id],
                'recipient_ids' => [$recipient->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Production Core');

        $this->withToken($token)
            ->deleteJson('/api/v1/service-groups/'.$groupId)
            ->assertNoContent();
    }

    public function test_user_endpoints_support_search_and_protect_admin_rules(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
        $standardUser = User::factory()->create([
            'name' => 'Standard User',
            'email' => 'user@example.com',
        ]);
        $token = $this->issueApiKey($admin, ['users:read', 'users:write']);

        $this->withToken($token)
            ->getJson('/api/v1/users?search=Standard&role=user')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'user@example.com');

        $createResponse = $this->withToken($token)
            ->postJson('/api/v1/users', [
                'name' => 'API User',
                'email' => 'api-user@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => 'user',
            ]);

        $createResponse->assertCreated();
        $createdUserId = $createResponse->json('data.id');

        $this->withToken($token)
            ->patchJson('/api/v1/users/'.$admin->id, [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'role' => 'user',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->withToken($token)
            ->patchJson('/api/v1/users/'.$createdUserId, [
                'name' => 'API User',
                'email' => 'api-user@example.com',
                'role' => 'admin',
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        $this->withToken($token)
            ->deleteJson('/api/v1/users/'.$admin->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user']);

        $this->withToken($token)
            ->deleteJson('/api/v1/users/'.$standardUser->id)
            ->assertNoContent();
    }

    /**
     * Issue a bearer-token API key for tests and return the plain token.
     *
     * @param  array<int, string>  $permissions
     */
    private function issueApiKey(User $user, array $permissions, ?CarbonInterface $expiresAt = null, ?CarbonInterface $revokedAt = null): string
    {
        $plainTextToken = ApiKey::generatePlainTextToken();

        ApiKey::query()->create([
            'name' => fake()->words(2, true),
            'user_id' => $user->id,
            'created_by_id' => $user->id,
            'token_prefix' => substr($plainTextToken, 0, 12),
            'token_hash' => ApiKey::hashToken($plainTextToken),
            'permissions' => $permissions,
            'expires_at' => $expiresAt,
            'last_used_at' => null,
            'revoked_at' => $revokedAt,
        ]);

        return $plainTextToken;
    }
}
