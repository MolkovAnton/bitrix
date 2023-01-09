<?
//Подключение функций без класса
include @($_SERVER['DOCUMENT_ROOT']."/local/php_interface/include/functions.php");

//Автозагрузка классов
spl_autoload_register(function ($class_name) {
	$class = explode('\\', $class_name);
	$file = $_SERVER['DOCUMENT_ROOT']."/local/php_interface/include/autoload/". implode('/', $class) . ".php";
	$altFile = $_SERVER['DOCUMENT_ROOT']."/local/php_interface/include/autoload/". end($class) . ".php";
	
	if (file_exists($file)) {
		$filename = $file;
	} else if (file_exists($altFile)) {
		$filename = $altFile;
	}

	if ($filename) { 
		include_once $filename; 
		return true;
	}
	return false;
});

//Модуль индексации обработчиков
if (Bitrix\Main\Loader::includeModule('ma.eventhandlers')) {
	MA\Eventhandlers\Handlers::init();
}
?>