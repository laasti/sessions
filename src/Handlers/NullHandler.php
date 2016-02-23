<?php

namespace Laasti\Sessions\Handlers;

use SessionHandlerInterface;

class NullHandler implements SessionHandlerInterface
{
    public function open($savePath, $sessionName)
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        return '';
    }

    public function write($id, $data)
    {
        return true;
    }

    public function destroy($id)
    {
        return true;
    }

    public function gc($maxLifetime)
    {
        return true;
    }

}
