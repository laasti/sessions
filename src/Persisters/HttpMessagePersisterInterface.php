<?php

namespace Laasti\Sessions\Persisters;

use Laasti\Sessions\Session;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface HttpMessagePersisterInterface extends PersisterInterface
{
    /**
     * 
     * @param RequestInterface $request
     * @return Session
     */
    public function retrieve(RequestInterface $request = null);
    
    /**
     * 
     * @param \Laasti\Sessions\Persisters\Session $session
     * @param ResponseInterface $response
     * @return ResponseInterface $response
     */
    public function persist(Session $session, ResponseInterface $response = null);
}
