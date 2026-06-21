<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Repository\Community\NewsArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends AbstractController
{
    public function __construct(
        private readonly NewsArticleRepository $newsArticleRepository,
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'latest_news' => $this->newsArticleRepository->findLatestPublished(3),
        ]);
    }
}
