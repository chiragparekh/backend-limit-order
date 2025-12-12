<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function ($user) {
    return true;
});

Broadcast::routes(['middleware' => ['auth:sanctum']]);
