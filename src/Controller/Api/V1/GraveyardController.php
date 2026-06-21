<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Entity\Auth\User;
use App\Enum\HeroRole;
use App\Enum\MemorialCause;
use App\Enum\Race;
use App\Service\Graveyard\GraveyardPresenter;
use App\Service\Graveyard\GraveyardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/graveyard')]
#[IsGranted('ROLE_PLAYER')]
class GraveyardController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly GraveyardPresenter $graveyardPresenter,
        private readonly GraveyardService $graveyardService,
    ) {
    }

    #[Route('', name: 'api_graveyard_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->jsonError('error.no_team', 400);
        }

        $role = $this->parseEnum($request->query->get('role'), HeroRole::class);
        $cause = $this->parseEnum($request->query->get('cause'), MemorialCause::class);
        $race = $this->parseEnum($request->query->get('race'), Race::class);

        $search = $request->query->get('search');
        $search = is_string($search) ? trim($search) : null;
        if ('' === $search) {
            $search = null;
        }

        return $this->json([
            'summary' => $this->graveyardPresenter->presentSummary($team),
            'records' => $this->graveyardPresenter->presentListForTeam($team, $role, $cause, $race, $search),
        ]);
    }

    #[Route('/{id}', name: 'api_graveyard_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->jsonError('error.no_team', 400);
        }

        $memorial = $this->graveyardPresenter->findForTeam($id, $team);
        if (!$memorial) {
            return $this->jsonError('error.memorial_not_found', 404);
        }

        return $this->json($this->graveyardService->serializeMemorial($memorial));
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enumClass
     *
     * @return T|null
     */
    private function parseEnum(mixed $value, string $enumClass): mixed
    {
        if (!is_string($value) || '' === $value) {
            return null;
        }

        return $enumClass::tryFrom($value);
    }
}
