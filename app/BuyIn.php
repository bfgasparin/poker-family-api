<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BuyIn extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['match_id', 'amount'];

    /**
     * Return the player
     *
     * @return void
     */
    public function player()
    {
        return $this->belongsTo('App\Player');
    }

    /**
     * Return the match
     *
     * @return void
     */
    public function match()
    {
        return $this->belongsTo('App\Match');
    }

    /**
     * Scope the query to include only buy ins of the given match
     *
     * @param \Illuminate\Database\Eloquent $query
     * @param \App\Match|string $match
     *
     * @return \Illuminate\Database\Eloquent
     */
    public function scopeOfMatch($query, $match)
    {
        return $query->when($match instanceof Match, function ($query) use ($match) {
            return $query->where('match_id', $match->id);
        }, function ($query) use ($match) {
            return $query->whereHas('match', function ($query) use ($match) {
                return $query->where('name', $match);
            });
        });
    }

}
