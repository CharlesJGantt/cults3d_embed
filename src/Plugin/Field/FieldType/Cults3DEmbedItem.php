<?php

namespace Drupal\cults3d_embed\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'cults3d_embed' field type.
 *
 * @FieldType(
 *   id = "cults3d_embed",
 *   label = @Translation("Cults3D Embed"),
 *   description = @Translation("Stores a Cults3D URL and displays model data from the Cults3D API."),
 *   default_widget = "cults3d_embed_url",
 *   default_formatter = "cults3d_embed_default"
 * )
 */
class Cults3DEmbedItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'url' => [
          'type' => 'varchar',
          'length' => 2048,
          'not null' => FALSE,
        ],
        'model_id' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => FALSE,
        ],
        'cache_timestamp' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['url'] = DataDefinition::create('string')
      ->setLabel(t('URL'))
      ->setRequired(FALSE);
    
    $properties['model_id'] = DataDefinition::create('string')
      ->setLabel(t('Model ID'))
      ->setRequired(FALSE);
    
    $properties['cache_timestamp'] = DataDefinition::create('integer')
      ->setLabel(t('Cache Timestamp'))
      ->setRequired(FALSE);
    
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('url')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();
    
    // Extract model ID from URL
    $url = $this->get('url')->getValue();
    if (!empty($url)) {
      // Match IDs in formats like cults3d.com/en/3d-model/game/example-model-12345
      if (preg_match('|cults3d\.com/[a-z]+/3d-model/[a-z-]+/([a-z0-9-]+)|i', $url, $matches)) {
        $this->set('model_id', $matches[1]);
      }
      // Or direct ID format if used
      elseif (preg_match('|/model/([a-z0-9-]+)|i', $url, $matches)) {
        $this->set('model_id', $matches[1]);
      }
    }
  }
}