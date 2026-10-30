<?php

declare(strict_types=1);

namespace Tests\Support;

final class Tree
{
    /**
     * @return array<string, mixed>
     */
    public static function map(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $item) {
            $map[(string) $key] = $item;
        }

        return $map;
    }

    /**
     * @return list<mixed>
     */
    public static function items(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    public static function at(mixed $value, string $path): mixed
    {
        $current = $value;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    public static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        return array_values(array_filter(self::items($value), is_string(...)));
    }
}
