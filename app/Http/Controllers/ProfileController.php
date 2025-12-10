<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ProfileResource;
use App\Models\User;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): ProfileResource
    {
        /** @var User $user */
        $user = $request->user();

        $user->loadMissing('assets');

        return new ProfileResource($user);
    }
}
