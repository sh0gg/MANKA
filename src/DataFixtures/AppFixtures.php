<?php

namespace App\DataFixtures;

use App\Entity\Device;
use App\Entity\Issue;
use App\Entity\IssueCategory;
use App\Entity\Observation;
use App\Entity\User;
use App\Enum\IssueType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Datos de demostración para KiwiAtlántico (MANKA_spec.md, sección 10).
 */
class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $users = $this->loadUsers($manager);
        $devices = $this->loadDevices($manager);
        $categories = $this->loadCategories($manager);

        $manager->flush();

        $this->loadIssues($manager, $users, $devices, $categories);

        $manager->flush();
    }

    /**
     * @return array<string, User>
     */
    private function loadUsers(ObjectManager $manager): array
    {
        $definitions = [
            'sorrantia' => ['Sergi', 'Orrantia', 'sorrantia@kiwiatlantico.com', ['ROLE_ADMIN'], true],
            'mtorres' => ['Martín', 'Torres', 'mtorres@kiwiatlantico.com', ['ROLE_TECHNICIAN'], true],
            'irodriguez' => ['Iván', 'Rodríguez', 'irodriguez@kiwiatlantico.com', ['ROLE_TECHNICIAN'], true],
            'jcancelo' => ['José', 'Cancelo', 'jcancelo@kiwiatlantico.com', ['ROLE_TECHNICIAN'], true],
            'spardo' => ['Sergio', 'Pardo', 'spardo@kiwiatlantico.com', ['ROLE_TECHNICIAN'], true],
            'alopez' => ['Ana', 'López', 'alopez@kiwiatlantico.com', [], true],
            'cperez' => ['Carlos', 'Pérez', 'cperez@kiwiatlantico.com', [], false],
        ];

        $users = [];
        foreach ($definitions as $key => [$name, $surname, $email, $roles, $active]) {
            $user = new User();
            $user->setName($name);
            $user->setSurname($surname);
            $user->setEmail($email);
            $user->setRoles($roles);
            $user->setActive($active);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'manka2026'));
            $manager->persist($user);
            $users[$key] = $user;
        }

        return $users;
    }

    /**
     * @return array<string, Device>
     */
    private function loadDevices(ObjectManager $manager): array
    {
        $names = [
            'Arranque', 'Básculas L2', 'Calibradora', 'Cámara frixorífica 1', 'Cámara frixorífica 2',
            'Carretilla 1', 'Carretilla 2', 'Carretilla 3', 'Colector L1-L5', 'Compresor C-02', 'Dosificador Cloro',
        ];

        $devices = [];
        foreach ($names as $name) {
            $device = new Device();
            $device->setName($name);
            $manager->persist($device);
            $devices[$name] = $device;
        }

        return $devices;
    }

    /**
     * @return array<string, IssueCategory>
     */
    private function loadCategories(ObjectManager $manager): array
    {
        $names = ['Avería mecánica', 'Fallo eléctrico', 'Temperatura', 'Calibración', 'Limpeza', 'Outro'];

        $categories = [];
        foreach ($names as $name) {
            $category = new IssueCategory();
            $category->setName($name);
            $manager->persist($category);
            $categories[$name] = $category;
        }

        return $categories;
    }

    /**
     * @param array<string, User>          $users
     * @param array<string, Device>        $devices
     * @param array<string, IssueCategory> $categories
     */
    private function loadIssues(ObjectManager $manager, array $users, array $devices, array $categories): void
    {
        $now = new \DateTimeImmutable();

        $definitions = [
            ['Cámara frixorífica 1', 'Temperatura', IssueType::CORRECTIVO, -2, null, false, 'alopez', ['mtorres'], [
                ['alopez', 'A cámara non baixa de 8°C, debería estar a -18°C.'],
            ]],
            ['Compresor C-02', 'Avería mecánica', IssueType::CORRECTIVO, -25, -23, true, 'alopez', ['irodriguez'], [
                ['alopez', 'Ruído moi forte ao arrancar o compresor.'],
                ['irodriguez', 'Substituída a correa, comprobado funcionamento correcto.'],
            ]],
            ['Carretilla 1', 'Avería mecánica', IssueType::CORRECTIVO, -1, null, false, 'alopez', [], []],
            ['Básculas L2', 'Calibración', IssueType::PREVENTIVO, -40, -40, true, 'mtorres', ['mtorres'], [
                ['mtorres', 'Calibración trimestral realizada segundo protocolo.'],
            ]],
            ['Dosificador Cloro', 'Fallo eléctrico', IssueType::CORRECTIVO, -15, -14, true, 'cperez', ['jcancelo'], [
                ['cperez', 'O dosificador non arrinca, posible fusible fundido.'],
                ['jcancelo', 'Fusible substituído. Probado durante 2h sen incidencias.'],
            ]],
            ['Colector L1-L5', 'Limpeza', IssueType::PREVENTIVO, -60, -59, true, 'spardo', ['spardo', 'jcancelo'], []],
            ['Carretilla 2', 'Outro', IssueType::CORRECTIVO, -5, null, false, 'alopez', ['irodriguez'], [
                ['alopez', 'As luces de posición non funcionan.'],
            ]],
            ['Cámara frixorífica 2', 'Temperatura', IssueType::CORRECTIVO, -70, -69, false, 'cperez', ['mtorres'], [
                ['mtorres', 'Sonda de temperatura defectuosa, substituída.'],
            ]],
            ['Calibradora', 'Calibración', IssueType::PREVENTIVO, -90, -89, true, 'mtorres', ['mtorres', 'spardo'], []],
            ['Carretilla 3', 'Avería mecánica', IssueType::CORRECTIVO, -3, null, false, 'alopez', ['jcancelo'], [
                ['alopez', 'Perda de presión hidráulica notable.'],
                ['jcancelo', 'Revisando o circuíto hidráulico, pendente de peza de recambio.'],
            ]],
            ['Arranque', 'Fallo eléctrico', IssueType::CORRECTIVO, -120, -118, true, 'spardo', ['irodriguez'], []],
            ['Compresor C-02', 'Limpeza', IssueType::PREVENTIVO, -45, -45, true, 'mtorres', ['mtorres'], []],
        ];

        foreach ($definitions as [$deviceName, $categoryName, $type, $startOffsetDays, $endOffsetDays, $hygieneCheckDone, $creatorKey, $technicianKeys, $observations]) {
            $issue = new Issue();
            $issue->setDevice($devices[$deviceName]);
            $issue->setCategory($categories[$categoryName]);
            $issue->setType($type);
            $issue->setCreatedBy($users[$creatorKey]);
            $issue->setStartAt($now->modify(sprintf('%d days', $startOffsetDays)));

            if (null !== $endOffsetDays) {
                $issue->close($now->modify(sprintf('%d days', $endOffsetDays)), $hygieneCheckDone);
            }

            foreach ($technicianKeys as $technicianKey) {
                $issue->addTechnician($users[$technicianKey]);
            }

            foreach ($observations as [$authorKey, $content]) {
                $observation = new Observation();
                $observation->setContent($content);
                $observation->setAuthor($users[$authorKey]);
                $issue->addObservation($observation);
            }

            $manager->persist($issue);
        }
    }
}
