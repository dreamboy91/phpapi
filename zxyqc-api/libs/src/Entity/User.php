<?php

namespace Entity;

use Framework\Entity\EntityInterface;

class User implements EntityInterface
{
    public $id;
    public $password;
    public $full_name;

    function getTableName()
    {
        return "system_user";
    }

    function getRepository()
    {
        return 'Model\\UserRepository';
    }
}