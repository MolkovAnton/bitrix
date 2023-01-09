<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$role = $arResult['USER_ROLE'];
?>
<?if ($role === 'EDITOR' || $role === 'VIEW'):?>
<div class="settings_title">
    <h2>Ключевые слова для проверки сайта Бегимотом:</h2>
</div>
    <?foreach ($arResult['COMPANY_TYPE_LIST'] as $id => $companyType):?>

        <div class="settings_block">
            <h4><?=htmlspecialchars_decode($companyType['VALUE'])?></h4>
            <div class="parser_search_words">
                <div class="search_words">
                    <textarea name="search_words" id="search_words_<?=$id?>" data-id="<?=$id?>" <?=$role !== 'EDITOR' ? 'disabled' : ''?>><?=htmlspecialchars_decode($arResult['SETTINGS'][$id])?></textarea>
                    <?if ($role === 'EDITOR') :?>
                    <div id="set_search_words_button_<?=$id?>" class="ui-btn-primary ui-btn-main" data-id="<?=$id?>">Сохранить</div>
                    <?  endif;?>
                </div>
            </div>
        </div>

    <?  endforeach;?>
<?endif;?>
<script>
    BX.ready(function(){
        BX.ParserSettings = BX.ParserSettings.create(
            {
                settings: <?=CUtil::PhpToJSObject($arResult['SETTINGS'])?>,
                componentName: '<?=$component->GetName()?>',
            }
        );
    });
</script>