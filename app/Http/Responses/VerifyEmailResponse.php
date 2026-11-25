<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsToCurrentTeam;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailResponse implements VerifyEmailResponseContract
{
    use RedirectsToCurrentTeam;

    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $user = $request->user();

        // Storefront customers are global accounts and do not need a Team.
        if (! $user?->currentTeam && ! $user?->personalTeam()) {
            return redirect()->intended('/');
        }

        return redirect()->intended(
            $this->redirectPathForCurrentTeam($request, Fortify::redirects('email-verification')).'?verified=1',
        );
    }
}
