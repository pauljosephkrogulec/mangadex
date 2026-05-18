<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class LogoutController extends AbstractController
{
    #[Route('/logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        $response = $this->json(['message' => 'Logged out successfully']);
        $cookie = Cookie::create('mangadex_jwt_token')
            ->withValue('')
            ->withExpires(1)
            ->withPath('/')
            ->withHttpOnly(true)
            ->withSameSite('lax');

        $response->headers->setCookie($cookie);

        return $response;
    }
}
