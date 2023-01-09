<?use Bitrix\Main\Localization\Loc;?>
<div class="side-tabs__wrapper">
    <div class="side-tabs__name"><h1><?=Loc::getMessage('MA_SEGMENTS.ADD_FAIL_TITLE')?></h1></div>
    <p class="text error"><?=Loc::getMessage('MA_SEGMENTS.ADD_FAIL_DESCRIPTION')?></p>
    <div><?=$this->error?></div>
    
    <div class="segment">
        <div class="segment__status">
            <a href="javascript:void(0)" class="link" data-events='click|getTemplate|"addSegment"'><?=Loc::getMessage('MA_SEGMENTS.ADD_FAIL_RETRY')?></a>
            <a href="<?=$this->curUrl?>" class="link"><?=Loc::getMessage('MA_SEGMENTS.ADD_FAIL_LIST')?></a>
        </div>
    </div>

</div>