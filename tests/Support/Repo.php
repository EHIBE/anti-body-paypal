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
        return self::filesIn('.github/workflows', ['yml', 'yaml']);
    }

    /**
     * @return list<string>
     */
    public static function hookFiles(): array
    {
        return self::filesIn('.husky', []);
    }

    /**
     * @return array<string, string>
     */
    public static function forbiddenHookPatterns(): array
    {
        return [
            'no-verify flag' => '/--no-verify/',
            'HUSKY=0 switch' => '/HUSKY=0/',
            'git add -A' => '/git add\s+-A\b/',
            'git add --all' => '/git add\s+--all\b/',
            'git add dot' => '/git add\s+\.(\s|$)/',
            'eval' => '/\beval\b/',
            'curl' => '/\bcurl\b/',
            'wget' => '/\bwget\b/',
        ];
    }

    /**
     * @param  list<string>  $extensions
     * @return list<string>
     */
    private static function filesIn(string $directory, array $extensions): array
    {
        $entries = scandir(self::path($directory));
        $relative = [];

        foreach ($entries === false ? [] : $entries as $entry) {
            $path = self::path($directory.'/'.$entry);
            $extension = pathinfo($entry, PATHINFO_EXTENSION);

            if (! is_file($path) || str_starts_with($entry, '_')) {
                continue;
            }

            if ($extensions === [] || in_array($extension, $extensions, true)) {
                $relative[] = $directory.'/'.$entry;
            }
        }

        sort($relative);

        return $relative;
    }
}
