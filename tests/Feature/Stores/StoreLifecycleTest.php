<?php

namespace Tests\Feature\Stores;

use App\Enums\StoreStatus;
use App\Models\Team;
use App\Services\Stores\StoreLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StoreLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_can_be_activated_suspended_and_reactivated(): void
    {
        $store = Team::factory()->create()->stores()->create([
            'name' => 'Lifecycle Store',
            'slug' => 'lifecycle-store',
            'status' => StoreStatus::Draft->value,
        ]);
        $service = app(StoreLifecycleService::class);

        $service->transition($store, StoreStatus::Active);
        $this->assertSame(StoreStatus::Active->value, $store->fresh()->status);

        $service->transition($store->fresh(), StoreStatus::Suspended);
        $this->assertSame(StoreStatus::Suspended->value, $store->fresh()->status);

        $service->transition($store->fresh(), StoreStatus::Active);
        $this->assertSame(StoreStatus::Active->value, $store->fresh()->status);
    }

    public function test_archived_store_cannot_be_reactivated(): void
    {
        $store = Team::factory()->create()->stores()->create([
            'name' => 'Archived Store',
            'slug' => 'archived-store',
            'status' => StoreStatus::Archived->value,
        ]);

        $this->expectException(ValidationException::class);

        app(StoreLifecycleService::class)->transition($store, StoreStatus::Active);
    }
}
