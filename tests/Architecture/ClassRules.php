<?php

declare(strict_types=1);

namespace Tests\Architecture;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionEnum;
use ReflectionMethod;
use ReflectionNamedType;
use SplFileInfo;

final class ClassRules
{
    private const array EMPTY_FOLDERS = ['Services', 'Repositories', 'Actions', 'Jobs', 'Policies'];

    private const array FOLDER_EXCEPTIONS = [
        'Http/Controllers' => ['Controller.php'],
        'Models' => ['User.php'],
    ];

    /**
     * @param  list<class-string>  $classes
     * @return list<string>
     */
    public static function actions(array $classes): array
    {
        $violations = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);

            if (! self::isConcreteClass($reflection)) {
                $violations[] = "{$class} is not a concrete class and does not belong in Actions";

                continue;
            }

            if (! str_ends_with($class, 'Action')) {
                $violations[] = "{$class} must end in Action";
            }

            if (! $reflection->isFinal()) {
                $violations[] = "{$class} must be final";
            }

            if (self::publicMethodNames($reflection) !== ['handle']) {
                $violations[] = "{$class} must have exactly one public method, handle";
            }
        }

        return $violations;
    }

    /**
     * @param  list<class-string>  $classes
     * @return list<string>
     */
    public static function dataObjects(array $classes): array
    {
        $violations = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);

            if (! self::isConcreteClass($reflection)) {
                $violations[] = "{$class} is not a concrete class and does not belong in Data";

                continue;
            }

            if (! $reflection->isFinal() || ! $reflection->isReadOnly()) {
                $violations[] = "{$class} must be a final readonly class";
            }
        }

        return $violations;
    }

    /**
     * @param  list<class-string>  $classes
     * @return list<string>
     */
    public static function enums(array $classes): array
    {
        $violations = [];

        foreach ($classes as $class) {
            if (! enum_exists($class)) {
                $violations[] = "{$class} is not an enum and does not belong in Enums";

                continue;
            }

            $backingType = new ReflectionEnum($class)->getBackingType();

            if (! $backingType instanceof ReflectionNamedType || $backingType->getName() !== 'string') {
                $violations[] = "{$class} must be a string-backed enum";
            }
        }

        return $violations;
    }

    /**
     * @param  list<class-string>  $classes
     * @return list<string>
     */
    public static function finalClasses(array $classes): array
    {
        $violations = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);

            if (! self::isConcreteClass($reflection) || $reflection->isAbstract() || $reflection->isFinal()) {
                continue;
            }

            $violations[] = "{$class} must be final";
        }

        return $violations;
    }

    /**
     * @param  list<string>  $files
     * @return list<string>
     */
    public static function unresolvedFiles(array $files): array
    {
        return array_map(
            static fn (string $file): string => "{$file} does not declare the class its path implies",
            $files,
        );
    }

    /**
     * @return list<string>
     */
    public static function horizontalFolders(string $appPath): array
    {
        $violations = [];

        foreach (self::EMPTY_FOLDERS as $folder) {
            $files = self::phpFilesIn($appPath.'/'.$folder);

            if ($files !== []) {
                $violations[] = "app/{$folder} must not hold code: ".implode(', ', $files);
            }
        }

        foreach (self::FOLDER_EXCEPTIONS as $folder => $allowed) {
            $files = array_values(array_diff(self::phpFilesIn($appPath.'/'.$folder), $allowed));

            if ($files !== []) {
                $violations[] = "app/{$folder} may only hold ".implode(', ', $allowed).': '.implode(', ', $files);
            }
        }

        return $violations;
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     */
    private static function isConcreteClass(ReflectionClass $reflection): bool
    {
        return ! $reflection->isInterface() && ! $reflection->isEnum() && ! $reflection->isTrait();
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @return list<string>
     */
    private static function publicMethodNames(ReflectionClass $reflection): array
    {
        $names = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $names[] = $method->getName();
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private static function phpFilesIn(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($path) + 1));
            }
        }

        sort($files);

        return $files;
    }
}
