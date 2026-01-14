<?php

namespace TNM\Footprints\Channels;

use Exception;
use InvalidArgumentException;
use TNM\Footprints\Helpers\IdKeyGenerator;
use function class_exists;
use function in_array;

class ElasticsearchChannel extends BaseChannel
{
    /**
     * @throws Exception
     */
    public function log(array $footprint, array $config): void
    {
        if (!class_exists(\Elastic\Elasticsearch\ClientBuilder::class)) {
            throw new Exception("Elasticsearch client not installed.");
        }

        $builder = \Elastic\Elasticsearch\ClientBuilder::create()
            ->setHosts($config['hosts']);

        if (!empty($config['api_key'])) {
            $builder->setApiKey($config['api_key']);
        } elseif (!empty($config['username']) && !empty($config['password'])) {
            $builder->setBasicAuthentication($config['username'], $config['password']);
        }

        $client = $builder->build();

        $operationType = $config['operation_type'] ?? 'index';
        if (!in_array($operationType, ['index', 'create'])) {
            throw new Exception("Invalid Elasticsearch operation type: {$operationType}. Must be 'index' or 'create'");
        }

        $documentIdField = $config['document_id_field'] ?? 'request_id';

        try {
            $documentId = IdKeyGenerator::generateDocumentId($footprint, $documentIdField);
        } catch (InvalidArgumentException $e) {
            throw new Exception("Elasticsearch document ID generation failed: " . $e->getMessage());
        }

        $body = $footprint;
        $body['@timestamp'] = isset($footprint['requested_at'])
            ? date('c', strtotime($footprint['requested_at']))
            : gmdate('c');

        $params = [
            'index' => $config['index'],
            'id' => $documentId,
            'body' => $body
        ];

        if ($operationType === 'create') {
            $client->create($params);
        } else {
            $client->index($params);
        }
    }
}