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

class ServiceGroupManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_users_can_visit_the_service_group_management_page(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('service-groups.index'));

        $response->assertOk();
        $response->assertSee('sticky top-4 z-20', false);
        $response->assertSee('Manage services');
    }

    public function test_non_admin_users_cannot_visit_the_service_group_management_page(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('service-groups.index'));

        $response->assertForbidden();
    }

    public function test_admin_users_can_manage_service_groups_and_linked_services_from_the_group_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $service = Service::factory()->create(['name' => 'Billing API']);
        $secondService = Service::factory()->create(['name' => 'Marketing site']);
        $recipientGroup = RecipientGroup::factory()->create(['name' => 'Operations']);
        $recipient = Recipient::factory()->create(['name' => 'Ops mailbox']);

        $response = Livewire::test('pages::services.groups')
            ->set('groupName', 'Production')
            ->set('selectedServiceIds', [(string) $service->id])
            ->set('groupSelectedRecipientGroupIds', [(string) $recipientGroup->id])
            ->set('groupSelectedRecipientIds', [(string) $recipient->id])
            ->call('saveServiceGroup');

        $response->assertHasNoErrors();

        $serviceGroup = ServiceGroup::query()->where('name', 'Production')->first();

        $this->assertNotNull($serviceGroup);
        $this->assertSame([$service->id], $serviceGroup->services()->pluck('services.id')->all());
        $this->assertSame([$recipientGroup->id], $serviceGroup->recipientGroups()->pluck('recipient_groups.id')->all());
        $this->assertSame([$recipient->id], $serviceGroup->recipients()->pluck('recipients.id')->all());

        $response
            ->call('editServiceGroup', $serviceGroup->id)
            ->set('groupName', 'Production Primary')
            ->set('selectedServiceIds', [(string) $service->id, (string) $secondService->id])
            ->call('saveServiceGroup')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('service_groups', [
            'id' => $serviceGroup->id,
            'name' => 'Production Primary',
        ]);

        $this->assertSame(
            [$service->id, $secondService->id],
            $serviceGroup->fresh()->services()->orderBy('services.id')->pluck('services.id')->all()
        );
    }

    public function test_editing_a_service_group_dispatches_a_focus_event_for_the_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $serviceGroup = ServiceGroup::factory()->create();

        Livewire::test('pages::services.groups')
            ->call('editServiceGroup', $serviceGroup->id)
            ->assertSet('editingServiceGroupId', $serviceGroup->id)
            ->assertDispatched('focus-form', form: 'service-group');
    }

    public function test_admin_users_confirm_before_deleting_a_service_group(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $serviceGroup = ServiceGroup::factory()->create(['name' => 'Production']);

        Livewire::test('pages::services.groups')
            ->call('confirmServiceGroupDeletion', $serviceGroup->id)
            ->assertSet('showDeleteConfirmationModal', true)
            ->assertSet('deleteConfirmationId', $serviceGroup->id)
            ->call('deleteConfirmedItem');

        $this->assertDatabaseMissing('service_groups', [
            'id' => $serviceGroup->id,
        ]);
    }

    public function test_admin_users_can_search_service_groups(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $billingService = Service::factory()->create(['name' => 'Billing API']);
        $marketingService = Service::factory()->create(['name' => 'Marketing site']);

        $productionGroup = ServiceGroup::factory()->create(['name' => 'Production']);
        $stagingGroup = ServiceGroup::factory()->create(['name' => 'Staging']);

        $productionGroup->services()->sync([$billingService->id]);
        $stagingGroup->services()->sync([$marketingService->id]);

        Livewire::test('pages::services.groups')
            ->assertSee('Production')
            ->assertSee('Staging')
            ->set('search', 'billing')
            ->assertSee('Production')
            ->assertDontSee('Staging')
            ->set('search', 'missing-group')
            ->assertSee('No service groups match your search.');
    }
}
