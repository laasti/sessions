<?php

namespace Laasti\Sessions;

class Session
{

    protected $started = false;
    protected $changed = false;
    protected $data = [];
    protected $handler;
    protected $sessionId;
    protected $expirationTime;
    protected $gcProbability;
    protected $flashdataKey;

    public function __construct(\SessionHandlerInterface $handler, $sessionId, $expirationTime = null, $gcProbability = null, $flashdataKey = 'laasti:flashdata')
    {
        $this->handler = $handler;
        $this->sessionId = $sessionId;
        $this->expirationTime = $expirationTime ?: ini_get('session.gc_maxlifetime');
        $this->gcProbability = $gcProbability ?: ini_get('session.gc_probability');
        $this->flashdataKey = $flashdataKey;
    }

    public function add(array $data)
    {
        $this->lazyLoad();
        $this->data = array_merge($this->data, $data);
        $this->changed = true;
        return $this;
    }

    public function all()
    {
        $this->lazyLoad();
        return $this->data;
    }

    public function set($key, $value)
    {
        $this->lazyLoad();
        $this->data[$key] = $value;
        $this->changed = true;
        return $this;
    }

    public function get($key, $default)
    {
        $this->lazyLoad();
        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }

    public function remove($key)
    {
        $this->lazyLoad();
        unset($this->data[$key]);
        $this->changed = true;
        return $this;
    }

    public function clear()
    {
        $this->lazyLoad();
        $this->changed = true;
        $this->data = array();
        return $this;
    }

    public function hasChanged()
    {
        return $this->changed;
    }

    public function flash($key, $value)
    {
        $this->lazyLoad();
        $this->data[$this->flashdataKey.'.new'][$key] = $value;
        $this->changed = true;
        return $this;
    }

    public function reflash($keys = array())
    {
        $this->lazyLoad();
        if (empty($keys)) {
            $keys = array_keys($this->data[$this->flashdataKey.'.old']);
        }
        foreach ($keys as $key) {
            if (isset($this->data[$this->flashdataKey.'.old'][$key])) {
                $this->data[$this->flashdataKey.'.new'][$key] = $this->data[$this->flashdataKey.'.old'][$key];
            }
        }
        $this->changed = true;
        return $this;
    }

    public function isEmpty()
    {
        $this->lazyLoad();
        return count($this->data) === 0;
    }

    public function hasStarted()
    {
        return $this->started;
    }

    public function start()
    {
        srand(time());
        if ((rand() % 100) < $this->gcProbability) {
            $this->handler->gc(time()+$this->expirationTime);
        }
        $this->handler->open();
        $this->data = $this->deserializeString($this->handler->read($this->sessionId));
        $this->swapFlashdata();
        $this->started = true;
        $this->changed = false;
        
        return $this;
    }

    public function save($end = true)
    {
        $this->handler->write($this->sessionId, $this->getSerializedString());
        if ($end) {
            $this->end();
        }
        return $this;
    }

    public function end()
    {
        $this->handler->close();
        $this->started = false;
        return $this;
    }

    public function destroy()
    {
        $this->handler->destroy($this->sessionId);
        $this->clear();
        $this->started = false;
        return $this;
    }

    public function withSessionId($sessionId, $keepData = true, $destroyOldSession = true)
    {
        $oldId = $this->sessionId;
        $new = clone $this;
        $new->sessionId = $sessionId;
        if (!$keepData) {
            $new->clear();
        }
        if ($destroyOldSession) {
            $this->handler->destroy($oldId);
        }
        return $new;
    }

    protected function swapFlashdata()
    {
        $this->data[$this->flashdataKey.'.old'] = $this->data[$this->flashdataKey.'.new'];
        $this->data[$this->flashdataKey.'.new'] = array();
        $this->changed = true;
        return $this;
    }

    protected function lazyLoad()
    {
        if (!$this->started) {
            $this->start();
        }
    }

    protected function deserializeString($string)
    {
        if (empty($string)) {
            return [];
        }
        
        $data = @unserialize(strip_slashes($string));

        if (is_array($data)) {
            foreach ($data as $key => $val) {
                if (is_string($val)) {
                    $data[$key] = str_replace('{{slash}}', '\\', $val);
                }
            }

            return $data;
        }

        return (is_string($data)) ? str_replace('{{slash}}', '\\', $data) : $data;
    }

    protected function getSerializedString()
    {
        $data = array_merge($this->data, []);
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                if (is_string($val)) {
                    $data[$key] = str_replace('\\', '{{slash}}', $val);
                }
            }
        } else if (is_string($data)) {
            $data = str_replace('\\', '{{slash}}', $data);
        }

        return serialize($data);
    }

}
