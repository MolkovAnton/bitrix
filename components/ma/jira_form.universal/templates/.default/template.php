<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
$APPLICATION->SetAdditionalCSS('/bitrix/js/ui/entity-editor/entity-editor.min.css');
$APPLICATION->SetAdditionalCSS('/bitrix/js/ui/forms/ui.forms.min.css');
$APPLICATION->SetAdditionalCSS('/bitrix/js/ui/buttons/src/css/ui.buttons.css');
?>
<div id="jira_form_container_<?=$arResult['FORM_NAME']?>">
    <form class="jira-form" id="jira_form_<?=$arResult['FORM_NAME']?>"> 
        <? foreach ($arResult['FIELDS'] as $name => $field):?>
            <? if ($field['TYPE'] !== 'hidden') :?>
                <div class="ui-entity-editor-block-title ui-entity-widget-content-block-title-edit">
                    <label class="ui-entity-editor-block-title-text" for="<?=$name?>"><?=$field['TITLE']?></label>
                </div>
            <? endif; ?>
            <? if ($field['TYPE'] === 'hidden') :?>
                <input type="hidden" name="<?=$name?>" value="<?=$field['VALUE']?>">
            <? elseif ($field['TYPE'] === 'string') :?>
                <div class="ui-entity-editor-content-block">
                    <input class="ui-ctl-element" type="text" value="<?$field['VALUE']?>" name="<?=$name?>" <?=$field['REQUIRED'] === 'Y' ? 'required' : ''?> data-alias="<?=$field['ALIAS']?>">
                </div>
            <?elseif ($field['TYPE'] === 'textarea') :?>
                <div class="ui-entity-editor-content-block">
                    <textarea class="ui-entity-editor-field-textarea" rows="<?=$field['ROWS'] ?: 5?>" name="<?=$name?>" <?=$field['REQUIRED'] === 'Y' ? 'required' : ''?> data-alias="<?=$field['ALIAS']?>"></textarea>
                </div>
            <?elseif ($field['TYPE'] === 'list') :?>
                <div class="ui-entity-editor-content-block">
                    <span class="fields enumeration field-wrap">
                        <span class="enumeration-select field-item">
                            <select name="<?=$name?>" <?=$field['MULTIPLE'] === 'Y' ? 'multiple' : ''?>>
                                <?  foreach ($field['OPTIONS'] as $type) {?>
                                <option value="<?=$type['VALUE']?>"><?=$type['NAME']?></option>
                                <?}?>
                            </select>
                        </span>
                    </span>
                </div>
            <? elseif ($field['TYPE'] === 'user') :?>
                <div class="ui-entity-editor-content-block">
                    <?$APPLICATION->IncludeComponent(
                        'bitrix:main.user.selector',
                        '',
                        [
                            'ID' => $name,
                            'INPUT_NAME' => $name,
                        ],
                        null,
                        array('HIDE_ICONS' => 'Y')
                    );?>
                </div>
            <? elseif ($field['TYPE'] === 'date') :?>
                <div class="ui-entity-editor-content-block">
                    <input class="ui-ctl-element" type="text" value="" name="<?=$name?>" onclick="BX.calendar({node: this, field: this, bTime: false, callback_after: BX.delegate(formatTime, this)});">
                </div>
            <? endif;?>
        <? endforeach;?>
        <div class="ui-entity-control">
            <button class="ui-btn ui-btn-success" title="Отправить">Отправить</button>   
        </div>
    </form>
</div>

<script>
    BX.ready(function(){
        BX.JiraFormUniversal['<?=$arResult['FORM_NAME']?>'] = BX.JiraFormUniversal.create(
            {
                componentName: '<?=$component->GetName()?>',
                formId: 'jira_form_<?=$arResult['FORM_NAME']?>',
                container: 'jira_form_container_<?=$arResult['FORM_NAME']?>',
                options: <?=CUtil::PhpToJSObject($arResult['OPTIONS'])?>,
                requestOptions: <?=CUtil::PhpToJSObject($arResult['REQUEST_OPTIONS'])?>,
                usersFields: <?=CUtil::PhpToJSObject($arResult['USERS_FIELDS'])?>,
                alias: <?=CUtil::PhpToJSObject($arResult['ALIAS'])?>,
            }
        );
    });
    
    function formatTime(time) {
        let date = new Date(time);
        this.value = date.toISOString().slice(0,10);
    }
</script>