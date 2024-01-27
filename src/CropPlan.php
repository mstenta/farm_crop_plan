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

  /**
   * {@inheritdoc}
   */
  public function getCropPlantingStages(PlanRecordInterface $crop_planting): array {
    $stages = [];

    // Load variables.
    $seeding_date = $crop_planting->get('seeding_date')->value;
    $transplant_days = $crop_planting->get('transplant_days')->value;
    $maturity_days = $crop_planting->get('maturity_days')->value;
    $harvest_days = $crop_planting->get('harvest_days')->value;

    // If a seeding date and maturity days are available, add a seeding stage.
    // The end date is the maturity date by default. If a transplanting is
    // planned, then the end date is the transplanting date.
    if (!empty($seeding_date) && !empty($maturity_days)) {
      $stages[] = [
        'type' => 'seeding',
        'start' => $seeding_date,
        'end' => $seeding_date + ((!empty($transplant_days) ? $transplant_days : $maturity_days) * 3600 * 24),
        'location' => [],
      ];
    }

    // If a seeding date, maturity days, and transplanting days are available,
    // add a transplanting stage.
    if (!empty($seeding_date) && !empty($maturity_days) && !empty($transplant_days)) {
      $stages[] = [
        'type' => 'transplanting',
        'start' => $seeding_date + ($transplant_days * 3600 * 24),
        'end' => $seeding_date + ($maturity_days * 3600 * 24),
        'location' => [],
      ];
    }

    // If a seeding date, maturity date, and harvest days are available, add a
    // harvest stage.
    if (!empty($seeding_date) && !empty($maturity_days) && !empty($harvest_days)) {
      $stages[] = [
        'type' => 'harvest',
        'start' => $seeding_date + ($maturity_days * 3600 * 24),
        'end' => $seeding_date + ($maturity_days * 3600 * 24) + ($harvest_days * 3600 * 24),
        'location' => [],
      ];
    }

    return $stages;
  }

}
