<?php

namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    /**
     * The error code for the API response.
     */
    protected string $errorCode;

    /**
     * Additional data to include in the response.
     */
    protected array $extra = [];

    /**
     * Create a new API exception instance.
     */
    public function __construct(string $message, string $errorCode, int $status = 422, array $extra = [])
    {
        parent::__construct($message, $status);

        $this->errorCode = $errorCode;
        $this->extra = $extra;
    }

    /**
     * Get the error code.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get the extra data.
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render()
    {
        return response()->json([
            'code' => $this->errorCode,
            'message' => $this->message,
            ...$this->extra,
        ], $this->status);
    }
}
