<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Kingdom\KingdomService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides auth_kingdoms() Twig function so the auth modal can render the
 * kingdom list on any page without requiring the controller to pass it.
 */
class AuthExtension extends AbstractExtension
{
    public function __construct(
        private readonly KingdomService $kingdomService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('auth_kingdoms', $this->getKingdoms(...)),
        ];
    }

    /** @return array<int, array{kingdom: object, playerCount: int, capacity: int}> */
    public function getKingdoms(): array
    {
        return $this->kingdomService->listWithCapacity();
    }
}
