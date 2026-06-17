<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Enum\ChronicleCategory;
use App\Enum\ChronicleEventType;
use App\Service\TeamChronicle\TeamChroniclePresenter;
use App\Service\Translation\UserMessageTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class TeamChronicleController extends AbstractController
{
    public function __construct(
        private readonly TeamChroniclePresenter $teamChroniclePresenter,
        private readonly UserMessageTranslator $userMessages,
    ) {
    }

    #[Route('/app/chronicle', name: 'app_team_chronicle', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', $this->userMessages->trans('error.no_team'));

            return $this->redirectToRoute('app_home');
        }

        $typeValue = $request->query->get('type');
        $type = is_string($typeValue) && '' !== $typeValue
            ? ChronicleEventType::tryFrom($typeValue)
            : null;

        $categoryValue = $request->query->get('category', 'all');
        $category = ChronicleCategory::tryFrom($categoryValue) ?? ChronicleCategory::All;

        $sort = $request->query->get('sort', 'date-desc');
        if (!in_array($sort, ['date-desc', 'date-asc'], true)) {
            $sort = 'date-desc';
        }

        $entries = $this->teamChroniclePresenter->presentFilteredForTeam(
            $team,
            $type,
            $category,
            $sort,
            null,
            $user->getLocale(),
        );

        return $this->render('team_chronicle/index.html.twig', [
            'team' => $team,
            'entries' => $entries,
            'current_type' => $type?->value,
            'current_category' => $category->value,
            'current_sort' => $sort,
            'types' => ChronicleEventType::cases(),
            'categories' => ChronicleCategory::cases(),
        ]);
    }
}
