<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen2.5:7b-instruct'),
        'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'bge-m3'),
    ],

    'recommender' => [
        // ALS eğitim script'ini çalıştıracak Python yorumlayıcısı.
        // Varsayılan, recommender/ klasörüne göre göreli: lokal kurulumda
        // (Herd) venv proje içinde duruyor. Docker'da ise venv /opt/venv'e
        // kuruluyor (proje klasörü bind-mount edildiği için içine kurulamaz),
        // orada bu değişken mutlak yolla eziliyor.
        'python' => env('RECOMMENDER_PYTHON', 'venv/bin/python'),
    ],

];
