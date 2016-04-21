<?php

namespace Laasti\Sessions;

use Interop\Container\ContainerInterface;

class SaveSessionToContainerMiddleware
{
    /**
     * @var ContainerInterface
     */
    protected $container;
    protected $attribute;
    
    public function __construct(ContainerInterface $container, $attribute = 'session')
    {
        $this->container = $container;
        $this->attribute = $attribute;
    }
    
    
    public function __invoke(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, callable $next)
    {
        //Save session to request
        $attr = $this->attribute;
        $this->container->add('sessions.'.$this->attribute, function() use ($request, $attr) {
            return $request->getAttribute($attr);
        });
        
        return $next($request, $response);
    }

    
}
