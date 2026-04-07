<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\ServiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        Recipient::factory()->count(2)->create();
        RecipientGroup::factory()->count(3)->create();
        Service::factory()->currentlyDown()->create([
            'name' => 'Billing API',
            'url' => 'https://billing.example.com',
            'last_status_changed_at' => now()->subMinutes(5),
        ]);
        Service::factory()->currentlyUp()->count(3)->create();
        ServiceTemplate::factory()->count(2)->create();
        ServiceGroup::factory()->count(5)->create();
        ApiKey::factory()->count(4)->create([
            'user_id' => $user->id,
            'created_by_id' => $user->id,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSeeText('Dashboard');
        $response->assertSeeText('Service status');
        $response->assertSeeText('Billing API');
        $response->assertSeeText('https://billing.example.com');
        $response->assertSeeText('Down for 5 minutes');
        $response->assertSeeTextInOrder(['Recipients', '2', 'Recipient groups', '3', 'Services', '4', 'Templates', '2', 'Service groups', '5', 'Users', '1', 'API Keys', '4']);
        $response->assertSeeText('View only');
        $response->assertDontSee(route('recipients.index'), false);
        $response->assertDontSee(route('recipient-groups.index'), false);
        $response->assertDontSee(route('services.index'), false);
        $response->assertDontSee(route('service-templates.index'), false);
        $response->assertDontSee(route('service-groups.index'), false);
        $response->assertDontSee(route('users.index'), false);
        $response->assertDontSee(route('api-keys.index'), false);
    }

    public function test_admin_users_see_monitoring_and_access_navigation_groups(): void
    {
        $admin = User::factory()->admin()->create();
        Recipient::factory()->count(2)->create();
        RecipientGroup::factory()->count(3)->create();
        Service::factory()->currentlyDown()->create([
            'name' => 'Billing API',
            'url' => 'https://billing.example.com',
            'last_status_changed_at' => now()->subMinutes(5),
        ]);
        Service::factory()->currentlyUp()->count(3)->create();
        ServiceTemplate::factory()->count(2)->create();
        ServiceGroup::factory()->count(5)->create();
        User::factory()->count(2)->create();
        ApiKey::factory()->count(4)->create([
            'user_id' => $admin->id,
            'created_by_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeText('Monitoring');
        $response->assertSeeText('Access');
        $response->assertSeeText('Dashboard');
        $response->assertSeeText('Recipients');
        $response->assertSeeText('Recipient groups');
        $response->assertSeeText('Services');
        $response->assertSeeText('Templates');
        $response->assertSeeText('Service groups');
        $response->assertSeeText('Users');
        $response->assertSeeText('API Keys');
        $response->assertSeeTextInOrder(['Recipients', '2', 'Recipient groups', '3', 'Services', '4', 'Templates', '2', 'Service groups', '5', 'Users', '3', 'API Keys', '4']);
        $response->assertDontSeeText('Platform');
        $response->assertSee(route('recipients.index'), false);
        $response->assertSee(route('recipient-groups.index'), false);
        $response->assertSee(route('services.index'), false);
        $response->assertSee(route('service-templates.index'), false);
        $response->assertSee(route('service-groups.index'), false);
        $response->assertSee(route('users.index'), false);
        $response->assertSee(route('api-keys.index'), false);
        $response->assertDontSeeText('View only');
        $response->assertSeeText('Service status');
        $response->assertSeeText('Billing API');
        $response->assertSeeText('Down for 5 minutes');
        $response->assertSeeText('Manage services');
    }
}
