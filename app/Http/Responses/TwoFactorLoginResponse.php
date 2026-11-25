<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false], 200);
        }

        if ($request->session()->pull('platform_login', false)) {
            if (! $request->user()?->isPlatformAdmin()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('platform.login')
                    ->withErrors(['email' => 'This account does not have platform access.']);
            }

            return redirect()->route('platform.admin.teams.index');
        }

        if ($request->user()?->isPlatformAdmin()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('platform.login')
                ->withErrors(['email' => 'Use the platform administrator sign-in page.']);
        }

        $team = $request->user()?->currentTeam;
        $destination = $team
            ? route('dashboard', ['current_team' => $team->slug])
            : route('home');

        return redirect()->intended($destination);
    }
}
