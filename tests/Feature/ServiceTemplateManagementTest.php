<?php

namespace Tests\Feature;

use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\ServiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_users_can_visit_the_template_management_page(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('service-templates.index'));

        $response->assertOk();
        $response->assertSeeText('Templates');
        $response->assertSee('sticky top-4 z-20', false);
        $response->assertSeeText('Manage services');
    }

    public function test_non_admin_users_cannot_visit_the_template_management_page(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('service-templates.index'));

        $response->assertForbidden();
    }

    public function test_admin_users_can_create_a_template_with_saved_assignments(): void
    {
        $admin = User::factory()->admin()->create();
        $serviceGroup = ServiceGroup::factory()->create(['name' => 'Production']);
        $recipientGroup = RecipientGroup::factory()->create(['name' => 'Operations']);
        $recipient = Recipient::factory()->create(['name' => 'Ops mailbox']);

        $this->actingAs($admin);

        $response = Livewire::test('pages::services.templates')
            ->set('templateName', 'Website starter')
            ->set('serviceName', 'Marketing site')
            ->set('intervalSeconds', Service::INTERVAL_3_MINUTES)
            ->set('expectType', Service::EXPECT_TEXT)
            ->set('expectValue', 'All systems operational')
            ->set('selectedServiceGroupIds', [(string) $serviceGroup->id])
            ->set('selectedRecipientGroupIds', [(string) $recipientGroup->id])
            ->set('selectedRecipientIds', [(string) $recipient->id])
            ->call('saveTemplate');

        $response->assertHasNoErrors();

        $template = ServiceTemplate::query()->where('name', 'Website starter')->first();

        $this->assertNotNull($template);
        $this->assertSame('Marketing site', $template->serviceName());
        $this->assertSame(Service::INTERVAL_3_MINUTES, $template->intervalSeconds());
        $this->assertSame(Service::EXPECT_TEXT, $template->expectType());
        $this->assertSame('All systems operational', $template->expectValue());
        $this->assertSame([$serviceGroup->id], $template->selectedServiceGroupIds());
        $this->assertSame([$recipientGroup->id], $template->selectedRecipientGroupIds());
        $this->assertSame([$recipient->id], $template->selectedRecipientIds());
    }

    public function test_editing_a_template_dispatches_a_focus_event_for_the_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $template = ServiceTemplate::factory()->create();

        Livewire::test('pages::services.templates')
            ->call('editTemplate', $template->id)
            ->assertSet('editingServiceTemplateId', $template->id)
            ->assertDispatched('focus-form', form: 'service-template');
    }

    public function test_admin_users_confirm_before_deleting_a_template(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $template = ServiceTemplate::factory()->create(['name' => 'Website starter']);

        Livewire::test('pages::services.templates')
            ->call('confirmTemplateDeletion', $template->id)
            ->assertSet('showDeleteConfirmationModal', true)
            ->assertSet('deleteConfirmationId', $template->id)
            ->call('deleteConfirmedItem');

        $this->assertDatabaseMissing('service_templates', [
            'id' => $template->id,
        ]);
    }

    public function test_admin_users_can_search_templates(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        ServiceTemplate::factory()->create([
            'name' => 'Website starter',
            'configuration' => [
                'name' => 'Marketing site',
                'interval_seconds' => Service::INTERVAL_1_MINUTE,
                'expect_type' => null,
                'expect_value' => null,
                'service_group_ids' => [],
                'recipient_group_ids' => [],
                'recipient_ids' => [],
            ],
        ]);

        ServiceTemplate::factory()->create([
            'name' => 'Payroll template',
            'configuration' => [
                'name' => 'Payroll dashboard',
                'interval_seconds' => Service::INTERVAL_5_MINUTES,
                'expect_type' => null,
                'expect_value' => null,
                'service_group_ids' => [],
                'recipient_group_ids' => [],
                'recipient_ids' => [],
            ],
        ]);

        Livewire::test('pages::services.templates')
            ->assertSee('Website starter')
            ->assertSee('Payroll template')
            ->set('search', 'payroll')
            ->assertSee('Payroll template')
            ->assertDontSee('Website starter')
            ->set('search', 'missing-template')
            ->assertSee('No templates match your search.');
    }
}
