<?php

namespace Framework\Entity;


class Repository
{
    protected $affected_rows;
    protected $last_id;
    protected $table;
    protected $class;
    /**
     * @var \PDO
     */
    private $DB;
    private $_table;

    public function __construct($DB, $table = null, $class = null)
    {
        $this->table = $table;
        $this->class = $class;
        $this->DB = $DB;
    }

    public function from($table)
    {
        if ($this->class != null) {
            throw new \Exception("You should use default repository for querying without entity");
        }

        $this->_table = $table;

        return $this;
    }

    public function find($id)
    {
        return $this->findOneByField('id', $id);
    }

    public function findOneByField($field, $value, $fields = '*', $orderBy = null)
    {
        return $this->getAssocOrObject(
            $this->findByField($field, $value, $fields, 1, $orderBy)
        );
    }

    /**
     * @param \PDOStatement $stmt
     *
     * @return mixed
     */
    protected function getAssocOrObject(\PDOStatement $stmt)
    {
        return $this->class == null ? $stmt->fetch() : $stmt->fetchObject($this->class);
    }

    public function findByField($field, $value, $fields = '*', $limit = null, $orderBy = null)
    {
        return $this->findBy(array("$field=?" => $value), $fields, $limit, $orderBy);
    }

    public function findBy($where, $fields = '*', $limit = null, $orderBy = null)
    {
        $this->affected_rows = 0;

        if ($this->_table == null) {
            $this->_table = $this->table;
        }

        if ($this->_table == null) {
            throw new \Exception("You must use from to define your table before each query");
        }

        $where = $this->getWhereArray($where);

        $query = "SELECT $fields FROM  {$this->_table} WHERE $where[0]";

        $query .= " ".$this->orderBy($orderBy);

        if ($limit != null) {
            $query .= " LIMIT $limit";
        }

        $stmt = $this->getDB()->prepare($query);
        $stmt->execute($where[1]);
        $this->affected_rows = $stmt->rowCount();

        $this->_table = null;

        return $stmt;
    }

    /**
     * @param $where
     *
     * @return array
     */
    protected function getWhereArray($where)
    {
        return $this->getWhereObject($where)->textAndArgs();
    }

    /**
     * @param $where
     *
     * @return Where
     */
    protected function getWhereObject($where)
    {
        if ($where instanceof Where) {
            return $where;
        }

        return new Where(is_array($where) ? $where : 'and');
    }

    private function orderBy($criteria, $order = "")
    {
        if ($criteria == null) {
            return "";
        }

        if (!is_array($criteria)) {
            return "$criteria $order";
        }

        if (isset($criteria[1]) && $this->isValidOrderString($criteria)) {
            return $this->orderBy($criteria[0], $criteria[1]);
        }

        $multipleOrderBy = array();
        foreach ($criteria as $key => $value) {
            if (is_array($value)) {
                $multipleOrderBy[] = $this->orderBy($value);
            } else {
                $order_criteria = is_int($key) ? $value : $key;
                $lower_key = strtolower($value);
                $order = ($lower_key == 'asc' || $lower_key == 'desc' || $lower_key == 'random') ? $value : null;
                $multipleOrderBy[] = $this->orderBy($order_criteria, $order);
            }
        }

        return implode(', ', $multipleOrderBy);
    }

    /**
     * @param $criteria
     *
     * @return bool
     */
    private function isValidOrderString($criteria)
    {
        return in_array(strtolower($criteria[1]), array('asc', 'desc', 'random'));
    }

    /**
     * @return \PDO
     */
    public function getDB()
    {
        return $this->DB;
    }

    public function getResults($query, $parameter = array()) {
        $stmt = $this->getDB()->prepare($query);
        $stmt->execute($parameter);

        return $stmt->fetchAll();
    }

    public function getSingleResults($query, $parameter = array()) {
        $stmt = $this->getDB()->prepare($query);
        $stmt->execute($parameter);
        return $stmt->fetch();
    }

    public function insert($data = array())
    {
        $single = false;

        if (empty($data)) {
            $this->last_id = null;

            return false;
        }

        if (!$this->isMultipleRecord($data)) {
            $single = true;
            $data = array($data);
        }


        if ($this->_table == null) {
            $this->_table = $this->table;
        }

        if ($this->_table == null) {
            throw new \Exception("You must use from to define your table before each query");
        }

        $ids = $this->insertBatch($data);

        $this->_table = null;

        $this->last_id = $ids[count($ids) - 1];

        return $single ? $ids[0] : $ids;
    }

    public function update($data, $where)
    {
        if (empty($data)) {
            $this->last_id = null;

            return false;
        }

        if (!$this->isMultipleRecord($data)) {
            $data = array($data);
        }


        if ($this->_table == null) {
            $this->_table = $this->table;
        }

        if ($this->_table == null) {
            throw new \Exception("You must use from to define your table before each query");
        }

        $this->updateBatch($data, $where);

        $this->_table = null;
    }

    private function isMultipleRecord($data)
    {
        return !is_string(key($data));
    }

    private function insertBatch($data)
    {
        $this->affected_rows = 0;

        $fields = array_keys($data[0]);
        $query = "INSERT INTO {$this->_table} (".implode(", ", $fields).") VALUES(:".implode(", :", $fields).")";

        $stmt = $this->DB->prepare($query);
        $ids = array();

        foreach ($data as $row) {
            $stmt->execute($row);
            $this->affected_rows += $stmt->rowCount();
            $ids[] = $this->DB->lastInsertId();
        }

        return $ids;
    }

    public function __call($methodName, $args)
    {
        $watch = array('findOneBy', 'findBy');

        foreach ($watch as $found) {
            if (stristr($methodName, $found)) {
                array_unshift($args, strtolower(str_replace($found, '', $methodName)));

                return call_user_func_array(array($this, $found.'Field'), $args);
            }
        }
    }

    public function query()
    {
        return call_user_func_array(array($this->DB, 'query'), func_get_args());
    }

    public function exec()
    {
        return call_user_func_array(array($this->DB, 'exec'), func_get_args());
    }

    public function execute()
    {
        return call_user_func_array(array($this->DB, 'execute'), func_get_args());
    }

    /**
     * @return mixed
     */
    public function getAffectedRows()
    {
        return $this->affected_rows;
    }

    /**
     * @return mixed
     */
    public function getLastId()
    {
        return $this->last_id;
    }

    private function updateBatch($data, array $where = array())
    {
        $this->affected_rows = 0;

        $fields = array_keys($data[0]);

        $fields = array_map(function ($value) {
           return "$value = ?";
        },$fields);


        $where = $this->getWhereArray($where);

        $query = "UPDATE {$this->_table} SET ".implode(", ", $fields)." WHERE $where[0]";

        $stmt = $this->DB->prepare($query);

        foreach ($data as $row) {
            $parameters = array_values(array_merge($row, $where[1]));
            $stmt->execute($parameters);
            $this->affected_rows += $stmt->rowCount();
        }

        return $this->affected_rows;
    }
}