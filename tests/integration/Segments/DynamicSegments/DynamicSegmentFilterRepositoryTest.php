<?php declare(strict_types = 1);

namespace MailPoet\Segments;

use MailPoet\Entities\DynamicSegmentFilterData;
use MailPoet\Entities\DynamicSegmentFilterEntity;
use MailPoet\Entities\SegmentEntity;
use MailPoet\Segments\DynamicSegments\DynamicSegmentFilterRepository;
use MailPoet\Segments\DynamicSegments\Filters\UserRole;
use MailPoet\Segments\DynamicSegments\Filters\WooCommerceTotalSpent;

class DynamicSegmentFilterRepositoryTest extends \MailPoetTest {
  /** @var DynamicSegmentFilterRepository */
  private $dynamicSegmentFilterRepository;

  /** @var SegmentsRepository */
  private $segmentsRepository;

  public function _before() {
    parent::_before();
    $this->cleanup();
    $this->dynamicSegmentFilterRepository = $this->diContainer->get(DynamicSegmentFilterRepository::class);
    $this->segmentsRepository = $this->diContainer->get(SegmentsRepository::class);
  }

  public function testItReturnsDynamicSegmentFilterBySegmentTypeAndAction(): void {
    $segment = $this->createSegment('Dynamic Segment');
    $this->createDynamicSegmentFilter($segment, [
      'segmentType' => DynamicSegmentFilterData::TYPE_WOOCOMMERCE,
      'action' => WooCommerceTotalSpent::ACTION_TOTAL_SPENT,
    ]);

    $dynamicFilter = $this->dynamicSegmentFilterRepository->findOnyBySegmentTypeAndAction(
      DynamicSegmentFilterData::TYPE_WOOCOMMERCE,
      WooCommerceTotalSpent::ACTION_TOTAL_SPENT
    );
    assert($dynamicFilter instanceof DynamicSegmentFilterEntity);
    expect($dynamicFilter->getFilterData()->getParam('segmentType'))->equals(DynamicSegmentFilterData::TYPE_WOOCOMMERCE);
    expect($dynamicFilter->getFilterData()->getParam('action'))->equals(WooCommerceTotalSpent::ACTION_TOTAL_SPENT);

    $dynamicFilter = $this->dynamicSegmentFilterRepository->findOnyBySegmentTypeAndAction(
      DynamicSegmentFilterData::TYPE_USER_ROLE,
      UserRole::TYPE
    );
    expect($dynamicFilter)->null();
  }

  private function createSegment(string $name): SegmentEntity {
    $segment = new SegmentEntity($name, SegmentEntity::TYPE_DYNAMIC, '');
    $this->segmentsRepository->persist($segment);
    $this->segmentsRepository->flush();
    return $segment;
  }

  private function createDynamicSegmentFilter(
    SegmentEntity $segment,
    array $filterData
  ): DynamicSegmentFilterEntity {
    $filter = new DynamicSegmentFilterEntity($segment, new DynamicSegmentFilterData($filterData));
    $this->dynamicSegmentFilterRepository->persist($filter);
    $this->dynamicSegmentFilterRepository->flush();
    return $filter;
  }

  private function cleanup() {
    $this->truncateEntity(SegmentEntity::class);
    $this->truncateEntity(DynamicSegmentFilterEntity::class);
  }

  public function _after() {
    parent::_after();
    $this->cleanup();
  }
}
