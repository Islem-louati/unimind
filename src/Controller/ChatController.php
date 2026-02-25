<?php

namespace App\Controller;

use App\Service\GeminiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ChatController extends AbstractController
{
    #[Route('/chat', name: 'app_chat')]
    public function index(Request $request, GeminiService $geminiService): Response
    {
        // Si le formulaire a été soumis (en POST)
        if ($request->isMethod('POST')) {
            $userMessage = $request->request->get('message');
            if ($userMessage) {
                $botReply = $geminiService->sendMessage($userMessage);
                // En AJAX, on retourne du JSON
                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'reply' => $botReply,
                    ]);
                }
                // Sinon, on recharge la page avec le message (fallback)
                // Mais on préférera l'AJAX pour une meilleure expérience
            }
        }

        // Récupère l'historique depuis la session pour l'afficher
        $history = $request->getSession()->get('chat_history', []);

        return $this->render('etudiant/meditation/index.html.twig', [
            'history' => $history,
        ]);
    }

    #[Route('/chat/clear', name: 'app_chat_clear')]
    public function clear(GeminiService $geminiService): Response
    {
        $geminiService->clearHistory();
        return $this->redirectToRoute('etudiant_meditation_index');
    }
}