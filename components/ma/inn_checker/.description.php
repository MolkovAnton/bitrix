<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arComponentDescription = array(
	"NAME" => Loc::getMessage("INN_CHECKER.COMPONENT_NAME"),
	"DESCRIPTION" => Loc::getMessage("INN_CHECKER.COMPONENT_DESC"),
);
