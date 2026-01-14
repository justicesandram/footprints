<?php

namespace TNM\Footprints\Channels;

interface ChannelInterface
{
    /**
     * Log the footprint to the specific channel.
     *
     * @param array $footprint
     * @param array $config
     * @return void
     */
    public function log(array $footprint, array $config): void;
}