<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Permite que Symfony Security actualice o hash do contrasinal cando cambia o algoritmo.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Técnicos e administradores activos, para listas de asignación.
     * O filtro por rol fai-se en PHP: o tipo "roles" é un array serializado en JSON
     * e non convén depender da sintaxe JSON propia de cada motor de BD en DQL.
     *
     * @return User[]
     */
    public function findActiveTechnicians(): array
    {
        $activeUsers = $this->createQueryBuilder('u')
            ->andWhere('u.active = true')
            ->orderBy('u.surname', 'ASC')
            ->addOrderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values(array_filter(
            $activeUsers,
            static fn (User $user) => array_intersect(['ROLE_TECHNICIAN', 'ROLE_ADMIN'], $user->getRoles())
        ));
    }

    /**
     * @return User[]
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.surname', 'ASC')
            ->addOrderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
