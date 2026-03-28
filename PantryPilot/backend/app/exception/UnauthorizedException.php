<?php
declare(strict_types=1);

namespace app\exception;

final class UnauthorizedException extends ApiException
{
    public function __construct(string $message = 'Unauthorized', \Throwable $previous = null)
    {
        parent::__construct($message, 401, $previous);
    }
}
