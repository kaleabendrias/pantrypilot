<?php
declare(strict_types=1);

namespace app\exception;

final class ConflictException extends ApiException
{
    public function __construct(string $message = 'Conflict', \Throwable $previous = null)
    {
        parent::__construct($message, 409, $previous);
    }
}
