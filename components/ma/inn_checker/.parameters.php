<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arParams = array(
	"PARAMETERS" => array(
		"PROP_NAME" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("INN_CHECKER.PROP_NAME"),
			"TYPE" => "STRING",
			"DEFAULT" => "20",
			"MULTIPLE" => "Y"
		),
		"PROP_NAME_IN_FILE" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("INN_CHECKER.PROP_NAME_IN_FILE"),
			"TYPE" => "STRING",
			"DEFAULT" => "20",
			"MULTIPLE" => "Y"
		),
	),
);
?> 