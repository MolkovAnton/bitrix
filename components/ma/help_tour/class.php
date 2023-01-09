<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Loader,
	Bitrix\Main\Localization\Loc,
    Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter\{Authentication, HttpMethod, Csrf};

class HelpTour extends \CBitrixComponent implements Controllerable {

    protected function init()
    {
        $this->user = $GLOBALS['USER']->getId();
        $this->shownHelp = $this->filterShownHelp();
        $this->arResult = [
            'STEPS' => $this->getSteps(),
            'AUTO_START' => $this->shownHelp ? true : false
        ];
    }

    public function executeComponent() 
    {
        try {
            $this->init();
            if (empty($this->arResult['STEPS'])) return false;
            $this->includeComponentTemplate();
        } catch(\Exception $e) {
            ShowError($e->getMessage());
            return false;
        }
    }
    
    /* 
     * Выбирает подсказки из инфоблока, должны быть определены 
     * $this->arParams['IBLOCK_CODE'] - код инфоблока подсказок
     *  $this->targets - массив id элементов DOM структуры 
     */
    private function getSteps() {
        if (!Loader::includeModule('iblock'))
            throw new \Exception('Iblock module not instaled');
        
        $result = [];
        $arSelect = ["ID", "NAME", "DETAIL_TEXT", "PROPERTY_ANCHOR"];
        $arFilter = ["IBLOCK_CODE" => $this->arParams['IBLOCK_CODE'], "ACTIVE"=>"Y"];

        $res = CIBlockElement::GetList(['SORT' => 'ASC'], $arFilter, false, false, $arSelect);
        while($elem = $res->fetch())
        {
            $result[$elem['PROPERTY_ANCHOR_VALUE']] = [
                'target' => $elem['PROPERTY_ANCHOR_VALUE'],
                'text' => $elem['DETAIL_TEXT'],
                'title' => $elem['NAME'],
                'shown' => in_array($elem['PROPERTY_ANCHOR_VALUE'], $this->shownHelp) ? 'Y' : 'N'
            ];
        }
        
        return array_values($result);
    }
    
    private function getHLEntity() {
        $hlblock = HighloadBlockTable::getList(['filter' => ['NAME' => $this->arParams['HL_PARAMS']['HL_BLOCK_NAME']], 'cache' => array("ttl" => 3600 * 24 * 7)])->fetch();
        $entity = HighloadBlockTable::compileEntity($hlblock)->getDataClass();
        return $entity;
    }

    /*
     * Отбрасывает подсказки которые уже были показаны пользователю
     */
    private function filterShownHelp() {
        if(empty($this->user)) return false;

        $entity = $this->getHLEntity();
        $helpRes = $entity::getList([
            'filter' => [$this->arParams['HL_PARAMS']['USER_FIELD_NAME'] => $this->user],
            'cache' => array("ttl" => 3600 * 24 * 7)
        ])->fetch();
        
        return $helpRes[$this->arParams['HL_PARAMS']['SHOWN_HELP_FIELD_NAME']];
    }

    //AJAX ACTIONS
    //Необходимый для работы метод
    public function configureActions()
    {
        return [
            'test' => [
                'prefilters' => [
                    new Authentication(),
                    new HttpMethod([HttpMethod::METHOD_POST]),
                    new Csrf(),
                ],
                'postfilters' => []
            ]
        ];
    }
    
    private function clearUserSteps(array $steps) {
        $entity = $this->getHLEntity();
        $helpId = $entity::getList([
            'filter' => [$this->arParams['HL_PARAMS']['USER_FIELD_NAME'] => $this->user]
        ])->fetch();
        if ($helpId['ID'] > 0) {
            $newSteps = array_diff($helpId[$this->arParams['HL_PARAMS']['SHOWN_HELP_FIELD_NAME']], $steps);
            $fields = [
                $this->arParams['HL_PARAMS']['SHOWN_HELP_FIELD_NAME'] => $newSteps
            ];
            $entity::update($helpId['ID'], $fields);
        }
        
        return $newSteps;
    }

    /*
     * Аякс метод получения списка не показанных подсказок
     */
    public function getNewTourAction($post)
    {
        if(!empty($post['COMPONENT_PARAMS']))
        {
            $this->arParams = $post['COMPONENT_PARAMS'];
            $this->user = $GLOBALS['USER']->getId();
            $this->shownHelp = $this->clearUserSteps($post['STEPS']);
            $steps = $this->getSteps();
            return $steps;
        }
    }
    
    public function finishTourAction($post) {
        if(!empty($post['COMPONENT_PARAMS']) && !empty($post['STEPS']))
        {
            $this->arParams = $post['COMPONENT_PARAMS'];
            $this->user = $GLOBALS['USER']->getId();
            $entity = $this->getHLEntity();
            $helpId = $entity::getList([
                'filter' => [$this->arParams['HL_PARAMS']['USER_FIELD_NAME'] => $this->user]
            ])->fetch();
            if ($helpId['ID'] > 0) {
                $fields = [
                    $this->arParams['HL_PARAMS']['SHOWN_HELP_FIELD_NAME'] => array_unique(array_merge($helpId[$this->arParams['HL_PARAMS']['SHOWN_HELP_FIELD_NAME']], $post['STEPS']))
                ];
                $result = $entity::update($helpId['ID'], $fields);
            } else {
                $fields = [
                    $this->arParams['HL_PARAMS']['USER_FIELD_NAME'] => $this->user,
                    $this->arParams['HL_PARAMS']['SHOWN_HELP_FIELD_NAME'] => array_unique($post['STEPS'])
                ];
                $result = $entity::add($fields);
            }
            return $result;
        }
    }
}