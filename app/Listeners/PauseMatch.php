<?php

namespace App\Listeners;

use App\Events\RoundEvent;

class PauseMatch
{
    /**
     * Handle the event.
     *
     * @param  MatchRunning  $event
     * @return void
     */
    public function handle($event)
    {
        $this->getMatch($event)->pause();
    }

    protected function getMatch($event)
    {
        if ($event instanceof RoundEvent) {
            return $event->round->match;
        }

        return $event->match;
    }
}
