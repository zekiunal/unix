<?php

namespace Nexus\Socket;

interface ServiceInterface
{
    public function listen(array $routers);
}