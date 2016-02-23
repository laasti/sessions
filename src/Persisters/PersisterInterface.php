<?php

namespace Laasti\Sessions\Persisters;

interface PersisterInterface
{
    /**
     * @return \Laasti\Sessions\Session
     */
    public function retrieve();
    
    /**
     * @param \Laasti\Sessions\Persisters\Session $session
     */
    public function persist(Session $session);
}
