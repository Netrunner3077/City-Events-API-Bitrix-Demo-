<?php

namespace Local\Rest\Auth;

use Bitrix\Main\Context;

class AuthMiddleware
{
    const API_KEY = 'secret-api-key-2026';

    public static function check(): bool
    {
        $request = Context::getCurrent()->getRequest();
        $apiKey = $request->getHeader('X-API-Key');
        return $apiKey === self::API_KEY;
    }
}