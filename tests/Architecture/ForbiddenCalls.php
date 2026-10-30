<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PhpToken;
use Symfony\Component\Finder\Finder;

final class ForbiddenCalls
{
    public const array DEBUG_FUNCTIONS = ['dd', 'ddd', 'dump', 'var_dump', 'ray'];

    public const array SHELL_FUNCTIONS = ['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen'];

    public const array UNSAFE_FUNCTIONS = ['env', 'getenv', 'unserialize'];

    public const array RAW_QUERY_METHODS = ['whereRaw', 'orWhereRaw', 'selectRaw', 'orderByRaw', 'havingRaw', 'orHavingRaw', 'groupByRaw', 'fromRaw'];

    public const array RAW_DB_STATIC_CALLS = ['raw', 'statement', 'unprepared'];

    public const array UNGUARD_STATIC_CALLS = ['unguard', 'unguarded'];

    private const array IGNORED_PATHS = ['#(^|/)Tests/#'];

    /**
     * @return array<string, string>
     */
    public static function rawSqlAllowlist(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public static function in(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $offences = [];

        foreach (Finder::create()->files()->in($directory)->name('*.php') as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());

            if (self::isIgnored($relative)) {
                continue;
            }

            foreach (self::inSource($file->getContents()) as $offence) {
                $offences[] = $relative.' '.$offence;
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
        $tokens = array_values(array_filter(
            PhpToken::tokenize($source),
            static fn (PhpToken $token): bool => ! $token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
        ));

        $offences = [];
        $insideBackticks = false;

        foreach ($tokens as $index => $token) {
            if ($token->text === '`') {
                $insideBackticks = ! $insideBackticks;

                if ($insideBackticks) {
                    $offences[] = 'line '.$token->line.' uses the backtick shell operator';
                }

                continue;
            }

            $offence = self::offenceAt($tokens, $index, $token);

            if ($offence !== null) {
                $offences[] = 'line '.$token->line.' '.$offence;
            }
        }

        return $offences;
    }

    /**
     * @param  list<PhpToken>  $tokens
     */
    private static function offenceAt(array $tokens, int $index, PhpToken $token): ?string
    {
        if ($token->is(T_EVAL)) {
            return 'uses eval';
        }

        if (! $token->is([T_STRING, T_NAME_FULLY_QUALIFIED]) || ($tokens[$index + 1]->text ?? '') !== '(') {
            return null;
        }

        $name = strtolower(ltrim($token->text, '\\'));
        $previous = $tokens[$index - 1] ?? null;
        $receiver = $tokens[$index - 2] ?? null;

        if ($previous !== null && $previous->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])) {
            return self::methodOffence($name, $token->text, $tokens, $index);
        }

        if ($previous !== null && $previous->is(T_DOUBLE_COLON)) {
            return self::staticOffence($name, $receiver->text ?? '');
        }

        if ($previous !== null && $previous->is([T_FUNCTION, T_NEW, T_CONST])) {
            return null;
        }

        return self::functionOffence($name);
    }

    private static function functionOffence(string $name): ?string
    {
        foreach (['debug helper' => self::DEBUG_FUNCTIONS, 'shell function' => self::SHELL_FUNCTIONS, 'unsafe function' => self::UNSAFE_FUNCTIONS] as $kind => $names) {
            if (in_array($name, $names, true)) {
                return "calls the {$kind} {$name}()";
            }
        }

        return null;
    }

    /**
     * @param  list<PhpToken>  $tokens
     */
    private static function methodOffence(string $name, string $original, array $tokens, int $index): ?string
    {
        if (in_array($name, self::DEBUG_FUNCTIONS, true)) {
            return "calls the debug method {$original}()";
        }

        if (in_array($name, self::rawQueryMethodNames(), true) && ! array_key_exists($name, array_change_key_case(self::rawSqlAllowlist()))) {
            return "calls the raw query method {$original}()";
        }

        if (in_array($name, self::RAW_DB_STATIC_CALLS, true) && self::chainReachesConnection($tokens, $index)) {
            return "calls the raw query method ->{$original}() on a database connection";
        }

        return in_array($name, self::UNGUARD_STATIC_CALLS, true) ? "calls {$name}() to disable mass assignment protection" : null;
    }

    private static function staticOffence(string $name, string $receiver): ?string
    {
        $class = self::className($receiver);

        if (in_array($name, self::UNGUARD_STATIC_CALLS, true)) {
            return "calls {$receiver}::{$name}() to disable mass assignment protection";
        }

        if ($class === 'db' && in_array($name, self::RAW_DB_STATIC_CALLS, true) && ! array_key_exists('DB::'.$name, self::rawSqlAllowlist())) {
            return "calls the raw query method DB::{$name}()";
        }

        if (in_array($name, self::rawQueryMethodNames(), true)) {
            return "calls the raw query method {$receiver}::{$name}()";
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function rawQueryMethodNames(): array
    {
        return array_map(strtolower(...), self::RAW_QUERY_METHODS);
    }

    private static function className(string $name): string
    {
        return strtolower(ltrim(substr($name, (int) strrpos('\\'.$name, '\\')), '\\'));
    }

    /**
     * @param  list<PhpToken>  $tokens
     */
    private static function chainReachesConnection(array $tokens, int $index): bool
    {
        $depth = 0;

        for ($position = $index - 1; $position >= 0; $position--) {
            $token = $tokens[$position];

            if ($token->text === ')') {
                $depth++;

                continue;
            }

            if ($token->text === '(') {
                if ($depth === 0) {
                    return false;
                }

                $depth--;

                continue;
            }

            if ($depth === 0 && (in_array($token->text, [';', '{', '}', ',', '=', '=>'], true) || $token->is([T_RETURN, T_ECHO]))) {
                return false;
            }

            $next = $tokens[$position + 1] ?? null;

            if (strtolower($token->text) === 'connection' && $next?->text === '(') {
                return true;
            }

            if ($token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) && $next?->is(T_DOUBLE_COLON) === true && self::className($token->text) === 'db') {
                return true;
            }
        }

        return false;
    }

    private static function isIgnored(string $relative): bool
    {
        return array_any(self::IGNORED_PATHS, fn (string $pattern): bool => preg_match($pattern, $relative) === 1);
    }
}
