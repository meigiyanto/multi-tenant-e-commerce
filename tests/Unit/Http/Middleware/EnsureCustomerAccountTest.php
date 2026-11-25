<?php

namespace Tests\\Unit\\Http\\Middleware;

use App\\Http\\Middleware\\EnsureCustomerAccount;
use App\\Models\\Team;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Http\\Request;
use Symfony\\Component\\HttpKernel\\Exception\\HttpException;
use Tests\\TestCase;

class EnsureCustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_independent_user_is_allowed_as_customer(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/account/profile');
        $request->setUserResolver(fn () => $user);

        $response = app(EnsureCustomerAccount::class)->handle($request, fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_team_member_is_not_allowed_to_use_customer_profile(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => 'owner']);
        $request = Request::create('/account/profile');
        $request->setUserResolver(fn () => $user);

        try {
            app(EnsureCustomerAccount::class)->handle($request, fn () => response('ok'));
            $this->fail('A team member must not pass customer account middleware.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
