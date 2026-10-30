<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Symfony\Component\Finder\Finder;

final class RawSqlAliases
{
    public const array POSTGRES_RESERVED_WORDS = [
        'all', 'analyse', 'analyze', 'and', 'any', 'array', 'as', 'asc', 'asymmetric', 'authorization', 'binary',
        'both', 'case', 'cast', 'check', 'collate', 'collation', 'column', 'concurrently', 'constraint', 'create',
        'cross', 'current_catalog', 'current_date', 'current_role', 'current_schema', 'current_time',
        'current_timestamp', 'current_user', 'default', 'deferrable', 'desc', 'distinct', 'do', 'else', 'end',
        'except', 'false', 'fetch', 'for', 'foreign', 'freeze', 'from', 'full', 'grant', 'group', 'having', 'ilike',
        'in', 'initially', 'inner', 'intersect', 'into', 'is', 'isnull', 'join', 'lateral', 'leading', 'left',
        'like', 'limit', 'localtime', 'localtimestamp', 'natural', 'not', 'notnull', 'null', 'offset', 'on', 'only',
        'or', 'order', 'outer', 'overlaps', 'placing', 'primary', 'references', 'returning', 'right', 'select',
        'session_user', 'similar', 'some', 'symmetric', 'system_user', 'table', 'tablesample', 'then', 'to',
        'trailing', 'true', 'union', 'unique', 'user', 'using', 'variadic', 'verbose', 'when', 'where', 'window',
        'with',
    ];

    private const string SQL_HINT = '/\b(count|sum|min|max|avg)\s*\(|\bselect\s|\bcase\s+when\b|\bfrom\s/i';

    private const string ALIAS = '/\bas\s+["`]?([A-Za-z_][A-Za-z0-9_]*)["`]?/i';

    /**
     * @param  list<string>  $directories
     * @return list<string>
     */
    public static function in(array $directories): array
    {
        $existing = array_values(array_filter($directories, is_dir(...)));

        if ($existing === []) {
            return [];
        }

        $files = Finder::create()
            ->files()
            ->in($existing)
            ->name('*.php')
            ->notPath('#(^|/)Tests(/|$)#')
            ->notPath('#(^|/)factories(/|$)#');

        $offences = [];

        foreach ($files as $file) {
            foreach (self::inSource($file->getContents()) as $offence) {
                $offences[] = str_replace('\\', '/', $file->getRelativePathname()).' '.$offence;
            }
        }

        sort($offences);

        return $offences;
    }

    /**
     * @return list<string>
     */
    public static function inSource(string $source): array
    {
        $found = [];

        foreach (explode("\n", $source) as $number => $line) {
            if (preg_match_all('/([\'"])((?:(?!\1).)*)\1/', $line, $literals, PREG_SET_ORDER) < 1) {
                continue;
            }

            foreach ($literals as $literal) {
                foreach (self::reservedAliasesIn($literal[2]) as $alias) {
                    $found[] = 'line '.($number + 1).' aliases a column to the Postgres reserved word "'.$alias.'"';
                }
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private static function reservedAliasesIn(string $literal): array
    {
        if (preg_match(self::SQL_HINT, $literal) !== 1) {
            return [];
        }

        preg_match_all(self::ALIAS, $literal, $aliases);

        return array_values(array_filter(
            $aliases[1],
            static fn (string $alias): bool => in_array(strtolower($alias), self::POSTGRES_RESERVED_WORDS, true),
        ));
    }
}
