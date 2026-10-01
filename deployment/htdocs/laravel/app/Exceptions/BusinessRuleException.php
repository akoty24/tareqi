<?php

namespace App\Exceptions;

use App\Helpers\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * A request that is well-formed but violates a business rule
 * (e.g. booking a full trip). Rendered as a JSON error with a stable code
 * the frontend can translate.
 */
class BusinessRuleException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        ?string $message = null,
        public readonly int $status = 422,
    ) {
        parent::__construct($message ?? __("errors.{$errorCode}"));
    }

    public static function make(string $errorCode, int $status = 422, array $replace = []): self
    {
        return new self($errorCode, __("errors.{$errorCode}", $replace), $status);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->status, null, $this->errorCode);
    }
}
