<?php
header('Content-type:text/html; Charset=utf-8');
$sitehost = (isHTTPS() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']; //获取域名
$payTime = time(); //付款时间
$userid = (int) getcvar('mluserid');
$username = '游客';
$bgid = 0;
if ($userid) {
	$username = getcvar('mlusername');
}
$outTradeNo = $ddno ? $ddno : date('Ymdhis') . rand();
$posttime = date("Y-m-d H:i:s");
$payip = egetip();
$paybz = '无';
$gourl = '/e/member/cp/';
if ($phome == 'PayToFen') {
	$pr = $empire->fetch1("select paymoneytofen,payminmoney from {$dbtbpre}enewspublic limit 1");
	$fen = floor($money) * $pr['paymoneytofen'];
	$paybz = '购买点数: ' . $fen;
	$jumpurl = $sitehost . '/e/payapi/pay.php?money=' . $money . '&payid=' . $payr['payid'] . '&phome=' . $phome;
} elseif ($phome == 'PayToMoney') {
	$paybz = '存预付款:' . $money;
	$jumpurl = $sitehost . '/e/payapi/pay.php?money=' . $money . '&payid=' . $payr['payid'] . '&phome=' . $phome;
} elseif (isset($buyr['id'])) {
	$phome = 'BuyGroupPay';
	$paybz = "充值类型:" . addslashes($buyr['gname']);
	$bgid = $buyr['id'];
	$jumpurl = $sitehost . '/e/payapi/BuyGroupPay.php?id=' . $bgid . '&payid=' . $payr['payid'] . '&phome=' . $phome;
} else {
	$phome = 'ShopPay';
	$paybz = '商品购买，订单(ddno=' . $outTradeNo . ')';
	$jumpurl = $sitehost . '/e/payapi/ShopPay.php?paytype=wxpay' . '&phome=' . $phome;
	$gourl = $public_r['newsurl'];
}

//支付标识
$arr['BuyGroupPay'] = '购买会员组';
$arr['ShopPay'] = '商城支付';
$arr['PayToFen'] = '购买点数';
$arr['PayToMoney'] = '存预付款';
$arr['PayToMoney'] = '存预付款';

$orderName = $productname;
$body = $productsay . ",动作:" . $phome . ",订单号:" . $out_trade_no; //商品描述
/*** 请填写以下配置信息 ***/
$returnUrl = $sitehost; //付款成功后的同步回调地址
$notifyUrl = $sitehost . "/e/payapi/payjs/notify.php";

//添加订单
$num = $empire->gettotal("select count(*) as total from {$dbtbpre}enewspayrecord where orderid='$outTradeNo'");
if (!$num) {
	$empire->query("insert into {$dbtbpre}enewspayrecord(id,userid,username,orderid,money,posttime,paybz,type,payip,phome,status,bgid) values(NULL,'$userid','$username','$outTradeNo','$money','$posttime','$paybz','payjs','$payip','$phome',0,'$bgid');");
}
//删除2小时内未付款订单，减少数据库压力
$empire->query("delete from {$dbtbpre}enewspayrecord where UNIX_TIMESTAMP(posttime)+7200<UNIX_TIMESTAMP() and status=0");

// 配置通信参数
$price = $money;
$config = array(
    'mchid' => $payr['payuser'],   // 配置商户号
    'key'   => $payr['paykey'],   // 配置通信密钥
);

// 初始化
include_once("payjs.class.php");
$payjs = new Payjs($config);

$parameter = array(
    'mchid' => $payr['payuser'],
		"notify_url"	=> $notifyUrl,
		"out_trade_no"	=> (string) $outTradeNo,
		"total_fee"	=> (float) $price * 100,
		'body' => '用户充值'
);
//请求URL
$url = $payjs->cashier($parameter);
$url = 'https://qr.payjs.cn/image?body=' . urlencode($url);

//判断是否HTTPS
function isHTTPS() {
	if (defined('HTTPS') && HTTPS) {
		return true;
	}

	if (!isset($_SERVER)) {
		return FALSE;
	}

	if (!isset($_SERVER['HTTPS'])) {
		return FALSE;
	}

	if ($_SERVER['HTTPS'] === 1) {
		//Apache
		return TRUE;
	} elseif ($_SERVER['HTTPS'] === 'on') {
		//IIS
		return TRUE;
	} elseif ($_SERVER['SERVER_PORT'] == 443) {
		//其他
		return TRUE;
	}
	return FALSE;
}
	?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<title><?=$arr[$phome]?>收银台</title>
	<link rel="stylesheet" href="https://payjs.cn/static/css/bootstrap.min.css">
	<style>
        body{background-color:#f4f6f8;}
        .qrcode img{border:1px solid #ccc;max-width: 220px;}
        .f14{font-size:14px;}
        .center{text-align: center;}
	</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-dark">
    <div class="container">
        <a class="navbar-brand" href="#" style="color:#ccc;">收银台</a>
    </div>
</nav>

<div class="container">
    <div class="row" style="padding:100px 0;background-color:#fff;">
        <div class="col"></div>
        <div class="col f14 center">
            <div class="qrcode center"><img src="<?=$url?>" alt="用微信扫码"></div>
            <div style="margin:8px;">
            <p> 您正在<span><?=$arr[$phome]?></span> </p>
            <p>请在规定时间内完成付款。</p>
            </div>
            <div class="endtime" id="endtime"></div>
        </div>
        <div class="col center">
            <img src="https://cdn.payjs.cn/5f53c2e223a00" style="width:100%;max-width:220px;">
        </div>
        <div class="col"></div>
        <div class="col-12 center f12" id="msg" style="margin:40px 0;"></div>
    </div>
    <div class="row">
    </div>
</div>
<script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.5.1/jquery.js"></script>
<script>
//监听支付情况
setInterval("issuccess()",1000);
function issuccess(){
    $.get('payjs/issuccess.php',{'orderid':'<?=$outTradeNo?>'}, function(data) {
        if(data){
            $("#msg").html("<span class='alert alert-success f12'>支付成功！3秒钟后返回会员中心</span>");
            setTimeout("back()",5000);
        }
    });
}

function back(){
    window.location.href='<?=$public_r['newsurl']?>e/member/my/';
}

var maxtime = 60*60*2; //倒计时开始，支付宝的支付有效期是2小时
function CountDown() {
    if (maxtime >= 0) {
        hour = PrefixInteger(Math.floor(maxtime / 3600),2);
        minutes = PrefixInteger(Math.floor(maxtime  / 60 % 60),2);
        seconds = PrefixInteger(Math.floor(maxtime % 60),2);
        msg = "请在 <span>" + hour + "："+ minutes + "：" + seconds + "</span> 秒内完成支付";
        document.all["endtime"].innerHTML = msg;
        --maxtime;
    } else{
        clearInterval(timer);
        alert("支付过期，请重新下单!");
    }
}
timer = setInterval("CountDown()", 1000);
function PrefixInteger(num, n) {
    return (Array(n).join(0) + num).slice(-n);
}
</script>
</body>
</html>
