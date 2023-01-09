<?use Bitrix\Main\Localization\Loc;?>
<div class="side-tabs__name"><?=Loc::getMessage('MA_SEGMENTS.SEARCH_RESULT_TITLE')?></div>
<p class="text"><?=Loc::getMessage('MA_SEGMENTS.SEARCH_RESULT_DESCRIPTION')?></p>
<div class="segment">
    <ul class="segment__partners">
        <? foreach ($this->partners as $guid => $partner) {?>
            <? if (isset($partner['SUB_RESULT'])) {?>
            <li class="segment__partner error js-segmentParnert" data-name="partner">
                <?=$guid?>:
                <div class="segment__partner-inner">
                    <? foreach ($partner['SUB_RESULT'] as $key => $subPartner) {?>
                    <div class="segment__partner-name" data-name="partnerName">
                        <?=$subPartner['NAME']?>
                        <a href="javascript:void(0)" class="segment__partner-remove js-segmentParnertRemove" data-events='click|deleteDuplicate|<?=json_encode(["guid"=>$guid, "key"=>$key])?>' data-name="partnerRemove">
                            <?=Loc::getMessage('MA_SEGMENTS.SEARCH_RESULT_DELETE')?>
                        </a>
                    </div>
                    <?}?>
                </div>
            </li>
            <?} else {?>
            <li class="segment__partner js-segmentParnert">
                <?=$guid?>:
                <div class="segment__partner-inner">
                    <div class="segment__partner-name">
                        <?=$partner['NAME']?>
                    </div>
                </div>
            </li>
            <?}?>
        <?}?>
        <? foreach ($this->notFound as $guid) {?>
            <li class="segment__partner error js-segmentParnert">
                <?=$guid?>:
                <div class="segment__partner-inner"></div>
            </li>
        <?}?>
    </ul>
    <form class="segment__create">
        <label class="label label_column">
            <span class="label__title"><?=Loc::getMessage('MA_SEGMENTS.SEARCH_RESULT_SEGMENT_NAME')?></span>
            <span class="label__content">
                <input type="text" class="input" name="" maxlength="" placeholder="<?=Loc::getMessage('MA_SEGMENTS.SEARCH_RESULT_SEGMENT_NAME_PLACEHOLDER')?>">
            </span>
        </label>
        <button class="ui-btn ui-btn-lg ui-btn-primary" data-events="click|addSegment"><?=Loc::getMessage('MA_SEGMENTS.SEARCH_RESULT_SEGMENT_BUTTON')?></button>
    </form>
</div>