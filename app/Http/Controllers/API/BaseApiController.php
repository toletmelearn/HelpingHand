<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BaseApiController extends Controller
{
    /**
     * Success response
     *
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    public function success($data = null, $message = 'Success', $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ];

        return response()->json($response, $code);
    }

    /**
     * Backward-compatible success response wrapper.
     */
    public function sendResponse($result, $message = 'Success', $code = 200): JsonResponse
    {
        return $this->success($result, $message, $code);
    }

    /**
     * Error response
     *
     * @param string $message
     * @param int $code
     * @param mixed $errors
     * @return JsonResponse
     */
    public function error($message = 'Error', $code = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => now()->toISOString()
        ];

        return response()->json($response, $code);
    }

    /**
     * Backward-compatible error response wrapper.
     */
    public function sendError($error, $errorMessages = [], $code = 404): JsonResponse
    {
        return $this->error($error, $code, $errorMessages);
    }

    /**
     * Validation error response
     *
     * @param array $errors
     * @return JsonResponse
     */
    public function validationError($errors): JsonResponse
    {
        return $this->error('Validation failed', 422, $errors);
    }
}
