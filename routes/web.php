<?php

declare(strict_types=1);

use App\Events\OrderMatched;
use App\Models\Order;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    broadcast(new OrderMatched(Order::find(3)));
});

Route::get('/', function () {
    return view('welcome');
});
