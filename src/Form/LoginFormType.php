<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

class LoginFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new NotBlank([
                        'message' => 'L\'adresse email est obligatoire.',
                    ]),
                    new Email([
                        'message' => 'Veuillez entrer une adresse email valide.',
                    ]),
                    new Length([
                        'max'        => 180,
                        'maxMessage' => 'L\'email ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
                'attr' => [
                    'placeholder'  => 'votre@email.fr',
                    'class'        => 'form-control',
                    'autocomplete' => 'email',
                ],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le mot de passe est obligatoire.',
                    ]),
                    new Length([
                        'min'        => 8,
                        'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        'max'        => 4096,
                        'maxMessage' => 'Le mot de passe est trop long.',
                    ]),
                ],
                'attr' => [
                    'class'        => 'form-control',
                    'autocomplete' => 'current-password',
                    'placeholder'  => 'Votre mot de passe',
                ],
            ])
            ->add('_remember_me', CheckboxType::class, [
                'label'    => 'Se souvenir de moi',
                'required' => false,
                'mapped'   => false,
                'attr'     => ['class' => 'form-check-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_field_name' => '_csrf_token',
            'csrf_token_id'   => 'authenticate',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
