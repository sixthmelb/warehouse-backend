<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk standardize API response format
 * 
 * File: app/Http/Middleware/ApiResponseFormat.php
 */
class ApiResponseFormat
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Hanya process JSON responses
        if ($response->headers->get('Content-Type') === 'application/json') {
            $data = json_decode($response->getContent(), true);
            
            // Jika response belum dalam format standard, wrap dengan format standard
            if (!isset($data['success'])) {
                $statusCode = $response->getStatusCode();
                
                $formattedData = [
                    'success' => $statusCode >= 200 && $statusCode < 300,
                    'message' => $this->getDefaultMessage($statusCode),
                    'data' => $data,
                    'timestamp' => now()->toISOString(),
                ];
                
                $response->setContent(json_encode($formattedData));
            }
        }

        return $response;
    }

    /**
     * Get default message berdasarkan status code
     */
    private function getDefaultMessage(int $statusCode): string
    {
        return match ($statusCode) {
            200 => 'Request successful',
            201 => 'Resource created successfully',
            204 => 'Request successful',
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Resource not found',
            422 => 'Validation error',
            500 => 'Internal server error',
            default => 'Request processed',
        };
    }
}