<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
CJSCore::Init(array("popup"));
use Bitrix\Main\Localization\Loc;
?>

<script>
    BX.ready(() => {
        const params = <?=CUtil::PhpToJSObject($arResult)?>;
        params.componentName = '<?=$component->GetName()?>';
        params['message'] = {};
        params.message.haveOtherProducts = '<?=Loc::getMessage('HAVE_OTHER_PRODUCTS_'.$arResult["SOURCE"])?>';
        BX.BasketImport = new BasketImport(params);
    });
</script>