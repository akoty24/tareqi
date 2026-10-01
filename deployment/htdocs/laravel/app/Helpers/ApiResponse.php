<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    /**
     * Success response (200)
     */
    public static function success(
        mixed $data = null,
        string $message = 'Operation successful',
        int $statusCode = Response::HTTP_OK,
        array $meta = []
    ): JsonResponse {
        if ($statusCode < 100 || $statusCode > 599) {
            $statusCode = Response::HTTP_OK;
        }

        $response = [
            'success'   => true,
            'message'   => $message,
            'data'      => $data,
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Created response (201)
     */
    public static function created(
        mixed $data = null,
        string $message = 'Item created successfully'
    ): JsonResponse {
        return self::success($data, $message, Response::HTTP_CREATED);
    }

    /**
     * Updated response (200)
     */
    public static function updated(
        mixed $data = null,
        string $message = 'Item updated successfully'
    ): JsonResponse {
        return self::success($data, $message, Response::HTTP_OK);
    }

    /**
     * Deleted response (200)
     */
    public static function deleted(string $message = 'Item deleted successfully'): JsonResponse
    {
        return self::success(null, $message, Response::HTTP_OK);
    }

    /**
     * Error response
     */
    public static function error(
        string $message = 'An error occurred',
        int $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR,
        mixed $errors = null,
        ?string $errorCode = null
    ): JsonResponse {
        if ($statusCode < 100 || $statusCode > 599) {
            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        $response = [
            'success'   => false,
            'message'   => $message,
        ];

        if (!is_null($errors)) {
            $response['errors'] = $errors;
        }

        if (!is_null($errorCode)) {
            $response['error_code'] = $errorCode;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Validation error response (422)
     */
    public static function validationError(
        mixed $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return self::error($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    /**
     * Not found response (404)
     */
    public static function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return self::error($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Unauthorized response (401)
     */
    public static function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return self::error($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Forbidden response (403)
     */
    public static function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return self::error($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Paginated response (200)
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        string $message = 'Data retrieved successfully',
        ?string $resourceClass = null,
        array $extraMeta = []
    ): JsonResponse {
        $items = $resourceClass
            ? $resourceClass::collection($paginator->getCollection())->resolve()
            : $paginator->items();

        return self::success(
            $items,
            $message,
            Response::HTTP_OK,
            array_merge([
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ], $extraMeta)
        );
    }
}