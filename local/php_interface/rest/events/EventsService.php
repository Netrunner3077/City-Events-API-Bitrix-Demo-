<?php

namespace Local\Rest\Events;

use CIBlockElement;
use CModule;
use Bitrix\Main\Type\DateTime;

require_once __DIR__ . '/../pagination/CursorPagination.php';

class EventsService
{
    const IBLOCK_CODE = 'city_events';

    public function __construct()
    {
        CModule::IncludeModule('iblock');
    }

    private function getIblockId(): int
    {
        $iblock = \CIBlock::GetList([], ['CODE' => self::IBLOCK_CODE])->Fetch();
        return $iblock ? (int)$iblock['ID'] : 0;
    }

    /**
     * @throws \Exception
     */
    public function create(array $data): array
    {
        require_once __DIR__ . '/EventsValidator.php';

        $validator = new EventsValidator();
        $validator->validateCreate($data);

        $popularity = $this->calculatePopularity($data);
        if ($popularity === 1) {
            throw new \Exception('Low popularity Not interesting Event', 400);
        }
        $data['POPULARITY'] = $popularity;

        $iblockId = $this->getIblockId();
        $element = new CIBlockElement();

        $fields = [
            'IBLOCK_ID' => $iblockId,
            'NAME' => $data['title'],
            'ACTIVE' => $data['status'] === 'published' ? 'Y' : 'N',
            'PROPERTY_VALUES' => [
                'PLACE' => $data['place'],
                'START_AT' => new DateTime($data['start_at'], 'Y-m-d H:i:s'),
                'END_AT' => new DateTime($data['end_at'], 'Y-m-d H:i:s'),
                'TAGS' => $data['tags'] ?? [],
                'CAPACITY' => $data['capacity'],
                'STATUS' => $data['status'],
                'POPULARITY' => $popularity,
                'CHANGE_NUMBER' => 0,
            ]
        ];

        $elementId = $element->Add($fields);
        if (!$elementId) {
            throw new \Exception('Failed to create event: ' . $element->LAST_ERROR, 500);
        }

        return $this->getById($elementId);
    }

    public function getById(int $id): ?array
    {
        $iblockId = $this->getIblockId();
        $rs = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $iblockId, 'ID' => $id],
            false,
            false,
            ['ID', 'NAME', 'ACTIVE', 'PROPERTY_*']
        );

        if ($el = $rs->GetNextElement()) {
            return $this->formatElement($el);
        }

        return null;
    }

    public function update(int $id, array $data): array
    {
        $current = $this->getById($id);
        if (!$current) {
            throw new \Exception('Event not found', 404);
        }

        $validator = new EventsValidator();
        $validator->validateUpdate($current, $data);

        $merged = array_merge($current, $data);

        if (isset($data['start_at']) || isset($data['capacity']) || isset($data['tags'])) {
            $merged['popularity'] = $this->calculatePopularity($merged);
            if ($merged['popularity'] === 1) {
                throw new \Exception('Low popularity Not interesting Event', 400);
            }
        }

        $merged['change_number'] = ($current['change_number'] ?? 0) + 1;

        $iblockId = $this->getIblockId();
        $element = new CIBlockElement();

        $updateFields = [
            'NAME' => $merged['title'],
            'ACTIVE' => $merged['status'] === 'published' ? 'Y' : 'N',
        ];

        $propValues = [
            'PLACE' => $merged['place'],
            'START_AT' => new DateTime($merged['start_at'], 'Y-m-d H:i:s'),
            'END_AT' => new DateTime($merged['end_at'], 'Y-m-d H:i:s'),
            'TAGS' => $merged['tags'] ?? [],
            'CAPACITY' => $merged['capacity'],
            'STATUS' => $merged['status'],
            'POPULARITY' => $merged['popularity'],
            'CHANGE_NUMBER' => $merged['change_number'],
        ];

        $res = $element->Update($id, $updateFields);
        if (!$res) {
            throw new \Exception('Failed to update event: ' . $element->LAST_ERROR, 500);
        }

        CIBlockElement::SetPropertyValuesEx($id, $iblockId, $propValues);

        return $this->getById($id);
    }

    public function delete(int $id): void
    {
        $event = $this->getById($id);
        if (!$event) {
            throw new \Exception('Event not found', 404);
        }

        $now = new \DateTime();
        $start = new \DateTime($event['start_at']);
        if ($start <= $now) {
            throw new \Exception('Cannot delete event that has already started', 403);
        }
        if ($event['status'] === 'published') {
            throw new \Exception('Cannot delete published event', 403);
        }

        if (!CIBlockElement::Delete($id)) {
            throw new \Exception('Failed to delete event', 500);
        }
    }

    public function getList(array $params = []): array
    {
        $iblockId = $this->getIblockId();
        $filter = ['IBLOCK_ID' => $iblockId];

        // Фильтрация
        if (!empty($params['from'])) {
            $filter['>=PROPERTY_START_AT'] = new DateTime($params['from'], 'Y-m-d H:i:s');
        }
        if (!empty($params['to'])) {
            $filter['<=PROPERTY_START_AT'] = new DateTime($params['to'], 'Y-m-d H:i:s');
        }
        if (!empty($params['place'])) {
            $filter['=PROPERTY_PLACE'] = $params['place'];
        }
        if (!empty($params['status'])) {
            $filter['=PROPERTY_STATUS'] = $params['status'];
        }
        if (!empty($params['tag'])) {
            $filter['PROPERTY_TAGS'] = $params['tag'];
        }
        if (!empty($params['min_popularity'])) {
            $filter['>=PROPERTY_POPULARITY'] = (int)$params['min_popularity'];
        }
        if (!empty($params['max_popularity'])) {
            $filter['<=PROPERTY_POPULARITY'] = (int)$params['max_popularity'];
        }

        $sortRules = $this->buildSort($params['sort'] ?? 'soon_and_popular');

        $limit = min((int)($params['limit'] ?? 10), 50);
        $cursor = $params['cursor'] ?? null;

        $pagination = new \Local\Rest\Pagination\CursorPagination(); // создаём всегда

        if ($cursor) {
            $pagination->applyCursor($filter, $cursor, $sortRules);
        }

        $rs = CIBlockElement::GetList(
            $sortRules,
            $filter,
            false,
            ['nTopCount' => $limit + 1],
            ['ID', 'NAME', 'ACTIVE', 'PROPERTY_*']
        );

        $items = [];
        $lastItem = null;
        $count = 0;
        while ($el = $rs->GetNextElement()) {
            $count++;
            if ($count > $limit) break;
            $item = $this->formatElement($el);
            $items[] = $item;
            $lastItem = $item;
        }

        $hasNext = $count > $limit;

        $selfUrl = $this->buildUrl($params);
        $nextUrl = null;
        $prevUrl = null;

        if ($hasNext && $lastItem) {
            $nextCursor = $pagination->getNextCursor($lastItem, $sortRules);
            $nextParams = array_merge($params, ['cursor' => $nextCursor]);
            $nextUrl = $this->buildUrl($nextParams);
        }

        if ($cursor) {
        }

        $total = $this->countTotal($filter);

        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'per_page' => $limit,
            ],
            'links' => [
                'self' => $selfUrl,
                'next' => $nextUrl,
                'prev' => null,
            ]
        ];
    }

    private function formatElement($el): array
    {
        $fields = $el->GetFields();
        $props = $el->GetProperties();

        $tags = [];
        if ($props['TAGS']['VALUE']) {
            $tags = is_array($props['TAGS']['VALUE']) ? $props['TAGS']['VALUE'] : [$props['TAGS']['VALUE']];
        }

        $formatDate = function ($value) {
            if ($value instanceof \Bitrix\Main\Type\DateTime) {
                return $value->format('Y-m-d H:i:s');
            } elseif (is_string($value)) {
                $date = \DateTime::createFromFormat('d.m.Y H:i:s', $value);
                if ($date) {
                    return $date->format('Y-m-d H:i:s');
                }
                return $value;
            }
            return null;
        };

        return [
            'id' => (int)$fields['ID'],
            'title' => $fields['NAME'],
            'place' => $props['PLACE']['VALUE'],
            'start_at' => $formatDate($props['START_AT']['VALUE']),
            'end_at' => $formatDate($props['END_AT']['VALUE']),
            'tags' => $tags,
            'capacity' => (int)$props['CAPACITY']['VALUE'],
            'status' => $props['STATUS']['VALUE'],
            'popularity' => (int)$props['POPULARITY']['VALUE'],
            'change_number' => (int)$props['CHANGE_NUMBER']['VALUE'],
        ];
    }

    private function calculatePopularity(array $data): int
    {
        $today = new \DateTime('today');
        $start = new \DateTime($data['start_at']);
        $daysToStart = $today->diff($start)->days;
        if ($start < $today) {
            $daysToStart = -$daysToStart;
        }

        $capacity = (int)($data['capacity'] ?? 0);
        $tagCount = count($data['tags'] ?? []);

        $raw = 3 + ($capacity / 1000) - ($daysToStart / 10) + $tagCount;

        $rounded = round($raw);
        if ($rounded < 1) $rounded = 1;
        if ($rounded > 5) $rounded = 5;

        return (int)$rounded;
    }

    private function buildSort($sortType): array
    {
        switch ($sortType) {
            case 'popularity':
                return ['PROPERTY_POPULARITY' => 'DESC', 'ID' => 'ASC'];
            case 'soon_and_popular':
            default:
                return ['PROPERTY_START_AT' => 'ASC', 'PROPERTY_POPULARITY' => 'DESC', 'ID' => 'ASC'];
        }
    }

    private function countTotal(array $filter): int
    {
        return CIBlockElement::GetList([], $filter, [], false, ['ID']);
    }

    private function buildUrl(array $params): string
    {
        $query = http_build_query($params);
        return '/events/v1/' . ($query ? '?' . $query : '');
    }
}
