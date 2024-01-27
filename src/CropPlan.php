<?php

namespace Drupal\farm_crop_plan;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\farm_log\LogQueryFactoryInterface;
use Drupal\plan\Entity\PlanInterface;
use Drupal\plan\Entity\PlanRecordInterface;

/**
 * Crop plan logic.
 */
class CropPlan implements CropPlanInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Log query factory.
   *
   * @var \Drupal\farm_log\LogQueryFactoryInterface
   */
  protected LogQueryFactoryInterface $logQueryFactory;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\farm_log\LogQueryFactoryInterface $log_query_factory
   *   Log query factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LogQueryFactoryInterface $log_query_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logQueryFactory = $log_query_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getCropPlantings(PlanInterface $plan): array {
    return $this->entityTypeManager->getStorage('plan_record')->loadByProperties(['plan' => $plan->id(), 'type' => 'crop_planting']);
  }

  /**
   * {@inheritdoc}
   */
  public function getLogs(PlanRecordInterface $crop_planting, bool $access_check = TRUE): array {
    $plant_assets = $crop_planting->get('plant')->referencedEntities();
    if (empty($plant_assets)) {
      return [];
    }
    $options = [
      'asset' => reset($plant_assets),
    ];
    $query = $this->logQueryFactory->getQuery($options);
    $query->accessCheck($access_check);
    $log_ids = $query->execute();
    return $this->entityTypeManager->getStorage('log')->loadMultiple($log_ids);
  }

}
