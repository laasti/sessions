<?php

namespace Laasti\Sessions\Persisters;

use Dflydev\FigCookies\Cookies;
use Dflydev\FigCookies\SetCookie;
use Dflydev\FigCookies\SetCookies;
use InvalidArgumentException;
use Laasti\Sessions\Session;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SessionHandler;


class HttpMessageCookiePersister implements HttpMessagePersisterInterface
{
    const DEFAULT_COOKIE_NAME = 'laasti:session';
    const DEFAULT_FLASHDATA_KEY = 'laasti:flashdata';
    const DEFAULT_METADATA_KEY = 'laasti:metadata';
    protected $config;
    
    public function __construct(array $config = array())
    {
        $config += array(
            'match_ip' => false,
            'match_useragent' => false,
            'expire_time' => 60*60*20,
            'expire_anyway_time' => 60*60*60*24*7,
            'regenerate_time' => 300,
            'flashdata' => self::DEFAULT_FLASHDATA_KEY,
            'metadata' => self::DEFAULT_METADATA_KEY,
            'gc_probability' => ini_get('session.gc_probability'),
            'cookie_name' => self::DEFAULT_COOKIE_NAME,
            'cookie_lifetime' => ini_get('session.cookie_lifetime'),
            'cookie_domain' => ini_get('session.cookie_domain'),
            'cookie_path' => ini_get('session.cookie_path'),
            'cookie_secure' => ini_get('session.cookie_secure'),
            'cookie_httponly' => ini_get('session.cookie_httponly'),
            'hash_callback' => [$this, 'generateSessionId']
        );
        if (!isset($config['handler'])) {
            $config['handler'] = new SessionHandler(ini_get('session.save_path'));
        }
        $this->config = $config;
    }
    
    public function retrieve(RequestInterface $request = null)
    {
        if (is_null($request)) {
            throw new InvalidArgumentException('You must pass an instance of RequestInterface.');
        }
        $cookies = Cookies::fromRequest($request);
        $sessionId = $cookies->get($this->config['cookie_name']);
        $isNew = false;
        
        if (is_null($sessionId)) {
            $sessionId = call_user_func_array($this->config['hash_callback'], [$request]);
            $isNew = true;
        } else if ($sessionId instanceof \Dflydev\FigCookies\Cookie) {
            $sessionId = $sessionId->getValue();
        }
        $session = new Session($this->config['handler'], $sessionId, $this->config['expire_time'], $this->config['gc_probability'], $this->config['flashdata']);

        if ($this->validateSession($session, $request, $isNew)) {
            $now = time();
            $meta = $session->get($this->config['metadata'], []);
            if (($meta['last_regenerated_time']+$this->config['regenerate_time'] < $now)) {
                $session = $session->withSessionId($this->newSessionId($request), true, true);
                $meta['last_regenerated_time'] = $now;
            }        
        } else {
            //The session was tempered with or has expired, change sessionId and create anew
            $session = $session->withSessionId($this->newSessionId($request), false, true);
            $meta = [];
        }
        $session->set($this->config['metadata'], $this->getUpdatedMetadata($meta, $request));
        
        return $session;
        
    }
    
    public function persist(Session $session, ResponseInterface $response = null, $overwriteExistingCookie = false)
    {
        if (is_null($response)) {
            throw new InvalidArgumentException('You must pass an instance of ResponseInterface.');
        }
        $setCookies = SetCookies::fromResponse($response);
        //Cookie already set in response by a preceding middleware
        if (!$overwriteExistingCookie && $setCookies->has($this->config['cookie_name'])) {
            return $response;
        }
        
        $setCookie = SetCookie::create($this->config['cookie_name'])
                ->withPath($this->config['cookie_path'])
                ->withDomain($this->config['cookie_domain'])
                ->withSecure($this->config['cookie_secure'])
                ->withHttpOnly($this->config['cookie_httponly']);
        
        if ($session->wasDestroyed()) {
            $setCookie = $setCookie->withValue('')
                ->withExpires(1);
        } else {
            $setCookie = $setCookie->withValue($session->getSessionId())
                ->withExpires($this->config['cookie_lifetime'] == 0 ? 0 : time()+$this->config['cookie_lifetime']);
        }
        
        return $setCookies->with($setCookie)->renderIntoSetCookieHeader($response);        
    }
    
    
    protected function newSessionId(RequestInterface $request)
    {
        return call_user_func_array($this->config['hash_callback'], [$request]);
    }

    protected function validateSession(Session $session, RequestInterface $request, $isNew)
    {
        $meta = $session->get($this->config['metadata'], []);
        $time = time();
        //TODO better ip address
        if (!$isNew && (!isset($meta['last_activity_time']) || !isset($meta['ip_address']) || !isset($meta['user_agent']) || !isset($meta['creation_time']) || !isset($meta['last_regenerated_time']))) {
            return false;
        }
        if (!isset($meta['last_activity_time'])) {
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
        if (($this->config['match_ip'] || $this->config['match_useragent']) && !$request instanceof ServerRequestInterface) {
            throw new \RuntimeException('When enabling math_ip or match_useragent options, you need to use a ServerRequestInterface.');
        }
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
    
    protected function getUpdatedMetadata($meta, RequestInterface $request)
    {
        $now = time();
        $params = ['REMOTE_ADDR' => '', 'HTTP_USER_AGENT' => ''];
        if ($request instanceof ServerRequestInterface) {
            $params = array_merge($params, $request->getServerParams());
        }
        $meta += [
            'creation_time' => $now,
            'ip_address' => $params['REMOTE_ADDR'],
            'user_agent' => $params['HTTP_USER_AGENT'],
            'last_regenerated_time' => $now
        ];
        
        $meta['last_activity_time'] = $now;

        return $meta;
    }

    public function generateSessionId(RequestInterface $request)
    {
        $sessid = '';
        while (strlen($sessid) < 32) {
            $sessid .= mt_rand(0, mt_getrandmax());
        }
        $keyPayload = uniqid($sessid, TRUE).time();
        if ($request instanceof ServerRequestInterface) {
            $server = $request->getServerParams();
            $keyPayload .= isset($server['REMOTE_ADDR']) ? $server['REMOTE_ADDR'] : '';
        }
        $sessid = sha1($keyPayload);
        return $sessid;
    }
}
