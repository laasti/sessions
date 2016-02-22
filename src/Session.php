<?php


namespace Laasti\Sessions;

class Session
{
    public function __construct(\SessionHandlerInterface $handler, \Laasti\Sessions\ValidatorInterface $validator)
    {
        //How to retrieve cookie?
        ;
    }
    public function set($key, $value);
    public function get($key, $default);
    public function remove($key);
    public function clear();
    public function refresh($keepData = true);
    public function start();
    public function save($end = true);
    public function end();
    public function destroy();

}
