<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true)die();

use \Investprojects\Connector as Investproject;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter\{Authentication, HttpMethod, Csrf};

class InvestprojectsImportComponent extends \CBitrixComponent implements Controllerable {
    public function executeComponent() 
    {
        try {	
            $this->includeComponentTemplate();
        } catch(\Exception $e) {
            ShowError($e->getMessage());
            return false;
        }
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
    
    //Пример ajax метода создания лидов
    public function addProjectsAction($post)
    {
        if(!empty($post['PROJECTS']))
        {
            try {
                $con = new Investproject();
                $result = $con->addProjects($post['PROJECTS'], $post['ADDITIONAL']);
                return $result;
            } catch (Error $e) {
                return ['ALL' => ['ERROR' => $e->getMessage()]];
            }
        }
    }
}