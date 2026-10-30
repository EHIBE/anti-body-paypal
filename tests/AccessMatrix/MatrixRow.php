<?php

declare(strict_types=1);

namespace Tests\AccessMatrix;

use App\Models\User;
use Closure;

final readonly class MatrixRow
{
    public const string ABILITY = 'ability';

    public const string ROUTE = 'route';

    /**
     * @param  list<string>  $allowed
     * @param  Closure(?User): bool  $probe
     */
    private function __construct(
        public string $kind,
        public string $key,
        public array $allowed,
        public Closure $probe,
    ) {}

    /**
     * @param  list<string>  $allowed
     * @param  Closure(?User): bool  $probe
     */
    public static function ability(string $ability, array $allowed, Closure $probe): self
    {
        return new self(self::ABILITY, $ability, $allowed, $probe);
    }

    /**
     * @param  list<string>  $allowed
     * @param  Closure(?User): bool  $probe
     */
    public static function route(string $routeName, array $allowed, Closure $probe): self
    {
        return new self(self::ROUTE, $routeName, $allowed, $probe);
    }

    public function allows(string $actor): bool
    {
        return in_array($actor, $this->allowed, true);
    }
}
