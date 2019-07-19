<?php

namespace App;

use RuntimeException;
use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];

    /**
     * Return the matches
     */
    public function matches()
    {
        return $this->belongsToMany('App\Match', 'buy_ins');
    }

    public function buy_ins()
    {
        return $this->hasMany('App\BuyIn');
    }

    /**
     * Register to a match
     *
     * @return App\BuyIn
     */
    public function registerTo(Match $match, $amount)
    {
        throw_unless($match->isCreated(), new RuntimeException('Can not register to the match'));
        throw_if($this->buy_ins()->where('match_id', $match->id)->exists(), new RuntimeException('Already registered in the match'));

        return $this->buy_ins()->create([
            'match_id' => $match->id,
            'amount' => $amount
        ]);
    }
}
