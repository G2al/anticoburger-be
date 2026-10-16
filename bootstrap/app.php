<?php

use App\Exceptions\BookingException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$apiError = fn (string $code, string $message, int $status, array $extra = [], array $headers = []): JsonResponse => response()->json([
    'error' => ['code' => $code, 'message' => $message, ...$extra],
], $status, $headers);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) use ($apiError): void {
        $exceptions->dontReport(BookingException::class);

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (ValidationException $exception, Request $request) use ($apiError) {
            if ($request->is('api/*')) {
                return $apiError('VALIDATION_FAILED', 'I dati inviati non sono validi.', 422, ['fields' => $exception->errors()]);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) use ($apiError) {
            if ($request->is('api/*')) {
                return $apiError('TOO_MANY_REQUESTS', 'Troppe richieste. Riprova tra poco.', 429, [], $exception->getHeaders());
            }
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) use ($apiError) {
            if ($request->is('api/*')) {
                return $apiError('NOT_FOUND', 'Risorsa non trovata.', 404);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request) use ($apiError) {
            if ($request->is('api/*')) {
                return $apiError('METHOD_NOT_ALLOWED', 'Metodo HTTP non consentito.', 405, [], $exception->getHeaders());
            }
        });

        $exceptions->render(function (Throwable $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof HttpExceptionInterface) {
                return $apiError('HTTP_ERROR', 'Richiesta non valida.', $exception->getStatusCode(), [], $exception->getHeaders());
            }

            if (! config('app.debug')) {
                return $apiError('SERVER_ERROR', 'Si è verificato un errore imprevisto. Riprova più tardi.', 500);
            }

            return null;
        });
    })->create();
