<?php

namespace Laasti\Sessions;

use Dflydev\FigCookies\Cookies;
use Dflydev\FigCookies\SetCookie;
use Dflydev\FigCookies\SetCookies;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SessionHandler;

class SessionMiddleware
{
    protected $config;
    
    public function __construct(array $config = array())
    {
        $config += array(
            'match_ip' => false,
            'match_useragent' => false,
            'expire_time' => 60*60*20,
            'expire_anyway_time' => 60*60*60*24*7,
            'regenerate_time' => 300,
            'flashdata' => 'laasti:flashdata',
            'metadata' => 'laasti:metadata',
            'gc_probability' => ini_get('session.gc_probability'),
            'cookie_name' => 'laasti:session',
            'cookie_lifetime' => ini_get('session.cookie_lifetime'),
            'cookie_domain' => ini_get('session.cookie_domain'),
            'cookie_path' => ini_get('session.cookie_path'),
            'cookie_secure' => ini_get('session.cookie_secure'),
            'cookie_httponly' => ini_get('session.cookie_httponly'),
            'attribute' => 'session',
            'hash_callback' => [$this, 'generateSessionId']
        );
        if (!isset($config['handler'])) {
            $config['handler'] = new SessionHandler(ini_get('session.save_path'));
        }
        $this->config = $config;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        //Save session to request
        $session = $this->createSession($request);
        $request->withAttribute($this->config['attribute'], $session);
        
        $response = $next($request, $response);

        //Send session cookie
        $setCookies = SetCookies::fromResponse($response);
        $setCookie = new SetCookie($this->config['cookie_name'], $session->getSessionId());
        $setCookie = $setCookie->withExpires($this->config['cookie_lifetime'])
                ->withPath($this->config['cookie_path'])
                ->withDomain($this->config['cookie_domain'])
                ->withSecure($this->config['cookie_secure'])
                ->withHttpOnly($this->config['cookie_httponly']);
        $response = $setCookies->with($setCookie)->renderIntoSetCookieHeader($response);

        return $response;
    }

    protected function newSessionId(ServerRequestInterface $request)
    {
        return call_user_func_array($this->config['hash_callback'], $request);
    }

    protected function createSession(ServerRequestInterface $request)
    {
        $cookies = Cookies::fromRequest($request);

        $sessionId = $cookies->get($this->config['cookie_name']);
        $isNew = false;
        if (is_null($sessionId)) {
            $sessionId = call_user_func_array($this->config['hash_callback'], $request);
            $isNew = true;
        }
        $session = new Session($this->config['handler'], $sessionId, $this->config['expire_time'], $this->config['gc_probability'], $this->config['flashdata']);

        if (!$this->validateSession($session, $request, $isNew)) {
            //The session was tempered with or has expired, change sessionId and create anew
            $session = $session->withSessionId($this->newSessionId($request), false, true);
        }
        $meta = $session->get($this->config['metadata'], []);
        $now = time();
        $meta += [
            'creation_time' => $now,
            'ip_address' => $request->getServerParams()['REMOTE_ADDR'],
            'user_agent' => $request->getServerParams()['HTTP_USER_AGENT'],
        ];
        
        $meta['last_activity_time'] = $now;

        if ($meta['last_regenerated_time']+$this->config['regenerate_time'] < $now) {
            $session = $session->withSessionId($this->newSessionId($request), true, true);
        }
        $session->set($this->config['metadata'], $meta);
        return $session;
    }

    protected function validateSession(\Session $session, ServerRequestInterface $request, $isNew)
    {
        $meta = $session->get($this->config['metadata'], []);
        $time = time();
        //TODO better ip address
        if (!$isNew && (!isset($meta['last_activity_time']) || !isset($meta['ip_address']) || !isset($meta['user_agent']) || !isset($meta['creation_time']) || !isset($meta['last_regenerated_time']))) {
            return false;
        }
        //check last activity
        if ($meta['last_activity_time']+$this->config['expire_time'] < $time) {
            return false;
        }
        if ($meta['last_activity_time']+$this->config['expire_anyway_time'] < $time) {
            return false;
        }
        //check ip
        //TODO better ip address
        if ($this->config['match_ip'] && $meta['ip_address'] !== $request->getServerParams()['REMOTE_ADDR']) {
            return false;
        }
        //check user agent
        if ($this->config['match_useragent'] && $meta['user_agent'] !== $request->getServerParams()['HTTP_USER_AGENT']) {
            return false;
        }

        return true;
    }

    //Todo move to session
    public function generateSessionId(ServerRequestInterface $request)
    {
        $sessid = '';
        while (strlen($sessid) < 32) {
            $sessid .= mt_rand(0, mt_getrandmax());
        }
        $server = $request->getServerParams();
        $sessid = md5(uniqid($sessid, TRUE).time().$server['REMOTE_ADDR']);
        return $sessid;
    }
}
