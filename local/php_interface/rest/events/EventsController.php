<?php

namespace Local\Rest\Events;

use Bitrix\Main\Context;

require_once __DIR__ . '/EventsService.php';
require_once __DIR__ . '/EventsValidator.php';

class EventsController
{
    public function list(): void
    {
        $request = Context::getCurrent()->getRequest();
        $params = $request->getQueryList()->toArray();

        $service = new EventsService();
        $result = $service->getList($params);

        $this->jsonResponse(200, $result);
    }

    public function create(): void
    {
        $data = $this->getJsonInput();

        $service = new EventsService();
        try {
            $event = $service->create($data);
            $this->jsonResponse(201, $event);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 400;
            $this->errorResponse($code, $e->getMessage());
        }
    }

    public function get(int $id): void
    {
        $service = new EventsService();
        $event = $service->getById($id);

        if (!$event) {
            $this->errorResponse(404, 'Event not found');
            return;
        }

        $dayOfWeek = (int)date('w');
        if ($dayOfWeek === 2 || $dayOfWeek === 3) {
            $event['recommendation'] = 'Рекомендуем по вторникам и средам';
        }

        $this->jsonResponse(200, $event);
    }

    public function update(int $id): void
    {
        $data = $this->getJsonInput();

        $service = new EventsService();
        try {
            $event = $service->update($id, $data);
            $this->jsonResponse(200, $event);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 400;
            $this->errorResponse($code, $e->getMessage());
        }
    }

    public function delete(int $id): void
    {
        $service = new EventsService();
        try {
            $service->delete($id);
            $this->jsonResponse(204, null);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 403;
            $this->errorResponse($code, $e->getMessage());
        }
    }

    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if (!is_array($data)) {
            $this->errorResponse(400, 'Invalid JSON');
        }
        return $data;
    }

    private function jsonResponse(int $status, $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    private function errorResponse(int $status, string $message, string $code = 'ERROR'): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => [
                'message' => $message,
                'code' => $code
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
