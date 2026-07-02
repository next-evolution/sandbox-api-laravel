<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $authUser = $request->attributes->get('authUser');

        if ($authUser === null || !$authUser->isAdmin()) {
            return response()->json([
                'status'     => 403,
                'statusText' => 'FORBIDDEN',
                'message'    => 'Admin required',
            ], 403);
        }

        return $next($request);
    }
}
