<?php
// src/Controller/Dashboard/PsychologueController.php

namespace App\Controller\Dashboard;

use App\Entity\User;
use App\Repository\ConsultationRepository;
use App\Repository\RendezVousRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/psy')]
#[IsGranted('ROLE_PSYCHOLOGUE')]
class PsychologueController extends AbstractController
{
    #[Route('', name: 'app_dashboard_psy')]
    public function index(
        RendezVousRepository $rdvRepository,
        ConsultationRepository $consultationRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Statistiques pour le dashboard
        $stats = [
            'rdv_aujourdhui' => $rdvRepository->count([
                'psy' => $user,
                // Ajouter un filtre pour aujourd'hui si vous avez la méthode
            ]),
            'patients_actifs' => count($user->getConsultationsPsy()),
            'rdv_en_attente' => $rdvRepository->count([
                'psy' => $user,
                // Ajouter statut "en_attente" si vous l'avez
            ]),
        ];

        // Prochains rendez-vous
        $prochainsRdv = $rdvRepository->findByPsyOrderedByDate($user, 'ASC', 5);

        return $this->render('dashboard/psychologue/index.html.twig', [
            'user' => $user,
            'stats' => $stats,
            'prochains_rdv' => $prochainsRdv,
        ]);
    }

    #[Route('/consultations', name: 'app_psy_consultations')]
    public function consultations(ConsultationRepository $consultationRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $consultations = $consultationRepository->findBy(
            ['psy' => $user],
            ['date_consultation' => 'DESC']
        );

        return $this->render('dashboard/psychologue/consultations.html.twig', [
            'user' => $user,
            'consultations' => $consultations,
        ]);
    }

    #[Route('/patients', name: 'app_psy_patients')]
    public function patients(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Récupérer tous les étudiants uniques qui ont eu des consultations avec ce psy
        $consultations = $user->getConsultationsPsy();
        $patients = [];

        foreach ($consultations as $consultation) {
            $etudiant = $consultation->getEtudiant();
            if ($etudiant && !isset($patients[$etudiant->getUserId()])) {
                $patients[$etudiant->getUserId()] = $etudiant;
            }
        }

        return $this->render('dashboard/psychologue/patients.html.twig', [
            'user' => $user,
            'patients' => $patients,
        ]);
    }

    #[Route('/rendez-vous', name: 'app_psy_rendez_vous')]
    public function rendezVous(RendezVousRepository $rdvRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $rendezVous = $rdvRepository->findByPsyOrderedByDate($user, 'DESC');

        return $this->render('psy/rendezvous/index.html.twig', [
            'user' => $user,
            'rendez_vous' => $rendezVous,
        ]);
    }

    #[Route('/profil', name: 'app_psy_profil')]
    public function profil(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('dashboard/psychologue/profil.html.twig', [
            'user' => $user,
        ]);
    }
}
