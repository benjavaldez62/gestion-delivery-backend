<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class RecuperarContrasena extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $payload = json_encode([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], JSON_THROW_ON_ERROR);
        $fragment = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $url = rtrim(config('app.frontend_url'), '/').'/restablecer-contrasena#'.$fragment;
        $minutes = config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('Recuperación de contraseña')
            ->line('Recibimos una solicitud para cambiar la contraseña de tu cuenta.')
            ->action('Restablecer contraseña', $url)
            ->line("Este enlace vence en {$minutes} minutos y se puede usar una sola vez.")
            ->line('Si no solicitaste este cambio, podés ignorar este mensaje.');
    }
}
