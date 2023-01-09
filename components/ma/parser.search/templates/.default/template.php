<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
\Bitrix\Main\Page\Asset::getInstance()->addJs($this->GetFolder().'/js/papaparse.js');
?>

<?if (!empty($arResult['ERROR'])) {?>
<div class="error">
    <? foreach ($arResult['ERROR'] as $error) {?>
    <p><?=$error?></p>
    <?}?>
</div>
<?}?>

<div class="parser_body <?=!empty($arResult['ERROR']) ? 'hidden' : ''?>">
    <span class="desc">
        <?$APPLICATION->IncludeComponent(
            'itees:function_description',
            '',
            array(
                "IBLOCK_CODE" => "HELP_DESCRIPTION",
                "ELEMENT_CODE" => "parser_search",
                "WINDOW_POSITION" => "left",
                "CACHE_TIME" => 3600000,
                "CACHE_TYPE" => "A",
            ),
            $component,
            array('HIDE_ICONS' => 'Y')
        );?>
    </span>
    
    <div>
        <div class="parser_head_info">
            <h3>Загрузите файл в формате csv для проверки</h3>
            <div><a href="<?=$this->GetFolder()?>/Шаблон файла импорта.csv">Шаблон файла импорта</a></div>
            
            <?if ($arResult['EDITOR'] === "Y") {?>
                <div><a href="<?=$this->GetFolder()?>/log.txt" target="_blank" type="txt">История</a></div>
            <?}?>
                
            <div class="parser_buttons">
                <div id="parser_input_button" class="ui-btn-primary ui-btn-main">
                    Загрузить файл
                    <input accept=".csv" type="file" name="parser_input_file" id="parser_input_file" class="hidden">
                </div>
                <div class="file_name"></div>
            </div>   
        </div>
    </div>
    
    <div>
        <div id="field_chooser" class="hidden">
            <h3>Выберите соответствия полей</h3>
            <div class="field_select_cont">
                <select data-field="url"></select>
                <span>Сайт</span>
            </div>
            <div class="field_select_cont">
                <select data-field="inn"></select>
                <span>ИНН</span>
            </div>
            <div class="ui-btn-primary ui-btn-main">Применить</div>
            <div class="export"></div>
        </div>
    </div>
    
    <div class="parser_search_words">
        <div>
            <h3>Сайты будут проверены на содержание следующих слов:</h3>
            <div class="search_words">
                <?if ($arResult['EDITOR'] === "Y") {?>
                    <textarea name="search_words" id="search_words"><?=$arResult['WORDS']?></textarea>
                    <div id="set_search_words_button" class="ui-btn-primary ui-btn-main">Сохранить</div>
                <?} else {?>
                    <div class="info_small">Могут быть изменены редактором группы</div>
                    <textarea readonly name="search_words" id="search_words"><?=$arResult['WORDS']?></textarea>
                <?}?>
            </div>
        </div>
    </div>
    
    <div>
        <div id="parser_info" class="hidden">
            <div class="parser_info_body"></div>
            <div data-role='button' data-stage='1' class="ui-btn-primary ui-btn-main">Продолжить</div>
        </div>
    </div>
</div>

<?if ($arResult['EDITOR'] === "Y") {?>
<div class="user_selector_container">
    <div class="user_selector">
        <h3>Выбор ответственных</h3>
        <div>
            <div class="user_selector_title">При нахождении слов</div>
            <span data-user="found">
                <?$APPLICATION->IncludeComponent(
                    'bitrix:main.user.selector',
                    '',
                    [
                        'ID' => 'RESP_FOUND',
                        'INPUT_NAME' => 'RESP_FOUND',
                        'LIST' => [$arResult['RESPONSIBLE_FOR_FOUND_WORDS_LEAD']['ID']],
                    ],
                    null,
                    array('HIDE_ICONS' => 'Y')
                );?>
            </span>
        </div>
        <div class="user_selector_item">
            <div class="user_selector_title">Если слова не найдены</div>
            <span data-user="notFound">
                <?$APPLICATION->IncludeComponent(
                    'bitrix:main.user.selector',
                    '',
                    [
                        'ID' => 'RESP_NOT_FOUND',
                        'INPUT_NAME' => 'RESP_NOT_FOUND',
                        'LIST' => [$arResult['RESPONSIBLE_FOR_NOT_FOUND_WORDS_LEAD']['ID']],
                    ],
                    null,
                    array('HIDE_ICONS' => 'Y')
                );?>
            </span>
        </div>
        <div id="set_responsibles_button" class="ui-btn-primary ui-btn-main">Сохранить</div>
    </div>
</div>
<?}?>

<script>
    BX.ready(function(){
        BX.ParserSearch = BX.ParserSearch.create(
            {
                searchButton: 'parser_input_button',
                searchWordsArea: 'search_words',
                inputFileArea: 'parser_input_file',
                fieldChooser: 'field_chooser',
                parserUrl: '<?=$arResult['PARSER_URL']?>',
                saveWordsButton: 'set_search_words_button',
                parserInfo: 'parser_info',
                componentName: '<?=$component->GetName()?>',
                fieldsNamesInLead: <?=CUtil::PhpToJSObject($arResult['LEAD_FIELDS_NAMES'])?>,
                assignedName: 'ASSIGNED_BY_ID',
                foundAssignedId: <?=$arResult['RESPONSIBLE_FOR_FOUND_WORDS_LEAD'] ? CUtil::PhpToJSObject($arResult['RESPONSIBLE_FOR_FOUND_WORDS_LEAD']) : 0?>,
                notFoundAssignedId: <?=$arResult['RESPONSIBLE_FOR_NOT_FOUND_WORDS_LEAD'] ? CUtil::PhpToJSObject($arResult['RESPONSIBLE_FOR_NOT_FOUND_WORDS_LEAD']) : 0?>,
                setResponsibleButton: 'set_responsibles_button',
                responsibleFoundSelectorId: 'RESP_FOUND',
                responsibleNotFoundSelectorId: 'RESP_NOT_FOUND',
                curUser: '<?=$arResult['CUR_USER']?>',
                logPatch: '<?=$arResult['LOG_PATCH']?>',
                tagFieldName: '<?=$arResult['TAG_FIELD_NAME']?>',
                responsiblesFilled: '<?= ($arResult['RESPONSIBLE_FOR_FOUND_WORDS_LEAD'] && $arResult['RESPONSIBLE_FOR_NOT_FOUND_WORDS_LEAD']) ? true : false ?>',
                fmFieldsNames: <?=CUtil::PhpToJSObject($arResult['FM_FIELDS_NAMES'])?>,
            }
        );
    });
</script>