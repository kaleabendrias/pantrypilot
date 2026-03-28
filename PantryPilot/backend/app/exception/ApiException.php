<?php
declare(strict_types=1);

namespace app\exception;

class ApiException extends \RuntimeException
{
    public function __construct(string $message, private readonly int $statusCode, \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
