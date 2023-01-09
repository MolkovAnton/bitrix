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

<div class="inn_checker_info">
    <div class="inn_checker_title">
        <span><?=GetMessage("INN_CHECKER.TITLE")?></span>
    </div>
    <div class="inn_checker_description"><?=GetMessage("INN_CHECKER.DESCRIPTION")?></div>
</div>

<div class="inn_checker_body">
    <form name="inn_checker" id="inn_checker">
        <div class="inn_checker_form_title"><?=GetMessage("INN_CHECKER.FORM_TITLE")?></div>
        <div class="inn_checker_type_chooser">
            <label for="inn_swith"><?=GetMessage("INN_CHECKER.INN_SWITCHER")?></label><input type="checkbox" name="inn_swith" value="<?=$arResult['PROP_NAME_IN_FILE']['INN']?>">
            <label for="tel_swith"><?=GetMessage("INN_CHECKER.TEL_SWITCHER")?></label><input type="checkbox" name="tel_swith" value="<?=$arResult['PROP_NAME_IN_FILE']['PHONE']?>">
            <label for="email_swith"><?=GetMessage("INN_CHECKER.EMAIL_SWITCHER")?></label><input type="checkbox" name="email_swith" value="<?=$arResult['PROP_NAME_IN_FILE']['EMAIL']?>">
            <label for="site_swith"><?=GetMessage("INN_CHECKER.SITE_SWITCHER")?></label><input type="checkbox" name="site_swith" value="<?=$arResult['PROP_NAME_IN_FILE']['SITE']?>">
        </div>
        <div class="inn_checker_file_block">
            <span><b><?=GetMessage("INN_CHECKER.CHOOSE_FILE")?></b></span><input accept=".csv" type="file" name="import" id="import">
        </div>
        <div class="bottom_buttons">
            <button id="button_send" class="popup-window-button ui-btn ui-btn-success"><?=GetMessage("INN_CHECKER.SEND")?></button>
        </div>
    </form>
</div>

<div id="dowload-link">
    <span class="find"></span>
    <span class="notFind"></span>
</div>
<div id="result-container"></div>

<script>
    BX.ready(function(){
        BX.InnChecker = BX.InnChecker.create(
            {
                form: 'inn_checker',
                sendButton: 'button_send',
                import: 'import',
                propNames: <?=CUtil::PhpToJSObject($arResult['PROP_NAMES'])?>,
                propNamesInFile: <?=CUtil::PhpToJSObject($arResult['PROP_NAME_IN_FILE'])?>,
                ajaxUrl: '<?=$this->getComponent()->GetPath()?>/ajax.php',
                resultContainer: 'result-container',
                downloadLink: 'dowload-link',
            }
        );
    });
</script>