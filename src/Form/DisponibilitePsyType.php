<?php
// src/Form/DisponibiliteType.php

namespace App\Form;

use App\Entity\DisponibilitePsy;
use App\Enum\TypeConsultation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class DisponibiliteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date_dispo', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date',
                'attr' => [
                    'class' => 'form-control',
                    'min' => (new \DateTime())->format('Y-m-d'),
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez sélectionner une date.']),
                    new Assert\GreaterThanOrEqual([
                        'value' => 'today',
                        'message' => 'La date doit être aujourd\'hui ou dans le futur.'
                    ])
                ]
            ])
            ->add('heure_debut', TimeType::class, [
                'widget' => 'single_text',
                'label' => 'Heure de début',
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez sélectionner une heure de début.'])
                ]
            ])
            ->add('heure_fin', TimeType::class, [
                'widget' => 'single_text',
                'label' => 'Heure de fin',
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez sélectionner une heure de fin.'])
                ]
            ])
            ->add('type_consult', ChoiceType::class, [
                'label' => 'Type de consultation',
                'choices' => [
                    'Présentiel' => TypeConsultation::PRESENTIEL->value,
                    'En ligne' => TypeConsultation::EN_LIGNE->value,
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez sélectionner un type de consultation.'])
                ]
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Bureau 203, Bâtiment A'
                ]
            ]);
    }

    

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DisponibilitePsy::class,
        ]);
    }
}