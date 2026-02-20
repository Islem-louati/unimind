<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $firewallName = 'main';
        $session = $request->getSession();

        if ($session !== null) {
            $targetPath = $this->getTargetPath($session, $firewallName);

            if ($targetPath) {
                if ($this->userHasRole($token, 'ROLE_ADMIN') && str_starts_with($targetPath, '/admin')) {
                    return new RedirectResponse($targetPath);
                }

                if ($this->userHasRole($token, 'ROLE_RESPONSABLE_ETUDIANT') && str_starts_with($targetPath, '/responsable-etudiant')) {
                    return new RedirectResponse($targetPath);
                }

                if ($this->userHasRole($token, 'ROLE_ETUDIANT') && str_starts_with($targetPath, '/etudiant')) {
                    return new RedirectResponse($targetPath);
                }
            }
        }

        if ($this->userHasRole($token, 'ROLE_ADMIN')) {
            return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
        }

        if ($this->userHasRole($token, 'ROLE_RESPONSABLE_ETUDIANT')) {
            return new RedirectResponse($this->urlGenerator->generate('app_back_dashboard'));
        }

        if ($this->userHasRole($token, 'ROLE_ETUDIANT')) {
            return new RedirectResponse($this->urlGenerator->generate('app_evenement_etudiant_index'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    private function userHasRole(TokenInterface $token, string $role): bool
    {
        foreach ($token->getRoleNames() as $roleName) {
            if ($roleName === $role) {
                return true;
            }
        }

        return false;
    }
}
