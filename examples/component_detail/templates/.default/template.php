<?php
/**
 * Шаблон детальной страницы
 */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$item = $arResult['ITEM'];
?>

<article class="news-detail">
    <h1><?= htmlspecialchars($item['NAME']) ?></h1>

    <?php if (!empty($item['DETAIL_PICTURE'])): ?>
        <img src="<?= $item['DETAIL_PICTURE'] ?>" alt="<?= htmlspecialchars($item['NAME']) ?>">
    <?php endif; ?>

    <?php if (!empty($item['DETAIL_TEXT'])): ?>
        <div class="news-detail__content">
            <?= $item['DETAIL_TEXT'] ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($item['AUTHOR'])): ?>
        <p class="news-detail__author">Автор: <?= htmlspecialchars($item['AUTHOR']) ?></p>
    <?php endif; ?>
</article>
