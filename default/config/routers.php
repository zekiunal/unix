<?php

use App\Controllers\HomeController;
use App\Controllers\UserController;

return [
    '/' => [
        [
            'controller'  => HomeController::class,
            'action'      => 'index',
            'method'      => 'get',
            'uri'         => '/',
            'template'    => 'dashboard',
            'is_public'   => true,
            'title'       => 'Mintrade - AI-Powered Strategies & Automated Bots',
            'description' => 'Welcome to Mintrade, Create your strategy with Mintrade\'s AI assistant...',
        ],

    ],
    '/user'    => [
        [
            'controller'  => UserController::class,
            'action'      => 'index',
            'method'      => 'GET',
            'uri'         => '/info',
            'template'    => 'dashboard',
            'accept'      => ['account_id', 'csrf_token'],
            'validations' => [
            ],
            'is_public'   => false,
            'title'       => 'Mintrade - Submit Account Details',
            'description' => 'Submit your account details to start using Mintrade...',
        ],
    ]
];