<?php

use App\Exceptions\AuthenticationException;
use App\Exceptions\DuplicateException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\InsertException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UpdateException;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\JwtAuthMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt.auth'   => JwtAuthMiddleware::class,
            'role.admin' => AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'status'     => 401,
                'statusText' => 'UNAUTHORIZED',
                'message'    => $e->getMessage(),
            ], 401);
        });

        $exceptions->render(function (ForbiddenException $e, Request $request) {
            return response()->json([
                'status'     => 403,
                'statusText' => 'FORBIDDEN',
                'message'    => $e->getMessage(),
            ], 403);
        });

        $exceptions->render(function (NotFoundException $e, Request $request) {
            return response()->json([
                'status'     => 404,
                'statusText' => 'NOT_FOUND',
                'message'    => $e->getMessage(),
            ], 404);
        });

        $exceptions->render(function (DuplicateException|InsertException|UpdateException $e, Request $request) {
            return response()->json([
                'status'     => 400,
                'statusText' => 'BAD_REQUEST',
                'message'    => $e->getMessage(),
            ], 400);
        });
    })->create();
