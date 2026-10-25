<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/* W96 MIXXTUNE_STAFF_ALERTS: short warning e-mail to staff (backup problem, agreements to renew) */
class StaffAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $title,
        public string $name,
        public array $lines,
        public ?string $url = null
    ) {
    }

    public function build()
    {
        $app = e(config('app.name'));
        $items = implode('', array_map(fn ($l) => '<li style="margin:4px 0">'.e($l).'</li>', $this->lines));
        $link = $this->url ? '<p><a href="'.e($this->url).'">'.e($this->url).'</a></p>' : '';

        return $this
            ->subject($app.': '.$this->title)
            ->html(
                '<p>Hello '.e($this->name).',</p>'
                .'<p><strong>'.e($this->title).'</strong></p>'
                .'<ul>'.$items.'</ul>'
                .$link
                .'<p style="color:#666;font-size:12px">This is an automatic message from '.$app.'.</p>'
            );
    }
}
