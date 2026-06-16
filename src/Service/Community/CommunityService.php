<?php

declare(strict_types=1);

namespace App\Service\Community;

use App\Entity\Community\ForumPost;
use App\Entity\Community\ForumThread;
use App\Entity\Community\Message;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
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
        Team $author,
        Kingdom $kingdom,
        string $category,
        string $title,
        string $body,
    ): ForumThread {
        if ($author->getKingdom() !== $kingdom) {
            throw new \DomainException('You can only post threads in your own kingdom.');
        }

        $filteredTitle = $this->filterService->filterContent($title);
        $filteredBody = $this->filterService->filterContent($body);

        $thread = new ForumThread();
        $thread->setKingdom($kingdom);
        $thread->setAuthorTeam($author);
        $thread->setCategory($category);
        $thread->setTitle($filteredTitle);

        $post = new ForumPost();
        $post->setThread($thread);
        $post->setAuthorTeam($author);
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
    public function createPost(Team $author, ForumThread $thread, string $body): ForumPost
    {
        if ($thread->isLocked()) {
            throw new \DomainException('This thread is locked.');
        }

        if ($author->getKingdom() !== $thread->getKingdom()) {
            throw new \DomainException('You can only post replies in your own kingdom\'s board.');
        }

        $filteredBody = $this->filterService->filterContent($body);

        $post = new ForumPost();
        $post->setThread($thread);
        $post->setAuthorTeam($author);
        $post->setBody($filteredBody);

        $thread->getPosts()->add($post);

        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }

    /**
     * Send a private mail message to another team in the same kingdom.
     */
    public function sendMessage(Team $sender, Team $receiver, string $subject, string $body): Message
    {
        if ($sender === $receiver) {
            throw new \DomainException('You cannot send a message to yourself.');
        }

        if ($sender->getKingdom() !== $receiver->getKingdom()) {
            throw new \DomainException('You can only message teams in your own kingdom.');
        }

        $filteredSubject = $this->filterService->filterContent($subject);
        $filteredBody = $this->filterService->filterContent($body);

        $message = new Message();
        $message->setSenderTeam($sender);
        $message->setReceiverTeam($receiver);
        $message->setSubject($filteredSubject);
        $message->setBody($filteredBody);

        $this->em->persist($message);
        $this->em->flush();

        return $message;
    }

    /**
     * Count unread inbox messages for a team.
     */
    public function countUnreadInbox(Team $team): int
    {
        return (int) $this->em->getRepository(Message::class)->count([
            'receiverTeam' => $team,
            'deletedByReceiver' => false,
            'readAt' => null,
        ]);
    }

    /**
     * Get teams in the same kingdom that can receive mail (excluding self and NPCs).
     *
     * @return array<int, array{id: int|null, name: string}>
     */
    public function getMessageRecipients(Team $team): array
    {
        $kingdom = $team->getKingdom();

        /** @var array<int, Team> $teams */
        $teams = $this->em->getRepository(Team::class)->createQueryBuilder('t')
            ->where('t.kingdom = :kingdom')
            ->andWhere('t.id != :teamId')
            ->andWhere('t.isNpc = false')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('teamId', $team->getId())
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (Team $recipient): array => [
                'id' => $recipient->getId(),
                'name' => $recipient->getName(),
            ],
            $teams
        );
    }

    /**
     * Get inbox messages for a team.
     *
     * @return array<int, Message>
     */
    public function getInboxMessages(Team $team): array
    {
        /** @var array<int, Message> $result */
        $result = $this->em->getRepository(Message::class)->findBy(
            ['receiverTeam' => $team, 'deletedByReceiver' => false],
            ['id' => 'DESC']
        );

        return $result;
    }

    /**
     * Get sent messages for a team.
     *
     * @return array<int, Message>
     */
    public function getSentMessages(Team $team): array
    {
        /** @var array<int, Message> $result */
        $result = $this->em->getRepository(Message::class)->findBy(
            ['senderTeam' => $team, 'deletedBySender' => false],
            ['id' => 'DESC']
        );

        return $result;
    }

    /**
     * Soft delete a message for a team.
     */
    public function deleteMessage(Team $team, int $messageId): void
    {
        /** @var Message|null $message */
        $message = $this->em->getRepository(Message::class)->find($messageId);
        if (!$message) {
            throw new \DomainException('Message not found.');
        }

        $updated = false;

        if ($message->getSenderTeam() === $team) {
            $message->setDeletedBySender(true);
            $updated = true;
        }

        if ($message->getReceiverTeam() === $team) {
            $message->setDeletedByReceiver(true);
            $updated = true;
        }

        if (!$updated) {
            throw new \DomainException('You do not have permission to delete this message.');
        }

        // If deleted by both parties, we can completely remove it from the DB
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
    public function lockThread(Team $actor, ForumThread $thread, bool $lock): void
    {
        if ($thread->getAuthorTeam() !== $actor) {
            throw new \DomainException('Only the thread author can modify its lock status.');
        }

        $thread->setIsLocked($lock);
        $this->em->flush();
    }
}
