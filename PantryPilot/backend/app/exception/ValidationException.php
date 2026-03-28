<?php
declare(strict_types=1);

namespace app\exception;

final class ValidationException extends ApiException
{
    public function __construct(string $message = 'Validation failed', \Throwable $previous = null)
    {
        parent::__construct($message, 422, $previous);
    }
}
