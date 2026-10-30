<?php

declare(strict_types=1);

use Tests\Support\Repo;
use Tests\Support\Tree;
use Tests\Support\WorkflowAudit;

/**
 * @return list<string>
 */
function toolingComposerScript(string $name): array
{
    return Tree::strings(Tree::at(Repo::json('composer.json'), 'scripts.'.$name));
}

function toolingToolNamePattern(): string
{
    return '/\b(phpstan|pint|rector|pest)\b/i';
}

it('runs the CI gate as the timeout switch, the check group, the asset build and the tests', function (): void {
    expect(toolingComposerScript('ci:check'))->toBe([
        'Composer\\Config::disableProcessTimeout',
        '@check',
        'npm run build',
        '@test',
    ]);
});

it('groups the checks as validate, refactor, lint, types and audit in that order', function (): void {
    expect(toolingComposerScript('check'))->toBe([
        '@composer validate --strict',
        '@refactor:check',
        '@lint:check',
        '@types:check',
        '@audit:check',
    ]);
});

it('defines each check script exactly', function (string $script, array $commands): void {
    expect(toolingComposerScript($script))->toBe($commands);
})->with([
    'audit:check' => ['audit:check', ['@composer audit', 'npm audit --audit-level=high']],
    'lint' => ['lint', ['pint --parallel']],
    'lint:check' => ['lint:check', ['pint --parallel --test']],
    'rector' => ['rector', ['rector']],
    'refactor:check' => ['refactor:check', ['rector process --dry-run --no-progress-bar']],
    'types:check' => ['types:check', ['phpstan analyse --memory-limit=1G --no-progress']],
    'fix' => ['fix', ['@rector', '@lint']],
    'test' => ['test', ['@php artisan config:clear --ansi @no_additional_args', '@php artisan test --parallel']],
]);

it('runs the test script in parallel after clearing the config cache', function (): void {
    $commands = toolingComposerScript('test');

    expect($commands[0] ?? null)->toContain('config:clear')
        ->and($commands[1] ?? null)->toContain('--parallel');
});

it('installs npm packages without lifecycle scripts and then runs the prepare script right after', function (): void {
    expect(implode("\n", toolingComposerScript('setup')))->toContain("npm install --ignore-scripts\nnpm run prepare");
});

it('requires PHP 8.4 or newer within the major version', function (): void {
    expect(Tree::at(Repo::json('composer.json'), 'require.php'))->toBe('^8.4');
});

it('defines the npm scripts that wire Husky and the single gate', function (): void {
    $scripts = Tree::map(Repo::json('package.json')['scripts'] ?? null);

    expect($scripts['prepare'] ?? null)->toBe('husky')
        ->and($scripts['pre-commit'] ?? null)->toBe('composer rector && composer lint')
        ->and($scripts['ci:check'] ?? null)->toBe('composer ci:check');
});

it('keeps npm lifecycle scripts disabled', function (): void {
    expect(Repo::lines('.npmrc'))->toContain('ignore-scripts=true')
        ->and(Repo::lines('.npmrc'))->not->toContain('ignore-scripts=false');
});

it('calls the single CI gate from the pre-push hook', function (): void {
    expect(Repo::read('.husky/pre-push'))->toContain("npm run ci:check\n");
});

it('calls the single CI gate from the ci job of the tests workflow', function (): void {
    $commands = WorkflowAudit::runCommands(Repo::read('.github/workflows/tests.yml'), 'ci');

    expect($commands)->toContain('composer ci:check');
});

it('lists no individual tool in a hook or a workflow so that the gate has one definition', function (string $path): void {
    expect(preg_match(toolingToolNamePattern(), Repo::read($path)))->toBe(0);
})->with(function (): array {
    $datasets = [];

    foreach ([...Repo::hookFiles(), ...Repo::workflowFiles()] as $path) {
        $datasets[$path] = [$path];
    }

    return $datasets;
});

it('notices a tool name pasted into a hook or workflow', function (string $line): void {
    expect(preg_match(toolingToolNamePattern(), $line))->toBe(1);
})->with([
    'phpstan' => ['vendor/bin/phpstan analyse'],
    'pint' => ['vendor/bin/pint --test'],
    'rector' => ['vendor/bin/rector process'],
    'pest' => ['vendor/bin/pest --parallel'],
    'uppercase' => ['run: PHPStan'],
]);

it('does not mistake printf for pint', function (): void {
    expect(preg_match(toolingToolNamePattern(), 'printf "%s\n" "$x"'))->toBe(0);
});
