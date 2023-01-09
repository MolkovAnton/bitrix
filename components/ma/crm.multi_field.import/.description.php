<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arComponentDescription = array(
	"NAME" => Loc::getMessage(""),
	"DESCRIPTION" => "Импорт данных о мероприятиях в контакт без потери предыдущих значений",
);
