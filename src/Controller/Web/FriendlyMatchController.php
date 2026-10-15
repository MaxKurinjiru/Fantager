<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Enum\FriendlyMatchStatus;
use App\Repository\Team\TeamRepository;
use App\Service\FriendlyMatch\FriendlyMatchService;
use App\Service\Translation\UserMessageTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class FriendlyMatchController extends AbstractController
{
    public function __construct(
        private readonly FriendlyMatchService $friendlyMatchService,
        private readonly TeamRepository $teamRepository,
        private readonly UserMessageTranslator $userMessages,
    ) {
    }

    #[Route('/app/friendly-matches', name: 'app_friendly_matches', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (null === $team) {
            $this->addFlash('error', $this->userMessages->trans('error.no_team'));

            return $this->redirectToRoute('app_home');
        }

        $this->friendlyMatchService->expireStalePending(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        $tab = (string) $request->query->get('tab', 'upcoming');
        if (!in_array($tab, ['upcoming', 'pending', 'history'], true)) {
            $tab = 'upcoming';
        }

        $all = $this->friendlyMatchService->listForKingdom($team->getKingdom());
        $teamId = $team->getId();

        $upcoming = [];
        $pending = [];
        $history = [];

        foreach ($all as $match) {
            $status = $match->getStatus();
            if (FriendlyMatchStatus::Pending === $status) {
                $pending[] = $match;
            } elseif (in_array($status, [FriendlyMatchStatus::Scheduled, FriendlyMatchStatus::InProgress], true)) {
                $upcoming[] = $match;
            } else {
                $history[] = $match;
            }
        }

        $matches = match ($tab) {
            'pending' => $pending,
            'history' => $history,
            default => $upcoming,
        };

        $opponents = $this->teamRepository->findPlayerTeamsByKingdom(
            (int) $team->getKingdom()->getId(),
            $teamId
        );

        return $this->render('friendly_matches/index.html.twig', [
            'team' => $team,
            'tab' => $tab,
            'matches' => $matches,
            'pending_count' => count($pending),
            'upcoming_count' => count($upcoming),
            'opponents' => $opponents,
            'serialized_matches' => $this->friendlyMatchService->serializeList($matches),
        ]);
    }
}
