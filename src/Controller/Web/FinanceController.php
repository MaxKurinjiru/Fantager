<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Repository\Team\FinancialRecordRepository;
use App\Service\Economy\FinanceSummaryService;
use App\Service\Translation\UserMessageTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class FinanceController extends AbstractController
{
    public function __construct(
        private readonly FinancialRecordRepository $recordRepository,
        private readonly FinanceSummaryService $financeSummaryService,
        private readonly UserMessageTranslator $userMessages,
    ) {
    }

    #[Route('/app/finance', name: 'app_finance', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', $this->userMessages->trans('error.no_team'));

            return $this->redirectToRoute('app_home');
        }

        $type = $request->query->get('type');
        if ('' === $type) {
            $type = null;
        }
        $actor = $request->query->get('actor');
        if ('' === $actor) {
            $actor = null;
        }
        $sort = $request->query->get('sort', 'date-desc');

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 50;

        $total = $this->recordRepository->countByTeamFiltered($team, $type, $actor);
        $totalPages = max(1, (int) ceil($total / $limit));

        if ($page > $totalPages && $total > 0) {
            return $this->redirectToRoute('app_finance', array_merge($request->query->all(), ['page' => $totalPages]));
        }

        $records = $this->recordRepository->findByTeamFiltered($team, $type, $actor, $sort, $page, $limit);
        $summary = $this->financeSummaryService->buildOverview($team);

        return $this->render('finance/index.html.twig', [
            'team' => $team,
            'summary' => $summary,
            'records' => $records,
            'current_type' => $type,
            'current_actor' => $actor,
            'current_sort' => $sort,
            'types' => FinancialRecordType::cases(),
            'actors' => FinancialRecordActor::cases(),
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
        ]);
    }
}
