<?php
// src/Controller/Etudiant/RendezVousController.php

namespace App\Controller\Etudiant;

use App\Entity\DisponibilitePsy;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Enum\StatutDisponibilite;
use App\Enum\StatutRendezVous;
use App\Repository\DisponibilitePsyRepository;
use App\Repository\RendezVousRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/etudiant/rendez-vous')]
#[IsGranted('ROLE_ETUDIANT')]
class RendezVousController extends AbstractController
{
    #[Route('', name: 'app_etudiant_rdv', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        RendezVousRepository $rdvRepository,
        DisponibilitePsyRepository $dispoRepository,
        UserRepository $userRepository
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Traitement POST (création d'un rendez-vous)
        if ($request->isMethod('POST')) {
            $dispoId = $request->request->get('dispo_id');
            $motif = $request->request->get('motif');
            
            if (!$dispoId) {
                $this->addFlash('error', 'Disponibilité non sélectionnée.');
                return $this->redirectToRoute('app_etudiant_rdv');
            }
            
            try {
                // Récupérer la disponibilité
                $disponibilite = $dispoRepository->find($dispoId);
                
                if (!$disponibilite) {
                    throw new \Exception('Disponibilité non trouvée.');
                }
                
                // Vérifier que la disponibilité est bien disponible
                if (!$disponibilite->isDisponible()) {
                    throw new \Exception('Cette disponibilité n\'est plus disponible.');
                }
                
                // Vérifier que la disponibilité n'est pas passée
                if ($disponibilite->isPassed()) {
                    throw new \Exception('Cette disponibilité est déjà passée.');
                }
                
                // Récupérer le psychologue associé à la disponibilité
                $psy = $disponibilite->getUser();
                if (!$psy || !$psy->isPsychologue()) {
                    throw new \Exception('Psychologue non trouvé.');
                }
                
                // Créer le rendez-vous
                $rendezVous = new RendezVous();
                $rendezVous->setDisponibilite($disponibilite);
                $rendezVous->setEtudiant($user);
                $rendezVous->setPsy($psy);
                $rendezVous->setMotif($motif);
                
                // Marquer la disponibilité comme réservée
                $disponibilite->setStatut(StatutDisponibilite::RESERVE->value);
                
                $em->persist($rendezVous);
                $em->flush();
                
                $this->addFlash('success', 'Rendez-vous créé avec succès ! Il est en attente de confirmation.');
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            }
            
            return $this->redirectToRoute('app_etudiant_rdv');
        }
        
        // GET - Afficher la liste des rendez-vous de l'étudiant
        $rendezVousList = $rdvRepository->findBy(
            ['etudiant' => $user],
            ['created_at' => 'DESC']
        );
        
        // Récupérer les disponibilités disponibles pour prendre rendez-vous
        $now = new \DateTime();
        $disponibilitesDisponibles = $dispoRepository->findDisponibilitesDisponibles($now);
        
        // Filtrer pour ne garder que les disponibilités futures
        $disponibilitesFutures = [];
        foreach ($disponibilitesDisponibles as $dispo) {
            if ($dispo->getDateTimeFin() > $now && $dispo->isDisponible()) {
                $disponibilitesFutures[] = $dispo;
            }
        }
        
        return $this->render('etudiant/rendezvous/index.html.twig', [
            'user' => $user,
            'rendez_vous_list' => $rendezVousList,
            'disponibilites_futures' => $disponibilitesFutures,
            'statuts_rendezvous' => StatutRendezVous::getChoices(),
        ]);
    }

    #[Route('/{id}/annuler', name: 'app_etudiant_rdv_annuler', methods: ['POST'])]
    public function annuler(
        RendezVous $rendezVous,
        Request $request,
        EntityManagerInterface $em
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Vérifier que le rendez-vous appartient à l'étudiant connecté
        if ($rendezVous->getEtudiant() !== $user) {
            throw $this->createAccessDeniedException();
        }
        
        // Vérifier que le rendez-vous peut être annulé
        if (!$rendezVous->canBeCancelled()) {
            $this->addFlash('error', 'Ce rendez-vous ne peut pas être annulé.');
            return $this->redirectToRoute('app_etudiant_rdv');
        }
        
        try {
            // Annuler le rendez-vous
            $rendezVous->annuler();
            
            // Libérer la disponibilité
            $disponibilite = $rendezVous->getDisponibilite();
            if ($disponibilite) {
                $disponibilite->setStatut(StatutDisponibilite::DISPONIBLE->value);
            }
            
            $em->flush();
            
            $this->addFlash('success', 'Rendez-vous annulé avec succès.');
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }
        
        return $this->redirectToRoute('app_etudiant_rdv');
    }

    #[Route('/{id}/detail', name: 'app_etudiant_rdv_detail', methods: ['GET'])]
    public function detail(RendezVous $rendezVous): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Vérifier que le rendez-vous appartient à l'étudiant connecté
        if ($rendezVous->getEtudiant() !== $user) {
            throw $this->createAccessDeniedException();
        }
        
        return $this->render('etudiant/rendezvous/detail.html.twig', [
            'user' => $user,
            'rendez_vous' => $rendezVous,
        ]);
    }
}