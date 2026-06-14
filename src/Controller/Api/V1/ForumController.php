<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\Auth\User;
use App\Entity\Community\ForumThread;
use App\Service\Community\CommunityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/forum')]
#[IsGranted('ROLE_PLAYER')]
class ForumController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommunityService $communityService,
    ) {
    }

    #[Route('/threads', name: 'api_forum_threads_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->json(['error' => 'No team assigned.'], Response::HTTP_BAD_REQUEST);
        }

        $kingdom = $team->getKingdom();
        $category = $request->query->get('category');

        $qb = $this->em->createQueryBuilder();
        $qb->select('t')
           ->from(ForumThread::class, 't')
           ->where('t.kingdom = :kingdom')
           ->setParameter('kingdom', $kingdom);

        if ($category && 'all' !== $category) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $category);
        }

        $qb->orderBy('t.id', 'DESC');

        /** @var list<ForumThread> $threads */
        $threads = $qb->getQuery()->getResult();

        $data = array_map([$this, 'serializeThread'], $threads);

        return $this->json($data);
    }

    #[Route('/threads', name: 'api_forum_threads_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->json(['error' => 'No team assigned.'], Response::HTTP_BAD_REQUEST);
        }

        $content = json_decode($request->getContent(), true) ?? [];
        $title = trim($content['title'] ?? '');
        $body = trim($content['body'] ?? '');
        $category = trim($content['category'] ?? '');

        if ('' === $title || '' === $body || '' === $category) {
            return $this->json(['error' => 'Title, body and category are required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $thread = $this->communityService->createThread(
                $team,
                $team->getKingdom(),
                $category,
                $title,
                $body
            );

            return $this->json($this->serializeThread($thread), Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/threads/{id}', name: 'api_forum_threads_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->json(['error' => 'No team assigned.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var ForumThread|null $thread */
        $thread = $this->em->getRepository(ForumThread::class)->find($id);
        if (!$thread) {
            return $this->json(['error' => 'Thread not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($thread->getKingdom() !== $team->getKingdom()) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $postsData = [];
        foreach ($thread->getPosts() as $post) {
            $postsData[] = [
                'id' => $post->getId(),
                'body' => $post->getBody(),
                'createdAt' => $post->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'author_team' => [
                    'id' => $post->getAuthorTeam()->getId(),
                    'name' => $post->getAuthorTeam()->getName(),
                    'colors' => $post->getAuthorTeam()->getColors(),
                ],
            ];
        }

        $threadData = $this->serializeThread($thread);
        $threadData['posts'] = $postsData;

        return $this->json($threadData);
    }

    #[Route('/threads/{id}/posts', name: 'api_forum_posts_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reply(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->json(['error' => 'No team assigned.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var ForumThread|null $thread */
        $thread = $this->em->getRepository(ForumThread::class)->find($id);
        if (!$thread) {
            return $this->json(['error' => 'Thread not found.'], Response::HTTP_NOT_FOUND);
        }

        $content = json_decode($request->getContent(), true) ?? [];
        $body = trim($content['body'] ?? '');

        if ('' === $body) {
            return $this->json(['error' => 'Post body cannot be empty.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $post = $this->communityService->createPost($team, $thread, $body);

            return $this->json([
                'id' => $post->getId(),
                'body' => $post->getBody(),
                'createdAt' => $post->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'author_team' => [
                    'id' => $post->getAuthorTeam()->getId(),
                    'name' => $post->getAuthorTeam()->getName(),
                    'colors' => $post->getAuthorTeam()->getColors(),
                ],
            ], Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/threads/{id}/lock', name: 'api_forum_threads_lock', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function lock(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->json(['error' => 'No team assigned.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var ForumThread|null $thread */
        $thread = $this->em->getRepository(ForumThread::class)->find($id);
        if (!$thread) {
            return $this->json(['error' => 'Thread not found.'], Response::HTTP_NOT_FOUND);
        }

        $content = json_decode($request->getContent(), true) ?? [];
        $lock = (bool) ($content['lock'] ?? true);

        try {
            $this->communityService->lockThread($team, $thread, $lock);

            return $this->json(['success' => true, 'isLocked' => $thread->isLocked()]);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /** @return array<string, mixed> */
    private function serializeThread(ForumThread $thread): array
    {
        return [
            'id' => $thread->getId(),
            'category' => $thread->getCategory(),
            'title' => $thread->getTitle(),
            'createdAt' => $thread->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'isLocked' => $thread->isLocked(),
            'author_team' => [
                'id' => $thread->getAuthorTeam()->getId(),
                'name' => $thread->getAuthorTeam()->getName(),
                'colors' => $thread->getAuthorTeam()->getColors(),
            ],
            'posts_count' => $thread->getPosts()->count(),
        ];
    }
}
