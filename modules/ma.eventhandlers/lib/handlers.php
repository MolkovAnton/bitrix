<?php

namespace MA\Eventhandlers;

use Bitrix\Main\EventManager;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\UserFieldTable;
use Bitrix\Main\Config\Option;

class Handlers
{
    const HLB_NAME = "Handlers";
    const TABLE_NAME = 'ma_handlers';

    public static function init($debug = false)
    {
        if (Loader::includeModule('highloadblock')) {
            $handlersHLBlock = HighloadBlockTable::getList(array(
                'filter' => array('NAME' => self::HLB_NAME),
                'cache' => array("ttl" => 3600 * 24 * 7)
            ))->fetch();
            if ($handlersHLBlock !== false) {
				self::setHandlers($handlersHLBlock, $debug);
            }
        }
    }
    
    private static function setHandlers($handlersHLBlock, $debug = false)
    {
        $handlersEntity = HighloadBlockTable::compileEntity($handlersHLBlock);
        if ($handlersEntity->hasField('UF_ACTIVE') &&
            $handlersEntity->hasField('UF_MODULE') &&
            $handlersEntity->hasField('UF_EVENT') &&
            $handlersEntity->hasField('UF_HANDLER') &&
            $handlersEntity->hasField('UF_SITE')) {
            
            $siteFieldId = UserFieldTable::GetList([
                'filter' => ['FIELD_NAME'=>'UF_SITE', 'ENTITY_ID' => "HLBLOCK_".$handlersHLBlock['ID']],
                'cache' => array("ttl" => 3600 * 24 * 7)
            ])->fetch()['ID'];
            
            $siteList = [];
            $siteListRes = \CUserFieldEnum::GetList([], ['USER_FIELD_ID'=>$siteFieldId, 'XML_ID'=>[SITE_ID, 'ALL']]);
            while ($site = $siteListRes->fetch()) {
                $siteList[] = $site['ID'];
            }
            
            $filter = ['UF_ACTIVE' => true];
            $rsHandlers = \HandlersTable::getList(array(
                'filter' => $filter,
                'select' => array('*'),
                'cache' => array("ttl" => 3600 * 24 * 7)
            ));
            
            global $USER_FIELD_MANAGER;
            $USER_FIELD_MANAGER->CleanCache();
            while ($obHandler = $rsHandlers->fetchObject()) {
                $loadHandler = true;
                $inSites = array_intersect($obHandler->getUfSite(), $siteList);
                if (ADMIN_SECTION !== true && empty($inSites)) {
                    $loadHandler = false;
                }
                $handlerFunction = $obHandler->getUfHandler();
                $bHandlerFounded = self::handlerFunctionExist($handlerFunction, $debug);

                if($bHandlerFounded && $loadHandler) {
                    EventManager::getInstance()->addEventHandler(
                        $obHandler->getUfModule(),
                        $obHandler->getUfEvent(),
                        $handlerFunction
                    );
                }
            }
        } else {
            if ($debug) {
                Debug::dumpToFile($handlersEntity, 'Unsupported highload block', 'handlers.log');
            }
        }
    }
    
    public static function testHeandlers($debug = false)
    {
        if (Loader::includeModule('highloadblock')) {
            $handlersHLBlock = HighloadBlockTable::getList(array(
                'filter' => array('NAME' => self::HLB_NAME),
                'cache' => array("ttl" => 3600 * 24 * 7)
            ))->fetch();
            if ($handlersHLBlock !== false) {
				$result = array();
				$activeCount = 0;
                $handlersEntity = HighloadBlockTable::compileEntity($handlersHLBlock);
                if ($handlersEntity->hasField('UF_ACTIVE') &&
                    $handlersEntity->hasField('UF_MODULE') &&
                    $handlersEntity->hasField('UF_EVENT') &&
                    $handlersEntity->hasField('UF_HANDLER') && 
                    $handlersEntity->hasField('UF_SITE')) {
                    $rsHandlers = \HandlersTable::getList(array(
                        'select' => array('*'),
                        'cache' => array("ttl" => 3600 * 24 * 7)
                    ));
					global $USER_FIELD_MANAGER;
					$USER_FIELD_MANAGER->CleanCache();
                    while ($obHandler = $rsHandlers->fetchObject()) {
						$active = $obHandler->getUfActive();
                        $handlerFunction = $obHandler->getUfHandler();
                        $functionInfo = self::handlerFunctionInfo($handlerFunction);
						
                        if ($active) {
                            $activeCount ++;
                        }
						$result["DATA"][] = array(
							"ID" => $obHandler->getId(),
							"ACTIVE" => $active,
							"MODULE" => $obHandler->getUfModule(),
							"EVENT" => $obHandler->getUfEvent(),
							"FUNCTION" => $obHandler->getUfHandler(),
							//"EVENT_KEY" => $eKey ? $eKey : "error",
							"CLASS_EXISTS" => $functionInfo['classEx'],
							"FUNCTION_EXISTS" => $functionInfo['classEx'] ? $functionInfo['methodEx'] : $functionInfo['functionEx'],
						);
                    }
                } else {
                    if ($debug) {
                        Debug::dumpToFile($handlersEntity, 'Unsupported highload block', 'handlers.log');
                    }
                }
				$result["TOTAL"] = count($result);
				$result["ACTIVE_TOTAL"] = $activeCount;
				return $result;
            }
        }
    }
    
    private static function handlerFunctionInfo($function, $debug = false)
    {
        $result = [];
        $classEx = $methodEx = $functionEx = '';
        $result['FUNCTION_EXIST'] = true;
        $arHandler = explode('::', $function);
        if(count($arHandler) == 2) {
            $handlerFunction = $arHandler;
            $classEx = class_exists($handlerFunction[0]);
            $methodEx = method_exists($handlerFunction[0], $handlerFunction[1]);

            if (!$classEx) {
                $result['classEx'] = false;
                $result['FUNCTION_EXIST'] = false;
                if ($debug)
                    Debug::dumpToFile($handlerFunction, 'Event handler class not found', 'handlers.log');
            } else if (!$methodEx) {
                $result['methodEx'] = false;
                $result['FUNCTION_EXIST'] = false;
                if ($debug)
                    Debug::dumpToFile($handlerFunction, 'Event handler method not found', 'handlers.log');
            }
        } else {
            $handlerFunction = $function;
            $functionEx = function_exists($handlerFunction);
            if (!$functionEx) {
                $result['FUNCTION_EXIST'] = false;
                $result['functionEx'] = false;
                if ($debug)
                    Debug::dumpToFile($handlerFunction, 'Event handler function not found', 'handlers.log');
            }
        }
        
        return $result;
    }
    
    private static function handlerFunctionExist($function)
    {
        return self::handlerFunctionInfo($function)['FUNCTION_EXIST'];
    }
    
    public static function setSiteListToUpdate()
    {
        Option::set('ma.eventhandlers', 'site_list_update', true);
    }
    
    public static function deleteSiteFromList($site_id)
    {
        $curValues = self::getSiteFieldList();
        $curList = $curValues['SITE_LIST'];
        $siteFieldId = $curValues['FIELD_ID'];
        
        $delVal[$curList[$site_id]['ID']] = $curList[$site_id];
        $delVal[$curList[$site_id]['ID']]['VALUE'] = "";
        $delVal[$curList[$site_id]['ID']]['DEL'] = "Y";
            
        $userFielEnum = new \CUserFieldEnum;
        $userFielEnum->SetEnumValues($siteFieldId, $delVal);
    }
    
    public static function updateSiteList($list)
    {
        if ($list->table_id === 'tbl_'.self::TABLE_NAME && Option::get('ma.eventhandlers', 'site_list_update') == true) {
            $curValues = self::getSiteFieldList();
            $curList = $curValues['SITE_LIST'];
            $siteFieldId = $curValues['FIELD_ID'];
            
            $sites = $newList = [];
            
            $siteRes = \Bitrix\Main\SiteTable::getList([]);
            while ($site = $siteRes->fetch()) {
                $sites[] = $site;
            }
            
            $i = 0;
            foreach ($sites as $site) {
                $arrValue = [
                    "VALUE" => $site['NAME']." [".$site['LID']."]",
                    "XML_ID" => $site['LID'],
                    "DEF" => "N"
                ];
                $newList[$curList['ALL']['ID']] = $curList['ALL'];
                unset($curList['ALL']);
                if (array_key_exists($site['LID'], $curList)) {
                    $newList[$curList[$site['LID']]['ID']] = $arrValue;
                    unset($curList[$site['LID']]);
                } else {
                    $newList["n$i"] = $arrValue;
                    $i++;
                }
            }
            foreach ($curList as $curSite) {
                $newList[$curSite['ID']] = [
                    "VALUE" => "",
                    "DEL" => "Y"
                ];
            }
            
            $userFielEnum = new \CUserFieldEnum;
            $userFielEnum->SetEnumValues($siteFieldId, $newList);
            
            Option::set('MA.eventhandlers', 'site_list_update', false);
        }
    }
    
    private static function getSiteFieldList()
    {
        $handlersHLBlock = HighloadBlockTable::getList(array(
            'filter' => array('NAME' => self::HLB_NAME),
            'cache' => array("ttl" => 3600 * 24 * 7)
        ))->fetch()['ID'];

        $siteFieldId = UserFieldTable::getList([
            'filter' => ['ENTITY_ID' => 'HLBLOCK_'.$handlersHLBlock, 'FIELD_NAME' => 'UF_SITE'],
            'select' => ['ID']
        ])->fetch()['ID'];

        $curList = [];
        $siteListRes = \CUserFieldEnum::getList([], ['USER_FIELD_ID' => $siteFieldId]);
        while ($siteListItem = $siteListRes->fetch()) {
            $curList[$siteListItem['XML_ID']] = $siteListItem;
        }
        
        return ['FIELD_ID' => $siteFieldId, 'SITE_LIST' => $curList];
    }

    public static function clearCache()
    {
        HighloadBlockTable::getEntity()->cleanCache();
        \HandlersTable::getEntity()->cleanCache();
    }
}