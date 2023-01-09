<?use Bitrix\Main\Localization\Loc;?>
<div class="side-tabs__name"><h1><?=Loc::getMessage('MA_SEGMENTS.CREATE_TITLE')?></h1></div>
<p class="text"><?=Loc::getMessage('MA_SEGMENTS.CREATE_DESCRIPTION')?></p>
<div class="segment">
	<form>
		<textarea class="segment__area" data-name="guid" data-events="paste|checkInput;keyup|checkInput;cut|checkInput"></textarea>
		<div class="segment__buttons">
			<button class="ui-btn ui-btn-lg ui-btn-primary" data-events="click|uploadGuids" data-name="uploadGuidsButton" disabled><?=Loc::getMessage('MA_SEGMENTS.CREATE_LOAD')?></button>
		</div>
	</form>
</div>
<div id="search_result"></div>