<?php

/**
 * @file
 * farm_crop_plan.features.field_instance.inc
 */

/**
 * Implements hook_field_default_field_instances().
 */
function farm_crop_plan_field_default_field_instances() {
  $field_instances = array();

  // Exported field_instance: 'farm_plan-crop-field_farm_files'.
  $field_instances['farm_plan-crop-field_farm_files'] = array(
    'bundle' => 'crop',
    'deleted' => 0,
    'description' => '',
    'display' => array(
      'default' => array(
        'label' => 'above',
        'module' => 'file',
        'settings' => array(),
        'type' => 'file_table',
        'weight' => 6,
      ),
    ),
    'entity_type' => 'farm_plan',
    'field_name' => 'field_farm_files',
    'label' => 'Files',
    'required' => 0,
    'settings' => array(
      'description_field' => 1,
      'file_directory' => 'farm/plan/crop',
      'file_extensions' => '',
      'max_filesize' => '',
      'user_register_form' => FALSE,
    ),
    'widget' => array(
      'active' => 1,
      'module' => 'multiupload_filefield_widget',
      'settings' => array(
        'progress_indicator' => 'bar',
      ),
      'type' => 'file_mfw',
      'weight' => 5,
    ),
  );

  // Exported field_instance: 'farm_plan-crop-field_farm_notes'.
  $field_instances['farm_plan-crop-field_farm_notes'] = array(
    'bundle' => 'crop',
    'default_value' => NULL,
    'deleted' => 0,
    'description' => '',
    'display' => array(
      'default' => array(
        'label' => 'inline',
        'module' => 'text',
        'settings' => array(),
        'type' => 'text_default',
        'weight' => 5,
      ),
    ),
    'entity_type' => 'farm_plan',
    'field_name' => 'field_farm_notes',
    'label' => 'Notes',
    'required' => 0,
    'settings' => array(
      'text_processing' => 1,
      'user_register_form' => FALSE,
    ),
    'widget' => array(
      'active' => 1,
      'module' => 'text',
      'settings' => array(
        'rows' => 5,
      ),
      'type' => 'text_textarea',
      'weight' => 4,
    ),
  );

  // Translatables
  // Included for use with string extractors like potx.
  t('Files');
  t('Notes');

  return $field_instances;
}
