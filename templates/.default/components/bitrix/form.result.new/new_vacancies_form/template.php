<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>

<div class="web-form js-webForm <?=$arParams["ADDITIONAL_CLASS"]?>">
    <?if(!empty($arResult["FORM_NOTE"])) {?>
        <div class="web-form__message"><?=!empty($arParams["SUCCESS_MESSAGE"]) ? $arParams["SUCCESS_MESSAGE"] : $arResult["FORM_NOTE"];?></div>
    <?}else{?>
        <? echo $arResult["FORM_HEADER"];

            if ($arResult["isFormTitle"] && $arParams['HIDE_TITLE'] != 'Y')
            {
                ?><h2 class="web-form__title"><?=$arResult["FORM_TITLE"]?></h2><?
            }

            if ($arResult["isFormDescription"] && $arParams['HIDE_DESCRIPTION'] != 'Y')
            {
                ?><p class="web-form__description"><?=$arResult["FORM_DESCRIPTION"]?></p><?
            }

            if ($arResult["isFormImage"] == "Y")
            {
                ?><img src="<?=$arResult["FORM_IMAGE"]["URL"]?>" class="web-form__img"><?
            }

            foreach ($arResult["QUESTIONS"] as $FIELD_SID => $arQuestion)
            {
                $error = '';
                if(!empty($arResult["FORM_ERRORS"][$FIELD_SID])) {
                    $error = 'error';
                }

                $required = $requiredLabel = '';
                if($arQuestion["REQUIRED"] == "Y"){
                    $required = 'required';
                    $requiredLabel = '<span class="label__required">*</span>';
                }

                $type = $arQuestion['STRUCTURE'][0]['FIELD_TYPE'];

                switch ($type)
                {
                    case "hidden":
                        if(!empty($arQuestion['DEFAULT_VALUE'])) {
                            $arQuestion["HTML_CODE"] = str_replace(
                                'value=""',
                                'value="'.$arQuestion['DEFAULT_VALUE'].'"',
                                $arQuestion["HTML_CODE"]
                            );
                        }
                        echo $arQuestion["HTML_CODE"];
                        break;
                    case "text":
                    case "password":
                    case "email":
                    case "url":
                        ?>
                        <label class="label label_column <?=$error?>">
                            <?if($arParams['HIDE_FIELDS_NAME'] != 'Y') {?>
                                <span class="label__title"><?=$arQuestion["CAPTION"]?> <?=$requiredLabel?></span>
                            <?}?>
                            <span class="label__content ">
                                <?foreach($arQuestion['STRUCTURE'] as $key => $item) {
                                    $name = "form_" . $item['FIELD_TYPE'] . "_" . $item['ID'];
                                    if(!isset($arResult["arrVALUES"][$name]) && !empty($arQuestion['DEFAULT_VALUE']))
                                    {
                                        $arResult["arrVALUES"][$name] = $arQuestion['DEFAULT_VALUE'];
                                    }
                                    ?><input
                                        type="<?=$item['FIELD_TYPE']?>"
                                        <?=$item['FIELD_PARAM']?>
                                        <?=$required?>
                                        name="<?=$name?>"
                                        value="<?=htmlspecialcharsbx($arResult["arrVALUES"][$name])?>">
                                <?}?>
                            </span>
                        </label>
                        <?
                        break;
                    case "date":
                        ?>
                        <label class="label label_column js-datepicker <?=$error?>">
                            <?if($arParams['HIDE_FIELDS_NAME'] != 'Y') {?>
                                <span class="label__title"><?=$arQuestion["CAPTION"]?> <?=$requiredLabel?></span>
                            <?}?>
                            <span class="label__content ">
                                <?foreach($arQuestion['STRUCTURE'] as $key => $item) {
                                    $name = "form_" . $item['FIELD_TYPE'] . "_" . $item['ID'];
                                    if(!isset($arResult["arrVALUES"][$name]) && !empty($arQuestion['DEFAULT_VALUE']))
                                    {
                                        $arResult["arrVALUES"][$name] = $arQuestion['DEFAULT_VALUE'];
                                    }
                                    ?><input
                                        type="text"
                                        <?=$item['FIELD_PARAM']?>
                                        <?=$required?>
                                        name="<?=$name?>"
                                        autocomplete="off"
                                        value="<?=htmlspecialcharsbx($arResult["arrVALUES"][$name])?>"><?
                                }?>
                            </span>
                        </label>
                        <?
                        break;
                    case "textarea":
                        ?>
                        <label class="label label_column <?=$error?>">
                            <?if($arParams['HIDE_FIELDS_NAME'] != 'Y') {?>
                                <span class="label__title"><?=$arQuestion["CAPTION"]?> <?=$requiredLabel?></span>
                            <?}?>
                            <span class="label__content ">
                                <?foreach($arQuestion['STRUCTURE'] as $key => $item) {
                                    $name = "form_" . $item['FIELD_TYPE'] . "_" . $item['ID'];
                                    if(!isset($arResult["arrVALUES"][$name]) && !empty($arQuestion['DEFAULT_VALUE']))
                                    {
                                        $arResult["arrVALUES"][$name] = $arQuestion['DEFAULT_VALUE'];
                                    }
                                    ?><textarea
                                        <?=$item['FIELD_PARAM']?>
                                        name="<?=$name?>"
                                        <?=$required?>><?=htmlspecialcharsbx($arResult["arrVALUES"][$name])?></textarea><?
                                }?>
                            </span>
                        </label>
                        <?
                        break;
                    case "radio":
                        ?>
                        <div class="label label_column <?=$error?>">
                            <?if($arParams['HIDE_FIELDS_NAME'] != 'Y') {?>
                                <span class="label__title"><?=$arQuestion["CAPTION"]?> <?=$requiredLabel?></span>
                            <?}?>
                            <span class="label__content ">
                                <?foreach($arQuestion['STRUCTURE'] as $key => $item) {
                                    $name = "form_" . $item['FIELD_TYPE'] . "_" . $FIELD_SID;
                                    $text = !empty($item['MESSAGE']) ? $item['MESSAGE'] : $item['VALUE'];
                                    if(!isset($arResult["arrVALUES"][$name]) && !empty($arQuestion['DEFAULT_VALUE']))
                                    {
                                        $arResult["arrVALUES"][$name] = $arQuestion['DEFAULT_VALUE'];
                                    }
                                    ?><label class="custom-input custom-input__radio">
                                        <input
                                            <?=$required?>
                                            <?=$item['FIELD_PARAM']?>
                                            name="<?=$name?>"
                                            type="radio"
                                            value="<?=$item['ID']?>"
                                            <?if($arResult["arrVALUES"][$name] == $item['ID']){?>checked="checked"<?}?>>
                                        <span class="custom-input__item"></span>
                                        <span class="custom-input__title" title="<?=$text?>"><?=$text?></span>
                                    </label>
                                <?}?>
                            </span>
                        </div>
                        <?
                        break;
                    case "checkbox":
                        ?>
                        <div class="label label_column <?=$error?>">
                            <?if($arParams['HIDE_FIELDS_NAME'] != 'Y') {?>
                                <span class="label__title"><?=$arQuestion["CAPTION"]?> <?=$requiredLabel?></span>
                            <?}?>
                            <span class="label__content ">
                                <?foreach($arQuestion['STRUCTURE'] as $key => $item) {
                                    $name = "form_" . $item['FIELD_TYPE'] . "_" . $FIELD_SID;
                                    $text = !empty($item['MESSAGE']) ? $item['MESSAGE'] : $item['VALUE'];
                                    if(!isset($arResult["arrVALUES"][$name]) && !empty($arQuestion['DEFAULT_VALUE']))
                                    {
                                        $arResult["arrVALUES"][$name] = $arQuestion['DEFAULT_VALUE'];
                                    }
                                    ?><label class="custom-input">
                                        <input
                                            name="<?=$name?>[]"
                                            <?=$item['FIELD_PARAM']?>
                                            type="checkbox"
                                            value="<?=$item['ID']?>"
                                            <?if(in_array($item['ID'], $arResult["arrVALUES"][$name])){?>checked="checked"<?}?>>
                                        <span class="custom-input__item"></span>
                                        <span class="custom-input__title" title="<?=$text?>"><?=$text?></span>
                                    </label>
                                <?}?>
                            </span>
                        </div>
                        <?
                        break;
                    case "dropdown":
                        $name = "form_" . $arQuestion['STRUCTURE'][0]['FIELD_TYPE'] . "_" . $FIELD_SID;
                        if(!empty($arQuestion['CUSTOM_FIELD_NAME'])) {
                            $name = $arQuestion['CUSTOM_FIELD_NAME'];
                        }
                        if(!isset($arResult["arrVALUES"][$name]) && !empty($arQuestion['DEFAULT_VALUE'])) {
                            $arResult["arrVALUES"][$name] = $arQuestion['DEFAULT_VALUE'];
                        }?>
                        <label class="label label_column web-form__select js-webFormSelect <?=$error?>">
                            <?if($arParams['HIDE_FIELDS_NAME'] != 'Y') {?>
                                <span class="label__title"><?=$arQuestion["CAPTION"]?> <?=$requiredLabel?></span>
                            <?}?>
                            <span class="label__content ">
                                <select <?=$required?> name="<?=$name?>">
                                    <?foreach($arQuestion['STRUCTURE'] as $key => $item) {
                                        $text = !empty($item['MESSAGE']) ? $item['MESSAGE'] : $item['VALUE'];
                                        ?><option
                                            <?if($item['ID'] == $arResult["arrVALUES"][$name]){?>selected="selected"<?}?>
                                            value="<?=$item['ID']?>"><?=$text?></option><?
                                    }?>
                                </select>
                            </span>
                        </label>
                        <?
                        break;
                    case "multiselect":
                        $name = "form_" . $arQuestion['STRUCTURE'][0]['FIELD_TYPE'] . "_" . $FIELD_SID;
                        if(!isset($arResult["arrVALUES"][$name]) && !empty($arQuestion['DEFAULT_VALUE']))
                        {
                            $arResult["arrVALUES"][$name] = $arQuestion['DEFAULT_VALUE'];
                        }?>
                        <label class="label label_column web-form__select js-webFormSelect <?=$error?>">
                            <?if($arParams['HIDE_FIELDS_NAME'] != 'Y') {?>
                                <span class="label__title"><?=$arQuestion["CAPTION"]?> <?=$requiredLabel?></span>
                            <?}?>
                            <span class="label__content ">
                                <select <?=$required?> multiple name="<?=$name?>[]">
                                    <?foreach($arQuestion['STRUCTURE'] as $key => $item) {
                                        $text = !empty($item['MESSAGE']) ? $item['MESSAGE'] : $item['VALUE'];
                                        ?><option
                                            <?if(in_array($item['ID'], $arResult["arrVALUES"][$name])){?>selected="selected"<?}?>
                                            value="<?=$item['ID']?>"><?=$text?></option><?
                                    }?>
                                </select>
                            </span>
                        </label>
                        <?
                        break;

                    case 'image':
                        foreach($arQuestion['STRUCTURE'] as $key => $item) {?>
                            <div class="label label__load_image js-loadBlock <?=$error?>" data-type="image"><?
                                if($key == 0) {
                                    ?><span class="label__title"><?=$arQuestion["CAPTION"]?> <?=$requiredLabel?></span><?
                                }
                                $name = "form_" . $item['FIELD_TYPE'] . "_" . $item['ID'];
                                ?>
                                <input <?=$required?> type="file" name="<?=$name?>" type="file" accept=".jpg, .jpeg, .png, .gif, .bmp">
                            </div>
                        <?}
                        break;
                    case 'file':
                        foreach($arQuestion['STRUCTURE'] as $key => $item) {?>
                            <div class="label label__load_file js-loadBlock <?=$error?>"><?
                                if($key == 0) {
                                    ?><span class="label__title"><?=$arQuestion["CAPTION"]?> <?=$requiredLabel?></span><?
                                }
                                $name = "form_" . $item['FIELD_TYPE'] . "_" . $item['ID'];
                                ?>
                                <input <?=$required?> type="file" name="<?=$name?>">
                            </div>
                        <?}
                        break;
                }
            }
            ?>

            <input
                <?=(intval($arResult["F_RIGHT"]) < 10 ? "disabled=\"disabled\"" : "");?>
                type="submit"
                name="web_form_submit"
                class="button button__conversion"
                value="<?=htmlspecialcharsbx(trim($arResult["arForm"]["BUTTON"]) == '' ? GetMessage("FORM_ADD") : $arResult["arForm"]["BUTTON"]);?>" />
            
            <?if ($arResult["isFormErrors"] == "Y") {?>
                <div class="web-form__message error">
                    <?if($arParams["USE_EXTENDED_ERRORS"] == "Y") {?>
                        <p>
                            <font class="errortext">
                                <?
                                foreach($arResult['FORM_ERRORS'] as $FIELD_SID => $errorText)
                                {
                                    echo GetMessage("FORM_ERROR_MESSAGE") . " " . $arResult['QUESTIONS'][$FIELD_SID]['CAPTION'];
                                }
                                ?>
                                <br>
                            </font>
                        </p>
                    <?} else {?>
                        <?=$arResult["FORM_ERRORS_TEXT"];?>
                    <?}?>
                </div>
            <?}?>

            <?=$arResult["FORM_FOOTER"]?>
    <?}?>
</div>