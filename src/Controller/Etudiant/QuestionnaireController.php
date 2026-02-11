<?php
// src/Controller/Etudiant/QuestionnaireController.php

namespace App\Controller\Etudiant;

use App\Entity\Questionnaire;
use App\Entity\ReponseQuestionnaire;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/etudiant/questionnaire')]
class QuestionnaireController extends AbstractController
{
    #[Route('/liste', name: 'etudiant_questionnaire_liste')]
    public function liste(Request $request, EntityManagerInterface $em): Response
    {
        $typeSelectionne = $request->query->get('type', 'tous');
        
        // ✅ CORRECTION : Utiliser 'nbre_questions' (nom de la propriété PHP), pas 'nbreQuestions'
        $qb = $em->getRepository(Questionnaire::class)
            ->createQueryBuilder('q')
            ->where('q.nbre_questions > 0');
        
        // Filtrer par type si spécifié
        if ($typeSelectionne !== 'tous' && $typeSelectionne !== '') {
            $qb->andWhere('q.type = :type')
               ->setParameter('type', strtoupper($typeSelectionne));
        }
        
        $questionnaires = $qb->orderBy('q.nom', 'ASC')->getQuery()->getResult();
        
        // Identifier les questionnaires déjà complétés par l'étudiant
        $etudiant = $this->getUser();
        $questionnairesCompletesIds = [];
        
        if ($etudiant) {
            $mesReponses = $em->getRepository(ReponseQuestionnaire::class)
                ->findBy(['etudiant' => $etudiant]);
            
            foreach ($mesReponses as $reponse) {
                if ($questionnaire = $reponse->getQuestionnaire()) {
                    $questionnairesCompletesIds[] = $questionnaire->getId();
                }
            }
        }
        
        // Types disponibles pour les filtres
        $typesDisponibles = [];
        $typesQuery = $em->createQuery('SELECT DISTINCT q.type FROM App\Entity\Questionnaire q WHERE q.nbre_questions > 0 ORDER BY q.type');
        $typesResult = $typesQuery->getResult();
        
        foreach ($typesResult as $type) {
            if (isset($type['type'])) {
                $typesDisponibles[] = $type['type'];
            }
        }
        
        return $this->render('etudiant/questionnaire/liste.html.twig', [
            'questionnaires' => $questionnaires,
            'type_selectionne' => $typeSelectionne,
            'types_disponibles' => $typesDisponibles,
            'questionnaires_completes_ids' => $questionnairesCompletesIds,
        ]);
    }
    
#[Route('/passer/{id}', name: 'etudiant_questionnaire_passer')]
public function passer(Questionnaire $questionnaire, EntityManagerInterface $em): Response
{
    // Vérifier que le questionnaire a des questions
    if ($questionnaire->getNbreQuestions() === 0 || $questionnaire->getQuestions()->count() === 0) {
        $this->addFlash('warning', 'Ce questionnaire n\'a pas encore de questions.');
        return $this->redirectToRoute('etudiant_questionnaire_liste');
    }
    
    // Vérifier si l'étudiant est déjà connecté
    $etudiant = $this->getUser();
    
    // Vérifier si déjà répondu (seulement si connecté)
    if ($etudiant) {
        $dejaRepondu = $em->getRepository(ReponseQuestionnaire::class)
            ->findOneBy([
                'questionnaire' => $questionnaire,
                'etudiant' => $etudiant
            ]);
        
        if ($dejaRepondu) {
            $this->addFlash('warning', 'Vous avez déjà répondu à ce questionnaire. Vous pouvez voir vos résultats dans "Mes réponses".');
            return $this->redirectToRoute('etudiant_mes_reponses_show', ['id' => $dejaRepondu->getId()]);
        }
    }
    
    // CORRECTION : Utiliser 'question_id' au lieu de 'id'
    $questions = $em->getRepository(Question::class)
        ->createQueryBuilder('q')
        ->where('q.questionnaire = :questionnaire')
        ->setParameter('questionnaire', $questionnaire)
        ->orderBy('q.question_id', 'ASC') // ← CORRECTION ICI
        ->getQuery()
        ->getResult();
    
    return $this->render('etudiant/questionnaire/passer.html.twig', [
        'questionnaire' => $questionnaire,
        'questions' => $questions,
    ]);
}
    
    #[Route('/soumettre/{id}', name: 'etudiant_questionnaire_soumettre', methods: ['POST'])]
    public function soumettre(Request $request, Questionnaire $questionnaire, EntityManagerInterface $em): Response
    {
        // === CONTRÔLE DE SAISIE 1 : Vérification de l'authentification ===
        $etudiant = $this->getUser();
        if (!$etudiant) {
            $this->addFlash('error', 'Vous devez être connecté pour soumettre un questionnaire.');
            return $this->redirectToRoute('etudiant_questionnaire_passer', ['id' => $questionnaire->getId()]);
        }
        
        // === CONTRÔLE DE SAISIE 2 : Vérification des questions ===
        if ($questionnaire->getNbreQuestions() === 0 || $questionnaire->getQuestions()->count() === 0) {
            $this->addFlash('error', 'Ce questionnaire n\'a pas de questions valides.');
            return $this->redirectToRoute('etudiant_questionnaire_liste');
        }
        
        // === CONTRÔLE DE SAISIE 3 : Vérification si déjà répondu ===
        $dejaRepondu = $em->getRepository(ReponseQuestionnaire::class)
            ->findOneBy([
                'questionnaire' => $questionnaire,
                'etudiant' => $etudiant
            ]);
        
        if ($dejaRepondu) {
            $this->addFlash('error', 'Vous avez déjà soumis une réponse à ce questionnaire.');
            return $this->redirectToRoute('etudiant_mes_reponses_show', [
                'id' => $dejaRepondu->getReponseQuestionnaireId()
            ]);
        }
        
        // === CONTRÔLE DE SAISIE 4 : Récupération et validation des données POST ===
       $reponsesData = $request->request->all('reponses');
        
        if (empty($reponsesData)) {
            $this->addFlash('error', 'Aucune réponse n\'a été soumise. Veuillez répondre aux questions avant de soumettre.');
            return $this->redirectToRoute('etudiant_questionnaire_passer', ['id' => $questionnaire->getId()]);
        }
        
        // === CONTRÔLE DE SAISIE 5 : Validation détaillée des réponses ===
        $questions = $questionnaire->getQuestions();
        $reponsesValides = [];
        $erreurs = [];
        $questionsRepondues = 0;
        
        foreach ($questions as $question) {
            $questionId = $question->getId();
            
            // Vérifier si la question a une réponse
            if (!isset($reponsesData[$questionId])) {
                $erreurs[] = sprintf(
                    'Question "%s" n\'a pas de réponse.',
                    substr($question->getTexte(), 0, 50) . (strlen($question->getTexte()) > 50 ? '...' : '')
                );
                continue;
            }
            
            $valeur = $reponsesData[$questionId];
            
            // === CONTRÔLE DE SAISIE 6 : Validation du type de données ===
            if (!is_numeric($valeur)) {
                $erreurs[] = sprintf(
                    'La réponse à la question "%s" doit être un nombre.',
                    substr($question->getTexte(), 0, 50) . (strlen($question->getTexte()) > 50 ? '...' : '')
                );
                continue;
            }
            
            $valeur = (int) $valeur;
            
            // === CONTRÔLE DE SAISIE 7 : Validation de la plage de valeurs ===
            $optionsScores = $question->getScoreOptions();
            
            if (empty($optionsScores)) {
                // Valeurs par défaut : 0-4 (échelle Likert standard)
                if ($valeur < 0 || $valeur > 4) {
                    $erreurs[] = sprintf(
                        'Le score pour la question "%s" doit être entre 0 et 4 (vous avez saisi: %d).',
                        substr($question->getTexte(), 0, 50) . (strlen($question->getTexte()) > 50 ? '...' : ''),
                        $valeur
                    );
                    continue;
                }
            } else {
                // Vérifier si la valeur correspond à un score valide prédéfini
                if (!in_array($valeur, $optionsScores, true)) {
                    $erreurs[] = sprintf(
                        'Le score pour la question "%s" doit être une des valeurs autorisées: %s.',
                        substr($question->getTexte(), 0, 50) . (strlen($question->getTexte()) > 50 ? '...' : ''),
                        implode(', ', $optionsScores)
                    );
                    continue;
                }
            }
            
            // Si toutes les validations sont passées
            $reponsesValides[$questionId] = $valeur;
            $questionsRepondues++;
        }
        
        // === CONTRÔLE DE SAISIE 8 : Vérification du nombre de questions répondues ===
        $totalQuestions = count($questions);
        if ($questionsRepondues < $totalQuestions) {
            $this->addFlash('warning', 
                sprintf('Vous avez répondu à %d questions sur %d. Les questions manquantes seront ignorées.',
                    $questionsRepondues, $totalQuestions)
            );
        }
        
        // === CONTRÔLE DE SAISIE 9 : Gestion des erreurs de validation ===
        if (!empty($erreurs)) {
            $messageErreur = 'Des erreurs de validation ont été détectées:<br>';
            $messageErreur .= implode('<br>', array_slice($erreurs, 0, 5));
            
            if (count($erreurs) > 5) {
                $messageErreur .= sprintf('<br>... et %d autres erreurs.', count($erreurs) - 5);
            }
            
            $this->addFlash('error', $messageErreur);
            return $this->redirectToRoute('etudiant_questionnaire_passer', ['id' => $questionnaire->getId()]);
        }
        
        // === CONTRÔLE DE SAISIE 10 : Vérification minimale des réponses ===
        if ($questionsRepondues === 0) {
            $this->addFlash('error', 'Aucune réponse valide n\'a été soumise.');
            return $this->redirectToRoute('etudiant_questionnaire_passer', ['id' => $questionnaire->getId()]);
        }
        
        try {
            // === CONTRÔLE DE SAISIE 11 : Création de l'entité avec validation ===
            $reponseQuestionnaire = ReponseQuestionnaire::createFromResponses(
                $questionnaire,
                $etudiant,
                $reponsesValides,
                null, // durée (optionnelle)
                sprintf('Soumis via l\'interface étudiante le %s', date('d/m/Y à H:i'))
            );
            
            // Validation supplémentaire de l'entité créée
            if (!$reponseQuestionnaire->getQuestionnaire()) {
                throw new \RuntimeException('Questionnaire non associé à la réponse.');
            }
            
            if (!$reponseQuestionnaire->getEtudiant()) {
                throw new \RuntimeException('Étudiant non associé à la réponse.');
            }
            
            if ($reponseQuestionnaire->getScoreTotal() < 0) {
                throw new \RuntimeException('Score total invalide.');
            }
            
            // === CONTRÔLE DE SAISIE 12 : Persistance et sauvegarde ===
            $em->persist($reponseQuestionnaire);
            $em->flush();
            
            // === CONTRÔLE DE SAISIE 13 : Vérification post-sauvegarde ===
            $idSauvegarde = $reponseQuestionnaire->getReponseQuestionnaireId();
            if (!$idSauvegarde) {
                throw new \RuntimeException('Erreur lors de l\'enregistrement : ID non généré.');
            }
            
            // Message de succès avec informations
            $scoreTotal = $reponseQuestionnaire->getScoreTotal();
            $scoreMaxPossible = $questionnaire->getNbreQuestions() * 4; // 4 étant le score max par question
            $pourcentage = $scoreMaxPossible > 0 ? round(($scoreTotal / $scoreMaxPossible) * 100, 1) : 0;
            
            $this->addFlash('success', 
                sprintf('Questionnaire soumis avec succès !<br>Score: %.1f/%d (%.1f%%)<br>Niveau: %s',
                    $scoreTotal, $scoreMaxPossible, $pourcentage, $reponseQuestionnaire->getNiveau())
            );
            
            // Redirection vers la page de détails de la réponse
            return $this->redirectToRoute('etudiant_mes_reponses_show', [
                'id' => $idSauvegarde
            ]);
            
        } catch (\InvalidArgumentException $e) {
            // Erreurs de validation métier
            $this->addFlash('error', 'Erreur de validation : ' . $e->getMessage());
            return $this->redirectToRoute('etudiant_questionnaire_passer', ['id' => $questionnaire->getId()]);
            
        } catch (\RuntimeException $e) {
            // Erreurs d'exécution
            $this->addFlash('error', 'Erreur lors du traitement : ' . $e->getMessage());
            return $this->redirectToRoute('etudiant_questionnaire_passer', ['id' => $questionnaire->getId()]);
            
        } catch (\Exception $e) {
            // Erreurs générales (base de données, etc.)
            $this->addFlash('error', 
                'Une erreur technique est survenue lors de l\'enregistrement. ' .
                'Veuillez réessayer. Détails: ' . $e->getMessage()
            );
            return $this->redirectToRoute('etudiant_questionnaire_passer', ['id' => $questionnaire->getId()]);
        }
    }
    
    #[Route('/detail/{id}', name: 'etudiant_questionnaire_detail')]
    public function detail(Questionnaire $questionnaire, EntityManagerInterface $em): Response
    {
        // Vérifier que le questionnaire a des questions
        if ($questionnaire->getNbreQuestions() === 0) {
            $this->addFlash('warning', 'Ce questionnaire n\'a pas encore de questions.');
            return $this->redirectToRoute('etudiant_questionnaire_liste');
        }
        
        // Récupérer les statistiques du questionnaire
        $statistiques = $questionnaire->getStatistiquesReponses();
        
        // Récupérer les questions
        $questions = $em->getRepository(Question::class)
            ->createQueryBuilder('q')
            ->where('q.questionnaire = :questionnaire')
            ->setParameter('questionnaire', $questionnaire)
            ->orderBy('q.questionId', 'ASC')
            ->getQuery()
            ->getResult();
        
        return $this->render('etudiant/questionnaire/detail.html.twig', [
            'questionnaire' => $questionnaire,
            'questions' => $questions,
            'statistiques' => $statistiques,
        ]);
    }
}