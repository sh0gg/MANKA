<?php

namespace App\Controller\Admin;

use App\Entity\ApiToken;
use App\Form\ApiTokenType;
use App\Repository\ApiTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/tokens')]
#[IsGranted('ROLE_ADMIN')]
final class ApiTokenController extends AbstractController
{
    #[Route(name: 'app_admin_token_index', methods: ['GET'])]
    public function index(ApiTokenRepository $apiTokenRepository): Response
    {
        return $this->render('admin/token/index.html.twig', [
            'tokens' => $apiTokenRepository->findAllOrderedByCreatedAt(),
        ]);
    }

    #[Route('/new', name: 'app_admin_token_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $token = new ApiToken();
        $form = $this->createForm(ApiTokenType::class, $token);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $token->setToken('manka_tk_'.bin2hex(random_bytes(24)));
            $entityManager->persist($token);
            $entityManager->flush();

            $this->addFlash('token_created', $token->getToken());

            return $this->redirectToRoute('app_admin_token_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/token/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/revoke', name: 'app_admin_token_revoke', methods: ['POST'])]
    public function revoke(Request $request, ApiToken $token, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('revoke'.$token->getId(), $request->getPayload()->getString('_token'))) {
            $token->setActive(false);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_token_index', [], Response::HTTP_SEE_OTHER);
    }
}
