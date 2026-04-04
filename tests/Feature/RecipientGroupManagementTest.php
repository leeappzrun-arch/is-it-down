<?php

namespace Tests\Feature;

use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecipientGroupManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_users_can_visit_the_recipient_group_management_page(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('recipient-groups.index'));

        $response->assertOk();
        $response->assertSee('sticky top-4 z-20', false);
        $response->assertSee('Manage recipients');
    }

    public function test_non_admin_users_cannot_visit_the_recipient_group_management_page(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('recipient-groups.index'));

        $response->assertForbidden();
    }

    public function test_admin_users_can_manage_recipient_groups_and_members_from_the_group_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $primaryRecipient = Recipient::factory()->create(['name' => 'Ops mailbox']);
        $secondaryRecipient = Recipient::factory()->create(['name' => 'Pager duty']);

        $response = Livewire::test('pages::recipients.groups')
            ->set('groupName', 'Operations')
            ->set('selectedRecipientIds', [(string) $primaryRecipient->id])
            ->call('saveGroup');

        $response->assertHasNoErrors();

        $group = RecipientGroup::query()->where('name', 'Operations')->first();

        $this->assertNotNull($group);
        $this->assertSame([$primaryRecipient->id], $group->recipients()->pluck('recipients.id')->all());

        $response
            ->call('editGroup', $group->id)
            ->set('groupName', 'Primary Operations')
            ->set('selectedRecipientIds', [(string) $primaryRecipient->id, (string) $secondaryRecipient->id])
            ->call('saveGroup')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('recipient_groups', [
            'id' => $group->id,
            'name' => 'Primary Operations',
        ]);

        $this->assertSame(
            [$primaryRecipient->id, $secondaryRecipient->id],
            $group->fresh()->recipients()->orderBy('recipients.id')->pluck('recipients.id')->all()
        );
    }

    public function test_editing_a_recipient_group_dispatches_a_focus_event_for_the_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $group = RecipientGroup::factory()->create();

        Livewire::test('pages::recipients.groups')
            ->call('editGroup', $group->id)
            ->assertSet('editingGroupId', $group->id)
            ->assertDispatched('focus-form', form: 'recipient-group');
    }

    public function test_admin_users_confirm_before_deleting_a_recipient_group(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $group = RecipientGroup::factory()->create(['name' => 'Operations']);

        Livewire::test('pages::recipients.groups')
            ->call('confirmGroupDeletion', $group->id)
            ->assertSet('showDeleteConfirmationModal', true)
            ->assertSet('deleteConfirmationId', $group->id)
            ->call('deleteConfirmedItem');

        $this->assertDatabaseMissing('recipient_groups', [
            'id' => $group->id,
        ]);
    }

    public function test_admin_users_can_search_recipient_groups(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $operationsRecipient = Recipient::factory()->create(['name' => 'Ops mailbox']);
        $operationsGroup = RecipientGroup::factory()->create(['name' => 'Operations']);
        $operationsGroup->recipients()->sync([$operationsRecipient->id]);

        Livewire::test('pages::recipients.groups')
            ->assertSee('Operations')
            ->set('search', 'operations')
            ->assertSee('Operations')
            ->set('search', 'missing-group')
            ->assertSee('No recipient groups match your search.');
    }
}
