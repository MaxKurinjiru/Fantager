<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Entity\Auth\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class MeController extends AbstractController
{
    use ApiControllerTrait;

    #[Route('/api/v1/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->jsonError('error.access_denied', 401);
        }

        $team = $user->getTeam();
        $kingdom = $user->getKingdom();

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'display_name' => $user->getDisplayName(),
            'display_name_slug' => $user->getDisplayNameSlug(),
            'locale' => $user->getLocale(),
            'is_verified' => $user->isVerified(),
            'roles' => $user->getRoles(),
            'kingdom_id' => $kingdom?->getId(),
            'team_id' => $team?->getId(),
            'team_name' => $team?->getName(),
        ]);
    }
}
