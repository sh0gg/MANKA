<?php

namespace App\Entity;

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
    private ?\DateTime $startAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $endAt = null;

    #[ORM\ManyToOne(inversedBy: 'issues')]
    #[ORM\JoinColumn(nullable: false)]
    private ?IssueCategory $category = null;

    #[ORM\ManyToOne(inversedBy: 'issues')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Device $device = null;

    /**
     * @var Collection<int, Technician>
     */
    #[ORM\ManyToMany(targetEntity: Technician::class, inversedBy: 'issues')]
    private Collection $technician;

    #[ORM\Column]
    private ?bool $hygiene = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $comments = null;

    public function __construct()
    {
        $this->technician = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartAt(): ?\DateTime
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTime $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTime
    {
        return $this->endAt;
    }

    public function setEndAt(?\DateTime $endAt): static
    {
        $this->endAt = $endAt;

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
     * @return Collection<int, Technician>
     */
    public function getTechnician(): Collection
    {
        return $this->technician;
    }

    public function addTechnician(Technician $technician): static
    {
        if (!$this->technician->contains($technician)) {
            $this->technician->add($technician);
        }

        return $this;
    }

    public function removeTechnician(Technician $technician): static
    {
        $this->technician->removeElement($technician);

        return $this;
    }

    public function isHygiene(): ?bool
    {
        return $this->hygiene;
    }

    public function setHygiene(bool $hygiene): static
    {
        $this->hygiene = $hygiene;

        return $this;
    }

    public function getComments(): ?string
    {
        return $this->comments;
    }

    public function setComments(?string $comments): static
    {
        $this->comments = $comments;

        return $this;
    }
}
