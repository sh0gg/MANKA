<?php

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Actualiza o "último acceso" do usuario, mostrado na táboa de Usuarios do panel
 * de administración (ver sección 9 do spec).
 */
#[AsEventListener(event: LoginSuccessEvent::class)]
class LoginSuccessListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}
