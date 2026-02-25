<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\RoleType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Types
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

// Contraintes
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Email as EmailAssert;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // COMMUN
            ->add('nom', TextType::class, [
                'label' => 'Nom *',
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est obligatoire.']),
                    new Length(['min' => 2, 'max' => 50]),
                ],
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'Votre nom']
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom *',
                'constraints' => [
                    new NotBlank(['message' => 'Le prénom est obligatoire.']),
                    new Length(['min' => 2, 'max' => 50]),
                ],
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'Votre prénom']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email *',
                'constraints' => [
                    new NotBlank(['message' => 'L\'email est obligatoire.']),
                    new EmailAssert(['message' => 'Veuillez entrer une adresse email valide.']),
                    new Length(['max' => 180]),
                ],
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'votre@email.com']
            ])
            ->add('cin', TextType::class, [
                'label' => 'CIN *',
                'constraints' => [
                    new NotBlank(['message' => 'Le CIN est obligatoire.']),
                    new Regex([
                        'pattern' => '/^[A-Z]{1,2}[0-9]{6}$/',
                        'message' => 'Format CIN invalide (ex: AB123456).',
                    ]),
                ],
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'AB123456']
            ])

            /**
             * ROLE = radios EXPANDED (réels) mais cachés visuellement.
             * - mapped=false : on le lit côté contrôleur
             * - required=false + empty_data='' : pas de NotNull implicite
             * - NotBlank => notre message FR si vide
             */
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'mapped' => false,
                'choices' => [
                    'Étudiant'             => RoleType::ETUDIANT->value,
                    'Psychologue'          => RoleType::PSYCHOLOGUE->value,
                    'Responsable Étudiant' => RoleType::RESPONSABLE_ETUDIANT->value,
                ],
                'expanded' => true,     // radios
                'multiple' => false,
                'required' => false,    // désactive NotNull implicite
                'empty_data' => '',     // si rien => '' (pas null)
                'constraints' => [
                    new NotBlank(['message' => 'Le rôle est obligatoire.'])
                ],
                'attr' => ['class' => 'd-none'],
            ])

            // MOT DE PASSE (plain, mapped=false)
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les mots de passe doivent correspondre.',
                'first_options' => [
                    'label' => 'Mot de passe *',
                    'attr' => [
                        'class' => 'form-control form-control-lg',
                        'placeholder' => 'Mot de passe',
                        'autocomplete' => 'new-password'
                    ],
                    'constraints' => [
                        new NotBlank(['message' => 'Le mot de passe est obligatoire.']),
                        new Length(['min' => 8, 'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.']),
                        new Regex([
                            'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/',
                            'message' => 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.',
                        ])
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe *',
                    'attr' => [
                        'class' => 'form-control form-control-lg',
                        'placeholder' => 'Confirmer mot de passe',
                        'autocomplete' => 'new-password'
                    ]
                ],
            ])

            // ÉTUDIANT
            ->add('identifiant', TextType::class, [
                'label' => 'Identifiant étudiant *',
                'required' => false,
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'Votre identifiant étudiant']
            ])
            ->add('nom_etablissement', TextType::class, [
                'label' => 'Nom de l\'établissement *',
                'required' => false,
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'Nom de votre établissement']
            ])

            // PSY
            ->add('specialite', TextType::class, [
                'label' => 'Spécialité *',
                'required' => false,
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'Votre spécialité']
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse *',
                'required' => false,
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'Votre adresse professionnelle']
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone *',
                'required' => false,
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => '12345678'] // Tunisie: 8 chiffres
            ])

            // RESPONSABLE
            ->add('poste', TextType::class, [
                'label' => 'Poste *',
                'required' => false,
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'Votre poste']
            ])
            ->add('etablissement', TextType::class, [
                'label' => 'Établissement *',
                'required' => false,
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'Nom de l\'établissement']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,

            // ⚠️ Groupe dédié = on n’applique PAS les NotNull automatiques de Doctrine (groupe Default)
            'validation_groups' => ['Registration'],

            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'registration_item',
        ]);
    }
}