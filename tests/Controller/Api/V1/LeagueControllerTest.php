<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1;

use App\Controller\Api\V1\LeagueController;
use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueFixture;
use App\Entity\League\LeagueGroup;
use App\Entity\League\LeagueSeason;
use App\Entity\Team\Team;
use App\Enum\LeagueFixtureStatus;
use App\Enum\LeagueSeasonStatus;
use App\Repository\Kingdom\KingdomRepository;
use App\Service\League\LeagueService;
use App\Service\League\SeasonTransitionService;
use App\Service\Translation\UserMessageTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AllowMockObjectsWithoutExpectations]
class LeagueControllerTest extends TestCase
{
    /** @var LeagueService&MockObject */
    private $leagueService;
    /** @var SeasonTransitionService&MockObject */
    private $seasonTransitionService;
    /** @var KingdomRepository&MockObject */
    private $kingdomRepository;
    /** @var EntityManagerInterface&MockObject */
    private $em;
    private UserMessageTranslator $userMessages;
    private LeagueController $controller;

    protected function setUp(): void
    {
        $this->leagueService = $this->createMock(LeagueService::class);
        $this->seasonTransitionService = $this->createMock(SeasonTransitionService::class);
        $this->kingdomRepository = $this->createMock(KingdomRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);
        $this->userMessages = new UserMessageTranslator($translator, $this->createStub(RequestStack::class));

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => 'security.token_storage' === $id);
        $container->method('get')->willReturnCallback(static fn (string $id) => 'security.token_storage' === $id ? $tokenStorage : null);

        $this->controller = new LeagueController(
            $this->leagueService,
            $this->seasonTransitionService,
            $this->kingdomRepository,
            $this->em
        );
        $this->controller->setUserMessageTranslator($this->userMessages);
        $this->controller->setContainer($container);
    }

    public function testStandingsReturnsKingdomNotFoundWhenNoKingdom(): void
    {
        $request = new Request();

        $response = $this->controller->standings($request);

        $this->assertSame(404, $response->getStatusCode());
        $content = json_decode((string) $response->getContent(), true);
        $this->assertSame('error.kingdom_not_found', $content['error']);
    }

    public function testStandingsReturnsPayloadWhenKingdomExists(): void
    {
        $kingdom = new Kingdom();
        $this->kingdomRepository->method('find')->willReturn($kingdom);

        $season = new LeagueSeason();
        $season->setKingdom($kingdom);
        $season->setSeasonNumber(1);
        $season->setStartDate(new \DateTimeImmutable('2026-01-01'));
        $season->setEndDate(new \DateTimeImmutable('2026-03-31'));
        $season->setStatus(LeagueSeasonStatus::Active);

        $this->leagueService->method('getCurrentSeason')->willReturn($season);
        $this->leagueService->method('getGroupsForSeason')->willReturn([]);

        $request = new Request(['kingdomId' => '1']);
        $response = $this->controller->standings($request);

        $this->assertSame(200, $response->getStatusCode());
        $content = json_decode((string) $response->getContent(), true);
        $this->assertSame(1, $content['season']['season_number']);
        $this->assertSame(2000 + (int) $season->getStartDate()->format('y'), $content['season']['year']);
        $this->assertSame('active', $content['season']['status']);
        $this->assertIsArray($content['groups']);
        $this->assertIsArray($content['standings']);
    }

    public function testFixturesReturnsListForGroup(): void
    {
        $group = new LeagueGroup();
        $groupRepo = $this->createStub(EntityRepository::class);
        $groupRepo->method('find')->willReturn($group);

        $this->em->method('getRepository')->willReturn($groupRepo);

        $fixture = new LeagueFixture();
        $fixture->setGroup($group);
        $fixture->setStatus(LeagueFixtureStatus::Scheduled);
        $fixture->setScheduledAt(new \DateTimeImmutable('2026-08-01 10:00:00'));

        $homeTeam = new Team();
        $homeTeam->setName('Home Lions');
        $awayTeam = new Team();
        $awayTeam->setName('Away Tigers');

        $fixture->setHomeTeam($homeTeam);
        $fixture->setAwayTeam($awayTeam);

        $this->leagueService->method('getFixturesForGroup')->willReturn([$fixture]);

        $request = new Request(['groupId' => '5']);
        $response = $this->controller->fixtures($request);

        $this->assertSame(200, $response->getStatusCode());
        $content = json_decode((string) $response->getContent(), true);
        $this->assertCount(1, $content);
        $this->assertSame('Home Lions', $content[0]['home_team']['name']);
        $this->assertSame('Away Tigers', $content[0]['away_team']['name']);
    }

    public function testSeasonsReturnsKingdomSeasons(): void
    {
        $kingdom = new Kingdom();
        $this->kingdomRepository->method('find')->willReturn($kingdom);

        $season = new LeagueSeason();
        $season->setKingdom($kingdom);
        $season->setSeasonNumber(1);
        $season->setStartDate(new \DateTimeImmutable('2026-08-01'));
        $season->setEndDate(new \DateTimeImmutable('2026-10-31'));
        $season->setStatus(LeagueSeasonStatus::Active);

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturn([$season]);
        $this->em->method('getRepository')->willReturn($repository);

        $request = new Request(['kingdomId' => '2']);
        $response = $this->controller->seasons($request);

        $this->assertSame(200, $response->getStatusCode());
        $content = json_decode((string) $response->getContent(), true);
        $this->assertCount(1, $content);
        $this->assertSame(1, $content[0]['season_number']);
        $this->assertSame(2026, $content[0]['year']);
    }

    public function testProcessSeasonExecutesTransition(): void
    {
        $kingdom = new Kingdom();
        $this->kingdomRepository->method('find')->willReturn($kingdom);

        $this->seasonTransitionService->expects($this->once())->method('executeTransition')->with($kingdom);

        $content = json_encode(['kingdomId' => 1]);
        $request = new Request([], [], [], [], [], [], false !== $content ? $content : null);
        $response = $this->controller->processSeason($request);

        $this->assertSame(200, $response->getStatusCode());
        $content = json_decode((string) $response->getContent(), true);
        $this->assertTrue($content['success']);
    }
}
