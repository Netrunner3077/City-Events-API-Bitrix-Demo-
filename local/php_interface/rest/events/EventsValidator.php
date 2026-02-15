<?php

namespace Local\Rest\Events;

class EventsValidator
{
    public function validateCreate(array $data): void
    {
        $this->validateRequired($data);
        $this->validateDates($data);
        $this->validateTags($data);
        $this->validateCapacity($data);
        $this->validateStatusCreate($data);
    }

    public function validateUpdate(array $current, array $data): void
    {
        if (isset($data['start_at'])) {
            $start = new \DateTime($data['start_at']);
            $now = new \DateTime();
            if ($start < $now) {
                $this->throwValidation('start_at cannot be in the past');
            }
        }

        if ($current['status'] === 'cancelled' && isset($data['status']) && $data['status'] === 'published') {
            $this->throwValidation('Cannot change status from cancelled to published');
        }

        if (isset($data['end_at']) || isset($data['start_at'])) {
            $mergedStart = $data['start_at'] ?? $current['start_at'];
            $mergedEnd = $data['end_at'] ?? $current['end_at'];
            $this->validateDatePair($mergedStart, $mergedEnd);
        }

        if (isset($data['tags'])) {
            $this->validateTags($data);
        }

        if (isset($data['capacity'])) {
            $this->validateCapacity($data);
        }
    }

    private function validateRequired(array $data): void
    {
        $required = ['title', 'place', 'start_at', 'end_at', 'capacity', 'status'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->throwValidation("Field '$field' is required");
            }
        }
    }

    private function validateDates(array $data): void
    {
        $this->validateDatePair($data['start_at'], $data['end_at']);
    }

    private function validateDatePair(string $start, string $end): void
    {
        $startDt = \DateTime::createFromFormat('Y-m-d H:i', $start);
        $endDt = \DateTime::createFromFormat('Y-m-d H:i', $end);
        if (!$startDt || !$endDt) {
            $this->throwValidation('Invalid datetime format, use YYYY-MM-DD HH:MM');
        }
        if ($startDt >= $endDt) {
            $this->throwValidation('start_at must be earlier than end_at');
        }
    }

    private function validateTags(array $data): void
    {
        if (isset($data['tags'])) {
            if (!is_array($data['tags'])) {
                $this->throwValidation('tags must be an array');
            }
            if (count($data['tags']) > 5) {
                $this->throwValidation('tags count must not exceed 5');
            }
        }
    }

    private function validateCapacity(array $data): void
    {
        if (isset($data['capacity'])) {
            $cap = (int)$data['capacity'];
            if ($cap < 1 || $cap > 5000) {
                $this->throwValidation('capacity must be between 1 and 5000');
            }
        }
    }

    private function validateStatusCreate(array $data): void
    {
        if ($data['status'] === 'cancelled') {
            $this->throwValidation('Cannot create event with status cancelled');
        }
    }

    private function throwValidation(string $message): void
    {
        throw new \Exception($message, 422);
    }
}
