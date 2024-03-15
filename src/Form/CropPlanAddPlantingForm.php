<?php

namespace Drupal\farm_crop_plan\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\plan\Entity\PlanInterface;
use Drupal\plan\Entity\PlanRecord;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Crop plan add planting form.
 */
class CropPlanAddPlantingForm extends FormBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * CropPlanAddPlantingForm constructor.
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
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_crop_plan_add_planting_form';
  }

  /**
   * Title callback.
   *
   * @param \Drupal\plan\Entity\PlanInterface|null $plan
   *   The plan entity.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function title(PlanInterface $plan = NULL) {
    if (empty($plan)) {
      return $this->t('Add planting');
    }
    return $this->t('Add planting to @plan', ['@plan' => $plan->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, PlanInterface $plan = NULL) {
    if (empty($plan)) {
      return;
    }
    $form_state->set('plan_id', $plan->id());

    $form['plant'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Plant asset'),
      '#target_type' => 'asset',
      '#selection_settings' => [
        'target_bundles' => ['plant'],
      ],
      '#required' => TRUE,
    ];

    $form['seeding_date'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Seeding date'),
      '#default_value' => new DrupalDateTime('midnight', $this->currentUser()->getTimeZone()),
      '#required' => TRUE,
    ];

    $form['transplant_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Days to transplant'),
      '#step' => 1,
      '#min' => 1,
      '#max' => 365,
    ];

    $form['maturity_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Days to harvest'),
      '#step' => 1,
      '#min' => 1,
      '#max' => 365,
    ];

    $form['harvest_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Harvest window (days)'),
      '#step' => 1,
      '#min' => 1,
      '#max' => 365,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Require plant asset.
    $plant = $form_state->getValue('plant');
    if (empty($plant)) {
      $form_state->setErrorByName('plant', $this->t('Select a plant asset.'));
      return;
    }

    // Check for existing crop_planting records for the plan and plant.
    $plan_id = $form_state->get('plan_id');
    $existing = $this->entityTypeManager->getStorage('plan_record')->getQuery()
      ->accessCheck(FALSE)
      ->condition('plan', $plan_id)
      ->condition('plant', $plant)
      ->count()
      ->execute();
    if ($existing > 0) {
      $form_state->setErrorByName('plant', $this->t('This plant asset is already added to the plan.'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $plan_id = $form_state->get('plan_id');
    $plant = $form_state->getValue('plant');
    $record = PlanRecord::create([
      'type' => 'crop_planting',
      'plan' => $plan_id,
      'plant' => $plant,
      'seeding_date' => $form_state->getValue('seeding_date')->getTimestamp(),
      'transplant_days' => $form_state->getValue('transplant_days'),
      'maturity_days' => $form_state->getValue('maturity_days'),
      'harvest_days' => $form_state->getValue('harvest_days'),
    ]);
    $record->save();
    $this->messenger()->addMessage($this->t('Added @crop_planting', ['@crop_planting' => $record->label()]));
    $form_state->setRedirect('entity.plan.canonical', ['plan' => $plan_id]);
  }

}
