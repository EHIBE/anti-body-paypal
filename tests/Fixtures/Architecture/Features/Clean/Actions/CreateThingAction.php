<?php

declare(strict_types=1);

namespace Tests\Fixtures\Architecture\Features\Clean\Actions;

use Tests\Fixtures\Architecture\Features\Clean\Data\ThingData;

final readonly class CreateThingAction
{
    public function __construct(private string $prefix) {}

    public function handle(string $name): ThingData
    {
        return new ThingData($this->prefix.$name);
    }
}
