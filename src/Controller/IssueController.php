<?php

namespace App\Controller;

use App\Entity\Issue;
use App\Entity\Observation;
use App\Entity\User;
use App\Form\IssueType;
use App\Repository\IssueRepository;
use App\Security\Voter\IssueVoter;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/issues')]
#[IsGranted('ROLE_USER')]
final class IssueController extends AbstractController
{
    #[Route(name: 'app_issue_index', methods: ['GET'])]
    public function index(IssueRepository $issueRepository): Response
    {
        $issues = $this->visibleIssues($issueRepository);

        return $this->render('issue/index.html.twig', [
            'issues' => $issues,
            'is_privileged' => $this->isGranted('ROLE_TECHNICIAN'),
            'stats' => $this->buildStats($issues),
        ]);
    }

    #[Route('/export', name: 'app_issue_export', methods: ['GET'])]
    public function export(IssueRepository $issueRepository): StreamedResponse
    {
        $issues = $this->visibleIssues($issueRepository);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Incidencias');

        $headers = ['Estado', 'Inicio', 'Fin', 'Equipo', 'Categoría', 'Tipo', 'Técnicos', 'Creado por', 'Hixiene'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        $row = 2;
        foreach ($issues as $issue) {
            $technicians = implode(', ', array_map(
                static fn (User $technician) => $technician->getFullName(),
                $issue->getTechnicians()->toArray()
            ));

            $sheet->fromArray([
                $issue->getStatus()->label(),
                $issue->getStartAt()->format('d/m/Y H:i'),
                $issue->getEndAt()?->format('d/m/Y H:i') ?? '',
                $issue->getDevice()->getName(),
                $issue->getCategory()->getName(),
                $issue->getType()->label(),
                $technicians,
                $issue->getCreatedBy()->getFullName(),
                $issue->isOpen() ? '' : ($issue->isHygieneCheckDone() ? 'Verificada' : 'Sen verificar'),
            ], null, 'A'.$row);
            ++$row;
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $response = new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="incidencias_'.date('Y-m-d').'.xlsx"');

        return $response;
    }

    /**
     * @return Issue[]
     */
    private function visibleIssues(IssueRepository $issueRepository): array
    {
        /** @var User $user */
        $user = $this->getUser();

        // ROLE_USER só ve as incidencias que el mesmo abriu (spec, sección 9).
        return $this->isGranted('ROLE_TECHNICIAN')
            ? $issueRepository->findAllOrderedByStatus()
            : $issueRepository->findCreatedBy($user);
    }

    #[Route('/new', name: 'app_issue_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $isPrivileged = $this->isGranted('ROLE_TECHNICIAN');

        $issue = new Issue();
        $form = $this->createForm(IssueType::class, $issue, [
            'is_privileged' => $isPrivileged,
            'include_initial_observation' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $issue->setCreatedBy($user);

            $initialObservation = $form->get('initialObservation')->getData();
            if (null !== $initialObservation && '' !== trim($initialObservation)) {
                $observation = new Observation();
                $observation->setContent($initialObservation);
                $observation->setAuthor($user);
                $issue->addObservation($observation);
            }

            $entityManager->persist($issue);
            $entityManager->flush();

            return $this->redirectToRoute('app_issue_show', ['id' => $issue->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('issue/new.html.twig', [
            'issue' => $issue,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_issue_show', methods: ['GET'])]
    #[IsGranted(IssueVoter::VIEW, subject: 'issue')]
    public function show(Issue $issue): Response
    {
        return $this->render('issue/show.html.twig', [
            'issue' => $issue,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_issue_edit', methods: ['GET', 'POST'])]
    #[IsGranted(IssueVoter::EDIT, subject: 'issue')]
    public function edit(Request $request, Issue $issue, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(IssueType::class, $issue, [
            'is_privileged' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_issue_show', ['id' => $issue->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('issue/edit.html.twig', [
            'issue' => $issue,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/close', name: 'app_issue_close', methods: ['POST'])]
    #[IsGranted(IssueVoter::CLOSE, subject: 'issue')]
    public function close(Request $request, Issue $issue, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('close'.$issue->getId(), $request->getPayload()->getString('_token'))) {
            $issue->close(hygieneCheckDone: $request->getPayload()->getBoolean('hygieneCheckDone'));
            $entityManager->flush();
            $this->addFlash('success', 'Incidencia pechada correctamente.');
        }

        return $this->redirectToRoute('app_issue_show', ['id' => $issue->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/observations', name: 'app_issue_add_observation', methods: ['POST'])]
    #[IsGranted(IssueVoter::ADD_OBSERVATION, subject: 'issue')]
    public function addObservation(Request $request, Issue $issue, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $content = trim((string) $request->getPayload()->getString('content'));

        if ($this->isCsrfTokenValid('observation'.$issue->getId(), $request->getPayload()->getString('_token')) && '' !== $content) {
            $observation = new Observation();
            $observation->setContent($content);
            $observation->setAuthor($user);
            $issue->addObservation($observation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_issue_show', ['id' => $issue->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_issue_delete', methods: ['POST'])]
    #[IsGranted(IssueVoter::DELETE, subject: 'issue')]
    public function delete(Request $request, Issue $issue, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$issue->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($issue);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_issue_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * @param Issue[] $issues
     *
     * @return array{total: int, open: int, closed: int, avg_resolution_hours: ?float}
     */
    private function buildStats(array $issues): array
    {
        $open = array_filter($issues, static fn (Issue $issue) => $issue->isOpen());
        $closedWithDuration = array_filter(
            $issues,
            static fn (Issue $issue) => !$issue->isOpen() && null !== $issue->getEndAt()
        );

        $avgHours = null;
        if (\count($closedWithDuration) > 0) {
            $totalHours = array_sum(array_map(
                static fn (Issue $issue) => ($issue->getEndAt()->getTimestamp() - $issue->getStartAt()->getTimestamp()) / 3600,
                $closedWithDuration
            ));
            $avgHours = $totalHours / \count($closedWithDuration);
        }

        return [
            'total' => \count($issues),
            'open' => \count($open),
            'closed' => \count($issues) - \count($open),
            'avg_resolution_hours' => $avgHours,
        ];
    }
}
