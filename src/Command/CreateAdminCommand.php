<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Bootstrap do primeiro usuario ROLE_ADMIN.
 *
 * MANKA non ten rexistro público: todos os usuarios créanse dende o panel de
 * administración (ver spec, sección 5), pero alguén ten que crear o primeiro
 * administrador. Este comando resolve ese problema do "ovo e a galiña".
 */
#[AsCommand(name: 'app:create-admin', description: 'Crea ou actualiza un usuario ROLE_ADMIN')]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email do administrador')
            ->addArgument('password', InputArgument::REQUIRED, 'Contrasinal')
            ->addArgument('name', InputArgument::OPTIONAL, 'Nome', 'Admin')
            ->addArgument('surname', InputArgument::OPTIONAL, 'Apelidos', 'MANKA')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        $isNew = null === $user;

        if ($isNew) {
            $user = new User();
            $user->setEmail($email);
        }

        $user
            ->setName($input->getArgument('name'))
            ->setSurname($input->getArgument('surname'))
            ->setRoles(['ROLE_ADMIN'])
            ->setActive(true)
            ->setPassword($this->passwordHasher->hashPassword($user, $input->getArgument('password')))
        ;

        if ($isNew) {
            $this->entityManager->persist($user);
        }
        $this->entityManager->flush();

        $io->success(sprintf('%s o administrador "%s".', $isNew ? 'Creado' : 'Actualizado', $email));

        return Command::SUCCESS;
    }
}
