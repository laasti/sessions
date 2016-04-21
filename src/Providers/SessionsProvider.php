<?php

namespace Laasti\Sessions\Providers;

class SessionsProvider extends \League\Container\ServiceProvider\AbstractServiceProvider
{
    protected $provides = [
        'Laasti\Sessions\HttpMessagePersisterMiddleware',
        'Laasti\Sessions\SaveSessionToContainerMiddleware',
        'Laasti\Sessions\Persisters\HttpMessagePersisterInterface',
        'Laasti\Sessions\Handlers\FileHandler',
        'Laasti\Sessions\Handlers\FakeHandler',
        'Laasti\Sessions\Handlers\LogHandler',
        'Laasti\Sessions\Handlers\NullHandler',
    ];
    
    protected $defaultConfig = [
        'handler' => null,
        'handler_args' => [],
        'settings' => []
    ];
    
    public function register()
    {
        $globalConfig = $this->getContainer()->get('config');
        $config = (isset($globalConfig['sessions']) ? $globalConfig['sessions'] : [])+$this->defaultConfig;
        if (is_null($config['handler'])) {
            $config['handler'] = 'Laasti\Sessions\Handlers\FileHandler';
            $config['handler_args'] = [ini_get('session.save_path')];
        }
        $this->getContainer()->add('sessions.handler', $config['handler'])->withArguments($config['handler_args']);
        
        $this->getContainer()->add('Laasti\Sessions\Persisters\HttpMessagePersisterInterface', function($settings, $handler) {
            $settings['handler'] = $handler;
            return new \Laasti\Sessions\Persisters\HttpMessageCookiePersister($settings);
        })->withArguments([$config['settings'], 'sessions.handler']);
        
        $this->getContainer()->add('Laasti\Sessions\HttpMessagePersisterMiddleware')->withArguments([
            'Laasti\Sessions\Persisters\HttpMessagePersisterInterface'
        ]);
        $this->getContainer()->add('Laasti\Sessions\SaveSessionToContainerMiddleware')->withArguments([
            'Interop\Container\ContainerInterface'
        ]);
    }
}
