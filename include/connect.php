<?php

$sqlConnection = @mysql_connect('localhost', 'root', '') or die('MySQL connection error2');
$dataBase = @mysql_select_db('message', $sqlConnection) or die("Can't connect to specified database");
mysql_query('SET NAMES utf8');
?>