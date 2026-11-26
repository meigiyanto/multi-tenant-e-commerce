<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformAdmin;
use App\Models\User;
use App\Notifications\Platform\PlatformEventNotification;
use App\Services\Notifications\PlatformNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_their_notifications(): void
    {
        $admin = User::factory()->create();
        PlatformAdmin::query()->create(['user_id' => $admin->id]);
        $admin->notify(new PlatformEventNotification([
            'event' => 'refund_requested',
            'title' => 'New refund request',
            'message' => 'A customer requested a refund.',
            'url' => '/platform/admin/refunds',
        ]));

        $this->actingAs($admin)
            ->get(route('platform.admin.notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('platform/notifications/index')
                ->where('unreadCount', 1)
                ->has('notifications.data', 1)
                ->where('notifications.data.0.event', 'refund_requested')
            );
    }

    public function test_non_platform_admin_cannot_access_platform_notifications(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('platform.admin.notifications.index'))
            ->assertForbidden();
    }

    public function test_platform_admin_can_mark_one_notification_as_read(): void
    {
        $admin = User::factory()->create();
        PlatformAdmin::query()->create(['user_id' => $admin->id]);
        $notification = new PlatformEventNotification([
            'event' => 'refund_requested',
            'title' => 'New refund request',
            'message' => 'A customer requested a refund.',
            'url' => route('platform.admin.refunds.index'),
        ]);
        $admin->notify($notification);
        $notificationId = $admin->notifications()->firstOrFail()->id;

        $this->actingAs($admin)
            ->get(route('platform.admin.notifications.read', $notificationId))
            ->assertRedirect(route('platform.admin.refunds.index'));

        $this->assertNotNull($admin->notifications()->findOrFail($notificationId)->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $admin = User::factory()->create();
        $otherAdmin = User::factory()->create();
        PlatformAdmin::query()->create(['user_id' => $admin->id]);
        PlatformAdmin::query()->create(['user_id' => $otherAdmin->id]);
        $otherAdmin->notify(new PlatformEventNotification([
            'event' => 'order_created',
            'title' => 'New order',
            'message' => 'A new order was placed.',
            'url' => '/platform/admin/notifications',
        ]));
        $notificationId = $otherAdmin->notifications()->firstOrFail()->id;

        $this->actingAs($admin)
            ->get(route('platform.admin.notifications.read', $notificationId))
            ->assertNotFound();

        $this->assertNull($otherAdmin->notifications()->findOrFail($notificationId)->read_at);
    }

    public function test_platform_admin_can_mark_all_notifications_as_read(): void
    {
        $admin = User::factory()->create();
        PlatformAdmin::query()->create(['user_id' => $admin->id]);
        $admin->notify(new PlatformEventNotification(['title' => 'First']));
        $admin->notify(new PlatformEventNotification(['title' => 'Second']));

        $this->actingAs($admin)
            ->post(route('platform.admin.notifications.read-all'))
            ->assertRedirect(route('platform.admin.notifications.index'));

        $this->assertSame(0, $admin->unreadNotifications()->count());
        $this->assertSame(2, $admin->readNotifications()->count());
    }

    public function test_platform_notification_service_only_notifies_platform_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $regularUser = User::factory()->create();
        PlatformAdmin::query()->create(['user_id' => $admin->id]);

        app(PlatformNotificationService::class)->notifyAdmins([
            'event' => 'order_created',
            'title' => 'New order',
            'message' => 'A new order was placed.',
            'url' => '/platform/admin/notifications',
        ]);

        Notification::assertSentTo($admin, PlatformEventNotification::class);
        Notification::assertNotSentTo($regularUser, PlatformEventNotification::class);
    }
}
