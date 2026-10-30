<?php

declare(strict_types=1);

namespace Tests\AccessMatrix;

final class AccessMatrix
{
    public const string OWNER = 'owner';

    public const string AGENT = 'agent';

    public const string GUEST = 'guest';

    public const array ACTORS = [self::OWNER, self::AGENT, self::GUEST];

    public const array ACTIVE_ACTORS = [self::OWNER, self::GUEST];

    public const string AGENT_FORBIDDEN_ABILITIES = '/approve|reject|override|vendor|amount|payee/i';

    /**
     * @return list<MatrixRow>
     */
    public static function rows(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public static function openRoutes(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public static function abilities(): array
    {
        return self::keys(MatrixRow::ABILITY);
    }

    /**
     * @return list<string>
     */
    public static function routes(): array
    {
        return self::keys(MatrixRow::ROUTE);
    }

    /**
     * @param  list<MatrixRow>  $rows
     * @return list<string>
     */
    public static function forbiddenAgentGrants(array $rows): array
    {
        $grants = [];

        foreach ($rows as $row) {
            if ($row->allows(self::AGENT) && preg_match(self::AGENT_FORBIDDEN_ABILITIES, $row->key) === 1) {
                $grants[] = $row->key;
            }
        }

        return $grants;
    }

    /**
     * @param  list<MatrixRow>  $rows
     * @return list<string>
     */
    public static function unknownActors(array $rows): array
    {
        $allowed = array_merge([], ...array_map(static fn (MatrixRow $row): array => $row->allowed, $rows));

        return array_values(array_unique(array_diff($allowed, self::ACTORS)));
    }

    /**
     * @return list<string>
     */
    private static function keys(string $kind): array
    {
        $keys = [];

        foreach (self::rows() as $row) {
            if ($row->kind === $kind) {
                $keys[] = $row->key;
            }
        }

        return $keys;
    }
}
