<?php

namespace Framework\Entity;


class Where {
    public $type = 'and';
    public $negate = false;
    public $clauses = array();

    function __construct($type) {
        $criteria = array();
        if(is_array($type)) {
            $criteria = $type;
            $type = 'and';
        }
        $type = strtolower($type);
        if ($type !== 'or' && $type !== 'and') throw new \Exception("Invalid argument");
        $this->type = $type;

        foreach ($criteria as $key => $value) {
            $this->add("$key", $value);
        }
    }

    function add() {
        $args = func_get_args();
        $sql = array_shift($args);

        if ($sql instanceof Where) {
            $this->clauses[] = $sql;
        } else {
            $this->clauses[] = array('sql' => $sql, 'args' => $args);
        }
    }

    function negateLast() {
        $i = count($this->clauses) - 1;
        if (!isset($this->clauses[$i])) return;

        if ($this->clauses[$i] instanceof Where) {
            $this->clauses[$i]->negate();
        } else {
            $this->clauses[$i]['sql'] = 'NOT (' . $this->clauses[$i]['sql'] . ')';
        }
    }

    function negate() {
        $this->negate = ! $this->negate;
    }

    function addClause($type) {
        $r = new Where($type);
        $this->add($r);
        return $r;
    }

    function count() {
        return count($this->clauses);
    }

    function textAndArgs() {
        $sql = array();
        $args = array();

        if (count($this->clauses) == 0) return array('(1)', $args);

        foreach ($this->clauses as $clause) {
            if ($clause instanceof Where) {
                list($clause_sql, $clause_args) = $clause->textAndArgs();
            } else {
                $clause_sql = $clause['sql'];
                $clause_args = $clause['args'];
            }

            $sql[] = "($clause_sql)";
            $args = array_merge($args, $clause_args);
        }

        if ($this->type == 'and') $sql = implode(' AND ', $sql);
        else $sql = implode(' OR ', $sql);

        if ($this->negate) $sql = '(NOT ' . $sql . ')';
        return array($sql, $args);
    }

    function text() { return $this; }
}