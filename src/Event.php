<?php

namespace cusodede\sse;

/**
 * Interface Event
 * @package cusodede\sse
 */
interface Event
{
    /**
     * Check for continue to send event.
     * @return bool
     */
    public function check(): bool;

    /**
     * Get Updated Data.
     * @return string
     */
    public function update(): string;
}