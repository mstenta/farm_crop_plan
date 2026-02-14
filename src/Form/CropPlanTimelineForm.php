<?php

declare(strict_types=1);

namespace Drupal\farm_crop_plan\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\farm_crop_plan\CropPlanInterface;
use Drupal\plan\Entity\PlanInterface;

/**
 * Crop plan form.
 */
class CropPlanTimelineForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CropPlanInterface $cropPlan,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_crop_plan_timeline_form';
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
      'plant_type' => $this->t('Plant type'),
      'location' => $this->t('Location'),
    ];
    $mode_default = 'plant_type';
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

    // Render the timeline.
    $row_url = Url::fromRoute("farm_crop_plan.timeline_by_$display_mode", ['plan' => $plan->id()]);
    $form['timeline']['gantt'] = [
      '#type' => 'farm_timeline',
      '#rows' => [$row_url->setAbsolute()->toString()],
      '#attributes' => [
        'data-table-header' => $this->t('Plant assets (by @type)', ['@type' => $mode_options[$display_mode]]),
      ],
      '#attached' => [
        'library' => ['farm_crop_plan/timeline'],
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
