<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
$component = new CBitrixComponent();
$component->initComponent('bitrix:form.result.new', 'new_vacancies_form');
$component->initComponentTemplate('');
\Bitrix\Main\Page\Asset::getInstance()->addCss($component->getTemplate()->GetFolder().'/style.css');