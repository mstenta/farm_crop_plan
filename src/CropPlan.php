<?php

namespace Drupal\farm_crop_plan;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\plan\Entity\PlanInterface;

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
   * Class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getCropPlantings(PlanInterface $plan): array {
    return $this->entityTypeManager->getStorage('plan_record')->loadByProperties(['plan' => $plan->id(), 'type' => 'crop_planting']);
  }

}
