<?php

/**
 * @file
 * farm_crop_plan.field_group.inc
 */

/**
 * Implements hook_field_group_info().
 */
function farm_crop_plan_field_group_info() {
  $field_groups = array();

  $field_group = new stdClass();
  $field_group->disabled = FALSE; /* Edit this to true to make a default field_group disabled initially */
  $field_group->api_version = 1;
  $field_group->identifier = 'group_farm_files|farm_plan|crop|form';
  $field_group->group_name = 'group_farm_files';
  $field_group->entity_type = 'farm_plan';
  $field_group->bundle = 'crop';
  $field_group->mode = 'form';
  $field_group->parent_name = 'group_farm_tabs';
  $field_group->data = array(
    'label' => 'Files',
    'weight' => '7',
    'children' => array(
      0 => 'field_farm_files',
    ),
    'format_type' => 'field_group_easy_responsive_tabs_nav_item',
    'format_settings' => array(
      'formatter' => '',
      'instance_settings' => array(
        'classes' => 'group-farm-files field-group-field_group_easy_responsive_tabs_nav_item',
        'required_fields' => 1,
      ),
    ),
  );
  $field_groups['group_farm_files|farm_plan|crop|form'] = $field_group;

  $field_group = new stdClass();
  $field_group->disabled = FALSE; /* Edit this to true to make a default field_group disabled initially */
  $field_group->api_version = 1;
  $field_group->identifier = 'group_farm_general|farm_plan|crop|form';
  $field_group->group_name = 'group_farm_general';
  $field_group->entity_type = 'farm_plan';
  $field_group->bundle = 'crop';
  $field_group->mode = 'form';
  $field_group->parent_name = 'group_farm_tabs';
  $field_group->data = array(
    'label' => 'General',
    'weight' => '4',
    'children' => array(
      0 => 'field_farm_notes',
      1 => 'name',
      2 => 'field_farm_season',
    ),
    'format_type' => 'field_group_easy_responsive_tabs_nav_item',
    'format_settings' => array(
      'label' => 'General',
      'instance_settings' => array(
        'required_fields' => 1,
        'classes' => 'group-farm-general field-group-field_group_easy_responsive_tabs_nav_item',
      ),
    ),
  );
  $field_groups['group_farm_general|farm_plan|crop|form'] = $field_group;

  $field_group = new stdClass();
  $field_group->disabled = FALSE; /* Edit this to true to make a default field_group disabled initially */
  $field_group->api_version = 1;
  $field_group->identifier = 'group_farm_tabs|farm_plan|crop|form';
  $field_group->group_name = 'group_farm_tabs';
  $field_group->entity_type = 'farm_plan';
  $field_group->bundle = 'crop';
  $field_group->mode = 'form';
  $field_group->parent_name = '';
  $field_group->data = array(
    'label' => 'Tabs',
    'weight' => '0',
    'children' => array(
      0 => 'group_farm_files',
      1 => 'group_farm_general',
    ),
    'format_type' => 'field_group_easy_responsive_tabs_nav',
    'format_settings' => array(
      'label' => 'Tabs',
      'instance_settings' => array(
        'type' => 'vertical',
        'width' => 'auto',
        'fit' => '1',
        'closed' => '0',
        'activetab_bg' => '',
        'inactive_bg' => '',
        'active_border_color' => '',
        'active_content_border_color' => '',
        'tabidentify' => '',
        'required_fields' => 1,
        'classes' => 'group-farm-tabs field-group-field_group_easy_responsive_tabs_nav',
      ),
    ),
  );
  $field_groups['group_farm_tabs|farm_plan|crop|form'] = $field_group;

  // Translatables
  // Included for use with string extractors like potx.
  t('Files');
  t('General');
  t('Tabs');

  return $field_groups;
}
