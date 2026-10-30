<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('runs on the database driver the CI job expects', function (): void {
    expect(DB::connection()->getDriverName())->toBe(getenv('ANTIBODY_EXPECTED_DB_DRIVER'));
})->skip(
    fn (): bool => getenv('ANTIBODY_EXPECTED_DB_DRIVER') === false || getenv('ANTIBODY_EXPECTED_DB_DRIVER') === '',
    'ANTIBODY_EXPECTED_DB_DRIVER is only set by the postgres CI job',
);
