<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Cache\CacheInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly UserPasswordHasherInterface $hasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin
            ->setEmail('admin@test.fr')
            ->setPassword($this->hasher->hashPassword($admin, 'testflorajet'))
            ->setRoles(['ROLE_ADMIN', 'ROLE_USER'])
        ;

        $sampleUser = new User();
        $sampleUser
            ->setEmail('user@test.fr')
            ->setPassword($this->hasher->hashPassword($sampleUser, 'testflorajet'))
            ->setRoles(['ROLE_USER'])
        ;

        $manager->persist($admin);
        $manager->persist($sampleUser);

        $this->cache->delete('articles');
        $manager->flush();
    }
}
