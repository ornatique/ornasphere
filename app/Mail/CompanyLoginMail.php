<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CompanyLoginMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Company $company;
    public User $user;
    public string $token;
    public string $setPasswordUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Company $company, User $user, string $token)
    {
        $this->company = $company;
        $this->user    = $user;
        $this->token   = $token;

        // Works for local + live automatically
        $this->setPasswordUrl = url('/set-password/' . $token);
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Set Your ERP Password')
            ->view('superadmin.auth.company.company-login')
            ->with([
                'company'        => $this->company,
                'user'           => $this->user,
                'setPasswordUrl' => $this->setPasswordUrl,
            ]);
    }
}
