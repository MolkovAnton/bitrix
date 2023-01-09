<?php
//Возвращает руководителя отдела в котором состоит пользователь
function getBitrixUserManager($user_id = false) {
   if(!$user_id) {
	   $user_id = $GLOBALS["USER"]->GetID();
   } 
   return array_keys(CIntranetUtils::GetDepartmentManager(CIntranetUtils::GetUserDepartments($user_id), $user_id, true));
}

function s($var, $userID = 0) {
    global $USER;
    
    if (($userID > 0 && $USER->GetID() == $userID) || $userID == 0) {
        if ($var === false) {
            $var = "false";
        }
        echo "<xmp>";
        print_r($var);
        echo "</xmp>";
    }
}

function p($var, $url = false, $delimiter = false) {
    if (empty($url)) {
        $url = $_SERVER["DOCUMENT_ROOT"] . "/logs/log.txt";
    }

    if (!empty($delimiter)) {
        $delimiter = "\n\n--- " . $delimiter . " ---\n\n";
    } else {
        $delimiter = "\n\n-------------------------------------------------\n\n";
    }
    file_put_contents($url, $delimiter . print_r($var, true), FILE_APPEND);
}

function ArrayToTimestamp($array,$url="")
{
    $sURL = $_SERVER["DOCUMENT_ROOT"]."/data/{$url}";
    CheckDirPath($sURL);
    ArrayToFile($array,"array",$sURL.time().".php");
}

function ArrayToFile($array,$name,$filename)
{
    file_put_contents($filename,"<?\${$name} = ".var_export($array,true)."?>");
}

function SetUserField ($entity_id, $value_id, $uf_id, $uf_value) //запись значени¤
{
    return $GLOBALS["USER_FIELD_MANAGER"]->Update ($entity_id, $value_id,
    Array ($uf_id => $uf_value));
}

function GetUserField ($entity_id, $value_id, $uf_id)
{
    $arUF = $GLOBALS["USER_FIELD_MANAGER"]->GetUserFields ($entity_id, $value_id);
    return $arUF[$uf_id]["VALUE"];
}

//функция проверяет принадлежность пользователя к группе по символьному коду группы
function CheckUserGroupByCode($groupCode)
{
	if(empty($groupCode))
		return false;
	
	$dbGroups = CGroup::GetListEx(Array(), Array('STRING_ID' => $groupCode), false, false, array('ID'));

	if($arGroup = $dbGroups->GetNext(false, false))
	{
		global $USER;
		$arUserGroups = $USER->GetUserGroupArray();
		if(in_array($arGroup['ID'], $arUserGroups))
			return true;
	}
	
	return false;
}
?>