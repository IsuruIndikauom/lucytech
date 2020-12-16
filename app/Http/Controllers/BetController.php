<?php

namespace App\Http\Controllers;

use App\Http\Services\BetService;
use Illuminate\Http\Request;

class BetController extends Controller
{
    private $bet_service;

    public function __construct()
    {
        $this->bet_service = new BetService;
    }

    public function bet(Request $request)
    {
        return $this->bet_service->bet($request->all());
    }
}
