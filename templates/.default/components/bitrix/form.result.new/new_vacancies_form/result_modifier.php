<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
$arResult["QUESTIONS"]['ADDITIONAL_INFO']['DEFAULT_VALUE'] = 'Отклик на вакансию '.$_REQUEST['VACANCY_NAME'].'['.$_REQUEST['VACANCY_ID'].'] в городе '.$_REQUEST['VACANCY_LOCATION'].'.';
$arResult["QUESTIONS"]['ADVISOR']['DEFAULT_VALUE'] = $USER->GetFullName();
$arResult["QUESTIONS"]['SELF_NAME']['DEFAULT_VALUE'] = $USER->GetFullName();
?>