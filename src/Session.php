<?php

namespace Laasti\Sessions;

class Session
{

    protected $started = false;
    protected $changed = false;
    protected $data = [];
    protected $legacyMode = false;
    protected $handler;
    protected $validator;
    protected $persister;

    public function __construct(\SessionHandlerInterface $handler, \Laasti\Sessions\ValidatorInterface $validator, Persisters\PersisterInterface $persister, $legacyMode = false)
    {
        //How to retrieve cookie?
        $this->handler = $handler;
        $this->validator = $validator;
        $this->persister = $persister;
        $this->legacyMode = $legacyMode;
        if ($this->legacyMode === true) {
            $this->data = & $_SESSION;
        }
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
        $this->lazyLoad();
        return $this->changed;
    }

    public function isEmpty()
    {
        $this->lazyLoad();
        return count($this->data) === 0;
    }

    public function regenerate($keepData = true)
    {
        $oldId = $this->persister->getSessionId();
        $this->persister->createNewSessionId();
        if (!$keepData) {
            $this->clear();
            $this->destroy($oldId);
        }
        return $this;
    }

    public function hasStarted()
    {
        return $this->started;
    }

    public function start()
    {
        //TODO call gc: http://php.net/manual/en/sessionhandlerinterface.gc.php
        $this->handler->open($save_path, $name);
        $this->data = $this->deserializeString($this->handler->read($this->persister->getSessionId()));
        $this->started = true;
        $this->changed = false;
        
        return $this;
    }

    public function save($end = true)
    {
        $this->handler->write($this->persister->getSessionId(), $this->getSerializedString());
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
        $this->handler->destroy($this->persister->getSessionId());
        $this->clear();
        $this->started = false;
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
