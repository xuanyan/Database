<?php

require 'Database.php';
$db = Database::connect('mysql', 'localhost', 'root', 'root', 'mysql');

var_dump($db->getDriver());

print_r($db->getAll("SHOW TABLES"));

// lol
?>