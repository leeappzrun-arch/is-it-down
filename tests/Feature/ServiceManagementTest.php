<?php

namespace Tests\Feature;

use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\Service;
use App\Models\ServiceGroup;
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
        $response->assertSeeText('Expand to review monitoring status, the next check timer, routing details, and effective recipients.');
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

    public function test_admin_users_can_manage_service_groups(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $recipientGroup = RecipientGroup::factory()->create(['name' => 'Operations']);
        $recipient = Recipient::factory()->create(['name' => 'Ops mailbox']);

        $response = Livewire::test('pages::services.index')
            ->set('groupName', 'Production')
            ->set('groupSelectedRecipientGroupIds', [(string) $recipientGroup->id])
            ->set('groupSelectedRecipientIds', [(string) $recipient->id])
            ->call('saveServiceGroup');

        $response->assertHasNoErrors();

        $serviceGroup = ServiceGroup::query()->where('name', 'Production')->first();

        $this->assertNotNull($serviceGroup);
        $this->assertSame([$recipientGroup->id], $serviceGroup->recipientGroups()->pluck('recipient_groups.id')->all());
        $this->assertSame([$recipient->id], $serviceGroup->recipients()->pluck('recipients.id')->all());

        $response
            ->call('editServiceGroup', $serviceGroup->id)
            ->set('groupName', 'Production Primary')
            ->call('saveServiceGroup')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('service_groups', [
            'id' => $serviceGroup->id,
            'name' => 'Production Primary',
        ]);

        $response
            ->call('confirmServiceGroupDeletion', $serviceGroup->id)
            ->assertSet('showDeleteConfirmationModal', true)
            ->assertSet('deleteConfirmationType', 'service-group')
            ->call('deleteConfirmedItem');

        $this->assertDatabaseMissing('service_groups', [
            'id' => $serviceGroup->id,
        ]);
    }

    public function test_editing_a_service_group_dispatches_a_focus_event_for_the_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $serviceGroup = ServiceGroup::factory()->create();

        Livewire::test('pages::services.index')
            ->call('editServiceGroup', $serviceGroup->id)
            ->assertSet('editingServiceGroupId', $serviceGroup->id)
            ->assertDispatched('focus-form', form: 'service-group');
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
        $admin = User::factory()->admin()->create();

        $service = Service::factory()->currentlyDown()->create([
            'name' => 'Marketing site',
            'next_check_at' => now()->addSeconds(45),
            'last_check_reason' => 'Expected HTTP 200 response but received 503.',
            'last_status_changed_at' => now()->subMinutes(5),
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
        $response->assertSee('wire:poll.5s.visible', false);
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

    public function test_admin_users_can_search_services_and_service_groups(): void
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

        ServiceGroup::factory()->create(['name' => 'Production']);
        ServiceGroup::factory()->create(['name' => 'Staging']);

        Livewire::test('pages::services.index')
            ->assertSee('Marketing site')
            ->assertSee('Billing API')
            ->assertSee('Production')
            ->assertSee('Staging')
            ->set('search', 'marketing')
            ->assertSee('Marketing site')
            ->assertDontSee('https://billing.example.com')
            ->assertSee('No service groups match your search.')
            ->set('search', 'production')
            ->assertSee('Production')
            ->assertSee('No services match your search.')
            ->assertDontSee('https://marketing.example.com')
            ->assertDontSee('https://billing.example.com');
    }
}
