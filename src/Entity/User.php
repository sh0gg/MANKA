<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
// "user" é palabra reservada en PostgreSQL: as comiñas invertidas fan que Doctrine
// a escape de forma consistente tanto en DDL (migracións) como en SQL en tempo de execución.
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Xa existe un usuario rexistrado con este correo electrónico.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Indica un correo electrónico.')]
    #[Assert\Email(message: 'O correo electrónico non é válido.')]
    private ?string $email = null;

    #[ORM\Column]
    private ?string $password = null;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Indica o nome.')]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Indica os apelidos.')]
    private ?string $surname = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    /**
     * Incidencias nas que este usuario actúa como técnico asignado.
     *
     * @var Collection<int, Issue>
     */
    #[ORM\ManyToMany(targetEntity: Issue::class, mappedBy: 'technicians')]
    private Collection $assignedIssues;

    /**
     * Incidencias que este usuario abriu (creador).
     *
     * @var Collection<int, Issue>
     */
    #[ORM\OneToMany(targetEntity: Issue::class, mappedBy: 'createdBy')]
    private Collection $createdIssues;

    /**
     * @var Collection<int, Observation>
     */
    #[ORM\OneToMany(targetEntity: Observation::class, mappedBy: 'author')]
    private Collection $observations;

    public function __construct()
    {
        $this->assignedIssues = new ArrayCollection();
        $this->createdIssues = new ArrayCollection();
        $this->observations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Identificador único usado polo sistema de seguridade (login por email).
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // Todo usuario autenticado ten, como mínimo, ROLE_USER.
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function eraseCredentials(): void
    {
        // No hai datos sensibles temporais que limpar.
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSurname(): ?string
    {
        return $this->surname;
    }

    public function setSurname(string $surname): static
    {
        $this->surname = $surname;

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->name.' '.$this->surname);
    }

    /**
     * Iniciais para o avatar (ex. "Martín Torres" -> "MT").
     */
    public function getInitials(): string
    {
        $initials = mb_substr((string) $this->name, 0, 1).mb_substr((string) $this->surname, 0, 1);

        return mb_strtoupper($initials);
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    /**
     * @return Collection<int, Issue>
     */
    public function getAssignedIssues(): Collection
    {
        return $this->assignedIssues;
    }

    /**
     * @return Collection<int, Issue>
     */
    public function getCreatedIssues(): Collection
    {
        return $this->createdIssues;
    }

    /**
     * @return Collection<int, Observation>
     */
    public function getObservations(): Collection
    {
        return $this->observations;
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}
