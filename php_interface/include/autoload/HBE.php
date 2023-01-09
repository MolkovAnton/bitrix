<?
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Entity;

// вспомогательные функции для работы с highloadblock'ами
class HBE
{
    function GetEntityDataClass($hbId)
    {
        if(empty($hbId)) return false;

        if(!CModule::IncludeModule('highloadblock')) return false;

        if(is_numeric($hbId))
        {
            $hlblock = HL\HighloadBlockTable::getById($hbId)->fetch();
        }
        else
        {
            $hlblock = HL\HighloadBlockTable::getList(['filter' => ['NAME' => $hbId]])->fetch();
        }

        $entity = HL\HighloadBlockTable::compileEntity($hlblock);

        return $entity->getDataClass();
    }

    //добавление элемента
    function Add($hbId, $data)
    {
        if(empty($hbId)) return false;

        $entity_data_class = self::GetEntityDataClass($hbId);

        $result = $entity_data_class::add($data);

        if($result->isSuccess())
        {
            return $result->getId();
        }
        else
        {
            global $APPLICATION;
            $APPLICATION->ThrowException(implode('', $result->getErrorMessages())); 
            return false;
        }
    }

    //добавление элемента
    function Update($hbId, $elemId, $data)
    {
        if(empty($hbId)) return false;

        $entity_data_class = self::GetEntityDataClass($hbId);

        $result = $entity_data_class::update($elemId, $data);

        if($result->isSuccess())
        {
            return $result->getId();
        }
        else
        {
            global $APPLICATION;
            $APPLICATION->ThrowException(implode('', $result->getErrorMessages())); 
            return false;
        }
    }

    //выборка
    function Get($hbId, $arSelect = array(), $arFilter = array(), $arOrder = array(), $ob = true)
    {
        if(empty($hbId)) return false;

        if(empty($arSelect)) $arSelect = array("*");

        $entity_data_class = self::GetEntityDataClass($hbId);

        $rsData = $entity_data_class::getList(array("select" => $arSelect, "filter" => $arFilter, "order" => $arOrder));

        $rsRes = new CDBResult($rsData);

        if($ob)
            return $rsRes;
        else
        {
            while($arRes = $rsRes->Fetch())
                $arResult[$arRes['ID']] = $arRes;

            return $arResult;
        }
    }

    //удаление
    function Del($hbId, $elemId)
    {
        if(empty($hbId)) return false;

        $entity_data_class = self::GetEntityDataClass($hbId);

        $result = $entity_data_class::delete($elemId);

        if($result->isSuccess())
        {
            return true;
        }
    }
}
?>