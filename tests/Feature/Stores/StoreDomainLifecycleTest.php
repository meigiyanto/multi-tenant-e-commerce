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
        $this->assertNotNull($domain->fresh()->verified_at);
        $this->assertNull($domain->fresh()->verification_token);
        $this->assertNull($domain->fresh()->verification_expires_at);
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

    public function test_verification_challenge_expires_after_configured_hours_and_can_be_renewed(): void
    {
        config(['storefront.domain_verification_hours' => 72]);
        $start = CarbonImmutable::parse('2026-11-20 10:00:00');
        $this->travelTo($start);

        $domain = Team::factory()->create()->stores()->create([
            'name' => 'Expiring Domain Store',
            'slug' => 'expiring-domain-store',
        ])->domains()->create([
            'domain' => 'shop.example.test',
            'status' => StoreDomainStatus::Pending->value,
        ]);
        $service = app(StoreDomainLifecycleService::class);

        $service->startVerification($domain);
        $domain->refresh();

        $this->assertSame($start->addHours(72)->toDateTimeString(), $domain->verification_expires_at->toDateTimeString());

        $this->travel(72)->hours();
        $this->assertTrue($service->expireIfOverdue($domain->fresh()));
        $this->assertSame(StoreDomainStatus::Expired->value, $domain->fresh()->status);
        $this->assertNull($domain->fresh()->verification_token);

        $service->startVerification($domain->fresh());
        $this->assertSame(StoreDomainStatus::Verifying->value, $domain->fresh()->status);
        $this->assertNotNull($domain->fresh()->verification_token);
        $this->assertTrue($domain->fresh()->verification_expires_at->isFuture());
    }

    public function test_expired_challenge_cannot_be_marked_verified_even_if_dns_token_matches(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00:00'));
        $domain = Team::factory()->create()->stores()->create([
            'name' => 'Expired Domain Store',
            'slug' => 'expired-domain-store',
        ])->domains()->create([
            'domain' => 'shop.example.test',
            'status' => StoreDomainStatus::Verifying->value,
            'verification_token' => 'valid-token',
            'verification_expires_at' => now()->subSecond(),
        ]);

        try {
            app(StoreDomainLifecycleService::class)->markVerified($domain);
            $this->fail('An expired challenge must not verify.');
        } catch (ValidationException) {
            $this->assertSame(StoreDomainStatus::Verifying->value, $domain->fresh()->status);
        }
    }
}
