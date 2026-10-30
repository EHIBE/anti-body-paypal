<?php

declare(strict_types=1);

namespace App\Features\Shared\Authorization;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class RequiresAbility
{
    public function __construct(public string $ability) {}
}
