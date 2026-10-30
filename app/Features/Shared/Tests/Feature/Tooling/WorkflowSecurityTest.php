<?php

declare(strict_types=1);

use Tests\Support\Repo;
use Tests\Support\Tree;
use Tests\Support\WorkflowAudit;

/**
 * @return array<string, array{string}>
 */
function toolingWorkflowDatasets(): array
{
    $datasets = [];

    foreach (Repo::workflowFiles() as $file) {
        $datasets[$file] = [$file];
    }

    return $datasets;
}

/**
 * @return array<string, array{file: string, job: array<string, mixed>, yaml: string}>
 */
function toolingWorkflowJobs(): array
{
    $jobs = [];

    foreach (Repo::workflowFiles() as $file) {
        foreach (WorkflowAudit::jobs(Repo::read($file)) as $id => $job) {
            $jobs[$id] = ['file' => $file, 'job' => $job, 'yaml' => Repo::read($file)];
        }
    }

    return $jobs;
}

function toolingCleanWorkflow(): string
{
    return implode("\n", [
        'name: demo',
        '',
        'on:',
        '  push:',
        '    branches:',
        '      - main',
        '  pull_request:',
        '',
        'permissions:',
        '  contents: read',
        '',
        'concurrency:',
        '  group: ${{ github.workflow }}-${{ github.ref }}',
        "  cancel-in-progress: \${{ github.ref != 'refs/heads/main' }}",
        '',
        'jobs:',
        '  build:',
        '    name: build',
        '    runs-on: ubuntu-latest',
        '    timeout-minutes: 5',
        '',
        '    steps:',
        '      - name: Checkout code',
        '        uses: actions/checkout@'.str_repeat('a', 40).' # v1.2.3',
        '        with:',
        '          persist-credentials: false',
        '',
        '      - name: Run tests',
        '        run: composer test',
        '',
    ]);
}

function toolingMutateWorkflow(string $search, string $replace): string
{
    $clean = toolingCleanWorkflow();
    $mutated = str_replace($search, $replace, $clean);

    expect($mutated)->not->toBe($clean);

    return $mutated;
}

it('finds the workflow files', function (): void {
    expect(Repo::workflowFiles())->toBe(['.github/workflows/security.yml', '.github/workflows/tests.yml']);
});

it('pins every action to a 40-hex commit SHA followed by a version comment', function (string $file): void {
    $yaml = Repo::read($file);

    expect(WorkflowAudit::actionRefs($yaml))->not->toBe([])
        ->and(WorkflowAudit::pinning($yaml))->toBe([]);
})->with(toolingWorkflowDatasets());

it('sets the top-level permissions to exactly contents read and grants no write permission anywhere', function (string $file): void {
    $yaml = Repo::read($file);

    expect(Tree::at(WorkflowAudit::document($yaml), 'permissions'))->toBe(['contents' => 'read'])
        ->and(WorkflowAudit::permissions($yaml))->toBe([]);
})->with(toolingWorkflowDatasets());

it('sets persist-credentials false on every checkout step', function (string $file): void {
    $yaml = Repo::read($file);
    $checkouts = array_filter(WorkflowAudit::actionRefs($yaml), static fn (string $ref): bool => str_starts_with($ref, 'actions/checkout@'));

    expect($checkouts)->not->toBe([])
        ->and(WorkflowAudit::checkouts($yaml))->toBe([]);
})->with(toolingWorkflowDatasets());

it('does not use the pull_request_target trigger', function (string $file): void {
    expect(WorkflowAudit::triggers(Repo::read($file)))->toBe([]);
})->with(toolingWorkflowDatasets());

it('gives every job a timeout', function (string $file): void {
    $yaml = Repo::read($file);

    expect(WorkflowAudit::jobs($yaml))->not->toBe([])
        ->and(WorkflowAudit::timeouts($yaml))->toBe([]);
})->with(toolingWorkflowDatasets());

it('cancels superseded runs except on main', function (string $file): void {
    $yaml = Repo::read($file);

    expect(Tree::at(WorkflowAudit::document($yaml), 'concurrency.cancel-in-progress'))->toBe("\${{ github.ref != 'refs/heads/main' }}")
        ->and(WorkflowAudit::concurrency($yaml))->toBe([]);
})->with(toolingWorkflowDatasets());

it('contains no comment lines other than the version comment after a pinned SHA', function (string $file): void {
    expect(WorkflowAudit::comments(Repo::read($file)))->toBe([]);
})->with(toolingWorkflowDatasets());

it('reports no violation of any rule for a workflow file', function (string $file): void {
    expect(WorkflowAudit::violations(Repo::read($file)))->toBe([]);
})->with(toolingWorkflowDatasets());

it('declares exactly the six expected jobs with the job id as the job name', function (): void {
    $jobs = toolingWorkflowJobs();

    expect(array_keys($jobs))->toEqualCanonicalizing(['ci', 'postgres', 'gitleaks', 'dependency-review', 'zizmor', 'actionlint']);

    foreach ($jobs as $id => $entry) {
        expect($entry['job']['name'] ?? null)->toBe($id);
    }
});

it('checks out the full history in the gitleaks job and runs the gitleaks action with the project config', function (): void {
    $entry = toolingWorkflowJobs()['gitleaks'];
    $steps = array_map(Tree::map(...), Tree::items($entry['job']['steps'] ?? null));
    $checkout = array_values(array_filter($steps, static fn (array $step): bool => str_starts_with(Tree::text($step['uses'] ?? null), 'actions/checkout@')));
    $scan = array_values(array_filter($steps, static fn (array $step): bool => str_starts_with(Tree::text($step['uses'] ?? null), 'gitleaks/gitleaks-action@')));

    expect($entry['file'])->toBe('.github/workflows/security.yml')
        ->and($checkout)->toHaveCount(1)
        ->and(Tree::at($checkout[0], 'with.fetch-depth'))->toBe(0)
        ->and($scan)->toHaveCount(1)
        ->and(Tree::at($scan[0], 'env.GITLEAKS_CONFIG'))->toBe('.gitleaks.toml');
});

it('runs composer setup and then composer ci:check in the ci job', function (): void {
    $entry = toolingWorkflowJobs()['ci'];
    $commands = WorkflowAudit::runCommands($entry['yaml'], 'ci');
    $positions = array_flip($commands);
    $setup = $positions['composer setup'] ?? -1;
    $gate = $positions['composer ci:check'] ?? -1;

    expect($entry['file'])->toBe('.github/workflows/tests.yml')
        ->and($setup)->toBeGreaterThanOrEqual(0)
        ->and($setup)->toBeLessThan($gate);
});

it('runs the postgres job against a postgres service with a health check and the whole suite', function (): void {
    $entry = toolingWorkflowJobs()['postgres'];
    $job = $entry['job'];
    $commands = implode("\n", WorkflowAudit::runCommands($entry['yaml'], 'postgres'));
    $setupPhp = array_values(array_filter(
        array_map(Tree::map(...), Tree::items($job['steps'] ?? null)),
        static fn (array $step): bool => str_starts_with(Tree::text($step['uses'] ?? null), 'shivammathur/setup-php@'),
    ));

    expect($entry['file'])->toBe('.github/workflows/tests.yml')
        ->and(Tree::text(Tree::at($job, 'services.postgres.image')))->toStartWith('postgres@sha256:')
        ->and(Tree::text(Tree::at($job, 'services.postgres.options')))->toContain('--health-cmd')
        ->and(Tree::at($job, 'env.DB_CONNECTION'))->toBe('pgsql')
        ->and(Tree::at($job, 'env.ANTIBODY_EXPECTED_DB_DRIVER'))->toBe('pgsql')
        ->and($setupPhp)->toHaveCount(1)
        ->and(Tree::text(Tree::at($setupPhp[0], 'with.extensions')))->toContain('pdo_pgsql')
        ->and($commands)->toContain('php artisan migrate:fresh --seed --force')
        ->and($commands)->toContain('php artisan test');
});

it('runs dependency review only on pull requests and fails on high severity', function (): void {
    $entry = toolingWorkflowJobs()['dependency-review'];
    $steps = array_map(Tree::map(...), Tree::items($entry['job']['steps'] ?? null));
    $review = array_values(array_filter($steps, static fn (array $step): bool => str_starts_with(Tree::text($step['uses'] ?? null), 'actions/dependency-review-action@')));

    expect($entry['job']['if'] ?? null)->toBe("github.event_name == 'pull_request'")
        ->and($review)->toHaveCount(1)
        ->and(Tree::at($review[0], 'with.fail-on-severity'))->toBe('high');
});

it('accepts a clean workflow', function (): void {
    expect(WorkflowAudit::violations(toolingCleanWorkflow()))->toBe([]);
});

it('reports a planted unpinned action', function (string $search, string $replace, string $expected): void {
    $violations = WorkflowAudit::pinning(toolingMutateWorkflow($search, $replace));

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($expected);
})->with(function (): array {
    $sha = str_repeat('a', 40);

    return [
        'tag instead of a SHA' => ['actions/checkout@'.$sha.' # v1.2.3', 'actions/checkout@v4 # v4', 'actions/checkout@v4 is not pinned to a 40-hex commit SHA'],
        'branch instead of a SHA' => ['actions/checkout@'.$sha.' # v1.2.3', 'actions/checkout@main # main', 'is not pinned to a 40-hex commit SHA'],
        'short SHA' => ['actions/checkout@'.$sha.' # v1.2.3', 'actions/checkout@'.substr($sha, 0, 7).' # v1', 'is not pinned to a 40-hex commit SHA'],
        'uppercase SHA' => ['actions/checkout@'.$sha.' # v1.2.3', 'actions/checkout@'.strtoupper($sha).' # v1', 'is not pinned to a 40-hex commit SHA'],
        'SHA with 41 characters' => ['actions/checkout@'.$sha.' # v1.2.3', 'actions/checkout@'.$sha.'a # v1', 'is not pinned to a 40-hex commit SHA'],
        'missing version comment' => [' # v1.2.3', '', 'has no trailing # version comment'],
        'empty version comment' => [' # v1.2.3', ' #', 'has no trailing # version comment'],
        'docker image by tag' => ['actions/checkout@'.$sha.' # v1.2.3', 'docker://alpine:3.20', 'docker://alpine:3.20 is not pinned to a 40-hex commit SHA'],
    ];
});

it('reports a planted write permission', function (string $search, string $replace, string $expected): void {
    $violations = WorkflowAudit::permissions(toolingMutateWorkflow($search, $replace));

    expect($violations)->toContain($expected);
})->with([
    'job grants contents write' => ['    timeout-minutes: 5', "    timeout-minutes: 5\n    permissions:\n      contents: write", 'jobs.build.permissions.contents grants write'],
    'job grants write-all' => ['    timeout-minutes: 5', "    timeout-minutes: 5\n    permissions: write-all", 'jobs.build.permissions grants write-all'],
    'job grants id-token write' => ['    timeout-minutes: 5', "    timeout-minutes: 5\n    permissions:\n      id-token: write", 'jobs.build.permissions.id-token grants write'],
    'top-level contents write' => ["permissions:\n  contents: read", "permissions:\n  contents: write", 'permissions.contents grants write'],
    'top-level permissions widened' => ["permissions:\n  contents: read", "permissions:\n  contents: read\n  issues: read", 'top-level permissions must be exactly contents: read'],
    'top-level permissions removed' => ["permissions:\n  contents: read\n\n", '', 'top-level permissions must be exactly contents: read'],
]);

it('reports a planted pull_request_target trigger', function (): void {
    $violations = WorkflowAudit::triggers(toolingMutateWorkflow('  pull_request:', '  pull_request_target:'));

    expect($violations)->toBe(['the pull_request_target trigger is forbidden']);
});

it('reports a planted checkout without persist-credentials false', function (string $search, string $replace): void {
    $violations = WorkflowAudit::checkouts(toolingMutateWorkflow($search, $replace));

    expect($violations)->toBe(['job build step 0: checkout does not set persist-credentials: false']);
})->with([
    'with block removed' => ["        with:\n          persist-credentials: false\n", ''],
    'persist-credentials true' => ['persist-credentials: false', 'persist-credentials: true'],
    'persist-credentials quoted' => ['persist-credentials: false', "persist-credentials: 'false'"],
    'another input only' => ['persist-credentials: false', 'fetch-depth: 0'],
]);

it('reports a planted job without a timeout', function (string $search, string $replace): void {
    $violations = WorkflowAudit::timeouts(toolingMutateWorkflow($search, $replace));

    expect($violations)->toBe(['job build has no positive timeout-minutes']);
})->with([
    'timeout removed' => ["    timeout-minutes: 5\n", ''],
    'timeout zero' => ['timeout-minutes: 5', 'timeout-minutes: 0'],
    'timeout as text' => ['timeout-minutes: 5', 'timeout-minutes: soon'],
]);

it('reports a planted concurrency block that never cancels or always cancels', function (string $search, string $replace): void {
    $violations = WorkflowAudit::concurrency(toolingMutateWorkflow($search, $replace));

    expect($violations)->toBe(['concurrency.cancel-in-progress must cancel runs except on main']);
})->with([
    'always cancels' => ["\${{ github.ref != 'refs/heads/main' }}", 'true'],
    'never cancels' => ["\${{ github.ref != 'refs/heads/main' }}", 'false'],
    'cancels only on main' => ['!=', '=='],
    'block removed' => ["concurrency:\n  group: \${{ github.workflow }}-\${{ github.ref }}\n  cancel-in-progress: \${{ github.ref != 'refs/heads/main' }}\n\n", ''],
]);

it('reports a planted comment line', function (): void {
    $violations = WorkflowAudit::comments(toolingMutateWorkflow('name: demo', "# explanation\nname: demo"));

    expect($violations)->toBe(['line 1: comment lines are not allowed']);
});

it('reports every violation of a workflow that breaks all rules at once', function (): void {
    $yaml = implode("\n", [
        'name: bad',
        'on:',
        '  pull_request_target:',
        'permissions: write-all',
        'jobs:',
        '  build:',
        '    runs-on: ubuntu-latest',
        '    steps:',
        '      - uses: actions/checkout@v4',
    ]);

    $violations = WorkflowAudit::violations($yaml);

    expect($violations)->toContain('line 9: uses actions/checkout@v4 is not pinned to a 40-hex commit SHA')
        ->and($violations)->toContain('the pull_request_target trigger is forbidden')
        ->and($violations)->toContain('top-level permissions must be exactly contents: read')
        ->and($violations)->toContain('permissions grants write-all')
        ->and($violations)->toContain('concurrency.cancel-in-progress must cancel runs except on main')
        ->and($violations)->toContain('job build has no positive timeout-minutes')
        ->and($violations)->toContain('job build step 0: checkout does not set persist-credentials: false');
});
