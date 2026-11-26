<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()->notifications()->latest()->paginate(25);

        return Inertia::render('platform/notifications/index', [
            'notifications' => $notifications->through(fn ($item): array => [
                'id' => $item->id, 'event' => data_get($item->data, 'event'),
                'title' => data_get($item->data, 'title'), 'message' => data_get($item->data, 'message'),
                'url' => data_get($item->data, 'url'), 'read_at' => $item->read_at?->toISOString(),
                'created_at' => $item->created_at?->toISOString(),
            ]),
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return redirect()->to((string) data_get($item->data, 'url', route('platform.admin.notifications.index')));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);
        return to_route('platform.admin.notifications.index');
    }
}
