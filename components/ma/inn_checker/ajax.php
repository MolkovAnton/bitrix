<?php
define('NO_KEEP_STATISTIC', 'Y');
define('NO_AGENT_STATISTIC','Y');
define('DisableEventsCheck', true);
define('DisableMessageServiceCheck', false);

$action = isset($_REQUEST['ACTION']) ? $_REQUEST['ACTION'] : '';

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if (!CModule::IncludeModule('crm'))
{
    return;
}

if ($action) {
    CBitrixComponent::includeComponentClass("itees:inn_checker");
    $comp = new InnCheckerComponent();
    $comp->setRequest(\Bitrix\Main\Application::getInstance()->getContext()->getRequest());
    print_r($comp->ajaxSearch());
}