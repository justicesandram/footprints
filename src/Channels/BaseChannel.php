<?php

namespace TNM\Footprints\Channels;

abstract class BaseChannel implements ChannelInterface
{
    /**
     * Safely encode value to JSON, handling UTF-8 errors.
     */
    protected function safeJsonEncode(mixed $value): string
    {
        $encoded = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if ($encoded === false) {
            return json_encode([
                'error' => 'JSON encoding failed',
                'message' => json_last_error_msg()
            ]);
        }

        return $encoded;
    }
}