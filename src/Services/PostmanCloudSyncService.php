<?php

namespace YasinTgh\LaravelPostman\Services;

use Illuminate\Support\Facades\Http;
use YasinTgh\LaravelPostman\Contracts\CloudSyncServiceInterface;
use YasinTgh\LaravelPostman\Exceptions\CloudSyncException;

class PostmanCloudSyncService implements CloudSyncServiceInterface
{
    private const API_BASE = 'https://api.getpostman.com';

    private string $apiKey;
    private string $collectionId;
    private ?string $workspaceId;

    public function __construct(
        private readonly array $config,
        private readonly CollectionMerger $merger,
    ) {
        $this->apiKey       = (string) data_get($config, 'cloud.api_key', '');
        $this->collectionId = (string) data_get($config, 'cloud.collection_id', '');
        $this->workspaceId  = data_get($config, 'cloud.workspace_id');
    }

    public function fetchRemoteCollection(): array
    {
        $this->assertCredentials();

        $response = Http::withHeaders($this->headers())
            ->timeout(30)
            ->get(self::API_BASE . "/collections/{$this->collectionId}");

        if ($response->failed()) {
            throw CloudSyncException::forFetchFailure($response->status(), $response->body());
        }

        $collection = $response->json('collection');

        if (!is_array($collection)) {
            throw CloudSyncException::forFetchFailure($response->status(), $response->body());
        }

        return $collection;
    }

    public function merge(array $remoteCollection, array $generatedCollection): array
    {
        return $this->merger->merge($remoteCollection, $generatedCollection);
    }

    public function pushCollection(array $collection): void
    {
        $this->assertCredentials();

        $url = self::API_BASE . "/collections/{$this->collectionId}";

        if (is_string($this->workspaceId) && $this->workspaceId !== '') {
            $url .= '?workspace=' . urlencode($this->workspaceId);
        }

        $response = Http::withHeaders($this->headers())
            ->timeout(45)
            ->put($url, ['collection' => (new CollectionSanitizer())->sanitize($collection)]);

        if ($response->failed()) {
            throw CloudSyncException::forPushFailure($response->status(), $response->body());
        }
    }

    private function assertCredentials(): void
    {
        if (empty($this->apiKey)) {
            throw CloudSyncException::forMissingCredentials('POSTMAN_API_KEY');
        }

        if (empty($this->collectionId)) {
            throw CloudSyncException::forMissingCredentials('POSTMAN_COLLECTION_ID');
        }
    }

    private function headers(): array
    {
        return [
            'X-Api-Key'    => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }
}
