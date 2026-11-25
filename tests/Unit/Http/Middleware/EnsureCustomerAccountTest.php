<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\EnsureCustomerAccount;
use App\Models\Customer;
use App\Models\Store;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureCustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_store_customer_is_allowed(): void
    {
        $store = $this->makeStore();
        $customer = Customer::query()->create(['store_id' => $store->id, 'name' => 'Customer', 'email' => 'customer@example.com', 'password' => 'password', 'email_verified_at' => now()]);
        $request = Request::create('/account/profile');
        $request->setUserResolver(fn (?string $guard = null) => $guard === 'customer' ? $customer : null);

        $response = app(EnsureCustomerAccount::class)->handle($request, fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_platform_team_member_is_not_a_customer_identity(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => 'owner']);
        $request = Request::create('/account/profile');
        $request->setUserResolver(fn (?string $guard = null) => $guard === 'customer' ? null : $user);

        try {
            app(EnsureCustomerAccount::class)->handle($request, fn () => response('ok'));
            $this->fail('A team member must not pass customer account middleware.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    private function makeStore(): Store
    {
        $team = Team::factory()->create();

        return $team->stores()->create(['name' => 'Store', 'slug' => 'store-'.$team->id, 'status' => 'active']);
    }
}
