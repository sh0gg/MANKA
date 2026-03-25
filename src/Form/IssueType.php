<?php

namespace App\Form;

use App\Entity\Device;
use App\Entity\Issue;
use App\Entity\IssueCategory;
use App\Entity\Technician;
use App\Repository\TechnicianRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IssueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startAt')
            ->add('endAt')
            ->add('hygiene')
            ->add('comments')
            ->add('category', EntityType::class, [
                'class' => IssueCategory::class,
                'choice_label' => 'name',
            ])
            ->add('device', EntityType::class, [
                'class' => Device::class,
                'choice_label' => 'name',
            ])
            ->add('technician', EntityType::class, [
                'class' => Technician::class,
                'choice_label' => function (Technician $technician) {
                    return $technician->getName().' '.$technician->getSurname();
                },
                'multiple' => true,
                'expanded' => true,
                'query_builder' => function (TechnicianRepository $tr) {
                    return $tr->createQueryBuilder('t')
                        ->andWhere('t.active = :active')
                        ->setParameter('active', true)
                        ->orderBy('t.surname', 'ASC')
                        ->addOrderBy('t.name', 'ASC');
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Issue::class,
        ]);
    }
}
