<?php

namespace Tests\Feature\Stores;

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_can_own_multiple_stores(): void
    {
        $team = Team::factory()->create();

        $firstStore = $team->stores()->create([
            'name' => 'Store One',
            'slug' => 'store-one',
        ]);

        $secondStore = $team->stores()->create([
            'name' => 'Store Two',
            'slug' => 'store-two',
        ]);

        $this->assertCount(2, $team->stores()->get());
        $this->assertSame($team->id, $firstStore->team_id);
        $this->assertSame($team->id, $secondStore->team_id);
    }

    public function test_store_slug_is_unique_per_team_not_globally(): void
    {
        $firstTeam = Team::factory()->create();
        $secondTeam = Team::factory()->create();

        $firstStore = $firstTeam->stores()->create([
            'name' => 'Main Store',
            'slug' => 'main-store',
        ]);

        $secondStore = $secondTeam->stores()->create([
            'name' => 'Main Store',
            'slug' => 'main-store',
        ]);

        $this->assertDatabaseHas('stores', [
            'id' => $firstStore->id,
            'team_id' => $firstTeam->id,
            'slug' => 'main-store',
        ]);

        $this->assertDatabaseHas('stores', [
            'id' => $secondStore->id,
            'team_id' => $secondTeam->id,
            'slug' => 'main-store',
        ]);
    }

    public function test_store_can_have_multiple_normalized_domains(): void
    {
        $team = Team::factory()->create();

        $store = $team->stores()->create([
            'name' => 'Main Store',
            'slug' => 'main-store',
        ]);

        $store->domains()->create([
            'domain' => 'WWW.Example.COM.',
            'type' => 'custom',
            'is_primary' => true,
        ]);

        $store->domains()->create([
            'domain' => 'shop.example.com',
            'type' => 'custom',
        ]);

        $this->assertCount(2, $store->domains()->get());
        $this->assertSame('www.example.com', $store->domains()->first()->domain);
        $this->assertTrue($store->primaryDomain()->exists());
    }
}
