<?php

/**
 * @file
 * farm_crop_plan.features.inc
 */

/**
 * Implements hook_ctools_plugin_api().
 */
function farm_crop_plan_ctools_plugin_api($module = NULL, $api = NULL) {
  if ($module == "field_group" && $api == "field_group") {
    return array("version" => "1");
  }
}

/**
 * Implements hook_default_farm_plan_type().
 */
function farm_crop_plan_default_farm_plan_type() {
  $items = array();
  $items['crop'] = entity_import('farm_plan_type', '{
    "type" : "crop",
    "label" : "Crop Plan",
    "weight" : 0,
    "rdf_mapping" : []
  }');
  return $items;
}
