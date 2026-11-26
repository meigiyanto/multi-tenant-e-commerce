<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StoreNotificationController extends Controller
{
    public function index(Request $request, Team $team, Store $store): Response
    {
        $this->authorizeStore($team, $store);
        $notifications = $request->user()->notifications()
            ->where('data->store_id', $store->id)
            ->latest()
            ->paginate(25);

        return Inertia::render('stores/notifications/index', [
            'team' => ['name' => $team->name, 'slug' => $team->slug],
            'store' => ['id' => $store->id, 'name' => $store->name],
            'notifications' => $notifications->through(fn ($item): array => [
                'id' => $item->id,
                'event' => data_get($item->data, 'event'),
                'title' => data_get($item->data, 'title'),
                'message' => data_get($item->data, 'message'),
                'read_at' => $item->read_at?->toISOString(),
                'created_at' => $item->created_at?->toISOString(),
            ]),
            'unreadCount' => $request->user()->unreadNotifications()->where('data->store_id', $store->id)->count(),
        ]);
    }

    public function read(Request $request, Team $team, Store $store, string $notification): RedirectResponse
    {
        $this->authorizeStore($team, $store);
        $item = $request->user()->notifications()
            ->where('data->store_id', $store->id)
            ->whereKey($notification)
            ->firstOrFail();
        $item->markAsRead();
        $url = (string) data_get($item->data, 'url', '');

        return redirect()->to(str_starts_with($url, '/settings/teams/') ? $url : route('teams.stores.notifications.index', [$team, $store]));
    }

    public function readAll(Request $request, Team $team, Store $store): RedirectResponse
    {
        $this->authorizeStore($team, $store);
        $request->user()->unreadNotifications()
            ->where('data->store_id', $store->id)
            ->update(['read_at' => now()]);

        return to_route('teams.stores.notifications.index', [$team, $store]);
    }

    private function authorizeStore(Team $team, Store $store): void
    {
        abort_unless((int) $store->team_id === (int) $team->id, 404);
        Gate::authorize('update', $store);
    }
}
