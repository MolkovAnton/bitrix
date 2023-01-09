<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Engine\Contract\Controllerable,
    Bitrix\Main\Loader,
    MA\Favorites\UniversalStorage;
use Bitrix\Main\Engine\ActionFilter\{HttpMethod};

/***
 * Параметры компонента
 * 'TYPE' - тип элемента
 * 'ID' - id элемента для добавления в избранное
 */

Loader::includeModule('MA.favorites');

class FavoritesItem extends \CBitrixComponent implements Controllerable {

    protected function init()
    {
        $type = $this->arParams['TYPE'];
        $id = $this->arParams['ID'];
        
        if (!$type || !$id || !is_int($id))
            throw new Exception('Не заполнены параметры компонента');
        if (!Loader::includeModule('MA.favorites'))
            throw new \Exception('Не установлен модуль Избранное');
        
        $storage = new UniversalStorage($type);
        $this->arResult = [
            'TYPE' => $type,
            'ID' => $id,
            'ACTIVE' => $storage->has($id)
        ];
    }

    public function executeComponent() 
    {
        try {
            $this->init();
            $this->includeComponentTemplate();
        } catch(\Exception $e) {
            ShowError($e->getMessage());
            return false;
        }
    }
    
    public function configureActions()
    {
        return [
            'handleFavorite' => [
                'prefilters' => [
                    new HttpMethod([HttpMethod::METHOD_POST])
                ],
                'postfilters' => []
            ]
        ];
    }
    
    public function handleFavoriteAction($data) {
        $storage = new UniversalStorage($data['type']);
        return $storage->handle($data['id']);
    }
}