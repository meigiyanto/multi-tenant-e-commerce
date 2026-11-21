<?php

namespace App\Providers;

use App\Contracts\Payments\PaymentGateway;
use App\Contracts\Shipping\ShippingProvider;
use App\Contracts\Stores\DomainOwnershipVerifier;
use App\Services\Payments\MidtransGateway;
use App\Services\Shipping\RajaOngkirShippingProvider;
use App\Services\Stores\DnsTxtRecordVerifier;
use App\Support\Stores\StoreContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(StoreContext::class);
        $this->app->bind(DomainOwnershipVerifier::class, DnsTxtRecordVerifier::class);
        $this->app->bind(PaymentGateway::class, MidtransGateway::class);
        $this->app->bind(ShippingProvider::class, function ($app): ShippingProvider {
            $class = config('shipping.providers.'.config('shipping.default').'.class', RajaOngkirShippingProvider::class);

            return $app->make($class);
        });
    }

    public function boot(): void
    {
        $this->configureDefaults();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);
        DB::prohibitDestructiveCommands(app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised()
            : null,
        );
    }
}
