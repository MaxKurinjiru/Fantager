<?php

declare(strict_types=1);

namespace App\Service\Community;

use App\Entity\Auth\User;
use App\Entity\Community\ForumPost;
use App\Entity\Community\ForumThread;
use App\Entity\Team\Team;

final class ForumThreadHelper
{
    public function getLastPostAt(ForumThread $thread): \DateTimeImmutable
    {
        $last = $thread->getCreatedAt();

        foreach ($thread->getPosts() as $post) {
            if ($post->getCreatedAt() > $last) {
                $last = $post->getCreatedAt();
            }
        }

        return $last;
    }

    public function getFirstPost(ForumThread $thread): ?ForumPost
    {
        $posts = $thread->getPosts()->toArray();

        if ([] === $posts) {
            return null;
        }

        usort(
            $posts,
            static fn (ForumPost $a, ForumPost $b): int => $a->getCreatedAt() <=> $b->getCreatedAt()
        );

        return $posts[0];
    }

    public function getPreview(ForumThread $thread, int $maxLength = 140): string
    {
        $firstPost = $this->getFirstPost($thread);
        if (null === $firstPost) {
            return '';
        }

        $body = trim(preg_replace('/\s+/u', ' ', $firstPost->getBody()) ?? '');

        if (mb_strlen($body) <= $maxLength) {
            return $body;
        }

        return mb_substr($body, 0, $maxLength - 1).'…';
    }

    public function getRepliesCount(ForumThread $thread): int
    {
        return max(0, $thread->getPosts()->count() - 1);
    }

    /** @return list<ForumPost> */
    public function getSortedPosts(ForumThread $thread): array
    {
        $posts = $thread->getPosts()->toArray();

        usort(
            $posts,
            static fn (ForumPost $a, ForumPost $b): int => $a->getCreatedAt() <=> $b->getCreatedAt()
        );

        return $posts;
    }

    /**
     * @param list<ForumThread> $threads
     *
     * @return list<ForumThread>
     */
    public function sortForListing(array $threads): array
    {
        usort(
            $threads,
            function (ForumThread $a, ForumThread $b): int {
                if ($a->isPinned() !== $b->isPinned()) {
                    return $b->isPinned() <=> $a->isPinned();
                }

                return $this->getLastPostAt($b) <=> $this->getLastPostAt($a);
            }
        );

        return $threads;
    }

    /** @return array<string, mixed> */
    public function serializeTeam(?Team $team): ?array
    {
        if (null === $team) {
            return null;
        }

        return [
            'id' => $team->getId(),
            'name' => $team->getName(),
            'emblem' => $team->getEmblem() ?: '🛡️',
            'colors' => $team->getColors(),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeAuthor(User $user, ?Team $team): array
    {
        return [
            'id' => $user->getId(),
            'display_name' => $user->getDisplayName(),
            'team' => $this->serializeTeam($team),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeThread(ForumThread $thread): array
    {
        return [
            'id' => $thread->getId(),
            'category' => $thread->getCategory(),
            'title' => $thread->getTitle(),
            'createdAt' => $thread->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'lastPostAt' => $this->getLastPostAt($thread)->format(\DateTimeInterface::ATOM),
            'isLocked' => $thread->isLocked(),
            'isPinned' => $thread->isPinned(),
            'preview' => $this->getPreview($thread),
            'author' => $this->serializeAuthor($thread->getAuthorUser(), $thread->getAuthorTeam()),
            'posts_count' => $thread->getPosts()->count(),
            'replies_count' => $this->getRepliesCount($thread),
        ];
    }

    /** @return array<string, mixed> */
    public function serializePost(ForumPost $post): array
    {
        return [
            'id' => $post->getId(),
            'body' => $post->getBody(),
            'createdAt' => $post->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'author' => $this->serializeAuthor($post->getAuthorUser(), $post->getAuthorTeam()),
        ];
    }
}
