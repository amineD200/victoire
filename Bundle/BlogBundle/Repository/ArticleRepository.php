<?php

namespace Victoire\Bundle\BlogBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Victoire\Bundle\BlogBundle\Entity\Blog;
use Victoire\Bundle\CoreBundle\Repository\StateFullRepositoryTrait;
use Victoire\Bundle\PageBundle\Entity\PageStatus;

/**
 * The Article repository.
 */
class ArticleRepository extends EntityRepository
{
    use StateFullRepositoryTrait;

    /**
     * Get all articles in the repository.
     *
     * @param bool $excludeUnpublished Should we get only the published BasePages ?
     *
     * @return ArticleRepository
     */
    public function getAll($excludeUnpublished = false, Blog $blog = null)
    {
        $this->clearInstance();
        $this->qb = $this->getInstance();

        //If $excludeUnpublished === true, we exclude the non published results
        if ($excludeUnpublished) {
            $this->qb
                ->andWhere('article.status = :status')
                ->orWhere('article.status = :scheduled_status AND article.publishedAt <= :now')
                ->setParameter('status', PageStatus::PUBLISHED)
                ->setParameter('scheduled_status', PageStatus::SCHEDULED)
                ->setParameter('now', new \DateTime());
        }

        if (null != $blog) {
            $this->qb
                ->andWhere('article.blog = :blog')
                ->setParameter('blog', $blog->getId());
        }

        return $this;
    }

    /**
     * Filter repositoy by Blog.
     *
     * @return ArticleRepository
     */
    public function filterByBlog(Blog $blog)
    {
        $this->getInstance()
            ->andWhere('article.blog = :blog')
            ->setParameter('blog', $blog);

        return $this;
    }

    /**
     * Get very next festivals query builder.
     *
     * @param method        $method        The method to run
     * @param hydrationMode $hydrationMode How the results will be (Object ? Array )
     *
     * @return array()
     */
    public function run($method = 'getResult', $hydrationMode = Query::HYDRATE_OBJECT)
    {
        return $this->getInstance()->getQuery()->$method($hydrationMode);
    }

    /**
     * Use to assemble ArticleList Query with Filter Query.
     *
     * @param null $listingQuery
     *
     * @return $this
     */
    public function filterWithListingQuery($listingQuery = null)
    {
        if ($listingQuery) {
            $dql = $this->createQueryBuilder('item')
                ->leftJoin('item.blog', 'blog')
                ->getDql();
            $dql = $dql.' '.$listingQuery;
            $this->qb
                ->andWhere($this->qb->expr()->in('article', $dql));
        }

        return $this;
    }

    public function getPreviousRecord($id)
    {
        $blog = $this->find($id)->getBlog();
        $queryBuilder = $this->getAll(true, $blog)->getInstance();

        return $queryBuilder->andWhere($queryBuilder->expr()->lt('article.id', ':id'))
            ->setParameter('id', $id)
            ->orderBy('article.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getNextRecord($id)
    {
        $blog = $this->find($id)->getBlog();
        $queryBuilder = $this->getAll(true, $blog)->getInstance();

        return $queryBuilder->andWhere($queryBuilder->expr()->gt('article.id', ':id'))
            ->setParameter('id', $id)
            ->orderBy('article.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getVeryFirstRecord()
    {
        $queryBuilder = $this->getAll(true)
            ->getInstance();

        return $queryBuilder
            ->orderBy('article.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getVeryLastRecord()
    {
        $queryBuilder = $this->getAll(true)
            ->getInstance();

        return $queryBuilder
            ->orderBy('article.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get Article by blog ordered by publication DESC.
     *
     * @param Blog $blog
     *
     * @return array()
     */
    public function getArticles(Blog $blog)
    {
        $queryBuilder = $this->getAll(false, $blog)
            ->getInstance();

        return $queryBuilder
            ->andWhere('article.status IN (:status)')
            ->setParameter('status', [
                PageStatus::PUBLISHED,
                PageStatus::SCHEDULED,
                PageStatus::UNPUBLISHED,
            ])
            ->orderBy('article.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get articles by blog ordered by creation DESC.
     *
     * @param Blog $blog
     *
     * @return array()
     */
    public function getDrafts(Blog $blog)
    {
        $queryBuilder = $this->getAll(false, $blog)
            ->getInstance();

        return $queryBuilder
            ->andWhere('article.status = :status')
            ->setParameter('status', PageStatus::DRAFT)
            ->orderBy('article.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
