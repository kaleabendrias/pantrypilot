<?php
declare(strict_types=1);

namespace app\exception;

final class NotFoundException extends ApiException
{
    public function __construct(string $message = 'Not found', \Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
