<?php

declare(strict_types=1);

namespace App\Tests\Service\Community;

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
        $thread = $this->createThread(false, '2024-01-01', [str_repeat('a', 200)]);

        self::assertSame(140, mb_strlen($this->helper->getPreview($thread)));
        self::assertStringEndsWith('…', $this->helper->getPreview($thread));
    }

    /** @param list<string> $postDates */
    private function createThread(bool $pinned, string $threadDate, array $postDates): ForumThread
    {
        $team = $this->createMock(Team::class);
        $team->method('getId')->willReturn(1);
        $team->method('getName')->willReturn('Test Team');
        $team->method('getEmblem')->willReturn('🛡️');
        $team->method('getColors')->willReturn(['primary' => '#10b981', 'secondary' => '#0f1720']);

        $thread = new ForumThread();
        $thread->setIsPinned($pinned);

        $reflection = new \ReflectionClass($thread);
        $createdAt = $reflection->getProperty('createdAt');
        $createdAt->setAccessible(true);
        $createdAt->setValue($thread, new \DateTimeImmutable($threadDate));

        foreach ($postDates as $index => $date) {
            $post = new ForumPost();
            $post->setAuthorTeam($team);
            $post->setBody('Body '.$index);
            $post->setThread($thread);

            $postReflection = new \ReflectionClass($post);
            $postCreatedAt = $postReflection->getProperty('createdAt');
            $postCreatedAt->setAccessible(true);
            $postCreatedAt->setValue($post, new \DateTimeImmutable($date));

            $thread->getPosts()->add($post);
        }

        return $thread;
    }
}
