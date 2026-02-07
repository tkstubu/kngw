<?php
/**
 * =================================================================
 * header.php
 * 各画面共通ヘッダー用PHPスクリプト
 * =================================================================
 */
//=================================================================
// ロジック部
//=================================================================

//--------------------------------
// include
//--------------------------------
require_once("login.php");
require_once("setting.php");
?>

<?php
//=================================================================
// デザイン部
//=================================================================
?>
<! DOCTYPE html>
<html>
	<head>
    	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<title><?php printSystemName() ?></title>
		<link rel="icon" href="../lotus_favicon.ico">
		<link rel="apple-touch-icon-precomposed" href="https://lotas-system.net/webclip.png" />
		<link rel="stylesheet" type="text/css" href="css/style.css" media="all">
		<link rel="stylesheet" type="text/css" href="css/progressBar.css" media="all">
		<script type="text/javascript" src="js/json2.js"></script>
		<script type="text/javascript" src="js/form.js"></script>
		<script type="text/javascript" src="js/pace.min.js"></script>
	</head>
    
	<body>
		<div class="wrap">
		<div id="header">
			<div id="title">
				<p><?php printSystemName() ?>&nbsp;<?php echo config::SOFTWARE_VERSION ?></p>
			</div>
			<div id="menu">
				<?php checkLogin() ?>
			</div>
		</div>
<?php
/**
 * =================================================================
 *  Copyright(c)2013 iSKET All Rights Reserved.
 * =================================================================
 */
?>