<?php

namespace App\Repositories;

use App\Mail\ResetEmailMessage;
use App\Mail\ResetPasswordMessage;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Mail;

class UserRepository
{
    /**
     * @param \App\Models\User $user
     * @param array $request
     * @return \App\Models\User
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function updateUser(User $user, array $request): User
    {
        $user->fill($request);
        $user->update();
        // Mail::to($user->email)->send(new ResetPasswordMessage($user));
        Mail::to($user->email)->send(new ResetEmailMessage($user));
        return $user;
    }

    public function verifyUserEmail(User &$user, string $newEmail)
    {
        if (!is_null($newEmail)) {
            if (strtolower($user->email) !== strtolower($newEmail)) {
                if (is_null(User::where('email', $newEmail)->first())) {
                    $user->new_email         = $newEmail;
                    $user->email_verified_at = null;
                    $user->update();

                    return true;
                }
            } else {
                return false;
            }
        }
    }

    public function changeEmail(User $user)
    {
        if ($request['change_email']) {
            if ($this->verifyUserEmail($user, $request['new_email'])) {
                $user->sendEmailVerificationNotification();
                $user->email     = $request['new_email'];
                $user->new_email = null;
            }
        }
    }

    public function changePassword(User $user, string $password)
    {
        $user->password = $password;
        $user->update();

        // Mail::to($user->email)->send(new ResetPasswordMessage($user));
    }
}
