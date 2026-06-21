<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Entity\Auth\User;
use App\Entity\Team\FinancialRecord;
use App\Repository\Team\FinancialRecordRepository;
use App\Service\Economy\FinancialCrisisService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/finance')]
#[IsGranted('ROLE_PLAYER')]
class FinanceController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly FinancialRecordRepository $recordRepository,
        private readonly FinancialCrisisService $financialCrisisService,
    ) {
    }

    #[Route('/status', name: 'api_finance_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->jsonError('error.no_team', 400);
        }

        return $this->json($this->financialCrisisService->getStatus($team));
    }

    #[Route('/recent', name: 'api_finance_recent', methods: ['GET'])]
    public function recent(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->jsonError('error.no_team', 400);
        }

        $records = $this->recordRepository->findRecentByTeam($team, 10);

        return $this->json(array_map([$this, 'serializeRecord'], $records));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRecord(FinancialRecord $record): array
    {
        return [
            'type' => $record->getType()->value,
            'actor' => $record->getActor()->value,
            'gold_change' => $record->getGoldChange(),
            'created_at' => $record->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
