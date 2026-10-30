<?php

declare(strict_types=1);

namespace Tests\Architecture;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class SliceClasses
{
    public const string APP_NAMESPACE = 'App\\Features';

    public static function appRoot(): string
    {
        return dirname(__DIR__, 2).'/app/Features';
    }

    /**
     * @return list<class-string>
     */
    public static function actions(?string $root = null, string $namespace = self::APP_NAMESPACE): array
    {
        return self::in('Actions', $root, $namespace);
    }

    /**
     * @return list<class-string>
     */
    public static function dataObjects(?string $root = null, string $namespace = self::APP_NAMESPACE): array
    {
        return self::in('Data', $root, $namespace);
    }

    /**
     * @return list<class-string>
     */
    public static function enums(?string $root = null, string $namespace = self::APP_NAMESPACE): array
    {
        return self::in('Enums', $root, $namespace);
    }

    /**
     * @return list<class-string>
     */
    public static function everySliceClass(?string $root = null, string $namespace = self::APP_NAMESPACE): array
    {
        return self::scan($root ?? self::appRoot(), $namespace, '#^[^/]+/#');
    }

    /**
     * @return list<string>
     */
    public static function sliceFolders(?string $root = null): array
    {
        $folders = glob(($root ?? self::appRoot()).'/*', GLOB_ONLYDIR);

        if ($folders === false) {
            return [];
        }

        return array_map(basename(...), $folders);
    }

    /**
     * @return list<class-string>
     */
    public static function in(string $folder, ?string $root = null, string $namespace = self::APP_NAMESPACE): array
    {
        return self::scan($root ?? self::appRoot(), $namespace, '#^[^/]+/'.preg_quote($folder, '#').'/#');
    }

    /**
     * @return list<class-string>
     */
    private static function scan(string $root, string $namespace, string $pattern): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $classes = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            if (preg_match($pattern, $relative) !== 1 || ! self::isClassFile($relative)) {
                continue;
            }

            $class = $namespace.'\\'.str_replace('/', '\\', substr($relative, 0, -4));

            if (class_exists($class) || interface_exists($class) || enum_exists($class) || trait_exists($class)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    private static function isClassFile(string $relative): bool
    {
        return ! str_contains('/'.$relative, '/Tests/')
            && preg_match('/^[A-Z][A-Za-z0-9]*\.php$/', basename($relative)) === 1;
    }
}
