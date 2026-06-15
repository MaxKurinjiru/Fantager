<?php

declare(strict_types=1);

namespace App\Tests\Service\Community;

use App\Entity\Community\ForumThread;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Service\Community\CommunityService;
use App\Service\Community\ContentFilterService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CommunityServiceTest extends TestCase
{
    private EntityManagerInterface $entityManagerMock;
    private ContentFilterService $filterService;
    private CommunityService $communityService;

    protected function setUp(): void
    {
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->filterService = new ContentFilterService();
        $this->communityService = new CommunityService($this->entityManagerMock, $this->filterService);
    }

    public function testCreateThreadRejectsCrossKingdomAuthor(): void
    {
        $author = new Team();
        $authorKingdom = new Kingdom();
        $author->setKingdom($authorKingdom);

        $otherKingdom = new Kingdom();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('You can only post threads in your own kingdom.');

        $this->communityService->createThread($author, $otherKingdom, 'general', 'Title', 'Body');
    }

    public function testCreatePostRejectsLockedThread(): void
    {
        $author = new Team();
        $kingdom = new Kingdom();
        $author->setKingdom($kingdom);

        $thread = new ForumThread();
        $thread->setKingdom($kingdom);
        $thread->setIsLocked(true);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('This thread is locked.');

        $this->communityService->createPost($author, $thread, 'Reply body');
    }

    public function testSendMessageRejectsSelfMessage(): void
    {
        $team = new Team();
        $kingdom = new Kingdom();
        $team->setKingdom($kingdom);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('You cannot send a message to yourself.');

        $this->communityService->sendMessage($team, $team, 'Hello', 'Body');
    }
}
