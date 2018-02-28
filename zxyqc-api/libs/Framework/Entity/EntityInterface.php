<?php

namespace Framework\Entity;


interface EntityInterface
{
    function getTableName();
    function getRepository();
}