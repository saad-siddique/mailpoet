<?php

namespace MailPoet\Form\Listing;

use MailPoet\Entities\FormEntity;
use MailPoet\Listing\ListingDefinition;
use MailPoet\Listing\ListingRepository;
use MailPoet\WP\Functions as WPFunctions;
use MailPoetVendor\Doctrine\ORM\QueryBuilder;

class FormListingRepository extends ListingRepository {
  public function getGroups(ListingDefinition $definition): array {
    $queryBuilder = clone $this->queryBuilder;
    $this->applyFromClause($queryBuilder);
    $this->applyParameters($queryBuilder, $definition->getParameters());

    // total count
    $countQueryBuilder = clone $queryBuilder;
    $countQueryBuilder->select('COUNT(f) AS formCount');
    $countQueryBuilder->andWhere('f.deletedAt IS NULL');
    $totalCount = (int)$countQueryBuilder->getQuery()->getSingleScalarResult();

    // trashed count
    $trashedCountQueryBuilder = clone $queryBuilder;
    $trashedCountQueryBuilder->select('COUNT(f) AS formCount');
    $trashedCountQueryBuilder->andWhere('f.deletedAt IS NOT NULL');
    $trashedCount = (int)$trashedCountQueryBuilder->getQuery()->getSingleScalarResult();

    return [
      [
        'name' => 'all',
        'label' => WPFunctions::get()->__('All', 'mailpoet'),
        'count' => $totalCount,
      ],
      [
        'name' => 'trash',
        'label' => WPFunctions::get()->__('Trash', 'mailpoet'),
        'count' => $trashedCount,
      ],
    ];
  }

  protected function applySelectClause(QueryBuilder $queryBuilder) {
    $queryBuilder->select("PARTIAL f.{id,name,status,settings,createdAt,updatedAt,deletedAt}");
  }

  protected function applyFromClause(QueryBuilder $queryBuilder) {
    $queryBuilder->from(FormEntity::class, 'f');
  }

  protected function applyGroup(QueryBuilder $queryBuilder, string $group) {
    // include/exclude deleted
    if ($group === 'trash') {
      $queryBuilder->andWhere('f.deletedAt IS NOT NULL');
    } else {
      $queryBuilder->andWhere('f.deletedAt IS NULL');
    }
  }

  protected function applySorting(QueryBuilder $queryBuilder, string $sortBy, string $sortOrder) {
    $queryBuilder->addOrderBy("f.$sortBy", $sortOrder);
  }

  protected function applySearch(QueryBuilder $queryBuilder, string $search) {
    // TODO: Implement applySearch() method.
  }

  protected function applyFilters(QueryBuilder $queryBuilder, array $filters) {
    // TODO: Implement applyFilters() method.
  }

  protected function applyParameters(QueryBuilder $queryBuilder, array $parameters) {
    // TODO: Implement applyParameters() method.
  }
}
