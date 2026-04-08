<?php
/**
 * Пример шаблона компонента
 *
 * К этому моменту $arResult['ITEMS'] уже содержит
 * трансформированные данные из ElementDataExtractor:
 * - файловые поля = URL (не ID)
 * - enum = текст (не ID)
 * - HTML = строка (не массив с TEXT/TYPE)
 */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

if (empty($arResult['ITEMS'])) {
    return;
}
?>

<div class="news-list">
    <?php foreach ($arResult['ITEMS'] as $item): ?>
        <article class="news-list__item">
            <?php if (!empty($item['COVER'])): ?>
                <img
                    src="<?= $item['COVER'] ?>"
                    alt="<?= htmlspecialchars($item['NAME']) ?>"
                    class="news-list__cover"
                >
            <?php endif; ?>

            <h2 class="news-list__title">
                <?= htmlspecialchars($item['TITLE'] ?: $item['NAME']) ?>
            </h2>

            <?php if (!empty($item['STATUS'])): ?>
                <span class="news-list__status"><?= htmlspecialchars($item['STATUS']) ?></span>
            <?php endif; ?>

            <?php if (!empty($item['PRICE'])): ?>
                <div class="news-list__price"><?= number_format((float)$item['PRICE'], 0, '', ' ') ?> ₽</div>
            <?php endif; ?>

            <?php if (!empty($item['PREVIEW_TEXT'])): ?>
                <p class="news-list__text"><?= $item['PREVIEW_TEXT'] ?></p>
            <?php endif; ?>

            <?php if (!empty($item['BODY'])): ?>
                <div class="news-list__body"><?= $item['BODY'] ?></div>
            <?php endif; ?>

            <?php if (!empty($item['GALLERY']) && is_array($item['GALLERY'])): ?>
                <div class="news-list__gallery">
                    <?php foreach ($item['GALLERY'] as $imageUrl): ?>
                        <img src="<?= $imageUrl ?>" alt="" class="news-list__gallery-img">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
