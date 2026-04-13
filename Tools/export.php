<?php
/**
 * Экспорт существующих инфоблоков в CDD-конфиги
 *
 * Доступ:
 *   /local/src/BitrixCdd/Tools/export.php?key=YOUR_SECRET_KEY
 *   /local/src/BitrixCdd/Tools/export.php  (для админа)
 *
 * GET-параметры:
 *   key      - секретный ключ
 *   types    - фильтр по типам через запятую
 *   action   - 'preview' | 'download' (по умолчанию показывает UI)
 *   demo[]   - коды инфоблоков для экспорта demo_data
 */

const EXPORT_SECRET_KEY = 'cdd-export-change-me';

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

// -- Авторизация --
$isAdmin = false;
global $USER;
if (is_object($USER) && $USER->IsAdmin()) {
    $isAdmin = true;
}

$key = $_GET['key'] ?? '';
if (!$isAdmin && $key !== EXPORT_SECRET_KEY) {
    http_response_code(403);
    die('Access denied');
}

if (EXPORT_SECRET_KEY === 'cdd-export-change-me' && !$isAdmin) {
    http_response_code(403);
    die('Change EXPORT_SECRET_KEY before using key-based access');
}

// -- Автозагрузка --
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/src/BitrixCdd/Core/Autoloader.php';
$loader = new \BitrixCdd\Core\Autoloader();
$loader->addNamespace('BitrixCdd', $_SERVER['DOCUMENT_ROOT'] . '/local/src/BitrixCdd');
$loader->register();

// -- Параметры --
$typeFilter = [];
if (!empty($_GET['types'])) {
    $typeFilter = array_map('trim', explode(',', $_GET['types']));
}

$demoDataIblocks = $_GET['demo'] ?? [];
if (is_string($demoDataIblocks)) {
    $demoDataIblocks = array_map('trim', explode(',', $demoDataIblocks));
}

$action = $_GET['action'] ?? '';
$authParam = $isAdmin ? '' : '&key=' . urlencode($key);

// -- Действия --
if ($action === 'download' || $action === 'preview') {
    $exporter = new \BitrixCdd\Tools\ConfigExporter($typeFilter, $demoDataIblocks);

    if ($action === 'download') {
        $zipData = $exporter->exportZip();
        $filename = 'cdd-config-' . date('Y-m-d-His') . '.zip';

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($zipData));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $zipData;
        exit;
    }

    // Preview
    $files = $exporter->export();
    $assets = $exporter->getAssetFiles();

    header('Content-Type: text/html; charset=utf-8');
    renderPreview($files, $assets, $demoDataIblocks, $typeFilter, $authParam);
    exit;
}

// -- UI: выбор инфоблоков --
$exporter = new \BitrixCdd\Tools\ConfigExporter($typeFilter);
$iblockList = $exporter->getIblockList();

header('Content-Type: text/html; charset=utf-8');
renderUI($iblockList, $typeFilter, $authParam);

// ── Рендеринг ──────────────────────────────────────────────────

function renderUI(array $iblockList, array $typeFilter, string $authParam): void
{
    $typesParam = !empty($typeFilter) ? '&types=' . htmlspecialchars(implode(',', $typeFilter)) : '';
    ?>
<!DOCTYPE html><html><head><meta charset="utf-8">
<title>CDD Config Export</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: -apple-system, sans-serif; background: #1e1e2e; color: #cdd6f4; padding: 24px; max-width: 900px; margin: 0 auto; }
    h1 { margin-bottom: 8px; color: #cba6f7; }
    .subtitle { color: #6c7086; margin-bottom: 24px; }
    form { background: #313244; border-radius: 8px; padding: 20px; }
    .section { margin-bottom: 16px; }
    .section-title { font-weight: bold; margin-bottom: 8px; color: #a6e3a1; }
    .iblock-list { max-height: 400px; overflow-y: auto; }
    .iblock-row { display: flex; align-items: center; gap: 10px; padding: 6px 8px; border-radius: 4px; }
    .iblock-row:hover { background: #45475a; }
    .iblock-row label { flex: 1; cursor: pointer; display: flex; gap: 10px; align-items: center; }
    .iblock-row .type { color: #6c7086; font-size: 12px; min-width: 80px; }
    .iblock-row .name { flex: 1; }
    .iblock-row .count { color: #6c7086; font-size: 12px; }
    .actions { display: flex; gap: 12px; margin-top: 20px; }
    .btn { padding: 10px 24px; border-radius: 6px; border: none; cursor: pointer; font-size: 14px; font-weight: bold; text-decoration: none; display: inline-block; }
    .btn-primary { background: #a6e3a1; color: #1e1e2e; }
    .btn-primary:hover { background: #94e2d5; }
    .btn-secondary { background: #45475a; color: #cdd6f4; }
    .btn-secondary:hover { background: #585b70; }
    .select-controls { margin-bottom: 8px; }
    .select-controls a { color: #89b4fa; cursor: pointer; font-size: 13px; margin-right: 12px; }
    .note { color: #6c7086; font-size: 13px; margin-top: 16px; }
    input[type="checkbox"] { width: 16px; height: 16px; accent-color: #a6e3a1; }
</style>
</head><body>
<h1>CDD Config Export</h1>
<p class="subtitle">Экспорт инфоблоков в конфиги BitrixCdd</p>

<form method="get">
    <?php if ($authParam): ?>
        <input type="hidden" name="key" value="<?= htmlspecialchars($_GET['key'] ?? '') ?>">
    <?php endif; ?>
    <?php if (!empty($typeFilter)): ?>
        <input type="hidden" name="types" value="<?= htmlspecialchars(implode(',', $typeFilter)) ?>">
    <?php endif; ?>

    <div class="section">
        <div class="section-title">Структура (всегда экспортируется)</div>
        <p style="color: #6c7086; font-size: 13px;">global.php, типы инфоблоков, инфоблоки со свойствами</p>
    </div>

    <div class="section">
        <div class="section-title">Демо-данные (выбери инфоблоки)</div>
        <div class="select-controls">
            <a onclick="document.querySelectorAll('input[name=\'demo[]\']').forEach(c=>c.checked=true)">Выбрать все</a>
            <a onclick="document.querySelectorAll('input[name=\'demo[]\']').forEach(c=>c.checked=false)">Снять все</a>
        </div>
        <div class="iblock-list">
            <?php
            $currentType = '';
            foreach ($iblockList as $ib):
                if ($ib['type'] !== $currentType):
                    $currentType = $ib['type'];
                    echo '<div style="padding:4px 8px;color:#6c7086;font-size:11px;text-transform:uppercase;margin-top:8px">' . htmlspecialchars($currentType) . '</div>';
                endif;
            ?>
            <div class="iblock-row">
                <label>
                    <input type="checkbox" name="demo[]" value="<?= htmlspecialchars($ib['code']) ?>">
                    <span class="name"><?= htmlspecialchars($ib['name']) ?></span>
                    <span class="count"><?= $ib['count'] ?> эл.</span>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="note">Файлы (изображения и т.д.) копируются в assets/. Для инфоблоков с большим количеством элементов экспорт может занять время.</p>
    </div>

    <div class="actions">
        <button type="submit" name="action" value="download" class="btn btn-primary">Скачать ZIP</button>
        <button type="submit" name="action" value="preview" class="btn btn-secondary">Предпросмотр</button>
    </div>
</form>
</body></html>
    <?php
}

function renderPreview(array $files, array $assets, array $demoIblocks, array $typeFilter, string $authParam): void
{
    $downloadParams = http_build_query(array_filter([
        'action' => 'download',
        'key' => $_GET['key'] ?? '',
        'types' => !empty($typeFilter) ? implode(',', $typeFilter) : '',
        'demo' => $demoIblocks,
    ]));
    ?>
<!DOCTYPE html><html><head><meta charset="utf-8">
<title>CDD Config Export - Preview</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: monospace; background: #1e1e2e; color: #cdd6f4; padding: 20px; }
    h1 { margin-bottom: 8px; color: #cba6f7; }
    .info { color: #6c7086; margin-bottom: 16px; }
    .file { margin-bottom: 24px; }
    .file-header {
        background: #313244; padding: 8px 16px; border-radius: 6px 6px 0 0;
        color: #a6e3a1; font-weight: bold; font-size: 14px;
        display: flex; justify-content: space-between;
    }
    .file-header span { color: #6c7086; font-weight: normal; }
    pre {
        background: #181825; padding: 16px; border-radius: 0 0 6px 6px;
        overflow-x: auto; font-size: 13px; line-height: 1.5;
        border: 1px solid #313244; border-top: none; max-height: 500px; overflow-y: auto;
    }
    .actions { margin-bottom: 20px; display: flex; gap: 12px; }
    .actions a {
        display: inline-block; padding: 8px 20px; background: #a6e3a1; color: #1e1e2e;
        text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px;
    }
    .actions a:hover { background: #94e2d5; }
    .actions a.back { background: #45475a; color: #cdd6f4; }
    .assets-info { background: #313244; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; }
    .assets-info strong { color: #f9e2af; }
</style></head><body>

<h1>CDD Config Export</h1>
<div class="info"><?= count($files) ?> файлов<?= !empty($assets) ? ', ' . count($assets) . ' assets' : '' ?></div>
<div class="actions">
    <a href="?<?= htmlspecialchars($downloadParams) ?>">Скачать ZIP</a>
    <a href="?<?= htmlspecialchars($authParam ? 'key=' . urlencode($_GET['key'] ?? '') : '') ?>" class="back">Назад</a>
</div>

<?php if (!empty($assets)): ?>
<div class="assets-info">
    <strong>Assets (<?= count($assets) ?>):</strong>
    <?php foreach (array_keys($assets) as $i => $path): ?>
        <?php if ($i >= 10): ?><br>... и ещё <?= count($assets) - 10 ?><?php break; endif; ?>
        <br><span style="color:#6c7086"><?= htmlspecialchars($path) ?></span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php foreach ($files as $path => $content):
    $lines = substr_count($content, "\n") + 1;
?>
<div class="file">
    <div class="file-header"><?= htmlspecialchars($path) ?> <span><?= $lines ?> строк</span></div>
    <pre><?= htmlspecialchars($content) ?></pre>
</div>
<?php endforeach; ?>

</body></html>
    <?php
}
