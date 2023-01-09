<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
\Bitrix\Main\Page\Asset::getInstance()->addJs($this->GetFolder().'/js/papaparse.min.js');
?>
<div class="main_container">
    <h2>Импорт сущности (не поддерживает изменение контактной информации - телефонов, email и тд)</h2>
    <div class="parser_button">
        Файл
        <input accept=".csv" type="file" name="parser_input_file" id="input_button">
    </div>
    <div class="entity_selector">
        Сущность
        <select id="entity_selector">
            <option value="lead">Лид</option>
            <option value="deal">Сделка</option>
            <option value="contact">Контакт</option>
            <option value="company">Компания</option>
            <option value="user">Пользователь</option>
        </select>
    </div>
    <div id="start_button" class="hidden ui-btn-primary ui-btn-main">Начать</div>
</div>

<div id="preload"></div>

<div id="import_result"></div>

<script>
    BX.ready(function(){
        BX.CrmImportId = BX.CrmImportId.create(
            {
                componentName: '<?=$component->GetName()?>',
                fileButton: 'input_button',
                entitySelector: 'entity_selector',
                preloadContainer: 'preload',
                resultContainer: 'import_result',
                startButton: 'start_button',
                //someData: <?=CUtil::PhpToJSObject($arResult['SOME_DATA'])?>,
            }
        );
    });
</script>