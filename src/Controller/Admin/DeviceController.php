<?php

namespace App\Controller\Admin;

use App\Entity\Device;
use App\Form\DeviceType;
use App\Repository\DeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/devices')]
#[IsGranted('ROLE_ADMIN')]
final class DeviceController extends AbstractController
{
    #[Route(name: 'app_admin_device_index', methods: ['GET'])]
    public function index(DeviceRepository $deviceRepository): Response
    {
        $devices = $deviceRepository->findBy([], ['name' => 'ASC']);

        $stats = [];
        foreach ($devices as $device) {
            $openCount = 0;
            $lastStartAt = null;
            foreach ($device->getIssues() as $issue) {
                if ($issue->isOpen()) {
                    ++$openCount;
                }
                if (null === $lastStartAt || $issue->getStartAt() > $lastStartAt) {
                    $lastStartAt = $issue->getStartAt();
                }
            }
            $stats[$device->getId()] = [
                'open' => $openCount,
                'total' => $device->getIssues()->count(),
                'last' => $lastStartAt,
            ];
        }

        return $this->render('admin/device/index.html.twig', [
            'devices' => $devices,
            'stats' => $stats,
        ]);
    }

    /**
     * Borra en lote os equipos seleccionados. Sáltanse os que teñan
     * incidencias asociadas (Issue.device é nullable: false, non se pode
     * borrar sen romper a integridade referencial); pensado para limpar
     * equipos creados por erro durante a carga inicial de datos.
     */
    #[Route('/bulk/delete', name: 'app_admin_device_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request, DeviceRepository $deviceRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('bulk_device_delete', $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_admin_device_index', [], Response::HTTP_SEE_OTHER);
        }

        $devices = $deviceRepository->findBy(['id' => $request->getPayload()->all('ids')]);

        $deleted = 0;
        $skipped = 0;
        foreach ($devices as $device) {
            if (0 === $device->getIssues()->count()) {
                $entityManager->remove($device);
                ++$deleted;
            } else {
                ++$skipped;
            }
        }

        $entityManager->flush();

        $message = sprintf('%d equipo(s) eliminado(s).', $deleted);
        if ($skipped > 0) {
            $message .= sprintf(' %d omitido(s) por ter incidencias asociadas.', $skipped);
        }
        $this->addFlash($skipped > 0 ? 'error' : 'success', $message);

        return $this->redirectToRoute('app_admin_device_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/new', name: 'app_admin_device_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $device = new Device();
        $form = $this->createForm(DeviceType::class, $device);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($device);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_device_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/device/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'app_admin_device_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Device $device, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DeviceType::class, $device);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_device_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/device/edit.html.twig', ['form' => $form, 'device' => $device]);
    }
}
