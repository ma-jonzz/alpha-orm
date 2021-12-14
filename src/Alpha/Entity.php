<?php

namespace Alpha;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query;
use Doctrine\DBAL\Query\QueryBuilder;
use \Alpha\Manager;


/**
 * Alpha Entities
 *
 * @author Original by Matthieu Duclos / Nicolas Bonnotte / Julien Zamor
 * @copyright LPGL 2001 - 2014 / this version : © TroisYaourts 2013
 * @package Alpha
 * @subpackage Entity
 */

abstract class Entity implements \Iterator, \Serializable, \JsonSerializable, \ArrayAccess{

    private $_pile;
    private $_cursor=0;

    const TABLE_NAME = '';
    const PRIMARY = 'id';

    protected $db;
    protected $manager;


    function __construct(Manager $manager, $id = null, array $default_value = null)
    {
        $this->setManager($manager);
        if (!is_null($id)){
          $this->id = $id;
          $this->get();
        }else{
          $this->id = null;
          $this->_pile = array();
          if(!is_null($default_value)){
            $this->loadData($default_value);
          }

        }

    }

    //BASIC DATABASE MANIPULATION

    public function get() {
      if (is_null($this->id))
        return null;

      $sql = "SELECT  o.*
            FROM    " . static::TABLE_NAME . " AS o
            WHERE   o.".static::PRIMARY." = ?";
      $row = $this->db->fetchAssociative($sql, array($this->id));

      if(empty($row))
        $this->id = null;
      else {
        $this->loadPile(array($row));
        $this->current();
      }

      return $this;
    }

    public function append($obj){
      $class = get_class($this);
      if(!($obj instanceof $class))
          throw new Exception("Incompatible type !");
      if (isset($obj->id) && $obj->id > 0 && $obj->id != null) {
          $this->_pile[] = $obj->toArray();
      }
      return $this;
    }

    /*
     * @author Credit to Thomas Pellan
     */
    public function filter($callable){
      $pos = $this->_cursor;
      if(!is_callable($callable))
        throw new Exception('Cannot filter with non-callable');
      $filtered_list = new static($this->manager);
      foreach($this as $item){
        call_user_func($callable, $item) ? $filtered_list->append($item) : null;
      }
      $this->_cursor = $pos;
      return $filtered_list;
    }

    public function delete()
    {
      $this->db->delete(static::TABLE_NAME, array(static::PRIMARY => $this->id));
      $this->id = null;
    }

    public function save() {

      //only save columns we need
      $colmeta = $this->manager->getColumnsMeta() ;
      $data = $this->toArray();
      foreach ($data as $key => $value) {
        if(!isset($colmeta[$key])) unset($data[$key]);
        if(is_bool($value)) $data[$key] = (int)$data[$key]; //late conversion for db insertion
      }

      if ($this->isNew()) {
          // Insertion des données en base
          $this->db->insert(static::TABLE_NAME, $data);
          $this->id = $this->db->lastInsertId();
          return $this;
      }
      else {
          //mode "update"
          unset($data[static::PRIMARY]);
          $this->db->update(static::TABLE_NAME, $data, array(static::PRIMARY => $this->id));
          // Renvoyer l'objet
          return $this;

      }
    }

    public function isNew(){
      return !(isset($this->id) && $this->id > 0 && $this->id != null);
    }

    public function isEmpty(){
      return ($this->count() == 0);
    }

    //BASIC QUERY MACHINERY

    public static function fetchAll(Manager $manager,QueryBuilder $query = null)
    {
      $list = new static($manager);

      if(is_null($query)){
        $query = $manager->query();
      }
      $sql_res = $query->execute();
      $list->loadPile($sql_res->fetchAll());

      return $list ;

    }

    public static function fetchOne(Manager $manager,QueryBuilder $query)
    {
      $query = $query->setMaxResults(1);
      $list = self::fetchAll($manager, $query);
      if(!$list->valid()) return $list;
      return $list->current();
    }


    /*
     * ITERABLE IMPLEMENTATION
     * Cf : http://php.net/manual/en/class.iterator.php
     */

    public function count() {
      return count($this->_pile);
    }

    public function next() {
      $this->_cursor++;
    }

    public function valid() {
      return $this->_cursor < count($this->_pile);
    }

    public function current() {
      return $this;
    }
    public function key() {
      return $this->id;
    }

    public function rewind() {
      $this->_cursor = 0;
    }

    // Not required by Iterable but is sometimes useful
    public function first() {
      $this->rewind();
      return $this->current();
    }

    // Not required by Iterable but is sometimes useful
    public function end() {
      $this->_cursor = count($this->_pile) - 1;
      return $this->current();
    }

    //INTERNAL MACHINERY

    //on met le curseur sur la dernière donnée
    final public function loadData(array $data, $prefix = null) {
      //on unset l'id afin d'être sûr de ne pas l'overrider par mégarde
      unset($data["id"]);
      foreach ($data as $p => $value) {
        $this->$p = $value;
      }
      $this->end();
    }

    final public function loadPile(array $fetched_pile){
      $this->_pile = $fetched_pile;
      return;
    }

    //EXPORT FUNCTION

    //dump recursively to a generic array
    final public function toArray() {
      $data = array();
      if(!isset($this->_pile[$this->_cursor])) return $data;
      foreach($this->_pile[$this->_cursor] as $colname => $colvalue){
        if($colvalue instanceof Entity){
          if($this->$colname->count() <= 1){
            $data[$colname] = $this->$colname->toArray();
          }else{
            $data[$colname] = array();
            foreach ($this->$colname as $subObject) {
              $data[$colname][] = $this->$colname->toArray();
            }
          }
        }else{
          $data[$colname] = $this->$colname;
        }
        if(substr($colname, -5) == "_json") {
          $nicecolname = substr($colname, 0, -5);
          $data[$nicecolname] = $this->$nicecolname;
        }
      }
      return $data;
    }

    //dump to a generic object
    final public function toObject(){
       $data = new \stdClass();

       foreach($this->toArray() as $colname => $value){
        $data->$colname = $value;
       }
       return $data;
    }

    //dump all the piled data
    final public function dumpPile(){
      return $this->_pile;
    }


    public function getDbName(){
        return $this->_name;
    }

    /*
     * ITERABLE IMPLEMENTATION
     * Cf : http://php.net/manual/en/class.serializable.php
     */

    public function serialize() {
        return serialize(array("_pile"=>$this->_pile,"_cursor"=>$this->_cursor));
    }
    public function unserialize($data) {
        $saved = unserialize($data);
        $this->_pile = $saved["_pile"];
        $this->_cursor = $saved["_cursor"];
        return $this;
    }
    public function setManager($manager){
      $this->manager = $manager;
      $this->db = $manager->db;
    }

    /*
     * JsonSerializable IMPLEMENTATION
     * Cf : http://php.net/manual/en/class.jsonserializable.php
     */

    public function jsonSerialize(){
      return $this->toObject();
    }

    /*
     * ArrayAccess IMPLEMENTATION
     * Cf : http://php.net/manual/en/class.arrayaccess.php
     */

    public function offsetExists ($offset){
      return isset($this->_pile[$offset]);
    }

    public function offsetGet($offset){
      if($this->offsetExists($offset)){
        $this->_cursor = $offset;
        return $this;
      }else{
        throw new Exception("Offset ".$offset." is undefined", 1);
      }
    }

    public function offsetSet ($offset ,$value){
      throw new Exception("Illegal setting of value : Use ->append(\$data)", 1);
    }
    public function offsetUnset ($offset){
      throw new Exception("Illegal unsetting", 1);
    }

    /*
     * Sortable
     * not actually an interface, just a wrapper
     * see http://php.net/manual/en/function.usort.php
     */

    public function usort($compare){
      if(!is_callable($compare)){
        throw new Exception("Comparison function is not callable");
      }
      return usort($this->_pile, $compare);
    }

    /*
     * MAGICAL SETTER / GETTER
     * All the job of loadPile is now here on a pay-as-you-need basis
     * Worst case is equivalent to previous implementation
     */

    public function __isset($p){
      if($p == "id" && static::PRIMARY!="id"){
        return isset($this->{static::PRIMARY});
      }
      //check the line pointed by the cursor exists
      if(!isset($this->_pile[$this->_cursor])) return false;
      //check the property exists in the line
      if(  !isset($this->_pile[$this->_cursor][$p])
        && !isset($this->_pile[$this->_cursor][$p."_json"]))
      {
        return false;
      }

      return true;
    }

    public function __get($p){
      //id is a shortcut for the real primary id
      if($p == "id"){
        if(isset($this->{static::PRIMARY})){
          return (int)$this->_pile[$this->_cursor][static::PRIMARY];
        }else{
          return null;
        }
      }

      //if our property exists : format it according to Platform Columns and return it
      if($this->__isset($p)){

        if(isset($this->_pile[$this->_cursor][$p])){
          $colvalue = $this->_pile[$this->_cursor][$p];
        }elseif(isset($this->_pile[$this->_cursor][$p."_json"])){
          //property didn't exist proper, __isset, garanties it is json
          $colvalue = json_decode($this->_pile[$this->_cursor][$p."_json"], true);
        }
        $colmeta = $this->manager->getColumnsMeta();

        //do we have this column in our infos ?
        if(isset($colmeta[$p])){
          $platform   = $this->db->getDatabasePlatform();
          $type = $colmeta[$p]->getType();
          if(  $type instanceof \Doctrine\DBAL\Types\BigIntType
            || $type instanceof \Doctrine\DBAL\Types\DecimalType
            || $type instanceof \Doctrine\DBAL\Types\FloatType
            || $type instanceof \Doctrine\DBAL\Types\IntegerType
            || $type instanceof \Doctrine\DBAL\Types\SmallIntType
            || $type instanceof \Doctrine\DBAL\Types\StringType
            || $type instanceof \Doctrine\DBAL\Types\TextType
            )
          {
            $colvalue = $colmeta[$p]->getType()->convertToPHPValue($colvalue,$platform);
          }
          if($type instanceof \Doctrine\DBAL\Types\BooleanType){
            $colvalue = (boolean) $colvalue;
          }
        }

        return $colvalue;
      }

      //did not match anything
      return null;
    }

    public function __set($p, $value){
      if(!isset($this->_pile[$this->_cursor])){
        $this->_pile[$this->_cursor] = [];
      }
      if($p == "id"){
        $this->_pile[$this->_cursor][static::PRIMARY] = $value;
      }

      //on hijack le cas où on joue avec une colonne en json : on n'a que la valeur encodée en mémoire
      $colmeta = $this->manager->getColumnsMeta();
      if( isset($colmeta[$p."_json"]) ) {
        $p = $p."_json";
        $value = json_encode($value);
      }

      $this->_pile[$this->_cursor][$p] = $value;
    }
}
