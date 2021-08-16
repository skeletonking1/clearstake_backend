<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RequestWithdraw extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $name = '';
    protected $token_amount = 0;

    public function __construct($name, $token_amount)
    {
        $this->name = $name;
        $this->token_amount = $token_amount;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('User ' . $this->name . ' has requested a withdrawal')
                    ->view('emails.request_withdraw', [
                        'name' => $this->name,
                        'token_amount' => $this->token_amount
                    ]);
    }
}
