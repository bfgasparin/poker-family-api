<?php

namespace App;

use RuntimeException;
use App\Events\RoundEnded;
use App\Events\RoundStarted;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Round extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['number', 'small_blind', 'big_blind', 'duration'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['started_at', 'ended_at'];

    /**
     * All of the relationships to be touched.
     *
     * @var array
     */
    protected $touches = ['match'];

    /**
     * The event map for the model.
     * We explictly use event classes to force Laravel to serialize the eloquent models
     * with the SerializesModels trait on the these event classes.
     * If we do not use an event classes, Laravel will use it's internal event that serialize
     * the entire eloquent model instance, leading to some undesirable issues, like not updating
     * the eloquent model states ('changes', 'original' fields ...).
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'created' => RoundStarted::class,
    ];

    /**
     * Create a new User model instance.
     *
     * @param array $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        $this->started_at = now();
        $this->time_in_pause = 0;

        parent::__construct($attributes);
    }

    public function match()
    {
        return $this->belongsTo('App\Match');
    }

    public function incrementTimeInPause($time)
    {
        $this->time_in_pause += $time;
        $this->save();

        return $this;
    }

    public function end()
    {
        $result = DB::transaction(function () {
            // we also need to lock this match so the user could not be able start it at the same time
            $match = $this->newQuery()
                ->where('id', $this->id)
                ->lockForUpdate()
                ->first();

            throw_unless(is_null($this->ended_at), new RuntimeException('The round is already ended'));

            $match->ended_at = now();

            $match->save();
        });

        event(new RoundEnded($this));

        // Reload the current model instance with fresh attributes from the database after the cancelation
        $this->refresh();

        return $result;
    }

    /**
     * Return if the round has ended
     *
     * @return bool
     */
    public function hasEnded()
    {
        return ! is_null($this->ended_at);
    }
}
