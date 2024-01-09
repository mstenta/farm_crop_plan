<?php

namespace Drupal\farm_crop_plan\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\farm_crop_plan\CropPlanInterface;
use Drupal\plan\Entity\PlanInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Crop plan form.
 */
class CropPlanTimelineForm extends FormBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The crop plan service.
   *
   * @var \Drupal\farm_crop_plan\CropPlanInterface
   */
  protected $cropPlan;

  /**
   * CropPlanTimelineForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\farm_crop_plan\CropPlanInterface $crop_plan
   *   The crop plan service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CropPlanInterface $crop_plan) {
    $this->entityTypeManager = $entity_type_manager;
    $this->cropPlan = $crop_plan;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('farm_crop_plan'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'crop_plan_timeline_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $plan = NULL) {

    // If a plan is not available, bail.
    if (empty($plan) || !($plan instanceof PlanInterface) || $plan->bundle() != 'crop') {
      return [
        '#type' => 'markup',
        '#markup' => 'No crop plan was provided.',
      ];
    }

    // Add a wrapper for the timeline.
    $form['timeline'] = [
      '#type' => 'details',
      '#title' => $this->t('Timeline'),
      '#open' => TRUE,
      '#attributes' => [
        'id' => 'timeline',
      ],
    ];

    // Toggle the timeline view by plant type (default) or by location.
    $default_mode = 'plant_type';
    $form['timeline']['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Organize timeline'),
      '#options' => [
        'plant_type' => $this->t('by plant type'),
        'location' => $this->t('by location'),
      ],
      '#default_value' => $default_mode,
      '#ajax' => [
        'callback' => [$this, 'timelineCallback'],
        'wrapper' => 'timeline',
      ],
    ];

    // Get the selected display mode from form state.
    $display_mode = $form_state->getValue('mode', $default_mode);

    // If the display mode is "by plant type", create a section for each plant
    // type, with a timeline element for each plant of that type.
    if ($display_mode == 'plant_type') {
      $crop_plantings_by_type = $this->cropPlan->getCropPlantingsByType($plan);
      foreach ($crop_plantings_by_type as $plant_type_id => $crop_plantings) {
        $plant_type = $this->entityTypeManager->getStorage('taxonomy_term')->load($plant_type_id);
        $form['timeline'][$plant_type_id] = [
          '#type' => 'details',
          '#title' => $this->t('Plant type: @plant_type', ['@plant_type' => $plant_type->label()]),
          '#open' => TRUE,
        ];
        $form['timeline'][$plant_type_id]['timeline'] = $this->renderCropPlantingsTimeline($crop_plantings);
      }
    }

    // Or, if the display mode is "by location", create a section for each
    // location, with a timeline element for each plant in that location.
    elseif ($display_mode == 'location') {
      $crop_plantings_by_location = $this->cropPlan->getCropPlantingsByLocation($plan);
      foreach ($crop_plantings_by_location as $location_id => $crop_plantings) {
        $location_asset = $this->entityTypeManager->getStorage('asset')->load($location_id);
        $form['timeline'][$location_id] = [
          '#type' => 'details',
          '#title' => $this->t('Location: @location', ['@location' => $location_asset->label()]),
          '#open' => TRUE,
        ];
        $form['timeline'][$location_id]['timeline'] = $this->renderCropPlantingsTimeline($crop_plantings);
      }
    }

    return $form;
  }

  /**
   * Helper function for rendering crop plantings timeline.
   *
   * @param \Drupal\plan\Entity\PlanRecordInterface[] $crop_plantings
   *   An array of crop plantings.
   *
   * @return array
   *   Returns a rendered timeline of the provided crop plantings.
   */
  protected function renderCropPlantingsTimeline(array $crop_plantings) {

    // Render a simple table of crop_planting record data.
    $header = [
      'plant' => $this->t('Plant asset'),
      'seeding_date' => $this->t('Seeding date'),
      'transplant_days' => $this->t('Days to transplant'),
      'maturity_days' => $this->t('Days to harvest'),
      'harvest_days' => $this->t('Harvest window'),
      'logs' => $this->t('Logs'),
      'crop_planting_stages' => $this->t('Crop planting stages'),
      'asset_location_stages' => $this->t('Asset location stages'),
    ];
    $rows = [];
    if (!empty($crop_plantings)) {
      foreach ($crop_plantings as $crop_planting) {
        $asset = $crop_planting->get('plant')->referencedEntities()[0];
        $logs = $this->cropPlan->getLogs($crop_planting);
        $crop_planting_stages = $this->cropPlan->getCropPlantingStages($crop_planting);
        $asset_location_stages = $this->cropPlan->getAssetLocationStages($asset);
        $rows[] = [
          $asset->toLink(),
          date('Y-m-d', $crop_planting->get('seeding_date')->value),
          $crop_planting->get('transplant_days')->value,
          $crop_planting->get('maturity_days')->value,
          $crop_planting->get('harvest_days')->value,
          Link::fromTextAndUrl(count($logs), Url::fromRoute('view.farm_log.page_asset', ['asset' => $asset->id()])),
          count($crop_planting_stages),
          count($asset_location_stages),
        ];
      }
    }
    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
  }

  /**
   * Ajax callback for timeline.
   */
  public function timelineCallback(array $form, FormStateInterface $form_state) {
    return $form['timeline'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
