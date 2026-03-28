<?php
declare(strict_types=1);

namespace app\exception;

final class ForbiddenException extends ApiException
{
    public function __construct(string $message = 'Forbidden', \Throwable $previous = null)
    {
        parent::__construct($message, 403, $previous);
    }
}
