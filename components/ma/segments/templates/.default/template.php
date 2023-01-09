<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
use Bitrix\Main\Localization\Loc;
?>
<div class="side-tabs__wrapper" id="segmentsContainer">
    <div class="side-tabs__name">
        <h1>
            <?=$arResult['SEGMENT'] 
                ? Loc::getMessage('MA_SEGMENTS.SEGMENT_NAME').": ".$arResult['SEGMENT']['NAME']." ".Loc::getMessage('MA_SEGMENTS.SEGMENT_FROM')." ".$arResult['SEGMENT']['DATE']
                : Loc::getMessage('MA_SEGMENTS.NAME')
            ?>
        </h1>
    </div>
    <div class="segment">
        <div class="segment__buttons">
            <?if ($arResult['SEGMENT']) {?>
            <a href="<?=$arResult['CUR_URL']?>" class="ui-btn ui-btn-lg ui-btn-link"><?=Loc::getMessage('MA_SEGMENTS.LIST')?></a>
            <?}?>
            <a href="javascript:void(0)" class="ui-btn ui-btn-lg ui-btn-primary" data-events='click|getTemplate|"addSegment"'><?=Loc::getMessage('MA_SEGMENTS.CREATE')?></a>
        </div>

        <?$APPLICATION->IncludeComponent(
            "bitrix:main.ui.grid",
            "",
            array(
                'GRID_ID' => $arResult['GRID_ID'],
                'COLUMNS' => $arResult['COLUMNS'],
                'ROWS' => $arResult['ROWS']['DATA'],
                "AJAX_MODE" => "N",
                "AJAX_ID" => CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
                "PAGE_SIZES" => array(
                    array("NAME" => "5", "VALUE" => "5"),
                    array("NAME" => "10", "VALUE" => "10"),
                    array("NAME" => "20", "VALUE" => "20"),
                    array("NAME" => "50", "VALUE" => "50"),
                    array("NAME" => "100", "VALUE" => "100")
                ),
                "AJAX_OPTION_JUMP" => "N",
                "SHOW_CHECK_ALL_CHECKBOXES" => $arResult['SEGMENT'] ? true : false,
                "SHOW_ROW_CHECKBOXES" => $arResult['SEGMENT'] ? true : false,
                "SHOW_ROW_ACTIONS_MENU" => true,
                "SHOW_GRID_SETTINGS_MENU" => true,
                "SHOW_NAVIGATION_PANEL" => true,
                "SHOW_PAGINATION" => true,
                "SHOW_SELECTED_COUNTER" => true,
                "SHOW_TOTAL_COUNTER" => true,
                "SHOW_PAGESIZE" => true,
                "SHOW_ACTION_PANEL" => $arResult['PANEL'] ? true : false,
                "ACTION_PANEL" => $arResult['PANEL'],
                "ALLOW_COLUMNS_SORT" => true,
                "ALLOW_COLUMNS_RESIZE" => true,
                "ALLOW_SORT" => true,
                "ALLOW_PIN_HEADER" => false,
                "AJAX_OPTION_HISTORY" => "Y",
                "AJAX_LOADER" => null,
                "SHOW_MORE_BUTTON" => true,
                "NAV_OBJECT" => $arResult['ROWS']['NAVIGATE'],
                "TOTAL_ROWS_COUNT" => $arResult['ROWS']['NAVIGATE']->getRecordCount()
            ),
            false,
            array("HIDE_ICONS" => "Y")
        );?>
    </div>
</div>
<script>
    BX.ready(() => {
        BX.segmentsController = new Segments({
            componentName: '<?=$component->GetName()?>',
            container: 'segmentsContainer',
            params: <?=CUtil::PhpToJSObject($arParams)?>,
            templatePath: '<?=$this->GetFolder()?>/',
            curUrl: '<?=$arResult['CUR_URL']?>',
            gridId: '<?=$arResult['GRID_ID']?>'
        });
    });
</script>