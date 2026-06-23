<?php

namespace App\Security;

use App\Repository\ApiTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Valida o Bearer Token enviado na cabeceira "Authorization" da API REST
 * contra os ApiToken xerados dende o panel de administración.
 *
 * A API non actúa en nome dun usuario da aplicación (User): o token representa
 * unha integración externa, polo que se modela cun usuario "ROLE_API_CLIENT" sintético.
 */
class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private readonly ApiTokenRepository $apiTokenRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        $token = $this->apiTokenRepository->findOneByToken($accessToken);

        if (null === $token || !$token->isValid()) {
            throw new BadCredentialsException('Token de API non válido, inactivo ou caducado.');
        }

        $token->setLastUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return new UserBadge(
            $token->getToken(),
            static fn () => new InMemoryUser($token->getName(), null, ['ROLE_API_CLIENT'])
        );
    }
}
