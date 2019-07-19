<?php

namespace App\Listeners;

use App\Events\RoundEvent;
use App\Jobs\EndCurrentRound;

class ManageRounds
{
    /**
     * Handle the event.
     *
     * @param  MatchRunning  $event
     * @return void
     */
    public function handle($event)
    {
        EndCurrentRound::dispatch($this->getMatch($event))
            ->delay($this->getMatch($event)->next_round_starts_at);
    }

    protected function getMatch($event)
    {
        if ($event instanceof RoundEvent) {
            return $event->round->match;
        }

        return $event->match;
    }
}
