<?php

namespace YasinTgh\LaravelPostman\Contracts;

use YasinTgh\LaravelPostman\Exceptions\CloudSyncException;

interface CloudSyncServiceInterface
{
    /**
     * @throws CloudSyncException
     */
    public function fetchRemoteCollection(): array;

    public function merge(array $remoteCollection, array $generatedCollection): array;

    /**
     * @throws CloudSyncException
     */
    public function pushCollection(array $collection): void;
}
