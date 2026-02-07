<?php
/**
 * =================================================================
 * msg.php
 * 本部からのメッセージ表示/保存用 PHPスクリプト
 * =================================================================
 */

//=================================================================
// ロジック部
//=================================================================

//--------------------------------
// include
//--------------------------------

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
	'msg' => '',
	'icn' => '',
	'executive_list' => array()
);

//--------------------------------
// 表示ページを選択
//--------------------------------
$page = selectPageForMsg($getArray, $postArray, $listArray);

/**
 * ----------------------------------------------------------
 * selectPageForMsg()
 * メッセージ設定で表示するページを選択する
 * @param $getArray：GETで送られてきたパラメータ
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function selectPageForMsg($getArray, &$postArray, &$listArray) {

	$page = '';

	// 選択中のメニューに応じた結果を表示
	switch ($getArray['type']) {
		case  'setting':
            $page = makeMsgSettingPage($postArray, $listArray);
			break;
		default:
			break;
	}
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makeMsgSettingPage()
 * メッセージを設定するための設定画面
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makeMsgSettingPage($postArray, &$listArray) {

	$page = "setting";

	// 同友一覧を取得
	$listArray['executive_list'] = getExecutiveList();
	if (count($listArray['executive_list']) == 0) {
		return 'no_executive';
	}

    // 保存する時
	if (isset($postArray['save'])) {
		//DBGMSG("makeMsgSettingPage save");
		// データベースに同友向けメッセージを保存
		if (writeMsgforExecutive($postArray) != 'success'){
			return 'db_write_fail';
		}
	}

    // データベースに保存されている全同友向けメッセージを取得
    $msg = getMsgforExecutive();
    if (strlen($msg) > 0) {
        $msgArray = explode('##', $msg);
		$listArray['msg'] = $msgArray[1];
		$listArray['icn'] = $msgArray[2];
    }
    else {
		$listArray['msg'] = "";
		$listArray['icn'] = 1;
	}
	
	// 同友向け個別メッセージを取得
	foreach ($listArray['executive_list'] as $executiveArray) {
		$listArray[$executiveArray['value'].'_msg'] = getEachExecutiveMsg($executiveArray['value']);
	}

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
// メッセージ設定画面
//---------------------------------------------
if ($page === 'setting') { ?>

<div id="contents">
    <p>同友向けに表示するメッセージを入力後、「保存」ボタンを押してください。</p><br />

    <form method="POST" action="index.php?reg=msg&type=<?php echo $getArray['type']?>">
		<?php printSubmitButton('保存', 'save') ?>
		<input type="hidden" name="command" value="msg_save">
		<hr>

		<?php
		//--------------------------------------
		// 全体向けメッセージの表示
		//--------------------------------------
		?>
		<table>
		<tr>
			<td class="bg_wet_asphalt" style="width:700px;">【全同友向け】 お知らせメッセージ</td>
		</tr>
		<tr>
			<td><?php
				printTextAreaBox('msg', 700, 'left', $listArray['msg']);
			?>
			</td>
		</tr>
		</table>
		<br /><p>アイコンを選んでください。</p><br />
		<table>
		<?php
		for ($i = 1; $i < 13; $i++) {
			$checked = 0;
			if ($listArray['icn'] == $i) {
				$checked = 1;
			}

			// 表示
			if ($i == 1 || $i == 7) {
				echo '<tr>';
			}
			echo '<td>';
			printRadioButton('icn', '', $i, $checked);
			echo '<img src="./images/face/'.$i.'.png"><br />';
			echo '</td>';
			if ($i == 6 || $i == 12) {
				echo '</tr>';
			}
		}
		?>
		</table><br /><br />

		<?php
		//--------------------------------------
		// 同友向け個別メッセージの表示
		//--------------------------------------
		?>
		<table>
		<tr>
			<td class="bg_wet_asphalt" style="width:700px;" colspan="3">【個別同友向け】 お知らせメッセージ</td>
		</tr>
		<?php
		foreach ($listArray['executive_list'] as $executiveArray) { ?>
			<tr>
			<td><?php echo $executiveArray['code'] ?></td>
			<td class="left"><?php echo $executiveArray['name'] ?></td>
			<td><?php printTextAreaBox($executiveArray['value'].'_msg', 500, 'left', $listArray[$executiveArray['value'].'_msg']); ?></td>
			</tr>
		<?php } ?>
		</table>
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
 *  Copyright(c)2017 iSKET All Rights Reserved.
 * =================================================================
 */
?>