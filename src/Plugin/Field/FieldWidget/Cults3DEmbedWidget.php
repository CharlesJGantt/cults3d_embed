<?php

namespace Drupal\cults3d_embed\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'cults3d_embed_url' widget.
 *
 * @FieldWidget(
 *   id = "cults3d_embed_url",
 *   label = @Translation("Cults3D URL"),
 *   field_types = {
 *     "cults3d_embed"
 *   }
 * )
 */
class Cults3DEmbedWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Cults3D URL'),
      '#description' => $this->t('Enter a URL like https://cults3d.com/en/3d-model/game/example-model'),
      '#default_value' => isset($items[$delta]->url) ? $items[$delta]->url : NULL,
      '#maxlength' => 2048,
      '#required' => $element['#required'],
      '#placeholder' => 'https://cults3d.com/en/3d-model/game/example-model',
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$value) {
      if (!empty($value['url'])) {
        // Ensure URL is properly formatted
        if (!preg_match('~^https?://~i', $value['url'])) {
          $value['url'] = 'https://' . $value['url'];
        }
      }
    }
    return $values;
  }
}