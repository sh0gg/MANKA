<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulario de alta/edición de usuarios dende o panel de administración.
 * Non existe rexistro público (spec, sección 5): este formulario só é
 * accesible para ROLE_ADMIN.
 */
class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nome'])
            ->add('surname', TextType::class, ['label' => 'Apelidos'])
            ->add('email', EmailType::class, ['label' => 'Correo electrónico'])
            ->add('roleChoice', ChoiceType::class, [
                'mapped' => false,
                'label' => 'Rol',
                'data' => $options['initial_role'],
                'choices' => [
                    'Traballador de planta' => 'ROLE_USER',
                    'Técnico de mantemento' => 'ROLE_TECHNICIAN',
                    'Administrador' => 'ROLE_ADMIN',
                ],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Conta activa',
                'required' => false,
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'required' => !$options['is_edit'],
                'label' => $options['is_edit'] ? 'Nova contrasinal' : 'Contrasinal',
                'help' => $options['is_edit'] ? 'Deixa en branco para non cambiar a contrasinal actual.' : null,
                'constraints' => $options['is_edit'] ? [] : [new Assert\NotBlank(), new Assert\Length(min: 6, minMessage: 'A contrasinal debe ter polo menos {{ limit }} caracteres.')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
            'initial_role' => 'ROLE_USER',
        ]);
        $resolver->setAllowedTypes('is_edit', 'bool');
        $resolver->setAllowedTypes('initial_role', 'string');
    }
}
