<?php

declare(strict_types=1);

namespace Tests\Support;

use Symfony\Component\Yaml\Yaml;

final class WorkflowAudit
{
    public const string CANCEL_EXPRESSION = '${{ github.ref != \'refs/heads/main\' }}';

    /**
     * @return list<string>
     */
    public static function violations(string $yaml): array
    {
        return [
            ...self::pinning($yaml),
            ...self::comments($yaml),
            ...self::triggers($yaml),
            ...self::permissions($yaml),
            ...self::concurrency($yaml),
            ...self::timeouts($yaml),
            ...self::checkouts($yaml),
        ];
    }

    /**
     * @return list<string>
     */
    public static function actionRefs(string $yaml): array
    {
        $refs = [];

        foreach (self::lines($yaml) as $line) {
            if (preg_match(self::usesPattern(), $line, $match) === 1) {
                $refs[] = $match['ref'];
            }
        }

        return $refs;
    }

    /**
     * @return list<string>
     */
    public static function pinning(string $yaml): array
    {
        $violations = [];

        foreach (self::lines($yaml) as $index => $line) {
            if (preg_match(self::usesPattern(), $line, $match) !== 1 || str_starts_with($match['ref'], './')) {
                continue;
            }

            $number = $index + 1;

            if (preg_match('/^[^@\s]+@[0-9a-f]{40}$/', $match['ref']) !== 1) {
                $violations[] = "line {$number}: uses {$match['ref']} is not pinned to a 40-hex commit SHA";

                continue;
            }

            if (trim($match['version'] ?? '') === '') {
                $violations[] = "line {$number}: uses {$match['ref']} has no trailing # version comment";
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public static function comments(string $yaml): array
    {
        $violations = [];

        foreach (self::lines($yaml) as $index => $line) {
            if (str_starts_with(ltrim($line), '#')) {
                $number = $index + 1;
                $violations[] = "line {$number}: comment lines are not allowed";
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public static function triggers(string $yaml): array
    {
        return preg_match('/\bpull_request_target\b/', $yaml) === 1
            ? ['the pull_request_target trigger is forbidden']
            : [];
    }

    /**
     * @return list<string>
     */
    public static function permissions(string $yaml): array
    {
        $document = self::document($yaml);
        $violations = [];

        if (Tree::at($document, 'permissions') !== ['contents' => 'read']) {
            $violations[] = 'top-level permissions must be exactly contents: read';
        }

        return [...$violations, ...self::writeGrants($document, '')];
    }

    /**
     * @return list<string>
     */
    public static function concurrency(string $yaml): array
    {
        return Tree::at(self::document($yaml), 'concurrency.cancel-in-progress') === self::CANCEL_EXPRESSION
            ? []
            : ['concurrency.cancel-in-progress must cancel runs except on main'];
    }

    /**
     * @return list<string>
     */
    public static function timeouts(string $yaml): array
    {
        $violations = [];

        foreach (self::jobs($yaml) as $id => $job) {
            $timeout = $job['timeout-minutes'] ?? null;

            if (! is_int($timeout) || $timeout < 1) {
                $violations[] = "job {$id} has no positive timeout-minutes";
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public static function checkouts(string $yaml): array
    {
        $violations = [];

        foreach (self::jobs($yaml) as $id => $job) {
            foreach (Tree::items($job['steps'] ?? null) as $index => $step) {
                $uses = Tree::text(Tree::at($step, 'uses'));

                if (str_starts_with($uses, 'actions/checkout@') && Tree::at($step, 'with.persist-credentials') !== false) {
                    $violations[] = "job {$id} step {$index}: checkout does not set persist-credentials: false";
                }
            }
        }

        return $violations;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function jobs(string $yaml): array
    {
        $jobs = [];

        foreach (Tree::map(self::document($yaml)['jobs'] ?? null) as $id => $job) {
            $jobs[$id] = Tree::map($job);
        }

        return $jobs;
    }

    /**
     * @return array<string, mixed>
     */
    public static function document(string $yaml): array
    {
        return Tree::map(Yaml::parse($yaml));
    }

    /**
     * @return list<string>
     */
    public static function runCommands(string $yaml, string $job): array
    {
        $commands = [];

        foreach (Tree::items(self::jobs($yaml)[$job]['steps'] ?? null) as $step) {
            $run = Tree::text(Tree::at($step, 'run'));

            if ($run !== '') {
                $commands[] = trim($run);
            }
        }

        return $commands;
    }

    /**
     * @return list<string>
     */
    private static function writeGrants(mixed $node, string $path): array
    {
        $found = [];

        foreach (Tree::map($node) as $key => $value) {
            $here = $path === '' ? $key : $path.'.'.$key;

            if ($key === 'permissions') {
                $found = [...$found, ...self::grants($value, $here)];
            }

            $found = [...$found, ...self::writeGrants($value, $here)];
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private static function grants(mixed $permissions, string $path): array
    {
        if (is_string($permissions)) {
            return str_contains($permissions, 'write') ? ["{$path} grants {$permissions}"] : [];
        }

        $found = [];

        foreach (Tree::map($permissions) as $scope => $level) {
            if (is_string($level) && str_contains($level, 'write')) {
                $found[] = "{$path}.{$scope} grants {$level}";
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private static function lines(string $yaml): array
    {
        return explode("\n", str_replace("\r\n", "\n", $yaml));
    }

    private static function usesPattern(): string
    {
        return '/^\s*(?:-\s+)?uses:\s*(?<ref>[^\s#]+)(?:\s*#(?<version>.*))?$/';
    }
}
