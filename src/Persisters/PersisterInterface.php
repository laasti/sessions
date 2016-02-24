<?php

namespace Laasti\Sessions\Persisters;

use Laasti\Sessions\Session;

interface PersisterInterface
{
    /**
     * @return Session
     */
    public function retrieve();
    
    /**
     * @param \Laasti\Sessions\Persisters\Session $session
     */
    public function persist(Session $session);
}
