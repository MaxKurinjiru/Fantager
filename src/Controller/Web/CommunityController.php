<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Entity\Community\ForumThread;
use App\Repository\Community\ForumThreadRepository;
use App\Service\Community\ForumThreadHelper;
use App\Service\Translation\UserMessageTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class CommunityController extends AbstractController
{
    private const CATEGORIES = ['general', 'strategy', 'trading', 'bugs'];

    public function __construct(
        private readonly ForumThreadRepository $threadRepository,
        private readonly ForumThreadHelper $threadHelper,
        private readonly UserMessageTranslator $userMessages,
    ) {
    }

    #[Route('/app/community', name: 'app_community', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $kingdom = $user->getKingdom();

        if (null === $kingdom) {
            $this->addFlash('error', $this->userMessages->trans('error.no_kingdom'));

            return $this->redirectToRoute('app_home');
        }

        $activeCategory = $request->query->getString('category', 'all');
        if ('all' !== $activeCategory && !in_array($activeCategory, self::CATEGORIES, true)) {
            $activeCategory = 'all';
        }

        $search = trim($request->query->getString('q', ''));
        $threads = $this->threadRepository->findForKingdomListing(
            $kingdom,
            'all' === $activeCategory ? null : $activeCategory,
            '' !== $search ? $search : null,
        );
        $threads = $this->threadHelper->sortForListing($threads);

        $pinnedThreads = array_values(array_filter($threads, static fn (ForumThread $t): bool => $t->isPinned()));
        $regularThreads = array_values(array_filter($threads, static fn (ForumThread $t): bool => !$t->isPinned()));

        return $this->render('community/index.html.twig', [
            'team' => $user->getTeam(),
            'kingdom' => $kingdom,
            'threads' => $threads,
            'pinned_threads' => $pinnedThreads,
            'regular_threads' => $regularThreads,
            'categories' => self::CATEGORIES,
            'active_category' => $activeCategory,
            'search' => $search,
        ]);
    }

    #[Route('/app/community/threads/{id}', name: 'app_community_thread', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $kingdom = $user->getKingdom();

        if (null === $kingdom) {
            $this->addFlash('error', $this->userMessages->trans('error.no_kingdom'));

            return $this->redirectToRoute('app_home');
        }

        /** @var ForumThread|null $thread */
        $thread = $this->threadRepository->find($id);
        if (null === $thread || $thread->getKingdom() !== $kingdom) {
            throw new NotFoundHttpException('Thread not found.');
        }

        $posts = $this->threadHelper->getSortedPosts($thread);
        $originalPost = array_shift($posts);

        $activeCategory = $request->query->getString('category', 'all');
        if ('all' !== $activeCategory && !in_array($activeCategory, self::CATEGORIES, true)) {
            $activeCategory = 'all';
        }

        return $this->render('community/thread.html.twig', [
            'team' => $user->getTeam(),
            'thread' => $thread,
            'original_post' => $originalPost,
            'replies' => $posts,
            'active_category' => $activeCategory,
            'is_author' => $thread->getAuthorUser()->getId() === $user->getId(),
        ]);
    }
}
