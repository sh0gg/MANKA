<?php

namespace App\Controller;

use App\Entity\IssueCategory;
use App\Form\IssueCategoryType;
use App\Repository\IssueCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/issue/category')]
final class IssueCategoryController extends AbstractController
{
    #[Route(name: 'app_issue_category_index', methods: ['GET'])]
    public function index(IssueCategoryRepository $issueCategoryRepository): Response
    {
        return $this->render('issue_category/index.html.twig', [
            'issue_categories' => $issueCategoryRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_issue_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $issueCategory = new IssueCategory();
        $form = $this->createForm(IssueCategoryType::class, $issueCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($issueCategory);
            $entityManager->flush();

            return $this->redirectToRoute('app_issue_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('issue_category/new.html.twig', [
            'issue_category' => $issueCategory,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_issue_category_show', methods: ['GET'])]
    public function show(IssueCategory $issueCategory): Response
    {
        return $this->render('issue_category/show.html.twig', [
            'issue_category' => $issueCategory,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_issue_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, IssueCategory $issueCategory, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(IssueCategoryType::class, $issueCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_issue_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('issue_category/edit.html.twig', [
            'issue_category' => $issueCategory,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_issue_category_delete', methods: ['POST'])]
    public function delete(Request $request, IssueCategory $issueCategory, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$issueCategory->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($issueCategory);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_issue_category_index', [], Response::HTTP_SEE_OTHER);
    }
}
