<?php

/**
 * @file
 * Farm crop plan theme functions.
 */

/**
 * Theme function for the crop plan timeline.
 */
function theme_farm_crop_plan_timeline($variables) {

  // Get the plan ID, bail if empty.
  if (empty($variables['plan_id'])) {
    return '';
  }
  $plan_id = $variables['plan_id'];

  // Get the timeline display mode, bail if it isn't "planting" or "area".
  if (empty($variables['mode']) || !in_array($variables['mode'], array('planting', 'area'))) {
    return '';
  }
  $mode = $variables['mode'];

  // Collect information about plantings in the plan.
  $plantings = farm_crop_plan_timeline_plantings($plan_id);

  // If there are no plantings, bail.
  if (empty($plantings)) {
    return '';
  }

  // Based on the planting info, calculate the plan's start and end timestamps.
  $plan_start = NULL;
  $plan_end = NULL;
  foreach ($plantings as $planting) {
    foreach ($planting['stages'] as $stage) {
      if ((!empty($stage['start']) && $stage['start'] < $plan_start) || is_null($plan_start)) {
        $plan_start = $stage['start'];
      }
      if ((!empty($stage['end']) && $stage['end'] > $plan_end) || is_null($plan_end)) {
        $plan_end = $stage['end'];
      }
    }
  }

  // Generate header columns.
  $header = farm_crop_plan_timeline_table_header($plan_start, $plan_end);

  // Build rows, depending on the mode.
  if ($mode == 'area') {
    $rows = farm_crop_plan_timeline_area_rows($plantings, $plan_start, $plan_end);
  }
  else {
    $rows = farm_crop_plan_timeline_planting_rows($plantings, $plan_start, $plan_end);
  }

  // If there are no rows, bail.
  if (empty($rows)) {
    return '';
  }

  // Add CSS.
  drupal_add_css(drupal_get_path('module', 'farm_crop_plan') . '/farm_crop_plan.css');

  // Render the table.
  $options = array(
    'header' => $header,
    'rows' => $rows,
    'attributes' => array(
      'class' => 'farm-crop-plan',
    ),
  );
  return theme('table', $options);
}

/**
 * Collect information about plantings in a plan for timeline rendering.
 *
 * @param $plan_id
 *   The ID of the plan.
 *
 * @return array
 *   Returns an array of planting information.
 */
function farm_crop_plan_timeline_plantings($plan_id) {
  $plantings = array();

  // Load records from the {farm_crop_plan_planting} table.
  $planting_records = db_query('SELECT * FROM {farm_crop_plan_planting} WHERE plan_id = :plan_id', array(':plan_id' => $plan_id))->fetchAll();

  // If there are no planting records, bail.
  if (empty($planting_records)) {
    return $plantings;
  }

  // Iterate through the planting records to collect the information we need.
  foreach ($planting_records as $planting_record) {

    // Start an array of planting info.
    $planting_info = array();

    // Load the planting asset.
    $planting_asset = farm_asset_load($planting_record->asset_id);
    $planting_info['asset'] = $planting_asset;

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
    $planting_info['harvest_window'] = $harvest_window;

    // If there is a seeding log, load it.
    $seeding_log = NULL;
    if (!empty($planting_record->seeding_id)) {
      $seeding_log = log_load($planting_record->seeding_id);
      $planting_info['seeding'] = $seeding_log;
    }

    // If there is a transplanting log, load it.
    $transplanting_log = NULL;
    if (!empty($planting_record->transplanting_id)) {
      $transplanting_log = log_load($planting_record->transplanting_id);
      $planting_info['transplanting'] = $transplanting_log;
    }

    // If there is no seeding or transplanting log, skip.
    if (empty($seeding_log) && empty($transplanting_log)) {
      continue;
    }

    // Calculate the harvest date. This will be calculated from the seeding
    // date if it available. Otherwise, the transplanting date will be used.
    $harvest_timestamp = NULL;
    if (!empty($seeding_log->timestamp)) {
      $harvest_timestamp = strtotime('+' . $days_to_harvest . ' days', $seeding_log->timestamp);
    }
    elseif (!empty($transplanting_log->timestamp)) {
      $harvest_timestamp = strtotime('+' . $days_to_harvest . ' days', $transplanting_log->timestamp);
    }

    // Increase harvest timestamp by harvest days.
    $last_harvest_timestamp = $harvest_timestamp;
    if (!empty($harvest_window)) {
      $last_harvest_timestamp = strtotime('+' . $harvest_window . ' days', $harvest_timestamp);
    }

    // Start array of stages to visualize.
    $stages = array();

    // Display a seeding stage.
    if (!empty($seeding_log)) {

      // Generate links to areas referenced by the seeding.
      $area_links = farm_crop_plan_log_area_links($seeding_log);

      // Determine the timestamp when the planting will leave this area.
      // This will either be the transplanting timestamp (if a transplant
      // log exists), or the harvest timestamp.
      $end = $harvest_timestamp;
      if (!empty($transplanting_log->timestamp)) {
        $end = $transplanting_log->timestamp;
      }

      // Generate a link to the seeding log.
      $log_label = 'Seeding: ' . date('Y-m-d', $seeding_log->timestamp);
      $log_uri = entity_uri('log', $seeding_log);
      $log_link = l($log_label, $log_uri['path']);
      $log_text = '<small>' . $log_link . '</small>';

      // Add area links if location is included.
      if (!empty($area_links)) {
        $log_text .= '<br /> in <small>' . implode(', ', $area_links) . '</small>';
      }

      $stages[] = array(
        'start' => $seeding_log->timestamp,
        'end' => $end,
        'type' => 'planted',
        'areas' => array_keys($area_links),
        'data' => $log_text,
      );
    }

    // Display a transplanting stage.
    if (!empty($transplanting_log)) {

      // Generate links to areas referenced by the transplanting.
      $area_links = farm_crop_plan_log_area_links($transplanting_log);

      // Generate a link to the transplanting log.
      $log_label = 'Transplanting: ' . date('Y-m-d', $transplanting_log->timestamp);
      $log_uri = entity_uri('log', $transplanting_log);
      $log_link = l($log_label, $log_uri['path']);
      $log_text = '<small>' . $log_link . '</small>';

      // Add area links if location is included.
      if (!empty($area_links)) {
        $log_text .= '<br /> to <small>' . implode(', ', $area_links) . '</small>';
      }

      $stages[] = array(
        'start' => $transplanting_log->timestamp,
        'end' => $harvest_timestamp,
        'type' => 'transplanted',
        'areas' => array_keys($area_links),
        'data' => $log_text,
      );
    }

    // Display a harvest stage.
    // The harvest will take place in the transplant area, if it exists.
    // Otherwise it will take place in the seeding area.
    $harvest_areas = !empty($area_links) ? array_keys($area_links) : array();
    if (!empty($planting_record->harvest_window)) {
      $stages[] = array(
        'start' => $harvest_timestamp,
        'end' => $last_harvest_timestamp,
        'type' => 'harvest',
        'areas' => $harvest_areas,
        'data' => '<small>Harvest ' . date('Y-m-d', $harvest_timestamp) . ' for ' . format_plural($harvest_window, '1 day', '@count days') . '</small>',
      );
    }

    // Add stages to the planting info.
    $planting_info['stages'] = $stages;

    // Add planting info to plantings array.
    $plantings[$planting_asset->id] = $planting_info;
  }

  return $plantings;
}

/**
 * Sort plantings by first timestamp.
 */
function farm_crop_plan_plantings_sort($a, $b) {
  $a_start = farm_crop_plan_planting_start($a);
  $b_start = farm_crop_plan_planting_start($b);
  if ($a_start == $b_start) {
    return 0;
  }
  return ($a_start < $b_start) ? -1 : 1;
}

/**
 * Determine the start timestamp of a planting.
 *
 * @param array $planting
 *   An array of planting info from farm_crop_plan_plantings().
 *
 * @return int|bool
 *   Returns the timestamp of the earliest stage. Returns FALSE if a timestamp
 *   cannot be found.
 */
function farm_crop_plan_planting_start($planting) {
  $start = FALSE;
  foreach ($planting['stages'] as $stage) {
    if ($stage['start'] < $start || $start === FALSE) {
      $start = $stage['start'];
    }
  }
  return $start;
}

/**
 * Build an array of area links from a movement log.
 *
 * @param $log
 *   The log entity.
 *
 * @return array
 *   An array of links to areas, keyed by area ID.
 */
function farm_crop_plan_log_area_links($log) {
  $area_links = array();
  if (!empty($log->field_farm_movement[LANGUAGE_NONE][0]['value'])) {
    $movement = field_collection_item_load($log->field_farm_movement[LANGUAGE_NONE][0]['value']);
    if (!empty($movement->field_farm_move_to[LANGUAGE_NONE])) {
      foreach ($movement->field_farm_move_to[LANGUAGE_NONE] as $area_ref) {

        // If the term ID is not available, skip it.
        if (empty($area_ref['tid'])) {
          continue;
        }
        $tid = $area_ref['tid'];

        // Load the area.
        $area = taxonomy_term_load($tid);

        // Get the area label and URI.
        $entity_label = entity_label('taxonomy_term', $area);
        $entity_uri = entity_uri('taxonomy_term', $area);

        // Generate a link to the area.
        $area_link = l(t($entity_label), $entity_uri['path']);
        $area_links[$tid] = $area_link;
      }
    }
  }
  return $area_links;
}

/**
 * Build table rows for the "by planting" mode.
 *
 * @param array $plantings
 *   An array of planting info compiled by theme_farm_crop_plan_timeline().
 * @param int $plan_start
 *   The timestamp when the plan starts.
 * @param int $plan_end
 *   The timestamp when the plan ends.
 *
 * @return array
 *   An array of rows for use in theme_table().
 */
function farm_crop_plan_timeline_planting_rows($plantings, $plan_start, $plan_end) {
  $rows = array();

  // Sort plantings by their start timestamps.
  usort($plantings, 'farm_crop_plan_plantings_sort');

  // Iterate through the plantings and build rows.
  foreach ($plantings as $planting) {
    $row = array();

    // Generate a planting row to be added to the table.
    $planting_label = entity_label('farm_asset', $planting['asset']);
    $planting_uri = entity_uri('farm_asset', $planting['asset']);
    $planting_link = l(t($planting_label), $planting_uri['path']);

    // Add the planting link to the first column.
    $row[] = $planting_link;

    // Array mapping stage days to start day.
    $stage_days = array();

    // Map stages to day columns.
    foreach ($planting['stages'] as $stage) {

      // Figure out the day that this planting will start in the area, and the
      // total number of days it will spend there.
      $start_day = (int) round(($stage['start'] - $plan_start) / (60 * 60 * 24));
      $end_day = (int) round(($stage['end'] - $plan_start) / (60 * 60 * 24));

      // Calculate total days as the difference to prevent edge cases that
      // display a gap between harvest and transplant.
      $total_days = $end_day - $start_day;

      // Map stage to stage_days array by index of the start day.
      $stage_days[$start_day] = array(
        'data' => $stage['data'],
        'class' => $stage['type'],
        'colspan' => $total_days,
      );
    }

    // Generate all cells in the row.
    $num_days = farm_crop_plan_timeline_num_days($plan_start, $plan_end);
    for ($j = 0; $j < $num_days; $j++) {
      if (isset($stage_days[$j])) {
        $row[] = $stage_days[$j];
        // Update number of cells remaining.
        // Avoid rendering too many cells in each row.
        $span = $stage_days[$j]['colspan'];
        if ($span > 0) {
          $j += $span - 1;
        }
      }
      else {
        $row[] = '';
      }
    }

    // Add the row.
    $rows[] = $row;
  }

  return $rows;
}

/**
 * Build table rows for the "by area" mode.
 *
 * @param array $plantings
 *   An array of planting info compiled by theme_farm_crop_plan_timeline().
 * @param int $plan_start
 *   The timestamp when the plan starts.
 * @param int $plan_end
 *   The timestamp when the plan ends.
 *
 * @return array
 *   An array of rows for use in theme_table().
 */
function farm_crop_plan_timeline_area_rows($plantings, $plan_start, $plan_end) {
  $rows = array();

  // Sort plantings by their start timestamps.
  usort($plantings, 'farm_crop_plan_plantings_sort');

  // Group the plantings into the areas that they are in.
  $areas_plantings = array();
  foreach ($plantings as $delta => $planting) {
    foreach ($planting['stages'] as $stage) {
      foreach ($stage['areas'] as $area_id) {

        // Add the planting if it isn't already.
        if (empty($areas_plantings[$area_id][$delta])) {
          $areas_plantings[$area_id][$delta] = $planting;
        }

        // Add the seeding/transplanting log.
        if ($stage['type'] == 'planted') {
          $areas_plantings[$area_id][$delta]['log'] = $areas_plantings[$area_id][$delta]['seeding'];
        }
        elseif ($stage['type'] == 'transplanted') {
          $areas_plantings[$area_id][$delta]['log'] = $areas_plantings[$area_id][$delta]['transplanting'];
        }
        else {
          continue;
        }

        // Set the start and end dates for this stage.
        $areas_plantings[$area_id][$delta]['start'] = $stage['start'];
        $areas_plantings[$area_id][$delta]['end'] = $stage['end'];
      }
    }
  }

  // Generate rows for each area/planting combination.
  foreach ($areas_plantings as $area_id => $area_plantings) {

    // Load the area.
    $area = taxonomy_term_load($area_id);

    // Get the area label and URI.
    $entity_label = entity_label('taxonomy_term', $area);
    $entity_uri = entity_uri('taxonomy_term', $area);

    // Generate a link to the area.
    $area_link = l(t($entity_label), $entity_uri['path']);

    // Iterate through the plantings.
    foreach ($area_plantings as $planting) {
      $row = array();

      // Add the area link to the first column.
      $row[] = $area_link;

      // Only show the area link for the first planting.
      $area_link = '';

      // Figure out the day that this planting will start in the area, and the
      // total number of days it will spend there.
      $start_day = (int) round(($planting['start'] - $plan_start) / (60 * 60 * 24));
      $total_days = (int) round(($planting['end'] - $planting['start']) / (60 * 60 * 24));

      // Calculate harvest days if a harvest window was specified.
      $harvest_window = 0;
      $harvest_days = 0;
      if (!empty($planting['harvest_window'])) {
        $harvest_window = $planting['harvest_window'];
        $harvest_days = (int) $harvest_window;

        // Minimum of 1 day to ensure harvest window is displayed.
        if ($harvest_days == 0) {
          $harvest_days = 1;
        }
      }

      // If a log is not available, skip it.
      if (empty($planting['log'])) {
        continue;
      }

      // Generate a planting row to be added to the table.
      $planting_label = entity_label('farm_asset', $planting['asset']);
      $planting_uri = entity_uri('farm_asset', $planting['asset']);
      $planting_link = l(t($planting_label), $planting_uri['path']);
      switch ($planting['log']->type) {
        case 'farm_seeding':
          $log_label = t('Seeding');
          break;
        case 'farm_transplanting':
          $log_label = t('Transplanting');
          break;
        default:
          continue 2;
      }
      $log_label .= ': ' . date('Y-m-d', $planting['log']->timestamp);
      $log_uri = entity_uri('log', $planting['log']);
      $log_link = l($log_label, $log_uri['path']);
      $planting_summary = '<strong>' . $planting_link . '</strong><br /><small>' . $log_link . '</small>';
      $planting_row = array(
        'data' => $planting_summary,
        'class' => 'planted',
        'colspan' => $total_days - $harvest_days,
      );

      // Define a row for the harvest window.
      $harvest_row = array(
        'data' => '<small>' . format_plural($harvest_window, '1 day', '@count days') . '</small>',
        'class' => 'harvest',
        'colspan' => $harvest_days,
      );

      // Generate all cells in the row.
      $num_days = farm_crop_plan_timeline_num_days($plan_start, $plan_end);
      for ($j = 0; $j < $num_days; $j++) {
        if ($j == $start_day) {
          $row[] = $planting_row;
          if ($harvest_days > 0) {
            $row[] = $harvest_row;
          }
          // Update number of cells remaining.
          // Avoid rendering too many cells in each row.
          $j += $total_days - 1;
        }
        else {
          $row[] = '';
        }
      }

      // Add the row.
      $rows[] = $row;
    }
  }

  return $rows;
}

/**
 * Helper function for generating header columns for the timeline.
 *
 * @param $start
 *   Timestamp representing when the timeline starts.
 * @param $end
 *   Timestamp representing when the timeline ends.
 *
 * @return array
 *   Returns a header array for use with theme_table().
 */
function farm_crop_plan_timeline_table_header($start, $end) {
  $header = array('');
  $num_days = farm_crop_plan_timeline_num_days($start, $end);
  $time = $start;
  $i = 0;
  while ($i < $num_days) {
    $date = array(
      'data' => date('M j', $time),
      'colspan' => 7,
    );
    $next_time = strtotime('+7 days', $time);
    if (REQUEST_TIME >= $time && REQUEST_TIME <= $next_time) {
      $date['class'] = 'current';
    }
    $header[] = $date;
    $time = $next_time;
    $i += 7;
  }
  return $header;
}

/**
 * Helper function for calculating the number of days in a given time span.
 *
 * @param $start
 *   Timestamp representing when the timeline starts.
 * @param $end
 *   Timestamp representing when the timeline ends.
 *
 * @return int
 *   Returns the number of days between the start and end timestamps.
 */
function farm_crop_plan_timeline_num_days($start, $end) {
  return (int) round(($end - $start) / (60 * 60 * 24));
}
