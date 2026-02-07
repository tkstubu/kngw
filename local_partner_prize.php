<?php
/**
 * =================================================================
 * local_partner_prize.php
 * 地元提携企業
 * 販促費表示、設定用のファンクション
 * =================================================================
 */

//=================================================================
// ロジック部
//=================================================================

//--------------------------------
// 設定
//--------------------------------
if (config::FLAG_DEBUG_MSG) {
	// エラー表示(0:OFF 1:ON)
	ini_set('display_errors', 1);
	//error_reporting(E_ERROR);
}

if (config::FLAG_SECURITY) {
	// セキュリティ(クリックジャッキング対策)
	header('X-FRAME-OPTIONS: DENY');
}

//--------------------------------
// パラメータ受信
//--------------------------------
$getArray  = convertSpecialChar($_GET);
$postArray = convertSpecialChar($_POST);

//--------------------------------
// パラメータのエラー処理
//--------------------------------
checkParameterIsSet($getArray['type']);

//--------------------------------
// リスト初期化
//--------------------------------
$listArray = array (
    'user_info'        => array(),
	'fiscal_year_list' => array(),
	'item_list'        => array(),
	'data_list'        => array(),
	'p_prize'          => array()
);

//--------------------------------
// 表示ページを選択
//--------------------------------
$page = selectPageLocalPartnerPrize($getArray, $postArray, $listArray);

/**
 * ----------------------------------------------------------
 * selectPageLocalPartnerPrize()
 * 地元提携表彰状況の表示と設定で表示するページを選択する
 * @param $getArray：GETで送られてきたパラメータ
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function selectPageLocalPartnerPrize($getArray, &$postArray, &$listArray) {

	$page = '';

	// 選択中のメニューに応じた結果を表示
	switch ($getArray['type']) {
		case  'view':
            $page = makeLocalPartnerPrizeViewPage($postArray, $listArray);
			break;
		case  'setting':
            $page = makeLocalPartnerPrizeSettingPage($postArray, $listArray);
			break;
		default:
			break;
	}
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makeLocalPartnerPrizeViewPage()
 * 実績を閲覧するためのページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makeLocalPartnerPrizeViewPage($postArray, &$listArray) {

    $page = "view";

	// ログインユーザ情報を取得
	$listArray['user_info'] = getUserInfo($_SESSION['USERID']);

    // 年度一覧を取得
	$listArray['fiscal_year_list'] = getFiscalYearList();
	if (count($listArray['fiscal_year_list']) == 0) {
		return 'no_data';
    }

	// 保存ボタンが押された時
	if (isset($postArray['save'])) {
		// 地元提携の設定を保存
		if (writePartnerPrizeInfo($postArray, 'LLTB') != 'success') {
			return 'db_write_fail';
		}
		if (writePartnerPrizeInfo($postArray, 'LLTY') != 'success') {
			return 'db_write_fail';
		}
		if (writePartnerPrizeInfo($postArray, 'LLEP') != 'success') {
			return 'db_write_fail';
		}
		if (writePartnerPrizeInfo($postArray, 'LLEG') != 'success') {
			return 'db_write_fail';
		}
		if (writePartnerPrizeInfo($postArray, 'LLLJ') != 'success') {
			return 'db_write_fail';
		}
		if (writePartnerPrizeInfo($postArray, 'LLLO') != 'success') {
			return 'db_write_fail';
		}
	}

	// 設定値読み込み
	if (isset($postArray['show'])) {
		$listArray['p_prize']['LLTB'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLTB');
		$listArray['p_prize']['LLTY'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLTY');
		$listArray['p_prize']['LLEP'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLEP');
		$listArray['p_prize']['LLEG'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLEG');
		$listArray['p_prize']['LLLJ'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLLJ');
		$listArray['p_prize']['LLLO'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLLO');
	}

    return $page;
}

/**
 * ----------------------------------------------------------
 * makeLocalPartnerPrizeSettingPage()
 * 設定するためのページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makeLocalPartnerPrizeSettingPage($postArray, &$listArray) {

	$page = "setting";

	// 種目別の設定値を読み込み
	$listArray['p_prize']['LLTB'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLTB');
	$listArray['p_prize']['LLTY'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLTY');
	$listArray['p_prize']['LLEP'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLEP');
	$listArray['p_prize']['LLEG'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLEG');
	$listArray['p_prize']['LLLJ'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLLJ');
	$listArray['p_prize']['LLLO'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLLO');

	//printArray($listArray['data_list']); // デバッグ用

    return $page;
}

//=================================================================
// デザイン部
//=================================================================

//---------------------------------------------
// コンテンツ内メニュー
//---------------------------------------------
?>

<?php
//echo '<p>GET '; var_dump($getArray).'</p>';
//echo '<p>POST '; var_dump($postArray).'</p>';
?>

<?php
//---------------------------------------------
// 支部表彰得点状況 表示画面
//---------------------------------------------
if ($page === 'view') { ?>

<div id="contents_select">

<form style="display:inline" id='result_view' method="POST" action="index.php?reg=local_partner_prize&type=<?php echo $getArray['type']?>">
	<?php printSelectBox($listArray['fiscal_year_list'], 'fiscal_year', 70, $postArray['fiscal_year'], 'changeSelectBoxContents()') ?>年度
	<?php printSubmitButton('表示', 'show'); ?>
</form>

<?php
// 設定ボタンは管理者・提携のみ
if (($listArray['user_info']['auth'] == config::USER_ADMIN || $listArray['user_info']['auth'] == config::USER_PARTNER) && isset($postArray['show'])) { ?>
	<form style="display:inline" method="POST" action="index.php?reg=local_partner_prize&type=setting">
		<?php printSubmitButton('設定', 'setting'); ?>
		<input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>">
	</form>
<?php } ?>

</div>
<hr>

<div id="contents">
    <?php
	if (isset($postArray['show'])) {
		echo '<p>'.$postArray['fiscal_year'].'年度の地元提携企業からの報奨金の状況を表示しています。</p><br />';
	}
	else {
		echo '<p>地元提携企業からの報奨金状況を表示する年度を選択して「表示」ボタンを押してください。</p><br />';
		exit;
	} ?>

	<?php
	//---------------------------------
	// 【LT】ブリヂストンタイヤジャパン（株）神奈川カンパニー 支援施策
	?>
	<hr><p class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の【LT】ブリヂストンタイヤジャパン（株）神奈川カンパニーの支援施策状況</p>
	<p>計算方法：単価✕実績</p><br />
	<?php printPartnerPrize('LLTB', 'year', $listArray['p_prize']['LLTB'], 'rate', 1); ?>
	<?php printPartnerPrize('LLTB', 'half_period_1', $listArray['p_prize']['LLTB'], 'rate', 1); ?>
	<?php printPartnerPrize('LLTB', 'half_period_1', $listArray['p_prize']['LLTB'], 'rate', 1); ?>
	<?php printPartnerPrize('LLTB', 'summer', $listArray['p_prize']['LLTB'], 'unit', 1); ?>
	<?php printPartnerPrize('LLTB', 'autumn', $listArray['p_prize']['LLTB'], 'unit', 1); ?>
	<?php printPartnerPrize('LLTB', 'spring', $listArray['p_prize']['LLTB'], 'unit', 1); ?>

	<?php
	//---------------------------------
	// 【LT】横浜ゴム（株）神奈川カンパニー 支援施策
	?>
	<hr><p class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の【LT】横浜ゴム（株）神奈川カンパニーの支援施策状況</p>
	<?php printPartnerPrize('LLTY', 'half_period_1', $listArray['p_prize']['LLTY'], 'rate', 1); ?>
	<?php printPartnerPrize('LLTY', 'half_period_1', $listArray['p_prize']['LLTY'], 'rate', 1); ?>
	<?php printPartnerPrize('LLTY', 'summer', $listArray['p_prize']['LLTY'], 'unit', 1); ?>
	<?php printPartnerPrize('LLTY', 'autumn', $listArray['p_prize']['LLTY'], 'unit', 1); ?>
	<?php printPartnerPrize('LLTY', 'spring', $listArray['p_prize']['LLTY'], 'unit', 1); ?>

	<?php
	//---------------------------------
	// 【LE】パナソニック（株）首都圏支社 支援施策
	?>
	<hr><p class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の【LE】パナソニック（株）首都圏支社の支援施策状況</p>
	<?php printPartnerPrize('LLEP', 'summer', $listArray['p_prize']['LLEP'], 'unit', 3); ?>
	<?php printPartnerPrize('LLEP', 'autumn', $listArray['p_prize']['LLEP'], 'unit', 3); ?>
	<?php printPartnerPrize('LLEP', 'spring', $listArray['p_prize']['LLEP'], 'unit', 3); ?>
	<?php
	//---------------------------------
	// 【LE】（株）ジーエス・ユアサ 支援施策
	?>
	<hr><p class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の【LE】（株）ジーエス・ユアサ 支援施策の支援施策状況</p>
	<?php printPartnerPrize('LLEG', 'summer', $listArray['p_prize']['LLEG'], 'unit', 2); ?>
	<?php printPartnerPrize('LLEG', 'autumn', $listArray['p_prize']['LLEG'], 'unit', 2); ?>
	<?php printPartnerPrize('LLEG', 'spring', $listArray['p_prize']['LLEG'], 'unit', 2); ?>
	<?php
	//---------------------------------
	// 【LL】（株）ジャックス 支援施策
	?>
	<hr><p class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の【LL】（株）ジャックスの支援施策状況</p>
	<?php printPartnerPrize('LLLJ', 'summer', $listArray['p_prize']['LLLJ'], 'prize', 4); ?>
	<?php printPartnerPrize('LLLJ', 'autumn', $listArray['p_prize']['LLLJ'], 'prize', 4); ?>
	<?php printPartnerPrize('LLLJ', 'spring', $listArray['p_prize']['LLLJ'], 'prize', 4); ?>
	<?php
	//---------------------------------
	// 【LL】（株）オリエントコーポレーション 支援施策
	?>
	<hr><p class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の【LL】（株）オリエントコーポレーションの支援施策状況</p>
	<?php printPartnerPrize('LLLO', 'summer', $listArray['p_prize']['LLLO'], 'prize', 6); ?>
	<?php printPartnerPrize('LLLO', 'autumn', $listArray['p_prize']['LLLO'], 'prize', 6); ?>
	<?php printPartnerPrize('LLLO', 'spring', $listArray['p_prize']['LLLO'], 'prize', 6); ?>
</div>

<?php }
//---------------------------------------------
// 支部表彰得点状況 設定画面
//---------------------------------------------
elseif ($page === 'setting') { ?>

<div id="contents_select">

<form id='result_view' method="POST" action="index.php?reg=local_partner_prize&type=view">
    <?php printSubmitButton('保存', 'save'); ?>
    <input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>">
    <br /><br />
    <p><?php echo $postArray['fiscal_year'] ?>年度の販促費設定・実績を入力し、「保存」ボタンを押してください。</p>
</div>
<hr>

<div id="contents">
	<?php
	//---------------------------------
	// 【LT】ブリヂストンタイヤジャパン（株）神奈川カンパニー 支援施策
	if ($userInfo['auth'] == config::USER_ADMIN || $userInfo['user'] === config::LTB) { ?>
		<hr><p class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の【LT】ブリヂストンタイヤジャパン（株）神奈川カンパニーの支援施策設定</p><hr>
		<p class="bold">ブリヂストン特別施策の実績と料率を入力してください。</p><p>※項目には分かりやすい説明を自由に入力してください。</p><br />
		<?php printPartnerPrizeSetting('LLTB', 'year', $listArray['p_prize']['LLTB'], 'rate', 1); ?>
		<?php printPartnerPrizeSetting('LLTB', 'half_period_1', $listArray['p_prize']['LLTB'], 'rate', 1); ?>
		<?php printPartnerPrizeSetting('LLTB', 'half_period_2', $listArray['p_prize']['LLTB'], 'rate', 1); ?>
		<?php printPartnerPrizeSetting('LLTB', 'summer', $listArray['p_prize']['LLTB'], 'unit', 1); ?>
		<?php printPartnerPrizeSetting('LLTB', 'autumn', $listArray['p_prize']['LLTB'], 'unit', 1); ?>
		<?php printPartnerPrizeSetting('LLTB', 'spring', $listArray['p_prize']['LLTB'], 'unit', 1); ?>
	<?php }

	//---------------------------------
	// 【LT】横浜ゴム（株）神奈川カンパニー 支援施策
	if ($userInfo['auth'] == config::USER_ADMIN || $userInfo['user'] === config::LTY) { ?>
		<hr><p class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の【LT】横浜ゴム（株）神奈川カンパニーの支援施策設定</p><hr>
		<p class="bold">ブリヂストン特別施策の実績と料率を入力してください。</p><p>※項目には分かりやすい説明を自由に入力してください。</p><br />
		<?php printPartnerPrizeSetting('LLTY', 'half_period_1', $listArray['p_prize']['LLTY'], 'rate', 1); ?>
		<?php printPartnerPrizeSetting('LLTY', 'half_period_2', $listArray['p_prize']['LLTY'], 'rate', 1); ?>
		<?php printPartnerPrizeSetting('LLTY', 'summer', $listArray['p_prize']['LLTY'], 'unit', 1); ?>
		<?php printPartnerPrizeSetting('LLTY', 'autumn', $listArray['p_prize']['LLTY'], 'unit', 1); ?>
		<?php printPartnerPrizeSetting('LLTY', 'spring', $listArray['p_prize']['LLTY'], 'unit', 1); ?>
	<?php }

	//---------------------------------
	// 【LE】パナソニック（株）首都圏支社 支援施策
	if ($userInfo['auth'] == config::USER_ADMIN || $userInfo['user'] === config::LEP) { ?>
		<hr><p class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の【LE】パナソニック（株）首都圏支社の支援施策設定</p><hr>
		<br />
		<?php printPartnerPrizeSetting('LLEP', 'summer', $listArray['p_prize']['LLEP'], 'unit', 3); ?>
		<?php printPartnerPrizeSetting('LLEP', 'autumn', $listArray['p_prize']['LLEP'], 'unit', 3); ?>
		<?php printPartnerPrizeSetting('LLEP', 'spring', $listArray['p_prize']['LLEP'], 'unit', 3); ?>
	<?php }

	//---------------------------------
	// 【LE】（株）ジーエス・ユアサ 支援施策
	if ($userInfo['auth'] == config::USER_ADMIN || $userInfo['user'] === config::LEG) { ?>
		<hr><p class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の【LE】（株）ジーエス・ユアサ 支援施策の支援施策設定</p><hr>
		<br />
		<?php printPartnerPrizeSetting('LLEG', 'summer', $listArray['p_prize']['LLEG'], 'unit', 2); ?>
		<?php printPartnerPrizeSetting('LLEG', 'autumn', $listArray['p_prize']['LLEG'], 'unit', 2); ?>
		<?php printPartnerPrizeSetting('LLEG', 'spring', $listArray['p_prize']['LLEG'], 'unit', 2); ?>
    <?php }
    
	//---------------------------------
	// 【LL】（株）ジャックス 支援施策
	if ($userInfo['auth'] == config::USER_ADMIN || $userInfo['user'] === config::LLJ) { ?>
		<hr><p class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の【LL】（株）ジャックスの支援施策設定</p><hr>
		<br />
		<?php printPartnerPrizeSetting('LLLJ', 'summer', $listArray['p_prize']['LLLJ'], 'prize', 4); ?>
		<?php printPartnerPrizeSetting('LLLJ', 'autumn', $listArray['p_prize']['LLLJ'], 'prize', 4); ?>
		<?php printPartnerPrizeSetting('LLLJ', 'spring', $listArray['p_prize']['LLLJ'], 'prize', 4); ?>
	<?php }

	//---------------------------------
	// 【LL】（株）オリエントコーポレーション 支援施策
	if ($userInfo['auth'] == config::USER_ADMIN || $userInfo['user'] === config::LLO) { ?>
		<hr><p class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の【LL】（株）オリエントコーポレーションの支援施策設定</p><hr>
		<br />
		<?php printPartnerPrizeSetting('LLLO', 'summer', $listArray['p_prize']['LLLO'], 'prize', 6); ?>
		<?php printPartnerPrizeSetting('LLLO', 'autumn', $listArray['p_prize']['LLLO'], 'prize', 6); ?>
		<?php printPartnerPrizeSetting('LLLO', 'spring', $listArray['p_prize']['LLLO'], 'prize', 6); ?>
	<?php } ?>

    </form>
</div>

<?php }
//---------------------------------------------
// エラーページ
//---------------------------------------------
else {
    // エラーメッセージを表示
	printErrorMessage($page);
}
?>

<?php
/**
 * =================================================================
 *  Copyright(c)2017 incloop All Rights Reserved.
 * =================================================================
 */
?>