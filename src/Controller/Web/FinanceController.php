<?php

declare(strict_types=1);

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class FinanceController extends AbstractController
{
    #[Route('/app/finance', name: 'app_finance', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->redirectToRoute('app_economy', array_merge(
            ['tab' => 'ledger'],
            $request->query->all()
        ));
    }
}
