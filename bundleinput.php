<?php
/**
 * =================================================================
 * bundleinput.php
 * 実績一括入力用PHPスクリプト
 * =================================================================
 */

//=================================================================
// ロジック部
//=================================================================
//--------------------------------
// include
//--------------------------------
require_once("common/setting.php");
require_once("common/func.php");

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
		<link rel="stylesheet" type="text/css" href="css/style.css" media="all">
		<script type="text/javascript" src="js/form.js"></script>
	</head>
    
	<body>
		<div id="header">
			<div id="title">
				<p><?php printSystemName() ?>&nbsp;<?php echo config::SOFTWARE_VERSION ?></p>
			</div>
		</div>
		<hr>
		
		<div id="contents">
			<p>フォームに入力する値を一括で入力することができます。</p>
			<p>テキストエリアに同友毎に1行ずつ数値を入力し、「反映」ボタンを押してください。</p>
			<p class="font_red">※Excelのデータ(カンマ付き数字もOK)をコピーして、貼り付けることもできます。</p>
			<br />
			<p>1列目:同友コード、2列目:数値を入力して、エクセル2列をコピー&ペーストすることで同友コードを基に数値を入力できます。</p>
			<p>例：27001	123</p>
			<p>※キャンペーンの計画入力で提携企業が複数ある場合は、3列目、4列目のように列を増やしてください。</p>
			<p>例：27001	123	456</p>
			<br />
			<p>
			<textarea name="bandleinputarea" rows="20" cols="40"></textarea>
			</p>
			<br />
			<?php printButton('反映', '', 'closeBundleInputWindow()') ?>
		</div>
		
		<hr>

		<div id="footer">
			<div id="copyright">
				<p>Copyright&copy;2014 <?php printSystemName() ?></p>
			</div>
		</div>
	
	</body>
</html>

<?php
/**
 * =================================================================
 *  Copyright(c)2013-2014 iSKET All Rights Reserved.
 * =================================================================
 */
?>