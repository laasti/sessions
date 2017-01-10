<?php

namespace Laasti\Sessions;

class Session
{

    protected $new = true;
    protected $started = false;
    protected $destroyed = false;
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

    /**
     * Add data to the session in a batch
     * @param array $data
     * @return \Laasti\Sessions\Session
     */
    public function add(array $data)
    {
        $this->lazyLoad();
        $this->data = array_merge($this->data, $data);
        $this->changed = true;
        return $this;
    }

    /**
     * Returns all session data
     * @param bool $includingFlashdata
     * @return array
     */
    public function all($includingFlashdata = false)
    {
        $this->lazyLoad();
        if ($includingFlashdata) {
            return $this->data;
        }
        return array_diff_key($this->data, [$this->flashdataKey.'.old' => [], $this->flashdataKey.'.new' => []]);
    }

    /**
     * Add a new value to the session
     * 
     * @param string $key
     * @param mixed $value 
     * @return \Laasti\Sessions\Session
     * @throws \InvalidArgumentException
     */
    public function set($key, $value)
    {
        if (in_array($key, [$this->flashdataKey.'.old', $this->flashdataKey.'.new'])) {
            throw new \InvalidArgumentException('You can\'t set a flashdata key using set method. See flash.');
        }
        $this->lazyLoad();
        $this->data[$key] = $value;
        $this->changed = true;
        return $this;
    }

    /**
     * Get data from the session for a given key (checks in flashdata first)
     * @param string $key
     * @param mixed $default Default if key not found
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $this->lazyLoad();
        if (array_key_exists($key, $this->data[$this->flashdataKey.'.old'])) {
            return $this->data[$this->flashdataKey.'.old'][$key];
        }
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }
    
    /**
     * Checks if key is already used in the session (checks in flashdata first)
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        $this->lazyLoad();
        if (array_key_exists($key, $this->data[$this->flashdataKey.'.old'])) {
            return true;
        }
        return array_key_exists($key, $this->data);
    }

    /**
     * Deletes an item from the session
     * @param string $key
     * @return \Laasti\Sessions\Session
     * @throws \InvalidArgumentException
     */
    public function remove($key)
    {
        $this->lazyLoad();
        
        if (in_array($key, [$this->flashdataKey.'.old', $this->flashdataKey.'.new'])) {
            throw new \InvalidArgumentException('You can\'t remove flashdata using remove method. See unflash.');
        }
        
        unset($this->data[$key]);
        $this->changed = true;
        return $this;
    }

    /**
     * Delete all session data without destroying the session
     * @param bool $includingFlashdata Also delete session data
     * @return \Laasti\Sessions\Session
     */
    public function clear($includingFlashdata = false)
    {
        $this->lazyLoad();
        $this->changed = true;
        
        if ($includingFlashdata) {
            $this->data = [$this->flashdataKey.'.old' => [], $this->flashdataKey.'.new' => []];
        } else {
            $this->data = array_intersect_key($this->data, [$this->flashdataKey.'.old' => [], $this->flashdataKey.'.new' => []]);
        }
        
        return $this;
    }

    /**
     * Has the session changed since it was read from the handler
     * @return bool
     */
    public function hasChanged()
    {
        return $this->changed;
    }

    /**
     * Set a flash item, will live only once
     * @param string $key
     * @param mixed $value
     * @return \Laasti\Sessions\Session
     */
    public function flash($key, $value)
    {
        $this->lazyLoad();
        $this->data[$this->flashdataKey.'.new'][$key] = $value;
        $this->changed = true;
        return $this;
    }
    
    /**
     * Remove a flash item that was set in the previous request
     * @param string $key
     * @return \Laasti\Sessions\Session
     */
    public function unflash($key)
    {
        $this->lazyLoad();
        if (isset($this->data[$this->flashdataKey.'.old'][$key])) {
            unset($this->data[$this->flashdataKey.'.old'][$key]);
        }
        $this->changed = true;
        return $this;
    }

    /**
     * Flash items for another request
     * @param array $keys Subset of keys to reflash
     * @return \Laasti\Sessions\Session
     */
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

    /**
     * Is the session empty?
     * @return bool
     */
    public function isEmpty()
    {
        $this->lazyLoad();
        return count($this->data) === 0;
    }
    /**
     * Is the session new? (Never saved before)
     * @return bool
     */
    public function isNew()
    {
        $this->lazyLoad();
        return $this->new;
    }
    
    /**
     * Has the session been destroyed?
     * @return bool
     */
    public function wasDestroyed()
    {
        return $this->destroyed;
    }

    /**
     * Has the session started?
     * @return bool
     */
    public function hasStarted()
    {
        return $this->started;
    }

    /**
     * Start the session and read data from handler
     * @return \Laasti\Sessions\Session
     */
    public function start()
    {
        if ($this->destroyed) {
            throw new Exceptions\DestroyedSessionException('The session was destroyed. It can\'t be used anymore.');
        }
        srand(time());
        if ((rand() % 100) < $this->gcProbability) {
            $this->handler->gc(time()+$this->expirationTime);
        }
        $this->handler->open(null, null);
        $this->data = $this->deserializeString($this->handler->read($this->sessionId));
        $this->new = empty($this->data);
        $this->started = true;
        $this->changed = false;
        $this->swapFlashdata();
        
        return $this;
    }

    /**
     * Save session data using handler and optionnaly end the session
     * @param bool $end Defaults to true
     * @return \Laasti\Sessions\Session
     */
    public function save($end = true)
    {
        if ($this->destroyed) {
            throw new Exceptions\DestroyedSessionException('The session was destroyed. It can\'t be used anymore.');
        }
        if ($this->hasStarted()) {
            if ($this->hasChanged()) {
                $this->handler->write($this->sessionId, $this->getSerializedString());
            }
            $this->new = false;
            if ($end) {
                $this->end();
            }
        }
        return $this;
    }

    /**
     * Is the session closed?
     * @return \Laasti\Sessions\Session
     */
    public function end()
    {
        if ($this->destroyed) {
            throw new Exceptions\DestroyedSessionException('The session was destroyed. It can\'t be used anymore.');
        }
        if ($this->hasStarted()) {
            $this->handler->close();
            $this->started = false;
        }
        return $this;
    }
    
    /**
     * Revert any modification made to the session
     * @return \Laasti\Sessions\Session
     */
    public function revert()
    {
        $this->started = false;
        return $this;
    }

    /**
     * Delete the session and all its data
     * @return \Laasti\Sessions\Session
     */
    public function destroy()
    {
        if ($this->destroyed) {
            throw new Exceptions\DestroyedSessionException('The session was already destroyed. It can\'t be used anymore.');
        }
        $this->handler->destroy($this->sessionId);
        $this->handler->close();
        $this->clear();
        $this->new = false;
        $this->started = false;
        $this->changed = false;
        $this->destroyed = true;
        return $this;
    }
    
    public function getSessionId()
    {
        return $this->sessionId;
    }

    public function withSessionId($sessionId, $keepData = true, $destroyOldSession = true)
    {
        if ($this->destroyed) {
            throw new Exceptions\DestroyedSessionException('The session was destroyed. It can\'t be cloned anymore.');
        }
        $oldId = $this->sessionId;
        $new = clone $this;
        $new->sessionId = $sessionId;
        $new->new = true;
        $new->changed = true;
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
        if (isset($this->data[$this->flashdataKey.'.new'])) {
            $this->data[$this->flashdataKey.'.old'] = $this->data[$this->flashdataKey.'.new'];
            $this->changed = true;
        } else {
            $this->data[$this->flashdataKey.'.old'] = [];
        }
        $this->data[$this->flashdataKey.'.new'] = [];
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
        
        $data = @unserialize($string);

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
