<?use Bitrix\Main\Localization\Loc;?>
<div class="side-tabs__wrapper">
    <div class="side-tabs__name"><h1><?=Loc::getMessage('MA_SEGMENTS.ADD_SUCCESS_TITLE')?></h1></div>
    <p class="text">Сегмент успешно создан</p>

    <div class="segment">
        <div class="segment__status">
            <a href="javascript:void(0)" class="link" data-events='click|getTemplate|"addSegment"'><?=Loc::getMessage('MA_SEGMENTS.ADD_SUCCESS_NEW')?></a>
            <a href="<?=$this->curUrl?>?segment=<?=$this->segmentId?>" class="link"><?=Loc::getMessage('MA_SEGMENTS.ADD_SUCCESS_OPEN')?></a>
            <a href="<?=$this->curUrl?>" class="link"><?=Loc::getMessage('MA_SEGMENTS.ADD_SUCCESS_LIST')?></a>
        </div>
    </div>
</div>