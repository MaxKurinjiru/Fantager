<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LocaleController extends AbstractController
{
    #[Route('/change-locale/{locale}', name: 'app_change_locale', methods: ['GET'])]
    public function changeLocale(string $locale, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!in_array($locale, ['cs', 'en'], true)) {
            $locale = 'cs';
        }

        $request->getSession()->set('_locale', $locale);

        /** @var User|null $user */
        $user = $this->getUser();
        if ($user) {
            $user->setLocale($locale);
            $entityManager->flush();
        }

        $referrer = $request->headers->get('referer');
        if ($referrer) {
            // Avoid open redirect vulnerabilities by ensuring referer is local
            $host = $request->getHost();
            $refererHost = parse_url($referrer, PHP_URL_HOST);
            if (null === $refererHost || $refererHost === $host) {
                return $this->redirect($referrer);
            }
        }

        return $this->redirectToRoute('app_home');
    }
}
