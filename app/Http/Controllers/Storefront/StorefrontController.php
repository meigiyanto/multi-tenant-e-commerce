<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Stores\StoreContext;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function __invoke(StoreContext $context): Response
    {
        $store = $context->store();

        abort_if($store === null, 404);

        return Inertia::render('storefront/index', [
            'store' => [
                'name' => $store->name,
                'slug' => $store->slug,
                'currency' => $store->currency,
            ],
            'domain' => $context->domain()?->domain,
        ]);
    }
}
