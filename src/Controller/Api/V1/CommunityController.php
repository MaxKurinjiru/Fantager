<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Entity\Auth\User;
use App\Service\Community\PlayerProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1')]
#[IsGranted('ROLE_PLAYER')]
class CommunityController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly PlayerProfileService $profileService,
    ) {
    }

    /**
     * Returns the public profile of a player/user.
     *
     * GET /api/v1/players/{id}/profile
     */
    #[Route('/players/{id}/profile', name: 'api_player_profile', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function profile(int $id): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $this->getUser();

        try {
            $subject = $this->profileService->findSubject($viewer, $id);

            return $this->json($this->profileService->getProfile($subject, $viewer));
        } catch (\DomainException $e) {
            $status = str_contains($e->getMessage(), 'access_denied') ? 403 : 404;

            return $this->jsonException($e, $status);
        }
    }

    /**
     * Returns the public profile of a team.
     *
     * GET /api/v1/teams/{id}/profile
     */
    #[Route('/teams/{id}/profile', name: 'api_team_profile', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function teamProfile(int $id): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $this->getUser();

        try {
            $team = $this->profileService->findTeam($viewer, $id);

            return $this->json($this->profileService->getProfileByTeam($team, $viewer));
        } catch (\DomainException $e) {
            $status = str_contains($e->getMessage(), 'access_denied') ? 403 : 404;

            return $this->jsonException($e, $status);
        }
    }
}
