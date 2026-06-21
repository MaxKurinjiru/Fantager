# Fantager Backend — Examples

Real patterns from this codebase. **Bad** = do not ship. **Good** = target state.

Reference implementations: `SummoningService`, `Api\V1\SummoningController`, `Web\HeroController`, `tests/Service/Summoning/SummoningServiceTest.php`.

---

## 1. API GET endpoint

**Bad** — business rules and English errors in the controller

```php
#[Route('/api/v1/foo', methods: ['GET'])]
public function list(): JsonResponse
{
    $user = $this->getUser();
    if (!$user->getTeam()) {
        return $this->json(['error' => 'You have no team'], 400);
    }
    $heroes = $this->em->getRepository(Hero::class)->findBy(['team' => $user->getTeam()]);
    // ... stat calculations duplicated here ...
    return $this->json($heroes);
}
```

**Good** — thin controller, trait, translation keys, service/repository

```php
#[Route('/api/v1')]
class SummoningController extends AbstractController
{
    use ApiControllerTrait;

    #[Route('/summoning/status', name: 'api_summoning_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        $status = $this->summoningService->getStatus($team);

        return $this->json([
            'available' => $status['available'],
            'reason' => $status['reason'],
            'gold_cost' => $status['gold_cost'],
            'summons_used' => $status['summons_used'],
            'summons_max' => $status['summons_max'],
        ]);
    }

    private function getPlayerTeam(): ?Team
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $user?->getTeam();
    }
}
```

Docs: add row to `docs/route-map.md`.

---

## 2. API POST action

**Bad** — catch-all with raw exception message exposed

```php
try {
    $this->doSummon($team);
} catch (\Exception $e) {
    return $this->json(['error' => $e->getMessage()], 500);
}
return $this->json(['ok' => true]);
```

**Good** — service throws translatable exceptions; controller maps to JSON

```php
#[Route('', name: 'api_summoning_summon', methods: ['POST'])]
public function summon(Request $request): JsonResponse
{
    $team = $this->getPlayerTeam();
    if (null === $team) {
        return $this->jsonError('error.no_team', 422);
    }

    try {
        $hero = $this->summoningService->summon($team);
    } catch (\DomainException $e) {
        return $this->jsonException($e, 422);
    }

    return $this->json($this->heroService->serialize($hero), 201);
}
```

---

## 3. Service business rules

**Bad** — controller-level validation only; no shared service

```php
// In WebController only
if ($team->getGold() < 500) {
    $this->addFlash('error', 'Not enough gold');
    return $this->redirectToRoute('app_summoning');
}
```

**Good** — `UserFacingException` in service; used by Web and API

```php
public function summon(Team $team): Hero
{
    $rosterLimit = $this->hqService->getRosterLimit($team);
    $heroCount = $this->em->getRepository(Hero::class)->count(['team' => $team]);
    if ($heroCount >= $rosterLimit) {
        throw new UserFacingException('error.summoning_roster_full');
    }

    $this->financialCrisisService->assertSpendingAllowed($team, 'summon');

    $cost = $this->getGoldCost($team);
    $this->economyService->deductGold(
        $team,
        $cost,
        FinancialRecordType::SummonFee,
        FinancialRecordActor::Active,
    );
    // ... persist hero, chronicle, flush ...
    return $hero;
}
```

Register `error.summoning_roster_full` in `translations/messages.en.yaml` and `messages.cs.yaml`.

---

## 4. Web controller

**Bad** — hardcoded flash string; duplicated summon logic

```php
public function index(): Response
{
    $team = $this->getUser()->getTeam();
    if ($team->getGold() < 500) {
        $this->addFlash('error', 'Insufficient gold for summoning');
    }
    return $this->render('summoning/index.html.twig', ['cost' => 500]);
}
```

**Good** — `UserMessageTranslator`, data from service, `IsGranted`

```php
#[IsGranted('ROLE_PLAYER')]
class HeroController extends AbstractController
{
    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly UserMessageTranslator $userMessages,
    ) {
    }

    #[Route('/app/heroes', name: 'app_heroes', methods: ['GET'])]
    public function roster(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', $this->userMessages->trans('error.no_team'));
            return $this->redirectToRoute('app_home');
        }

        return $this->render('hero/roster.html.twig', [
            'team' => $team,
            'heroes' => $this->heroRepository->findCombatantsByTeam($team),
        ]);
    }
}
```

---

## 5. Service unit test

**Bad** — kernel test for pure calculation logic

```php
class SummoningServiceTest extends KernelTestCase
{
    public function testCost(): void
    {
        self::bootKernel();
        // hits real database ...
    }
}
```

**Good** — `TestCase`, mocked dependencies, mirrors production namespace

```php
#[AllowMockObjectsWithoutExpectations]
class SummoningServiceTest extends TestCase
{
    private SummoningService $service;

    protected function setUp(): void
    {
        $this->service = new SummoningService(
            $this->createMock(HeroGenerator::class),
            $this->createMock(EconomyService::class),
            // ... other mocks ...
        );
    }

    public function testGetStatusWithZeroSummonsOnNormalSpeedIsAvailable(): void
    {
        $team = $this->createTeam(speed: '1.00', gold: 1000, summonsThisCycle: 0);
        // configure repository mocks ...
        $status = $this->service->getStatus($team);
        $this->assertTrue($status['available']);
    }
}
```

Path: `tests/Service/Summoning/SummoningServiceTest.php`.

---

## 6. Vertical slice checklist (new domain feature)

When adding e.g. a new `/api/v1/foo` + `/app/foo` screen:

1. `docs/systems/foo-system.md` + `docs/screens/NN-foo.md`
2. Entity/repository if needed → `entity-reference.md`
3. `src/Service/Foo/FooService.php` + `tests/Service/Foo/FooServiceTest.php`
4. `src/Controller/Api/V1/FooController.php` (`ApiControllerTrait`)
5. `src/Controller/Web/FooController.php` + Twig (see `fantager-ui` skill)
6. `docs/route-map.md` + `docs/README.md` status row
7. Stimulus only if the screen needs AJAX — call the API you added in step 4

Copy the nearest complete feature (summoning, training, marketplace) before inventing new structure.
