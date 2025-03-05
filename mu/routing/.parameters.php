<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
/** @var array $arCurrentValues */
if (!\Bitrix\Main\Loader::includeModule("iblock"))
    return;
$iblockList = [];
$res = \Bitrix\Iblock\IblockTable::getList([
    "select" => ["ID", "NAME"],
]);
$iblockList[] = "";
while ($iblock = $res->Fetch()) {
    $iblockList[$iblock["ID"]] = "[" . $iblock["ID"] . "] " . $iblock["NAME"];
}
$iblockIDs = [];
if ($arCurrentValues["DEPARTMENTS_IBLOCK_ID"]) $iblockIDs[] = $arCurrentValues["DEPARTMENTS_IBLOCK_ID"];
if ($arCurrentValues["NEWS_IBLOCK_ID"]) $iblockIDs[] = $arCurrentValues["NEWS_IBLOCK_ID"];
$res = \Bitrix\Iblock\PropertyTable::getList([
    "filter" => ["IBLOCK_ID" => $iblockIDs],
]);
$propertyList = [];
while ($property = $res->Fetch()) {
    $propertyList[$property["IBLOCK_ID"]][$property["CODE"]] = "[" . $property["ID"] . "] " . $property["NAME"];
}
$arComponentParameters = [
    "GROUPS" => [
        "FACULTIES_SETTINGS" => [
            "NAME" => "Настройки факультетов",
        ],
        "DEPARTMENTS_SETTINGS" => [
            "NAME" => "Настройки кафедр",
        ],
        "NEWS_SETTINGS" => [
            "NAME" => "Настройки новостей",
        ],
    ],
    "PARAMETERS" => [
        "SEF_MODE" => [
            0 => [
                "NAME" => "Страница факультета",
                "DEFAULT" => "#ELEMENT_CODE#/"
            ],
            1 => [
                "NAME" => "Страница списка кафедр",
                "DEFAULT" => "departments/",
            ],
            2 => [
                "NAME" => "Страница кафедры",
                "DEFAULT" => "#ELEMENT_CODE#/"
            ],
            3 => [
                "NAME" => "Страница списка новостей",
                "DEFAULT" => "news/",
            ],
            4 => [
                "NAME" => "Страница новости",
                "DEFAULT" => "#ELEMENT_CODE#/"
            ],
        ],
        "FACULTIES_IBLOCK_ID" => [
            "NAME" => "Инфоблок факультетов",
            "TYPE" => "LIST",
            "DEFAULT" => "",
            "PARENT" => "FACULTIES_SETTINGS",
            "VALUES" => $iblockList,
        ],
        "DEPARTMENTS_IBLOCK_ID" => [
            "NAME" => "Инфоблок кафедр",
            "TYPE" => "LIST",
            "DEFAULT" => "",
            "PARENT" => "DEPARTMENTS_SETTINGS",
            "VALUES" => $iblockList,
            "REFRESH" => "Y",
        ],
        "DEPARTMENTS_LINK_PROPERTY_ID" => [
            "NAME" => "Свойство привязки кафедр",
            "TYPE" => "LIST",
            "DEFAULT" => "",
            "PARENT" => "DEPARTMENTS_SETTINGS",
            "VALUES" => $propertyList[$arCurrentValues["DEPARTMENTS_IBLOCK_ID"]],
        ],
        "NEWS_IBLOCK_ID" => [
            "NAME" => "Инфоблок новостей",
            "TYPE" => "LIST",
            "DEFAULT" => "",
            "PARENT" => "NEWS_SETTINGS",
            "VALUES" => $iblockList,
            "REFRESH" => "Y",
        ],
        "NEWS_LINK_PROPERTY_ID" => [
            "NAME" => "Свойство привязки новостей",
            "TYPE" => "LIST",
            "DEFAULT" => "",
            "PARENT" => "NEWS_SETTINGS",
            "VALUES" => $propertyList[$arCurrentValues["NEWS_IBLOCK_ID"]],
        ],
        "CACHE_TIME" => [
            "DEFAULT" => 3600000
        ],
    ],
];