<?php
namespace Local\Rest\Pagination;

class CursorPagination
{
    public function getNextCursor(array $lastItem, array $sortRules): string
    {
        $cursorData = [
            'last_id' => $lastItem['id'],
        ];
        
        foreach ($sortRules as $field => $direction) {
            $fieldMap = [
                'PROPERTY_START_AT' => 'start_at',
                'PROPERTY_POPULARITY' => 'popularity',
                'ID' => 'id'
            ];
            $cursorKey = strtolower($field);
            if (isset($fieldMap[$field])) {
                $cursorKey = $fieldMap[$field];
            }
            $cursorData[$cursorKey] = $lastItem[$cursorKey] ?? null;
            $cursorData[$cursorKey . '_dir'] = $direction;
        }
        
        return base64_encode(json_encode($cursorData));
    }
    
    public function applyCursor(array &$filter, string $cursor, array $sortRules): void
    {
        $cursorData = json_decode(base64_decode($cursor), true);
        if (!$cursorData) {
            return;
        }
        
        $conditions = [];
        $lastId = $cursorData['last_id'];
        
        if (isset($sortRules['PROPERTY_START_AT']) && $sortRules['PROPERTY_START_AT'] === 'ASC' &&
            isset($sortRules['PROPERTY_POPULARITY']) && $sortRules['PROPERTY_POPULARITY'] === 'DESC' &&
            isset($sortRules['ID']) && $sortRules['ID'] === 'ASC') {
            
            $lastStart = $cursorData['start_at'];
            $lastPopularity = $cursorData['popularity'];
            
            $filter[] = [
                'LOGIC' => 'OR',
                ['>PROPERTY_START_AT' => $lastStart],
                [
                    '=PROPERTY_START_AT' => $lastStart,
                    '<PROPERTY_POPULARITY' => $lastPopularity,
                ],
                [
                    '=PROPERTY_START_AT' => $lastStart,
                    '=PROPERTY_POPULARITY' => $lastPopularity,
                    '>ID' => $lastId,
                ]
            ];
        } elseif (isset($sortRules['PROPERTY_POPULARITY']) && $sortRules['PROPERTY_POPULARITY'] === 'DESC' && isset($sortRules['ID']) && $sortRules['ID'] === 'ASC' && count($sortRules) == 2) {
            $lastPopularity = $cursorData['popularity'];
            $lastId = $cursorData['last_id'];
            
            $filter[] = [
                'LOGIC' => 'OR',
                ['<PROPERTY_POPULARITY' => $lastPopularity],
                [
                    '=PROPERTY_POPULARITY' => $lastPopularity,
                    '>ID' => $lastId,
                ]
            ];
        } else {
            $filter['>ID'] = $lastId;
        }
    }
}