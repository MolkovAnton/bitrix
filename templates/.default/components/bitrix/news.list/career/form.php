<?define("NO_KEEP_STATISTIC", true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$APPLICATION->IncludeComponent(
	"bitrix:form.result.new",
	"new_vacancies_form",
	Array(
		"AJAX_MODE" => "Y",
		"AJAX_OPTION_HISTORY" => "N",
		"AJAX_OPTION_JUMP" => "N",
		"AJAX_OPTION_STYLE" => "Y",
		"WEB_FORM_ID" => $_GET['id'],	// ID веб-формы
		"IGNORE_CUSTOM_TEMPLATE" => "N",	// Игнорировать свой шаблон
		"USE_EXTENDED_ERRORS" => "N",	// Использовать расширенный вывод сообщений об ошибках
		"SEF_MODE" => "N",	// Включить поддержку ЧПУ
		"SEF_FOLDER" => "/about/",	// Каталог ЧПУ (относительно корня сайта)
		"CACHE_TYPE" => "N",	// Тип кеширования
		"CACHE_TIME" => "3600",	// Время кеширования (сек.)
		"LIST_URL" => "",	// Страница со списком результатов
		"EDIT_URL" => "",	// Страница редактирования результата
		"SUCCESS_URL" => "",	// Страница с сообщением об успешной отправке
		"CHAIN_ITEM_TEXT" => "",	// Название дополнительного пункта в навигационной цепочке
		"CHAIN_ITEM_LINK" => "",	// Ссылка на дополнительном пункте в навигационной цепочке
		"VARIABLE_ALIASES" => array(
			"WEB_FORM_ID" => "WEB_FORM_ID",
			"RESULT_ID" => "RESULT_ID",
		),
		"VACANCY_ID" => 1,
		"VACANCY_NAME" => 2,
		"VACANCY_LOCATION" => 3
	),
	false
);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");