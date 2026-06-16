<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffAiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $user->is_active, 401);

        abort_if(
            $user->hasRole('Client'),
            403,
            'Internal AI tools are not available to client portal accounts.',
        );

        return $next($request);
    }
}
