<?php

namespace App\Controller;

use App\Entity\SuiviTraitement;
use App\Entity\Traitement;
use App\Entity\User;
use App\Form\SuiviTraitementType;
use App\Repository\SuiviTraitementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Security;

#[Route('/suivi-traitements')]
class SuiviTraitementController extends AbstractController
{
    private Security $security;
    private EntityManagerInterface $entityManager;

    public function __construct(Security $security, EntityManagerInterface $entityManager)
    {
        $this->security = $security;
        $this->entityManager = $entityManager;
    }

    #[Route('/selectionner-patient', name: 'app_suivi_traitement_select_patient', methods: ['GET'])]
    public function selectPatient(EntityManagerInterface $entityManager): Response
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        if (!$this->isGranted('ROLE_PSYCHOLOGUE')) {
            throw $this->createAccessDeniedException('Accès réservé aux psychologues');
        }

        // Récupérer tous les étudiants uniques ayant des traitements avec ce psychologue
        $patientsData = $entityManager->getRepository(Traitement::class)->createQueryBuilder('t')
            ->leftJoin('t.etudiant', 'e')
            ->addSelect('e', 'COUNT(t.traitement_id) as nombreTraitements')
            ->where('t.psychologue = :user')
            ->andWhere('t.etudiant IS NOT NULL')
            ->setParameter('user', $user)
            ->groupBy('e.user_id')
            ->getQuery()
            ->getResult();
        
        // Transformer les données pour le template
        $patients = [];
        foreach ($patientsData as $data) {
            $patient = $data[0];
            $patient->nombreTraitements = $data['nombreTraitements'];
            $patients[] = $patient;
        }
        
        return $this->render('suivi_traitement/select_patient.html.twig', [
            'patients' => $patients
        ]);
    }

    #[Route('/selectionner-traitement/{patient_id}', name: 'app_suivi_traitement_select_traitement_by_patient', methods: ['GET'])]
    public function selectTraitementByPatient(EntityManagerInterface $entityManager, int $patient_id): Response
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        if (!$this->isGranted('ROLE_PSYCHOLOGUE')) {
            throw $this->createAccessDeniedException('Accès réservé aux psychologues');
        }

        // Récupérer le patient
        $patient = $entityManager->getRepository(User::class)->find($patient_id);
        if (!$patient) {
            throw $this->createNotFoundException('Patient non trouvé');
        }

        // Récupérer les traitements de ce patient avec ce psychologue
        $traitements = $entityManager->getRepository(Traitement::class)->createQueryBuilder('t')
            ->leftJoin('t.etudiant', 'e')
            ->leftJoin('t.psychologue', 'p')
            ->addSelect('e', 'p')
            ->where('t.psychologue = :user')
            ->andWhere('t.etudiant = :patient')
            ->setParameter('user', $user)
            ->setParameter('patient', $patient)
            ->getQuery()
            ->getResult();
        
        return $this->render('suivi_traitement/select_traitement.html.twig', [
            'traitements' => $traitements,
            'patient' => $patient
        ]);
    }

    #[Route('/selectionner-traitement', name: 'app_suivi_traitement_select_traitement', methods: ['GET'])]
    public function selectTraitement(EntityManagerInterface $entityManager): Response
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        if ($this->isGranted('ROLE_PSYCHOLOGUE')) {
            $traitements = $entityManager->getRepository(Traitement::class)->createQueryBuilder('t')
                ->leftJoin('t.etudiant', 'e')
                ->leftJoin('t.psychologue', 'p')
                ->addSelect('e', 'p')
                ->where('t.psychologue = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getResult();
        } elseif ($this->isGranted('ROLE_ETUDIANT')) {
            $traitements = $entityManager->getRepository(Traitement::class)->createQueryBuilder('t')
                ->leftJoin('t.etudiant', 'e')
                ->leftJoin('t.psychologue', 'p')
                ->addSelect('e', 'p')
                ->where('t.etudiant = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getResult();
        } else {
            $traitements = [];
        }
        
        return $this->render('suivi_traitement/select_traitement.html.twig', [
            'traitements' => $traitements
        ]);
    }

    #[Route('/', name: 'app_suivi_traitement_index', methods: ['GET'])]
    public function index(SuiviTraitementRepository $suiviTraitementRepository, Request $request, PaginatorInterface $paginator): Response
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer les paramètres de tri et filtrage
        $sort = $request->query->get('sort', 'date');
        $order = $request->query->get('order', 'asc');
        $search = $request->query->get('search', '');
        $statutFilter = $request->query->get('statut', '');
        $ressentiFilter = $request->query->get('ressenti', '');
        $dateFilter = $request->query->get('date', '');
        $page = $request->query->getInt('page', 1);

        // Construire la requête de base
        $qb = $suiviTraitementRepository->createQueryBuilder('s')
            ->leftJoin('s.traitement', 't')
            ->leftJoin('t.etudiant', 'e')
            ->leftJoin('t.psychologue', 'p')
            ->addSelect('t', 'e', 'p');

        // Appliquer le filtrage selon le rôle
        if ($this->isGranted('ROLE_RESPONSABLE_ETUDIANT')) {
            // Le responsable étudiant peut voir tous les suivis des étudiants (consultation uniquement)
        } elseif ($this->isGranted('ROLE_PSYCHOLOGUE')) {
            $qb->where('t.psychologue = :user')
               ->setParameter('user', $user);
        } elseif ($this->isGranted('ROLE_ETUDIANT')) {
            // L'étudiant ne voit que ses propres suivis
            $qb->where('t.etudiant = :user')
               ->setParameter('user', $user);
        } else {
            // Les autres rôles ne voient que leurs propres suivis
            $qb->where('t.etudiant = :user OR t.psychologue = :user')
               ->setParameter('user', $user);
        }

        // Appliquer les filtres
        if (!empty($search)) {
            $qb->andWhere('(s.observations LIKE :search OR s.observationsPsy LIKE :search OR t.titre LIKE :search OR e.nom LIKE :search OR e.prenom LIKE :search OR CONCAT(e.nom, \' \', e.prenom) LIKE :search)')
               ->setParameter('search', '%' . $search . '%');
        }

        if (!empty($statutFilter)) {
            if ($statutFilter === 'effectue') {
                $qb->andWhere('s.effectue = true');
            } elseif ($statutFilter === 'non_effectue') {
                $qb->andWhere('s.effectue = false');
            }
        }

        if (!empty($ressentiFilter)) {
            $qb->andWhere('s.ressenti = :ressenti')
               ->setParameter('ressenti', $ressentiFilter);
        }

        if (!empty($dateFilter)) {
            $date = new \DateTime($dateFilter);
            $qb->andWhere('s.dateSuivi = :date')
               ->setParameter('date', $date);
        }

        // Appliquer le tri - toujours trier par patient puis traitement pour l'organisation hiérarchique
        switch ($sort) {
            case 'date':
                $qb->orderBy('e.nom', 'asc')
                   ->addOrderBy('e.prenom', 'asc')
                   ->addOrderBy('t.titre', 'asc')
                   ->addOrderBy('s.dateSuivi', $order);
                break;
            case 'traitement':
                $qb->orderBy('e.nom', 'asc')
                   ->addOrderBy('e.prenom', 'asc')
                   ->addOrderBy('t.titre', $order)
                   ->addOrderBy('s.dateSuivi', 'asc');
                break;
            case 'patient':
                $qb->orderBy('e.nom', $order)
                   ->addOrderBy('e.prenom', $order)
                   ->addOrderBy('t.titre', 'asc')
                   ->addOrderBy('s.dateSuivi', 'asc');
                break;
            case 'ressenti':
                $qb->orderBy('e.nom', 'asc')
                   ->addOrderBy('e.prenom', 'asc')
                   ->addOrderBy('t.titre', 'asc')
                   ->addOrderBy('s.ressenti', $order)
                   ->addOrderBy('s.dateSuivi', 'asc');
                break;
            case 'evaluation':
                $qb->orderBy('e.nom', 'asc')
                   ->addOrderBy('e.prenom', 'asc')
                   ->addOrderBy('t.titre', 'asc')
                   ->addOrderBy('s.evaluation', $order)
                   ->addOrderBy('s.dateSuivi', 'asc');
                break;
            case 'statut':
                $qb->orderBy('e.nom', 'asc')
                   ->addOrderBy('e.prenom', 'asc')
                   ->addOrderBy('t.titre', 'asc')
                   ->addOrderBy('s.effectue', $order)
                   ->addOrderBy('s.valide', $order)
                   ->addOrderBy('s.dateSuivi', 'asc');
                break;
            default:
                $qb->orderBy('e.nom', 'asc')
                   ->addOrderBy('e.prenom', 'asc')
                   ->addOrderBy('t.titre', 'asc')
                   ->addOrderBy('s.dateSuivi', 'asc');
        }

        // Paginer les résultats avec filtres mais sans tri automatique
        $query = $qb->getQuery();
        
        // Créer une pagination avec filtres activés mais tri désactivé
        $suivis = $paginator->paginate(
            $query,
            $page,
            10,
            [
                'sortFieldParameterName' => 'disabled_sort',      // Désactiver le tri
                'sortDirectionParameterName' => 'disabled_order',   // Désactiver le tri
                'filterFieldParameterName' => 'search',          // Réactiver la recherche
                'filterValueParameterName' => 'search_value',    // Réactiver la recherche
                'distinct' => true
            ]
        );
        
        // S'assurer que le tri est désactivé mais les filtres activés
        $suivis->setParam('disabled_sort', null);
        $suivis->setParam('disabled_order', null);

        return $this->render('suivi_traitement/index.html.twig', [
            'suivis' => $suivis,
            'user_role' => $this->getMainRole($user),
            'sort' => $sort,
            'order' => $order,
            'stats' => $this->getGlobalSuiviStats($user), // Ajouter les vraies statistiques
            'filters' => [
                'search' => $search,
                'statut' => $statutFilter,
                'ressenti' => $ressentiFilter,
                'date' => $dateFilter
            ]
        ]);
    }

    /**
     * Obtenir les statistiques globales de tous les suivis (non paginés)
     */
    private function getGlobalSuiviStats($user): array
    {
        $user_role = $this->getMainRole($user);
        
        $qb = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from('App\Entity\SuiviTraitement', 's')
            ->leftJoin('s.traitement', 't')
            ->leftJoin('t.etudiant', 'e')
            ->leftJoin('t.psychologue', 'p');
        
        // Appliquer les filtres de rôle selon le type d'utilisateur
        if ($user_role === 'psychologue') {
            $qb->where('t.psychologue = :user')
               ->setParameter('user', $user);
        } elseif ($user_role === 'etudiant') {
            $qb->where('e.user_id = :user')
               ->setParameter('user', $user);
        }
        // Pour admin et responsable_etudiant, on voit tout
        
        // Obtenir tous les suivis (sans pagination)
        $allSuivis = $qb->getQuery()->getResult();
        
        // Calculer les statistiques
        $stats = [
            'total' => count($allSuivis),
            'effectues' => 0,
            'en_attente' => 0,
            'valides' => 0
        ];
        
        foreach ($allSuivis as $suivi) {
            if ($suivi->isEffectue()) {
                $stats['effectues']++;
            }
            
            if (!$suivi->isValide()) {
                $stats['en_attente']++;
            }
            
            if ($suivi->isValide()) {
                $stats['valides']++;
            }
        }
        
        return $stats;
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

    #[Route('/nouveau/{traitement_id}', name: 'app_suivi_traitement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, int $traitement_id): Response
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        $traitement = $entityManager->getRepository(Traitement::class)->find($traitement_id);
        
        if (!$traitement) {
            throw $this->createNotFoundException('Traitement non trouvé');
        }

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
            // Assurer que les champs non utilisés ont une valeur par défaut
            if ($this->isGranted('ROLE_ETUDIANT')) {
                $suivi->setValide(false);
                $suivi->setSaisiPar('etudiant');
                $suivi->setObservationsPsy(''); // Champ non utilisé par l'étudiant
                if ($suivi->isEffectue()) {
                    $suivi->setHeureEffective(new \DateTime());
                }
            } else {
                $suivi->setSaisiPar('psychologue');
                $suivi->setObservations(''); // Champ non utilisé par le psychologue
            }
            
            $entityManager->persist($suivi);
            $entityManager->flush();

            $this->addFlash('success', 'Le suivi a été créé avec succès.');

            return $this->redirectToRoute('app_traitement_show', ['id' => $traitement->getId()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Le formulaire contient des erreurs. Veuillez corriger les champs obligatoires.');
        }

        return $this->render('suivi_traitement/new.html.twig', [
            'suivi' => $suivi,
            'traitement' => $traitement,
            'form' => $form->createView(),
            'user_role' => $this->getMainRole($user)
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_suivi_traitement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SuiviTraitement $suivi, EntityManagerInterface $entityManager): Response
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
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

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Le formulaire contient des erreurs. Veuillez corriger les champs obligatoires.');
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
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        if (!$this->canEditSuivi($suivi, $user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce suivi');
        }

        if ($this->isCsrfTokenValid('effectuer'.$suivi->getId(), $request->request->get('_token'))) {
            $suivi->setEffectue(true);
            $suivi->setHeureEffective(new \DateTime());
            
            if ($this->isGranted('ROLE_ETUDIANT')) {
                $suivi->setValide(false);
            } else {
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

    #[Route('/{id}/supprimer', name: 'app_suivi_traitement_delete', methods: ['POST'])]
    public function delete(Request $request, SuiviTraitement $suivi, EntityManagerInterface $entityManager): Response
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
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

    private function canAccessSuivi(SuiviTraitement $suivi, User $user): bool
    {
        return $this->canAccessTraitement($suivi->getTraitement(), $user);
    }

    private function canEditSuivi(SuiviTraitement $suivi, User $user): bool
    {
        // Psychologue peut modifier les suivis de ses traitements
        if ($this->isGranted('ROLE_PSYCHOLOGUE') && $suivi->getTraitement()->getPsychologue() === $user) {
            return true;
        }

        // Étudiant peut modifier ses propres suivis
        if ($this->isGranted('ROLE_ETUDIANT') && $suivi->getTraitement()->getEtudiant() === $user) {
            return true;
        }

        return false;
    }

    private function canAccessTraitement(Traitement $traitement, User $user): bool
    {
        // Responsable étudiant peut voir tous les traitements
        if ($this->isGranted('ROLE_RESPONSABLE_ETUDIANT')) {
            return true;
        }

        // Psychologue ne voit que ses propres traitements
        if ($this->isGranted('ROLE_PSYCHOLOGUE')) {
            return $traitement->getPsychologue() === $user;
        }

        // Étudiant ne voit que ses propres traitements
        if ($this->isGranted('ROLE_ETUDIANT')) {
            return $traitement->getEtudiant() === $user;
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

    #[Route('/{id}', name: 'app_suivi_traitement_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, SuiviTraitementRepository $suiviTraitementRepository): Response
    {
        // Vérification supplémentaire pour s'assurer que l'ID est bien un entier
        if (!is_numeric($id) || (int)$id != $id) {
            throw $this->createNotFoundException('ID invalide');
        }
        
        $suivi = $suiviTraitementRepository->find($id);
        
        if (!$suivi) {
            throw $this->createNotFoundException('Suivi non trouvé pour l\'ID: ' . $id);
        }

        $user = $this->security->getUser();

        if (!$this->canAccessSuivi($suivi, $user)) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('suivi_traitement/show.html.twig', [
            'suivi' => $suivi,
            'user_role' => $this->getMainRole($user),
            'can_edit' => $this->canEditSuivi($suivi, $user),
            'can_validate' => $this->canValidateSuivi($suivi, $user)
        ]);
    }
}