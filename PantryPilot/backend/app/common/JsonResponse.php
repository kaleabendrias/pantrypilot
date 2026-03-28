<?php
declare(strict_types=1);

namespace app\common;

use think\response\Json;

final class JsonResponse
{
    public static function success(array $data = [], string $message = 'ok', int $code = 200): Json
    {
        return json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c'),
        ], $code);
    }

    public static function error(string $message, int $code = 422, array $errors = []): Json
    {
        return json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('c'),
        ], $code);
    }
}
