<?php

namespace App;

use RuntimeException;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use App\Events\{MatchRunning, MatchPaused};
use App\Eloquent\Concerns\{HasStatusAttributeEvents, HasEnumConstants};

class Match extends Model
{
    use HasStatusAttributeEvents,
        HasEnumConstants;

   /*
    |--------------------------------------------------------------------------
    | Status Enum
    |--------------------------------------------------------------------------
    */
    const STATUS_CREATED = 'created';
    const STATUS_RUNNING = 'running';
    const STATUS_PAUSED = 'paused';
    const STATUS_ENDED = 'ended';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['started_at', 'paused_at', 'ended_at'];

    protected $roundsStructure = [
        // small blind, bif blind, duration
        [0.25, 0.50, 1*60],
        [0.50, 1, 1*60],
        [0.75, 1.5, 1*60],
        [1, 2, 1*60],
        [1.25, 2.5, 1*60],
        [2, 4, 1*60],
        [3, 6, 1*60],
        [5, 10, 1*60],
        [7, 14, 30*60],
        [10, 20, 30*60],
        [15, 30, 30*60],
        [30, 60, 30*60],
    ];

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
        'running' => MatchRunning::class,
        'paused' => MatchPaused::class,
    ];

    /**
     * Create a new User model instance.
     *
     * @param array $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        $this->status = static::STATUS_CREATED;
        $this->rounds_qtd = count($this->roundsStructure);

        parent::__construct($attributes);
    }

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::resuming(function ($match) {
            if ($match->isPaused()) {
                $timeInPause = $match->paused_at->diffInSeconds(now());
                $match->current_round->incrementTimeInPause($timeInPause);
            }

            if ($match->current_round->hasEnded()) {
                $match->startNextRound();
            }
        });
    }

    /**
     * Return the rounds
     */
    public function rounds()
    {
        return $this->hasMany('App\Round');
    }

    /**
     * Return the players
     */
    public function players()
    {
        return $this->belongsToMany('App\Player', 'buy_ins');
    }

    /**
     * Return the current round active
     *
     * @return integer
     */
    public function getCurrentRoundAttribute()
    {
        if ($this->hasEnded()) {
            return null;
        }

        return $this->rounds->last();
    }

    /**
     * Return the elapsed time for the match (in seconds)
     *
     * @return integer
     */
    public function getElapsedTimeAttribute()
    {
        if (! $this->wasStarted()) {
            return 0;
        }

        $total = $this->started_at->diffInSeconds(now());

        return $total - $this->time_in_pause;
    }

    /**
     * Return the total time for the match (in seconds)
     *
     * @return integer
     */
    public function getTotalTimeAttribute()
    {
        return $this->started_at->diffInSeconds(now());
    }

    /**
     * Return the elapsed time for the match (in seconds)
     *
     * @return integer
     */
    public function getBlindStructureAttribute()
    {
        return collect($this->roundsStructure)->map(function ($structure, $index) {
            return [
                'round' => $index,
                'small_blind' => $structure[0],
                'big_blind' => $structure[1],
            ];
        });
    }


    /**
     * Return the total time in pause
     *
     * @return Carbon\Carbon
     */
    public function getTimeInPauseAttribute()
    {
        $currentTimeInPause = $this->isPaused()
            ? $this->paused_at->diffInSeconds(now())
            : 0;

        $pastTimeInPause = $this->rounds->sum('time_in_pause');

        return $pastTimeInPause + $currentTimeInPause;
    }

    /**
     * Return when the next round should start
     *
     * @return Carbon\Carbon
     */
    public function getNextRoundStartsAtAttribute()
    {
        if (! $this->wasStarted()) {
            return 0;
        }

        return $this->started_at->addSeconds($this->rounds->sum('duration') + $this->time_in_pause);
    }

    /**
     * Return if the match is created
     */
    public function isCreated()
    {
        return $this->status === static::STATUS_CREATED;
    }

    public function start()
    {
        $result = DB::transaction(function () {
            // we also need to lock this match so the user could not be able start it at the same time
            $match = $this->newQuery()
                ->where('id', $this->id)
                ->lockForUpdate()
                ->first();

            throw_unless($match->status === static::STATUS_CREATED, new RuntimeException('The match was already started'));
            throw_unless($match->players->count() >= 2, new RuntimeException('The match can not only start with 2 or more players'));


            $match->started_at = now();

            if (! $match->save()) {
                return false;
            }

            $this->fireModelEvent('started');

            $match->startNextRound();

            $match->performRun();
        });

        // Reload the current model instance with fresh attributes from the database
        $this->refresh();

        return $result;
    }

    /**
     * Return if the match was started
     */
    public function wasStarted()
    {
        return ! is_null($this->started_at);
    }

    /**
     * Resume the match
     */
    public function resume()
    {
        $result = DB::transaction(function () {
            // we also need to lock this match so the user could not be able start it at the same time
            $match = $this->newQuery()
                ->where('id', $this->id)
                ->lockForUpdate()
                ->first();

            throw_unless(in_array($match->status, [static::STATUS_PAUSED]), new RuntimeException('The match can not be resumed'));

            if ($match->fireModelEvent('resuming') === false) {
                throw new RuntimeException('resuming failed');
            }

            return $match->performRun();
        });

        // Reload the current model instance with fresh attributes from the database
        $this->refresh();

        return $result;
    }

    /**
     * undocumented function
     *
     * @return bool
     */
    protected function performRun()
    {
        $this->status = static::STATUS_RUNNING;

        return $this->save();
    }

    /**
     * Pause the match
     *
     * @return void
     */
    public function pause()
    {
        $result = DB::transaction(function () {
            // we also need to lock this match so the user could not be able start it at the same time
            $match = $this->newQuery()
                ->where('id', $this->id)
                ->lockForUpdate()
                ->first();

            throw_unless($this->status === static::STATUS_RUNNING, new RuntimeException('The match can not be paused'));

            $match->status = static::STATUS_PAUSED;
            $match->paused_at = now();

            return $match->save();
        });

        // Reload the current model instance with fresh attributes from the database
        $this->refresh();

        return $result;
    }

    public function isPaused()
    {
        return $this->status === static::STATUS_PAUSED;
    }

    /**
     * Start the next round
     */
    protected function startNextRound()
    {
        $result = DB::transaction(function () {
            // we also need to lock this match so the user could not be able start it at the same time
            $match = $this->newQuery()
                          ->where('id', $this->id)
                          ->lockForUpdate()
                          ->first();

            throw_unless($match->canStartNextRound(), new RuntimeException('It\'s not time to go to next round yet'));

            return $match->rounds()->create([
                'number' => with($number = ($match->current_round->number ?? 0) + 1),
                'small_blind' => $match->roundsStructure[$number][0],
                'big_blind' => $match->roundsStructure[$number][1],
                'duration' => $match->roundsStructure[$number][2]
            ]);
        });

        // Reload the current model instance with fresh attributes from the database
        $this->refresh();

        return $result;
    }

    /**
     * Return if can start a new round
     *
     * @return void
     */
    public function canStartNextRound()
    {
        if ($this->hasEnded()) {
            return false;
        }

        if (is_null($this->current_round)) {
            return true;
        }

        if (! $this->current_round->hasEnded()) {
            return false;
        }

        return true;
    }

    /**
     * Try to end the next round
     */
    public function tryEndCurrentRound()
    {
        // let's first fetch and lock the maatch to avoid other PHP processes to update it.
        return DB::transaction(function () {
            $match = $this->newQuery()
                          ->where('id', $this->id)
                          ->sharedLock()
                          ->first();

            if (! $match->canEndCurrentRound()) {
                return false;
            }

            // Why we call cancel from the current instance instead of $match instance? Calling the cancel method of the current instance we automatically refresh
            // the current instance's attributes. Calling from the $match instance would require us to hit the database again to refresh the
            // the current instance's attributes in match to keep it up to date with the $match instance.
            return $this->endCurrentRound();
        });
    }

    /**
     * End the next round
     */
    protected function endCurrentRound()
    {
        $result = DB::transaction(function () {
            // we also need to lock this match so the user could not be able end it at the same time
            $match = $this->newQuery()
                          ->where('id', $this->id)
                          ->lockForUpdate()
                          ->first();

            throw_unless($match->canEndCurrentRound(), new RuntimeException('It\'s not time to go to next round yet'));

            $match->current_round->end();
        });

        // Reload the current model instance with fresh attributes from the database
        $this->refresh();

        return $result;
    }

    /**
     * Return if can end the current round
     *
     * @return void
     */
    public function canEndCurrentRound()
    {
        if ($this->isPaused()) {
            return false;
        }

        if ($this->hasEnded()) {
            return false;
        }

        if (is_null($this->current_round)) {
            return false;
        }

        if ($this->current_round->hasEnded()) {
            return false;
        }

        return now()->gt($this->next_round_starts_at);
    }

    /**
     * return if the match has ended
     *
     * @return void
     */
    public function hasEnded()
    {
        return !is_null($this->ended_at);
    }

    /**
     * Register an activating model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function started($callback)
    {
        static::registerModelEvent('started', $callback);
    }


    /**
     * Register an activating model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function resuming($callback)
    {
        static::registerModelEvent('resuming', $callback);
    }

    /**
     * Register an activating model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function running($callback)
    {
        static::registerModelEvent('running', $callback);
    }
}
