<?php

namespace Laasti\Sessions\Test;

use Dflydev\FigCookies\Cookie;
use Dflydev\FigCookies\Cookies;
use Laasti\Sessions\Handlers\FakeHandler;
use Laasti\Sessions\Persisters\HttpMessageCookiePersister;
use Zend\Diactoros\ServerRequest;

class HttpMessageCookiePersisterTest extends \PHPUnit_Framework_TestCase
{

    protected $id;

    protected function getRequestWithCookie()
    {
        $this->id = uniqid('laasti.sessions', true);
        $request = new ServerRequest;
        $cookies = Cookies::fromRequest($request);
        $cookies = $cookies->with(new Cookie(HttpMessageCookiePersister::DEFAULT_COOKIE_NAME, $this->id));
        return $cookies->renderIntoCookieHeader($request);
    }

    protected function getFakeSessionData()
    {
        return [
            HttpMessageCookiePersister::DEFAULT_METADATA_KEY => [

                'creation_time' => time(),
                'last_activity_time' => time(),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Dummy',
                'last_regenerated_time' => time()
            ]
        ];
    }

    public function testCookieRetrieval()
    {
        $request = $this->getRequestWithCookie();
        $persister = new HttpMessageCookiePersister(['handler' => new FakeHandler($this->getFakeSessionData())]);
        $session = $persister->retrieve($request);
        $this->assertEquals($session->getSessionId(), $this->id);
    }

    public function testCookieRetrievalNoDataRegenerateId()
    {
        $request = $this->getRequestWithCookie();
        $persister = new HttpMessageCookiePersister(['handler' => new FakeHandler([])]);
        $session = $persister->retrieve($request);
        $this->assertNotEquals($session->getSessionId(), $this->id);
    }

}
