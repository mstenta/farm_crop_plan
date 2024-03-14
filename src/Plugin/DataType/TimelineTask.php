<?php

namespace Drupal\farm_crop_plan\Plugin\DataType;

use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\Plugin\DataType\ItemList;
use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * Timeline task data type.
 *
 * @DataType(
 *   id = "farm_timeline_task",
 *   label = @Translation("Timeline Task"),
 *   definition_class = "\Drupal\farm_crop_plan\TypedData\TimelineTaskDefinition"
 * )
 */
class TimelineTask extends Map implements ComplexDataInterface {

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if (isset($values) && !is_array($values)) {
      throw new \InvalidArgumentException("Invalid values given. Values must be represented as an associative array.");
    }

    // Inherit resource_id from Row parent ID.
    if (!isset($values['resource_id']) && $this->parent instanceof ItemList && $this->parent->parent instanceof TimelineRow) {
      $values['resource_id'] = $this->parent->parent->get('id')->getValue();
    }

    // Set default values.
    $values += [
      'enable_dragging' => FALSE,
    ];
    parent::setValue($values);
  }

}
