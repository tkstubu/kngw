<?php
/**
 * =================================================================
 * user.php
 * ユーザ管理用PHPスクリプト
 * =================================================================
 */

//=================================================================
// ロジック部
//=================================================================

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
	'item_list'        => array(),
	'area_list'        => array(),
	'partner_list'     => array(),
	'executive_list'   => array(),
	'exeofficer_list'  => array(),
	'cooperation_list' => array(),
	'adjustment_list'  => array()
);

//--------------------------------
// 表示ページを選択
//--------------------------------
$page = selectPageForUser($getArray, $postArray, $listArray);

/**
 * ----------------------------------------------------------
 * selectPageForUser()
 * ユーザ管理画面で表示するページを選択する
 * @param $getArray：GETで送られてきたパラメータ
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return string $page：表示するページ種別
 * ----------------------------------------------------------
 */
function selectPageForUser($getArray, $postArray, &$listArray) {

	$page = '';

	// 選択中のメニューに応じた結果を表示
	switch ($getArray['type']) {
		case  'top':
			$page = 'top';
			break;
		case  'new':
			$page = makeNewUserPage($postArray, $listArray);
			break;
		case  'plist':
			$page = makePartnerListPage($postArray, $listArray);
			break;
		case  'elist':
			$page = makeExecutiveListPage($postArray, $listArray);
			break;
		case  'eolist':
			$page = makeExecutiveOfficerListPage($postArray, $listArray);
			break;
		case  'alist':
			$page = makeAdjustmentListPage($postArray, $listArray);
			break;
		case  'olist':
			$page = makeCooperationListPage($postArray, $listArray);
			break;
		default:
			break;
	}
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makeNewUserPage()
 * ユーザを新規作成するためのページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return string $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makeNewUserPage($postArray, &$listArray) {
	
	$page = 'new';
	
	if (!isset($postArray['save'])) {

		// 種目一覧を取得
		$listArray['item_list'] = getItemList('no-insert');
		if (count($listArray['item_list']) == 0) {
			return 'no_item';
		}
	
		// 地区一覧を取得
		$listArray['area_list'] = getAreaList();
		if (count($listArray['area_list']) == 0) {
			return 'no_area';
		}
	}
	else {
	
		// 入力チェック
		if (strlen($postArray['name']) == 0 || strlen($postArray['kana']) == 0 ||
			strlen($postArray['user']) == 0 || strlen($postArray['password']) == 0 ||
			strlen($postArray['auth']) == 0 || 
			(strlen($postArray['code']) == 0 && $postArray['auth'] == config::USER_EXECUTIVE)) {
			
			return 'no_input';
		}
		elseif ($postArray['password'] !== $postArray['re_password']) {
			
			return 'unmatch_password';
		}
		else {
		
			// ユーザ存在チェック
			$userInfo = getUserInfo($postArray['user']);

			if ($userInfo !== '') {
				return 'exist_user';
			}
		
			// ユーザ情報を新規追加
			$ret = createUserInfo($postArray);
			if ($ret !== 'success') {
				return 'db_write_fail';
			}
		}
	}
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makePartnerListPage()
 * 提携企業一覧を表示するためのページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return string $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makePartnerListPage($postArray, &$listArray) {
	
	$page = 'plist';
	
	// 退会ボタンを押した場合は退会処理
	if (isset($postArray['exit'])) {
		$ret = exitUserInfo($postArray);
		if ($ret !== 'success') {
			return 'db_write_fail';
		}
	}
	
	// 編集する場合
	else if (isset($postArray['edit'])) {
		// 種目一覧を取得
		$listArray['item_list'] = getItemList('no-insert');
		if (count($listArray['item_list']) == 0) {
			return 'no_item';
		}
		
		// 編集する提携企業情報を取得
		$listArray['user_info'] = getUserInfo($postArray['user'], $postArray['id']);
	}
	
	// 変更完了の場合
	else if (isset($postArray['save'])) {
	
		// 入力チェック
		if (strlen($postArray['name']) == 0 || strlen($postArray['kana']) == 0 ||
			strlen($postArray['user']) == 0 || strlen($postArray['password']) == 0 ||
			strlen($postArray['auth']) == 0 ) {
			
			return 'no_input';
		}
		elseif ($postArray['password'] !== $postArray['re_password']) {
			
			return 'unmatch_password';
		}
		else {
			// ユーザ情報を新規追加
			$ret = updateUserInfo($postArray);
			if ($ret !== 'success') {
				return 'db_write_fail';
			}
		}
	}
	
	// 編集中以外は提携企業一覧を表示
	if (!isset($postArray['edit'])) {
		// 提携企業一覧を取得
		$listArray['partner_list'] = getPartnerList();
		if (count($listArray['partner_list']) == 0) {
			return 'no_partner';
		}
	}
	
	// デバッグ用
	//echo "<pre>";
	//print_r($listArray['user_info']);
	//echo "</pre>";
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makeExecutiveListPage()
 * 同友会社一覧を表示するためのページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return string $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makeExecutiveListPage($postArray, &$listArray) {
	
	$page = 'elist';

	// 退会ボタンを押した場合は退会処理
	if (isset($postArray['exit'])) {
		$ret = exitUserInfo($postArray);
		if ($ret !== 'success') {
			return 'db_write_fail';
		}
	}
	
	// 編集する場合
	else if (isset($postArray['edit'])) {
		// 種目一覧を取得
		$listArray['area_list'] = getAreaList();
		if (count($listArray['area_list']) == 0) {
			return 'no_area';
		}
		
		// 編集するユーザー情報を取得
		$listArray['user_info'] = getUserInfo($postArray['user'], $postArray['id']);
	}
	
	// 変更完了の場合
	else if (isset($postArray['save'])) {
	
		// 入力チェック
		if (strlen($postArray['name']) == 0 || strlen($postArray['kana']) == 0 ||
			strlen($postArray['user']) == 0 || strlen($postArray['password']) == 0 ||
			strlen($postArray['auth']) == 0 || strlen($postArray['code']) == 0) {
			
			return 'no_input';
		}
		elseif ($postArray['password'] !== $postArray['re_password']) {
			
			return 'unmatch_password';
		}
		else {
			// ユーザ情報を新規追加
			$ret = updateUserInfo($postArray);
			if ($ret !== 'success') {
				return 'db_write_fail';
			}
		}
	}
	
	// 編集中以外は同友会社一覧を表示
	if (!isset($postArray['edit'])) {
		// 同友会社一覧を取得
		$listArray['executive_list'] = getExecutiveList();
		if (count($listArray['executive_list']) == 0) {
			return 'no_executive';
		}
	}
	
	// デバッグ用
	//echo "<pre>";
	//print_r($listArray['executive_list']);
	//echo "</pre>";
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makeExecutiveOfficerListPage()
 * 同友役員一覧を表示するためのページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return string $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makeExecutiveOfficerListPage($postArray, &$listArray) {
	
	$page = 'eolist';
	
	// 削除ボタンを押した場合は退会処理
	if (isset($postArray['exit'])) {
		$ret = exitUserInfo($postArray);
		if ($ret !== 'success') {
			return 'db_write_fail';
		}
	}
	
	// 編集する場合
	else if (isset($postArray['edit'])) {
		// 編集するユーザー情報を取得
		$listArray['user_info'] = getUserInfo($postArray['user'], $postArray['id']);
	}
	
	// 変更完了の場合
	else if (isset($postArray['save'])) {
	
		// 入力チェック
		if (strlen($postArray['name']) == 0 || strlen($postArray['kana']) == 0 ||
			strlen($postArray['user']) == 0 || strlen($postArray['password']) == 0 ||
			strlen($postArray['auth']) == 0 ) {
			
			return 'no_input';
		}
		elseif ($postArray['password'] !== $postArray['re_password']) {
			
			return 'unmatch_password';
		}
		else {
			// ユーザ情報を新規追加
			$ret = updateUserInfo($postArray);
			if ($ret !== 'success') {
				return 'db_write_fail';
			}
		}
	}
	
	// 編集中以外は同友役員一覧を表示
	if (!isset($postArray['edit'])) {
		// 調整項目一覧を取得
		$listArray['exeofficer_list'] = getExeOfficerList();
		if (count($listArray['exeofficer_list']) == 0) {
			return 'no_exeofficer';
		}
	}
	
	// デバッグ用
	//printArray($listArray['exeofficer_list']);
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makeAdjustmentListPage()
 * 調整用一覧を表示するためのページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return string $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makeAdjustmentListPage($postArray, &$listArray) {
	
	$page = 'alist';

	// 削除ボタンを押した場合は退会処理
	if (isset($postArray['exit'])) {
		$ret = exitUserInfo($postArray);
		if ($ret !== 'success') {
			return 'db_write_fail';
		}
	}
	
	// 編集する場合
	else if (isset($postArray['edit'])) {
		// 種目一覧を取得
		$listArray['area_list'] = getAreaList();
		if (count($listArray['area_list']) == 0) {
			return 'no_area';
		}
		
		// 編集するユーザー情報を取得
		$listArray['user_info'] = getUserInfo($postArray['user'], $postArray['id']);
	}
	
	// 変更完了の場合
	else if (isset($postArray['save'])) {
	
		// 入力チェック
		if (strlen($postArray['name']) == 0 || strlen($postArray['kana']) == 0 ||
			strlen($postArray['user']) == 0 || strlen($postArray['password']) == 0 ||
			strlen($postArray['auth']) == 0 ) {
			
			return 'no_input';
		}
		elseif ($postArray['password'] !== $postArray['re_password']) {
			
			return 'unmatch_password';
		}
		else {
			// ユーザ情報を新規追加
			$ret = updateUserInfo($postArray);
			if ($ret !== 'success') {
				return 'db_write_fail';
			}
		}
	}
	
	// 編集中以外は調整一覧を表示
	if (!isset($postArray['edit'])) {
		// 調整項目一覧を取得
		$listArray['adjustment_list'] = getAdjustmentList();
		if (count($listArray['adjustment_list']) == 0) {
			return 'no_adjustment';
		}
	}
	
	// デバッグ用
	//echo "<pre>";
	//print_r($listArray['adjustment_list']);
	//echo "</pre>";
	
	return $page;
}


/**
 * ----------------------------------------------------------
 * makeCooperationListPage()
 * 協力会社一覧を表示するためのページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return string $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makeCooperationListPage($postArray, &$listArray) {
	
	$page = 'olist';
	
	// 削除ボタンを押した場合は退会処理
	if (isset($postArray['exit'])) {
		$ret = exitUserInfo($postArray);
		if ($ret !== 'success') {
			return 'db_write_fail';
		}
	}
	
	// 編集する場合
	else if (isset($postArray['edit'])) {
		// 編集するユーザー情報を取得
		$listArray['user_info'] = getUserInfo($postArray['user'], $postArray['id']);
	}
	
	// 変更完了の場合
	else if (isset($postArray['save'])) {
	
		// 入力チェック
		if (strlen($postArray['name']) == 0 || strlen($postArray['kana']) == 0 ||
			strlen($postArray['user']) == 0 || strlen($postArray['password']) == 0 ||
			strlen($postArray['auth']) == 0 ) {
			
			return 'no_input';
		}
		elseif ($postArray['password'] !== $postArray['re_password']) {
			
			return 'unmatch_password';
		}
		else {
			// ユーザ情報を新規追加
			$ret = updateUserInfo($postArray);
			if ($ret !== 'success') {
				return 'db_write_fail';
			}
		}
	}
	
	// 編集中以外は協力企業一覧を表示
	if (!isset($postArray['edit'])) {
		// 調整項目一覧を取得
		$listArray['cooperationList'] = getCooperationList();
		if (count($listArray['cooperationList']) == 0) {
			return 'no_cooperation';
		}
	}
	
	// デバッグ用
	//printArray($listArray['cooperationList']);
	
	return $page;
}

//=================================================================
// デザイン部
//=================================================================
?>

<?php
//echo '<p>GET '; var_dump($getArray).'</p>';
//echo '<p>POST '; var_dump($postArray).'</p>';
?>

<div id="contents_menu">

	<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'new') ?>"
	value="<?php echo '新規追加' ?>"
	onClick="location.href='./index.php?reg=user&type=new'">

	<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'plist') ?>"
	value="<?php echo '提携企業一覧' ?>"
	onClick="location.href='./index.php?reg=user&type=plist'">

	<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'elist') ?>"
	value="<?php echo '同友一覧' ?>"
	onClick="location.href='./index.php?reg=user&type=elist'">

	<?php if (local_config::FEATURE_EXECUTIVE_OFFICER) { ?>
	<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'eolist') ?>"
	value="<?php echo '同友役員一覧' ?>"
	onClick="location.href='./index.php?reg=user&type=eolist'">
	<?php } ?>

	<?php if (local_config::FEATURE_COOPERATION) { ?>
	<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'olist') ?>"
	value="<?php echo '協力会社一覧' ?>"
	onClick="location.href='./index.php?reg=user&type=olist'">
	<?php } ?>

	<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'alist') ?>"
	value="<?php echo '調整項目一覧' ?>"
	onClick="location.href='./index.php?reg=user&type=alist'">

</div>
<hr>

<?php
//---------------------------------------------
// top画面
//---------------------------------------------
if ($page === 'top') { ?>

<div id="contents">
	<p>操作を選択してください。</p>
</div>

<?php }
//---------------------------------------------
// ユーザ作成画面
//---------------------------------------------
elseif ($page === 'new') { ?>

<script type="text/javascript">
	window.onload=clickAuthSelectRadioButton;
</script>

<div id="contents">

	<?php if (!isset($postArray['save'])) { ?>

	<p>必要な情報を入力し、「新規追加」ボタンを押してください。</p>
	<p class="font_red">！！重要！！ 【提携企業】【同友】の新規追加後は「年間計画 → 計画作成」において再構成ボタンを押してください。</p><br />
	
	<form method="POST" action="index.php?reg=user&type=<?php echo $getArray['type']?>">
	<?php printSubmitButton('新規追加', 'save') ?>
	<hr>
	<span>名称</span>
	<?php printTextBox('name', 200, '', ''); ?><br />
	<span>よみがな</span>
	<?php printTextBox('kana', 200, '', ''); ?><br />
	<span>ユーザID(※半角英数)</span>
	<?php printTextBox('user', 200, '', ''); ?><br />
	<span>パスワード(※半角英数)</span>
	<?php printTextBox('password', 200, '', ''); ?><br />
	<span>パスワード確認(※半角英数)</span>
	<?php printTextBox('re_password', 200, '', ''); ?><br />
	<span>種別</span>
	<?php printRadioButton('auth', '提携企業', config::USER_PARTNER, false, 'clickAuthSelectRadioButton(1)') ?>
	<?php printRadioButton('auth', '同友', config::USER_EXECUTIVE, false, 'clickAuthSelectRadioButton(2)') ?>
	<?php if (local_config::FEATURE_EXECUTIVE_OFFICER) {
		printRadioButton('auth', '同友役員', config::USER_EXEOFFICER, false, 'clickAuthSelectRadioButton(4)');	// 同友役員を追加する場合
	} ?>
	<?php if (local_config::FEATURE_COOPERATION) {
		printRadioButton('auth', '協力企業', config::USER_COOPERATION, false, 'clickAuthSelectRadioButton(6)');	// 協力企業を追加する場合
	} ?>
	<?php printRadioButton('auth', '調整', config::USER_ADJUSTMENT, false, 'clickAuthSelectRadioButton(3)') ?><br />

	<div id="partner_item">
		<span>種目</span>
		<?php printSelectBox($listArray['item_list'], 'item', 100) ?><br />
	</div>
	
	<div id="user_area">
		<span>地域</span>
		<?php printSelectBox($listArray['area_list'], 'area', 100) ?><br />
	</div>
	
	<div id="user_info">
		<span>コード</span>
		<?php printTextBox('code', 200, '', '', true); ?><br />
		<span>キャンペーン参加</span>
		<?php printRadioButton('enterable', '参加', '1', false) ?>
		<?php printRadioButton('enterable', '不参加', '0', true) ?><br />
	</div>
	
	</form>
	
	<?php } else { ?>
		<p>ユーザーの追加が完了しました。編集は一覧から行えます。</p>
	<?php }  ?>
</div>

<?php }
//---------------------------------------------
// 提携企業一覧画面
//---------------------------------------------
elseif ($page === 'plist') { ?>

<div id="contents">

	<?php if (isset($postArray['edit'])) { ?>
	
	<p>変更内容を入力し、「編集完了」ボタンを押してください。</p><br />
	
	<form method="POST" action="index.php?reg=user&type=<?php echo $getArray['type']?>">
	<?php printSubmitButton('編集完了', 'save') ?>
	<input type="hidden" name="id" value="<?php echo $listArray['user_info']['id'] ?>">
	<input type="hidden" name="user" value="<?php echo $listArray['user_info']['user'] ?>">
	<input type="hidden" name="auth" value="<?php echo config::USER_PARTNER ?>">
	<hr>
	
	<span>名称</span>
	<?php printTextBox('name', 200, '', $listArray['user_info']['name']); ?><br />
	<span>よみがな</span>
	<?php printTextBox('kana', 200, '', $listArray['user_info']['kana']); ?><br />
	<span>ユーザID</span>
	<?php echo $listArray['user_info']['user'] ?><br />
	<span>パスワード(※半角英数)</span>
	<?php printTextBox('password', 200, '', $listArray['user_info']['password']); ?><br />
	<span>パスワード確認(※半角英数)</span>
	<?php printTextBox('re_password', 200, '', $listArray['user_info']['password']); ?><br />
	<span>種目</span>
	<?php printSelectBox($listArray['item_list'], 'item', 100, $listArray['user_info']['item']) ?><br />
	
	</form>
	
	<?php } else { ?>

	<?php if (isset($postArray['save'])) {
		echo '<p>編集が完了しました。操作を選択してください。</p><br />';
	}
	else {
		echo '<p>提携企業の一覧です。操作を選択してください。</p><br />';
	} ?>

	<table>
	<tr class="bg_wet_asphalt"><td>提携企業名</td><td>種目</td><td colspan="2">操作</td></tr>
	<?php
	foreach ($listArray['partner_list'] as $partnerArray) { ?>
		<tr>
			<td class="left" style="width:200px;"><?php echo $partnerArray['name'] ?></td>
			<td style="width:50px;"><?php echo $partnerArray['item'] ?></td>
			<form method="POST" action="index.php?reg=user&type=<?php echo $getArray['type']?>" onsubmit="return checkMessage('提携企業の情報を編集しますか？');">
				<td><?php printSubmitButton('編集', 'edit') ?></td>
				<input type="hidden" name="id" value="<?php echo $partnerArray['value'] ?>">
				<input type="hidden" name="user" value="<?php echo $partnerArray['user'] ?>">
			</form>
			<form method="POST" action="index.php?reg=user&type=<?php echo $getArray['type']?>" onsubmit="return checkMessage('本当に退会処理を行いますか？');">
				<td><?php printSubmitButton('退会', 'exit') ?></td>
				<input type="hidden" name="id" value="<?php echo $partnerArray['value'] ?>">
				<input type="hidden" name="user" value="<?php echo $partnerArray['user'] ?>">
			</form>
		</tr>
	<?php } ?>
	</table>
	
	<?php }  ?>
</div>

<?php }
//---------------------------------------------
// 同友会社一覧画面
//---------------------------------------------
elseif ($page === 'elist') { ?>

<div id="contents">

	<?php if (isset($postArray['edit'])) { ?>
	
	<p>変更内容を入力し、「編集完了」ボタンを押してください。</p><br />
	<p class="font_red">[キャンペーン分母同友を変更した場合の動作]</p>
	<p class="font_red">※キャンペーン中の場合：現在のキャンペーンと今後のキャンペーンに対して変更を行います。</p>
	<p class="font_red">※キャンペーン中でない場合：今後のキャンペーンに対して変更を行います。</p>
	<p class="font_red">※既に終了したキャンペーンに対して同友の分母状態を変更する場合は、<br />【キャンペーン】→【計画作成】→【編集】<br />から設定してください。</p><br />
	
	<form method="POST" action="index.php?reg=user&type=<?php echo $getArray['type']?>">
	<?php printSubmitButton('編集完了', 'save') ?>
	<input type="hidden" name="id" value="<?php echo $listArray['user_info']['id'] ?>">
	<input type="hidden" name="user" value="<?php echo $listArray['user_info']['user'] ?>">
	<input type="hidden" name="auth" value="<?php echo config::USER_EXECUTIVE ?>">
	<hr>
	
	<span>名称</span>
	<?php printTextBox('name', 200, '', $listArray['user_info']['name']); ?><br />
	<span>よみがな</span>
	<?php printTextBox('kana', 200, '', $listArray['user_info']['kana']); ?><br />
	<span>ユーザID</span>
	<?php echo $listArray['user_info']['user'] ?><br />
	<span>パスワード(※半角英数)</span>
	<?php printTextBox('password', 200, '', $listArray['user_info']['password']); ?><br />
	<span>パスワード確認(※半角英数)</span>
	<?php printTextBox('re_password', 200, '', $listArray['user_info']['password']); ?><br />
	<span>地域</span>
	<?php printSelectBox($listArray['area_list'], 'area', 100, $listArray['user_info']['area']) ?><br />
	<span>コード</span>
	<?php printTextBox('code', 100, '', $listArray['user_info']['code']); ?><br />
	<span>キャンペーン分母同友</span>
	<?php if($listArray['user_info']['enterable'] == config::STATUS_ENTERABLE) {
		printRadioButton('enterable', '分母', '1', true);
		printRadioButton('enterable', '分母外', '0', false);
		printRadioButton('enterable', '休会', '2', false);
	}
	elseif($listArray['user_info']['enterable'] == config::STATUS_UNENTERABLE) {
		printRadioButton('enterable', '分母', '1', false);
		printRadioButton('enterable', '分母外', '0', true);
		printRadioButton('enterable', '休会', '2', false);
	}
	else {
		printRadioButton('enterable', '分母', '1', false);
		printRadioButton('enterable', '分母外', '0', false);
		printRadioButton('enterable', '休会', '2', true);
	} ?>
	</form>
	
	<?php } else { ?>

	<?php if (isset($postArray['save'])) {
		echo '<p>編集が完了しました。操作を選択してください。</p><br />';
	}
	else {
		echo '<p>同友の一覧です。操作を選択してください。</p><br />';
	} ?>

	<table>
	<tr class="bg_wet_asphalt">
		<td style="width:50px;">コード</td>
		<td style="width:200px;">名称</td>
		<td style="width:100px;">地域</td>
		<td style="width:100px;">キャンペーン</td>
		<td colspan="2">操作</td>
		<?php if (local_config::FEATURE_SAVE_LOGIN_HISTORY) { ?>
		<td style="width:180px;">最終ログイン時間</td>
		<?php } ?>
	</tr>
	<?php
	foreach ($listArray['executive_list'] as $executiveArray) { ?>
		<tr>
			<td><?php echo $executiveArray['code'] ?></td>
			<td class="left"><?php echo $executiveArray['name'] ?></td>
			<td><?php echo $executiveArray['area'] ?></td>
			<?php
			if($executiveArray['enterable'] == config::STATUS_ENTERABLE) {
				$enterable = '参加';
			}
			elseif($executiveArray['enterable'] == config::STATUS_UNENTERABLE) {
				$enterable = '不参加';
			}
			else {
				$enterable = '休会';
			} ?>
			<td><?php echo $enterable ?></td>
			
			<form method="POST" action="index.php?reg=user&type=<?php echo $getArray['type']?>" onsubmit="return checkMessage('同友の情報を編集しますか？');">
				<td><?php printSubmitButton('編集', 'edit') ?></td>
				<input type="hidden" name="id" value="<?php echo $executiveArray['value'] ?>">
				<input type="hidden" name="user" value="<?php echo $executiveArray['user'] ?>">
			</form>
			<form method="POST" action="index.php?reg=user&type=<?php echo $getArray['type']?>" onsubmit="return checkMessage('本当に退会処理を行いますか？');">
				<td><?php printSubmitButton('退会', 'exit') ?></td>
				<input type="hidden" name="id" value="<?php echo $executiveArray['value'] ?>">
				<input type="hidden" name="user" value="<?php echo $executiveArray['user'] ?>">
			</form>

			<?php if (local_config::FEATURE_SAVE_LOGIN_HISTORY) { ?>
			<?php $loginHistoryArray = getLoginHistory($executiveArray['value']); ?>
			<td>
			<?php
				echo $loginHistoryArray[0];
				// 配列を全部連結
				$param = "";
				$cnt = 0;
				foreach($loginHistoryArray as $daytime){
					if ($cnt == 0) {
						$param = "ls".$cnt."=".$daytime;
					}
					else{
						$param = $param ."&ls".$cnt."=". $daytime;
					}
					$cnt++;
				}
			?>
			<a href="javascript:void(0);" onclick='openLoginHistory("<?php echo $executiveArray['name'] ?>", "<?php echo $param; ?>")'>[履歴]</a>
			</td>
			<?php } ?>
		</tr>
	<?php } ?>
	</table>
	
	<?php }  ?>
</div>

<?php }
//---------------------------------------------
// 同友役員一覧画面
//---------------------------------------------
elseif ($page === 'eolist') { ?>

<div id="contents">

	<?php if (isset($postArray['edit'])) { ?>
	
	<p>変更内容を入力し、「編集完了」ボタンを押してください。</p>
	
	<form method="POST" action="index.php?reg=user&type=<?php echo $getArray['type']?>">
	<?php printSubmitButton('編集完了', 'save') ?>
	<input type="hidden" name="id" value="<?php echo $listArray['user_info']['id'] ?>">
	<input type="hidden" name="user" value="<?php echo $listArray['user_info']['user'] ?>">
	<input type="hidden" name="auth" value="<?php echo config::USER_EXEOFFICER ?>">
	<hr>
	
	<span>名称</span>
	<?php printTextBox('name', 200, '', $listArray['user_info']['name']); ?><br />
	<span>よみがな</span>
	<?php printTextBox('kana', 200, '', $listArray['user_info']['kana']); ?><br />
	<span>ユーザID</span>
	<?php echo $listArray['user_info']['user'] ?><br />
	<span>パスワード(※半角英数)</span>
	<?php printTextBox('password', 200, '', $listArray['user_info']['password']); ?><br />
	<span>パスワード確認(※半角英数)</span>
	<?php printTextBox('re_password', 200, '', $listArray['user_info']['password']); ?><br />
	</form>
	
	<?php } else { ?>

	<?php if (isset($postArray['save'])) {
		echo '<p>編集が完了しました。操作を選択してください。</p><br />';
	}
	else {
		echo '<p>同友役員の一覧です。操作を選択してください。</p><br />';
	} ?>

	<table>
	<tr class="bg_wet_asphalt">
		<td style="width:200px;">名称</td>
		<td colspan="2">操作</td>
	</tr>
	<?php
	foreach ($listArray['exeofficer_list'] as $executiveArray) { ?>
		<tr>
			<td class="left"><?php echo $executiveArray['name'] ?></td>
			
			<form method="POST" action="index.php?reg=user&type=<?php echo $getArray['type']?>" onsubmit="return checkMessage('同友役員の情報を編集しますか？');">
				<td><?php printSubmitButton('編集', 'edit') ?></td>
				<input type="hidden" name="id" value="<?php echo $executiveArray['value'] ?>">
				<input type="hidden" name="user" value="<?php echo $executiveArray['user'] ?>">
			</form>
			<form method="POST" action="index.php?reg=user&type=<?php echo $getArray['type']?>" onsubmit="return checkMessage('本当に削除処理を行いますか？');">
				<td><?php printSubmitButton('削除', 'exit') ?></td>
				<input type="hidden" name="id" value="<?php echo $executiveArray['value'] ?>">
				<input type="hidden" name="user" value="<?php echo $executiveArray['user'] ?>">
			</form>
		</tr>
	<?php } ?>
	</table>
	
	<?php }  ?>
</div>

<?php }
//---------------------------------------------
// 協力企業一覧画面
//---------------------------------------------
elseif ($page === 'olist') { ?>

<div id="contents">

	<?php if (isset($postArray['edit'])) { ?>
	
	<p>変更内容を入力し、「編集完了」ボタンを押してください。</p>
	
	<form method="POST" action="index.php?reg=user&type=<?php echo $getArray['type']?>">
	<?php printSubmitButton('編集完了', 'save') ?>
	<input type="hidden" name="id" value="<?php echo $listArray['user_info']['id'] ?>">
	<input type="hidden" name="user" value="<?php echo $listArray['user_info']['user'] ?>">
	<input type="hidden" name="auth" value="<?php echo config::USER_EXEOFFICER ?>">
	<hr>
	
	<span>名称</span>
	<?php printTextBox('name', 200, '', $listArray['user_info']['name']); ?><br />
	<span>よみがな</span>
	<?php printTextBox('kana', 200, '', $listArray['user_info']['kana']); ?><br />
	<span>ユーザID</span>
	<?php echo $listArray['user_info']['user'] ?><br />
	<span>パスワード(※半角英数)</span>
	<?php printTextBox('password', 200, '', $listArray['user_info']['password']); ?><br />
	<span>パスワード確認(※半角英数)</span>
	<?php printTextBox('re_password', 200, '', $listArray['user_info']['password']); ?><br />
	</form>
	
	<?php } else { ?>

	<?php if (isset($postArray['save'])) {
		echo '<p>編集が完了しました。操作を選択してください。</p><br />';
	}
	else {
		echo '<p>協力企業の一覧です。操作を選択してください。</p><br />';
	} ?>

	<table>
	<tr class="bg_wet_asphalt">
		<td style="width:200px;">名称</td>
		<td colspan="2">操作</td>
	</tr>
	<?php
	foreach ($listArray['cooperationList'] as $cooperationArray) { ?>
		<tr>
			<td class="left"><?php echo $cooperationArray['name'] ?></td>
			
			<form method="POST" action="index.php?reg=user&type=<?php echo $getArray['type']?>" onsubmit="return checkMessage('協力企業の情報を編集しますか？');">
				<td><?php printSubmitButton('編集', 'edit') ?></td>
				<input type="hidden" name="id" value="<?php echo $cooperationArray['value'] ?>">
				<input type="hidden" name="user" value="<?php echo $cooperationArray['user'] ?>">
			</form>
			<form method="POST" action="index.php?reg=user&type=<?php echo $getArray['type']?>" onsubmit="return checkMessage('本当に削除処理を行いますか？');">
				<td><?php printSubmitButton('削除', 'exit') ?></td>
				<input type="hidden" name="id" value="<?php echo $cooperationArray['value'] ?>">
				<input type="hidden" name="user" value="<?php echo $cooperationArray['user'] ?>">
			</form>
		</tr>
	<?php } ?>
	</table>
	
	<?php }  ?>
</div>

<?php }
//---------------------------------------------
// 調整項目一覧画面
//---------------------------------------------
elseif ($page === 'alist') { ?>

<div id="contents">

	<?php if (isset($postArray['edit'])) { ?>
	
	<p>変更内容を入力し、「編集完了」ボタンを押してください。</p>
	
	<form method="POST" action="index.php?reg=user&type=<?php echo $getArray['type']?>">
	<?php printSubmitButton('編集完了', 'save') ?>
	<input type="hidden" name="id" value="<?php echo $listArray['user_info']['id'] ?>">
	<input type="hidden" name="user" value="<?php echo $listArray['user_info']['user'] ?>">
	<input type="hidden" name="auth" value="<?php echo config::USER_ADJUSTMENT ?>">
	<input type="hidden" name="enterable" value="<?php echo config::STATUS_UNENTERABLE ?>">
	<hr>
	
	<span>名称</span>
	<?php printTextBox('name', 200, '', $listArray['user_info']['name']); ?><br />
	<span>よみがな</span>
	<?php printTextBox('kana', 200, '', $listArray['user_info']['kana']); ?><br />
	<span>ユーザID</span>
	<?php echo $listArray['user_info']['user'] ?><br />
	<span>パスワード(※半角英数)</span>
	<?php printTextBox('password', 200, '', $listArray['user_info']['password']); ?><br />
	<span>パスワード確認(※半角英数)</span>
	<?php printTextBox('re_password', 200, '', $listArray['user_info']['password']); ?><br />
	<span>地域</span>
	<?php printSelectBox($listArray['area_list'], 'area', 100, $listArray['user_info']['area']) ?><br />
	</form>
	
	<?php } else { ?>

	<?php if (isset($postArray['save'])) {
		echo '<p>編集が完了しました。操作を選択してください。</p><br />';
	}
	else {
		echo '<p>調整項目の一覧です。操作を選択してください。</p><br />';
	} ?>

	<table>
	<tr class="bg_wet_asphalt">
		<td style="width:200px;">名称</td>
		<td style="width:100px;">地域</td>
		<td colspan="2">操作</td>
	</tr>
	<?php
	foreach ($listArray['adjustment_list'] as $executiveArray) { ?>
		<tr>
			<td class="left"><?php echo $executiveArray['name'] ?></td>
			<td><?php echo $executiveArray['area'] ?></td>
			
			<form method="POST" action="index.php?reg=user&type=<?php echo $getArray['type']?>" onsubmit="return checkMessage('調整項目の情報を編集しますか？');">
				<td><?php printSubmitButton('編集', 'edit') ?></td>
				<input type="hidden" name="id" value="<?php echo $executiveArray['value'] ?>">
				<input type="hidden" name="user" value="<?php echo $executiveArray['user'] ?>">
			</form>
			<form method="POST" action="index.php?reg=user&type=<?php echo $getArray['type']?>" onsubmit="return checkMessage('本当に削除処理を行いますか？');">
				<td><?php printSubmitButton('削除', 'exit') ?></td>
				<input type="hidden" name="id" value="<?php echo $executiveArray['value'] ?>">
				<input type="hidden" name="user" value="<?php echo $executiveArray['user'] ?>">
			</form>
		</tr>
	<?php } ?>
	</table>
	
	<?php }  ?>
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
 *  Copyright(c)2013 iSKET All Rights Reserved.
 * =================================================================
 */
?>