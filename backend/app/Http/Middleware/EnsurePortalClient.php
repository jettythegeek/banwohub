<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $user->is_active, 401);

        abort_unless(
            $user->hasRole('Client') && $user->client_id,
            403,
            'Portal access requires an active client account linked to a client record.',
        );

        return $next($request);
    }
}
