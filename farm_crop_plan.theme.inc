<?php

/**
 * @file
 * Farm crop plan theme functions.
 */

/**
 * Theme function for the crop plan, organized by area.
 */
function theme_farm_crop_plan_areas($variables) {
  $output = '';

  // Get the plan ID, bail if empty.
  if (empty($variables['plan_id'])) {
    return $output;
  }
  $plan_id = $variables['plan_id'];

  // Load record from the {farm_crop_plan_planting} table.
  $planting_records = db_query('SELECT * FROM {farm_crop_plan_planting} WHERE plan_id = :plan_id', array(':plan_id' => $plan_id))->fetchAll();

  // If there are no planting records, bail.
  if (empty($planting_records)) {
    return $output;
  }

  // Keep track of the earliest and latest timestamps in this plan.
  $plan_start = NULL;
  $plan_end= NULL;

  // Maintain an index of plantings by area.
  $areas_plantings = array();

  // Iterate through the planting records.
  foreach ($planting_records as $planting_record) {

    // Load the planting asset.
    $planting_asset = farm_asset_load($planting_record->asset_id);

    // Load "Days to harvest". Skip if empty.
    if (empty($planting_record->days_to_harvest)) {
      continue;
    }
    $days_to_harvest = $planting_record->days_to_harvest;

    // Load "Harvest window". Default to 0 days.
    $harvest_window = $planting_record->harvest_window;
    if (empty($harvest_window)) {
      $harvest_window = 0;
    }

    // If there is a seeding log, load it.
    $seeding_log = NULL;
    if (!empty($planting_record->seeding_id)) {
      $seeding_log = log_load($planting_record->seeding_id);
    }

    // If there is a transplanting log, load it.
    $transplanting_log = NULL;
    if (!empty($planting_record->transplanting_id)) {
      $transplanting_log = log_load($planting_record->transplanting_id);
    }

    // If there is no seeding or transplanting log, skip.
    if (empty($seeding_log) && empty($transplanting_log)) {
      continue;
    }

    // Update the plan's earliest timestamp.
    if ((!empty($seeding_log->timestamp) && $seeding_log->timestamp < $plan_start) || is_null($plan_start)) {
      $plan_start = $seeding_log->timestamp;
    }
    if ((!empty($transplanting_log->timestamp) && $transplanting_log->timestamp < $plan_start) || is_null($plan_start)) {
      $plan_start = $transplanting_log->timestamp;
    }

    // Calculate the harvest date. This will be calculated from the transplanting
    // date if it available. Otherwise, the seeding date will be used.
    $harvest_timestamp = NULL;
    if (!empty($transplanting_log->timestamp)) {
      $harvest_timestamp = strtotime('+' . $days_to_harvest . ' days', $transplanting_log->timestamp);
    }
    elseif (!empty($seeding_log->timestamp)) {
      $harvest_timestamp = strtotime('+' . $days_to_harvest . ' days', $seeding_log->timestamp);
    }

    // Increase harvest timestamp by harvest days.
    if (!empty($harvest_timestamp)) {
      $harvest_timestamp = strtotime('+' . $harvest_window . ' days', $harvest_timestamp);
    }

    // Update the plan's latest timestamp.
    if ($harvest_timestamp > $plan_end) {
      $plan_end = $harvest_timestamp;
    }

    // Iterate through areas referenced by the seeding.
    if (!empty($seeding_log->field_farm_movement[LANGUAGE_NONE][0]['value'])) {
      $movement = field_collection_item_load($seeding_log->field_farm_movement[LANGUAGE_NONE][0]['value']);
      if (!empty($movement->field_farm_move_to[LANGUAGE_NONE])) {
        foreach ($movement->field_farm_move_to[LANGUAGE_NONE] as $area_ref) {
          if (!empty($area_ref['tid'])) {

            // Determine the timestamp when the planting will leave this area.
            // This will either be the transplanting timestamp (if a transplant
            // log exists), or the harvest timestamp.
            $end = $harvest_timestamp;
            if (!empty($transplanting_log->timestamp)) {
              $end = $transplanting_log->timestamp;
            }

            // Build a summary of the planting for this area.
            $areas_plantings[$area_ref['tid']][] = array(
              'asset' => $planting_asset,
              'log' => $seeding_log,
              'start' => $seeding_log->timestamp,
              'end' => $end,
            );
          }
        }
      }
    }

    // Iterate through areas referenced by the transplanting.
    if (!empty($transplanting_log->field_farm_movement[LANGUAGE_NONE][0]['value'])) {
      $movement = field_collection_item_load($transplanting_log->field_farm_movement[LANGUAGE_NONE][0]['value']);
      if (!empty($movement->field_farm_move_to[LANGUAGE_NONE])) {
        foreach ($movement->field_farm_move_to[LANGUAGE_NONE] as $area_ref) {
          if (!empty($area_ref['tid'])) {

            // Build a summary of the planting for this area.
            $areas_plantings[$area_ref['tid']][] = array(
              'asset' => $planting_asset,
              'log' => $transplanting_log,
              'start' => $transplanting_log->timestamp,
              'end' => $harvest_timestamp,
              'harvest_window' => $harvest_window,
            );
          }
        }
      }
    }
  }

  // Add inline CSS.
  drupal_add_css('table td.planted {background-color: #dff0d8;} table td.harvest {background-color: #9ADB7F','inline');

  // Generate header columns for each week.
  $header = array('');
  $num_weeks = (int) round(($plan_end - $plan_start) / (60 * 60 * 24 * 7));
  $time = $plan_start;
  for ($i = 0; $i < $num_weeks; $i++) {
    $header[] = date('M j', $time);
    $time = strtotime('+7 days', $time);
  }

  // Generate rows for each area/planting combination.
  $rows = array();
  foreach ($areas_plantings as $area_id => $plantings) {

    // Load the area.
    $area = taxonomy_term_load($area_id);

    // Get the area label and URI.
    $entity_label = entity_label('taxonomy_term', $area);
    $entity_uri = entity_uri('taxonomy_term', $area);

    // Generate a link to the area.
    $area_link = l(t($entity_label), $entity_uri['path']);

    // Iterate through the plantings.
    foreach ($plantings as $planting) {
      $row = array();

      // Add the area link to the first column.
      $row[] = $area_link;

      // Only show the area link for the first planting.
      $area_link = '';

      // Figure out the week that this planting will start in the area, and the
      // total number of weeks it will spend there.
      $start_week = (int) round(($planting['start'] - $plan_start) / (60 * 60 * 24 * 7));
      $total_weeks = (int) round(($planting['end'] - $planting['start']) / (60 * 60 * 24 * 7));

      // Calculate harvest weeks if a harvest window was specified.
      $harvest_window = 0;
      $harvest_weeks = 0;
      if (!empty($planting['harvest_window'])) {
        $harvest_window = $planting['harvest_window'];
        $harvest_weeks = (int) round($harvest_window / 7);

        // Minimum of 1 week to ensure harvest window is displayed.
        if ($harvest_weeks == 0) {
          $harvest_weeks = 1;
        }
      }

      // Generate a planting row to be added to the table.
      $planting_label = entity_label('farm_asset', $planting['asset']);
      $planting_uri = entity_uri('farm_asset', $planting['asset']);
      $planting_link = l(t($planting_label), $planting_uri['path']);
      $log_label = '';
      switch ($planting['log']->type) {
        case 'farm_seeding':
          $log_label = t('Seeding');
          break;
        case 'farm_transplanting':
          $log_label = t('Transplanting');
          break;
      }
      $log_label .= ': ' . date('Y-m-d', $planting['log']->timestamp);
      $log_uri = entity_uri('log', $planting['log']);
      $log_link = l($log_label, $log_uri['path']);
      $planting_summary = '<strong>' . $planting_link . '</strong><br /><small>' . $log_link . '</small>';
      $planting_row = array(
        'data' => $planting_summary,
        'class' => 'planted',
        'colspan' => $total_weeks - $harvest_weeks,
      );

      // Define a row for the harvest window.
      $harvest_row = array(
        'data' => '<small>' . format_plural($harvest_window, '1 day', '@count days') . '</small>',
        'class' => 'harvest',
        'colspan' => $harvest_weeks,
      );

      // Generate all cells in the row.
      for ($j = 0; $j < $num_weeks; $j++) {
        if ($j == $start_week) {
          $row[] = $planting_row;
          if ($harvest_weeks > 0) {
            $row[] = $harvest_row;
          }
          // Update number of cells remaining.
          // Avoid rendering too many cells in each row.
          $j += $total_weeks - 1;
        }
        else {
          $row[] = '';
        }
      }

      // Add the row.
      $rows[] = $row;
    }
  }

  // Render the table.
  $options = array(
    'header' => $header,
    'rows' => $rows,
  );
  $output .= theme('table', $options);

  return $output;
}
