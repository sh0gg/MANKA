<?php

namespace App\Form;

use App\Entity\Device;
use App\Entity\Issue;
use App\Entity\IssueCategory;
use App\Entity\User;
use App\Enum\IssueType as IssueTypeEnum;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IssueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('device', EntityType::class, [
                'class' => Device::class,
                'choice_label' => 'name',
                'label' => 'Equipo afectado',
                'placeholder' => 'Selecciona un equipo...',
            ])
            ->add('category', EntityType::class, [
                'class' => IssueCategory::class,
                'choice_label' => 'name',
                'label' => 'Categoría',
                'placeholder' => 'Selecciona categoría...',
            ])
            ->add('type', EnumType::class, [
                'class' => IssueTypeEnum::class,
                'choice_label' => static fn (IssueTypeEnum $type) => $type->label(),
                'label' => 'Tipo',
                'expanded' => true,
            ])
            ->add('startAt', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Data e hora de inicio',
            ])
        ;

        // Sección "Persoal técnico": oculta para ROLE_USER (spec, sección 9).
        if ($options['is_privileged']) {
            $builder->add('technicians', EntityType::class, [
                'class' => User::class,
                'choice_label' => static fn (User $user) => $user->getFullName(),
                'label' => 'Técnicos asignados',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'query_builder' => static fn (UserRepository $repository) => $repository->createQueryBuilder('u')
                    ->orderBy('u.surname', 'ASC')
                    ->addOrderBy('u.name', 'ASC'),
                'choice_attr' => static fn (User $user) => $user->isActive() ? [] : ['disabled' => 'disabled'],
            ]);
        }

        if ($options['include_initial_observation']) {
            $builder->add('initialObservation', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Observación (opcional)',
                'attr' => ['rows' => 3, 'placeholder' => 'Describe o problema detectado...'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Issue::class,
            'is_privileged' => false,
            'include_initial_observation' => false,
        ]);
        $resolver->setAllowedTypes('is_privileged', 'bool');
        $resolver->setAllowedTypes('include_initial_observation', 'bool');
    }
}
