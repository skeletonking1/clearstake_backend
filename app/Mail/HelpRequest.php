<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class HelpRequest extends Mailable
{
    use Queueable, SerializesModels;

    protected $text = '';
    protected $userEmail = '';

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($userEmail, $text)
    {
        $this->userEmail = $userEmail;
        $this->text = $text;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('LP portal user needs help')
                    ->view('emails.help_request', [
                        'userEmail' => $this->userEmail,
                        'text' => $this->text
                    ]);
    }
}
