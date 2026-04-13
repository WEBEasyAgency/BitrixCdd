<?php
/**
 * Шаблон списка с пагинацией
 */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$items = $arResult['ITEMS'] ?? [];
$nav = $arResult['NAV'] ?? [];
?>

<div class="news-list">
    <?php foreach ($items as $item): ?>
        <article class="news-list__item">
            <?php if (!empty($item['PREVIEW_PICTURE'])): ?>
                <img src="<?= $item['PREVIEW_PICTURE'] ?>" alt="<?= htmlspecialchars($item['NAME']) ?>">
            <?php endif; ?>
            <h2><?= htmlspecialchars($item['NAME']) ?></h2>
            <?php if (!empty($item['PREVIEW_TEXT'])): ?>
                <p><?= htmlspecialchars($item['PREVIEW_TEXT']) ?></p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>

<?php if ($nav['TOTAL_PAGES'] > 1): ?>
<nav class="pagination">
    <?php if ($nav['HAS_PREV']): ?>
        <a href="?page=<?= $nav['PAGE'] - 1 ?>">Назад</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $nav['TOTAL_PAGES']; $i++): ?>
        <?php if ($i === $nav['PAGE']): ?>
            <span class="pagination__current"><?= $i ?></span>
        <?php else: ?>
            <a href="?page=<?= $i ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($nav['HAS_NEXT']): ?>
        <a href="?page=<?= $nav['PAGE'] + 1 ?>">Вперёд</a>
    <?php endif; ?>

    <span class="pagination__info">
        Страница <?= $nav['PAGE'] ?> из <?= $nav['TOTAL_PAGES'] ?> (<?= $nav['TOTAL'] ?> элементов)
    </span>
</nav>
<?php endif; ?>
