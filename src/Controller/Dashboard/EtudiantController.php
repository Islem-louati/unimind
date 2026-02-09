<?php
// src/Controller/Dashboard/EtudiantController.php

namespace App\Controller\Dashboard;

use App\Entity\User;
use App\Repository\RendezVousRepository;
use App\Repository\ConsultationRepository;
use App\Repository\TraitementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/etudiant')]
#[IsGranted('ROLE_ETUDIANT')]
class EtudiantController extends AbstractController
{
    #[Route('', name: 'app_dashboard_etudiant')]
    public function index(RendezVousRepository $rdvRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Récupérer les prochains rendez-vous
        $prochainsRdv = $rdvRepository->findProchainsRendezVous($user);
        
        // Calculer les statistiques
        $tousRdv = $rdvRepository->findBy(['etudiant' => $user]);
        
        $stats = [
            'prochains_rdv' => count($prochainsRdv),
            'total_consultations' => count(array_filter($tousRdv, fn($rdv) => $rdv->isTermine())),
            'traitements_actifs' => 0, // À implémenter si vous avez une entité Traitement
        ];
        
        return $this->render('dashboard/etudiant/index.html.twig', [
            'user' => $user,
            'prochains_rdv' => $prochainsRdv,
            'stats' => $stats,
        ]);
    }
    #[Route('/rendez-vous', name: 'app_etudiant_rdv')]
    public function rendezvous(RendezVousRepository $rdvRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $rendezVous = $rdvRepository->findByEtudiantOrderedByDate($user, 'DESC');

        return $this->render('dashboard/etudiant/rendez-vous.html.twig', [
            'user' => $user,
            'rendez_vous' => $rendezVous,
        ]);
    }

    #[Route('/consultations', name: 'app_etudiant_consultations')]
    public function consultations(ConsultationRepository $consultationRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $consultations = $consultationRepository->findBy(
            ['etudiant' => $user],
            ['date_consultation' => 'DESC']
        );

        return $this->render('dashboard/etudiant/consultations.html.twig', [
            'user' => $user,
            'consultations' => $consultations,
        ]);
    }

    #[Route('/traitements', name: 'app_etudiant_traitements')]
    public function traitements(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('dashboard/etudiant/traitements.html.twig', [
            'user' => $user,
            'traitements_actifs' => $user->getTraitementsActifs(),
            'traitements_termines' => $user->getTraitementsTermines(),
        ]);
    }

    #[Route('/profil', name: 'app_etudiant_profil')]
    public function profil(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('dashboard/etudiant/profil.html.twig', [
            'user' => $user,
        ]);
    }
}
