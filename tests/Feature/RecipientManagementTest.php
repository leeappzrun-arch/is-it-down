<?php

namespace Tests\Feature;

use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecipientManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_users_can_visit_the_recipient_management_page(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('recipients.index'));

        $response->assertOk();
        $response->assertSee('sticky top-4 z-20', false);
        $response->assertSee('x-on:scroll.window.throttle.50ms="updateStickyState()"', false);
        $response->assertSee('shadow-lg shadow-zinc-900/10 dark:shadow-black/30', false);
    }

    public function test_non_admin_users_cannot_visit_the_recipient_management_page(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('recipients.index'));

        $response->assertForbidden();
    }

    public function test_admin_users_can_create_a_mail_recipient_and_assign_it_to_multiple_groups(): void
    {
        $admin = User::factory()->admin()->create();
        $primaryGroup = RecipientGroup::factory()->create(['name' => 'Primary']);
        $secondaryGroup = RecipientGroup::factory()->create(['name' => 'Secondary']);

        $this->actingAs($admin);

        $response = Livewire::test('pages::recipients.index')
            ->set('name', 'Ops mailbox')
            ->set('endpointType', Recipient::TYPE_MAIL)
            ->set('endpointTarget', 'ops@example.com')
            ->set('selectedGroupIds', [(string) $primaryGroup->id, (string) $secondaryGroup->id])
            ->call('saveRecipient');

        $response->assertHasNoErrors();

        $recipient = Recipient::query()->where('name', 'Ops mailbox')->first();

        $this->assertNotNull($recipient);
        $this->assertSame('mailto://ops@example.com', $recipient->endpoint);
        $this->assertSame(
            [$primaryGroup->id, $secondaryGroup->id],
            $recipient->groups()->orderBy('recipient_groups.id')->pluck('recipient_groups.id')->all()
        );
    }

    public function test_admin_users_can_create_a_webhook_recipient_with_authentication(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $response = Livewire::test('pages::recipients.index')
            ->set('name', 'Pager duty')
            ->set('endpointType', Recipient::TYPE_WEBHOOK)
            ->set('endpointTarget', 'hooks.example.com/services/pager-duty')
            ->set('webhookAuthType', Recipient::WEBHOOK_AUTH_BEARER)
            ->set('webhookAuthToken', 'top-secret-token')
            ->call('saveRecipient');

        $response->assertHasNoErrors();

        $recipient = Recipient::query()->where('name', 'Pager duty')->first();

        $this->assertNotNull($recipient);
        $this->assertSame(Recipient::WEBHOOK_AUTH_BEARER, $recipient->webhook_auth_type);
        $this->assertSame('top-secret-token', $recipient->webhook_auth_token);
    }

    public function test_invalid_endpoints_are_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = Livewire::test('pages::recipients.index')
            ->set('name', 'Broken')
            ->set('endpointType', Recipient::TYPE_WEBHOOK)
            ->set('endpointTarget', 'not a valid webhook')
            ->call('saveRecipient');

        $response->assertHasErrors(['endpointTarget']);
    }

    public function test_webhook_authentication_fields_render_as_soon_as_webhook_protocol_is_selected(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test('pages::recipients.index')
            ->assertDontSee('Webhook authentication')
            ->set('endpointType', Recipient::TYPE_WEBHOOK)
            ->assertSee('Webhook authentication');
    }

    public function test_webhook_authentication_fields_render_as_soon_as_authentication_type_is_selected(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test('pages::recipients.index')
            ->set('endpointType', Recipient::TYPE_WEBHOOK)
            ->assertDontSee('Header name')
            ->set('webhookAuthType', Recipient::WEBHOOK_AUTH_HEADER)
            ->assertSee('Header name');
    }

    public function test_editing_a_recipient_dispatches_a_focus_event_for_the_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $recipient = Recipient::factory()->create();

        Livewire::test('pages::recipients.index')
            ->call('editRecipient', $recipient->id)
            ->assertSet('editingRecipientId', $recipient->id)
            ->assertDispatched('focus-form', form: 'recipient');
    }

    public function test_admin_users_confirm_before_deleting_a_recipient(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $recipient = Recipient::factory()->create(['name' => 'Pager duty']);

        Livewire::test('pages::recipients.index')
            ->call('confirmRecipientDeletion', $recipient->id)
            ->assertSet('showDeleteConfirmationModal', true)
            ->assertSet('deleteConfirmationType', 'recipient')
            ->call('deleteConfirmedItem');

        $this->assertDatabaseMissing('recipients', [
            'id' => $recipient->id,
        ]);
    }

    public function test_admin_users_can_manage_groups(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = Livewire::test('pages::recipients.index')
            ->set('groupName', 'Operations')
            ->call('saveGroup');

        $response->assertHasNoErrors();

        $group = RecipientGroup::query()->where('name', 'Operations')->first();

        $this->assertNotNull($group);

        $response
            ->call('editGroup', $group->id)
            ->set('groupName', 'On-call Operations')
            ->call('saveGroup')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('recipient_groups', [
            'id' => $group->id,
            'name' => 'On-call Operations',
        ]);

        $response
            ->call('confirmGroupDeletion', $group->id)
            ->assertSet('showDeleteConfirmationModal', true)
            ->assertSet('deleteConfirmationType', 'group')
            ->call('deleteConfirmedItem');

        $this->assertDatabaseMissing('recipient_groups', [
            'id' => $group->id,
        ]);
    }

    public function test_editing_a_group_dispatches_a_focus_event_for_the_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $group = RecipientGroup::factory()->create();

        Livewire::test('pages::recipients.index')
            ->call('editGroup', $group->id)
            ->assertSet('editingGroupId', $group->id)
            ->assertDispatched('focus-form', form: 'group');
    }

    public function test_admin_users_can_search_recipients_and_groups(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Recipient::factory()->create([
            'name' => 'Ops mailbox',
            'endpoint' => 'mailto://ops@example.com',
        ]);

        Recipient::factory()->create([
            'name' => 'Pager duty',
            'endpoint' => 'webhook://hooks.example.com/pager-duty',
        ]);

        RecipientGroup::factory()->create(['name' => 'Operations']);
        RecipientGroup::factory()->create(['name' => 'Finance']);

        Livewire::test('pages::recipients.index')
            ->assertSee('Ops mailbox')
            ->assertSee('Pager duty')
            ->assertSee('Operations')
            ->assertSee('Finance')
            ->set('search', 'pager')
            ->assertSee('Pager duty')
            ->assertDontSee('ops@example.com')
            ->assertSee('No groups match your search.')
            ->set('search', 'finance')
            ->assertSee('Finance')
            ->assertSee('No recipients match your search.')
            ->assertDontSee('ops@example.com')
            ->assertDontSee('hooks.example.com/pager-duty');
    }
}
