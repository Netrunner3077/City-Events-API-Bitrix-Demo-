<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!CModule::IncludeModule('iblock')) {
    die('iblock module required');
}

$iblockTypeId = 'city_events';
$rsType = CIBlockType::GetByID($iblockTypeId);
if (!$rsType->Fetch()) {
    $arFields = [
        'ID' => $iblockTypeId,
        'SECTIONS' => 'N',
        'IN_RSS' => 'N',
        'SORT' => 500,
        'LANG' => [
            'ru' => [
                'NAME' => 'Городские события',
                'SECTION_NAME' => 'Разделы',
                'ELEMENT_NAME' => 'События'
            ],
            'en' => [
                'NAME' => 'City Events',
                'SECTION_NAME' => 'Sections',
                'ELEMENT_NAME' => 'Events'
            ]
        ]
    ];
    $obBlocktype = new CIBlockType;
    $obBlocktype->Add($arFields);
}

$iblockCode = 'city_events';
$rsIblock = CIBlock::GetList([], ['CODE' => $iblockCode]);
if (!$rsIblock->Fetch()) {
    $ib = new CIBlock;
    $arFields = [
        'ACTIVE' => 'Y',
        'NAME' => 'Городские события',
        'CODE' => $iblockCode,
        'IBLOCK_TYPE_ID' => $iblockTypeId,
        'SITE_ID' => ['s1'],
        'SORT' => 500,
        'GROUP_ID' => ['2' => 'R'],
        'VERSION' => 2,
        'FIELDS' => [
            'CODE' => [
                'DEFAULT_VALUE' => [
                    'TRANSLITERATION' => 'Y',
                    'UNIQUE' => 'Y'
                ]
            ]
        ]
    ];
    $iblockId = $ib->Add($arFields);
} else {
    $iblockData = $rsIblock->Fetch();
    $iblockId = $iblockData['ID'];
}

$propDefinitions = [
    [
        'NAME' => 'Место',
        'CODE' => 'PLACE',
        'PROPERTY_TYPE' => 'S',
        'IS_REQUIRED' => 'Y'
    ],
    [
        'NAME' => 'Дата начала',
        'CODE' => 'START_AT',
        'PROPERTY_TYPE' => 'S',
        'USER_TYPE' => 'DateTime',
        'IS_REQUIRED' => 'Y'
    ],
    [
        'NAME' => 'Дата окончания',
        'CODE' => 'END_AT',
        'PROPERTY_TYPE' => 'S',
        'USER_TYPE' => 'DateTime',
        'IS_REQUIRED' => 'Y'
    ],
    [
        'NAME' => 'Теги',
        'CODE' => 'TAGS',
        'PROPERTY_TYPE' => 'S',
        'MULTIPLE' => 'Y',
        'IS_REQUIRED' => 'N'
    ],
    [
        'NAME' => 'Вместимость',
        'CODE' => 'CAPACITY',
        'PROPERTY_TYPE' => 'N',
        'IS_REQUIRED' => 'Y'
    ],
    [
        'NAME' => 'Статус',
        'CODE' => 'STATUS',
        'PROPERTY_TYPE' => 'L',
        'IS_REQUIRED' => 'Y',
        'VALUES' => [
            ['VALUE' => 'draft', 'DEF' => 'Y', 'SORT' => 100],
            ['VALUE' => 'published', 'SORT' => 200],
            ['VALUE' => 'cancelled', 'SORT' => 300]
        ]
    ],
    [
        'NAME' => 'Популярность',
        'CODE' => 'POPULARITY',
        'PROPERTY_TYPE' => 'N',
        'IS_REQUIRED' => 'Y',
        'DEFAULT_VALUE' => 3
    ],
    [
        'NAME' => 'Номер изменения',
        'CODE' => 'CHANGE_NUMBER',
        'PROPERTY_TYPE' => 'N',
        'IS_REQUIRED' => 'Y',
        'DEFAULT_VALUE' => 0
    ]
];

foreach ($propDefinitions as $prop) {
    $prop['IBLOCK_ID'] = $iblockId;
    $prop['ACTIVE'] = 'Y';
    if ($prop['PROPERTY_TYPE'] == 'L') {
        $values = $prop['VALUES'];
        unset($prop['VALUES']);
        $propId = 0;
        $rsProp = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $prop['CODE']]);
        if (!$rsProp->Fetch()) {
            $propId = (new CIBlockProperty)->Add($prop);
        }
        if ($propId) {
            foreach ($values as $val) {
                $val['PROPERTY_ID'] = $propId;
                $val['DEF'] = $val['DEF'] ?? 'N';
                $dbList = CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $propId, 'VALUE' => $val['VALUE']]);
                if (!$dbList->Fetch()) {
                    (new CIBlockPropertyEnum)->Add($val);
                }
            }
        }
    } else {
        $rsProp = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $prop['CODE']]);
        if (!$rsProp->Fetch()) {
            (new CIBlockProperty)->Add($prop);
        }
    }
}

echo 'Infoblock created successfully. ID=' . $iblockId;