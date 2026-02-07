<?php
/**
 * =================================================================
 * menu.php
 * メニュー用PHPスクリプト
 * =================================================================
 */

//=================================================================
// ロジック部
//=================================================================

// 直接アクセス禁止
//if (array_shift(get_included_files()) === __FILE__) die('Error. Invalid URL.');

//--------------------------------
// include
//--------------------------------

/**
 * ----------------------------------------------------------
 * checkMainMenuSelected()
 * メニューが選択されているかどうかを判定し、
 * 選択されている場合はスタイルシートを選択する。
 * @param $target メニュー種別
 * ----------------------------------------------------------
 */
function checkMainMenuSelected($target) {
	global $param;
	
	if ($param == $target) {
		echo 'selected';
	}
}

/**
 * ----------------------------------------------------------
 * getTargetContent()
 * コンテンツ領域に表示するスクリプトを選択する
 * @param $target メニュー種別
 * ----------------------------------------------------------
 */
function getTargetContent($target) {
	
	switch($target) {
		case config::MENU_NAME_1_TAG:
			$page =  config::MENU_NAME_1_TAG.'.php';
			break;
		case config::MENU_NAME_2_TAG:
			$page =  config::MENU_NAME_2_TAG.'.php';
			break;
		case config::MENU_NAME_3_TAG:
			$page =  config::MENU_NAME_3_TAG.'.php';
			break;
		case config::MENU_NAME_4_TAG:
			$page =  config::MENU_NAME_4_TAG.'.php';
			break;
		case config::MENU_NAME_5_TAG:
			$page =  config::MENU_NAME_5_TAG.'.php';
			break;
		case config::MENU_NAME_6_TAG:
			$page =  config::MENU_NAME_6_TAG.'.php';
			break;
		case config::MENU_NAME_7_TAG:
			$page =  config::MENU_NAME_7_TAG.'.php';
			break;
		case config::MENU_NAME_8_TAG:
			$page =  config::MENU_NAME_8_TAG.'.php';
			break;
		case config::MENU_NAME_9_TAG:
			$page =  config::MENU_NAME_9_TAG.'.php';
			break;
		case config::MENU_NAME_10_TAG:
			$page =  config::MENU_NAME_10_TAG.'.php';
			break;
		case config::MENU_NAME_11_TAG:
			$page =  config::MENU_NAME_11_TAG.'.php';
			break;
		case config::MENU_NAME_12_TAG:
			$page =  config::MENU_NAME_12_TAG.'.php';
			break;
		default:
			$page = 'error.php';
			break;
	}
	
	return $page;
}

//=================================================================
// デザイン部
//=================================================================
?>

<div id="main_menu">

	<?php
	$userInfo = getUserInfo($_SESSION['USERID']);
	?>

	<input type="button" class="menu <?php checkMainMenuSelected(config::MENU_NAME_1_TAG) ?>"
	value="<?php echo config::MENU_NAME_1 ?>"
	onClick="location.href='./index.php?reg=<?php echo config::MENU_NAME_1_TAG ?>'">

	<?php
	// 協力企業以外に表示 ▼ ここから ▼
	if ($userInfo['auth'] != config::USER_COOPERATION) { ?>
	
		<?php
		// 東海は実績入力のみボタンを表示
		if ($userInfo['user'] != "lh-t" && $userInfo['user'] != "lh-tokai") { ?>
			<input type="button" class="menu <?php checkMainMenuSelected(config::MENU_NAME_2_TAG) ?>"
			value="<?php echo config::MENU_NAME_2 ?>"
			onClick="location.href='./index.php?reg=<?php echo config::MENU_NAME_2_TAG ?>&type=view'">
		<?php } else { ?>
			<input type="button" class="menu <?php checkMainMenuSelected(config::MENU_NAME_2_TAG) ?>"
			value="<?php echo config::MENU_NAME_2 ?>"
			onClick="location.href='./index.php?reg=<?php echo config::MENU_NAME_2_TAG ?>&type=input'">
		<?php } ?>

		<?php if ($userInfo['user'] != "lh-t" && $userInfo['user'] != "lh-tokai") { ?>
			<input type="button" class="menu <?php checkMainMenuSelected(config::MENU_NAME_3_TAG) ?>"
			value="<?php echo config::MENU_NAME_3 ?>"
			onClick="location.href='./index.php?reg=<?php echo config::MENU_NAME_3_TAG ?>&type=view'">
		<?php } // 東海はキャンペーンを非表示 ?>
	<?php } //協力企業以外に表示 ▲ ここまで ▲ ?>

	<?php
	// 管理者、役員、同友のみ表示 ▼ ここから ▼
	if ($userInfo['auth'] == config::USER_ADMIN || $userInfo['auth'] == config::USER_EXEOFFICER || $userInfo['auth'] == config::USER_EXECUTIVE) {
		if (local_config::FEATURE_BRANCH_POINT_STATUS) { ?>
		<input type="button" class="menu <?php checkMainMenuSelected(config::MENU_NAME_7_TAG) ?>"
		value="<?php echo config::MENU_NAME_7 ?>"
		onClick="location.href='./index.php?reg=<?php echo config::MENU_NAME_7_TAG ?>&type=view'">
		<?php } //FEATURE_BRANCH_POINT_STATUS ?>

		<?php
		if (local_config::FEATURE_PRESIDENT_PRIZE_STATUS) { ?>
		<input type="button" class="menu <?php checkMainMenuSelected(config::MENU_NAME_8_TAG) ?>"
		value="<?php echo config::MENU_NAME_8 ?>"
		onClick="location.href='./index.php?reg=<?php echo config::MENU_NAME_8_TAG ?>&type=view'">
		<?php } //FEATURE_PRESIDENT_PRIZE_STATUS ?>
	<?php } //管理者、役員、同友のみ表示 ▲ ここまで ▲ ?>

	<?php
	// 管理者、役員のみ表示 ▼ ここから ▼
	if ($userInfo['auth'] == config::USER_ADMIN  || $userInfo['auth'] == config::USER_EXEOFFICER) {
		if (local_config::FEATURE_SALES_PROMOTION_FOR_BRANCH) { ?>

		<input type="button" class="menu <?php checkMainMenuSelected(config::MENU_NAME_10_TAG) ?>"
		value="<?php echo config::MENU_NAME_10 ?>"
		onClick="location.href='./index.php?reg=<?php echo config::MENU_NAME_10_TAG ?>&type=view'">

		<input type="button" class="menu <?php checkMainMenuSelected(config::MENU_NAME_9_TAG) ?>"
		value="<?php echo config::MENU_NAME_9 ?>"
		onClick="location.href='./index.php?reg=<?php echo config::MENU_NAME_9_TAG ?>&type=view'">
		
		<?php } //FEATURE_SALES_PROMOTION_FOR_BRANCH ?>
	<?php } //管理者、役員のみ表示 ▲ ここまで ▲ ?>

	<?php
	// 表彰は全員に表示（★兵庫専用★）
	if (local_config::FEATURE_COMMENDATION_FOR_HYOGO) { ?>
	<input type="button" class="menu <?php checkMainMenuSelected(config::MENU_NAME_12_TAG) ?>"
	value="<?php echo config::MENU_NAME_12 ?>"
	onClick="location.href='./index.php?reg=<?php echo config::MENU_NAME_12_TAG ?>&type=view'">
	<?php } ?>

	<?php
	// 協力企業は全員に表示
	if (local_config::FEATURE_COOPERATION) { ?>
		<?php if ($userInfo['user'] != "lh-t" && $userInfo['user'] != "lh-tokai") { ?>
			<input type="button" class="menu <?php checkMainMenuSelected(config::MENU_NAME_11_TAG) ?>"
			value="<?php echo config::MENU_NAME_11 ?>"
			onClick="location.href='./index.php?reg=<?php echo config::MENU_NAME_11_TAG ?>&type=view'">
		<?php } // 東海は協力企業を非表示 ?>
	<?php } ?>

	<?php
	// 管理者のみ表示 ▼ ここから ▼
	if ($userInfo['auth'] == config::USER_ADMIN) { ?>

		<?php
		if (local_config::FEATURE_MSG_FUNCTION) { ?>
		<input type="button" class="menu <?php checkMainMenuSelected(config::MENU_NAME_6_TAG) ?>"
		value="<?php echo config::MENU_NAME_6 ?>"
		onClick="location.href='./index.php?reg=<?php echo config::MENU_NAME_6_TAG ?>&type=setting'">
		<?php } ?>

		<input type="button" class="menu <?php checkMainMenuSelected(config::MENU_NAME_4_TAG) ?>"
		value="<?php echo config::MENU_NAME_4 ?>"
		onClick="location.href='./index.php?reg=<?php echo config::MENU_NAME_4_TAG ?>&type=top'">

		<input type="button" class="menu <?php checkMainMenuSelected(config::MENU_NAME_5_TAG) ?>"
		value="<?php echo config::MENU_NAME_5 ?>"
		onClick="location.href='./index.php?reg=<?php echo config::MENU_NAME_5_TAG ?>'">
	<?php } // 管理者のみ表示 ▲ ここまで ▲ ?>
	
</div>
<hr>

<?php
/**
 * =================================================================
 *  Copyright(c)2016 iSKET All Rights Reserved.
 * =================================================================
 */
?>