<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    #[Route(name: 'app_admin_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $userRepository->findAllOrderedByName(),
        ]);
    }

    /**
     * Activa en lote os usuarios seleccionados.
     */
    #[Route('/bulk/activate', name: 'app_admin_user_bulk_activate', methods: ['POST'])]
    public function bulkActivate(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('bulk_user_activate', $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        $users = $userRepository->findBy(['id' => $request->getPayload()->all('ids')]);
        foreach ($users as $user) {
            $user->setActive(true);
        }

        $entityManager->flush();
        $this->addFlash('success', sprintf('%d usuario(s) activado(s).', count($users)));

        return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Desactiva en lote os usuarios seleccionados (desactivación lóxica, spec
     * sección 8: o campo "active" preserva a trazabilidade sen eliminar
     * datos). Nunca se borran usuarios. O administrador non pode
     * autodesactivarse para evitar quedar bloqueado fóra do panel.
     */
    #[Route('/bulk/deactivate', name: 'app_admin_user_bulk_deactivate', methods: ['POST'])]
    public function bulkDeactivate(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('bulk_user_deactivate', $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        $users = $userRepository->findBy(['id' => $request->getPayload()->all('ids')]);
        $currentUser = $this->getUser();

        $deactivated = 0;
        foreach ($users as $user) {
            if ($user !== $currentUser) {
                $user->setActive(false);
                ++$deactivated;
            }
        }

        $entityManager->flush();
        $this->addFlash('success', sprintf('%d usuario(s) desactivado(s).', $deactivated));

        return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyRoleAndPassword($form, $user, $passwordHasher);
            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/user/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true,
            'initial_role' => $user->getRoles()[0],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyRoleAndPassword($form, $user, $passwordHasher);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/user/edit.html.twig', ['form' => $form, 'user' => $user]);
    }

    private function applyRoleAndPassword(FormInterface $form, User $user, UserPasswordHasherInterface $passwordHasher): void
    {
        $user->setRoles([$form->get('roleChoice')->getData()]);

        $plainPassword = $form->get('plainPassword')->getData();
        if ($plainPassword) {
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        }
    }
}
