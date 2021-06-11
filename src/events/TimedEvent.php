<?php
declare(strict_types = 1);

namespace cusodede\sse\events;

use cusodede\sse\Event;
use cusodede\sse\Utils;

abstract class TimedEvent implements Event
{
    /**
     * The time interval between two event triggers.
     *
     * @var int
     */
    protected $period = 1;

    /**
     * The creation time of the event.
     *
     * @var int
     */
    private $start = 0;

    /**
     * @inheritdoc
     */
    public function check()
    {
        if ($this->start === 0) {
            $this->start = time();
        }
        return Utils::timeMod($this->start, $this->period) === 0;
    }
}