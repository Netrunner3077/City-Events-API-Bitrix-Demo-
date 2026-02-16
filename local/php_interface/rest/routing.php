<?php
require_once __DIR__ . '/auth/AuthMiddleware.php';
require_once __DIR__ . '/events/EventsController.php';

use Local\Rest\Auth\AuthMiddleware;
use Local\Rest\Events\EventsController;

if (!AuthMiddleware::check()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => ['message' => 'Unauthorized', 'code' => 'UNAUTHORIZED']]);
    exit;
}

$request = \Bitrix\Main\Context::getCurrent()->getRequest();
$method = $request->getRequestMethod();
$path = parse_url($request->getRequestUri(), PHP_URL_PATH);
$path = str_replace('/events/v1', '', $path);
$segments = explode('/', trim($path, '/'));

$controller = new EventsController();

try {
    if (count($segments) === 0 || $segments[0] === '') {
        if ($method === 'GET') {
            $controller->list();
        } elseif ($method === 'POST') {
            $controller->create();
        } else {
            http_response_code(405);
            echo json_encode(['error' => ['message' => 'Method not allowed', 'code' => 'METHOD_NOT_ALLOWED']]);
        }
    } elseif (count($segments) === 1 && is_numeric($segments[0])) {
        $id = (int)$segments[0];
        if ($method === 'GET') {
            $controller->get($id);
        } elseif ($method === 'PUT') {
            $controller->update($id);
        } elseif ($method === 'DELETE') {
            $controller->delete($id);
        } else {
            http_response_code(405);
            echo json_encode(['error' => ['message' => 'Method not allowed', 'code' => 'METHOD_NOT_ALLOWED']]);
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => ['message' => 'Not found', 'code' => 'NOT_FOUND']]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => ['message' => $e->getMessage(), 'code' => 'INTERNAL_ERROR']]);
}