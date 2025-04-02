<?php

namespace Drupal\cults3d_embed\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Psr\Log\LoggerInterface;

/**
 * Plugin implementation of the 'cults3d_embed_cults3d_embed_default' formatter.
 *
 * @FieldFormatter(
 *   id = "cults3d_embed_cults3d_embed_default",
 *   label = @Translation("Cults3D Embed"),
 *   field_types = {
 *     "cults3d_embed"
 *   }
 * )
 */
class Cults3DEmbedDefaultFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a Cults3DEmbedDefaultFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct($plugin_id, $plugin_definition, $field_definition, array $settings, $label, $view_mode, array $third_party_settings, ClientInterface $http_client, CacheBackendInterface $cache_backend, LoggerInterface $logger) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->httpClient = $http_client;
    $this->cacheBackend = $cache_backend;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('http_client'),
      $container->get('cache.default'),
      $container->get('logger.factory')->get('cults3d_embed')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'width' => 640,
      'height' => 190,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    
    $elements['width'] = [
      '#type' => 'number',
      '#title' => $this->t('Width'),
      '#default_value' => $this->getSetting('width'),
      '#min' => 100,
      '#max' => 1200,
    ];
    
    $elements['height'] = [
      '#type' => 'number',
      '#title' => $this->t('Height'),
      '#default_value' => $this->getSetting('height'),
      '#min' => 100,
      '#max' => 800,
    ];
    
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Width: @width, Height: @height', [
      '@width' => $this->getSetting('width'),
      '@height' => $this->getSetting('height'),
    ]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    
    // Get API key from configuration.
    $config = \Drupal::config('cults3d_embed.settings');
    $api_key = $config->get('api_key');
    
    if (empty($api_key)) {
      $this->logger->error('Cults3D API key is not configured.');
      return [
        '#markup' => $this->t('Cults3D API key is not configured. Please configure it in the module settings.'),
      ];
    }

    foreach ($items as $delta => $item) {
      if (!empty($item->model_id)) {
        $model_id = $item->model_id;
        
        // Check if data is in cache.
        $cid = 'cults3d_embed:' . $model_id;
        $cache = $this->cacheBackend->get($cid);
        
        if ($cache && $cache->data) {
          $model_data = $cache->data;
        }
        else {
          // Fetch data from Cults3D GraphQL API.
          $model_data = $this->fetchModelData($model_id, $api_key);
          
          // Cache the data for 6 hours if we got valid data.
          if (!empty($model_data)) {
            $this->cacheBackend->set(
              $cid,
              $model_data,
              time() + (6 * 60 * 60),
              ['cults3d_embed:' . $model_id]
            );
          }
        }
        
        // If we have model data, build the render array.
        if (!empty($model_data)) {
          $width = $this->getSetting('width');
          $height = $this->getSetting('height');
          
          $elements[$delta] = [
            '#theme' => 'cults3d_embed',
            '#name' => $model_data['name'] ?? 'Unknown Model',
            '#summary' => $model_data['description'] ?? '',
            '#author' => $model_data['creator']['nick'] ?? 'Unknown Author',
            '#author_avatar' => $model_data['creator']['avatarUrl'] ?? '',
            '#image_url' => $model_data['images'][0]['url'] ?? '',
            '#likes_count' => $model_data['likesCount'] ?? 0,
            '#download_count' => $model_data['downloadCount'] ?? 0,
            '#view_count' => $model_data['viewCount'] ?? 0,
            '#model_url' => $model_data['url'] ?? ('https://cults3d.com/en/3d-model/art/' . $model_id),
            '#attributes' => [
              'style' => "width: {$width}px; height: {$height}px;",
            ],
            '#attached' => [
              'library' => [
                'cults3d_embed/cults3d-embed',
              ],
            ],
          ];
        }
        else {
          // Display error message if fetch failed.
          $elements[$delta] = [
            '#markup' => $this->t('Unable to load Cults3D model data.'),
          ];
        }
      }
    }

    return $elements;
  }

  /**
   * Fetch model data from Cults3D GraphQL API.
   *
   * @param string $model_id
   *   The Cults3D model ID.
   * @param string $api_key
   *   The Cults3D API key.
   *
   * @return array|null
   *   The model data or NULL if not found.
   */
  protected function fetchModelData($model_id, $api_key) {
    try {
      // GraphQL query for Cults3D.
      $query = <<<GRAPHQL
query {
  creations(ids: ["$model_id"], limit: 1) {
    name
    url
    description
    downloadCount
    viewCount
    likesCount
    images {
      url
    }
    creator {
      nick
      avatarUrl
    }
  }
}
GRAPHQL;

      $response = $this->httpClient->post('https://cults3d.com/graphql', [
        'headers' => [
          'Content-Type' => 'application/json',
          'Accept' => 'application/json',
          'Authorization' => 'Basic ' . base64_encode($api_key . ':'),
        ],
        'json' => [
          'query' => $query,
        ],
      ]);

      $data = json_decode($response->getBody(), TRUE);
      
      if (isset($data['data']['creations']) && !empty($data['data']['creations'])) {
        // Return the first item since we're only querying for one specific ID.
        return $data['data']['creations'][0];
      }
    }
    catch (RequestException $e) {
      $this->logger->error('Failed to fetch Cults3D model data: @error', [
        '@error' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

}