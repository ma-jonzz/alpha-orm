<?php

namespace Alpha;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class Manager
{

    private $_colnames = null;
    private $_colmeta  = null;

    public function __construct(Connection $db, $class_name, $namespace = 'Model\\')
    {
        $this->db = $db;
        $this->class_name = $namespace.$class_name;
        $this->namespace = $namespace;
    }

    public function get($id = null, array $default_value = null)
    {
        $c = $this->class_name;
        return new $c($this, $id, $default_value);
    }

    public function related($class_name, $namespace = null)
    {
        if(is_null($namespace)){
            $namespace = $this->namespace;
        }

        $aclassname = $this->class_name;
        if(defined("$aclassname::DB_NAME")){
            $adbname = $aclassname::DB_NAME;
        }else{
            $adbname = null;
        }
        
        $bclassname = $namespace.$class_name;
        if(defined("$bclassname::DB_NAME")){
            $bdbname = $bclassname::DB_NAME;
        }else{
            $bdbname = null;
        }

        if($adbname != $bdbname){
            throw new Exception("Can't reuse connection with Entities from different databases");
        }

        return new Manager($this->db, $class_name, $namespace);
    }

    public function query()
    {
        $c = $this->class_name;
        $qb = new QueryBuilder($this->db);
        $qb->select("o.*")
            ->from($c::TABLE_NAME,"o");
        return $qb;
    }

    public function getColumns()
    {
        if(is_null($this->_colnames)){
            $columns = $this->getColumnsMeta();
            $this->_colnames = array();
            foreach ($columns as $column) {
                $this->_colnames[] = $column->getName();
            }
        }
        return $this->_colnames;
    }

    public function getColumnsMeta()
    {
        if(is_null($this->_colmeta)){
            $sm = $this->db->getSchemaManager();
            $c = $this->class_name;
            //Doctrine doesn't know "enum" : make it string
            $this->db->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
            $this->_colmeta = $sm->listTableColumns($c::TABLE_NAME);
        }
        return $this->_colmeta;
    }

    public function __call($method, $parameters)
    {
        array_unshift($parameters, $this);
        if(!method_exists($this->class_name, $method)){
            throw new Exception("Call to undefined method ".$this->class_name."::".$method);
        }
        return call_user_func_array(array($this->class_name, $method), $parameters);
    }
}