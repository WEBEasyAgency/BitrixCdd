<?php

namespace BitrixCdd\Tools\Export;

/**
 * Сбор файлов из upload/ для включения в архив
 */
class AssetCollector
{
    /** [archivePath => absolutePath] */
    private array $files = [];

    /**
     * ��обрать файл и вернуть путь для конфига
     * @return string|null Путь вида /local/config/assets/{iblock}/{file}
     */
    public function collect(int $fileId, string $iblockCode): ?string
    {
        if ($fileId <= 0) return null;

        $fileArray = \CFile::GetFileArray($fileId);
        if (!$fileArray || empty($fileArray['SRC'])) return null;

        $absolutePath = $_SERVER['DOCUMENT_ROOT'] . $fileArray['SRC'];
        if (!file_exists($absolutePath)) return null;

        $originalName = $fileArray['ORIGINAL_NAME'] ?? $fileArray['FILE_NAME'] ?? basename($fileArray['SRC']);
        $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $assetName = $fileId . '_' . $baseName . '.' . $ext;

        $archivePath = "assets/{$iblockCode}/{$assetName}";
        $this->files[$archivePath] = $absolutePath;

        return "/local/config/assets/{$iblockCode}/{$assetName}";
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function reset(): void
    {
        $this->files = [];
    }
}
