<?php

/**
 * Простой автозагрузчик классов
 */
namespace BitrixCdd\Core;

class Autoloader
{
    private array $prefixes = [];

    /**
     * Добавление пространства имен
     */
    public function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . '/';
        $this->prefixes[$prefix] = $baseDir;
    }

    /**
     * Регистрация автозагрузчика
     */
    public function register(): void
    {
        spl_autoload_register([$this, 'loadClass']);
    }

    /**
     * Загрузка класса
     */
    private function loadClass(string $class): bool
    {
        $prefix = $class;
        
        while (($pos = strrpos($prefix, '\\')) !== false) {
            $prefix = substr($class, 0, $pos + 1);
            $relativeClass = substr($class, $pos + 1);
            
            $mappedFile = $this->loadMappedFile($prefix, $relativeClass);
            if ($mappedFile) {
                return $mappedFile;
            }
            
            $prefix = rtrim($prefix, '\\');
        }
        
        return false;
    }

    /**
     * Загрузка файла класса по маппингу
     */
    private function loadMappedFile(string $prefix, string $relativeClass): bool
    {
        if (isset($this->prefixes[$prefix])) {
            $baseDir = $this->prefixes[$prefix];
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            
            if (file_exists($file)) {
                require $file;
                return true;
            }
        }
        
        return false;
    }
}
