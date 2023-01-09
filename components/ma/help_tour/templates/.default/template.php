<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
CJSCore::Init(['ui.tour']);
?>

<div class="help-panel__item help-panel__item_help">
    <p class="help-panel__text">
        Чтобы запустить быстрый курс подсказок по интерфейсу нажмите сюда
    </p>
    <a href="javascript:void(0)" id="newHelpTour" class="js-closeHelpPanel">
        <img src="<?=SITE_TEMPLATE_PATH?>/images/help-panel/help.svg" alt="">
    </a>
</div>

<script>
    BX.ready(function(){
        if (BX.HelpTour.create) {
            BX.HelpTour = BX.HelpTour.create(
                {
                    componentName: '<?=$component->GetName()?>',
                    steps: <?=CUtil::PhpToJSObject($arResult['STEPS'])?>,
                    messages: {
                        startPopupText: "<?=GetMessage("START_POPUP_TEXT")?>",
                        startPopupTitle: "<?=GetMessage("START_POPUP_TITLE")?>",
                        startPopupButton: "<?=GetMessage("START_POPUP_BUTTON")?>",
                        closePopupClose: "<?=GetMessage("START_POPUP_CLOSE")?>",
                    },
                    componentParams: <?=CUtil::PhpToJSObject($arParams)?>,
                    newTourButton: "newHelpTour",
                    autoStart: <?=CUtil::PhpToJSObject($arResult['AUTO_START'])?>
                }
            );
        }
    });
</script>