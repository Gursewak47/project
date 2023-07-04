<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Traits\HandlesInertiaAndApiRequests;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class UserController extends Controller
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function index()
    {
        // return Inertia::render('Tenant/MyProfile');
    }

    public function update(UpdateUserRequest $request)
    {
        $this->userRepository->updateUser(auth()->user(), $request->validated());

        return Redirect::route('dashboard.index');
    }
}
