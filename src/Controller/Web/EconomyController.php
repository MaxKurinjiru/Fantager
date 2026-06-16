<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Training\Trainer;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Enum\HeroStatus;
use App\Enum\ItemStatus;
use App\Enum\TrainerStatus;
use App\Repository\Team\FinancialRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class EconomyController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FinancialRecordRepository $recordRepository,
    ) {
    }

    #[Route('/app/economy', name: 'app_economy', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', 'No team assigned to your account.');

            return $this->redirectToRoute('app_home');
        }

        $tab = $request->query->get('tab', 'browse');
        if (!in_array($tab, ['browse', 'sell', 'mylistings', 'history', 'ledger'], true)) {
            $tab = 'browse';
        }

        $heroes = $this->em->getRepository(Hero::class)->findBy([
            'team' => $team,
            'status' => [HeroStatus::Available],
        ]);
        $items = $this->em->getRepository(Item::class)->findBy([
            'ownerTeam' => $team,
            'status' => ItemStatus::Available,
            'equippedHero' => null,
        ]);
        $trainers = $this->em->getRepository(Trainer::class)->findBy([
            'team' => $team,
            'status' => TrainerStatus::Active,
        ]);

        $type = $request->query->get('type');
        if ('' === $type) {
            $type = null;
        }
        $actor = $request->query->get('actor');
        if ('' === $actor) {
            $actor = null;
        }
        $sort = $request->query->get('sort', 'date-desc');

        $records = $this->recordRepository->findByTeamFiltered($team, $type, $actor, $sort);

        $sellHeroId = $request->query->getInt('hero');
        if ($sellHeroId <= 0) {
            $sellHeroId = null;
        }

        return $this->render('economy/index.html.twig', [
            'team' => $team,
            'tab' => $tab,
            'heroes' => $heroes,
            'items' => $items,
            'trainers' => $trainers,
            'taxRate' => (float) $team->getKingdom()->getMarketplaceTaxRate(),
            'records' => $records,
            'current_type' => $type,
            'current_actor' => $actor,
            'current_sort' => $sort,
            'types' => FinancialRecordType::cases(),
            'actors' => FinancialRecordActor::cases(),
            'sell_hero_id' => $sellHeroId,
        ]);
    }
}
