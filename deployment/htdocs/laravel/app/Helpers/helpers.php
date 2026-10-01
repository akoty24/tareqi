<?php

if (!function_exists('created')) {
    function created($data, $message = 'created successfully', $statusCode = 201) {
        return response()->json([
            'data' => $data,
            'message' => $message,
            'status_code' => $statusCode,
        ], $statusCode);
    }
}
