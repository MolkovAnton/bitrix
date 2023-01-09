<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();?>
<li class="catalog-card__aside-item <?=$arResult['ACTIVE'] ? 'is-active' : ''?>" title="Избранное" onclick="handleFavorite('<?=$arResult['TYPE']?>', <?=$arResult['ID']?>, this);">
    <svg>
        <use xlink:href="/local/images/products/aside-menu-spite.svg#favorite"></use>
    </svg>
</li>
