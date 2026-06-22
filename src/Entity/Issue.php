<?php

namespace App\Entity;

use App\Enum\IssueStatus;
use App\Enum\IssueType;
use App\Repository\IssueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IssueRepository::class)]
class Issue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\Column(length: 20, enumType: IssueType::class)]
    private ?IssueType $type = null;

    #[ORM\Column(length: 20, enumType: IssueStatus::class)]
    private IssueStatus $status = IssueStatus::OPEN;

    /**
     * Indica se se verificaron as tarefas de hixiene/limpeza tras resolver a incidencia
     * (relevante en equipos de contacto alimentario, p. ex. cámaras frigoríficas).
     */
    #[ORM\Column]
    private bool $hygieneCheckDone = false;

    #[ORM\ManyToOne(inversedBy: 'issues')]
    #[ORM\JoinColumn(nullable: false)]
    private ?IssueCategory $category = null;

    #[ORM\ManyToOne(inversedBy: 'issues')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Device $device = null;

    /**
     * Técnicos asignados á incidencia.
     *
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'assignedIssues')]
    #[ORM\JoinTable(name: 'issue_technician')]
    private Collection $technicians;

    #[ORM\ManyToOne(inversedBy: 'createdIssues')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    /**
     * @var Collection<int, Observation>
     */
    #[ORM\OneToMany(targetEntity: Observation::class, mappedBy: 'issue', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $observations;

    public function __construct()
    {
        $this->technicians = new ArrayCollection();
        $this->observations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(?\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function getType(): ?IssueType
    {
        return $this->type;
    }

    public function setType(IssueType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): IssueStatus
    {
        return $this->status;
    }

    public function setStatus(IssueStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isOpen(): bool
    {
        return IssueStatus::OPEN === $this->status;
    }

    /**
     * Pecha a incidencia, marcando a data de fin.
     */
    public function close(\DateTimeImmutable $endAt = new \DateTimeImmutable(), bool $hygieneCheckDone = false): static
    {
        $this->status = IssueStatus::CLOSED;
        $this->endAt = $endAt;
        $this->hygieneCheckDone = $hygieneCheckDone;

        return $this;
    }

    public function isHygieneCheckDone(): bool
    {
        return $this->hygieneCheckDone;
    }

    public function setHygieneCheckDone(bool $hygieneCheckDone): static
    {
        $this->hygieneCheckDone = $hygieneCheckDone;

        return $this;
    }

    public function getCategory(): ?IssueCategory
    {
        return $this->category;
    }

    public function setCategory(?IssueCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getDevice(): ?Device
    {
        return $this->device;
    }

    public function setDevice(?Device $device): static
    {
        $this->device = $device;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getTechnicians(): Collection
    {
        return $this->technicians;
    }

    public function addTechnician(User $technician): static
    {
        if (!$this->technicians->contains($technician)) {
            $this->technicians->add($technician);
        }

        return $this;
    }

    public function removeTechnician(User $technician): static
    {
        $this->technicians->removeElement($technician);

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * @return Collection<int, Observation>
     */
    public function getObservations(): Collection
    {
        return $this->observations;
    }

    public function addObservation(Observation $observation): static
    {
        if (!$this->observations->contains($observation)) {
            $this->observations->add($observation);
            $observation->setIssue($this);
        }

        return $this;
    }

    public function removeObservation(Observation $observation): static
    {
        if ($this->observations->removeElement($observation)) {
            if ($observation->getIssue() === $this) {
                $observation->setIssue(null);
            }
        }

        return $this;
    }
}
