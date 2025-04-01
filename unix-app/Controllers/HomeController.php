<?php

namespace UnixApp\Controllers;

class HomeController extends BaseController
{
    public function index(): array
    {
        return [
            'message' => 'Hello World!'
        ];
    }

    public function __destruct()
    {
        #echo "Deconstruct \n";
    }
}