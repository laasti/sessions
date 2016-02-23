<?php

namespace Laasti\Sessions\Handlers;

use SessionHandlerInterface;

class FileHandler implements SessionHandlerInterface
{

    protected $path;

    public function __construct($path)
    {
        $this->path = $path;

        if (!is_dir($path) && !@mkdir($path, 0777, true)) {
            throw new \RuntimeException('Session directory does not exist: "' . $path . '".');
        }
        if (!is_writable($path)) {
            throw new \RuntimeException('Session directory is not writable: "' . $path . '".');
        }
    }

    public function __destruct()
    {
        // Fixes issue with Debian and Ubuntu session garbage collection
        if (mt_rand(1, 100) === 100) {
            $this->gc(ini_get('session.gc_maxlifetime'));
        }
    }

    public function open($savePath, $sessionName)
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        $data = '';
        if (file_exists($this->path . '/' . $id) && is_readable($this->path . '/' . $id)) {
            $data = (string) file_get_contents($this->path . '/' . $id);
        }
        return $data;
    }

    public function write($id, $data)
    {
        if (is_writable($this->path)) {
            return file_put_contents($this->path . '/' . $id, $data) === false ? false : true;
        }
        return false;
    }

    public function destroy($id)
    {
        if (file_exists($this->path . '/' . $id) && is_writable($this->path . '/' . $id)) {
            return unlink($this->path . '/' . $id);
        }
        return false;
    }

    public function gc($maxLifetime)
    {
        $files = glob($this->path . '/*');

        if (is_array($files)) {
            foreach ($files as $file) {
                if ((filemtime($file) + $maxLifetime) < time() && is_writable($file)) {
                    unlink($file);
                }
            }
        }

        return true;
    }

}
