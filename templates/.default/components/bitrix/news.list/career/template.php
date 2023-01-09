<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */
$this->setFrameMode(true);
\Bitrix\Main\UI\Extension::load("ui.vue3");
?>   
<div class="vacancies">
    <div class="vacancies__head">
        <div class="vacancies__head-wrapper">
            <p class="vacancies__text">
                Если в процессе планирования будущего вы ищите карьерного роста, самореализации или хотите попробовать свои силы в другой специализации, то вы можете рассмотреть переход на новую должность в рамках нашей компании.<br><br>
            </p>
            <p class="vacancies__text">
                Если у вас есть резюме кандидата, но в списке нет подходящей вакансии, отправляйте его рекрутерам.
                Мы рассмотрим его в первую очередь, как только такая вакансия откроется.
                <button class="vacancies__button js-vacancyButton" @click="getForm('<?=$this->GetFolder()?>/form.php?id=<?=$arParams['WEB_FORM_INVITE_ID']?>')">
                    Отправить резюме без вакансии
                </button>
            </p>
        </div>
        <h1 class="vacancies__title title_decor">
            Актуальные вакансии компании
        </h1>
    </div>
    <div class="vacancies__tabs">
        <div class="vacancies__tab js-vacancyTab" data-department="all" @click="showDep" :class="{'is-active': this.currentDep === 'all'}">Все вакансии</div>
        <? foreach ($arResult['categoryes'] as $code => $name) {?>
            <div class="vacancies__tab js-vacancyTab" data-department="<?=$code?>" @click="showDep" :class="{'is-active': this.currentDep === '<?=$code?>'}"><?=$name?></div>
        <?}?>
    </div>
    <div class="vacancies__list">
        <? foreach ($arResult['ITEMS'] as $arItem) {?>
        <?
        $this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
        $this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
        ?>
        <div class="vacancy js-vacancy" v-show="showDepVacancies('<?=$arItem['PROPERTIES']['category']['VALUE_XML_ID']?>')" :class="{'is-opened': this.openedVacancy[<?=$arItem['ID']?>]}" id="<?=$this->GetEditAreaId($arItem['ID']);?>"> 
            <div class="vacancy__head js-vacancyOpener" @click='showVacancy(<?=$arItem['ID']?>)'>
                <div class="vacancy__tags">
                    <div class="vacancy__tag"><?=$arItem['PROPERTIES']['category']['VALUE']?></div>
                    <div class="vacancy__tag vacancy__tag_location <?=$arItem['PROPERTIES']['city']['VALUE'] ? '' : 'is-hidden'?>"><?=$arItem['PROPERTIES']['city']['VALUE']?></div>
                </div>
                <h2 class="vacancies__title">
                    <?=$arItem['NAME']?>
                </h2>
            </div>
            <div class="vacancy__body js-vacancyBody" ref="<?=$arItem['ID']?>">
                <?=$arItem['DETAIL_TEXT']?>
                <div class="vacancies__button_container">
                    <button class="vacancies__button js-vacancyButton" @click="getForm('<?=$this->GetFolder()?>/form.php?id=<?=$arParams['WEB_FORM_ID']?>&VACANCY_NAME=<?=$arItem['NAME']?>&VACANCY_ID=<?=$arItem['ID']?>&VACANCY_LOCATION=<?=$arItem['PROPERTIES']['city']['VALUE']?>')">
                        Рекомендовать
                    </button>
                    <button class="vacancies__button js-vacancyButton" @click="getForm('<?=$this->GetFolder()?>/form.php?id=<?=$arParams['WEB_FORM_SELF_ID']?>&VACANCY_NAME=<?=$arItem['NAME']?>&VACANCY_ID=<?=$arItem['ID']?>&VACANCY_LOCATION=<?=$arItem['PROPERTIES']['city']['VALUE']?>')">
                        Откликнуться самому
                    </button>
                </div>
            </div>
        </div>
        <?}?>
    </div>
</div>