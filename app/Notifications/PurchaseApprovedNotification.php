<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

#[\AllowDynamicProperties]
class PurchaseApprovedNotification extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(array $params)
    {
        $this->item = $params['item'];
        $this->target = $params['target'];
        $this->sale_price = $params['sale_price'];
        $this->settings = Setting::getSettings();
    }

    public function via(): array
    {
        $notifyBy = [];

        if (Setting::getSettings()->webhook_endpoint != '') {
            $notifyBy[] = 'slack';
        }

        $notifyBy[] = 'mail';

        return $notifyBy;
    }

    public function toMail(): MailMessage
    {
        return (new MailMessage)
            ->subject(trans('mail.purchase_approved_subject'))
            ->greeting(trans('mail.purchase_approved_greeting'))
            ->line(trans('mail.purchase_approved_body', [
                'asset' => $this->item->display_name,
                'price' => $this->sale_price,
            ]))
            ->withSymfonyMessage(function (Email $message) {
                $message->getHeaders()->addTextHeader('X-System-Sender', 'Snipe-IT');
            });
    }

    public function toSlack(): SlackMessage
    {
        $botname = ($this->settings->webhook_botname) ?: 'Snipe-Bot';
        $channel = ($this->settings->webhook_channel) ?: '';

        return (new SlackMessage)
            ->content(trans('mail.purchase_approved_subject'))
            ->from($botname)
            ->to($channel)
            ->attachment(function ($attachment) {
                $attachment->title(
                    htmlspecialchars_decode($this->item->display_name),
                    $this->item->present()->viewUrl()
                )->fields([
                    trans('general.buyer') => $this->target->display_name,
                    trans('general.sale_price') => $this->sale_price,
                ]);
            });
    }
}
