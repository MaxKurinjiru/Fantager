<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Repository\Team\FinancialRecordRepository;
use App\Enum\FinancialRecordType;
use App\Enum\FinancialRecordActor;
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
    ) {
    }

    #[Route('/app/finance', name: 'app_finance', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', 'No team assigned to your account.');

            return $this->redirectToRoute('app_home');
        }

        $type = $request->query->get('type');
        if ($type === '') {
            $type = null;
        }
        $actor = $request->query->get('actor');
        if ($actor === '') {
            $actor = null;
        }
        $sort = $request->query->get('sort', 'date-desc');

        $records = $this->recordRepository->findByTeamFiltered($team, $type, $actor, $sort);

        $types = FinancialRecordType::cases();
        $actors = FinancialRecordActor::cases();

        return $this->render('finance/index.html.twig', [
            'team' => $team,
            'records' => $records,
            'current_type' => $type,
            'current_actor' => $actor,
            'current_sort' => $sort,
            'types' => $types,
            'actors' => $actors,
        ]);
    }
}
