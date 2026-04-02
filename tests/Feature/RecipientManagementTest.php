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
            ->set('endpoint', 'mailto://ops@example.com')
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
            ->set('endpoint', 'webhook://hooks.example.com/services/pager-duty')
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
            ->set('endpoint', 'sms://123456789')
            ->call('saveRecipient');

        $response->assertHasErrors(['endpoint']);
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

        $response->call('deleteGroup', $group->id);

        $this->assertDatabaseMissing('recipient_groups', [
            'id' => $group->id,
        ]);
    }
}
