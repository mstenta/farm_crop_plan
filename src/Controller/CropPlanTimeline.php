<?php

namespace Drupal\farm_crop_plan\Controller;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Core\Url;
use Drupal\farm_crop_plan\CropPlanInterface;
use Drupal\farm_crop_plan\TypedData\TimelineRowDefinition;
use Drupal\log\Entity\LogInterface;
use Drupal\plan\Entity\PlanInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;

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
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidService;

  /**
   * The typed data manager interface.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * The serializer service.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * CropPlanTimeline constructor.
   *
   * @param \Drupal\farm_crop_plan\CropPlanInterface $crop_plan
   *   The crop plan service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_service
   *   The UUID service.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager
   *   The typed data manager interface.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer service.
   */
  public function __construct(CropPlanInterface $crop_plan, UuidInterface $uuid_service, TypedDataManagerInterface $typed_data_manager, SerializerInterface $serializer) {
    $this->cropPlan = $crop_plan;
    $this->uuidService = $uuid_service;
    $this->typedDataManager = $typed_data_manager;
    $this->serializer = $serializer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('farm_crop_plan'),
      $container->get('uuid'),
      $container->get('typed_data_manager'),
      $container->get('serializer'),
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
    $destination_url = $plan->toUrl()->toString();
    /** @var \Drupal\farm_crop_plan\Bundle\CropPlantingInterface[] $crop_plantings_by_type */
    $crop_plantings_by_type = $this->cropPlan->getCropPlantingsByType($plan);
    foreach ($crop_plantings_by_type as $plant_type_id => $crop_plantings) {
      $plant_type = $this->entityTypeManager()->getStorage('taxonomy_term')->load($plant_type_id);
      $plant_type_url = new Url('view.farm_asset.page_term', ['taxonomy_term' => $plant_type->id()]);
      $plant_type_link = (new Link($plant_type->label(), $plant_type_url))->toString();
      $row_values = [
        'id' => "term--plant_type--$plant_type_id",
        'label' => $plant_type->label(),
        'link' => $plant_type_link,
        'expanded' => TRUE,
        'children' => [],
      ];

      // Include each crop planting record.
      /** @var \Drupal\farm_crop_plan\Bundle\CropPlantingInterface[] $crop_plantings */
      foreach ($crop_plantings as $crop_planting) {
        if ($plant = $crop_planting->getPlant()) {

          // Build tasks from the crop planting.
          $tasks = [];

          // Include planting stages.
          $edit_url = $crop_planting->toUrl('edit-form', ['query' => ['destination' => $destination_url]])->toString();
          $stage_tasks = array_map(function (array $stage) use ($edit_url) {
            $stage_type = $stage['type'];
            return [
              'id' => $this->uuidService->generate(),
              'label' => ' ',
              'edit_url' => $edit_url,
              'start' => $stage['start'],
              'end' => $stage['end'],
              'meta' => [
                'stage' => $stage_type,
              ],
              'classes' => [
                'stage',
                "stage--$stage_type",
              ],
            ];
          }, $this->cropPlan->getCropPlantingStages($crop_planting));
          array_push($tasks, ...$stage_tasks);

          // Include logs.
          $log_tasks = array_map(function (LogInterface $log) use ($destination_url) {
            $edit_url = $log->toUrl('edit-form', ['query' => ['destination' => $destination_url]])->toString();
            $log_id = $log->id();
            $bundle = $log->bundle();
            $status = $log->get('status')->value;
            return [
              'id' => $this->uuidService->generate(),
              'label' => $log->label(),
              'edit_url' => $edit_url,
              'start' => $log->get('timestamp')->value,
              'end' => $log->get('timestamp')->value + 86400,
              'meta' => [
                'label' => $log->label(),
                'entity_id' => $log_id,
                'entity_type' => 'log',
                'entity_bundle' => $bundle,
                'log_status' => $status,
              ],
              'classes' => [
                'log',
                "log--$bundle",
                "log--status-$status",
              ],
            ];
          }, $this->cropPlan->getLogs($crop_planting));
          array_push($tasks, ...$log_tasks);

          // Add the child row with tasks.
          $row_values['children'][] = [
            'id' => $this->uuidService->generate(),
            'label' => $plant->label(),
            'link' => $plant->toLink($plant->label(), 'canonical')->toString(),
            'tasks' => $tasks,
          ];
        }
      }

      // Add the row object.
      // @todo Create and instantiate a wrapper data type instead of rows.
      $row_definition = TimelineRowDefinition::create('farm_timeline_row');
      $data['rows'][] = $this->typedDataManager->create($row_definition, $row_values);
    }

    // Serialize to JSON and return response.
    $serialized = $this->serializer->serialize($data, 'json');
    return new JsonResponse($serialized, 200, [], TRUE);
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
    $destination_url = $plan->toUrl()->toString();
    /** @var \Drupal\farm_crop_plan\Bundle\CropPlantingInterface[] $crop_plantings_by_location */
    $crop_plantings_by_location = $this->cropPlan->getCropPlantingsByLocation($plan);
    foreach ($crop_plantings_by_location as $location_id => $crop_plantings) {
      $location_asset = $this->entityTypeManager()->getStorage('asset')->load($location_id);
      $row_values = [
        'id' => "asset--location--$location_id",
        'label' => $location_asset->label(),
        'link' => $location_asset->toLink()->toString(),
        'expanded' => TRUE,
        'children' => [],
      ];

      // Include each crop planting record.
      /** @var \Drupal\farm_crop_plan\Bundle\CropPlantingInterface[] $crop_plantings */
      foreach ($crop_plantings as $crop_planting) {
        if ($plant = $crop_planting->getPlant()) {

          // Build tasks from the crop planting.
          $tasks = [];

          // Include planting stages.
          $edit_url = $crop_planting->toUrl('edit-form', ['query' => ['destination' => $destination_url]])->toString();
          $stage_tasks = array_map(function (array $stage) use ($edit_url) {
            $stage_type = $stage['type'];
            return [
              'id' => $this->uuidService->generate(),
              'label' => ' ',
              'edit_url' => $edit_url,
              'start' => $stage['start'],
              'end' => $stage['end'],
              'meta' => [
                'stage' => $stage_type,
              ],
              'classes' => [
                'stage',
                "stage--$stage_type",
              ],
            ];
          }, $this->cropPlan->getCropPlantingStages($crop_planting));
          array_push($tasks, ...$stage_tasks);

          // Include logs.
          $log_tasks = array_map(function (LogInterface $log) use ($destination_url) {
            $edit_url = $log->toUrl('edit-form', ['query' => ['destination' => $destination_url]])->toString();
            $log_id = $log->id();
            $bundle = $log->bundle();
            $status = $log->get('status')->value;
            return [
              'id' => $this->uuidService->generate(),
              'label' => $log->label(),
              'edit_url' => $edit_url,
              'start' => $log->get('timestamp')->value,
              'end' => $log->get('timestamp')->value + 86400,
              'meta' => [
                'label' => $log->label(),
                'entity_id' => $log_id,
                'entity_type' => 'log',
                'entity_bundle' => $bundle,
                'log_status' => $status,
              ],
              'classes' => [
                'log',
                "log--$bundle",
                "log--status-$status",
              ],
            ];
          }, $this->cropPlan->getLogs($crop_planting));
          array_push($tasks, ...$log_tasks);

          // Add the child row with tasks.
          $row_values['children'][] = [
            'id' => $this->uuidService->generate(),
            'label' => $plant->label(),
            'link' => $plant->toLink($plant->label(), 'canonical')->toString(),
            'tasks' => $tasks,
          ];
        }
      }

      // Add the row object.
      // @todo Create and instantiate a wrapper data type instead of rows.
      $row_definition = TimelineRowDefinition::create('farm_timeline_row');
      $data['rows'][] = $this->typedDataManager->create($row_definition, $row_values);
    }

    // Serialize to JSON and return response.
    $serialized = $this->serializer->serialize($data, 'json');
    return new JsonResponse($serialized, 200, [], TRUE);
  }

}
