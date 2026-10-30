<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class Repo
{
    public static function root(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 2));
    }

    public static function path(string $relative): string
    {
        return self::root().'/'.ltrim($relative, '/');
    }

    public static function exists(string $relative): bool
    {
        return file_exists(self::path($relative));
    }

    public static function raw(string $relative): string
    {
        $contents = file_get_contents(self::path($relative));

        if ($contents === false) {
            throw new RuntimeException("Cannot read {$relative}.");
        }

        return $contents;
    }

    public static function read(string $relative): string
    {
        return str_replace("\r\n", "\n", self::raw($relative));
    }

    /**
     * @return list<string>
     */
    public static function lines(string $relative): array
    {
        return explode("\n", rtrim(self::read($relative), "\n"));
    }

    /**
     * @return array<string, mixed>
     */
    public static function yaml(string $relative): array
    {
        return Tree::map(Yaml::parse(self::read($relative)));
    }

    /**
     * @return array<string, mixed>
     */
    public static function json(string $relative): array
    {
        return Tree::map(json_decode(self::raw($relative), true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<string>
     */
    public static function workflowFiles(): array
    {
        $files = glob(self::root().'/.github/workflows/*.{yml,yaml}', GLOB_BRACE);

        if ($files === false) {
            return [];
        }

        $relative = array_map(
            static fn (string $file): string => '.github/workflows/'.basename($file),
            $files,
        );

        sort($relative);

        return $relative;
    }

    /**
     * @return list<string>
     */
    public static function hookFiles(): array
    {
        return ['.husky/commit-msg', '.husky/pre-commit', '.husky/pre-push'];
    }
}
