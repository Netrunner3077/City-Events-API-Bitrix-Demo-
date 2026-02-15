<?php

use Bitrix\Main\Context;

AddEventHandler('main', 'OnPageStart', function () {
    $request = Context::getCurrent()->getRequest();
    $uri = $request->getRequestUri();

    if (strpos($uri, '/events/v1/') === 0) {
        define('EVENTS_API_MODE', true);
        require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/rest/routing.php';
        exit;
    }
});

AddEventHandler('iblock', 'OnBeforeIBlockElementAdd', 'OnBeforeCityEventsSave');
AddEventHandler('iblock', 'OnBeforeIBlockElementUpdate', 'OnBeforeCityEventsSave');

function OnBeforeCityEventsSave(&$arFields)
{
    if ($arFields['IBLOCK_CODE'] !== 'city_events') {
        return;
    }

    CModule::IncludeModule('iblock');

    $elementId = (int)$arFields['ID'];
    $oldProps = [];
    if ($elementId) {
        $dbProps = CIBlockElement::GetProperty($arFields['IBLOCK_ID'], $elementId);
        while ($prop = $dbProps->Fetch()) {
            $oldProps[$prop['CODE']] = $prop;
        }
    }

    foreach (['START_AT', 'END_AT'] as $dateProp) {
        if (!empty($arFields['PROPERTY_VALUES'][$dateProp])) {
            $val = $arFields['PROPERTY_VALUES'][$dateProp];
            if (is_string($val)) {
                $dateTime = \DateTime::createFromFormat('d.m.Y H:i:s', $val);
                if ($dateTime) {
                    $arFields['PROPERTY_VALUES'][$dateProp] = \Bitrix\Main\Type\DateTime::createFromPhp($dateTime);
                } else {
                    $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $val);
                    if ($dateTime) {
                        $arFields['PROPERTY_VALUES'][$dateProp] = \Bitrix\Main\Type\DateTime::createFromPhp($dateTime);
                    }
                }
            }
        }
    }

    $data = [
        'start_at' => $arFields['PROPERTY_VALUES']['START_AT'] ?? $oldProps['START_AT']['VALUE'],
        'capacity' => $arFields['PROPERTY_VALUES']['CAPACITY'] ?? $oldProps['CAPACITY']['VALUE'],
        'tags' => $arFields['PROPERTY_VALUES']['TAGS'] ?? $oldProps['TAGS']['VALUE'],
    ];

    if (empty($data['start_at']) || empty($data['capacity'])) {
        return;
    }

    if ($data['start_at'] instanceof \Bitrix\Main\Type\DateTime) {
        $data['start_at'] = $data['start_at']->format('Y-m-d H:i:s');
    } elseif (is_string($data['start_at'])) {
        $date = \DateTime::createFromFormat('d.m.Y H:i:s', $data['start_at']);
        if ($date) {
            $data['start_at'] = $date->format('Y-m-d H:i:s');
        }
    }

    $data['capacity'] = (int)$data['capacity'];
    if (!is_array($data['tags'])) {
        $data['tags'] = $data['tags'] ? [$data['tags']] : [];
    }

    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/rest/events/EventsService.php';
    $service = new \Local\Rest\Events\EventsService();
    $popularity = $service->calculatePopularity($data);

    $arFields['PROPERTY_VALUES']['POPULARITY'] = $popularity;

    if ($elementId) {
        $currentChangeNumber = (int)($oldProps['CHANGE_NUMBER']['VALUE'] ?? 0);
        $arFields['PROPERTY_VALUES']['CHANGE_NUMBER'] = $currentChangeNumber + 1;
    } else {
        $arFields['PROPERTY_VALUES']['CHANGE_NUMBER'] = 0;
    }
}