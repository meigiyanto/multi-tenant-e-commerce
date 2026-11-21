<?php

namespace App\Services\Storefront;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class CartSessionService
{
    private const COOKIE_NAME = 'storefront_cart_session_id';

    public function id(Request $request): string
    {
        $id = $request->cookie(self::COOKIE_NAME);

        if (is_string($id) === false || $id === '') {
            $id = Str::random(48);
        }

        Cookie::queue(cookie(self::COOKIE_NAME, $id, 60 * 24 * 30, '/', null, $request->isSecure(), true, false, 'lax'));

        return $id;
    }
}
