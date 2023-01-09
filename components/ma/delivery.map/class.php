<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();

class DeliveryMap extends \CBitrixComponent {

    protected function init()
    {
        if (empty($this->arParams['API_KEY'])) {
            throw new Exception('Empty api key');
        }
        
        $this->arResult = [
            'IFRAME' => $this->arParams['IFRAME'],
            'START_POINT' => !empty($this->arParams['START_POINT']['LAT']) && !empty($this->arParams['START_POINT']['LON'])
                                ? ['LAT' => $this->arParams['START_POINT']['LON'], 'LON' => $this->arParams['START_POINT']['LAT']]
                                : ['LAT'=>37.663340,'LON'=>55.74785],
            'ZONES_PATH' => $this->arParams['ZONES_PATH'],
            'ZONE_PROP_ID' => $this->arParams['ZONE_PROP_ID'],
            'ADDRESS_PROP_ID' => $this->arParams['ADDRESS_PROP_ID'],
            'COORDS_PROP' => $this->arParams['COORDS_PROP'],
            'SELECTED_POINT' => $this->arParams['SELECTED_POINT'],
        ];
    }

    public function executeComponent() 
    {
        try {
            if ($this->arParams['IFRAME'] === 'Y') {
                global $APPLICATION;
                $APPLICATION->RestartBuffer();
                $APPLICATION->ShowHead();
            }
            $this->init();	
            $this->includeComponentTemplate();
        } catch(\Exception $e) {
            ShowError($e->getMessage());
            return false;
        }
    }
}