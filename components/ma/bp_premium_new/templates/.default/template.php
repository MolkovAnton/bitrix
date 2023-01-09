<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
CJSCore::Init(array("jquery", 'popup'));
?>

<div class="bp_header">
    <button class="ui-btn ui-btn-primary" data-binded-element="bp_premium"><?=GetMessage("BP_PREMIUM_SEND_BUTTON_HEADER")?></button>
    <button class="popup-window-button ui-btn ui-btn-success" data-binded-element="bp_approve">
        <?=GetMessage("BP_PREMIUM_APPROVE_BUTTON_HEADER")?>
    </button>
    <span id="docsToApprove" class="hidden"></span>
    <button class="ui-btn ui-btn-primary right" data-binded-element="bp_history"><?=GetMessage("BP_PREMIUM_HYSTORY_BUTTON_HEADER")?></button>
</div>

<div id="bp_premium" class="hidden">
    <div>  
        <div class="bp_info">
            <div class="bp_premium_title"><?=GetMessage("BP_PREMIUM_TITLE")?></div>
            <div class="bp_premium_description">
                <?=GetMessage("BP_PREMIUM_DESCRIPTION")?> 
            </div> 
            <div class="bp_succsess hidden" data-form="bp_primium"><?=GetMessage("BP_PREMIUM_SUCCESS")?></div>
            <div class="bp_error hidden" data-form="bp_primium"><?=GetMessage("BP_PREMIUM_ERROR")?></div>
        </div>

        <div class="bp_premium_body">
            <?if ($arResult['CAN_ADD_PREMIUM'] === true) {?>
                <form name="bp_primium">
                    <input type="text" name="approver" hidden value="<?=$arResult['USER_APPROVERS']['ID']?>">
                    <div class="table_container">
                        <div id="bp_premium_table">
                            <div class="table_row header">
                                <div class="table_cell"><?=GetMessage("BP_PREMIUM_TABLE_HEADER_NAME")?></div>
                                <div class="table_cell"><?=GetMessage("BP_PREMIUM_TABLE_HEADER_SUMM")?></div>
                                <div class="table_cell"><?=GetMessage("BP_PREMIUM_TABLE_HEADER_COMMENT")?></div>
                                <div class="table_cell empty_cross"></div>
                            </div>
                            <div class="table_row">
                                <div class="table_cell select">
                                    <select data-for="<?=array_key_first($arResult['USER_SUBORDINATES'])?>" type="text" name="sub_name" onfocus="this.classList.remove('error');">
                                        <? foreach ($arResult['USER_SUBORDINATES'] as $sub) {?>
                                            <option value="<?=$sub['ID']?>"><?=$sub['LAST_NAME']?> <?=$sub['NAME']?></option>
                                        <? } ?>
                                    </select>
                                </div>
                                <div class="table_cell summ"><input data-for="<?=array_key_first($arResult['USER_SUBORDINATES'])?>" type="text" name="summ" onfocus="this.classList.remove('error');"></div>
                                <div class="table_cell"><textarea data-for="<?=array_key_first($arResult['USER_SUBORDINATES'])?>" type="text" name="comment"></textarea></div>
                                <div class="table_cell"><span class="delete_button" type="text" name="delete"></span></div>
                            </div>
                        </div>
                        <span id="add_row"><?=GetMessage("BP_PREMIUM_ADD_USER")?></span>
                        <div class="file_input">
                            <label for="file">Прикрепить файл</label>
                            <input type="file" name="file">
                        </div>
                    </div>
                    <div class="bottom_buttons">
                        <?if (!empty($arResult['USER_APPROVERS'])) {?>
                            <button id="button_send" class="popup-window-button ui-btn ui-btn-success"><?=GetMessage("BP_PREMIUM_SEND_BUTTON")?></button>
                        <?} else {?>
                            <span class="no_approver_button">
                                <span class="ui-btn"><?=GetMessage("BP_PREMIUM_SEND_BUTTON")?></span>
                                <span class="error_mes"><?=GetMessage("BP_PREMIUM_SEND_BUTTON_NO_APPROVER")?></span>
                            </span>
                        <?}?>
                        <button id="button_send_no_approve" class="popup-window-button ui-btn ui-btn-success"><?=GetMessage("BP_PREMIUM_SEND_BUTTON_NO_APPROVE")?></button>
                    </div>
                </form>

                <?if (!empty($arResult['USER_APPROVERS'])) {?>
                    <div class="approver_container">
                        <span class="approver_title"><?=GetMessage("BP_PREMIUM_APPROVER_TITLE")?></span>
                        <span class="approver_avatar">
                            <a href="/company/personal/user/<?=$arResult['USER_APPROVERS']['ID']?>/" class="">
                                <div class="approver_img" style="background-image: url('<?=$arResult['USER_APPROVERS']['IMG']?>');"></div>
                                <div class=""><?=$arResult['USER_APPROVERS']['LAST_NAME']." ".$arResult['USER_APPROVERS']['NAME']?></div>
                            </a>
                        </span>
                    </div>
                <?}?>
            <?} else {?>
            <div class="bp_error text_center"><?=GetMessage("BP_PREMIUM_CANT_ADD")?></div>
            <?}?>
        </div>
    </div>

    <?  foreach ($arResult['USER_PROJECTS'] as $project) {?>
    <div class="project">
        <div class="bp_info">
            <div class="bp_premium_title"><?=GetMessage("BP_PREMIUM_PROJECT_TITLE").' "'.$project['NAME'].'"'?></div>
            <div class="bp_premium_description">
                <?=GetMessage("BP_PREMIUM_PROJECT_DESCRIPTION")?>
            </div>
            <div class="bp_premium_description">
                <?=GetMessage("BP_PREMIUM_PROJECT_BUDGET")?>
                <?=$project['BUDGET_LEFT']['SUMM'].' '.$project['BUDGET_LEFT']['CURRENCY']?>
            </div>
            <div class="bp_succsess hidden" data-form="bp_primium_project_<?=$project['ID']?>"><?=GetMessage("BP_PREMIUM_SUCCESS_PROJECT")?></div>
            <div class="bp_error hidden" data-form="bp_primium_project_<?=$project['ID']?>"><?=GetMessage("BP_PREMIUM_ERROR")?></div>
        </div>
        <div class="bp_premium_body">
            <form name="bp_primium_project_<?=$project['ID']?>">
                <input type="text" name="approver" hidden value="<?=$project['APPROVER']['ID']?>">
                <input type="text" name="project" hidden value="<?=$project['ID']?>">
                <div class="table_container">
                    <div id="bp_premium_table">
                        <div class="table_row header">
                            <div class="table_cell"><?=GetMessage("BP_PREMIUM_TABLE_HEADER_NAME")?></div>
                            <div class="table_cell"><?=GetMessage("BP_PREMIUM_TABLE_HEADER_SUMM")?></div>
                            <div class="table_cell"><?=GetMessage("BP_PREMIUM_TABLE_HEADER_COMMENT")?></div>
                        </div>
                        <?foreach ($project['MEMBERS'] as $member) {?>
                        <div class="table_row">
                            <div class="table_cell"><div class="user_name"><?=$member['NAME']?></div></div>
                            <div class="table_cell summ"><input data-for="<?=$member['ID']?>" type="text" name="summ" onfocus="this.classList.remove('error');"></div>
                            <div class="table_cell"><textarea data-for="<?=$member['ID']?>" type="text" name="comment"></textarea></div>
                        </div>
                        <?}?>
                    </div>
                    <div class="file_input">
                        <label for="file">Прикрепить файл</label>
                        <input type="file" name="file">
                    </div>
                </div>
                <div class="bottom_buttons">
                        <button class="popup-window-button ui-btn ui-btn-success"><?=GetMessage("BP_PREMIUM_SEND_BUTTON")?></button>
                </div>
            </form>

            <?if (!empty($project['APPROVER'])) {?>
                <div class="approver_container">
                    <span class="approver_title"><?=GetMessage("BP_PREMIUM_APPROVER_TITLE")?></span>
                    <span class="approver_avatar">
                        <a href="/company/personal/user/<?=$arResult['USER_APPROVERS']['ID']?>/" class="">
                            <div class="approver_img" style="background-image: url('<?=$project['APPROVER']['IMG']?>');"></div>
                            <div class=""><?=$project['APPROVER']['LAST_NAME']." ".$project['APPROVER']['NAME']?></div>
                        </a>
                    </span>
                </div>
            <?}?>
        </div>
    </div>
    <? } ?>
</div>

<div id="bp_history" class="hidden">
    <div class="bp_info">
        <div class="bp_premium_title"><?=GetMessage("BP_PREMIUM_HISTORY_TITLE")?></div>
    </div>
    <div class="history_selector_container">
        <select id="history_selector">
            <option value="premium_history" selected><?=GetMessage("BP_PREMIUM_SELECTOR_FROM")?></option>
            <option value="premium_history_approve"><?=GetMessage("BP_PREMIUM_SELECTOR_TO")?></option>
        </select>
    </div>
    <div id="premium_history"></div>
    <div id="premium_history_approve" class="hidden"></div>
</div>

<div id="bp_approve" class="hidden">
    <div class="bp_info">
        <div class="bp_premium_title"><?=GetMessage("BP_PREMIUM_APPROVE_TITLE")?></div>
        <div class="bp_premium_description"><?=GetMessage("BP_PREMIUM_APPROVE_DESCRIPTION")?></div> 
    </div>

    <div id="new_elements_container"></div>
</div>

<script>
    BX.ready(function(){
        BX.BpPremiumNew = BX.BpPremiumNew.create(
            {
                canAddPremium: <?=$arResult['CAN_ADD_PREMIUM'] === true ? 'true' : 'false'?>,
                canAddProjecPremium: <?=!empty($arResult['USER_PROJECTS']) ? 'true' : 'false'?>,
                projects: <?=CUtil::PhpToJSObject($arResult['USER_PROJECTS'])?>,
                isApprover: <?=$arResult['IS_APPROVER'] === true ? 'true' : 'false'?>,
                container: 'bp_premium_body',
                approveContainer: 'new_elements_container',
                headerClass: 'bp_header',
                successMes: 'bp_succsess',
                errorMes: 'bp_error',
                sendButton: 'button_send',
                sendButtonNoApprove: 'button_send_no_approve',
                addRowButton: 'add_row',
                premiumTable: 'bp_premium_table',
                userSelect: 'sub_name',
                docsNumContainer: 'docsToApprove',
                approver: '<?=$arResult["USER_APPROVERS"]['ID']?>',
                ajaxUrl: '<?=$this->getComponent()->GetPath()?>/ajax.php',
                historyContainer: 'premium_history',
                historyApproveContainer: 'premium_history_approve',
                historySelector: 'history_selector'
            }
        );
    });
</script>