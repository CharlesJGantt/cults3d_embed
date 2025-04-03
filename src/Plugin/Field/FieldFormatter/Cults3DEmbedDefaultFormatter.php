protected function fetchModelData($model_id, $api_key) {
  try {
    // GraphQL query for Cults3D using slug format from example
    $query = <<<GRAPHQL
{
  creation(slug: "$model_id") {
    name(locale: EN)
    shortUrl
    illustrationImageUrl
    viewsCount
    likesCount
    downloadsCount
    creator {
      nick
      shortUrl
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
        'url' => $creation['shortUrl'],
        'description' => '', // No description in this query
        'downloadCount' => $creation['downloadsCount'] ?? 0,
        'viewCount' => $creation['viewsCount'] ?? 0,
        'likesCount' => $creation['likesCount'] ?? 0,
        'images' => [
          [
            'url' => $creation['illustrationImageUrl'] ?? '',
          ],
        ],
        'creator' => [
          'nick' => $creation['creator']['nick'] ?? 'Unknown Author',
          'avatarUrl' => '', // No avatar URL in this query
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