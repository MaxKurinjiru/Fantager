<?php

declare(strict_types=1);

namespace App\Tests\Service\TeamChronicle;

use App\Entity\Team\TeamChronicle;
use App\Entity\Team\Team;
use App\Enum\ChronicleCategory;
use App\Enum\ChronicleEventType;
use App\Repository\Team\TeamChronicleRepository;
use App\Service\TeamChronicle\TeamChroniclePresenter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AllowMockObjectsWithoutExpectations]
class TeamChroniclePresenterTest extends TestCase
{
    public function testPresentEntryTranslatesSeasonStatusAndRace(): void
    {
        $repository = $this->createMock(TeamChronicleRepository::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = [], ?string $domain = null, ?string $locale = null): string => match ($id) {
                'activity.type.season_ended' => 'Season ended',
                'activity.season_status.promoted' => 'promoted',
                'activity.season_ended' => sprintf(
                    'Season %s ended in tier %s (position %s, %s).',
                    $params['%season%'] ?? $params['season'] ?? '',
                    $params['%tier%'] ?? $params['tier'] ?? '',
                    $params['%position%'] ?? $params['position'] ?? '',
                    $params['%status%'] ?? $params['status'] ?? '',
                ),
                default => $id,
            }
        );

        $log = new TeamChronicle();
        $log->setTeam(new Team());
        $log->setType(ChronicleEventType::SeasonEnded);
        $log->setSubjectKey('activity.season_ended');
        $log->setSubjectParams([
            'season' => '1',
            'tier' => 'T1',
            'position' => '2',
            'status' => 'promoted',
        ]);
        $log->setData(['gold' => 500]);

        $presenter = new TeamChroniclePresenter($repository, $translator);
        $entry = $presenter->presentEntry($log, 'en');

        $this->assertSame('season_ended', $entry['type']);
        $this->assertSame('🏆', $entry['icon']);
        $this->assertSame('competition', $entry['category']);
        $this->assertStringContainsString('promoted', $entry['message']);
        $this->assertSame('Season 1 ended in tier T1 (position 2, promoted).', $entry['message']);
    }

    public function testPresentEntryTranslatesTeamEstablishedParams(): void
    {
        $repository = $this->createMock(TeamChronicleRepository::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = [], ?string $domain = null, ?string $locale = null): string => match ($id) {
                'activity.type.team_established' => 'Team established',
                'activity.team_established' => sprintf(
                    'Team established in kingdom %s (season %s).',
                    $params['%kingdom%'] ?? $params['kingdom'] ?? '',
                    $params['%season%'] ?? $params['season'] ?? '',
                ),
                default => $id,
            }
        );

        $log = new TeamChronicle();
        $log->setTeam(new Team());
        $log->setType(ChronicleEventType::TeamEstablished);
        $log->setSubjectKey('activity.team_established');
        $log->setSubjectParams([
            'kingdom' => 'Main Kingdom',
            'season' => '1',
        ]);

        $presenter = new TeamChroniclePresenter($repository, $translator);
        $entry = $presenter->presentEntry($log, 'en');

        $this->assertSame(
            'Team established in kingdom Main Kingdom (season 1).',
            $entry['message'],
        );
    }

    public function testResolveCategoryMapsOwnershipTypes(): void
    {
        $repository = $this->createMock(TeamChronicleRepository::class);
        $translator = $this->createMock(TranslatorInterface::class);

        $presenter = new TeamChroniclePresenter($repository, $translator);

        $this->assertSame(
            ChronicleCategory::Ownership,
            $presenter->resolveCategory(ChronicleEventType::PlayerJoined),
        );
        $this->assertSame(
            ChronicleCategory::Ownership,
            $presenter->resolveCategory(ChronicleEventType::TeamRenamed),
        );
        $this->assertSame(
            ChronicleCategory::Roster,
            $presenter->resolveCategory(ChronicleEventType::SummonCompleted),
        );
    }

    public function testPresentTeamRenamedEntry(): void
    {
        $repository = $this->createMock(TeamChronicleRepository::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = [], ?string $domain = null, ?string $locale = null): string => match ($id) {
                'activity.type.team_renamed' => 'Team renamed',
                'activity.team_renamed' => sprintf(
                    'Team was renamed from %s to %s.',
                    $params['%old_name%'] ?? $params['old_name'] ?? '',
                    $params['%new_name%'] ?? $params['new_name'] ?? '',
                ),
                default => $id,
            }
        );

        $log = new TeamChronicle();
        $log->setTeam(new Team());
        $log->setType(ChronicleEventType::TeamRenamed);
        $log->setSubjectKey('activity.team_renamed');
        $log->setSubjectParams([
            'old_name' => 'Old Name',
            'new_name' => 'New Name',
        ]);

        $presenter = new TeamChroniclePresenter($repository, $translator);
        $entry = $presenter->presentEntry($log, 'en');

        $this->assertSame('team_renamed', $entry['type']);
        $this->assertSame('🏷️', $entry['icon']);
        $this->assertSame('ownership', $entry['category']);
        $this->assertSame('Team was renamed from Old Name to New Name.', $entry['message']);
    }
}
