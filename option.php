<?php
/**
 * =================================================================
 * option.php
 * システム管理用PHPスクリプト
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
// リスト初期化
//--------------------------------
$listArray = array (
	'setting' => array()
);

//--------------------------------
// 表示ページを選択
//--------------------------------
$page = selectPageForSystem($getArray, $postArray, $listArray);

/**
 * ----------------------------------------------------------
 * selectPageForSystem()
 * システム設定で表示するページを選択する
 * @param $getArray：GETで送られてきたパラメータ
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return string $page：表示するページ種別
 * ----------------------------------------------------------
 */
function selectPageForSystem($getArray, $postArray, &$listArray) {

	$page = '';

	// submitした結果に応じて処理を変更
	if (isset($postArray['save'])) {

		// 設定保存
		$ret = setSettingInfo($postArray);
		if ($ret === 'success') {
			
			// 設定情報を読み込み
			$listArray['setting'] = getSettingInfo();
			
			$page = 'readonly';
		}
		else {
			$page = $ret;
		}
	}
	else {

		// 設定情報を読み込み
		$listArray['setting'] = getSettingInfo();
		
		if (isset($postArray['edit'])) {
			$page = 'input';
		}
		else {
			$page = 'readonly';
		}
	}
	
	//echo "<pre>";
	//print_r($listArray['setting']);
	//echo "</pre>";
	
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

<?php
//---------------------------------------------
// 設定入力画面
//---------------------------------------------
if ($page === 'input') { ?>

<div id="contents">
	<p>システム名、種目、地区の設定を行い、「保存」ボタンを押してください。</p><br />
	
	<form method="POST" action="index.php?reg=option">
	<?php printSubmitButton('保存', 'save') ?>
	
	<hr>
	<p>システム名を記入してください。</p>
	<?php printTextBox('system_name', 500, '', $listArray['setting']['system_name']['value1']) ?>
	
	<hr>
	<p>メイン種目名称と単位、補助種目の名称と単位を記入してください。</p>
	<p class="font_red">※[重要]種目追加後は年間計画 → 計画作成において再構成ボタンを押してください。</p>
	<p class="font_red">※[注意]メイン種目と補助種目の追加は問題ありませんが、内容の異なる名称に変更した場合、過去に保存したデータは変更後の種目のデータとして表示されるようになりますので、変更時はご注意ください。</p>

	<table>
	<tr class="bg_wet_asphalt"><td colspan="2">メイン種目</td><td colspan="10">補助種目1</td></tr>
	<tr class="bg_wet_asphalt"><td>種目名称</td><td>単位</td><td>種目名称</td><td>単位</td><td>種目名称</td><td>単位</td><td>種目名称</td><td>単位</td><td>種目名称</td><td>単位</td><td>種目名称</td><td>単位</td></tr>
	
	<?php
	for ($i = 1; $i <= config::ITEM_NUM_MAX; $i++) {
		
		// キーの生成
		$key = 'item_'.$i;
		
		//echo '<p>';
		echo '<tr>';
		echo '<td rowspan="2">';
		printTextBox('item_'.$i.':value1', 100, '', $listArray['setting'][$key]['value1']);
		echo '</td>';
		echo '<td rowspan="2">';
		printTextBox('item_'.$i.':value2', 100, '', $listArray['setting'][$key]['value2']);
		echo '</td>';

		// 補助種目表示 1行目
		for ($sub_no = 1; $sub_no <= 5; $sub_no++) {

			$key_sub = 'sub_'.$i.'_'.$sub_no;

			echo '<td>';
			printTextBox('sub_'.$i.'_'.$sub_no.':value1', 100, '', $listArray['setting'][$key_sub]['value1']);
			echo '</td>';
			echo '<td>';
			printTextBox('sub_'.$i.'_'.$sub_no.':value2', 100, '', $listArray['setting'][$key_sub]['value2']);
			echo '</td>';
		}
		echo '</tr>';

		// 補助種目表示 2行目
		echo '<tr>';
		for ($sub_no = 6; $sub_no <= 10; $sub_no++) {

			$key_sub = 'sub_'.$i.'_'.$sub_no;

			echo '<td>';
			printTextBox('sub_'.$i.'_'.$sub_no.':value1', 100, '', $listArray['setting'][$key_sub]['value1']);
			echo '</td>';
			echo '<td>';
			printTextBox('sub_'.$i.'_'.$sub_no.':value2', 100, '', $listArray['setting'][$key_sub]['value2']);
			echo '</td>';
		}
		echo '<tr>';
	} ?>
	</table>
	
	<br />
	<hr>
	<p>地域名を記入してください。(※1つ以上の地域の入力が必要)</p>
	
	<?php
	if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
		echo '<p class="font_red">※地域名「本部」は削除しないでください。</p>';
	}

	for ($i = 1; $i <= config::AREA_NUM_MAX; $i++) {
		$key = 'area_'.$i;
		echo '<p>';
		printTextBox('area_'.$i.':value1', 100, '', $listArray['setting'][$key]['value1']);
		echo '</p>';
	} ?>
	</form>
	
</div>

<?php }
//---------------------------------------------
// 設定確認画面
//---------------------------------------------
elseif ($page === 'readonly') { ?>

<div id="contents">
	<?php
	if (isset($postArray['save'])) {
		echo '<p>設定を保存しました。設定情報を変更する場合は「編集」ボタンを押してください。</p><br />';
	}
	else {
		echo '<p>設定情報を変更する場合は「編集」ボタンを押してください。</p><br />';
	}
	?>
	
	<form method="POST" action="index.php?reg=option">
	<?php printSubmitButton('編集', 'edit') ?>
	
	<hr>
	<p>[システム名]</p>
	<?php echo $listArray['setting']['system_name']['value1'] ?>
	
	<hr>
	<p>[種目と単位]</p>
	<table>
	<tr class="bg_wet_asphalt"><td colspan="2">メイン種目</td><td colspan="10">補助種目</td></tr>
	<tr class="bg_wet_asphalt"><td>種目名称</td><td>単位</td><td>種目名称</td><td>単位</td><td>種目名称</td><td>単位</td><td>種目名称</td><td>単位</td><td>種目名称</td><td>単位</td><td>種目名称</td><td>単位</td></tr>
	<?php
	for ($i = 1; $i <= config::ITEM_NUM_MAX; $i++) {
		
		// 配列の添字生成
		$key = 'item_'.$i;
		$itemName = $listArray['setting'][$key]['value1'];
		$itemUnit = $listArray['setting'][$key]['value2'];

		// もし空白の場合の処理
		if ($itemName === '') {
			$itemName = '&nbsp';	// メイン種目名に空白を入力
		}
		if ($itemUnit === '') {
			$itemUnit = '&nbsp';	// メイン種目単位に空白を入力
		}

		for ($sub_no = 1; $sub_no <= config::SUBITEM_NUM_MAX; $sub_no++) {
			$key = 'sub_'.$i.'_'.$sub_no;
			$subName[$sub_no] = $listArray['setting'][$key]['value1'];
			$subUnit[$sub_no] = $listArray['setting'][$key]['value2'];

			// もし空白の場合の処理
			if ($subName[$sub_no] === '') {
				$subUnit[$sub_no] = '&nbsp';	// 補助種目1名に空白を入力
			}
			if ($subUnit[$sub_no] === '') {
				$subUnit[$sub_no] = '&nbsp';	// 補助種目1単位に空白を入力
			}
		}
		
		echo '<tr>';
		echo '<td rowspan="2" style="width:100px;">'.$itemName.'</td>';		// メイン種目 種目名
		echo '<td rowspan="2" style="width:100px;">'.$itemUnit.'</td>';		// メイン種目 単位

		for ($sub_no = 1; $sub_no <= 5; $sub_no++) {
			echo '<td style="width:100px;">'.$subName[$sub_no].'</td>';		// 補助種目1  種目名
			echo '<td style="width:100px;">'.$subUnit[$sub_no].'</td>';		// 補助種目1  単位
		}
		echo '</tr>';
		echo '<tr>';
		for ($sub_no = 6; $sub_no <= 10; $sub_no++) {
			echo '<td style="width:100px;">'.$subName[$sub_no].'</td>';		// 補助種目1  種目名
			echo '<td style="width:100px;">'.$subUnit[$sub_no].'</td>';		// 補助種目1  単位
		}
		echo '</tr>';
	} ?>
	</table>

	<hr>
	<p>[地域名]</p>
	<table>
	<tr class="bg_wet_asphalt"><td>地域名称</td></tr>
	<?php
	for ($i = 1; $i <= config::AREA_NUM_MAX; $i++) {
		
		// 配列の添字生成
		$key = 'area_'.$i;
		
		$areaName = $listArray['setting'][$key]['value1'];
		
		if ($areaName === '') {
			$areaName = '&nbsp';	// 地域に空白を入力
		}
		echo '<tr>';
		echo '<td style="width:100px;">'.$areaName.'</td>';
		echo '</tr>';
	} ?>
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
 *  Copyright(c)2013 iSKET All Rights Reserved.
 * =================================================================
 */
?>