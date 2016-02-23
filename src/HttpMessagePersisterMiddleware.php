<?php

namespace Laasti\Sessions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HttpMessagePersisterMiddleware
{
    protected $persister;
    protected $attribute;
    
    public function __construct(Persisters\HttpMessagePersisterInterface $persister, $attribute = 'session')
    {
        $this->persister = $persister;
        $this->attribute = $attribute;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        //Save session to request
        $session = $this->persister->retrieve($request);
        $request->withAttribute($this->attribute, $session);
        
        $response = $next($request, $response);

        //Add session cookie
        return $this->persister->persist($session, $response);
    }

}
