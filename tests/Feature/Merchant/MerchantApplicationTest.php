<?php

namespace Tests\Feature\Merchant;

use App\Models\MerchantApplication;
use App\Enums\TeamRole;
use App\Models\PlatformAdmin;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MerchantApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_customer_can_submit_a_merchant_application_but_cannot_create_a_workspace_until_approved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('merchant.application.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('merchant/application')->where('canApply', true));

        $this->actingAs($user)->post(route('merchant.application.store'), [
            'business_name' => 'Nuvora Labs',
            'business_description' => 'A retail business.',
        ])->assertRedirect(route('merchant.application.show'));

        $this->assertDatabaseHas('merchant_applications', [
            'user_id' => $user->id,
            'business_name' => 'Nuvora Labs',
            'status' => 'pending',
        ]);

        $this->actingAs($user)->post(route('teams.store'), ['name' => 'Nuvora Workspace'])->assertForbidden();
    }

    public function test_platform_admin_can_approve_application_and_customer_can_create_workspace(): void
    {
        $user = User::factory()->create();
        $application = MerchantApplication::query()->create([
            'user_id' => $user->id,
            'business_name' => 'Velora',
            'status' => 'pending',
        ]);
        $admin = User::factory()->create();
        PlatformAdmin::query()->create(['user_id' => $admin->id]);

        $this->actingAs($admin)
            ->patch(route('platform.admin.merchant-applications.update', $application), [
                'status' => 'approved',
                'review_notes' => 'Approved.',
            ])
            ->assertRedirect(route('platform.admin.merchant-applications.index'));

        $this->assertDatabaseHas('merchant_applications', [
            'id' => $application->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);

        $this->actingAs($user)
            ->post(route('teams.store'), ['name' => 'Velora Workspace'])
            ->assertRedirect();

        $this->assertDatabaseHas('teams', ['name' => 'Velora Workspace', 'is_personal' => false]);
    }


    public function test_customer_cannot_create_a_store_without_merchant_approval(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['is_personal' => false]);
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

        $this->actingAs($user)
            ->post(route('teams.stores.store', $team), [])
            ->assertForbidden();

        $this->assertDatabaseCount('stores', 0);
    }

    public function test_rejected_merchant_can_resubmit_an_application(): void
    {
        $user = User::factory()->create();
        MerchantApplication::query()->create([
            'user_id' => $user->id,
            'business_name' => 'Old Business Name',
            'status' => 'rejected',
            'review_notes' => 'Please provide more details.',
        ]);

        $this->actingAs($user)
            ->post(route('merchant.application.store'), [
                'business_name' => 'Updated Business Name',
                'business_description' => 'Updated business details.',
            ])
            ->assertRedirect(route('merchant.application.show'));

        $this->assertDatabaseHas('merchant_applications', [
            'user_id' => $user->id,
            'business_name' => 'Updated Business Name',
            'status' => 'pending',
            'reviewed_by' => null,
            'review_notes' => null,
        ]);
    }

    public function test_non_platform_user_cannot_review_merchant_applications(): void
    {
        $user = User::factory()->create();
        $application = MerchantApplication::query()->create([
            'user_id' => User::factory()->create()->id,
            'business_name' => 'BelanjaKu',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->patch(route('platform.admin.merchant-applications.update', $application), ['status' => 'approved'])
            ->assertForbidden();

        $this->assertDatabaseHas('merchant_applications', ['id' => $application->id, 'status' => 'pending']);
    }
}
