<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetEmailMessage extends Mailable implements ShouldBeUnique
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(private User $user)
    {
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $user = $this->user;
        return $this->view('mails.reset-email-message', compact('user'));
    }
}
