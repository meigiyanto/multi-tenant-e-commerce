<?php

namespace Tests\Feature\Platform;

use App\Models\MerchantApplication;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Notifications\Merchant\MerchantApplicationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MerchantApplicationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_applicant_is_notified_when_application_is_approved(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        PlatformAdmin::query()->create(['user_id' => $admin->id]);
        $applicant = User::factory()->create();
        $application = MerchantApplication::query()->create([
            'user_id' => $applicant->id,
            'business_name' => 'Nuvora Store',
            'business_description' => 'Online retail store',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('platform.admin.merchant-applications.update', $application), [
                'status' => 'approved',
                'review_notes' => 'Your application has been verified.',
            ])
            ->assertRedirect(route('platform.admin.merchant-applications.index'));

        Notification::assertSentTo($applicant, MerchantApplicationResult::class, fn (MerchantApplicationResult $notification): bool => $notification->toDatabase($applicant)['event'] === 'merchant_application_approved');
    }

    public function test_applicant_is_notified_when_application_is_rejected(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        PlatformAdmin::query()->create(['user_id' => $admin->id]);
        $applicant = User::factory()->create();
        $application = MerchantApplication::query()->create([
            'user_id' => $applicant->id,
            'business_name' => 'Nuvora Store',
            'business_description' => 'Online retail store',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('platform.admin.merchant-applications.update', $application), [
                'status' => 'rejected',
                'review_notes' => 'Please provide additional business information.',
            ])
            ->assertRedirect(route('platform.admin.merchant-applications.index'));

        Notification::assertSentTo($applicant, MerchantApplicationResult::class, fn (MerchantApplicationResult $notification): bool => $notification->toDatabase($applicant)['event'] === 'merchant_application_rejected');
    }
}
