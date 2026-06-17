<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Entity\Auth\User;
use App\Entity\Community\ForumThread;
use App\Repository\Community\ForumThreadRepository;
use App\Service\Community\CommunityService;
use App\Service\Community\ForumThreadHelper;
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
    use ApiControllerTrait;

    public function __construct(
        private readonly ForumThreadRepository $threadRepository,
        private readonly CommunityService $communityService,
        private readonly ForumThreadHelper $threadHelper,
    ) {
    }

    #[Route('/threads', name: 'api_forum_threads_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $kingdom = $user->getKingdom();
        if (null === $kingdom) {
            return $this->jsonError('error.no_kingdom', 400);
        }

        $category = $request->query->get('category');
        $search = trim($request->query->getString('q', ''));

        $threads = $this->threadRepository->findForKingdomListing(
            $kingdom,
            is_string($category) && 'all' !== $category ? $category : null,
            '' !== $search ? $search : null,
        );
        $threads = $this->threadHelper->sortForListing($threads);

        $data = array_map(
            fn (ForumThread $thread): array => $this->threadHelper->serializeThread($thread),
            $threads
        );

        return $this->json($data);
    }

    #[Route('/threads', name: 'api_forum_threads_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $kingdom = $user->getKingdom();
        if (null === $kingdom) {
            return $this->jsonError('error.no_kingdom', 400);
        }

        $content = json_decode($request->getContent(), true) ?? [];
        $title = trim($content['title'] ?? '');
        $body = trim($content['body'] ?? '');
        $category = trim($content['category'] ?? '');

        if ('' === $title || '' === $body || '' === $category) {
            return $this->jsonError('error.thread_fields_required', 400);
        }

        try {
            $thread = $this->communityService->createThread(
                $user,
                $kingdom,
                $category,
                $title,
                $body
            );

            return $this->json($this->threadHelper->serializeThread($thread), Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 400);
        }
    }

    #[Route('/threads/{id}', name: 'api_forum_threads_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $kingdom = $user->getKingdom();
        if (null === $kingdom) {
            return $this->jsonError('error.no_kingdom', 400);
        }

        /** @var ForumThread|null $thread */
        $thread = $this->threadRepository->find($id);
        if (!$thread) {
            return $this->jsonError('error.thread_not_found', 404);
        }

        if ($thread->getKingdom() !== $kingdom) {
            return $this->jsonError('error.access_denied', 403);
        }

        $postsData = [];
        foreach ($this->threadHelper->getSortedPosts($thread) as $post) {
            $postsData[] = $this->threadHelper->serializePost($post);
        }

        $threadData = $this->threadHelper->serializeThread($thread);
        $threadData['posts'] = $postsData;

        return $this->json($threadData);
    }

    #[Route('/threads/{id}/posts', name: 'api_forum_posts_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reply(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        /** @var ForumThread|null $thread */
        $thread = $this->threadRepository->find($id);
        if (!$thread) {
            return $this->jsonError('error.thread_not_found', 404);
        }

        $content = json_decode($request->getContent(), true) ?? [];
        $body = trim($content['body'] ?? '');

        if ('' === $body) {
            return $this->jsonError('error.post_body_empty', 400);
        }

        try {
            $post = $this->communityService->createPost($user, $thread, $body);

            return $this->json($this->threadHelper->serializePost($post), Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 400);
        }
    }

    #[Route('/threads/{id}/lock', name: 'api_forum_threads_lock', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function lock(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        /** @var ForumThread|null $thread */
        $thread = $this->threadRepository->find($id);
        if (!$thread) {
            return $this->jsonError('error.thread_not_found', 404);
        }

        $content = json_decode($request->getContent(), true) ?? [];
        $lock = (bool) ($content['lock'] ?? true);

        try {
            $this->communityService->lockThread($user, $thread, $lock);

            return $this->json(['success' => true, 'isLocked' => $thread->isLocked()]);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 400);
        }
    }
}
