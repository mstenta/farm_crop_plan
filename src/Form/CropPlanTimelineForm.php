<?php

namespace Drupal\farm_crop_plan\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\farm_crop_plan\CropPlanInterface;
use Drupal\plan\Entity\PlanInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Crop plan form.
 */
class CropPlanTimelineForm extends FormBase {

  /**
   * The crop plan service.
   *
   * @var \Drupal\farm_crop_plan\CropPlanInterface
   */
  protected $cropPlan;

  /**
   * CropPlanTimelineForm constructor.
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

    // Load crop_planting records associated with this plan.
    $crop_plantings = $this->cropPlan->getCropPlantings($plan);

    // Render a simple table of crop_planting record data.
    $header = [
      'plant' => $this->t('Plant asset'),
      'seeding_date' => $this->t('Seeding date'),
      'transplant_days' => $this->t('Days to transplant'),
      'maturity_days' => $this->t('Days to harvest'),
      'harvest_days' => $this->t('Harvest window'),
    ];
    $rows = [];
    if (!empty($crop_plantings)) {
      foreach ($crop_plantings as $crop_planting) {
        $rows[] = [
          $crop_planting->get('plant')->referencedEntities()[0]->label(),
          date('Y-m-d', $crop_planting->get('seeding_date')->value),
          $crop_planting->get('transplant_days')->value,
          $crop_planting->get('maturity_days')->value,
          $crop_planting->get('harvest_days')->value,
        ];
      }
    }
    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
