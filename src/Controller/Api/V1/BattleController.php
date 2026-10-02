<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Entity\Auth\User;
use App\Entity\Combat\Battle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/battles')]
#[IsGranted('ROLE_PLAYER')]
class BattleController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/{id}', name: 'api_battle_detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        $battle = $this->em->getRepository(Battle::class)->find($id);
        if (!$battle) {
            return $this->jsonError('error.battle_not_found', 404);
        }

        if ($battle->getTeamA()->getId() !== $team->getId() && $battle->getTeamB()->getId() !== $team->getId()) {
            return $this->jsonError('error.unauthorized_battle_view', 403);
        }

        return $this->json([
            'id' => $battle->getId(),
            'match_type' => $battle->getMatchType()->value,
            'status' => $battle->getStatus()->value,
            'result' => $battle->getResult()?->value,
            'score_a' => $battle->getScoreA(),
            'score_b' => $battle->getScoreB(),
            'current_round' => $battle->getCurrentRound(),
            'processed_at' => $battle->getProcessedAt()?->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/{id}/log', name: 'api_battle_log', methods: ['GET'])]
    public function log(int $id): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        $battle = $this->em->getRepository(Battle::class)->find($id);
        if (!$battle) {
            return $this->jsonError('error.battle_not_found', 404);
        }

        if ($battle->getTeamA()->getId() !== $team->getId() && $battle->getTeamB()->getId() !== $team->getId()) {
            return $this->jsonError('error.unauthorized_battle_view', 403);
        }

        return $this->json($battle->getCombatLog());
    }

    private function getPlayerTeam(): ?\App\Entity\Team\Team
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $user?->getTeam();
    }
}
