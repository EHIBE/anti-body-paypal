<?php

declare(strict_types=1);

namespace Tests\AccessMatrix;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthManager;

final class AccessMatrix
{
    public const string OWNER = 'owner';

    public const string AGENT = 'agent';

    public const string GUEST = 'guest';

    public const array ACTORS = [self::OWNER, self::AGENT, self::GUEST];

    public const array ACTIVE_ACTORS = [self::OWNER, self::GUEST];

    public const string AGENT_FORBIDDEN_ABILITIES = '/approv|reject|overrid|payee|amount|vendor\.(create|update|delete|edit)/i';

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
        return [
            'unnamed: GET /',
            'unnamed: GET up',
            'boost.browser-logs',
            'storage.local',
            'storage.local.upload',
        ];
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
     * @param  Closure(string): ?User  $actorFor
     * @return list<string>
     */
    public static function mismatches(array $rows, Closure $actorFor): array
    {
        $mismatches = [];

        foreach ($rows as $row) {
            foreach (self::ACTIVE_ACTORS as $actor) {
                resolve(AuthManager::class)->forgetGuards();

                $expected = $row->allows($actor);
                $actual = ($row->probe)($actorFor($actor));

                if ($expected !== $actual) {
                    $mismatches[] = "{$row->kind}:{$row->key} for {$actor}: expected ".($expected ? 'allow' : 'deny').', got '.($actual ? 'allow' : 'deny');
                }
            }
        }

        return $mismatches;
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
