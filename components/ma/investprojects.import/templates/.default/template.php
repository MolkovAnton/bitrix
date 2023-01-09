<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
CJSCore::Init(['ui.progressbar']);
?>

<div class="container" id="investprojects">
    <div class="center">
        <textarea name="projects" rows="8" cols="60" placeholder="Введите id проектов через запятую"></textarea><br>
        <input type="checkbox" name="additional"><label for="additional"><?=GetMessage('ADDITIONAL_CHECKBOX')?></label> 
    </div>
    <div class="center"><div class="ui-btn-main ui-btn-primary"><?=GetMessage('SEND_BUTTON')?></div></div>
</div>
<div id="inputResults"></div>

<script>
    BX.ready(function(){
        BX.InvestProjectsImport = BX.InvestProjectsImport.create(
            {
                componentName: '<?=$component->GetName()?>',
                container: 'investprojects',
                inputResults: 'inputResults'
            }
        );
    });
</script>