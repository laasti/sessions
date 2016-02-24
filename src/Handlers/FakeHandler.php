<?php

namespace Laasti\Sessions\Handlers;

use SessionHandlerInterface;

class FakeHandler implements SessionHandlerInterface
{
    protected $data;
    
    public function __construct($data = [])
    {
        $this->data = $data;
    }
    public function open($savePath = null, $sessionName = null)
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        return serialize($this->data);
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
