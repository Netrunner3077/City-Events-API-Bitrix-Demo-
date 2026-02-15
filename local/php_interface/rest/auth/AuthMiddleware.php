<?php

namespace Local\Rest\Auth;

use Bitrix\Main\Context;

class AuthMiddleware
{
    const API_KEY = 'secret-api-key-2026';

    public static function check(): bool
    {
        $headers = getallheaders();
        $apiKey = $headers['X-API-Key'] ?? '';
        return $apiKey === self::API_KEY;
    }
}
