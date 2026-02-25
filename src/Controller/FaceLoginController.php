<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Psr\Log\LoggerInterface;

class FaceLoginController extends AbstractController
{
    private const FACE_API_URL = 'http://127.0.0.1:5000';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private TokenStorageInterface $tokenStorage
    ) {}

    /**
     * Page de connexion par reconnaissance faciale
     */
    #[Route('/connexion/visage', name: 'app_face_login')]
    public function faceLoginPage(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        // Vérifier si l'API Python est disponible
        $apiAvailable = false;
        try {
            $response = $this->httpClient->request('GET', self::FACE_API_URL . '/health', ['timeout' => 3]);
            $data = $response->toArray(false);
            $apiAvailable = ($data['status'] ?? '') === 'ok' && ($data['deepface'] ?? false);
        } catch (\Exception $e) {
            $this->logger->warning('API Python indisponible: ' . $e->getMessage());
        }

        return $this->render('security/face_login.html.twig', [
            'api_available' => $apiAvailable,
        ]);
    }

    /**
     * Étape 1 : Trouver l'utilisateur par email
     */
    #[Route('/api/face-login/prepare', name: 'api_face_login_prepare', methods: ['POST'])]
    public function prepareLogin(Request $request): JsonResponse
    {
        $data  = json_decode($request->getContent(), true) ?? [];
        $email = trim($data['email'] ?? '');

        if (!$email) {
            return $this->json(['error' => 'Email requis'], 400);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->json(['error' => 'Aucun compte associé à cet email.'], 404);
        }

        if (!$user->isIsActive()) {
            return $this->json(['error' => 'Votre compte n\'est pas encore activé.'], 403);
        }

        if (!$user->isIsVerified()) {
            return $this->json(['error' => 'Veuillez d\'abord vérifier votre email.'], 403);
        }

        $profil = $user->getProfil();
        $photo  = $profil?->getPhoto();

        if (!$photo) {
            return $this->json([
                'error' => 'Aucune photo de profil configurée. Veuillez d\'abord ajouter une photo dans votre profil.'
            ], 422);
        }

        return $this->json([
            'ready'   => true,
            'user_id' => $user->getUserId(),
            'photo'   => $photo,
            'name'    => $user->getFullName(),
        ]);
    }

    /**
     * Étape 2 : Vérifier le visage capturé via l'API Python
     */
    #[Route('/api/face-login/verify', name: 'api_face_login_verify', methods: ['POST'])]
    public function verifyAndLogin(Request $request): JsonResponse
    {
        $data          = json_decode($request->getContent(), true) ?? [];
        $userId        = $data['user_id']        ?? null;
        $capturedImage = $data['captured_image'] ?? null;
        $userPhoto     = $data['user_photo']     ?? null;

        if (!$userId || !$capturedImage || !$userPhoto) {
            return $this->json(['error' => 'Données manquantes (user_id, captured_image, user_photo)'], 400);
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        // Appeler l'API Python
        try {
            $response = $this->httpClient->request('POST', self::FACE_API_URL . '/api/face/verify', [
                'json' => [
                    'captured_image' => $capturedImage,
                    'user_photo'     => $userPhoto,
                    'user_id'        => $userId,
                ],
                'timeout' => 20,
            ]);

            $result = $response->toArray(false);

        } catch (\Exception $e) {
            $this->logger->error('Erreur API reconnaissance faciale: ' . $e->getMessage());
            return $this->json([
                'error' => 'Service de reconnaissance faciale indisponible. Veuillez utiliser la connexion classique.'
            ], 503);
        }

        // Visage non reconnu
        if (!($result['match'] ?? false)) {
            $this->logger->warning(sprintf(
                'Tentative échouée user_id=%d (confidence=%.3f)',
                $userId,
                $result['confidence'] ?? 0
            ));

            return $this->json([
                'success'    => false,
                'message'    => $result['message'] ?? 'Visage non reconnu.',
                'confidence' => $result['confidence'] ?? 0,
            ]);
        }

        // ✅ Visage reconnu → créer un token de session temporaire
        $this->logger->info(sprintf(
            'Connexion faciale réussie user_id=%d email=%s (confidence=%.3f)',
            $userId,
            $user->getEmail(),
            $result['confidence'] ?? 0
        ));

        $loginToken = bin2hex(random_bytes(32));
        $session    = $request->getSession();
        $session->set('face_login_token',   $loginToken);
        $session->set('face_login_user_id', $userId);
        $session->set('face_login_expires', time() + 30);

        return $this->json([
            'success'     => true,
            'message'     => $result['message'] ?? 'Identité vérifiée !',
            'confidence'  => $result['confidence'] ?? 0,
            'login_token' => $loginToken,
            'redirect'    => $this->generateUrl('app_face_login_confirm'),
        ]);
    }

    /**
     * Étape 3 : Finaliser la connexion Symfony
     */
    #[Route('/connexion/visage/confirmer', name: 'app_face_login_confirm')]
    public function confirmFaceLogin(Request $request): Response
    {
        $session = $request->getSession();
        $userId  = $session->get('face_login_user_id');
        $expires = $session->get('face_login_expires');

        if (!$userId || !$expires || time() > $expires) {
            $this->addFlash('error', 'Session expirée. Veuillez réessayer.');
            return $this->redirectToRoute('app_face_login');
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé.');
            return $this->redirectToRoute('app_face_login');
        }

        // Nettoyer la session temporaire
        $session->remove('face_login_token');
        $session->remove('face_login_user_id');
        $session->remove('face_login_expires');

        // Connecter l'utilisateur dans Symfony
        $token = new PostAuthenticationToken($user, 'main', $user->getRoles());
        $this->tokenStorage->setToken($token);
        $session->set('_security_main', serialize($token));

        $this->addFlash('success', 'Bienvenue ' . $user->getPrenom() . ' ! Connexion par reconnaissance faciale réussie.');

        return $this->redirectToRoute('app_dashboard');
    }
}
