<?php

namespace Drupal\farm_crop_plan;

use Drupal\asset\Entity\AssetInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\farm_location\LogLocationInterface;
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
   * Log location service.
   *
   * @var \Drupal\farm_location\LogLocationInterface
   */
  protected $logLocation;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\farm_log\LogQueryFactoryInterface $log_query_factory
   *   Log query factory.
   * @param \Drupal\farm_location\LogLocationInterface $log_location
   *   Log location service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LogQueryFactoryInterface $log_query_factory, LogLocationInterface $log_location) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logQueryFactory = $log_query_factory;
    $this->logLocation = $log_location;
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
  public function getCropPlantingsByType(PlanInterface $plan): array {
    $crop_plantings_by_type = [];
    $crop_plantings = $this->getCropPlantings($plan);
    foreach ($crop_plantings as $crop_planting) {
      $plant_types = $crop_planting->getPlant()->get('plant_type')->referencedEntities();
      foreach ($plant_types as $plant_type) {
        $crop_plantings_by_type[$plant_type->id()][$crop_planting->id()] = $crop_planting;
      }
    }
    return $crop_plantings_by_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getCropPlantingsByLocation(PlanInterface $plan): array {
    $crop_plantings_by_location = [];
    $crop_plantings = $this->getCropPlantings($plan);
    foreach ($crop_plantings as $crop_planting) {
      $logs = $this->getAssetMovementLogs($crop_planting->getPlant());
      foreach ($logs as $log) {
        $locations = $this->logLocation->getLocation($log);
        foreach ($locations as $location) {
          $crop_plantings_by_location[$location->id()][$crop_planting->id()] = $crop_planting;
        }
      }
    }
    return $crop_plantings_by_location;
  }

  /**
   * {@inheritdoc}
   */
  public function getLogs(AssetInterface $asset, bool $access_check = TRUE): array {
    $query = $this->logQueryFactory->getQuery(['asset' => $asset]);
    $query->accessCheck($access_check);
    $log_ids = $query->execute();
    return $this->entityTypeManager->getStorage('log')->loadMultiple($log_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstLog(AssetInterface $asset, string $log_type = NULL, bool $access_check = TRUE) {
    $options = [
      'asset' => $asset,
      'limit' => 1,
    ];
    $query = $this->logQueryFactory->getQuery($options);
    if (!empty($log_type)) {
      $query->condition('type', $log_type);
    }
    $query->sort('timestamp', 'ASC');
    $query->sort('id', 'ASC');
    $query->accessCheck($access_check);
    $log_ids = $query->execute();
    return $this->entityTypeManager->getStorage('log')->load(reset($log_ids));
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

  /**
   * {@inheritdoc}
   */
  public function getAssetLocationStages(AssetInterface $asset): array {
    $stages = [];

    // Load all movement logs that reference the asset.
    $logs = $this->getAssetMovementLogs($asset);

    // Iterate through the logs and generate stages.
    foreach ($logs as $log) {
      $stages[] = [
        'type' => 'location',
        'start' => $log->get('timestamp')->value,
        'end' => NULL,
        'location' => $this->logLocation->getLocation($log),
      ];
    }

    // Sort stages chronologically.
    usort($stages, function ($a, $b) {
      if ($a['start'] == $b['end']) {
        return 0;
      }
      return ($a['start'] < $b['end']) ? -1 : 1;
    });

    // Iterate through the stages and fill in end timestamps, if available.
    // The end timestamp is assumed to be the next stage's start timestamp.
    foreach ($stages as $i => &$stage) {
      if (isset($stages[$i + 1]) && !empty($stages[$i + 1]['start'])) {
        $stage['end'] = $stages[$i + 1]['start'];
      }
    }

    return $stages;
  }

  /**
   * Get all movement logs that reference an asset.
   *
   * This does not check access so that must be done by downstream code.
   *
   * @param \Drupal\asset\Entity\AssetInterface $asset
   *   The asset entity.
   *
   * @return \Drupal\log\Entity\LogInterface[]
   *   Returns an array of movement logs.
   */
  protected function getAssetMovementLogs(AssetInterface $asset) {
    $options = [
      'asset' => $asset,
    ];
    $query = $this->logQueryFactory->getQuery($options);
    $query->condition('is_movement', TRUE);
    $query->accessCheck(FALSE);
    $log_ids = $query->execute();
    /** @var \Drupal\log\Entity\LogInterface[] $logs */
    $logs = $this->entityTypeManager->getStorage('log')->loadMultiple($log_ids);
    return $logs;
  }

}
