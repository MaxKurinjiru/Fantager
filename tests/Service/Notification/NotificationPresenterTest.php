<?php

declare(strict_types=1);

namespace App\Tests\Service\Notification;

use App\Entity\Auth\User;
use App\Entity\Notification\Notification;
use App\Enum\NotificationType;
use App\Service\Notification\NotificationPresenter;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AllowMockObjectsWithoutExpectations]
class NotificationPresenterTest extends TestCase
{
    public function testPresentRendersKeysInRequestedLocale(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static function (string $id, array $params = [], ?string $domain = null, ?string $locale = null): string {
                if ('notification.marketplace_sold_title' === $id) {
                    return 'cs' === $locale ? 'Nabídka prodána' : 'Listing Sold';
                }
                if ('notification.marketplace_sold_body' === $id) {
                    $listing = $params['%listing%'] ?? '';
                    $buyer = $params['%buyer%'] ?? '';
                    $amount = $params['%amount%'] ?? '';

                    return 'cs' === $locale
                        ? sprintf('Nabídka #%s koupena hráčem %s za %s.', $listing, $buyer, $amount)
                        : sprintf('Listing #%s bought by %s for %s.', $listing, $buyer, $amount);
                }

                return $id;
            }
        );

        $notification = $this->baseNotification();
        $notification->setTitleKey('notification.marketplace_sold_title');
        $notification->setBodyKey('notification.marketplace_sold_body');
        $notification->setBodyParams([
            '%listing%' => 123,
            '%buyer%' => 'Alice',
            '%amount%' => 500,
        ]);

        $presenter = new NotificationPresenter($translator);

        $en = $presenter->present($notification, 'en');
        $cs = $presenter->present($notification, 'cs');

        $this->assertSame('Listing Sold', $en['title']);
        $this->assertSame('Listing #123 bought by Alice for 500.', $en['body']);
        $this->assertSame('Nabídka prodána', $cs['title']);
        $this->assertSame('Nabídka #123 koupena hráčem Alice za 500.', $cs['body']);
        $this->assertSame('marketplace_sold', $en['type']);
        $this->assertFalse($en['is_read']);
    }

    public function testPresentFallsBackToStoredStringsWhenKeysMissing(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->never())->method('trans');

        $notification = $this->baseNotification();
        $notification->setTitle('Frozen title');
        $notification->setBody('Frozen body');

        $presenter = new NotificationPresenter($translator);
        $presented = $presenter->present($notification, 'en');

        $this->assertSame('Frozen title', $presented['title']);
        $this->assertSame('Frozen body', $presented['body']);
    }

    public function testPresentTranslatesFacilityRaceAndSeasonStatus(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = [], ?string $domain = null, ?string $locale = null): string => match ($id) {
                'hq.facilities_list.library.name' => 'Library & Academy',
                'heroes.race_elf' => 'Elf',
                'activity.season_status.promoted' => 'promoted',
                'activity.season_status.maintained' => 'position maintained',
                'notification.hq_upgrade_body' => sprintf('Upgrade of "%s".', $params['%facility%'] ?? ''),
                'notification.hq_arena_adaptation_body' => sprintf('Adapted to %s.', $params['%race%'] ?? ''),
                'notification.season_ended_body' => sprintf('(%s)', $params['%status_text%'] ?? ''),
                default => $id,
            }
        );

        $presenter = new NotificationPresenter($translator);

        $hq = $this->baseNotification();
        $hq->setTitleKey('notification.hq_upgrade_title');
        $hq->setBodyKey('notification.hq_upgrade_body');
        $hq->setBodyParams(['facility' => 'library', 'level' => 3]);
        $this->assertSame('Upgrade of "Library & Academy".', $presenter->present($hq, 'en')['body']);

        $race = $this->baseNotification();
        $race->setTitleKey('notification.hq_arena_adaptation_title');
        $race->setBodyKey('notification.hq_arena_adaptation_body');
        $race->setBodyParams(['%race%' => 'elf']);
        $this->assertSame('Adapted to Elf.', $presenter->present($race, 'en')['body']);

        $season = $this->baseNotification();
        $season->setTitleKey('notification.season_ended_title');
        $season->setBodyKey('notification.season_ended_body');
        $season->setBodyParams(['%status_text%' => 'retained']);
        $this->assertSame('(position maintained)', $presenter->present($season, 'en')['body']);
    }

    private function baseNotification(): Notification
    {
        $notification = new Notification();
        $notification->setUser(new User());
        $notification->setType(NotificationType::MarketplaceSold);

        return $notification;
    }
}
