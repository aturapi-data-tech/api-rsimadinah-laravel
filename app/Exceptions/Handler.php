<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontReport = [
        //
    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        // Jika request mengharapkan JSON (misalnya untuk API), kembalikan response JSON
        if ($request->expectsJson() || $request->wantsJson()) {
            $status = 500;
            if ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
            }

            return response()->json([
                'error'   => 'Error',
                'message' => $exception->getMessage(),
            ], $status);
        }

        return parent::render($request, $exception);
    }
}
