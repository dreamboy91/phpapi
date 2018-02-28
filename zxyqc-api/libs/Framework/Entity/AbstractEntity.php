<?php

namespace Framework\Entity;


abstract class AbstractEntity implements EntityInterface
{
    function getRepository()
    {
        return 'Framework\\Entity\\EntityManager';
    }
}