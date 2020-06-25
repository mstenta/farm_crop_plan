<?php

/**
 * @file
 * Farm crop plan theme functions.
 */

/**
 * Theme function for the crop plan timeline.
 */
function theme_farm_crop_plan_timeline($variables) {
  $form = $variables['form'];
  $output = '';

  // Get the plan, bail if empty.
  if (empty($form['#plan'])) {
    return $output;
  }
  $plan = $form['#plan'];

  // Get the timeline display mode, bail if it isn't "planting" or "area".
  if (empty($form['#mode']) || !in_array($form['#mode'], array('planting', 'area'))) {
    return $output;
  }
  $mode = $form['#mode'];

  // Collect information about plantings in the plan.
  $plantings = farm_crop_plan_plantings($plan->id);

  // If there are no plantings, bail.
  if (empty($plantings)) {
    return $output;
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

  // Set colgroup widths.
  $colgroups = array();
  $days = farm_crop_plan_timeline_num_days($plan_start, $plan_end);
  $percentage = round(1 / $days, 2);
  for ($i = 0; $i <= $days; $i++) {
    $colgroups[0][] = array(
      'style' => 'width: ' . $percentage . '%;',
    );
  }

  // Generate header columns.
  $header = farm_crop_plan_timeline_table_header($plan_start, $plan_end);

  // Build rows, depending on the mode.
  if ($mode == 'area') {
    $rows = farm_crop_plan_timeline_area_rows($plantings, $plan_start, $plan_end);
  }
  else {
    $rows = farm_crop_plan_timeline_planting_rows($form, $plantings, $plan_start, $plan_end);
  }

  // If there are no rows, bail.
  if (empty($rows)) {
    return '';
  }

  // Add CSS.
  drupal_add_css(drupal_get_path('module', 'farm_crop_plan') . '/farm_crop_plan.css');

  // Render the table.
  $options = array(
    'colgroups' => $colgroups,
    'header' => $header,
    'rows' => $rows,
    'attributes' => array(
      'class' => 'farm-crop-plan',
    ),
  );
  $output .= theme('table', $options);

  // Render remaining form elements.
  $output .= drupal_render_children($form);

  // Return the output.
  return $output;
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
 * Build table rows for the "by planting" mode.
 *
 * @param array &$form
 *   The form array from theme_farm_crop_plan_timeline(). Passed as reference so
 *   that drupal_render() can be run on child elements.
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
function farm_crop_plan_timeline_planting_rows(&$form, $plantings, $plan_start, $plan_end) {
  $rows = array();

  // Sort plantings by their start timestamps.
  usort($plantings, 'farm_crop_plan_plantings_sort');

  // Iterate through the plantings and build rows.
  foreach ($plantings as $planting) {
    $row = array(
      'data' => array(),
    );

    // If the asset doesn't exist, skip.
    if (empty($planting['asset'])) {
      continue;
    }

    // Generate a planting row to be added to the table.
    $planting_label = htmlspecialchars(entity_label('farm_asset', $planting['asset']));
    $planting_uri = entity_uri('farm_asset', $planting['asset']);
    $planting_link = l($planting_label, $planting_uri['path']);

    // Add the planting link to the first column.
    $row['data'][] = array(
      'data' => '<strong>' . $planting_link . '</strong>',
      'class' => array('left'),
    );

    // Array mapping stage days to start day.
    $stage_days = array();

    // Map stages to day columns.
    foreach ($planting['stages'] as $stage) {

      // Build the stage content, depending on the type.
      $stage_content = '';
      if ($stage['type'] == 'planted') {
        $log_label = t('Seeding') . ': ' . date('Y-m-d', $stage['start']);
        $log_uri = entity_uri('log', $planting['seeding']);
        $log_link = l($log_label, $log_uri['path']);
        $stage_content = '<small>' . $log_link . '</small>';
      }
      elseif ($stage['type'] == 'transplanted') {
        $log_label = t('Transplanting') . ': ' . date('Y-m-d', $stage['start']);
        $log_uri = entity_uri('log', $planting['transplanting']);
        $log_link = l($log_label, $log_uri['path']);
        $stage_content = '<small>' . $log_link . '</small>';
      }
      elseif ($stage['type'] == 'harvest') {
        $stage_content = '<small>'. t('Harvest') . ' ' . date('Y-m-d', $stage['start']) . ' ' . t('for') . ' ' . format_plural($planting['harvest_window'], '1 day', '@count days') . '</small>';
      }

      // Add area links to seeding/transplanting stages, if available.
      if (in_array($stage['type'], array('planted', 'transplanted'))) {
        if ($stage['type'] == 'planted') {
          $log = $planting['seeding'];
        }
        elseif ($stage['type'] == 'transplanted') {
          $log = $planting['transplanting'];
        }
        $area_links = farm_crop_plan_movement_log_area_links($log);
        if (!empty($area_links)) {
          $stage_content .= '<br /> ' . t('in') . ' <small>' . implode(', ', $area_links) . '</small>';
        }
      }

      // If both start and end timestamps are not available, stop now.
      if (empty($stage['start']) || empty($stage['end'])) {
        continue;
      }

      // Figure out the day that this planting will start in the area, and the
      // total number of days it will spend there.
      $start_day = (int) round(($stage['start'] - $plan_start) / (60 * 60 * 24));
      $end_day = (int) round(($stage['end'] - $plan_start) / (60 * 60 * 24));

      // Calculate total days as the difference to prevent edge cases that
      // display a gap between harvest and transplant.
      $total_days = $end_day - $start_day;

      // Map stage to stage_days array by index of the start day.
      $stage_days[$start_day] = array(
        'data' => $stage_content,
        'class' => array('stage', $stage['type']),
        'colspan' => $total_days,
      );
    }

    // Generate all cells in the row.
    $num_days = farm_crop_plan_timeline_num_days($plan_start, $plan_end);
    for ($j = 0; $j < $num_days; $j++) {
      if (isset($stage_days[$j])) {
        $row['data'][] = $stage_days[$j];
        // Update number of cells remaining.
        // Avoid rendering too many cells in each row.
        $span = $stage_days[$j]['colspan'];
        if ($span > 0) {
          $j += $span - 1;
        }
      }
      else {
        $row['data'][] = '';
      }
    }

    // If a planting form exists with a matching planting ID, assemble a second
    // row for it.
    if (!empty($form['plantings'][$planting['asset']->id])) {

      // Generate a unique for the planting form row.
      $planting_form_id = drupal_html_id('farm-crop-plan-planting-form-' . $planting['asset']->id);

      // Add a link to the timeline row for toggling form collapse state.
      $toggle = '<a class="btn btn-sm btn-info" role="button" data-toggle="collapse" href="#' . $planting_form_id . '" aria-expanded="false" aria-controls="' . $planting_form_id . '">Edit</a>';
      $row['data'][0]['data'] .= '<br />' . $toggle;

      // Unset the fieldset title and collapsed state, because the timeline row
      // will take over as the collapse controller.
      unset($form['plantings'][$planting['asset']->id]['#title']);
      unset($form['plantings'][$planting['asset']->id]['#collapsed']);

      // Assemble the form row with a full-width colspan.
      $form_row = array(
        array(
          'data' => '<div id="' . $planting_form_id . '" class="collapse">' . drupal_render($form['plantings'][$planting['asset']->id]) . '</div>',
          'colspan' => $num_days,
        ),
      );
    }

    // Add the rows.
    $rows[] = $row;
    if (!empty($form_row)) {
      $rows[] = $form_row;
    }
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

        // If both start and end timestamps are not available, stop now.
        if (empty($stage['start']) || empty($stage['end'])) {
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
    $entity_label = htmlspecialchars(entity_label('taxonomy_term', $area));
    $entity_uri = entity_uri('taxonomy_term', $area);

    // Generate a link to the area.
    $area_link = l($entity_label, $entity_uri['path']);

    // Iterate through the plantings.
    foreach ($area_plantings as $planting) {
      $row = array();

      // Add the area link to the first column.
      $row[] = array(
        'data' => $area_link,
        'class' => array('left'),
      );

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
      $planting_label = htmlspecialchars(entity_label('farm_asset', $planting['asset']));
      $planting_uri = entity_uri('farm_asset', $planting['asset']);
      $planting_link = l($planting_label, $planting_uri['path']);
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
        'class' => array('stage', 'planted'),
        'colspan' => $total_days - $harvest_days,
      );

      // Define a row for the harvest window.
      $harvest_timestamp = strtotime('+' . $planting['days_to_harvest'] . ' days', $planting['log']->timestamp);
      $harvest_row = array(
        'data' => '<small>Harvest ' . date('Y-m-d', $harvest_timestamp) . ' for ' . format_plural($harvest_window, '1 day', '@count days') . '</small>',
        'class' => array('stage', 'harvest'),
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
