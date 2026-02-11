<?php
use Bitrix\Main\Context;

AddEventHandler('main', 'OnPageStart', function() {
    $request = Context::getCurrent()->getRequest();
    $uri = $request->getRequestUri();
    
    if (strpos($uri, '/events/v1/') === 0) {
        define('EVENTS_API_MODE', true);
        require_once __DIR__ . '/rest/routing.php';
        exit;
    }
});