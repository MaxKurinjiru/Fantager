<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Repository\Community\NewsArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NewsController extends AbstractController
{
    private const PER_PAGE = 10;

    public function __construct(
        private readonly NewsArticleRepository $newsArticleRepository,
    ) {
    }

    #[Route('/news', name: 'app_news', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $total = $this->newsArticleRepository->countPublished();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        if ($page > $totalPages && $total > 0) {
            return $this->redirectToRoute('app_news', ['page' => $totalPages]);
        }

        return $this->render('news/index.html.twig', [
            'articles' => $this->newsArticleRepository->findPublishedPage($page, self::PER_PAGE),
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
        ]);
    }
}
