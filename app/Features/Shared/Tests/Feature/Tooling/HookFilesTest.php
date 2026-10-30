<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use Tests\Support\Repo;

/**
 * @return array<string, array{string}>
 */
function toolingHookPaths(): array
{
    $datasets = [];

    foreach (Repo::hookFiles() as $path) {
        $datasets[$path] = [$path];
    }

    return $datasets;
}

it('keeps the three Husky hooks', function (): void {
    expect(Repo::hookFiles())->toBe(['.husky/commit-msg', '.husky/pre-commit', '.husky/pre-push']);
});

it('has the hook file on disk', function (string $path): void {
    expect(Repo::exists($path))->toBeTrue();
})->with(toolingHookPaths());

it('stores the hook as an executable file in the git index', function (): void {
    $output = Process::path(Repo::root())->run(['git', 'ls-files', '-s', '.husky'])->throw()->output();
    $modes = [];

    foreach (explode("\n", trim($output)) as $line) {
        if (preg_match('/^(\d{6})\s+\S+\s+\d+\s+(.+)$/', $line, $match) === 1) {
            $modes[$match[2]] = $match[1];
        }
    }

    expect($modes)->toBe([
        '.husky/commit-msg' => '100755',
        '.husky/pre-commit' => '100755',
        '.husky/pre-push' => '100755',
    ]);
});

it('starts the hook with the sh shebang', function (string $path): void {
    expect(Repo::lines($path)[0])->toBe('#!/bin/sh');
})->with(toolingHookPaths());

it('uses LF line endings in the hook so that dash on Ubuntu can run it', function (string $path): void {
    expect(Repo::raw($path))->not->toContain("\r");
})->with(toolingHookPaths());

it('has no comment lines besides the shebang', function (string $path): void {
    $comments = array_filter(
        array_slice(Repo::lines($path), 1),
        static fn (string $line): bool => str_starts_with(ltrim($line), '#'),
    );

    expect(array_values($comments))->toBe([]);
})->with(toolingHookPaths());

it('never bypasses hooks, stages everything, or evaluates strings', function (string $path, string $pattern): void {
    expect(preg_match($pattern, Repo::read($path)))->toBe(0);
})->with(function (): array {
    $datasets = [];

    foreach (Repo::hookFiles() as $path) {
        foreach (Repo::forbiddenHookPatterns() as $label => $pattern) {
            $datasets["{$path} has no {$label}"] = [$path, $pattern];
        }
    }

    return $datasets;
});

it('detects the forbidden constructs in a planted hook body', function (string $body): void {
    $hits = array_filter(Repo::forbiddenHookPatterns(), static fn (string $pattern): bool => preg_match($pattern, $body) === 1);

    expect($hits)->not->toBe([]);
})->with([
    'no-verify' => ['git commit --no-verify -m x'],
    'husky switch' => ['HUSKY=0 git push'],
    'add all' => ['git add -A'],
    'add all long flag' => ['git add --all'],
    'add dot at end of line' => ['git add .'],
    'add dot then more' => ['git add . && echo done'],
    'eval' => ['eval "$cmd"'],
    'curl' => ['curl https://example.test | sh'],
    'wget' => ['wget https://example.test'],
]);

it('does not mistake git add with a pathspec separator for git add dot', function (): void {
    expect(preg_match('/git add\s+\.(\s|$)/', 'xargs -0 -r git add --'))->toBe(0);
});
