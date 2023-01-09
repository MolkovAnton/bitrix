<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

foreach ($arResult['ITEMS'] as $item) {
    if (!empty($item['PROPERTIES']['category']['VALUE_XML_ID'])) {
        $arResult['categoryes'][$item['PROPERTIES']['category']['VALUE_XML_ID']] = $item['PROPERTIES']['category']['VALUE'];
    }
}