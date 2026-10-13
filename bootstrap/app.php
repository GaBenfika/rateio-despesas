<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
    ->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*') || $request->expectsJson()
    );

    // 404 da API com mensagem clara, inclusive para ID que não existe (ou já foi excluído)
    $exceptions->render(function (NotFoundHttpException $e, Request $request) {
        if (! $request->is('api/*')) {
            return null; // nas telas web, mantém a página 404 padrão
        }

        $previous = $e->getPrevious();

        $message = $previous instanceof ModelNotFoundException
            ? 'Registro não encontrado (id: '.implode(', ', $previous->getIds()).').'
            : 'Rota não encontrada.';

        return response()->json(['message' => $message], 404);
    });
})->create();
