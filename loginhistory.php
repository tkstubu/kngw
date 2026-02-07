<?php
/**
 * =================================================================
 * loginhistory.php
 * ログイン履歴表示用PHPスクリプト
 * =================================================================
 */
//--------------------------------
// include
//--------------------------------
require_once("common/config.php");
require_once("common/setting.php");
require_once("common/func.php");

$getArray  = convertSpecialChar($_GET);
//printArray($getArray);

?>
<! DOCTYPE html>
<html>
	<head>
    	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<title><?php printSystemName() ?></title>
		<link rel="stylesheet" type="text/css" href="css/style.css" media="all">
		<script type="text/javascript" src="js/json2.js"></script>
		<script type="text/javascript" src="js/form.js"></script>
	</head>
	<body>
<hr>
『<?php echo $getArray['name']; ?>』のログイン履歴
<hr>

<?php
// ログイン履歴をあるだけ表示
$arraynum = count($getArray) - 1;
for ($i = 0; $i < $arraynum; $i++) {
    $param = 'ls'.$i;
    $cnt = $i+1;
    $cnt = sprintf("%02d", $cnt);   // 桁揃え

    if ($getArray[$param] !== ""){
        echo '['.$cnt.'] '.$getArray[$param].'<br />';
    }
}
?>
	</body>
</html>
<?php
/**
 * =================================================================
 *  Copyright(c)2017 iSKET All Rights Reserved.
 * =================================================================
 */
?>