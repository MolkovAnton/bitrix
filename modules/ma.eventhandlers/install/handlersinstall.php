<?
use Bitrix\Highloadblock\HighloadBlockLangTable;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Loader;

class HandlersInstall
{
    const HLB_NAME = "Handlers";
    
    public static function createHandlersHighloadblock($debug = false)
    {
        if (!Loader::includeModule('highloadblock')) {
            return;
        }
        $arTable = array(
            'NAME' => self::HLB_NAME,
            'TABLE_NAME' => 'ma_handlers',
        );

        $addResult = HighloadBlockTable::add($arTable);

        if ($addResult->isSuccess()) {
            HighloadBlockLangTable::add(array(
                'ID' => $addResult->getId(),
                'LID' => 'ru',
                'NAME' => 'Обработчики событий'
            ));

            HighloadBlockLangTable::add(array(
                'ID' => $addResult->getId(),
                'LID' => 'en',
                'NAME' => 'Event Handlers'
            ));

            $obUserField = new \CUserTypeEntity;

            $arLangFields = array("EDIT_FORM_LABEL", "LIST_COLUMN_LABEL", "LIST_FILTER_LABEL");
            $arUserFields = array(
				array("NAME" => "UF_ACTIVE", "TYPE" => "boolean", "MULTIPLE" => "N", "LANG_RU" => "Активность", "LANG_EN" => "Active"),
				array("NAME" => "UF_MODULE", "TYPE" => "string", "MULTIPLE" => "N", "LANG_RU" => "Модуль", "LANG_EN" => "Module"),
				array("NAME" => "UF_EVENT", "TYPE" => "string", "MULTIPLE" => "N", "LANG_RU" => "Событие", "LANG_EN" => "Event"),
				array("NAME" => "UF_HANDLER", "TYPE" => "string", "MULTIPLE" => "N", "LANG_RU" => "Функция обработчик", "LANG_EN" => "Event handler function"),
				array("NAME" => "UF_COMMENT", "TYPE" => "string", "MULTIPLE" => "N", "LANG_RU" => "Комментарий", "LANG_EN" => "Comment"),
                array("NAME" => "UF_SITE", "TYPE" => "enumeration", "MULTIPLE" => "Y", "LANG_RU" => "Сайт", "LANG_EN" => "Site"),
            );

            foreach ($arUserFields as $arUserField) {
                $arFields = array(
                    'ENTITY_ID' => 'HLBLOCK_' . $addResult->getId(),
                    'FIELD_NAME' => $arUserField["NAME"],
                    'USER_TYPE_ID' => $arUserField["TYPE"],
                    'XML_ID' => $arUserField["NAME"],
                    'SORT' => '100',
                    'MULTIPLE' => $arUserField["MULTIPLE"],
                    'MANDATORY' => 'Y',
                    'SHOW_FILTER' => 'E',
                    'SHOW_IN_LIST' => 'Y',
                    'EDIT_IN_LIST' => 'Y',
                    'IS_SEARCHABLE' => 'Y',
                    'SETTINGS' => array(
                        'DEFAULT_VALUE' => '',
                        'SIZE' => '60',
                        'ROWS' => '1',
                        'MIN_LENGTH' => '0',
                        'MAX_LENGTH' => '0',
                        'REGEXP' => '',
                    ),
                );

                foreach ($arLangFields as $langField) {
                    $arFields[$langField]["ru"] = $arUserField["LANG_RU"];
                    $arFields[$langField]["en"] = $arUserField["LANG_EN"];
                }

                $id = $obUserField->Add($arFields);

                if (!$id) {
                    if ($debug) {
                        Debug::dumpToFile($arFields, 'Failed to add user field', 'handlers.log');
                    }
                }
                
                if ($arUserField["NAME"] === "UF_SITE") {
                    self::setSiteListValues($id);
                }
            }
        } else {
            if ($debug) {
                Debug::dumpToFile($arTable, 'Failed to add highloadblock', 'handlers.log');
            }
        }
    }
    
    private static function setSiteListValues($id)
    {
        $values = [
            "n0" => [
                "VALUE" => "Для всех",
                "XML_ID" => "ALL",
                "DEF" => "Y"
            ]
        ];
        $i = 1;
        $siteRes = \Bitrix\Main\SiteTable::getList([]);
        while ($site = $siteRes->fetch()) {
            $arrValue = [
                "VALUE" => $site['NAME']." [".$site['LID']."]",
                "XML_ID" => $site['LID'],
                "DEF" => "N"
            ];
            $values["n$i"] = $arrValue;
            $i++;
        }
        
        $userFielEnum = new \CUserFieldEnum;
        $userFielEnum->SetEnumValues($id, $values);
    }
    
    public static function deleteHandlersHighloadblock()
    {
        if (!Loader::includeModule('highloadblock')) {
            return;
        }
        $handlersHLBlock = HighloadBlockTable::getList(array(
            'filter' => array('NAME' => self::HLB_NAME),
            'cache' => array("ttl" => 3600 * 24 * 7)
        ))->fetch()['ID'];
        if ($handlersHLBlock > 0) {
            HighloadBlockTable::delete($handlersHLBlock);
        }
    }
}