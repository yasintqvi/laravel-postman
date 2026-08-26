<?php

namespace YasinTgh\LaravelPostman\Exceptions;

use RuntimeException;

class CloudSyncException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }

    public static function forMissingCredentials(string $missing): self
    {
        return new self("Postman Cloud credential missing: {$missing}. Set it in your .env file.");
    }

    public static function forFetchFailure(int $status, string $body): self
    {
        $instance = new self("Failed to fetch collection from Postman Cloud (HTTP {$status}).", $status);
        $instance->resolveErrorDetail($body);
        return $instance;
    }

    public static function forPushFailure(int $status, string $body): self
    {
        $instance = new self("Failed to push collection to Postman Cloud (HTTP {$status}).", $status);
        $instance->resolveErrorDetail($body);
        return $instance;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    private function resolveErrorDetail(string $body): void
    {
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['error'])) {
            return;
        }

        if (isset($decoded['error']['message'])) {
            $this->message .= ' Postman: ' . $decoded['error']['message'];
        }

        $details = $decoded['error']['details'] ?? null;

        if (!is_array($details) || $details === []) {
            return;
        }

        $summaries = [];

        foreach (array_slice($details, 0, 5) as $detail) {
            if (is_string($detail)) {
                $summaries[] = $detail;
                continue;
            }

            if (!is_array($detail)) {
                continue;
            }

            $path = $detail['path'] ?? $detail['instancePath'] ?? $detail['dataPath'] ?? '';
            $msg  = $detail['message'] ?? '';
            $summaries[] = trim($path . ' ' . $msg);
        }

        if ($summaries !== []) {
            $this->message .= ' Details: ' . implode('; ', $summaries);
        }
    }
}
