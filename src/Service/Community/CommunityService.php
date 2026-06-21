<?php

declare(strict_types=1);

namespace App\Service\Community;

use App\Entity\Auth\User;
use App\Entity\Community\ForumPost;
use App\Entity\Community\ForumThread;
use App\Entity\Community\Message;
use App\Entity\Kingdom\Kingdom;
use App\Exception\UserFacingException;
use Doctrine\ORM\EntityManagerInterface;

class CommunityService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ContentFilterService $filterService,
    ) {
    }

    /**
     * Create a new forum thread and its first post.
     */
    public function createThread(
        User $author,
        Kingdom $kingdom,
        string $category,
        string $title,
        string $body,
    ): ForumThread {
        if ($author->getKingdom() !== $kingdom) {
            throw new UserFacingException('error.community_own_kingdom_threads');
        }

        $filteredTitle = $this->filterService->filterContent($title);
        $filteredBody = $this->filterService->filterContent($body);
        $authorTeam = $author->getTeam();

        $thread = new ForumThread();
        $thread->setKingdom($kingdom);
        $thread->setAuthorUser($author);
        $thread->setAuthorTeam($authorTeam);
        $thread->setCategory($category);
        $thread->setTitle($filteredTitle);

        $post = new ForumPost();
        $post->setThread($thread);
        $post->setAuthorUser($author);
        $post->setAuthorTeam($authorTeam);
        $post->setBody($filteredBody);

        $thread->getPosts()->add($post);

        $this->em->persist($thread);
        $this->em->persist($post);
        $this->em->flush();

        return $thread;
    }

    /**
     * Add a reply post to a thread.
     */
    public function createPost(User $author, ForumThread $thread, string $body): ForumPost
    {
        if ($thread->isLocked()) {
            throw new UserFacingException('error.community_thread_locked');
        }

        if ($author->getKingdom() !== $thread->getKingdom()) {
            throw new UserFacingException('error.community_own_kingdom_replies');
        }

        $filteredBody = $this->filterService->filterContent($body);
        $authorTeam = $author->getTeam();

        $post = new ForumPost();
        $post->setThread($thread);
        $post->setAuthorUser($author);
        $post->setAuthorTeam($authorTeam);
        $post->setBody($filteredBody);

        $thread->getPosts()->add($post);

        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }

    /**
     * Send a private mail message to another player in the same kingdom.
     */
    public function sendMessage(User $sender, User $receiver, string $subject, string $body): Message
    {
        if ($sender === $receiver) {
            throw new UserFacingException('error.community_cannot_message_self');
        }

        if ($sender->getKingdom() !== $receiver->getKingdom()) {
            throw new UserFacingException('error.community_own_kingdom_messages');
        }

        $filteredSubject = $this->filterService->filterContent($subject);
        $filteredBody = $this->filterService->filterContent($body);

        $message = new Message();
        $message->setSenderUser($sender);
        $message->setReceiverUser($receiver);
        $message->setSenderTeam($sender->getTeam());
        $message->setReceiverTeam($receiver->getTeam());
        $message->setSubject($filteredSubject);
        $message->setBody($filteredBody);

        $this->em->persist($message);
        $this->em->flush();

        return $message;
    }

    /**
     * Count unread inbox messages for a player.
     */
    public function countUnreadInbox(User $user): int
    {
        return (int) $this->em->getRepository(Message::class)->count([
            'receiverUser' => $user,
            'deletedByReceiver' => false,
            'readAt' => null,
        ]);
    }

    /**
     * Get players in the same kingdom that can receive mail (excluding self).
     *
     * @return array<int, array{id: int|null, display_name: string, team_name: string|null}>
     */
    public function getMessageRecipients(User $user): array
    {
        $kingdom = $user->getKingdom();
        if (null === $kingdom) {
            return [];
        }

        /** @var array<int, User> $users */
        $users = $this->em->getRepository(User::class)->createQueryBuilder('u')
            ->leftJoin('u.team', 't')->addSelect('t')
            ->where('u.kingdom = :kingdom')
            ->andWhere('u.id != :userId')
            ->andWhere('u.isVerified = true')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('userId', $user->getId())
            ->orderBy('u.displayName', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (User $recipient): array => [
                'id' => $recipient->getId(),
                'display_name' => $recipient->getDisplayName(),
                'team_name' => $recipient->getTeam()?->getName(),
            ],
            $users
        );
    }

    /**
     * Get inbox messages for a player.
     *
     * @return array<int, Message>
     */
    public function getInboxMessages(User $user, ?int $page = null, ?int $limit = null): array
    {
        $offset = (null !== $page && null !== $limit) ? ($page - 1) * $limit : null;

        /** @var array<int, Message> $result */
        $result = $this->em->getRepository(Message::class)->findBy(
            ['receiverUser' => $user, 'deletedByReceiver' => false],
            ['id' => 'DESC'],
            $limit,
            $offset
        );

        return $result;
    }

    /**
     * Count inbox messages for a player.
     */
    public function countInboxMessages(User $user): int
    {
        return $this->em->getRepository(Message::class)->count([
            'receiverUser' => $user,
            'deletedByReceiver' => false,
        ]);
    }

    /**
     * Get sent messages for a player.
     *
     * @return array<int, Message>
     */
    public function getSentMessages(User $user, ?int $page = null, ?int $limit = null): array
    {
        $offset = (null !== $page && null !== $limit) ? ($page - 1) * $limit : null;

        /** @var array<int, Message> $result */
        $result = $this->em->getRepository(Message::class)->findBy(
            ['senderUser' => $user, 'deletedBySender' => false],
            ['id' => 'DESC'],
            $limit,
            $offset
        );

        return $result;
    }

    /**
     * Count sent messages for a player.
     */
    public function countSentMessages(User $user): int
    {
        return $this->em->getRepository(Message::class)->count([
            'senderUser' => $user,
            'deletedBySender' => false,
        ]);
    }

    /**
     * Soft delete a message for a player.
     */
    public function deleteMessage(User $user, int $messageId): void
    {
        /** @var Message|null $message */
        $message = $this->em->getRepository(Message::class)->find($messageId);
        if (!$message) {
            throw new UserFacingException('error.message_not_found');
        }

        $updated = false;

        if ($message->getSenderUser() === $user) {
            $message->setDeletedBySender(true);
            $updated = true;
        }

        if ($message->getReceiverUser() === $user) {
            $message->setDeletedByReceiver(true);
            $updated = true;
        }

        if (!$updated) {
            throw new UserFacingException('error.community_cannot_delete_message');
        }

        if ($message->isDeletedBySender() && $message->isDeletedByReceiver()) {
            $this->em->remove($message);
        }

        $this->em->flush();
    }

    /**
     * Mark a message as read.
     */
    public function markMessageAsRead(Message $message): void
    {
        if (null === $message->getReadAt()) {
            $message->setReadAt(new \DateTimeImmutable('now'));
            $this->em->flush();
        }
    }

    /**
     * Lock or unlock a thread.
     */
    public function lockThread(User $actor, ForumThread $thread, bool $lock): void
    {
        if ($thread->getAuthorUser() !== $actor) {
            throw new UserFacingException('error.community_thread_lock_permission');
        }

        $thread->setIsLocked($lock);
        $this->em->flush();
    }

    public function findMessageRecipient(User $sender, int $receiverUserId): User
    {
        if ($receiverUserId <= 0) {
            throw new UserFacingException('error.recipient_not_found');
        }

        /** @var User|null $receiver */
        $receiver = $this->em->getRepository(User::class)->find($receiverUserId);
        if (!$receiver) {
            throw new UserFacingException('error.recipient_not_found');
        }

        return $receiver;
    }

    public function getMessageForUser(User $user, int $messageId): Message
    {
        /** @var Message|null $message */
        $message = $this->em->getRepository(Message::class)->find($messageId);
        if (!$message) {
            throw new UserFacingException('error.message_not_found');
        }

        $isSender = $message->getSenderUser() === $user && !$message->isDeletedBySender();
        $isReceiver = $message->getReceiverUser() === $user && !$message->isDeletedByReceiver();

        if (!$isSender && !$isReceiver) {
            throw new UserFacingException('error.access_denied');
        }

        if ($isReceiver) {
            $this->markMessageAsRead($message);
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeMessage(Message $message, ForumThreadHelper $authorHelper): array
    {
        return [
            'id' => $message->getId(),
            'subject' => $message->getSubject(),
            'body' => $message->getBody(),
            'sent_at' => $message->getSentAt()->format(\DateTimeInterface::ATOM),
            'read_at' => $message->getReadAt()?->format(\DateTimeInterface::ATOM),
            'sender' => $authorHelper->serializeAuthor(
                $message->getSenderUser(),
                $message->getSenderTeam(),
            ),
            'receiver' => $authorHelper->serializeAuthor(
                $message->getReceiverUser(),
                $message->getReceiverTeam(),
            ),
        ];
    }
}
