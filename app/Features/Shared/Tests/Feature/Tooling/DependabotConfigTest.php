<?php

declare(strict_types=1);

use Tests\Support\Repo;
use Tests\Support\Tree;

/**
 * @return array<string, array<string, mixed>>
 */
function toolingDependabotUpdates(): array
{
    $updates = [];

    foreach (Tree::items(Repo::yaml('.github/dependabot.yml')['updates'] ?? null) as $update) {
        $update = Tree::map($update);
        $updates[Tree::text($update['package-ecosystem'] ?? null)] = $update;
    }

    return $updates;
}

/**
 * @return array<string, array{string}>
 */
function toolingDependabotEcosystems(): array
{
    return [
        'github-actions' => ['github-actions'],
        'composer' => ['composer'],
        'npm' => ['npm'],
    ];
}

it('uses version 2 of the Dependabot schema', function (): void {
    expect(Repo::yaml('.github/dependabot.yml')['version'] ?? null)->toBe(2);
});

it('updates exactly github-actions, composer and npm once each', function (): void {
    $ecosystems = array_map(
        static fn (mixed $update): string => Tree::text(Tree::at($update, 'package-ecosystem')),
        Tree::items(Repo::yaml('.github/dependabot.yml')['updates'] ?? null),
    );

    sort($ecosystems);

    expect($ecosystems)->toBe(['composer', 'github-actions', 'npm']);
});

it('updates the ecosystem from the repository root', function (string $ecosystem): void {
    expect(Tree::at(toolingDependabotUpdates()[$ecosystem], 'directory'))->toBe('/');
})->with(toolingDependabotEcosystems());

it('checks the ecosystem weekly', function (string $ecosystem): void {
    expect(Tree::at(toolingDependabotUpdates()[$ecosystem], 'schedule.interval'))->toBe('weekly');
})->with(toolingDependabotEcosystems());

it('waits five days before proposing a new release of the ecosystem', function (string $ecosystem): void {
    expect(Tree::at(toolingDependabotUpdates()[$ecosystem], 'cooldown.default-days'))->toBe(5);
})->with(toolingDependabotEcosystems());

it('groups all updates of the ecosystem into one group with the wildcard pattern', function (string $ecosystem): void {
    $groups = Tree::map(Tree::at(toolingDependabotUpdates()[$ecosystem], 'groups'));

    expect(array_keys($groups))->toBe([$ecosystem])
        ->and(Tree::at($groups[$ecosystem], 'patterns'))->toBe(['*']);
})->with(toolingDependabotEcosystems());
