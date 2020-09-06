<?php
require "../../class/connect.php";
require "../../class/db_sql.php";
require "../../class/q_functions.php";
require "../../member/class/user.php";
$link = db_connect();
$empire = new mysqlquery();
$editor = 1;

$orderid = RepPostVar($_GET['orderid']);
$ddid = RepPostVar($_GET['ddid']);
$num = '';
if ($orderid) {
	$num = $empire->gettotal("select count(*) as total from {$dbtbpre}enewspayrecord where orderid='$orderid' and status='1' limit 1");
}
if ($ddid) {
	$num = $empire->gettotal("select count(*) as total from {$dbtbpre}enewsshopdd where ddid='$ddid' and haveprice=1 limit 1");
}
if ($num) {
	echo 1;
}
db_close();
$empire = null;
?>