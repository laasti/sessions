<?php

namespace Laasti\Sessions\Handlers;

use SessionHandlerInterface;

class LogHandler implements SessionHandlerInterface
{
    protected $logger;
    
    public function __construct(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    public function open($savePath = null, $sessionName = null)
    {
        $this->logger->debug('Opened session: '.$sessionName);
        return true;
    }

    public function close()
    {
        $this->logger->debug('Closed session');
        return true;
    }

    public function read($id)
    {
        $this->logger->debug('Read session data for id: '.$id);
        return '';
    }

    public function write($id, $data)
    {
        $this->logger->debug('Write session data for id: '.$id);
        return true;
    }

    public function destroy($id)
    {
        $this->logger->debug('Destroyed session for id: '.$id);
        return true;
    }

    public function gc($maxLifetime)
    {
        $this->logger->debug('Garbage collected sessions older than: '.$maxLifetime);
        return true;
    }

}
