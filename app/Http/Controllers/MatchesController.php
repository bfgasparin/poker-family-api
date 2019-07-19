<?php

namespace App\Http\Controllers;

use App\Match;
use Illuminate\Http\Request;
use App\Http\Resources\Match as MatchResource;

class MatchesController extends Controller
{
    /**
     * undocumented function
     *
     * @return void
     */
    public function index()
    {
        return MatchResource::collection(Match::all());
    }

    public function resume(Match $match)
    {
        $match->resume();

        return new MatchResource($match);
    }

    public function pause(Match $match)
    {
        $match->pause();

        return new MatchResource($match);
    }

}
