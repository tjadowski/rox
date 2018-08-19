<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class MemberRepository extends EntityRepository implements UserLoaderInterface
{
    /**
     * Loads the user for the given username.
     *
     * This method must return null if the user is not found.
     *
     * @param string $username The username
     *
     * @return UserInterface|null
     */
    public function loadMembersByUsernamePart($username)
    {
        return $this->createQueryBuilder('u')
            ->select('u.username')
            ->where('u.username Like :username')
            ->setParameter('username', '%'.$username.'%')
            ->setMaxResults(10)
            ->orderBy('u.username', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Loads the user for the given username.
     *
     * This method must return null if the user is not found.
     *
     * @param string $username The username
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     *
     * @return UserInterface|null
     */
    public function loadUserByUsername($username)
    {
        return $this->createQueryBuilder('u')
            ->where('u.username = :username OR u.email = :email')
            ->setParameter('username', $username)
            ->setParameter('email', $username)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByProfileInfo($term)
    {
        return $this->createQueryBuilder('u')
            ->where('u.username like :term OR u.email like :term')
            ->setParameter('term', '%' . $term . '%')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }
}
