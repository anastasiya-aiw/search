<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;

class SearchRepository extends EntityRepository
{
    const PER_PAGE = 10;

    public function findByRootPath($path) {
        if ('' === $path) {
            return [];
        }

        list($path) = explode('/', $path);

        $qb = $this->createQueryBuilder('t');
        $qb->where(
            $qb->expr()->like(
                't.path',
                $qb->expr()->literal($path . '/%')
            )
        );

        return $qb->getQuery()->getResult();
    }

    public function findLatest($text, $locale, $words) {
      // Вес отдельных слов в заголовке и тексте
      $coeff_title=round((20/count($words)),2);
      $coeff_doc=round((15/count($words)),2);
      $coeff_text=round((10/count($words)),2);

      // Условия для полного совпадения фразы в заголовке и тексте
      $relevance = '';
      $relevance .= " ( IF (s.content LIKE '%".$text."%', 10, 0)";

      // Условия для каждого из слов
      foreach($words as $w) {
        $relevance .= " + IF (s.content LIKE '%".$w."%', ".$coeff_text.", 0)";
        $relevance .= " + IF (s.size > 0, ".$coeff_doc.", 0)";
      }
      $relevance.=") AS relevance";

      $qb = $this->createQueryBuilder('s')
        ->addSelect($relevance)
        ->where('(s.locale = :locale or s.locale = \'\')');

      $orStatements = $qb->expr()->orX();
      foreach ($words as $w) {
        $orStatements->add(
          $qb->expr()->like('s.content', $qb->expr()->literal('%'.$w.'%'))
        );
      }
      $qb->andWhere($orStatements);

      return $qb
        ->setParameter('locale', $locale)
        ->orderBy('relevance','desc')
        ->addOrderBy('s.updatedAt','desc')
        ->getQuery()
        ->getResult();
    }

    /**
     * @param int $page
     * @return Paginator
     */
    public function findPaginated($text, $locale, $page, $words) {
      // Вес отдельных слов в заголовке и тексте
      $coeff_title=round((20/count($words)),2);
      $coeff_doc=round((15/count($words)),2);
      $coeff_text=round((10/count($words)),2);

      // Условия для полного совпадения фразы в заголовке и тексте
      $relevance = '';
      $relevance .= " ( IF (s.content LIKE '%".$text."%', 10, 0)";

      // Условия для каждого из слов
      foreach($words as $w) {
        $relevance .= " + IF (s.content LIKE '%".$w."%', ".$coeff_text.", 0)";
        $relevance .= " + IF (s.size > 0, ".$coeff_doc.", 0)";
      }
      $relevance.=") AS relevance";

      $qb = $this->createQueryBuilder('s')
        ->addSelect($relevance)
        ->where('(s.locale = :locale or s.locale = \'\')');

      $orStatements = $qb->expr()->orX();
      foreach ($words as $w) {
        $orStatements->add(
          $qb->expr()->like('s.content', $qb->expr()->literal('%'.$w.'%'))
        );
      }
      $qb->andWhere($orStatements);

      return $qb
        ->setParameter('locale', $locale)
        ->orderBy('relevance','desc')
        ->addOrderBy('s.updatedAt','desc')
        ->setMaxResults(self::PER_PAGE + 1)
        ->setFirstResult($page * self::PER_PAGE)
        ->getQuery()
        ->getResult();
    }
}
