<?php

namespace App\Controller\Api;

use App\Entity\Device;
use App\Entity\Issue;
use App\Entity\IssueCategory;
use App\Entity\User;
use App\Enum\IssueStatus;
use App\Enum\IssueType;
use App\Repository\IssueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API REST completa sobre Issue (spec, sección 7). Autenticación mediante
 * Bearer Token, xestionada polo firewall "api" (ver security.yaml e
 * App\Security\ApiTokenHandler). Non hai endpoints /export nin /stats.
 */
#[Route('/api/issues')]
final class IssueApiController extends AbstractController
{
    #[Route(name: 'app_api_issue_index', methods: ['GET'])]
    public function index(Request $request, IssueRepository $issueRepository): JsonResponse
    {
        $filters = [
            'from' => $this->parseDate($request->query->get('from')),
            'to' => $this->parseDate($request->query->get('to')),
            'device' => $request->query->get('device'),
            'category' => $request->query->get('category'),
            'status' => IssueStatus::tryFrom((string) $request->query->get('status')),
        ];

        $issues = $issueRepository->search($filters);

        return $this->json(array_map($this->serializeIssue(...), $issues));
    }

    #[Route('/{id}', name: 'app_api_issue_show', methods: ['GET'])]
    public function show(Issue $issue): JsonResponse
    {
        return $this->json($this->serializeIssue($issue));
    }

    #[Route(name: 'app_api_issue_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $device = $this->findOrFail($entityManager, Device::class, $data['deviceId'] ?? null, 'deviceId');
        $category = $this->findOrFail($entityManager, IssueCategory::class, $data['categoryId'] ?? null, 'categoryId');
        $createdBy = $this->findOrFail($entityManager, User::class, $data['createdById'] ?? null, 'createdById');
        $type = IssueType::tryFrom((string) ($data['type'] ?? ''));

        if (null === $type || empty($data['startAt'])) {
            return $this->json(['error' => 'Os campos "type" (preventivo|correctivo) e "startAt" son obrigatorios.'], Response::HTTP_BAD_REQUEST);
        }

        $issue = new Issue();
        $issue->setDevice($device);
        $issue->setCategory($category);
        $issue->setCreatedBy($createdBy);
        $issue->setType($type);
        $issue->setStartAt(new \DateTimeImmutable($data['startAt']));

        if (!empty($data['technicianIds']) && is_array($data['technicianIds'])) {
            foreach ($data['technicianIds'] as $technicianId) {
                $technician = $entityManager->getRepository(User::class)->find($technicianId);
                if ($technician instanceof User) {
                    $issue->addTechnician($technician);
                }
            }
        }

        $entityManager->persist($issue);
        $entityManager->flush();

        return $this->json($this->serializeIssue($issue), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'app_api_issue_update', methods: ['PATCH'])]
    public function update(Request $request, Issue $issue, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('deviceId', $data)) {
            $issue->setDevice($this->findOrFail($entityManager, Device::class, $data['deviceId'], 'deviceId'));
        }
        if (array_key_exists('categoryId', $data)) {
            $issue->setCategory($this->findOrFail($entityManager, IssueCategory::class, $data['categoryId'], 'categoryId'));
        }
        if (array_key_exists('type', $data)) {
            $type = IssueType::tryFrom((string) $data['type']);
            if (null === $type) {
                return $this->json(['error' => '"type" debe ser "preventivo" ou "correctivo".'], Response::HTTP_BAD_REQUEST);
            }
            $issue->setType($type);
        }
        if (array_key_exists('startAt', $data)) {
            $issue->setStartAt(new \DateTimeImmutable($data['startAt']));
        }
        if (array_key_exists('endAt', $data)) {
            $issue->setEndAt(null !== $data['endAt'] ? new \DateTimeImmutable($data['endAt']) : null);
        }
        if (array_key_exists('status', $data)) {
            $status = IssueStatus::tryFrom((string) $data['status']);
            if (null === $status) {
                return $this->json(['error' => '"status" debe ser "open" ou "closed".'], Response::HTTP_BAD_REQUEST);
            }
            $issue->setStatus($status);
        }
        if (array_key_exists('hygieneCheckDone', $data)) {
            $issue->setHygieneCheckDone((bool) $data['hygieneCheckDone']);
        }
        if (array_key_exists('technicianIds', $data) && is_array($data['technicianIds'])) {
            foreach ($issue->getTechnicians()->toArray() as $existing) {
                $issue->removeTechnician($existing);
            }
            foreach ($data['technicianIds'] as $technicianId) {
                $technician = $entityManager->getRepository(User::class)->find($technicianId);
                if ($technician instanceof User) {
                    $issue->addTechnician($technician);
                }
            }
        }

        $entityManager->flush();

        return $this->json($this->serializeIssue($issue));
    }

    #[Route('/{id}', name: 'app_api_issue_delete', methods: ['DELETE'])]
    public function delete(Issue $issue, EntityManagerInterface $entityManager): JsonResponse
    {
        $entityManager->remove($issue);
        $entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function serializeIssue(Issue $issue): array
    {
        return [
            'id' => $issue->getId(),
            'startAt' => $issue->getStartAt()->format(\DateTimeInterface::ATOM),
            'endAt' => $issue->getEndAt()?->format(\DateTimeInterface::ATOM),
            'type' => $issue->getType()->value,
            'status' => $issue->getStatus()->value,
            'hygieneCheckDone' => $issue->isHygieneCheckDone(),
            'device' => ['id' => $issue->getDevice()->getId(), 'name' => $issue->getDevice()->getName()],
            'category' => ['id' => $issue->getCategory()->getId(), 'name' => $issue->getCategory()->getName()],
            'technicians' => array_map(
                static fn (User $technician) => ['id' => $technician->getId(), 'name' => $technician->getFullName()],
                $issue->getTechnicians()->toArray()
            ),
            'createdBy' => ['id' => $issue->getCreatedBy()->getId(), 'name' => $issue->getCreatedBy()->getFullName()],
        ];
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function findOrFail(EntityManagerInterface $entityManager, string $class, mixed $id, string $field): object
    {
        $entity = null !== $id ? $entityManager->getRepository($class)->find($id) : null;

        if (null === $entity) {
            throw new BadRequestHttpException(sprintf('"%s" non é válido ou non existe.', $field));
        }

        return $entity;
    }
}
