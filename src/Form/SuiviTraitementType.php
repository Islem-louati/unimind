<?php

namespace App\Form;

use App\Entity\SuiviTraitement;
use App\Enum\Ressenti;
use App\Enum\SaisiPar;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Vich\UploaderBundle\Form\Type\VichFileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\Options;

class SuiviTraitementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEtudiant = $options['is_etudiant'] ?? false;

        // Champ date de suivi (commun à tous)
        $builder
            ->add('dateSuivi', DateType::class, [
                'label' => 'Date du suivi',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ]);

        // FORMULAIRE ÉTUDIANT
        if ($isEtudiant) {
            $builder
                ->add('ressenti', ChoiceType::class, [
                    'label' => 'Comment vous sentez-vous ?',
                    'choices' => array_combine(
                        array_map(fn($case) => $case->getLabel(), Ressenti::cases()),
                        array_map(fn($case) => $case->value, Ressenti::cases())
                    ),
                    'attr' => [
                        'class' => 'form-control'
                    ]
                ])
                ->add('evaluation', IntegerType::class, [
                    'label' => 'Évaluation (1-10)',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'min' => 1,
                        'max' => 10,
                        'placeholder' => 'Note de 1 à 10'
                    ]
                ])
                ->add('observations', TextareaType::class, [
                    'label' => 'Vos observations',
                    'required' => true,
                    'attr' => [
                        'class' => 'form-control',
                        'rows' => 4,
                        'placeholder' => 'Décrivez comment vous vous sentez, vos progrès, vos difficultés...'
                    ]
                ])
                ->add('effectue', CheckboxType::class, [
                    'label' => 'J\'ai effectué ce suivi',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-check-input'
                    ]
                ]);
        } 
        // FORMULAIRE PSYCHOLOGUE
        else {
            $builder
                ->add('heurePrevue', TimeType::class, [
                    'label' => 'Heure prévue',
                    'widget' => 'single_text',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control'
                    ]
                ])
                ->add('observationsPsy', TextareaType::class, [
                    'label' => 'Observations du psychologue',
                    'required' => true,
                    'attr' => [
                        'class' => 'form-control',
                        'rows' => 4,
                        'placeholder' => 'Notes professionnelles, observations, évolutions...'
                    ]
                ])
                ->add('valide', CheckboxType::class, [
                    'label' => 'Validé par le psychologue',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-check-input'
                    ]
                ]);
        }

        // Champ upload de document (commun à tous)
        $builder
            ->add('documentFile', VichFileType::class, [
                'required' => false,
                'label' => 'Document (PDF, Image, etc.)',
                'attr' => [
                    'accept' => '.pdf,.jpg,.jpeg,.png,.doc,.docx',
                    'class' => 'form-control'
                ],
                'help' => 'Formats acceptés: PDF, JPG, PNG, DOC, DOCX (max 10MB)'
            ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'Enregistrer',
            'attr' => [
                'class' => 'btn btn-primary'
            ]
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SuiviTraitement::class,
            'user_role' => 'user',
            'is_etudiant' => false,
            'validation_groups' => ['Default']
        ]);
        
        $resolver->setNormalizer('validation_groups', function (OptionsResolver $options, $value) {
            $isEtudiant = $options['is_etudiant'] ?? false;
            return $isEtudiant ? ['etudiant', 'Default'] : ['psychologue', 'Default'];
        });
    }
}
