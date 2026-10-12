<?php

namespace App\Http\Controllers;

use App\Services\OperationalHealth;

class OperationsController extends Controller
{
    public function index(OperationalHealth $health)
    {
        return response()->view('operations.index', ['health' => $health->inspect()])->header('Cache-Control', 'no-store, private');
    }

    public function status()
    {
        return response()->view('operations.public', ['paymentsEnabled' => PaymentController::enabled()]);
    }
}
