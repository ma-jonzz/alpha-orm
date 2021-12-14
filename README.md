Alpha ORM
=========

Alpha ORM is a micro-ORM built on Doctrine DBAL specifically tailored for usage in the Silex micro-framework and Symfony Flex application.



Install
=======
In your composer.json, add 

    "troisyaourts/alpha": "dev-master"

to the list of requirements and then
    
    composer update

In your typical "app.php" add the following lines :

    $app->register(new Provider\DoctrineServiceProvider(), array('db.options' => $config['db.options']));
    $app->register(new Alpha\ServiceProvider());

This will make the Alpha Manager available under "models" in your Controllers. 
The Alpha Manager use the main $app['db'] object.

Some examples of usage are provided in the "examples" directory.

To run some tests, clone the current release and do

    curl -s http://getcomposer.org/installer | php
    php composer.phar install
    php examples/test.php


Usage
=====

Class file
----------

In "Model" create a file "MyObject.php"
    
    <?php
      namespace Model;
      use Alpha\Entity;
      use Alpha\Manager;

      class MyObject extends Entity
      {
        const TABLE_NAME = 'myobject';
        const PRIMARY = 'prim_id'; //optionnal, default is juste "id"

        public function getHelloFoo(){
          return "Hello ".$this->foo;
        }

        public static function getAllBarred(Manager $manager){
          $q = $manager->query()
                             ->where('bar = :bar')
                             ->setParameter("bar", 1);
          return $manager->fetchAll($q);
        }
        
      }
    ?>

Fetching a new object
---------------------
    <?php 
      $myobj = $app['models']('MyObject')->get();
    ?>


Fetching a object by primary_id
-------------------------------
    <?php 
      $myobj = $app['models']('MyObject')->get(12345);
    ?>


Fetching a list of object using a defined static function
---------------------------------------------------------
    <?php 
      $myobj_iterator = $app['models']('MyObject')->getAllBarred();
      foreach($myobj_iterator as $prim_id => $obj)
        echo $obj->getHelloFoo();
    ?>

And append a new object to the list
    
    <?php 
      $lastItem = $app['models']('MyObject')->get(21);
      $myobj_iterator->append($lastItem);
      echo $myobj_iterator->end()->getHelloFoo();
    ?>

Saving an object
----------------
    <?php 
      $myobj = $app['models']('MyObject')->get();
      $myobj->bar = 1;
      $myobj->save();  //obj didn't exist : INSERT
      echo $myobj->id; //you can access to last_insert_id

      $myobj->foo = "Toto";
      $myobj->save(); //obj existed : UPDATE
    ?>




History
=======

This project started in 2001, when Matthieu Duclos decided to build a web application for an email-based role-playing game called Ys. He wrote some files, including what would later become the alpha class, and called the result aobjet.

He was later rejoined by some other people, and with Nicolas B. and Julien Z. he continued to developp his work. A new version was made and called alpha, as it was the first brick of any other web application we built.

The project has evolved over age and this version is specifically tailored for performance when used in the Silex micro-framework. 
