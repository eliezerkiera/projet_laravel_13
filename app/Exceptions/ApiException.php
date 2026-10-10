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
     * The HTTP status code for the API response.
     */
    protected int $status;

    /**
     * Additional data to include in the response.
     */
    protected array $extra = [];

    /**
     * Field-level validation errors, included as `errors.<field>` when present.
     */
    protected ?array $errors = null;

    /**
     * Create a new API exception instance.
     */
    public function __construct(string $message, string $errorCode, int $status = 422, array $extra = [], ?array $errors = null)
    {
        parent::__construct($message, $status);

        $this->errorCode = $errorCode;
        $this->status = $status;
        $this->extra = $extra;
        $this->errors = $errors;
    }

    /**
     * Create an exception tied to a single form field.
     */
    public static function forField(string $errorCode, string $field, string $message, int $status = 422): self
    {
        return new self($message, $errorCode, $status, [], [$field => [$message]]);
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
     * Get the field-level validation errors.
     */
    public function getErrors(): ?array
    {
        return $this->errors;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render()
    {
        $payload = [
            'code' => $this->errorCode,
            'message' => $this->message,
        ];

        if ($this->errors !== null) {
            $payload['errors'] = $this->errors;
        }

        return response()->json([...$payload, ...$this->extra], $this->status);
    }
}
