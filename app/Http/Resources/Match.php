<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\Resource;

class Match extends Resource
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $custom = [
            'is_paused' => $this->isPaused(),
            'blind_structure' => $this->blind_structure,
            'next_round_starts_at' => $this->next_round_starts_at,
            'current_round' => new Round($this->current_round),
        ];

        return array_merge(parent::toArray($request), $custom);
    }
}
