<?php 
echo "Starting test.".PHP_EOL;

include(__DIR__."/../vendor/autoload.php");

//Models

class MyObj extends Alpha\Entity{
  const DB_NAME = 'db1';
  const TABLE_NAME = 'my_obj';
  const PRIMARY = 'my_id';

  public function hasContent(){
    return !empty($this->content);
  }
}

class MySecObj extends Alpha\Entity{
  const DB_NAME = 'db2';
  const TABLE_NAME = 'my_secobj';
  const PRIMARY = 'id';
}
class MyThirdObj extends Alpha\Entity{
  const DB_NAME = 'db2';
  const TABLE_NAME = 'my_thirdobj';
  const PRIMARY = 'my_id';
}


//Initialisation
$tmp_database_file = $temp_file = tempnam(sys_get_temp_dir(), 'Alpha');


$config = new \Doctrine\DBAL\Configuration();
$connectionParams = array(
    'user'   => '',
    'password' => '',
    'path'   => $tmp_database_file,
    'driver' => 'pdo_sqlite',
);
$db = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);

$my_obj_create_query = <<<EOF
CREATE TABLE IF NOT EXISTS `my_obj` (
  `my_id` INTEGER PRIMARY KEY,
  `title` varchar(255),
  `content` text,
  `infos_json` text,
  `age` INTEGER
);
EOF;
$db->query($my_obj_create_query);

//Code
$manager = new Alpha\Manager($db, 'MyObj', '\\');

//TEST SAVE
$inst = $manager->get(null,array("title"=>"miou","age"=>33));
$inst->save();

var_dump(assert($inst->my_id == 1, "Save"));
var_dump(assert(is_int($inst->age), "Type Cast"));

//TEST EMPTY

var_dump(assert(empty($inst->content),"Empty ok"));
var_dump(assert(!empty($inst->age),"Non-Empty ok"));
var_dump(assert(empty($inst->infos),"Empty json ok"));

//TEST METHOD CALL
try{
  $manager->unknownFunction();
  $catched = 0;
}catch(Alpha\Exception $e){
  $catched = 1;
}

var_dump(assert($catched == 1, "unknownFunction Exception"));

//TEST RETRIEVAL VIA GET
$inst2 = $manager->get(1);

var_dump(assert($inst2->title == "miou", "Retrieval"));

//TEST CAST
var_dump(assert(is_int($inst2->my_id), "Cast"));
var_dump(assert(is_int($inst2->age), "Cast"));


//INSERTING A SECOND
$inst3 = $manager->get();
$inst3->title = "wouaf";
$inst3->content = "Un chien !";
$inst3->save();

var_dump(assert($inst3->hasContent(), "Method Call"));

//TEST FETCHALL
$inst4 = $manager->fetchAll();
var_dump(assert($inst4->count() == 2, "List retrieval"));

//TEST ITERATOR
foreach($inst4 as $key => $obj){
  assert($key == $obj->my_id, "Matching ids");
}
//TEST ARRAYACCESS
assert(2 == $inst4[1]->my_id, "Matching ids");

//TEST FILTER
$filtered = $inst4->filter(function($i){
    return $i->hasContent();
});

var_dump(assert($filtered->count() == 1, "Filter"));

//TEST JSON COLUMNS
$params5 = array(
  "title" => "coincoin",
  "content" => "Un canard",
  "infos" => array("legs" => 2)
  );
$inst5 = $manager->get(null,$params5);
$inst5->save();
$inst6 = $manager->get($inst5->my_id);

var_dump(assert(!empty($inst6->infos),"Non Empty json ok via proxy"));
var_dump(assert($inst6->infos["legs"] == 2, "JSON columns via proxy"));

$inst6->infos_json = json_encode(array("legs" => 3));
$inst6->save();
$inst7 = $manager->get($inst6->my_id);
var_dump(assert($inst7->infos["legs"] == 3, "JSON columns via json"));
var_dump(assert(!empty($inst6->infos),"Non Empty json ok via json"));


$inst7->infos = [];
var_dump(assert(empty($inst7->infos),"Empty json ok via json and property is empty"));

//MULTI DB
//Initialisation
$tmp_database_file2 = $temp_file2 = tempnam(sys_get_temp_dir(), 'Alpha');

$config = new \Doctrine\DBAL\Configuration();
$connectionParams = array(
    'user'   => '',
    'password' => '',
    'path'   => $tmp_database_file2,
    'driver' => 'pdo_sqlite',
);
$db2 = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);

$my_obj_create_query = <<<EOF
CREATE TABLE IF NOT EXISTS `my_secobj` (
  `id` INTEGER PRIMARY KEY,
  `title` varchar(255),
  `content` text,
  `infos_json` text
);
EOF;
$db2->query($my_obj_create_query);
$manager2 = new Alpha\Manager($db2, 'MySecObj', '\\');

$inst8 = $manager->get(null,array("title"=>"miou"));
$inst8->save();

try{
  $exception_catched = false;
  $manager2->related("MyObj");
}catch(Alpha\Exception $e){
  $exception_catched = true;
}
var_dump(assert($exception_catched, "Exception catched"));

try{
  $exception_catched = false;
  $manager2->related("MyThirdObj");
}catch(Alpha\Exception $e){
   $exception_catched = true;
}
var_dump(assert(!$exception_catched, "Exception catched"));

try{
  $exception_catched = false;
  $inst9 = unserialize(serialize($inst8));
  $inst9->current();
  $inst9->setManager($manager);
  $inst9->title="changed";
  $inst9->save();

  $inst10 = $manager->get($inst9->id);
  var_dump(assert($inst10->title == "changed","Serialization - Data integrity"));

}catch(Alpha\Exception $e){
   $exception_catched = true;
}

var_dump(assert(!$exception_catched, "Serialization"));

$result = json_decode(json_encode($inst10));
var_dump(assert($result->title == "changed","JsonSerialization - Data integrity"));

$inst9->extra = $inst8;
$dump = $inst9->toArray();
var_dump(assert(is_array($dump["extra"]),"Recursive toArray"));

$inst9->extra->append($inst7);
$dump = $inst9->toArray();
var_dump(assert(count($dump["extra"]) == 2,"Recursive multiToArray"));

echo "Test ended.".PHP_EOL;
unlink($tmp_database_file);
