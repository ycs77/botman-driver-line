<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Channel Access Token
    |--------------------------------------------------------------------------
    |
    | This value is the Line Bot channel access token.
    |
    */

    'channel_access_token' => env('LINE_BOT_CHANNEL_ACCESS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Channel Secret
    |--------------------------------------------------------------------------
    |
    | This value is the Line Bot channel secret.
    |
    */

    'channel_secret' => env('LINE_BOT_CHANNEL_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Endpoint Base
    |--------------------------------------------------------------------------
    |
    | This value is the Line Bot API endpoint base URL.
    |
    */

    'endpoint_base' => env('LINE_BOT_ENDPOINT_BASE', 'https://api.line.me'),

    /*
    |--------------------------------------------------------------------------
    | Endpoint Base
    |--------------------------------------------------------------------------
    |
    | This value is the Line Bot API endpoint base URL.
    |
    */

    'data_endpoint_base' => env('LINE_BOT_DATA_ENDPOINT_BASE', 'https://api-data.line.me'),

    /*
    |--------------------------------------------------------------------------
    | Line Verification
    |--------------------------------------------------------------------------
    |
    | Your Line verification token, used to validate the webhooks.
    |
    */
    // 'verification' => env('FACEBOOK_VERIFICATION'),

    /*
    |--------------------------------------------------------------------------
    | Line Start Button Payload
    |--------------------------------------------------------------------------
    |
    | The payload which is sent when the Get Started Button is clicked.
    |
    */
    'start_button_payload' => 'GET_STARTED',

    /*
    |--------------------------------------------------------------------------
    | Line Greeting Text
    |--------------------------------------------------------------------------
    |
    | Your Line Greeting Text which will be shown on your message start screen.
    |
    */
    'greeting_text' => [
        'greeting' => [
            [
                'locale' => 'default',
                'text' => 'Hello!',
            ],
            [
                'locale' => 'en_US',
                'text' => 'Timeless apparel for the masses.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Line Persistent Menu
    |--------------------------------------------------------------------------
    |
    | Example items for your persistent Line menu.
    | See https://developers.line.com/docs/messenger-platform/reference/messenger-profile-api/persistent-menu/#example
    |
    */
    'persistent_menu' => [
        [
            'locale' => 'default',
            'composer_input_disabled' => 'true',
            'call_to_actions' => [
                [
                    'title' => 'My Account',
                    'type' => 'nested',
                    'call_to_actions' => [
                        [
                            'title' => 'Pay Bill',
                            'type' => 'postback',
                            'payload' => 'PAYBILL_PAYLOAD',
                        ],
                    ],
                ],
                [
                    'type' => 'web_url',
                    'title' => 'Latest News',
                    'url' => 'http://botman.io',
                    'webview_height_ratio' => 'full',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Line Domain Whitelist
    |--------------------------------------------------------------------------
    |
    | In order to use domains you need to whitelist them
    |
    */
    'whitelisted_domains' => [
        'https://petersfancyapparel.com',
    ],
];
