<?php

namespace Drupal\farm_crop_plan\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\farm_crop_plan\CropPlanInterface;
use Drupal\log\Entity\LogInterface;
use Drupal\plan\Entity\PlanInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Crop plan timeline controller.
 */
class CropPlanTimeline extends ControllerBase {

  /**
   * The crop plan service.
   *
   * @var \Drupal\farm_crop_plan\CropPlanInterface
   */
  protected $cropPlan;

  /**
   * CropPlanTimeline constructor.
   *
   * @param \Drupal\farm_crop_plan\CropPlanInterface $crop_plan
   *   The crop plan service.
   */
  public function __construct(CropPlanInterface $crop_plan) {
    $this->cropPlan = $crop_plan;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('farm_crop_plan'),
    );
  }

  /**
   * API endpoint for crop plan timeline by plant type.
   *
   * @param \Drupal\plan\Entity\PlanInterface $plan
   *   The crop plan.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Json response of timeline data.
   */
  public function byPlantType(PlanInterface $plan) {

    $data = [];
    $crop_plantings_by_type = $this->cropPlan->getCropPlantingsByType($plan);
    foreach ($crop_plantings_by_type as $plant_type_id => $crop_plantings) {
      $plant_type = $this->entityTypeManager()->getStorage('taxonomy_term')->load($plant_type_id);
      $data['plant_type'][$plant_type_id] = [
        'label' => $plant_type->label(),
        'plants' => [],
      ];

      // Include each crop planting record.
      // @todo Move duplicated logic to generalized method in this class.
      foreach ($crop_plantings as $crop_planting) {

        // Include basic crop planting data.
        $asset = $crop_planting->get('plant')->referencedEntities()[0];
        $data['plant_type'][$plant_type_id]['plants'][$asset->id()] = [
          'label' => $asset->label(),
          'seeding_date' => $crop_planting->get('seeding_date')->value,
          'transplant_days' => $crop_planting->get('transplant_days')->value,
          'maturity_days' => $crop_planting->get('maturity_days')->value,
          'harvest_days' => $crop_planting->get('harvest_days')->value,
        ];

        // Include stages.
        $asset_location_stages = $this->cropPlan->getAssetLocationStages($asset);
        $crop_planting_stages = $this->cropPlan->getCropPlantingStages($crop_planting);
        $data['plant_type'][$plant_type_id]['plants'][$asset->id()]['stages'] = [
          ...$crop_planting_stages,
          ...$asset_location_stages
        ];

        // Include logs.
        $data['plant_type'][$plant_type_id]['plants'][$asset->id()]['logs'] = array_map(function (LogInterface $log) {
          return [
            'id' => $log->id(),
            'timestamp' => $log->get('timestamp')->value,
            'status' => $log->get('status')->value,
            'label' => $log->label(),
            'type' => $log->bundle(),
          ];
        }, $this->cropPlan->getLogs($crop_planting));
      }
    }
    return new JsonResponse($data);
  }

  /**
   * API endpoint for crop plan timeline by location.
   *
   * @param \Drupal\plan\Entity\PlanInterface $plan
   *   The crop plan.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Json response of timeline data.
   */
  public function byLocation(PlanInterface $plan) {

    $data = [];
    $crop_plantings_by_location = $this->cropPlan->getCropPlantingsByLocation($plan);
    foreach ($crop_plantings_by_location as $location_id => $crop_plantings) {
      $location_asset = $this->entityTypeManager()->getStorage('asset')->load($location_id);
      $data['location'][$location_id] = [
        'label' => $location_asset->label(),
        'plants' => [],
      ];

      foreach ($crop_plantings as $crop_planting) {
        $asset = $crop_planting->get('plant')->referencedEntities()[0];
        $crop_planting_stages = $this->cropPlan->getCropPlantingStages($crop_planting);
        $asset_location_stages = $this->cropPlan->getAssetLocationStages($asset);
        $data['location'][$location_id]['plants'][$asset->id()] = [
          'label' => $asset->label(),
          'seeding_date' => $crop_planting->get('seeding_date')->value,
          'transplant_days' => $crop_planting->get('transplant_days')->value,
          'maturity_days' => $crop_planting->get('maturity_days')->value,
          'harvest_days' => $crop_planting->get('harvest_days')->value,
        ];
        $data['location'][$location_id]['plants'][$asset->id()]['stages'] = [
          ...$crop_planting_stages,
          ...$asset_location_stages,
        ];
        // @todo Include logs.
      }
    }

    return new JsonResponse($data);
  }

}
