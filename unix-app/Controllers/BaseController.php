<?php

namespace UnixApp\Controllers;

abstract class BaseController
{
    protected array $data;
    public function setData(array $data = []): void
    {
        $this->data = $data;
    }
}