<?

use \Bitrix\Main\Loader,
    \Bitrix\Crm\EntityAddress,
    \Bitrix\Crm\EntityRequisite,
    \Bitrix\Crm\Timeline\CommentEntry,
    \Bitrix\Iblock\Model\Section;

class IteesFunctions
{
    //функция возвращает символьный код пользовательского свойства по xml_id
    //актуально для свойств созданных через публичную часть
    public function getUserTypeEntityCodeByXml($xmlId = false, $entityId = false)
    {
        if(!empty($xmlId))
        {
            $arFilter = ['XML_ID' => $xmlId];
            if(!empty($entityId))
            {
                $arFilter['ENTITY_ID'] = $entityId;
            }
            
            $result = [];
            $arUserTypeEntity = \Bitrix\Main\UserFieldTable::GetList([
                'filter' => $arFilter,
                'select' => ['XML_ID', 'FIELD_NAME']
            ]);
            while ($entity = $arUserTypeEntity->fetch()) {
                $result[$entity['XML_ID']] = $entity['FIELD_NAME'];
            }
            
            if(!empty($result) && is_array($xmlId)) {
                return $result;
            } else if (!empty($result)) {
                return $result[$xmlId];
            } else {
                return false;
            }
        }

        return false;
    }
	
    //функция загружает страницы сайтов из arUrls и проверяет наличие слов из arWords на странице
    function searchWordsOnSitePage($arWords = [], &$arUrls = [], $requiredWordsCount = 0) {

        if(!empty($arWords) && !empty($arUrls)) {

            $arResult = [];
            $strWordsUtf8 = implode('|', $arWords);
            $strWordsWin1251 = mb_convert_encoding($strWordsUtf8, "windows-1251", "utf-8");

            foreach($arUrls as $key => $url) {
                //получение страницы сайта
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                $page = curl_exec($ch);
                $info = curl_getinfo($ch);
                $error = curl_error($ch);
                curl_close($ch);

                //замена ссылки с редиректом от focus.kontur.ru на нормальную
                if(empty($error) && strpos($url, 'focus.kontur.ru') !== false && $info['url'] != $url)
                {
                    $arUrls[$key] = $info['url'];
                }

                if(empty($page)) {
                    $arResult['ERROR'][] = $url; //проблемы с получением страницы сайта
                    continue;
                }
                //поиск слов на странице
                if(preg_match_all('/' . $strWordsUtf8 . '|' . $strWordsWin1251 . '/', $page, $result) !== false) {
                    $arFoundWords =  array_unique($result[0]);
                }

                //распределение результата в зависимости от количества найденых слов
                if(!empty($requiredWordsCount) && (count($arFoundWords) > $requiredWordsCount)) {
                    $arResult['DATA'][] = $key; //слова найдены в нужном объеме
                }
            }

            return $arResult;
        }

        return false;
    }

    //проверка правв доступа
    function AccessRightCheck($functionalCode) {
        if(!empty($functionalCode) && Loader::includeModule('itees.car')) {
            $obCheckAccessRights = new Itees\CAR\CheckAccessRights();
            return $obCheckAccessRights->check($functionalCode);
        }
        return false;
    }
    
    //функция возвращает список отсутствующи по графику отсутствий сотрудников
    function getAbsenceUsers()
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $strDate = date('d.m.Y');
        $arUsers = [];

        $obElement = CIblockElement::GetList(
            [],
            [
                '<=DATE_ACTIVE_FROM' => $strDate,
                '>=DATE_ACTIVE_TO' => $strDate,
                'IBLOCK_CODE' => 'absence',
                'ACTIVE' => 'Y'
            ],
            false,
            false,
            ['PROPERTY_USER']
        );

        while($arElement = $obElement->fetch())
        {
            $arUsers[] = $arElement['PROPERTY_USER_VALUE'];
        }

        return $arUsers;
    }
    
    function getDepartmentByInn($inn)
    {
        $arResult = [];

        if(!empty($inn))
        {
            //найти регион инн
            if(strlen($inn) == 10 || strlen($inn) == 12) {
                $innRegion = substr($inn, 0, 2);
            //битиркс автоматом убирает первый 0 из ИНН
            //по этому регион может быть из одной цифры
            } elseif(strlen($inn) == 9 || strlen($inn) == 11) {
                $innRegion = substr($inn, 0, 1);
            }

            if(!empty($innRegion)) {

                //найти город и регион по инн
                $arRegionCity = \CIblockElement::GetList(
                    [],
                    [
                        "IBLOCK_CODE" => 'CRM_REGION',
                        "PROPERTY_NUMBER" => $innRegion,
                        "!PROPERTY_DEPARTMENT" => false
                    ],
                    false,
                    false,
                    ['ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'PROPERTY_DEPARTMENT']
                )->fetch();

                if(!empty($arRegionCity))
                {
                    //найти департамент по городу
                    if(!empty($arRegionCity['PROPERTY_DEPARTMENT_VALUE']))
                    {
                        $arResult['DEPARTMENT_ID'] = $arRegionCity['PROPERTY_DEPARTMENT_VALUE'];
                    }
                    //найти департамент по региону
//                    elseif(!empty($arRegionCity['IBLOCK_SECTION_ID']))
//                    {
//                        $arRegion = CIblockSection::GetList(
//                            [],
//                            ["IBLOCK_ID" => $arRegionCity['IBLOCK_ID'], "ID" => $arRegionCity['IBLOCK_SECTION_ID']],
//                            false,
//                            ['ID', 'UF_DEPARTMENT']
//                        )->fetch();
//                        
//                        if(!empty($arRegion['UF_DEPARTMENT']))
//                        {
//                            $this->departmentId = $arRegion['UF_DEPARTMENT'];
//                        }
//                    }

                    if(empty($arResult['DEPARTMENT_ID']))
                    {
                        $arResult['ERROR'] = 'Для города не указан департамент!';
                    }
                }
                else
                {
                    //нет города
                    $arResult['ERROR'] = 'Для данного ИНН не указан город!';
                }
            }
            else
            {
                //нет города
                $arResult['ERROR'] = 'Не корректный ИНН!';
            }
        }
        else
        {
            //нет инн
            $arResult['ERROR'] = 'Не заполнено поле ИНН!';
        }

        return $arResult;
    }
    
    //функция проверяет принадлежит ли пользователь департаменту
    //по id департамента пользователя и символьному коду департамента родителя
    function isUserFromChildDepartmentByDepartmentId($arUserDepartmentsId = [], $parentDepartmentCode = false)
    {
        if(!empty($arUserDepartmentsId) && !empty($parentDepartmentCode))
        {
            Loader::includeModule('iblock');

            $entitySection = Section::compileEntityByIblock(ADMINISTRATORS_GROUP_ID);

            $arParentDepartment = $entitySection::getList([
                'select' => ['ID', 'LEFT_MARGIN', 'RIGHT_MARGIN'],
                'filter' => [
                    'ACTIVE' => 'Y',
                    'CODE' => $parentDepartmentCode,
                    'IBLOCK.CODE' => 'departments'
                ]
            ])->fetch();
            
            if(!empty($arParentDepartment))
            {
                $arSubDepartments = $entitySection::getList([
                    'select' => ['ID'],
                    'filter' => [
                        'ACTIVE' => 'Y',
                        'ID' => $arUserDepartmentsId,
                        'IBLOCK.CODE' => 'departments',
                        '>LEFT_MARGIN' => $arParentDepartment['LEFT_MARGIN'],
                        '<RIGHT_MARGIN' => $arParentDepartment['RIGHT_MARGIN']
                    ],
                    'limit' => 1
                ])->fetch();

                if(!empty($arSubDepartments['ID']))
                {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    function isValidInn($inn)
    {
        if ( preg_match('/\D/', $inn) ) return false;

        $inn = (string) $inn;
        $len = strlen($inn);

        if ( $len === 10 )
        {
            return $inn[9] === (string) (((
                2*$inn[0] + 4*$inn[1] + 10*$inn[2] + 
                3*$inn[3] + 5*$inn[4] +  9*$inn[5] + 
                4*$inn[6] + 6*$inn[7] +  8*$inn[8]
            ) % 11) % 10);
        }
        elseif ( $len === 12 )
        {
            $num10 = (string) (((
                 7*$inn[0] + 2*$inn[1] + 4*$inn[2] +
                10*$inn[3] + 3*$inn[4] + 5*$inn[5] + 
                 9*$inn[6] + 4*$inn[7] + 6*$inn[8] +
                 8*$inn[9]
            ) % 11) % 10);

            $num11 = (string) (((
                3*$inn[0] +  7*$inn[1] + 2*$inn[2] +
                4*$inn[3] + 10*$inn[4] + 3*$inn[5] +
                5*$inn[6] +  9*$inn[7] + 4*$inn[8] +
                6*$inn[9] +  8*$inn[10]
            ) % 11) % 10);

            return $inn[11] === $num11 && $inn[10] === $num10;
        }

        return false;
    }

    //функция по url получает мета-теги description, keywords, og:title, og:description, title
    function getSiteMetaTags($url)
    {
        $html = self::getSitePage($url);

        $metaTagsStr = '';
        $metaParameters = [
            'description',
            'keywords',
            'og:title',
            'og:description'
        ];
        
        $pregTitle = self::getTitleFromHtml($html);
        
        if(!empty($pregTitle))
        {
            $metaTags[] = $pregTitle[0][0];
        }
        
        $metaTagsPreg = self::getMetaTagsFromHtml($html);
		
        $metaTagsPreg[1] = array_map('strtolower', $metaTagsPreg[1]);
        
        if(!empty($metaTagsPreg))
        {
            $allMetaTags = array_combine($metaTagsPreg[1], $metaTagsPreg[0]);
            
            foreach($metaParameters as $item)
            {
                $metaTags[] = $allMetaTags[$item];
            }
            
            $metaTagsStr = implode('', $metaTags);
        }

        if(in_array('content-type', $metaTagsPreg[1]))
        {
            $charsetKey = array_search('content-type', $metaTagsPreg[1]);

            if(strpos($metaTagsPreg[0][$charsetKey], '1251') !== false)
            {
                $metaTagsStr = mb_convert_encoding($metaTagsStr, 'utf-8', 'windows-1251');
            }
        }

        return $metaTagsStr;
    }

    //функция получает html по url
    function getSitePage($url = false)
    {
        $page = '';
        
        if(!empty($url))
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            $page = curl_exec($ch);
            curl_close($ch);
        }
        
        return $page;
    }
    
    //функция принимает на вход html и вбирает тег <title></title>
    function getTitleFromHtml($html)
    {
        preg_match_all( "|<title>(.*)</title>|sUSi", $html, $titles);
        
        return $titles;
    }
    
    //функция принимает на вход html и вбирает все мета-теги
    function getMetaTagsFromHtml($html)
    {
        $out = [];
        
        $pattern = '
            ~<\s*meta\s

            # using lookahead to capture type to $1
            (?=[^>]*?
            \b(?:name|property|http-equiv)\s*=\s*
            (?|"\s*([^"]*?)\s*"|\'\s*([^\']*?)\s*\'|
            ([^"\'>]*?)(?=\s*/?\s*>|\s\w+\s*=))
            )

            # capture content to $2
            [^>]*?\bcontent\s*=\s*
            (?|"\s*([^"]*?)\s*"|\'\s*([^\']*?)\s*\'|
            ([^"\'>]*?)(?=\s*/?\s*>|\s\w+\s*=))
            [^>]*>

            ~ix';

        preg_match_all($pattern, $html, $out);

        return $out;
    }
    
    public function sendMessageToChat(int $chatId, string $message)
    {
        Loader::includeModule('im');

        $ar = [
            'TO_CHAT_ID' => $chatId,
            'FROM_USER_ID' => 0, 
            'SYSTEM' => 'Y', 
            'MESSAGE' => $message
        ];

        CIMChat::AddMessage($ar);
    }
}