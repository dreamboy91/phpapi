<?php

namespace Framework\Factory;


use Framework\Entity\EntityInterface;
use Framework\Entity\Repository;

class EntityManager
{
    protected static $managers = array();

    /** @var  \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $db->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
        $db->setAttribute( \PDO::ATTR_EMULATE_PREPARES, true );
        $db->setAttribute( \PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC );
        $this->db = $db;
        self::$managers['_default_'] = new Repository($db, null, null);
    }

    /**
     * @param $entity
     * @return null | Repository
     * @throws \Exception
     */
    public function getRepository($entity = null)
    {
        if(empty($entity)) {
            return self::$managers['_default_'];
        }

        if(isset(self::$managers[$entity])) {
            return self::$managers[$entity];
        }

        $className = null;
        $table = $entity;
        $repository = "Framework\\Entity\\Repository";

        if(class_exists($entity)) {
            $className = $entity;
            $entity = new $entity();
            if (!$entity instanceof EntityInterface) {
                throw new \Exception('Not an entity');
            }

            $repository = $entity->getRepository();
            $table = $entity->getTableName();
        }

        self::$managers[$table] = new $repository($this->getDb(), $table, $className);

        if($className != null) {
            self::$managers[$className] = self::$managers[$table];
        }

        return self::$managers[$table];
    }

    public function getDb() {
        return $this->db;
    }
}