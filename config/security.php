<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Master Key
    |--------------------------------------------------------------------------
    |
    | Chave de 32 bytes (base64) usada exclusivamente para cifrar/decifrar as
    | Data Encryption Keys (envelope encryption). Nunca usada para cifrar dado
    | de negócio diretamente. Gerar com: php artisan security:master-key:generate
    |
    | @see docs/04-Seguranca.md
    */
    'master_key' => env('ENCRYPTION_MASTER_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Política de sessão
    |--------------------------------------------------------------------------
    */
    'session' => [
        'absolute_minutes' => (int) env('SESSION_ABSOLUTE_TIMEOUT_MINUTES', 480),
        'inactivity_minutes' => (int) env('SESSION_INACTIVITY_TIMEOUT_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | MFA
    |--------------------------------------------------------------------------
    */
    'mfa' => [
        'email_otp_ttl_minutes' => (int) env('MFA_EMAIL_OTP_TTL_MINUTES', 10),
        'email_otp_length' => 6,
    ],
];
