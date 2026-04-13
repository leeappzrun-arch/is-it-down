<?php

namespace Tests\Feature;

use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\Service;
use App\Models\ServiceDowntime;
use App\Models\ServiceGroup;
use App\Models\ServiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_users_can_visit_the_service_management_page(): void
    {
        Service::factory()->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('services.index'));

        $response->assertOk();
        $response->assertSee('sticky top-4 z-20', false);
        $response->assertSee('x-on:scroll.window.throttle.50ms="updateStickyState()"', false);
        $response->assertSee('shadow-lg shadow-zinc-900/10 dark:shadow-black/30', false);
        $response->assertSeeText('Expand to review monitoring status, the next check timer, routing details, and effective recipients.');
        $response->assertSee('x-data="{ expanded: false }"', false);
        $response->assertSee('x-bind:open="expanded"', false);
        $response->assertSeeText('Manage service templates');
    }

    public function test_non_admin_users_cannot_visit_the_service_management_page(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('services.index'));

        $response->assertForbidden();
    }

    public function test_admin_users_can_create_a_service_with_direct_and_group_assignments(): void
    {
        $admin = User::factory()->admin()->create();
        $serviceGroup = ServiceGroup::factory()->create(['name' => 'Production']);
        $recipientGroup = RecipientGroup::factory()->create(['name' => 'Operations']);
        $recipient = Recipient::factory()->create(['name' => 'Ops mailbox']);

        $this->actingAs($admin);

        $response = Livewire::test('pages::services.index')
            ->set('name', 'Marketing site')
            ->set('url', 'example.com/status')
            ->set('intervalSeconds', Service::INTERVAL_3_MINUTES)
            ->set('expectType', Service::EXPECT_TEXT)
            ->set('expectValue', 'All systems operational')
            ->set('additionalHeaders', [
                ['name' => 'X-Monitor', 'value' => 'is-it-down'],
            ])
            ->set('sslExpiryNotificationsEnabled', true)
            ->set('selectedServiceGroupIds', [(string) $serviceGroup->id])
            ->set('selectedRecipientGroupIds', [(string) $recipientGroup->id])
            ->set('selectedRecipientIds', [(string) $recipient->id])
            ->call('saveService');

        $response->assertHasNoErrors();

        $service = Service::query()->where('name', 'Marketing site')->first();

        $this->assertNotNull($service);
        $this->assertSame('https://example.com/status', $service->url);
        $this->assertSame(Service::INTERVAL_3_MINUTES, $service->interval_seconds);
        $this->assertSame(Service::EXPECT_TEXT, $service->expect_type);
        $this->assertSame('All systems operational', $service->expect_value);
        $this->assertSame([['name' => 'X-Monitor', 'value' => 'is-it-down']], $service->configuredAdditionalHeaders());
        $this->assertTrue($service->ssl_expiry_notifications_enabled);
        $this->assertSame([$serviceGroup->id], $service->groups()->pluck('service_groups.id')->all());
        $this->assertSame([$recipientGroup->id], $service->recipientGroups()->pluck('recipient_groups.id')->all());
        $this->assertSame([$recipient->id], $service->recipients()->pluck('recipients.id')->all());
    }

    public function test_invalid_expect_regex_patterns_are_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test('pages::services.index')
            ->set('name', 'Broken regex service')
            ->set('url', 'https://example.com')
            ->set('expectType', Service::EXPECT_REGEX)
            ->set('expectValue', '[invalid-regex')
            ->call('saveService')
            ->assertHasErrors(['expectValue']);
    }

    public function test_editing_a_service_dispatches_a_focus_event_for_the_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $service = Service::factory()->create();

        Livewire::test('pages::services.index')
            ->call('editService', $service->id)
            ->assertSet('editingServiceId', $service->id)
            ->assertDispatched('focus-form', form: 'service');
    }

    public function test_admin_users_confirm_before_deleting_a_service(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $service = Service::factory()->create(['name' => 'Marketing site']);

        Livewire::test('pages::services.index')
            ->call('confirmServiceDeletion', $service->id)
            ->assertSet('showDeleteConfirmationModal', true)
            ->assertSet('deleteConfirmationType', 'service')
            ->call('deleteConfirmedItem');

        $this->assertDatabaseMissing('services', [
            'id' => $service->id,
        ]);
    }

    public function test_admin_users_can_save_an_existing_service_as_a_template(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $serviceGroup = ServiceGroup::factory()->create(['name' => 'Production']);
        $recipientGroup = RecipientGroup::factory()->create(['name' => 'Operations']);
        $recipient = Recipient::factory()->create(['name' => 'Ops mailbox']);
        $service = Service::factory()->expectsText()->create([
            'name' => 'Marketing site',
            'interval_seconds' => Service::INTERVAL_3_MINUTES,
            'additional_headers' => [
                ['name' => 'X-Monitor', 'value' => 'is-it-down'],
            ],
            'ssl_expiry_notifications_enabled' => true,
        ]);

        $service->groups()->sync([$serviceGroup->id]);
        $service->recipientGroups()->sync([$recipientGroup->id]);
        $service->recipients()->sync([$recipient->id]);

        Livewire::test('pages::services.index')
            ->call('promptTemplateCreation', $service->id)
            ->assertSet('showCreateTemplateModal', true)
            ->set('templateName', 'Website starter')
            ->call('createTemplateFromService')
            ->assertHasNoErrors();

        $template = ServiceTemplate::query()->where('name', 'Website starter')->first();

        $this->assertNotNull($template);
        $this->assertSame('Marketing site', $template->serviceName());
        $this->assertSame(Service::INTERVAL_3_MINUTES, $template->intervalSeconds());
        $this->assertSame(Service::EXPECT_TEXT, $template->expectType());
        $this->assertSame('All systems operational', $template->expectValue());
        $this->assertSame([['name' => 'X-Monitor', 'value' => 'is-it-down']], $template->configuredAdditionalHeaders());
        $this->assertTrue($template->sslExpiryNotificationsEnabled());
        $this->assertSame([$serviceGroup->id], $template->selectedServiceGroupIds());
        $this->assertSame([$recipientGroup->id], $template->selectedRecipientGroupIds());
        $this->assertSame([$recipient->id], $template->selectedRecipientIds());
    }

    public function test_service_form_can_be_prefilled_from_a_template(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $serviceGroup = ServiceGroup::factory()->create();
        $recipientGroup = RecipientGroup::factory()->create();
        $recipient = Recipient::factory()->create();
        $template = ServiceTemplate::factory()->create([
            'name' => 'Website starter',
            'configuration' => [
                'name' => 'Marketing site',
                'interval_seconds' => Service::INTERVAL_5_MINUTES,
                'expect_type' => Service::EXPECT_TEXT,
                'expect_value' => 'All systems operational',
                'additional_headers' => [
                    ['name' => 'X-Monitor', 'value' => 'is-it-down'],
                ],
                'ssl_expiry_notifications_enabled' => true,
                'service_group_ids' => [$serviceGroup->id],
                'recipient_group_ids' => [$recipientGroup->id],
                'recipient_ids' => [$recipient->id],
            ],
        ]);

        Livewire::withQueryParams(['template' => $template->id])
            ->test('pages::services.index')
            ->assertSet('loadedTemplateId', $template->id)
            ->assertSet('loadedTemplateName', 'Website starter')
            ->assertSet('name', 'Marketing site')
            ->assertSet('url', '')
            ->assertSet('intervalSeconds', Service::INTERVAL_5_MINUTES)
            ->assertSet('expectType', Service::EXPECT_TEXT)
            ->assertSet('expectValue', 'All systems operational')
            ->assertSet('additionalHeaders', [['name' => 'X-Monitor', 'value' => 'is-it-down']])
            ->assertSet('sslExpiryNotificationsEnabled', true)
            ->assertSet('selectedServiceGroupIds', [(string) $serviceGroup->id])
            ->assertSet('selectedRecipientGroupIds', [(string) $recipientGroup->id])
            ->assertSet('selectedRecipientIds', [(string) $recipient->id]);
    }

    public function test_service_page_shows_effective_recipient_sources(): void
    {
        $admin = User::factory()->admin()->create();
        $directRecipient = Recipient::factory()->create(['name' => 'Direct mailbox', 'endpoint' => 'mailto://direct@example.com']);
        $groupRecipient = Recipient::factory()->create(['name' => 'Group mailbox', 'endpoint' => 'mailto://group@example.com']);
        $serviceGroupRecipient = Recipient::factory()->create(['name' => 'Service group mailbox', 'endpoint' => 'mailto://service-group@example.com']);
        $recipientGroup = RecipientGroup::factory()->create(['name' => 'Leadership']);
        $serviceGroup = ServiceGroup::factory()->create(['name' => 'Production']);
        $service = Service::factory()->create(['name' => 'Marketing site']);

        $recipientGroup->recipients()->sync([$groupRecipient->id]);
        $serviceGroup->recipients()->sync([$serviceGroupRecipient->id]);
        $service->recipients()->sync([$directRecipient->id]);
        $service->recipientGroups()->sync([$recipientGroup->id]);
        $service->groups()->sync([$serviceGroup->id]);

        $response = $this->actingAs($admin)->get(route('services.index'));

        $response->assertOk();
        $response->assertSeeText('Direct mailbox');
        $response->assertSeeText('Group mailbox');
        $response->assertSeeText('Service group mailbox');
        $response->assertSeeText('Direct recipient');
        $response->assertSeeText('Recipient group: Leadership');
        $response->assertSeeText('Service group: Production');
        $response->assertSeeText('Expand to review monitoring status, the next check timer, routing details, and effective recipients.');
    }

    public function test_service_page_shows_monitoring_status_and_next_check_timer(): void
    {
        $referenceTime = now();

        $this->travelTo($referenceTime);

        $admin = User::factory()->admin()->create();

        $service = Service::factory()->currentlyDown()->create([
            'name' => 'Marketing site',
            'next_check_at' => $referenceTime->copy()->addSeconds(44),
            'last_check_reason' => 'Expected HTTP 200 response but received 503.',
            'last_status_changed_at' => $referenceTime->copy()->subMinutes(5),
        ]);

        $expectedNextCheckSummary = $service->nextCheckSummary();

        $response = $this->actingAs($admin)->get(route('services.index'));

        $response->assertOk();
        $response->assertSeeText('Current status');
        $response->assertSeeText('Down for 5 minutes');
        $response->assertSeeText($expectedNextCheckSummary);
        $response->assertSeeText('Latest reason');
        $response->assertSeeText('Status duration: 5 minutes');
        $response->assertSeeText('Expected HTTP 200 response but received 503.');
        $response->assertSeeText('30-day uptime');
        $response->assertSee('wire:poll.5s.visible', false);

        $this->travelBack();
    }

    public function test_service_page_shows_checking_for_overdue_services(): void
    {
        $admin = User::factory()->admin()->create();

        Service::factory()->currentlyUp()->create([
            'name' => 'Billing API',
            'next_check_at' => now()->subSecond(),
        ]);

        $response = $this->actingAs($admin)->get(route('services.index'));

        $response->assertOk();
        $response->assertSeeText('Checking...');
    }

    public function test_service_page_rounds_up_status_durations_to_whole_seconds(): void
    {
        $referenceTime = now();

        $this->travelTo($referenceTime);

        $admin = User::factory()->admin()->create();

        Service::factory()->currentlyDown()->create([
            'name' => 'Fast status',
            'last_status_changed_at' => $referenceTime,
        ]);

        $response = $this->actingAs($admin)->get(route('services.index'));

        $response->assertOk();
        $response->assertSeeText('Down for 1 second');
        $response->assertSeeText('Status duration: 1 second');

        $this->travelBack();
    }

    public function test_admin_users_can_search_services(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Service::factory()->create([
            'name' => 'Marketing site',
            'url' => 'https://marketing.example.com',
        ]);

        Service::factory()->create([
            'name' => 'Billing API',
            'url' => 'https://billing.example.com',
        ]);

        Livewire::test('pages::services.index')
            ->assertSee('Marketing site')
            ->assertSee('Billing API')
            ->set('search', 'marketing')
            ->assertSee('Marketing site')
            ->assertDontSee('https://billing.example.com')
            ->set('search', 'missing-service')
            ->assertSee('No services match your search.')
            ->assertDontSee('https://marketing.example.com')
            ->assertDontSee('https://billing.example.com');
    }

    public function test_service_page_shows_downtime_history_records(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create([
            'name' => 'Billing API',
            'url' => 'https://billing.example.com/status',
        ]);

        ServiceDowntime::factory()->create([
            'service_id' => $service->id,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHours(2)->subMinutes(45),
            'started_reason' => 'Expected HTTP 200 response but received 503.',
            'recovery_reason' => 'Received an HTTP 200 response.',
            'ai_summary' => 'The upstream likely experienced a short maintenance window.',
        ]);

        $response = $this->actingAs($admin)->get(route('services.index'));

        $response->assertOk();
        $response->assertSeeText('Downtime history');
        $response->assertSeeText('The upstream likely experienced a short maintenance window.');
        $response->assertSeeText('Recovered after 15 minutes');
    }
}
