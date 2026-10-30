<?php

declare(strict_types=1);

namespace Tests\Architecture;

use FilesystemIterator;
use PhpToken;
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
     * @return list<class-string>
     */
    public static function everyEnum(?string $root = null, string $namespace = self::APP_NAMESPACE): array
    {
        return array_values(array_filter(
            self::everySliceClass($root, $namespace),
            enum_exists(...),
        ));
    }

    /**
     * @return list<string>
     */
    public static function unresolvedFiles(?string $root = null, string $namespace = self::APP_NAMESPACE): array
    {
        $root ??= self::appRoot();
        $unresolved = [];

        foreach (self::candidateFiles($root) as $relative) {
            if (self::resolve($root, $namespace, $relative) === null) {
                $unresolved[] = $relative;
            }
        }

        sort($unresolved);

        return $unresolved;
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
        $classes = [];

        foreach (self::candidateFiles($root) as $relative) {
            $class = preg_match($pattern, $relative) === 1 ? self::resolve($root, $namespace, $relative) : null;

            if ($class !== null) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return list<string>
     */
    private static function candidateFiles(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            if (str_contains($relative, '/') && self::isClassFile($relative)) {
                $files[] = $relative;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return class-string|null
     */
    private static function resolve(string $root, string $namespace, string $relative): ?string
    {
        $class = $namespace.'\\'.str_replace('/', '\\', substr($relative, 0, -4));

        if (! self::declares((string) file_get_contents($root.'/'.$relative), $class)) {
            return null;
        }

        if (class_exists($class) || interface_exists($class) || enum_exists($class) || trait_exists($class)) {
            return $class;
        }

        return null;
    }

    private static function declares(string $source, string $class): bool
    {
        $tokens = array_values(array_filter(
            PhpToken::tokenize($source),
            static fn (PhpToken $token): bool => ! $token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
        ));
        $namespace = '';

        foreach ($tokens as $index => $token) {
            if ($token->is(T_NAMESPACE)) {
                $namespace = isset($tokens[$index + 1]) ? $tokens[$index + 1]->text : '';

                continue;
            }

            $previous = $tokens[$index - 1] ?? null;
            $name = $tokens[$index + 1] ?? null;

            if ($token->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM]) && $previous?->is(T_DOUBLE_COLON) !== true && $name?->is(T_STRING) === true) {
                $declared = ltrim($namespace.'\\'.$name->text, '\\');

                if (strcasecmp($declared, $class) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function isClassFile(string $relative): bool
    {
        return ! str_contains('/'.$relative, '/Tests/')
            && preg_match('/^[A-Z][A-Za-z0-9]*\.php$/', basename($relative)) === 1;
    }
}
