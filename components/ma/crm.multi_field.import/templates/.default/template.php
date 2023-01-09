<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
\Bitrix\Main\Page\Asset::getInstance()->addJs($this->GetFolder().'/js/papaparse.min.js');
?>
<div class="main_container">
    <h2>Импорт мероприятий в карточку контакта</h2>
    <div class="parser_button">
        Файл
        <input accept=".csv" type="file" name="parser_input_file" id="input_button">
    </div>
    <div id="start_button" class="hidden ui-btn-primary ui-btn-main">Начать</div>
</div>

<div id="preload"></div>

<div id="import_result"></div>

<script>
    BX.ready(function(){
        BX.CrmMultiFieldImportEvents = BX.CrmMultiFieldImportEvents.create(
            {
                componentName: '<?=$component->GetName()?>',
                fileButton: 'input_button',
                preloadContainer: 'preload',
                resultContainer: 'import_result',
                startButton: 'start_button',
                fieldsNames: <?=CUtil::PhpToJSObject(array_keys($arResult['FIELD_NAMES']))?>,
            }
        );
    });
</script>