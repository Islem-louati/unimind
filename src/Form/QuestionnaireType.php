<?php

namespace App\Form;

use App\Entity\Questionnaire;
use App\Enum\TypeQuestionnaire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class QuestionnaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du questionnaire *',
                'attr'  => ['class' => 'form-control']
            ])
            ->add('code', TextType::class, [
                'label' => 'Code unique *',
                'attr'  => ['class' => 'form-control']
            ])
            // ✅ CORRECTION : Utilisation de TypeQuestionnaire::getChoices() au lieu de valeurs manuelles
            ->add('type', ChoiceType::class, [
                'label'   => 'Type de questionnaire *',
                'choices' => TypeQuestionnaire::getChoices(),
                'attr'    => ['class' => 'form-select']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description *',
                'attr'  => ['class' => 'form-control', 'rows' => 3]
            ])
            ->add('nbre_questions', IntegerType::class, [
                'label' => 'Nombre de questions *',
                'attr'  => ['class' => 'form-control']
            ])
            ->add('seuil_leger', IntegerType::class, [
                'label' => 'Seuil léger *',
                'attr'  => ['class' => 'form-control']
            ])
            ->add('seuil_modere', IntegerType::class, [
                'label' => 'Seuil modéré *',
                'attr'  => ['class' => 'form-control']
            ])
            ->add('seuil_severe', IntegerType::class, [
                'label' => 'Seuil sévère *',
                'attr'  => ['class' => 'form-control']
            ])
            ->add('interpretat_legere', TextareaType::class, [
                'label' => 'Interprétation légère *',
                'attr'  => ['class' => 'form-control', 'rows' => 2]
            ])
            ->add('interpretat_modere', TextareaType::class, [
                'label' => 'Interprétation modérée *',
                'attr'  => ['class' => 'form-control', 'rows' => 2]
            ])
            ->add('interpretat_severe', TextareaType::class, [
                'label' => 'Interprétation sévère *',
                'attr'  => ['class' => 'form-control', 'rows' => 2]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Questionnaire::class,
        ]);
    }
}