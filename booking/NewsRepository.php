<?php

namespace NewsBundle\Document;

use Doctrine\ODM\MongoDB\DocumentRepository;

class NewsRepository extends DocumentRepository
{
    /**
     * @return \Doctrine\ODM\MongoDB\Query\Query
     */
    public function getAllQuery()
    {
        return $this->createQueryBuilder()
            ->sort('createdAt', 'desc')
            ->getQuery();
    }

    /**
     * @param $type
     * @return \Doctrine\ODM\MongoDB\Query\Query
     */
    public function getAllByTypeQuery($type)
    {
        return $this
            ->createQueryBuilder()
            ->field('type')->equals($type)
            ->sort('createdAt', 'desc')
            ->getQuery();
    }

    /**
     * @param $search
     * @return \Doctrine\ODM\MongoDB\Query\Query
     */
    public function searchQuery($search = null)
    {
        if (!$search) {
            return $this->getAllQuery();
        }

        $query = $this->createQueryBuilder();

        $search = preg_quote(trim($search));

        if (1 === preg_match("/^[abcdef\\d]{24}$/", $search)) {
            $query->addOr($query->expr()->field('_id')->equals(new \MongoId($search)));
        }

        $query->addOr(
                $query->expr()->field('type')->equals(new \MongoRegex('/.*'.$search.'.*/i'))
            )->addOr(
                $query->expr()->field('title')->equals(new \MongoRegex('/.*'.$search.'.*/i'))
            )->addOr(
                $query->expr()->field('content')->equals(new \MongoRegex('/.*'.htmlspecialchars($search).'.*/i'))
            )->addOr(
                $query->expr()->field('author.1')->equals(new \MongoRegex('/.*'.$search.'.*/i')) // 'author.1 - author.name, without author.id
        );

        return $query->getQuery();
    }
}
