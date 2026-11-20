<?php

namespace Tests\Feature\Stores;

use App\Enums\StoreDomainStatus;
use App\Models\Team;
use App\Services\Stores\StoreDomainLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StoreDomainLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_must_pass_verification_before_activation(): void
    {
        $domain = Team::factory()->create()->stores()->create([
            'name' => 'Domain Store',
            'slug' => 'domain-store',
        ])->domains()->create([
            'domain' => 'shop.example.test',
            'status' => StoreDomainStatus::Pending->value,
        ]);
        $service = app(StoreDomainLifecycleService::class);

        $service->startVerification($domain);

        try {
            $service->transition($domain->fresh(), StoreDomainStatus::Active);
            $this->fail('An unverified domain must not be activated.');
        } catch (ValidationException) {
            $this->assertSame(StoreDomainStatus::Verifying->value, $domain->fresh()->status);
        }

        $this->assertNotNull($domain->fresh()->verification_token);
        $service->markVerified($domain->fresh());
        $this->assertInstanceOf(CarbonImmutable::class, $domain->fresh()->verified_at);
        $this->assertSame(StoreDomainStatus::Verified->value, $domain->fresh()->status);

        $service->transition($domain->fresh(), StoreDomainStatus::Active);
        $this->assertSame(StoreDomainStatus::Active->value, $domain->fresh()->status);
    }

    public function test_disabling_domain_invalidates_its_verified_timestamp(): void
    {
        $domain = Team::factory()->create()->stores()->create([
            'name' => 'Domain Store',
            'slug' => 'domain-store',
        ])->domains()->create([
            'domain' => 'shop.example.test',
            'status' => StoreDomainStatus::Active->value,
            'verified_at' => now(),
        ]);

        app(StoreDomainLifecycleService::class)->transition($domain, StoreDomainStatus::Disabled);

        $this->assertNull($domain->fresh()->verified_at);
        $this->assertSame(StoreDomainStatus::Disabled->value, $domain->fresh()->status);
    }
}
