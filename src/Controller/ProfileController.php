<?php
// src/Controller/ProfileController.php

namespace App\Controller;

use App\Entity\Profil;
use App\Entity\User;
use App\Form\ProfilType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[IsGranted('ROLE_USER')]
#[Route('/profil')]
class ProfileController extends AbstractController
{
    #[Route('/mon-profil', name: 'app_profile')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $profil = $user->getProfil();

        if (!$profil) {
            $profil = new Profil();
            $profil->setUser($user);
        }

        return $this->render('profile/indexP.html.twig', [
            'user' => $user,
            'profil' => $profil,
        ]);
    }

    #[Route('/modifier', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $profil = $user->getProfil();

        if (!$profil) {
            $profil = new Profil();
            $profil->setUser($user);
        }

        $form = $this->createForm(ProfilType::class, $profil, [
            'role' => $user->getRole()
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Le champ photoFile est automatiquement géré par VichUploader
            // Pas besoin de code supplémentaire pour l'upload

            if (!$profil->getProfilId()) {
                $entityManager->persist($profil);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
            'profil' => $profil,
        ]);
    }

    #[Route('/changer-mot-de-passe', name: 'app_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $currentPassword = $request->request->get('current_password');
            $newPassword = $request->request->get('new_password');
            $confirmPassword = $request->request->get('confirm_password');

            if (!$currentPassword || !$newPassword || !$confirmPassword) {
                $this->addFlash('error', 'Tous les champs sont obligatoires.');
                return $this->redirectToRoute('app_change_password');
            }

            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
                return $this->redirectToRoute('app_change_password');
            }

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les nouveaux mots de passe ne correspondent pas.');
                return $this->redirectToRoute('app_change_password');
            }

            if (strlen($newPassword) < 6) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
                return $this->redirectToRoute('app_change_password');
            }

            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);

            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été changé avec succès !');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/change_password.html.twig', [
            'user' => $user,
        ]);
    }

}