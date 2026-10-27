<?php

use App\Exceptions\WorkflowException;
use App\Http\Middleware\ResolveOrganization;
use App\Http\Middleware\ResolveRegion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Единый формат ошибок клиентского API: {"error": {"code", "message", "fields"}}. */
$apiError = fn (string $code, string $message, int $status, array $fields = []) => response()->json(
    ['error' => ['code' => $code, 'message' => $message, 'fields' => (object) $fields]], $status,
);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'region' => ResolveRegion::class,
            'organization' => ResolveOrganization::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($apiError): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $isApi = fn (Request $request) => $request->is('api/*');

        $exceptions->render(fn (WorkflowException $e, Request $r) => $isApi($r)
            ? $apiError($e->errorCode, $e->getMessage(), match ($e->errorCode) { 'forbidden' => 403, 'not_found' => 404, default => 422 }) : null);
        $exceptions->render(fn (ValidationException $e, Request $r) => $isApi($r)
            ? $apiError('validation', 'Проверьте заполнение полей.', 422, $e->errors()) : null);
        $exceptions->render(fn (AuthenticationException $e, Request $r) => $isApi($r)
            ? $apiError('unauthenticated', 'Войдите в приложение.', 401) : null);
        $exceptions->render(fn (AuthorizationException|AccessDeniedHttpException $e, Request $r) => $isApi($r)
            ? $apiError('forbidden', 'Недостаточно прав.', 403) : null);
        $exceptions->render(fn (ModelNotFoundException|NotFoundHttpException $e, Request $r) => $isApi($r)
            ? $apiError('not_found', 'Не найдено.', 404) : null);
        $exceptions->render(fn (ThrottleRequestsException $e, Request $r) => $isApi($r)
            ? $apiError('too_many_requests', 'Слишком много попыток. Повторите позже.', 429) : null);
    })->create();
