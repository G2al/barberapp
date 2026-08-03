<?php

namespace App\Services\Ai;

class SalonContactAction
{
    public function make(): ?array
    {
        $number = preg_replace('/\D+/', '', (string) config('barbershop.whatsapp_number'));

        if (blank($number)) {
            return null;
        }

        $message = trim((string) config('barbershop.whatsapp_message'));
        $url = 'https://wa.me/'.$number;

        if ($message !== '') {
            $url .= '?text='.rawurlencode($message);
        }

        return [
            'type' => 'contact_whatsapp',
            'label' => 'Contatta su WhatsApp',
            'url' => $url,
        ];
    }
}
