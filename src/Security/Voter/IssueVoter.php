<?php

namespace App\Security\Voter;

use App\Entity\Issue;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Regras de permisos sobre unha Issue concreta (ver spec, sección 5 e 6).
 *
 * - ROLE_TECHNICIAN e ROLE_ADMIN: acceso completo a calquera incidencia.
 * - ROLE_USER: só pode ver/pechar/reabrir as incidencias que el mesmo abriu
 *   (reabrir cobre o caso de que o fallo non se solucionase realmente), e
 *   nunca pode editalas nin eliminalas.
 * - Engadir observacións está permitido a calquera usuario autenticado
 *   mentres a incidencia siga aberta (spec, sección 5: "Pode engadir
 *   observacións a calquera incidencia aberta").
 */
class IssueVoter extends Voter
{
    public const string VIEW = 'ISSUE_VIEW';
    public const string EDIT = 'ISSUE_EDIT';
    public const string CLOSE = 'ISSUE_CLOSE';
    public const string REOPEN = 'ISSUE_REOPEN';
    public const string DELETE = 'ISSUE_DELETE';
    public const string ADD_OBSERVATION = 'ISSUE_ADD_OBSERVATION';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Issue && in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::CLOSE,
            self::REOPEN,
            self::DELETE,
            self::ADD_OBSERVATION,
        ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Issue $issue */
        $issue = $subject;

        if (self::ADD_OBSERVATION === $attribute) {
            return $issue->isOpen();
        }

        // Empregamos o servizo Security (e non $user->getRoles() directamente) porque
        // só el resolve a xerarquía de roles configurada en security.yaml: un ROLE_ADMIN
        // non leva "ROLE_TECHNICIAN" no seu array crudo, só o herda por xerarquía.
        if ($this->security->isGranted('ROLE_TECHNICIAN')) {
            return true;
        }

        $isOwner = $issue->getCreatedBy() === $user;

        return match ($attribute) {
            self::VIEW, self::CLOSE, self::REOPEN => $isOwner,
            default => false, // EDIT, DELETE: reservados a técnicos/administradores
        };
    }
}
