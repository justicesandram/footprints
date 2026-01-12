<?php

namespace TNM\Footprints\Helpers;

class IdKeyGenerator
{
    /**
     * Maximum length for generated IDs/keys
     */
    protected const MAX_LENGTH = 512;

    /**
     * Generate a document ID for Elasticsearch
     *
     * @param array $footprint
     * @param mixed $idConfig Can be a string (field name) or callable
     * @return string
     */
    public static function generateDocumentId(array $footprint, mixed $idConfig): string
    {
        if (is_string($idConfig)) {
            // Use field from footprint
            $id = $footprint[$idConfig] ?? null;
            if ($id === null) {
                throw new \InvalidArgumentException("Field '{$idConfig}' not found in footprint data");
            }
            return (string)$id;
        }

        if (is_callable($idConfig)) {
            $id = call_user_func($idConfig, $footprint);
            if (!is_string($id) && !is_numeric($id)) {
                throw new \InvalidArgumentException("ID generation function must return a string or numeric value");
            }
            $id = (string)$id;
            self::validateLength($id, 'document ID');
            return $id;
        }

        throw new \InvalidArgumentException("Document ID config must be a string (field name) or callable");
    }

    /**
     * Generate a message key for Kafka
     *
     * @param array $footprint
     * @param mixed $keyConfig Can be null, a string (field name), or callable
     * @return string|null
     */
    public static function generateMessageKey(array $footprint, mixed $keyConfig): ?string
    {
        if ($keyConfig === null) {
            return null;
        }

        if (is_string($keyConfig)) {
            $key = $footprint[$keyConfig] ?? null;
            if ($key === null) {
                return null;
            }
            return (string)$key;
        }

        if (is_callable($keyConfig)) {
            $key = call_user_func($keyConfig, $footprint);
            if ($key === null) {
                return null;
            }
            if (!is_string($key) && !is_numeric($key)) {
                throw new \InvalidArgumentException("Key generation function must return a string, numeric value, or null");
            }
            $key = (string)$key;
            self::validateLength($key, 'message key');
            return $key;
        }

        throw new \InvalidArgumentException("Message key config must be null, a string (field name), or callable");
    }

    /**
     * Validate that the generated ID/key is not too long
     *
     * @param string $value
     * @param string $type
     * @return void
     * @throws \InvalidArgumentException
     */
    protected static function validateLength(string $value, string $type): void
    {
        if (strlen($value) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(
                "Generated {$type} exceeds maximum length of " . self::MAX_LENGTH . " characters"
            );
        }
    }
}
