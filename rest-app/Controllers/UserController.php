<?php

namespace RestApp\Controllers;

class UserController extends BaseController
{
    public function users(): array
    {
        return [
            ['id' => 1, 'name' => 'Zeki']
        ];
    }
}