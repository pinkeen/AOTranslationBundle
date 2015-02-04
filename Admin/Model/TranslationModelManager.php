<?php

namespace AO\TranslationBundle\Admin\Model;

use Doctrine\ORM\EntityManager;
use Sonata\DoctrineORMAdminBundle\Model\ModelManager;
use Symfony\Bridge\Doctrine\RegistryInterface;

class TranslationModelManager extends ModelManager
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @param RegistryInterface $registry
     * @param EntityManager $entityManager
     */
    public function __construct(RegistryInterface $registry, EntityManager $entityManager)
    {
        parent::__construct($registry);

        $this->entityManager = $entityManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getEntityManager($class)
    {
        return $this->entityManager;
    }
}