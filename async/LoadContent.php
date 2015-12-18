<?php
	require_once('../include/export_class.php');
	include('../include/connect.php');
	$object = new ExportTable();
	$object->loadContent($_POST['nr'], (isset($_POST['table']) ? $_POST['table'] : ''), (isset($_POST['filename']) ? $_POST['filename'] : ''), (isset($_POST['type']) ? $_POST['type'] : ''), (isset($_POST['columns']) ? $_POST['columns'] : ''));
?>