<?php

namespace App\Services\Storefront;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CartSessionService
{
    private const COOKIE_NAME = 'storefront_cart_session_id';

    private const ATTRIBUTE_NAME = 'storefront_cart_session_id';

    public function id(Request $request): string
    {
        $id = $request->attributes->get(self::ATTRIBUTE_NAME) ?? $request->cookie(self::COOKIE_NAME);

        if (is_string($id) === false || $id === '') {
            $id = Str::random(48);
        }

        $request->attributes->set(self::ATTRIBUTE_NAME, $id);

        return $id;
    }

    public function attach(Response $response, Request $request): Response
    {
        $response->headers->setCookie(cookie(
            self::COOKIE_NAME,
            $this->id($request),
            60 * 24 * 30,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        ));

        return $response;
    }
}
