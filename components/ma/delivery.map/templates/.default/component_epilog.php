<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
$this->getTemplate()->addExternalJs('https://api-maps.yandex.ru/2.1/?apikey='.$this->arParams['API_KEY'].'&lang=ru_RU&coordorder=longlat&alt;');