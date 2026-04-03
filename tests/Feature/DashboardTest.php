<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Recipient;
use App\Models\RecipientGroup;
use App\Models\Service;
use App\Models\ServiceGroup;
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
        Service::factory()->count(4)->create();
        ServiceGroup::factory()->count(5)->create();
        ApiKey::factory()->count(4)->create([
            'user_id' => $user->id,
            'created_by_id' => $user->id,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSeeText('Dashboard');
        $response->assertSeeTextInOrder(['Recipients', '2', 'Recipient groups', '3', 'Services', '4', 'Service groups', '5', 'Users', '1', 'API Keys', '4']);
        $response->assertSeeText('View only');
        $response->assertDontSee(route('recipients.index'), false);
        $response->assertDontSee(route('services.index'), false);
        $response->assertDontSee(route('users.index'), false);
        $response->assertDontSee(route('api-keys.index'), false);
    }

    public function test_admin_users_see_monitoring_and_access_navigation_groups(): void
    {
        $admin = User::factory()->admin()->create();
        Recipient::factory()->count(2)->create();
        RecipientGroup::factory()->count(3)->create();
        Service::factory()->count(4)->create();
        ServiceGroup::factory()->count(5)->create();
        User::factory()->count(2)->create();
        ApiKey::factory()->count(4)->create([
            'user_id' => $admin->id,
            'created_by_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeTextInOrder(['Monitoring', 'Dashboard', 'Recipients', 'Services', 'Access', 'Users', 'API Keys']);
        $response->assertSeeTextInOrder(['Recipients', '2', 'Recipient groups', '3', 'Services', '4', 'Service groups', '5', 'Users', '3', 'API Keys', '4']);
        $response->assertDontSeeText('Platform');
        $response->assertSee(route('recipients.index'), false);
        $response->assertSee(route('services.index'), false);
        $response->assertSee(route('users.index'), false);
        $response->assertSee(route('api-keys.index'), false);
        $response->assertDontSeeText('View only');
    }
}
