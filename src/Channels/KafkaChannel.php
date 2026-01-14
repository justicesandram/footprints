<?php

namespace TNM\Footprints\Channels;

use Exception;
use InvalidArgumentException;
use TNM\Footprints\Helpers\IdKeyGenerator;
use TNM\Footprints\Services\Checker\ServiceHealthMonitor;
use TNM\Footprints\Services\Checker\TCPCheck;
use function class_exists;
use function extension_loaded;

class KafkaChannel extends BaseChannel
{
    /**
     * @throws Exception
     */
    public function log(array $footprint, array $config): void
    {
        if (!extension_loaded('rdkafka') || !class_exists(\RdKafka\Producer::class)) {
            throw new Exception("RdKafka extension not installed or enabled.");
        }

        $this->ensureConnectivity($config['brokers']);

        $conf = new \RdKafka\Conf();
        $conf->set('bootstrap.servers', $config['brokers']);
        $conf->set('socket.timeout.ms', (string)$config['timeout_ms']);

        if (!empty($config['sasl_username']) && !empty($config['sasl_password'])) {
            $conf->set('sasl.mechanism', $config['sasl_mechanism']);
            $conf->set('security.protocol', $config['security_protocol']);
            $conf->set('sasl.username', $config['sasl_username']);
            $conf->set('sasl.password', $config['sasl_password']);
        }

        $producer = new \RdKafka\Producer($conf);
        $topic = $producer->newTopic($config['topic']);

        $messageKey = null;
        if (isset($config['message_key'])) {
            try {
                $messageKey = IdKeyGenerator::generateMessageKey($footprint, $config['message_key']);
            } catch (InvalidArgumentException $e) {
                throw new Exception("Kafka message key generation failed: " . $e->getMessage());
            }
        }

        $messagePayload = $this->safeJsonEncode($footprint);
        $partition = RD_KAFKA_PARTITION_UA;
        $flags = 0;

        $topic->produce($partition, $flags, $messagePayload, $messageKey);

        $producer->poll(0);
        $result = $producer->flush(1000);

        if ($result !== RD_KAFKA_RESP_ERR_NO_ERROR) {
            throw new Exception("Kafka error code: {$result}");
        }
    }

    /**
     * Check if at least one broker is reachable via TCP.
     *
     * @throws Exception
     */
    private function ensureConnectivity(string $brokers): void
    {
        $monitor = new ServiceHealthMonitor();

        $brokerList = explode(',', $brokers);

        foreach ($brokerList as $broker) {
            $broker = trim($broker);
            if (empty($broker)) {
                continue;
            }

            $parts = explode(':', $broker);
            $host = $parts[0];
            $port = $parts[1] ?? 9092;

            $monitor->addCheck(new TCPCheck("Broker $broker", $host, (int)$port, 2));
        }

        $results = $monitor->start();

        if (!in_array(true, $results, true)) {
            throw new Exception("Kafka connectivity check failed. No reachable brokers found.");
        }
    }
}