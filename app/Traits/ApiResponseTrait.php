<?php

namespace App\Traits;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponseTrait
{
    /**
     * Success response
     */
    protected function success(
        mixed $data = null,
        string $message = 'Operation successful',
        int $statusCode = 200,
        array $meta = []
    ): JsonResponse {
        return ApiResponse::success($data, $message, $statusCode, $meta);
    }

    /**
     * Created response
     */
    protected function created(
        mixed $data = null,
        string $message = 'Item created successfully'
    ): JsonResponse {
        return ApiResponse::created($data, $message);
    }

    /**
     * Updated response
     */
    protected function updated(
        mixed $data = null,
        string $message = 'Item updated successfully'
    ): JsonResponse {
        return ApiResponse::updated($data, $message);
    }

    /**
     * Deleted response
     */
    protected function deleted(string $message = 'Item deleted successfully'): JsonResponse
    {
        return ApiResponse::deleted($message);
    }

    /**
     * Error response
     */
    protected function error(
        string $message = 'An error occurred',
        int $statusCode = 500,
        mixed $errors = null,
        ?string $errorCode = null
    ): JsonResponse {
        return ApiResponse::error($message, $statusCode, $errors, $errorCode);
    }

    /**
     * Validation error response
     */
    protected function validationError(
        mixed $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return ApiResponse::validationError($errors, $message);
    }

    /**
     * Not found response
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return ApiResponse::notFound($message);
    }

    /**
     * Unauthorized response
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return ApiResponse::unauthorized($message);
    }

    /**
     * Forbidden response
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return ApiResponse::forbidden($message);
    }

    /**
     * Paginated response
     */
    protected function paginated(
        LengthAwarePaginator $paginator,
        string $message = 'Data retrieved successfully',
        ?string $resourceClass = null,
        array $extraMeta = []
    ): JsonResponse {
        return ApiResponse::paginated($paginator, $message, $resourceClass, $extraMeta);
    }
}