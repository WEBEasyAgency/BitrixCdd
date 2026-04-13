<?php

namespace BitrixCdd\Tools;

use Bitrix\Main\Loader;
use BitrixCdd\Tools\Export\AssetCollector;
use BitrixCdd\Tools\Export\DemoDataReader;
use BitrixCdd\Tools\Export\IblockReader;
use BitrixCdd\Tools\Export\IblockTypeReader;
use BitrixCdd\Tools\Export\PhpConfigRenderer;

/**
 * Экспорт существующих инфоблоков в CDD-конфиги
 *
 * Оркестратор: делегирует чтение и рендеринг специализированным классам.
 */
class ConfigExporter
{
    private array $typeFilter;
    private array $demoDataIblocks;

    private IblockTypeReader $typeReader;
    private IblockReader $iblockReader;
    private DemoDataReader $demoReader;
    private AssetCollector $assets;
    private PhpConfigRenderer $renderer;

    public function __construct(array $typeFilter = [], array $demoDataIblocks = [])
    {
        Loader::includeModule('iblock');

        $this->typeFilter = $typeFilter;
        $this->demoDataIblocks = $demoDataIblocks;

        $this->typeReader = new IblockTypeReader();
        $this->iblockReader = new IblockReader();
        $this->assets = new AssetCollector();
        $this->demoReader = new DemoDataReader($this->assets);
        $this->renderer = new PhpConfigRenderer();
    }

    /**
     * @return array [path => content, ...]
     */
    public function export(): array
    {
        $this->assets->reset();
        $files = [];

        $files['global.php'] = $this->renderer->render($this->globalConfig());

        foreach ($this->typeReader->read($this->typeFilter) as $typeId => $config) {
            $files["iblock_types/{$typeId}.php"] = $this->renderer->render($config);
        }

        $iblocks = $this->iblockReader->read($this->typeFilter);

        foreach ($iblocks as $code => $config) {
            $files["iblocks/{$code}.php"] = $this->renderer->render($config);
        }

        foreach ($this->demoDataIblocks as $iblockCode) {
            if (!isset($iblocks[$iblockCode])) continue;

            $iblockId = $this->iblockReader->getIdByCode($iblockCode);
            if (!$iblockId) continue;

            $properties = $this->iblockReader->getPropertyCache($iblockId);
            $demoData = $this->demoReader->read($iblockId, $iblockCode, $properties);

            if (!empty($demoData)) {
                $files["demo_data/{$iblockCode}.php"] = $this->renderer->render($demoData);
            }
        }

        return $files;
    }

    /**
     * Экспорт в ZIP-архив
     */
    public function exportZip(): string
    {
        $files = $this->export();

        $tmpFile = tempnam(sys_get_temp_dir(), 'cdd_export_');
        $zip = new \ZipArchive();
        $zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($files as $path => $content) {
            $zip->addFromString("config/{$path}", $content);
        }

        foreach ($this->assets->getFiles() as $archivePath => $absolutePath) {
            if (file_exists($absolutePath)) {
                $zip->addFile($absolutePath, "config/{$archivePath}");
            }
        }

        $zip->close();
        $data = file_get_contents($tmpFile);
        unlink($tmpFile);

        return $data;
    }

    /**
     * Список инфоблоков для UI
     */
    public function getIblockList(): array
    {
        return $this->iblockReader->getList($this->typeFilter);
    }

    /**
     * Собранные asset-файлы (после export())
     */
    public function getAssetFiles(): array
    {
        return $this->assets->getFiles();
    }

    private function globalConfig(): array
    {
        return [
            'sync_mode' => 'off',
            'default_iblock_type' => 'content',
            'iblock_fields' => [
                'CODE' => [
                    'IS_REQUIRED' => 'N',
                    'DEFAULT_VALUE' => [
                        'TRANSLITERATION' => 'Y',
                        'TRANS_LEN' => 100,
                        'TRANS_CASE' => 'L',
                        'TRANS_SPACE' => '-',
                        'TRANS_OTHER' => '-',
                        'TRANS_EAT' => 'Y',
                        'USE_GOOGLE' => 'N',
                    ],
                ],
            ],
        ];
    }
}
