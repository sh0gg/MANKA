<?php

namespace App\Controller\Admin;

use App\Entity\IssueCategory;
use App\Form\IssueCategoryType;
use App\Repository\IssueCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/categories')]
#[IsGranted('ROLE_ADMIN')]
final class CategoryController extends AbstractController
{
    #[Route(name: 'app_admin_category_index', methods: ['GET'])]
    public function index(IssueCategoryRepository $issueCategoryRepository): Response
    {
        $categories = $issueCategoryRepository->findBy([], ['name' => 'ASC']);

        $stats = [];
        foreach ($categories as $category) {
            $lastStartAt = null;
            foreach ($category->getIssues() as $issue) {
                if (null === $lastStartAt || $issue->getStartAt() > $lastStartAt) {
                    $lastStartAt = $issue->getStartAt();
                }
            }
            $stats[$category->getId()] = [
                'total' => $category->getIssues()->count(),
                'last' => $lastStartAt,
            ];
        }

        return $this->render('admin/category/index.html.twig', [
            'categories' => $categories,
            'stats' => $stats,
        ]);
    }

    #[Route('/new', name: 'app_admin_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $category = new IssueCategory();
        $form = $this->createForm(IssueCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($category);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/category/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'app_admin_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, IssueCategory $category, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(IssueCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/category/edit.html.twig', ['form' => $form, 'category' => $category]);
    }
}
