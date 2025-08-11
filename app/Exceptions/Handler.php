<?php

/**
 * Exception Handler untuk API responses
 * 
 * File: app/Exceptions/Handler.php - Method untuk API exception handling
 */
namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
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

    /**
     * Render an exception into an HTTP response untuk API
     */
    public function render($request, Throwable $e)
    {
        // Handle API requests dengan JSON response
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Handle API exceptions dengan format JSON yang konsisten
     */
    protected function handleApiException(Request $request, Throwable $e): JsonResponse
    {
        // Authentication Exception
        if ($e instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'error' => 'Authentication required',
                'timestamp' => now()->toISOString(),
            ], 401);
        }

        // Validation Exception
        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
                'timestamp' => now()->toISOString(),
            ], 422);
        }

        // Not Found Exception
        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
                'error' => 'The requested resource was not found',
                'timestamp' => now()->toISOString(),
            ], 404);
        }

        // Method Not Allowed Exception
        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Method not allowed',
                'error' => 'The requested HTTP method is not allowed for this endpoint',
                'timestamp' => now()->toISOString(),
            ], 405);
        }

        // Generic Exception
        $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        
        return response()->json([
            'success' => false,
            'message' => 'An error occurred',
            'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            'timestamp' => now()->toISOString(),
        ], $statusCode);
    }
}