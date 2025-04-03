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
    // GraphQL query for Cults3D using slug format
    $query = <<<GRAPHQL
{
  creation(slug: "$model_id") {
    name(locale: EN)
    url: shortUrl
    description
    downloadCount: downloadsCount
    viewCount: viewsCount
    likesCount
    illustrationImageUrl
    creator {
      nick
      avatarUrl: imageUrl
    }
  }
}
GRAPHQL;

    $this->logger->notice('Cults3D API query: @query for model: @model', [
      '@query' => $query,
      '@model' => $model_id,
    ]);

    $response = $this->httpClient->post('https://cults3d.com/graphql', [
      'auth' => [$api_key, ''],  // Using auth parameter for Basic Auth
      'headers' => [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
      ],
      'json' => [
        'query' => $query,
      ],
    ]);

    $data = json_decode($response->getBody(), TRUE);
    
    $this->logger->notice('Cults3D API response: @data', [
      '@data' => print_r($data, TRUE),
    ]);
    
    if (isset($data['data']['creation'])) {
      $creation = $data['data']['creation'];
      
      // Restructure data to match what our formatter expects
      return [
        'name' => $creation['name'],
        'url' => $creation['url'],
        'description' => $creation['description'] ?? '',
        'downloadCount' => $creation['downloadCount'] ?? 0,
        'viewCount' => $creation['viewCount'] ?? 0,
        'likesCount' => $creation['likesCount'] ?? 0,
        'images' => [
          [
            'url' => $creation['illustrationImageUrl'] ?? '',
          ],
        ],
        'creator' => [
          'nick' => $creation['creator']['nick'] ?? 'Unknown Author',
          'avatarUrl' => $creation['creator']['avatarUrl'] ?? '',
        ],
      ];
    }
  }
  catch (RequestException $e) {
    $this->logger->error('Failed to fetch Cults3D model data: @error', [
      '@error' => $e->getMessage(),
    ]);
  }

  return NULL;
}
@FieldFormatter(
  id = "cults3d_embed_default",
  label = @Translation("Cults3D Embed"),
  field_types = {
    "cults3d_embed"
  }
)