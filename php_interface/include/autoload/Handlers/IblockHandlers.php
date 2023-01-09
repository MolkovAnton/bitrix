<?
namespace Handlers;

use \Bitrix\Iblock\Model\Section,
    \Bitrix\Main\Type\DateTime,
    \Functions\IblockFunctions;

class IblockHandlers
{
    const DEPARTMENT_ADD_EVENT = 'DEPARTMENT_ADD';
    const DEPARTMENT_CHANGE_EVENT = 'DEPARTMENT_CHANGE';
    const DEPARTMENT_DELETE_EVENT = 'DEPARTMENT_DELETE';

    public function logDepartmentChange($arFields)
    {
        if (!IblockFunctions::isDepartmentsIblock((int)$arFields['IBLOCK_ID'])) return;
        unset($arFields['IPROPERTY_TEMPLATES']);
        $entity = Section::compileEntityByIblock((int)$arFields['IBLOCK_ID']);
        $section = $entity::getList([
            'select' => array_keys($arFields),
            'filter' => [
                'ID' => $arFields['ID'],
                'IBLOCK_ID' => $arFields['IBLOCK_ID']
            ],
            'limit' => 1
        ])->fetch();
        
        $diff = [];
        foreach ($arFields as $name => $val) {
            if ($section[$name] != $val) {
                $diff[$name] = [
                    'OLD' => $section[$name],
                    'NEW' => $val
                ];
            }
        }
        
        if (!empty($diff)) {
            $message = [
                $arFields['ID'] => $diff
            ];
            $data = [
                'UF_DATE' => new DateTime(),
                'UF_USER_ID' => $GLOBALS['USER']->GetID(),
                'UF_EVENT' => 'OnBeforeIBlockSectionUpdate',
                'UF_DESCRIPTION' => json_encode($message),
                'UF_FUNCTIONAL_CODE' => self::DEPARTMENT_CHANGE_EVENT
            ];

            \HBE::Add('Logs', $data);
        }
    }
    
    public function logDepartmentAdd($arFields)
    {
        if (!IblockFunctions::isDepartmentsIblock((int)$arFields['IBLOCK_ID'])) return;
        
        $data = [
            'UF_DATE' => new DateTime(),
            'UF_USER_ID' => $GLOBALS['USER']->GetID(),
            'UF_EVENT' => 'OnAfterIBlockSectionAdd',
            'UF_DESCRIPTION' => 'Создан отдел - '.$arFields['NAME'].' ['.$arFields['ID'].']',
            'UF_FUNCTIONAL_CODE' => self::DEPARTMENT_ADD_EVENT
        ];

        \HBE::Add('Logs', $data);
    }
    
    public function logDepartmentDelete($arFields)
    {
        if (!IblockFunctions::isDepartmentsIblock((int)$arFields['IBLOCK_ID'])) return;
        
        $data = [
            'UF_DATE' => new DateTime(),
            'UF_USER_ID' => $GLOBALS['USER']->GetID(),
            'UF_EVENT' => 'OnAfterIBlockSectionAdd',
            'UF_DESCRIPTION' => 'Удален отдел - '.$arFields['NAME'].' ['.$arFields['ID'].']',
            'UF_FUNCTIONAL_CODE' => self::DEPARTMENT_DELETE_EVENT
        ];

        \HBE::Add('Logs', $data);
    }
    
    //обработчик события перед изменением элемента в Списке "Реестр мероприятий"
    function OnBeforeEventUpdate(&$arFields)
    {
        if($arFields['IBLOCK_ID'] == SEMINARS_IB_ID && !empty($arFields['ID']))
        {
            \Bitrix\Main\Loader::includeModule('iblock');

            $propTaymingCode = 'TAYMING_IZOBRAZHENIYA_PREVYU_DLYA_VIDEO_V_SEKUNDAK';
            
            $element = \CIblockElement::GetList(
                [],
                ['ID' => $arFields['ID']],
                false,
                false,
                ['PROPERTY_' . $propTaymingCode]
            )->fetch();

            $property = \Bitrix\Iblock\PropertyTable::getList([
                'filter' => [
                    'IBLOCK_ID' => $arFields['IBLOCK_ID'],
                    'ACTIVE' => 'Y',
                    'CODE' => [$propTaymingCode]
                ],
                'select' => ['ID', 'CODE']
            ])->fetch();

            if(
                current($arFields['PROPERTY_VALUES'][$property['ID']]) != 
                $element['PROPERTY_' . $propTaymingCode . '_VALUE']
            ) {
                $arFields['TAYMING_UPDATED'] = true;
            }
        }
    }
    
    //обработчик события после изменения элемента в Списке "Реестр мероприятий"
    function OnAfterEventUpdate(&$arFields)
    {
        if($arFields['IBLOCK_ID'] == SEMINARS_IB_ID)
        {
            \Bitrix\Main\Loader::includeModule('iblock');

            //либо новый элемент либо изменился тайминг превью
            if((int)$arFields['ID'] == (int)$arFields['RESULT'] || $arFields['TAYMING_UPDATED'])
            {
                //получить id и code свойств
                $properties = \Bitrix\Iblock\PropertyTable::getList([
                    'filter' => [
                        'IBLOCK_ID' => $arFields['IBLOCK_ID'],
                        'ACTIVE' => 'Y',
                        'CODE' => [
                            'SSYLKA_NA_ZAPIS_STORAGE_MA_RU',
                            'TAYMING_IZOBRAZHENIYA_PREVYU_DLYA_VIDEO_V_SEKUNDAK'
                        ]
                    ],
                    'select' => ['ID', 'CODE']
                ]);

                while ($property = $properties->Fetch()) {
                    $arPropValues[$property['CODE']] = current($arFields['PROPERTY_VALUES'][$property['ID']]);
                }

                if(
                    !empty($arPropValues['SSYLKA_NA_ZAPIS_STORAGE_MA_RU']['VALUE']) &&
                    !empty($arPropValues['TAYMING_IZOBRAZHENIYA_PREVYU_DLYA_VIDEO_V_SEKUNDAK'])
                )
                {
                    $storageUrl = \COption::GetOptionString("askaron.settings", "UF_STORAGE_URL");
                    $storageBasicLogin = \COption::GetOptionString("askaron.settings", "UF_STORAGE_BASIC_LOGIN");
                    $storageBasicPass = \COption::GetOptionString("askaron.settings", "UF_STORAGE_BASIC_PASS");
                    
                    //сформировать новое превью на storage.MA.ru
                    $curl = curl_init();
                    curl_setopt_array($curl, 
                        array(
                            CURLOPT_URL => $storageUrl."/scripts/",
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_CUSTOMREQUEST => "POST",
                            CURLOPT_HTTPHEADER => ['authorization: Basic '.base64_encode($storageBasicLogin.":".$storageBasicPass)],
                            CURLOPT_POSTFIELDS => urldecode(http_build_query([
                                'action' => 'get_image',
                                'url' => $arPropValues['SSYLKA_NA_ZAPIS_STORAGE_MA_RU']['VALUE'],
                                'time' => $arPropValues['TAYMING_IZOBRAZHENIYA_PREVYU_DLYA_VIDEO_V_SEKUNDAK']
                            ]))
                        )
                    );

                    $json = curl_exec($curl);
                    $result = json_decode($json, true);
                    curl_close($curl);

                    if(!empty($result['result']))
                    {
                        //загрузить новое превью на cp
                        $arImgUrl = pathinfo($result['result']);

                        $fileId = \CFile::SaveFile([
                            "name" => $arImgUrl['basename'],
                            "type" => "image/png",
                            "MODULE_ID" => "iblock",
                            "content" => file_get_contents($result['result'])
                        ], 'iblock');

                        if(!empty($fileId))
                        {
                            //привязка нового превью и удаление старого
                            \CIBlockElement::SetPropertyValueCode($arFields['ID'], 'IZOBRAZHENIE_PREVYU_DLYA_VIDEO', $fileId);
                            
                            //удаление превью на storage
                            $curl = curl_init();
                            curl_setopt_array($curl, 
                                array(
                                    CURLOPT_URL => $storageUrl."/scripts/",
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_CUSTOMREQUEST => "POST",
                                    CURLOPT_HTTPHEADER => ['authorization: Basic '.base64_encode($storageBasicLogin.":".$storageBasicPass)],
                                    CURLOPT_POSTFIELDS => urldecode(http_build_query([
                                        'action' => 'delete_image',
                                        'url' => $result['result']
                                    ]))
                                )
                            );

                            $json = curl_exec($curl);
                            curl_close($curl);
                        }
                    }
                }
            }
        }
    }
}