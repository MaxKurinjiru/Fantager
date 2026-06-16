<?php

declare(strict_types=1);

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class MarketplaceController extends AbstractController
{
    #[Route('/app/marketplace', name: 'app_marketplace')]
    public function hub(Request $request): Response
    {
        $params = array_merge(['tab' => 'browse'], $request->query->all());

        return $this->redirectToRoute('app_economy', $params);
    }
}
