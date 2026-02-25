<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Profil;
use App\Enum\RoleType;
use App\Form\RegistrationFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Form\ChangePasswordFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Form\FormError;
use App\Service\EmailManager;


class SecurityController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private \Symfony\Component\Mailer\MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private EmailManager $emailManager
    ) {}

    #[Route('/inscription', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        // On passe le FormType qui définit validation_groups=['Registration']
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {

            // 1) Rôle (mapped=false) → lire depuis le champ du formulaire
            $roleValue = (string) ($form->get('role')->getData() ?? '');
            $allowedRoles = [
                RoleType::ETUDIANT->value,
                RoleType::PSYCHOLOGUE->value,
                RoleType::RESPONSABLE_ETUDIANT->value,
            ];
            if ($roleValue === '' || !in_array($roleValue, $allowedRoles, true)) {
                $form->addError(new FormError('Veuillez sélectionner un rôle.'));
            }

            // 2) Email unique
            $email = $form->get('email')->getData();
            if ($email) {
                $exists = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($exists) {
                    $form->get('email')->addError(new FormError('Cet email est déjà utilisé.'));
                }
            }

            // 3) Validation serveur spécifique selon rôle
            if (in_array($roleValue, $allowedRoles, true)) {
                switch ($roleValue) {
                    case RoleType::ETUDIANT->value:
                        if (empty($user->getIdentifiant())) {
                            $form->get('identifiant')->addError(new FormError('L\'identifiant étudiant est obligatoire.'));
                        }
                        if (empty($user->getNomEtablissement())) {
                            $form->get('nom_etablissement')->addError(new FormError('Le nom de l\'établissement est obligatoire.'));
                        }
                        break;

                    case RoleType::PSYCHOLOGUE->value:
                        if (empty($user->getSpecialite())) {
                            $form->get('specialite')->addError(new FormError('La spécialité est obligatoire.'));
                        }
                        if (empty($user->getAdresse())) {
                            $form->get('adresse')->addError(new FormError('L\'adresse est obligatoire.'));
                        }
                        if (empty($user->getTelephone())) {
                            $form->get('telephone')->addError(new FormError('Le téléphone est obligatoire.'));
                        } elseif (!preg_match('/^[0-9]{8}$/', $user->getTelephone())) { // Tunisie: 8 chiffres
                            $form->get('telephone')->addError(new FormError('Le téléphone doit contenir 8 chiffres.'));
                        }
                        break;

                    case RoleType::RESPONSABLE_ETUDIANT->value:
                        if (empty($user->getPoste())) {
                            $form->get('poste')->addError(new FormError('Le poste est obligatoire.'));
                        }
                        if (empty($user->getEtablissement())) {
                            $form->get('etablissement')->addError(new FormError('L\'établissement est obligatoire.'));
                        }
                        break;
                }
            }

            // 4) Si tout OK → création
            if ($form->isValid() && \count($form->getErrors(true)) === 0) {
                try {
                    // Appliquer l'enum rôle à l'entité
                    $user->setRole(RoleType::from($roleValue));

                    // Mot de passe (hash) depuis plainPassword (mapped=false)
                    $plainPassword = $form->get('plainPassword')->getData();
                    $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

                    // Profil lié
                    $profil = new Profil();
                    if ($roleValue === RoleType::ETUDIANT->value) {
                        $profil->setEtablissement($user->getNomEtablissement());
                    } elseif ($roleValue === RoleType::PSYCHOLOGUE->value) {
                        $profil->setSpecialite($user->getSpecialite());
                        $profil->setTel($user->getTelephone());
                    } elseif ($roleValue === RoleType::RESPONSABLE_ETUDIANT->value) {
                        $profil->setEtablissement($user->getEtablissement());
                        $profil->setFonction($user->getPoste());
                    }

                    // Statuts / vérif
                    $user->setVerificationToken(Uuid::v4()->toRfc4122());
                    $user->setTokenExpiresAt(new \DateTime('+24 hours'));
                    $user->setIsActive(false);
                    $user->setIsVerified(false);
                    $user->setStatut('en_attente');

                    // Liaison
                    $user->setProfil($profil);
                    $profil->setUser($user);

                    $this->entityManager->persist($user);
                    $this->entityManager->persist($profil);
                    $this->entityManager->flush();

                    // Envoyer les emails
                    $this->emailManager->sendVerificationEmail($user);
                    $this->emailManager->notifyAdminNewPendingUser($user);

                    $this->addFlash('success', 'Votre compte a été créé avec succès ! Veuillez vérifier votre email pour activer votre compte.');
                    return $this->redirectToRoute('app_login');

                } catch (\Exception $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'inscription : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('error', 'Veuillez corriger les erreurs dans le formulaire.');
            }
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }


    #[Route('/verification/{token}', name: 'app_verify_email')]
    public function verifyEmail(string $token): Response
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'verificationToken' => $token
        ]);

        if (!$user) {
            $this->addFlash('error', 'Token de vérification invalide.');
            return $this->redirectToRoute('app_register');
        }

        $expiresAt = $user->getTokenExpiresAt();
        if ($expiresAt && $expiresAt < new \DateTime()) {
            $this->addFlash('error', 'Le lien de vérification a expiré.');
            return $this->redirectToRoute('app_register');
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $user->setTokenExpiresAt(null);
        $user->setIsActive(false); // activation finale par admin si c'est votre règle

        $this->entityManager->flush();

        $this->addFlash('success', 'Votre email a été vérifié avec succès. Votre compte sera activé par un administrateur.');
        return $this->redirectToRoute('app_login');
    }

    #[Route('/connexion', name: 'app_login')]
    public function login(
        AuthenticationUtils $authenticationUtils,
        Request $request
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $error        = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        // ── Validation PHP pure lors de la soumission ─────────────────
        $loginErrors = [];

        if ($request->isMethod('POST')) {
            $email    = trim($request->request->get('email', ''));
            $password = $request->request->get('password', '');

            // Validation email
            if (empty($email)) {
                $loginErrors['email'] = 'L\'adresse email est obligatoire.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $loginErrors['email'] = 'Veuillez entrer une adresse email valide.';
            } elseif (strlen($email) > 180) {
                $loginErrors['email'] = 'L\'email ne peut pas dépasser 180 caractères.';
            }

            // Validation mot de passe
            if (empty($password)) {
                $loginErrors['password'] = 'Le mot de passe est obligatoire.';
            } elseif (strlen($password) < 8) {
                $loginErrors['password'] = 'Le mot de passe doit contenir au moins 8 caractères.';
            }

            // Si erreurs de validation → ne pas laisser Symfony authentifier
            // Les erreurs sont passées au template directement
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
            'login_errors'  => $loginErrors,  // ← erreurs PHP pures
        ]);
    }
    #[Route('/deconnexion', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Cette méthode peut être vide - elle sera interceptée par la clé logout sur votre pare-feu.');
    }

    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password')]
    public function forgotPassword(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($user && $user->isVerified()) {
                $user->setResetToken(Uuid::v4()->toRfc4122());
                $user->setResetTokenExpiresAt(new \DateTime('+24 hours'));
                $this->entityManager->flush();
                $this->emailManager->sendResetPasswordEmail($user);
            }

            $this->addFlash('success', 'Si votre email existe dans notre système, vous recevrez un lien de réinitialisation.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'app_reset_password')]
    public function resetPassword(string $token, Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['resetToken' => $token]);
        
        if (!$user || !$user->isResetTokenValid()) {
            $this->addFlash('error', 'Le lien de réinitialisation est invalide ou expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
            $this->entityManager->flush();

            $this->addFlash('success', 'Mot de passe modifié avec succès.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }

    private function sendVerificationEmail(User $user): void
    {
        try {
            $verificationUrl = $this->urlGenerator->generate('app_verify_email', [
                'token' => $user->getVerificationToken()
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $email = (new TemplatedEmail())
                ->from(new Address('noreply@unimind.local', 'UniMind'))
                ->to($user->getEmail())
                ->subject('Vérification de votre email - UniMind')
                ->htmlTemplate('security/verification_email.html.twig')
                ->context([
                    'user' => $user,
                    'verificationUrl' => $verificationUrl,
                    'expiresAt' => $user->getTokenExpiresAt(),
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            // log silencieux
        }
    }


    private function sendResetPasswordEmail(User $user): void
    {
        try {
            $resetUrl = $this->urlGenerator->generate('app_reset_password', [
                'token' => $user->getResetToken()
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $email = (new TemplatedEmail())
                ->from(new Address('louatiislem74@gmail.com', 'UniMind'))
                ->to($user->getEmail())
                ->subject('Réinitialisation de votre mot de passe - UniMind')
                ->htmlTemplate('security/reset_password_email.html.twig')
                ->context([
                    'user' => $user,
                    'resetUrl' => $resetUrl,
                    'expiresAt' => $user->getResetTokenExpiresAt(),
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            error_log("Erreur envoi email réinitialisation: " . $e->getMessage());
        }
    }
}