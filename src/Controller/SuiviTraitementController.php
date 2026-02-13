<?php

namespace App\Controller;

use App\Entity\SuiviTraitement;
use App\Entity\Traitement;
use App\Entity\User;
use App\Form\SuiviTraitementType;
use App\Repository\SuiviTraitementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntityMapping;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;

#[Route('/suivi-traitement')]
class SuiviTraitementController extends AbstractController
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[Route('/', name: 'app_suivi_traitement_index', methods: ['GET'])]
    public function index(SuiviTraitementRepository $suiviTraitementRepository): Response
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $suivis = [];
        
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_RESPONSABLE_ETUDIANT')) {
            // Admin et responsable peuvent voir tous les suivis
            $suivis = $suiviTraitementRepository->createQueryBuilder('s')
                ->leftJoin('s.traitement', 't')
                ->leftJoin('t.etudiant', 'e')
                ->leftJoin('t.psychologue', 'p')
                ->addSelect('t', 'e', 'p')
                ->getQuery()
                ->getResult();
        } elseif ($this->isGranted('ROLE_PSYCHOLOGUE')) {
            // Psychologue voit les suivis de ses traitements, classés par traitement
            $suivis = $suiviTraitementRepository->createQueryBuilder('s')
                ->leftJoin('s.traitement', 't')
                ->leftJoin('t.etudiant', 'e')
                ->leftJoin('t.psychologue', 'p')
                ->addSelect('t', 'e', 'p')
                ->where('t.psychologue = :user')
                ->orderBy('t.titre', 'ASC')
                ->addOrderBy('e.nom', 'ASC')
                ->setParameter('user', $user)
                ->getQuery()
                ->getResult();
        } elseif ($this->isGranted('ROLE_ETUDIANT')) {
            // Étudiant voit seulement ses suivis, classés par traitement
            $suivis = $suiviTraitementRepository->createQueryBuilder('s')
                ->leftJoin('s.traitement', 't')
                ->leftJoin('t.etudiant', 'e')
                ->leftJoin('t.psychologue', 'p')
                ->addSelect('t', 'e', 'p')
                ->where('t.etudiant = :user')
                ->orderBy('t.titre', 'ASC')
                ->addOrderBy('s.dateSuivi', 'DESC')
                ->setParameter('user', $user)
                ->getQuery()
                ->getResult();
        }

        return $this->render('suivi_traitement/index.html.twig', [
            'suivis' => $suivis,
            'user_role' => $this->getMainRole($user)
        ]);
    }

    #[Route('/new', name: 'app_suivi_traitement_select_traitement', methods: ['GET'])]
    public function selectTraitement(EntityManagerInterface $entityManager): Response
    {
        $user = $this->security->getUser();
        
        // DEBUG
        dump([
            'user_email' => $user ? $user->getEmail() : 'no_user',
            'user_roles' => $user ? $user->getRoles() : [],
            'is_granted_psycho' => $this->isGranted('ROLE_PSYCHOLOGUE'),
            'is_granted_etudiant' => $this->isGranted('ROLE_ETUDIANT'),
            'is_granted_psycho_or_etudiant' => $this->isGranted('ROLE_PSYCHOLOGUE or ROLE_ETUDIANT')
        ]);
        
        // Récupérer les traitements selon le rôle
        if ($this->isGranted('ROLE_PSYCHOLOGUE')) {
            // Psychologue : ses traitements
            $traitements = $entityManager->getRepository(Traitement::class)->createQueryBuilder('t')
                ->leftJoin('t.etudiant', 'e')
                ->leftJoin('t.psychologue', 'p')
                ->addSelect('e', 'p')
                ->where('t.psychologue = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getResult();
        } else {
            // Étudiant : les traitements où il est le patient
            $traitements = $entityManager->getRepository(Traitement::class)->createQueryBuilder('t')
                ->leftJoin('t.etudiant', 'e')
                ->leftJoin('t.psychologue', 'p')
                ->addSelect('e', 'p')
                ->where('t.etudiant = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getResult();
        }
        
        return $this->render('suivi_traitement/select_traitement.html.twig', [
            'traitements' => $traitements
        ]);
    }

    #[Route('/new/{traitement_id}', name: 'app_suivi_traitement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, int $traitement_id): Response
    {
        $user = $this->security->getUser();
        
        // Récupérer le traitement
        $traitement = $entityManager->getRepository(Traitement::class)->find($traitement_id);
        
        if (!$traitement) {
            throw $this->createNotFoundException('Traitement non trouvé');
        }

        // Vérifier les droits d'accès au traitement
        if (!$this->canAccessTraitement($traitement, $user)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce traitement');
        }

        $suivi = new SuiviTraitement();
        $suivi->setTraitement($traitement);
        
        $form = $this->createForm(SuiviTraitementType::class, $suivi, [
            'user_role' => $this->getMainRole($user),
            'is_etudiant' => $this->isGranted('ROLE_ETUDIANT')
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Logique selon le rôle
            if ($this->isGranted('ROLE_ETUDIANT')) {
                // Étudiant : le suivi n'est pas validé par défaut
                $suivi->setValide(false);
                $suivi->setSaisiPar('etudiant');
                // Si l'étudiant coche "effectué", on met la date effective
                if ($suivi->isEffectue()) {
                    $suivi->setHeureEffective(new \DateTime());
                }
            } else {
                // Psychologue : peut valider directement
                $suivi->setSaisiPar('psychologue');
                // Le psychologue peut marquer comme effectué et valide
            }
            
            $entityManager->persist($suivi);
            $entityManager->flush();

            $this->addFlash('success', 'Le suivi a été créé avec succès.');

            return $this->redirectToRoute('app_traitement_show', ['id' => $traitement->getId()]);
        }

        // Afficher les erreurs de validation si le formulaire est invalide
        if ($form->isSubmitted() && !$form->isValid()) {
            $errors = $form->getErrors(true);
            $this->addFlash('error', 'Le formulaire contient des erreurs. Veuillez corriger les champs indiqués.');
            
            // Debug pour voir les erreurs dans la console
            dump('Erreurs de validation SuiviTraitement:', $errors);
            
            // Rediriger pour éviter le blocage
            return $this->render('suivi_traitement/new.html.twig', [
                'suivi' => $suivi,
                'traitement' => $traitement,
                'form' => $form->createView(),
                'user_role' => $this->getMainRole($user)
            ]);
        }

        return $this->render('suivi_traitement/new.html.twig', [
            'suivi' => $suivi,
            'traitement' => $traitement,
            'form' => $form->createView(),
            'user_role' => $this->getMainRole($user)
        ]);
    }

    #[Route('/{id}', name: 'app_suivi_traitement_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        Request $request,
        SuiviTraitementRepository $suiviTraitementRepository,
        int $id = 0
    ): Response {
        $suivi = $suiviTraitementRepository->find($id);
        
        if (!$suivi) {
            throw $this->createNotFoundException('Suivi non trouvé');
        }
        $user = $this->security->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Vérifier les droits d'accès
        if (!$this->canAccessSuivi($suivi, $user)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce suivi');
        }

        return $this->render('suivi_traitement/show.html.twig', [
            'suivi' => $suivi,
            'user_role' => $this->getMainRole($user),
            'can_edit' => $this->canEditSuivi($suivi, $user),
            'can_validate' => $this->canValidateSuivi($suivi, $user)
        ]);
    }

    #[Route('/{id}/edit', name: 'app_suivi_traitement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SuiviTraitement $suivi, EntityManagerInterface $entityManager): Response
    {
        $user = $this->security->getUser();
        
        if (!$this->canEditSuivi($suivi, $user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce suivi');
        }

        $form = $this->createForm(SuiviTraitementType::class, $suivi, [
            'user_role' => $this->getMainRole($user),
            'is_etudiant' => $this->isGranted('ROLE_ETUDIANT')
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le suivi a été modifié avec succès.');

            return $this->redirectToRoute('app_suivi_traitement_show', ['id' => $suivi->getId()]);
        }

        return $this->render('suivi_traitement/edit.html.twig', [
            'suivi' => $suivi,
            'form' => $form->createView(),
            'user_role' => $this->getMainRole($user),
            'can_edit' => $this->canEditSuivi($suivi, $user),
            'can_validate' => $this->canValidateSuivi($suivi, $user)
        ]);
    }

    #[Route('/{id}/effectuer', name: 'app_suivi_traitement_effectuer', methods: ['POST'])]
    public function effectuer(Request $request, SuiviTraitement $suivi, EntityManagerInterface $entityManager): Response
    {
        $user = $this->security->getUser();
        
        if (!$this->canEditSuivi($suivi, $user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce suivi');
        }

        if ($this->isCsrfTokenValid('effectuer'.$suivi->getId(), $request->request->get('_token'))) {
            $suivi->setEffectue(true);
            $suivi->setHeureEffective(new \DateTime());
            
            // Si c'est un étudiant, le suivi n'est pas encore validé
            if ($this->isGranted('ROLE_ETUDIANT')) {
                $suivi->setValide(false);
            } else {
                // Si c'est un psychologue ou admin, il valide directement
                $suivi->setValide(true);
            }
            
            $entityManager->flush();

            $this->addFlash('success', 'Le suivi a été marqué comme effectué.');
        }

        return $this->redirectToRoute('app_traitement_show', ['id' => $suivi->getTraitement()->getId()]);
    }

    #[Route('/{id}/valider', name: 'app_suivi_traitement_valider', methods: ['POST'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function valider(Request $request, SuiviTraitement $suivi, EntityManagerInterface $entityManager): Response
    {
        $user = $this->security->getUser();
        
        if (!$this->canValidateSuivi($suivi, $user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas valider ce suivi');
        }

        if ($this->isCsrfTokenValid('valider'.$suivi->getId(), $request->request->get('_token'))) {
            if (!$suivi->isEffectue()) {
                $this->addFlash('error', 'Un suivi doit être effectué avant d\'être validé.');
            } else {
                $suivi->setValide(true);
                $entityManager->flush();

                $this->addFlash('success', 'Le suivi a été validé avec succès.');
            }
        }

        return $this->redirectToRoute('app_traitement_show', ['id' => $suivi->getTraitement()->getId()]);
    }

    #[Route('/{id}', name: 'app_suivi_traitement_delete', methods: ['POST'])]
    public function delete(Request $request, SuiviTraitement $suivi, EntityManagerInterface $entityManager): Response
    {
        $user = $this->security->getUser();
        
        if (!$this->canEditSuivi($suivi, $user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce suivi');
        }

        if ($this->isCsrfTokenValid('delete'.$suivi->getId(), $request->request->get('_token'))) {
            $entityManager->remove($suivi);
            $entityManager->flush();

            $this->addFlash('success', 'Le suivi a été supprimé avec succès.');
        }

        return $this->redirectToRoute('app_traitement_show', ['id' => $suivi->getTraitement()->getId()]);
    }

    #[Route('/a-valider', name: 'app_suivi_traitement_a_valider', methods: ['GET'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function aValider(SuiviTraitementRepository $suiviTraitementRepository): Response
    {
        $user = $this->security->getUser();
        $suivis = $suiviTraitementRepository->findNonValidesByPsychologue($user);

        return $this->render('suivi_traitement/a_valider.html.twig', [
            'suivis' => $suivis,
            'user_role' => 'psychologue'
        ]);
    }

    private function canAccessSuivi(SuiviTraitement $suivi, User $user): bool
    {
        return $this->canAccessTraitement($suivi->getTraitement(), $user);
    }

    private function canEditSuivi(SuiviTraitement $suivi, User $user): bool
    {
        // Admin peut tout modifier
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Psychologue peut modifier les suivis de ses traitements
        if ($this->isGranted('ROLE_PSYCHOLOGUE') && $suivi->getTraitement()->getPsychologue() === $user) {
            return true;
        }

        // Étudiant peut modifier ses suivis (sans restriction de validation pour les tests)
        if ($this->isGranted('ROLE_ETUDIANT') && $suivi->getTraitement()->getEtudiant() === $user) {
            return true;
        }

        return false;
    }

    private function canValidateSuivi(SuiviTraitement $suivi, User $user): bool
    {
        // Admin peut tout valider
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Psychologue peut valider les suivis de ses traitements
        if ($this->isGranted('ROLE_PSYCHOLOGUE') && $suivi->getTraitement()->getPsychologue() === $user) {
            return $suivi->isEffectue() && !$suivi->isValide();
        }

        return false;
    }

    private function canAccessTraitement(Traitement $traitement, User $user): bool
    {
        // Admin et responsable peuvent tout voir
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_RESPONSABLE_ETUDIANT')) {
            return true;
        }

        // Psychologue peut voir ses traitements
        if ($this->isGranted('ROLE_PSYCHOLOGUE') && $traitement->getPsychologue() === $user) {
            return true;
        }

        // Étudiant peut voir ses traitements
        if ($this->isGranted('ROLE_ETUDIANT') && $traitement->getEtudiant() === $user) {
            return true;
        }

        return false;
    }

    private function getMainRole(User $user): string
    {
        $roles = $user->getRoles();
        
        if (in_array('ROLE_ADMIN', $roles)) {
            return 'admin';
        } elseif (in_array('ROLE_PSYCHOLOGUE', $roles)) {
            return 'psychologue';
        } elseif (in_array('ROLE_ETUDIANT', $roles)) {
            return 'etudiant';
        } elseif (in_array('ROLE_RESPONSABLE_ETUDIANT', $roles)) {
            return 'responsable';
        }
        
        return 'user';
    }
}
