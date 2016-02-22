<?php

namespace Laasti\Sessions\Persisters;

interface PersisterInterface
{
    public function getSessionId();
    public function createNewSessionId();
}
