<?php

namespace AppBundle\Twig;
use AppBundle\Entity\User;
use AppBundle\Entity\File;

class AppTwigExtension extends \Twig_Extension {
    private $entityManager;

    public function __construct(\Doctrine\ORM\EntityManager $entityManager) {
        $this->entityManager = $entityManager;
    }

    public function getFunctions() {
        return array(
            new \Twig_SimpleFunction('getUsers', array($this, 'getUsers')),
            new \Twig_SimpleFunction('getFiles', array($this, 'getFiles')),
        );
    }

    public function getUsers() {
        $em = $this->getEntityManager();
        $users = $em->getRepository(User::class)->findAll();
        return $users;
    }

    public function getFiles() {
        $em = $this->getEntityManager();
        $files = $em->getRepository(File::class)->findAll();
        return $files;
    }

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    public function getEntityManager() {
        return $this->entityManager;
    }
}