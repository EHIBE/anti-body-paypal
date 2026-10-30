<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Finder\Finder;

/**
 * @phpstan-type Rule array{id: string, regex: string, secretGroup: int, allowed: list<string>}
 * @phpstan-type Config array{rules: list<Rule>, paths: list<string>}
 */
final class SecretScan
{
    public const array SCANNED_DIRECTORIES = ['app', 'bootstrap', 'config', 'database', 'routes', 'resources', 'tests', 'docs', '.github'];

    public const array SCANNED_FILES = ['.env.example'];

    private const int MAX_BYTES = 2_000_000;

    /**
     * @return Config
     */
    public static function projectConfig(): array
    {
        return self::parse(Repo::read('.gitleaks.toml'));
    }

    /**
     * @return Rule
     */
    public static function rule(string $id): array
    {
        foreach (self::projectConfig()['rules'] as $rule) {
            if ($rule['id'] === $id) {
                return $rule;
            }
        }

        throw new RuntimeException("No gitleaks rule {$id}.");
    }

    /**
     * @return Config
     */
    public static function parse(string $toml): array
    {
        $ids = [];
        $regexes = [];
        $groups = [];
        $allowed = [];
        $paths = [];
        $section = 'root';

        foreach (explode("\n", str_replace("\r\n", "\n", $toml)) as $raw) {
            $line = trim($raw);

            if ($line === '[[rules]]') {
                $ids[] = '';
                $regexes[] = '';
                $groups[] = 0;
                $allowed[] = [];
                $section = 'rule';

                continue;
            }

            if (str_starts_with($line, '[')) {
                $section = match ($line) {
                    '[[rules.allowlists]]' => 'ruleAllow',
                    '[[allowlists]]' => 'globalAllow',
                    default => 'other',
                };

                continue;
            }

            $last = count($ids) - 1;

            if ($section === 'rule' && preg_match('/^id\s*=\s*"([^"]*)"$/', $line, $match) === 1) {
                $ids[$last] = $match[1];
            } elseif ($section === 'rule' && preg_match("/^regex\\s*=\\s*'''(.*)'''\$/", $line, $match) === 1) {
                $regexes[$last] = $match[1];
            } elseif ($section === 'rule' && preg_match('/^secretGroup\s*=\s*(\d+)$/', $line, $match) === 1) {
                $groups[$last] = (int) $match[1];
            } elseif ($section === 'ruleAllow' && str_starts_with($line, 'regexes')) {
                $allowed[$last] = [...$allowed[$last], ...self::quoted($line)];
            } elseif ($section === 'globalAllow' && str_starts_with($line, 'paths')) {
                $paths = [...$paths, ...self::quoted($line)];
            }
        }

        $rules = [];

        foreach ($ids as $index => $id) {
            $rules[] = ['id' => $id, 'regex' => $regexes[$index], 'secretGroup' => $groups[$index], 'allowed' => $allowed[$index]];
        }

        return ['rules' => $rules, 'paths' => $paths];
    }

    /**
     * @param  Config  $config
     * @return list<string>
     */
    public static function scanText(string $path, string $text, array $config): array
    {
        $findings = [];

        foreach (explode("\n", str_replace("\r\n", "\n", $text)) as $index => $line) {
            foreach ($config['rules'] as $rule) {
                if (self::lineHasSecret($line, $rule)) {
                    $number = $index + 1;
                    $findings[] = "{$path}:{$number} {$rule['id']}";
                }
            }
        }

        return $findings;
    }

    /**
     * @param  Rule  $rule
     */
    public static function lineHasSecret(string $line, array $rule): bool
    {
        $count = preg_match_all(self::delimit($rule['regex']), $line, $matches, PREG_SET_ORDER);

        if ($count === false) {
            throw new RuntimeException("Rule {$rule['id']} has an invalid regex.");
        }

        foreach ($matches as $match) {
            $secret = $match[$rule['secretGroup']] ?? $match[0];

            if (! self::isAllowedSecret($secret, $rule['allowed'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $allowedPatterns
     */
    public static function isAllowedSecret(string $secret, array $allowedPatterns): bool
    {
        return array_any(
            $allowedPatterns,
            static fn (string $pattern): bool => preg_match(self::delimit($pattern), $secret) === 1,
        );
    }

    /**
     * @param  list<string>  $pathPatterns
     */
    public static function isAllowedPath(string $relativePath, array $pathPatterns): bool
    {
        return array_any(
            $pathPatterns,
            static fn (string $pattern): bool => preg_match(self::delimit($pattern), $relativePath) === 1,
        );
    }

    /**
     * @param  Config  $config
     * @return list<string>
     */
    public static function scanRepository(string $root, array $config): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $findings = [];

        foreach (self::files($root) as $relative) {
            if (self::isAllowedPath($relative, $config['paths'])) {
                continue;
            }

            $text = file_get_contents($root.'/'.$relative);

            if ($text === false || str_contains($text, "\0")) {
                continue;
            }

            $findings = [...$findings, ...self::scanText($relative, $text, $config)];
        }

        sort($findings);

        return $findings;
    }

    /**
     * @return list<string>
     */
    private static function files(string $root): array
    {
        $directories = array_values(array_filter(
            array_map(static fn (string $directory): string => $root.'/'.$directory, self::SCANNED_DIRECTORIES),
            is_dir(...),
        ));

        $relative = [];

        if ($directories !== []) {
            foreach (Finder::create()->files()->in($directories)->ignoreDotFiles(false)->ignoreVCS(true)->size('< '.self::MAX_BYTES) as $file) {
                $relative[] = ltrim(substr(str_replace('\\', '/', $file->getPathname()), strlen($root)), '/');
            }
        }

        foreach (self::SCANNED_FILES as $file) {
            if (is_file($root.'/'.$file)) {
                $relative[] = $file;
            }
        }

        sort($relative);

        return $relative;
    }

    /**
     * @return list<string>
     */
    private static function quoted(string $line): array
    {
        preg_match_all("/'''(.*?)'''/", $line, $matches);

        return $matches[1];
    }

    private static function delimit(string $regex): string
    {
        return '~'.str_replace('~', '\~', $regex).'~';
    }
}
