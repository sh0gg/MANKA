<?php

namespace App\Form;

use App\Entity\ApiToken;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ApiTokenType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nome',
                'attr' => ['placeholder' => 'Ex: App móbil, Sistema de calidade...'],
            ])
            ->add('expiresAt', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Caduca o',
                'help' => 'Deixa en branco para que non caduque.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ApiToken::class,
        ]);
    }
}
