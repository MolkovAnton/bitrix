<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();

use \CustomComponent\Component,
    Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter\{HttpMethod};

class NewComponent extends Component implements Controllerable
{
    public function init() {
        $this->setModules(['sale', 'catalog']);
        $this->arResult['events'] = [
            'buttonEvents' => [
                [
                    'event' => 'click',
                    'params' => 'test',
                    'function' => 'buttonClick' 
                ],
                [
                    'event' => 'mouseover',
                    'params' => 'test2',
                    'function' => 'buttonHover' 
                ],
            ]
        ];
    }
    
    public function configureActions()
    {
        return [
            'getContent' => [
                'prefilters' => [
                    new HttpMethod([HttpMethod::METHOD_POST])
                ],
                'postfilters' => []
            ],
        ];
    }
    
    public function getContentAction($params, $template) {
        $html = $this->getView($template, 'start', ['test']);
        return ['html' => $html, 'values' => ['test' => 'test_val', $params]];
    }
}