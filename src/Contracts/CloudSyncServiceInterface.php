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
     * Summary of the last merge(): lists of "METHOD:path" keys under
     * added, updated and preserved (and removed when pruning is on).
     */
    public function lastMergeReport(): array;

    /**
     * @throws CloudSyncException
     */
    public function pushCollection(array $collection): void;
}
