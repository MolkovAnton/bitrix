<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
use Bitrix\Main\Localization\Loc;
$messages = Loc::loadLanguageFile(__FILE__);
?>
<div id="delivery-map" class="map-container"></div>

<script>
    BX.ready(function(){
        let params = {
            container: 'delivery-map',
            startPoint: [<?=$arResult['START_POINT']['LAT']?>, <?=$arResult['START_POINT']['LON']?>],
            iframe: '<?=$arResult['IFRAME']?>',
            zonesPatch: '<?=$arResult['ZONES_PATH']?>',
            exportProps: {
                zone: '<?=$arResult['ZONE_PROP_ID']?>',
                address: '<?=$arResult['ADDRESS_PROP_ID']?>'
            },
            selectedPoint: <?=CUtil::PhpToJSObject($arResult['SELECTED_POINT'])?>,
            coordsProp: '<?=$arResult['COORDS_PROP']?>'
        };
        params.messages = <?=CUtil::PhpToJSObject($messages)?>;
        BX.DeliveryMap = new DeliveryMap(params);
    });
</script>