<?php
require "../../class/connect.php";
require "../../class/db_sql.php";
require "../../class/q_functions.php";
require "../../member/class/user.php";
$link = db_connect();
$empire = new mysqlquery();
$payr = $empire->fetch1("select * from {$dbtbpre}enewspayapi where paytype='payjs'");
if (!$_POST) {
	die;
}
$arr = $_POST;
$mchid = $payr['payuser'];
$key =  $payr['paykey'];
$config = array(
    'mchid' => $mchid,   // 配置商户号
    'key'   => $key,   // 配置通信密钥
);

// 初始化
include_once("payjs.class.php");
$payjs = new Payjs($config);
$res = $payjs->notify($arr);
file_put_contents('payjs.txt', var_export($res, true));
if ($res != "验签失败") {
	//交易状态
	$trade_status = $_POST['return_code'];
	$orderid = $_POST['out_trade_no'];
	$money = (int) $_POST['total_fee'] / 100;

	$r = $empire->fetch1("select money,userid,username,bgid,phome,status from {$dbtbpre}enewspayrecord where orderid='$orderid'");
	if (!$r) {
		die('订单不存在');
	}
	if ($r['status'] == 1) {
		die('己支付');
	}
	echo $r['money'];
	if ($money != $r['money']) {
		die('金额不符');
	}
	include "../payfun.php";
	//订单操作
	if ($r['phome'] == 'ShopPay') {
		include '../../ShopSys/class/ShopSysFun.php';
		$res = $empire->fetch1("select ddid from {$dbtbpre}enewsshopdd where ddno='$orderid'");
		$ddid = $res['ddid'];
		$ddr = PayApiShopDdMoney($ddid);
		$money = (float) $money;
		$sql = $empire->query("update {$dbtbpre}enewsshopdd set haveprice=1 where ddid='$ddid'");
		//减少库存
		$shoppr = ShopSys_ReturnSet();
		if ($shoppr['cutnumtype'] == 1) {
			$buycarr = $empire->fetch1("select buycar from {$dbtbpre}enewsshopdd_add where ddid='$ddid'");
			Shopsys_CutMaxnum($orderid, $buycarr['buycar'], $ddr['havecutnum'], $shoppr, 0);
		}
		$sql = $empire->query("update {$dbtbpre}enewspayrecord set status=1 where orderid='$orderid'");
	}
	//购买点数
	if ($r['phome'] == 'PayToFen') {
		$userid = $r['userid'];
		$pr = $empire->fetch1("select paymoneytofen,payminmoney from {$dbtbpre}enewspublic limit 1");
		$fen = floor($money) * $pr['paymoneytofen'];
		$sql = $empire->query("update " . eReturnMemberTable() . " set " . egetmf('userfen') . "=" . egetmf('userfen') . "+" . $fen . " where " . egetmf('userid') . "='$userid'");
		$sql = $empire->query("update {$dbtbpre}enewspayrecord set status=1 where orderid='$orderid'");
	}
	//存预付款
	if ($r['phome'] == 'PayToMoney') {
		$userid = $r['userid'];
		$username = $r['username'];
		$sql = $empire->query("update " . eReturnMemberTable() . " set " . egetmf('money') . "=" . egetmf('money') . "+" . $money . " where " . egetmf('userid') . "='$userid'");
		$sql = $empire->query("update {$dbtbpre}enewspayrecord set status=1 where orderid='$orderid'");
		//备份充值记录
		BakBuy($userid, $username, $orderid, 0, $money, 0, 3);
	}
	//购买充值类型
	if ($r['phome'] == 'BuyGroupPay') {
		include "../../data/dbcache/MemberLevel.php";
		$userid = $r['userid'];
		$username = $r['username'];
		$money = (float) $money;
		$bgid = $r['bgid'];
		$buyr = $empire->fetch1("select * from {$dbtbpre}enewsbuygroup where id='$bgid'");
		//充值
		$user = $empire->fetch1("select " . eReturnSelectMemberF('userdate,userid,username,groupid') . " from " . eReturnMemberTable() . " where " . egetmf('userid') . "='$userid'");
		eAddFenToUser($buyr['gfen'], $buyr['gdate'], $buyr['ggroupid'], $buyr['gzgroupid'], $user);
		$sql = $empire->query("update {$dbtbpre}enewspayrecord set status=1 where orderid='$orderid'");
		//备份充值记录
		BakBuy($userid, $username, $buyr['gname'], $buyr['gfen'], $money, $buyr['gdate'], 1);
	}
	echo 'success';exit();
}
echo 'error';exit();
?>