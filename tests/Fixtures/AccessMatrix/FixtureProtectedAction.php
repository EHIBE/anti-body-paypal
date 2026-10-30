<?php

declare(strict_types=1);

namespace Tests\Fixtures\AccessMatrix;

use App\Features\Shared\Authorization\RequiresAbility;

#[RequiresAbility('fixture.approve')]
final class FixtureProtectedAction {}
