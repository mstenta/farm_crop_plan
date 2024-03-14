<?php

namespace Drupal\farm_crop_plan\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
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

    // Toggle the timeline view by plant type (default) or by location.
    $mode_options = [
      'plant-type' => $this->t('Plant type'),
      'location' => $this->t('Location'),
    ];
    $mode_default = 'plant-type';
    $form['options'] = [
      '#type' => 'details',
      '#title' => $this->t('Options'),
      '#weight' => 100,
    ];
    $form['options']['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Organize timeline by'),
      '#options' => $mode_options,
      '#default_value' => $mode_default,
      '#ajax' => [
        'callback' => [$this, 'timelineCallback'],
        'wrapper' => 'timeline-wrapper',
      ],
    ];

    // Add a wrapper for the timeline.
    $form['timeline'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'id' => 'timeline-wrapper',
        'class' => ['gin-layer-wrapper'],
      ],
    ];

    // Get the selected display mode from form state.
    $display_mode = $form_state->getValue('mode', $mode_default);

    // Render the timeline gantt chart.
    $form['timeline']['gantt'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'id' => 'timeline',
        'data-table-header' => $mode_options[$display_mode],
        'data-timeline-url' => 'plan/' . $plan->id() . '/timeline/' . $display_mode,
        'data-timeline-instantiator' => 'farm_crop_plan',
      ],
      '#attached' => [
        'library' => ['farm_crop_plan/timeline_gantt'],
      ],
    ];

    return $form;
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
