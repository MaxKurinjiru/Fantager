<?php

declare(strict_types=1);

namespace App\Tests\Service\Community;

use App\Entity\Auth\User;
use App\Entity\Community\ForumPost;
use App\Entity\Community\ForumThread;
use App\Entity\Team\Team;
use App\Service\Community\ForumThreadHelper;
use PHPUnit\Framework\TestCase;

final class ForumThreadHelperTest extends TestCase
{
    private ForumThreadHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new ForumThreadHelper();
    }

    public function testSortForListingPinsThreadsFirstAndUsesLastActivity(): void
    {
        $olderPinned = $this->createThread(true, '2024-01-01', ['2024-01-02']);
        $newerRegular = $this->createThread(false, '2024-02-01', ['2024-02-05']);
        $olderRegular = $this->createThread(false, '2024-01-10', ['2024-01-11']);

        $sorted = $this->helper->sortForListing([$olderRegular, $newerRegular, $olderPinned]);

        self::assertSame($olderPinned, $sorted[0]);
        self::assertSame($newerRegular, $sorted[1]);
        self::assertSame($olderRegular, $sorted[2]);
    }

    public function testGetRepliesCountExcludesOriginalPost(): void
    {
        $thread = $this->createThread(false, '2024-01-01', ['2024-01-01', '2024-01-02']);

        self::assertSame(1, $this->helper->getRepliesCount($thread));
    }

    public function testGetPreviewTruncatesLongBody(): void
    {
        $thread = $this->createThread(false, '2024-01-01', ['2024-01-01'], [str_repeat('a', 200)]);

        self::assertSame(140, mb_strlen($this->helper->getPreview($thread)));
        self::assertStringEndsWith('…', $this->helper->getPreview($thread));
    }

    public function testSerializeAuthorIncludesTeamWhenPresent(): void
    {
        $user = new User();
        $user->setDisplayName('Player One');

        $team = new Team();
        $team->setName('Dragons FC');
        $team->setEmblem('🐉');
        $team->setColors(['primary' => '#10b981', 'secondary' => '#0f1720']);

        $serialized = $this->helper->serializeAuthor($user, $team);

        self::assertSame('Player One', $serialized['display_name']);
        self::assertSame('Dragons FC', $serialized['team']['name']);
    }

    public function testSerializeAuthorOmitsTeamWhenAbsent(): void
    {
        $user = new User();
        $user->setDisplayName('Player One');

        $serialized = $this->helper->serializeAuthor($user, null);

        self::assertSame('Player One', $serialized['display_name']);
        self::assertNull($serialized['team']);
    }

    /** @param list<string> $postDates @param list<string> $postBodies */
    private function createThread(bool $pinned, string $threadDate, array $postDates, ?array $postBodies = null): ForumThread
    {
        $user = new User();
        $user->setDisplayName('Test Player');

        $team = new Team();
        $team->setName('Test Team');
        $team->setEmblem('🛡️');
        $team->setColors(['primary' => '#10b981', 'secondary' => '#0f1720']);

        $thread = new ForumThread();
        $thread->setIsPinned($pinned);
        $thread->setAuthorUser($user);
        $thread->setAuthorTeam($team);

        $reflection = new \ReflectionClass($thread);
        $createdAt = $reflection->getProperty('createdAt');
        $createdAt->setValue($thread, new \DateTimeImmutable($threadDate));

        foreach ($postDates as $index => $date) {
            $post = new ForumPost();
            $post->setAuthorUser($user);
            $post->setAuthorTeam($team);
            $post->setBody($postBodies[$index] ?? ('Body '.$index));
            $post->setThread($thread);

            $postReflection = new \ReflectionClass($post);
            $postCreatedAt = $postReflection->getProperty('createdAt');
            $postCreatedAt->setValue($post, new \DateTimeImmutable($date));

            $thread->getPosts()->add($post);
        }

        return $thread;
    }
}
