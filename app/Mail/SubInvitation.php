<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubInvitation extends Mailable
{
    use Queueable, SerializesModels;

    protected $link;
    protected $first_name;
    protected $title;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($link, $first_name, $title)
    {
        $this->link = $link;
        $this->first_name = $first_name;
        $this->title = $title;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $title = $this->title;
        return $this->view('emails.sub_invitation')->subject($title)->with([
            'link' => $this->link,
            'first_name' => $this->first_name
        ]);
    }
}
