<?php

declare(strict_types=1);

namespace App\Tests\Service\Community;

use App\Entity\Auth\User;
use App\Entity\Community\ForumThread;
use App\Entity\Kingdom\Kingdom;
use App\Exception\UserFacingException;
use App\Service\Community\CommunityService;
use App\Service\Community\ContentFilterService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CommunityServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&EntityManagerInterface */
    private $entityManagerMock;
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
        $author = new User();
        $authorKingdom = new Kingdom();
        $author->setKingdom($authorKingdom);

        $otherKingdom = new Kingdom();

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.community_own_kingdom_threads');

        $this->communityService->createThread($author, $otherKingdom, 'general', 'Title', 'Body');
    }

    public function testCreatePostRejectsLockedThread(): void
    {
        $author = new User();
        $kingdom = new Kingdom();
        $author->setKingdom($kingdom);

        $thread = new ForumThread();
        $thread->setKingdom($kingdom);
        $thread->setIsLocked(true);

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.community_thread_locked');

        $this->communityService->createPost($author, $thread, 'Reply body');
    }

    public function testSendMessageRejectsSelfMessage(): void
    {
        $user = new User();
        $kingdom = new Kingdom();
        $user->setKingdom($kingdom);

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.community_cannot_message_self');

        $this->communityService->sendMessage($user, $user, 'Hello', 'Body');
    }
}
