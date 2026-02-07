<?php
/**
 * =================================================================
 * point_status.php
 * 支部表彰得点状況の表示と設定用 PHPスクリプト
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
	'fiscal_year_list' => array(),
	'item_list'        => array(),
	'data_list'        => array(),
	'executive_list'   => array(),
	'lc_hold_number'   => array(),
	'point_status'     => array()
);

//--------------------------------
// 表示ページを選択
//--------------------------------
$page = selectPageForPointStatus($getArray, $postArray, $listArray);

/**
 * ----------------------------------------------------------
 * selectPageForPointStatus()
 * 支部表彰得点状況の表示と設定で表示するページを選択する
 * @param $getArray：GETで送られてきたパラメータ
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function selectPageForPointStatus($getArray, &$postArray, &$listArray) {

	$page = '';

	// 選択中のメニューに応じた結果を表示
	switch ($getArray['type']) {
		case  'view':
            $page = makePointStatusViewPage($postArray, $listArray);
			break;
		case  'setting':
            $page = makePointStatusSettingPage($postArray, $listArray);
			break;
		default:
			break;
	}
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makePointStatusViewPage()
 * 実績を閲覧するためのページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makePointStatusViewPage($postArray, &$listArray) {

	$page = "view";

	// ログインユーザ情報を取得
	$listArray['user_info'] = getUserInfo($_SESSION['USERID']);

	// 年度一覧を取得
	$listArray['fiscal_year_list'] = getFiscalYearList();
	if (count($listArray['fiscal_year_list']) == 0) {
		return 'no_data';
	}

	// 種目一覧を取得
	$listArray['item_list'] = getItemList();
	if (count($listArray['item_list']) == 0) {
		return 'no_item';
	}

	// 販促費では使用しない種目(LC、LEB)を削除
	foreach ($listArray['item_list'] as $key => $itemListArray) {
		if ($itemListArray['value'] === 'ALL' ||  $itemListArray['value'] === 'LC' || $itemListArray['value'] === 'LEB') {
			unset($listArray['item_list'][$key]);
		}
	}

	// 同友一覧を取得
	if (isset($postArray['fiscal_year'])) {
		$listArray['executive_list'] = getExecutiveList($postArray['fiscal_year'], 'campaign');
		if (count($listArray['executive_list']) == 0) {
			return 'no_executive';
		}
	}

    // 保存する時
	if (isset($postArray['save'])) {
		//DBGMSG("makePointStatusViewPage save");
		// データベースに支部表彰得点状況を保存
		if (writePointStatusInfo($postArray) != 'success'){
			return 'db_write_fail';
		}
	}

	// 表示する時
	if (isset($postArray['show'])) {

		// 種目毎の情報取得
		foreach ($listArray['item_list'] as $itemArray) {
			// 種目毎の月別の計画値と実績を取得。地域は%を指定することで全地域を指定
			$listArray['data_list'][$itemArray['value']] = getResultAndPlanByItem($postArray['fiscal_year'], $itemArray['value'], 'TOTAL', '%', true);
			//printArray($listArray['data_list'][$itemArray['value']]);
		}
		// LC保有枚数を取得
		$listArray['data_list'] += getLCHoldNumber($postArray['fiscal_year']);

		// デバッグ用
		//printArray($listArray['data_list']);

		// 手動入力している得点の読み込み
		$listArray['point_status'] = getPointStatusInfo($postArray['fiscal_year']);
		if (count($listArray['point_status']) == 0) {
			return 'no_data';
		}

		// 支部への販促費「LC保有目標達成賞金」の設定値を取得する
		$listArray['lc_hold_number'] = getLCHoldPromotionInfo($postArray['fiscal_year']);
		if (count($listArray['lc_hold_number']) == 0) {
			return 'no_data';
		}
	}

	return $page;
}

/**
 * ----------------------------------------------------------
 * makeMsgSettingPage()
 * 設定画面を表示するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makePointStatusSettingPage($postArray, &$listArray) {

    $page = "setting";

	// 設定値読み込み
	$listArray['point_status'] = getPointStatusInfo($postArray['fiscal_year']);
	if (count($listArray['point_status']) == 0) {
		return 'no_data';
	}
	//printArray($listArray['point_status']);

    return $page;
}

/**
 * ----------------------------------------------------------
 * printExcutiveWorkingAndMintargetPointTable()
 * 全同友稼働数、最低販売基準同友数に関連する得点を表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
function printExcutiveWorkingAndMintargetPointTable($postArray, &$listArray) {

	$listArray['point_status']['other'] = 0;
	$executiveData = array();
	$item_list = $listArray['item_list'];
	$e_count = 0;
	$e_item_overzero_count = array();
	$e_item_min_count = array();

	// 同友最低販売基準を取得
	$min_target = getExecutiveMinTargetInfo($postArray['fiscal_year']);
	//printArray($mintarget);

	//-------------------------------
	// 同友毎の販売数を取得
	foreach ($listArray['executive_list'] as $executiveArray) {
		// キャンペーン参加同友のみ実績を取得(期末分母同友数をカウントするので、春キャンだけ見ればOK)
		//if($executiveArray['enterable'] != config::STATUS_ENTERABLE) {
		if($executiveArray['spring_enterable'] != config::STATUS_ENTERABLE) {
			continue;
		}

		// 初期化
		$resultTotalList = array();
		$data = array();

		// 種目ごとの合計値を取得
		foreach ($listArray['item_list']  as $itemArray) {
			$data = getExecutiveResultTotalValue($postArray['fiscal_year'], $itemArray['value'], '%', 'TOTAL', $executiveArray['value']);
			//printArray($data);
			
			// 初期化
			$resultTotal = 0;
			
			// 12ヶ月分を加算
			for ($i = 1; $i < 13; $i++) {
				$resultTotal += $data[0][$i.'_result'];
			}
			
			// 種目ごとに合計値を保存
			$resultTotalList += array($itemArray['value'] => $resultTotal);
		}

		// 2015年以降はLM+LSを作り、LM、LSを削除
		if ($postArray['fiscal_year'] >= config::CAMPAIGN_LMLS_ADD_YEAR) {
			$resultTotalList['LM+LS'] = $resultTotalList['LM'] + $resultTotalList['LS'];
		}

		$executiveData[$executiveArray['value']] = $resultTotalList;
	}
	//printArray($executiveData);

	// 種目にLM+LSを追加
	array_unshift($item_list, array('value'=>'LM+LS',  'name'=>'LM+LS', 'unit'=>'台'));
	// 種目からLM、LSを削除
	$i = 0;
	foreach ($item_list as $itemArray) {
		if ($itemArray['value'] === 'ALL' || $itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
			unset($item_list[$i]);
		}
		$i++;
	}

	//-------------------------------
	// 全同友稼働数、最低販売基準同友数のカウント
	foreach ($listArray['executive_list'] as $executiveArray) {
		// キャンペーン参加同友のみ実績を取得(期末分母同友数をカウントするので、春キャンだけ見ればOK)
		//if($executiveArray['enterable'] != config::STATUS_ENTERABLE) {
		if($executiveArray['spring_enterable'] != config::STATUS_ENTERABLE) {
			continue;
		}
		
		// 種目毎に確認
		foreach ($item_list  as $itemArray) {
			// 種目毎に実績のある同友をカウント
			if ($executiveData[$executiveArray['value']][$itemArray['value']] > 0) {
				$e_item_overzero_count[$itemArray['value']]++;
			}
			// 種目毎に最低販売基準同友数をクリアしている同友をカウント
			if ($executiveData[$executiveArray['value']][$itemArray['value']] >= $min_target[$itemArray['value']]) {
				$e_item_min_count[$itemArray['value']]++;
			}

		}
		$e_count++;	// 支部販売基準同友数のカウント
	}

	// 2019年度から修正
	// 全同友稼働は最低販売基準達成に統合して、最低販売基準達成の得点を20点→30点に変更
	if ($postArray['fiscal_year'] < 2019) {
		$lowest_sell_reach_point = 20;	// 最低販売基準達成の得点(2018年以前)
	}
	else {
		$lowest_sell_reach_point = 30;	// 最低販売基準達成の得点(2019年以降)
	}

	// 全同友稼働の表示
	if ($postArray['fiscal_year'] < 2019) {

	echo '<p class="bold">＜全同友稼働＞</p>';
	echo '
	<table>
		<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>';
	foreach ($item_list  as $itemArray) {
		echo '<tr><td class="left">'.$itemArray['value'].'</td><td>10</td>';
		$point = floor(($e_item_overzero_count[$itemArray['value']] / $e_count)*10);	// 稼働同友数
		$listArray['point_status']['other'] += $point;									// 法人事業合計に加算
		echo '<td class="right">'.$point.'</td></tr>';
	}
	echo '</table>';
	echo '<br />';

	}	// 2019

	echo '<p class="bold">＜最低販売基準達成＞</p>';
	echo '
	<table>
		<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>';
	foreach ($item_list  as $itemArray) {
		echo '<tr><td class="left">'.$itemArray['value'].'</td><td>'.$lowest_sell_reach_point.'</td>';
		$point = floor(($e_item_min_count[$itemArray['value']] / $e_count)*$lowest_sell_reach_point);	// 最低販売基準達成同友数
		//$point = (($e_item_min_count[$itemArray['value']] / $e_count)*$lowest_sell_reach_point);	// 最低販売基準達成同友数
		$listArray['point_status']['other'] += $point;									// 法人事業合計に加算
		echo '<td class="right">'.$point.'</td></tr>';
		//echo '<td class="right">'.$e_item_min_count[$itemArray['value']].' '.$e_count.' '.$point.'</td></tr>';
	}
	echo '</table>';
}

/**
 * ----------------------------------------------------------
 * printExcutiveCampaignPointTable()
 * キャペーンに関連する得点を表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
function printExcutiveCampaignPointTable($postArray, &$listArray) {

	$item_list = $listArray['item_list'];
	$score = array();

	// 種目にLM+LSを追加
	array_unshift($item_list, array('value'=>'LM+LS',  'name'=>'LM+LS', 'unit'=>'台'));
	// 種目からLM、LSを削除
	$i = 0;
	foreach ($item_list as $itemArray) {
		if ($itemArray['value'] === 'ALL' || $itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
			unset($item_list[$i]);
		}
		$i++;
	}

	//-------------------------------
	// 夏のキャンペーン
	foreach ($item_list as $itemArray) {
		$campaignPoint = getCalcCampeignPoint($postArray['fiscal_year'], 'summer', $itemArray['value']);
		$score['summer'][$itemArray['value']] = getCalcCampeignBonusPoint($campaignPoint);
	}

	//-------------------------------
	// 秋のキャンペーン
	foreach ($item_list as $itemArray) {
		$campaignPoint = getCalcCampeignPoint($postArray['fiscal_year'], 'autumn', $itemArray['value']);
		$score['autumn'][$itemArray['value']] = getCalcCampeignBonusPoint($campaignPoint);
	}

	//-------------------------------
	// 春のキャンペーン
	foreach ($item_list as $itemArray) {
		$campaignPoint = getCalcCampeignPoint($postArray['fiscal_year'], 'spring', $itemArray['value']);
		$score['spring'][$itemArray['value']] = getCalcCampeignBonusPoint($campaignPoint);
	}
	//printArray($score);

	echo '<br />';
	echo '<p class="bold">＜サマーキャンペーン＞</p>
	<table>
		<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>';
	foreach ($item_list as $itemArray) {
		echo '<tr><td class="left">'.$itemArray['value'].'</td><td>10</td><td class="right">'.$score['summer'][$itemArray['value']].'</td></tr>';
		$listArray['point_status']['other'] += (int)$score['summer'][$itemArray['value']];	// 法人事業合計に加算
	}
	echo '</table>';

	echo '<br />';
	echo '<p class="bold">＜秋のキャンペーン＞</p>
	<table>
		<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>';
	foreach ($item_list as $itemArray) {
		echo '<tr><td class="left">'.$itemArray['value'].'</td><td>10</td><td class="right">'.$score['autumn'][$itemArray['value']].'</td></tr>';
		$listArray['point_status']['other'] += (int)$score['autumn'][$itemArray['value']];	// 法人事業合計に加算
	}
	echo '</table>';

	echo '<br />';
	echo '<p class="bold">＜春のキャンペーン＞</p>
	<table>
		<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>';
	foreach ($item_list as $itemArray) {
		echo '<tr><td class="left">'.$itemArray['value'].'</td><td>10</td><td class="right">'.$score['spring'][$itemArray['value']].'</td></tr>';
		$listArray['point_status']['other'] += (int)$score['spring'][$itemArray['value']];	// 法人事業合計に加算
	}
	echo '</table>';
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

// 年度によって格納場所がズレるのを調整するためのインデックス処理
$start_index_num = 18;
if ($postArray['fiscal_year'] >= 2025) {
	$start_index_num = 14;
}
else if ($postArray['fiscal_year'] >= 2023) {
	$start_index_num = 20;
}
else if ($postArray['fiscal_year'] >= 2022) {
	$start_index_num = 19;
}

if ($page === 'view') { ?>

<?php
	// 得点合計計算用
	$d_point = 0;
	$e_point = 0;
	$b_point = 0;
?>

<div id="contents_select">

	<form style="display:inline" id='result_view' method="POST" action="index.php?reg=point_status&type=view">
		<?php printSelectBox($listArray['fiscal_year_list'], 'fiscal_year', 70, $postArray['fiscal_year'], 'changeSelectBoxContents()') ?>年度
		<?php printSubmitButton('表示', 'show') ?>
	</form>

	<?php
	// グラフ編集、追加ボタンは管理者のみ
	if ($listArray['user_info']['auth'] == config::USER_ADMIN && isset($postArray['show'])) { ?>
		<form style="display:inline" method="POST" action="index.php?reg=point_status&type=setting">
			<?php printSubmitButton('修正') ?>
			<input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>">
		</form>
	<?php } ?>
</div>
<hr>

<div id="contents">
	<?php
	if (isset($postArray['show'])) {
		echo '<p>'.$postArray['fiscal_year'].'年度の支部表彰得点状況を表示しています。</p><br />';
		echo '<p>※同友会事業部門優績支部は [ 850 ] 点以上が目標</p>';
		echo '<p>※法人事業部門優績支部は [ 850 ] 点以上が目標</p>';
	}
	else {
		echo '<p>支部表彰得点状況を表示する年度を選択して「表示」ボタンを押してください。</p><br />';
		exit;
	} ?>

	<table id='total_table'>
		<?php if ($postArray['fiscal_year'] >= 2025) { ?>
			<tr class="bg_alizarin"><td width="100px">同友拡充部門得点合計</td><td width="100px">同友会事業部門得点合計</td><td width="100px">法人事業部門得点合計</td><td width="100px">総合計得点</td></tr>
			<tr><td>0</td><td>0</td><td>0</td><td>0</td></tr>
		<?php } else { ?>
			<tr class="bg_alizarin"><td width="100px">同友会事業合計</td><td width="100px">法人事業合計</td><td width="100px">総合計</td></tr>
			<tr><td>0</td><td>0</td><td>0</td></tr>
		<?php } ?>
	</table>
	<br />
	<table>
		<?php if ($postArray['fiscal_year'] >= 2025) { ?>
			<tr class="bg_wet_asphalt"><td width="100px">同友拡充部門順位</td><td width="100px">同友会事業部門順位</td><td width="100px">法人事業部門順位</td><td width="100px">総合順位</td></tr>
		<?php } else { ?>
			<tr class="bg_wet_asphalt"><td width="100px">同友会順位</td><td width="100px">法人事業順位</td><td width="100px">総合順位</td></tr>
		<?php } ?>
		<tr>
		<?php 
		// 2022年：同友会事業部門の内容が変更
		// 2021年：生命共済と団体総合補償保険に計画と実績を入れて得点は計算で算出変更
		if ($postArray['fiscal_year'] >= 2025) { ?>
			<td><?php echo $listArray['point_status'][$start_index_num+9]; ?> 位</td>
			<td><?php echo $listArray['point_status'][$start_index_num+10]; ?> 位</td>
			<td><?php echo $listArray['point_status'][$start_index_num+11]; ?> 位</td>
			<td><?php echo $listArray['point_status'][$start_index_num+12]; ?> 位</td>
		<?php } else if ($postArray['fiscal_year'] >= 2021) { ?>
			<td><?php echo $listArray['point_status'][$start_index_num+9]; ?> 位</td>
			<td><?php echo $listArray['point_status'][$start_index_num+10]; ?> 位</td>
			<td><?php echo $listArray['point_status'][$start_index_num+11]; ?> 位</td>
		<?php } else { ?>
			<td><?php echo $listArray['point_status'][25]; ?> 位</td>
			<td><?php echo $listArray['point_status'][26]; ?> 位</td>
			<td><?php echo $listArray['point_status'][27]; ?> 位</td>
		<?php } ?>
		</tr>
	</table>
	<br />
	<br />
	<hr><p class="bold">■ 同友会事業部門</p><hr>
	<?php if ($postArray['fiscal_year'] >= 2025) {
		// 2025年度 ?>
		<p class="bold">＜同友拡充部門＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">同友拡充（30点/1入会）</td><td></td>
			<td class="right"><?php echo $listArray['point_status'][0]; ?></td></tr>
			<tr><td class="left">同友数純増（30点ｘ純増数）</td><td></td>
			<td class="right"><?php echo $listArray['point_status'][1]; ?></td></tr>
		</table>
		<br />
		<p class="bold">＜同友会事業部門＞</p>
		<p class="bold">＜基本＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">例会議事録の提出（10点/月）</td><td>120</td>
			<td class="right"><?php echo $listArray['point_status'][2]; ?></td></tr>
			<tr><td class="left">例会出席（10x出席率/月）</td><td>120</td>
			<td class="right"><?php echo $listArray['point_status'][3]; ?></td></tr>
			<tr><td class="left">機関会議の出欠（-100/欠席）</td><td></td>
			<td class="right"><?php echo $listArray['point_status'][4]; ?></td></tr>
			<tr><td class="left">アンケート（10点ｘ提出率）</td><td>10</td>
			<td class="right"><?php echo $listArray['point_status'][5]; ?></td></tr>
			<tr><td class="left">データベース構築（5x入力率/月）</td><td>60</td>
			<td class="right"><?php echo $listArray['point_status'][6]; ?></td></tr>
			<tr><td class="left">ロータスとわかる店づくり（100点ｘ達成率）</td><td>100</td>
			<td class="right"><?php echo $listArray['point_status'][7]; ?></td></tr>
			<tr><td class="left">統一カラー車の保有（50点ｘ達成率）</td><td>50</td>
			<td class="right"><?php echo $listArray['point_status'][8]; ?></td></tr>
			<tr><td class="left">VI顔ピクトの掲示（100点ｘ達成率）</td><td>100</td>
			<td class="right"><?php echo $listArray['point_status'][9]; ?></td></tr>
		</table>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">経営者向け勉強会の実施</td><td>120</td>
			<td class="right"><?php echo $listArray['point_status'][10]; ?></td></tr>
			<tr><td class="left">ロータススタンダード研修</td><td>100</td>
			<td class="right"><?php echo $listArray['point_status'][11]; ?></td></tr>
			<tr><td class="left">支部活動</td><td>120</td>
			<td class="right"><?php echo $listArray['point_status'][12]; ?></td></tr>
			<tr><td class="left">地域での奉仕活動</td><td>40</td>
			<td class="right"><?php echo $listArray['point_status'][13]; ?></td></tr>
		</table>
		<br />
	<?php } else if ($postArray['fiscal_year'] >= 2024) {
		// 2024年 ?>
		<p class="bold">＜基本＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">例会議事録の提出（10点/月）</td><td>120</td>
			<td class="right"><?php echo $listArray['point_status'][0]; ?></td></tr>
			<tr><td class="left">例会出席（10x出席率/月）</td><td>120</td>
			<td class="right"><?php echo $listArray['point_status'][1]; ?></td></tr>
			<tr><td class="left">本部会議への出席（-100/欠席）</td><td></td>
			<td class="right"><?php echo $listArray['point_status'][2]; ?></td></tr>
			<tr><td class="left">アンケート（10点ｘ提出率）</td><td>10</td>
			<td class="right"><?php echo $listArray['point_status'][3]; ?></td></tr>
			<tr><td class="left">データベース入力（5x入力率/月）</td><td>60</td>
			<td class="right"><?php echo $listArray['point_status'][4]; ?></td></tr>
			<tr><td class="left">ロータスとわかる店づくり（CI）（100点ｘ達成率）</td><td>100</td>
			<td class="right"><?php echo $listArray['point_status'][5]; ?></td></tr>
			<tr><td class="left">統一カラー車（50点ｘ達成率）</td><td>50</td>
			<td class="right"><?php echo $listArray['point_status'][6]; ?></td></tr>
			<tr><td class="left">顔ピクト（VI）（100点ｘ達成率）</td><td>100</td>
			<td class="right"><?php echo $listArray['point_status'][7]; ?></td></tr>
		</table>
		<br />
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">同友拡充（30点/1入会）</td><td></td>
			<td class="right"><?php echo $listArray['point_status'][8]; ?></td></tr>
			<tr><td class="left">3年未満退会（1社ｘ▲30点）</td><td></td>
			<td class="right"><?php echo $listArray['point_status'][9]; ?></td></tr>
			<tr><td class="left">同友数純増（30点ｘ純増数）</td><td></td>
			<td class="right"><?php echo $listArray['point_status'][10]; ?></td></tr>
		</table>
		<br />
		<p class="bold">＜支部活動＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">事業適正チェック（15点×実施率）</td><td>15</td>
			<td class="right"><?php echo $listArray['point_status'][11]; ?></td></tr>
			<tr><td class="left">経営に関する勉強会実施（30点ｘ参加率ｘ3企画）</td><td>90</td>
			<td class="right"><?php echo $listArray['point_status'][12]; ?></td></tr>
			<tr><td class="left">ハラスメントチェック（15点ｘ実施率）</td><td>15</td>
			<td class="right"><?php echo $listArray['point_status'][13]; ?></td></tr>
			<tr><td class="left">ロータススタンダード研修[基礎編]浸透率（70点ｘ浸透率）</td><td>70</td>
			<td class="right"><?php echo $listArray['point_status'][14]; ?></td></tr>
			<tr><td class="left">ロータス白書15（30点ｘ提出率）</td><td>30</td>
			<td class="right"><?php echo $listArray['point_status'][15]; ?></td></tr>
			<tr><td class="left">支部統一施策（30点ｘ2企画）</td><td>60</td>
			<td class="right"><?php echo $listArray['point_status'][16]; ?></td></tr>
			<tr><td class="left">同友企業HP設置率（50点ｘ設置率）</td><td>50</td>
			<td class="right"><?php echo $listArray['point_status'][17]; ?></td></tr>
			<tr><td class="left">地域での奉仕活動</td><td>30</td>
			<td class="right"><?php echo $listArray['point_status'][18]; ?></td></tr>
			<tr><td class="left">広報宣伝活動</td><td>20</td>
			<td class="right"><?php echo $listArray['point_status'][19]; ?></td></tr>
		</table>
		<br />
	<?php } else if ($postArray['fiscal_year'] >= 2023) {
		// 2023年 ?>
		<p class="bold">＜基本＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">例会議事録の提出（10点/月）</td><td>120</td>
			<td class="right"><?php echo $listArray['point_status'][0]; ?></td></tr>
			<tr><td class="left">例会出席（10x出席率/月）</td><td>120</td>
			<td class="right"><?php echo $listArray['point_status'][1]; ?></td></tr>
			<tr><td class="left">本部会議への出席（-100/欠席）</td><td></td>
			<td class="right"><?php echo $listArray['point_status'][2]; ?></td></tr>
			<tr><td class="left">アンケート（30点ｘ提出率）</td><td>30</td>
			<td class="right"><?php echo $listArray['point_status'][3]; ?></td></tr>
			<tr><td class="left">データベース入力（5x入力率/月）</td><td>60</td>
			<td class="right"><?php echo $listArray['point_status'][4]; ?></td></tr>
			<tr><td class="left">ロータスとわかる店づくり（CI）（100点ｘ達成率）</td><td>100</td>
			<td class="right"><?php echo $listArray['point_status'][5]; ?></td></tr>
			<tr><td class="left">統一カラー車（50点ｘ達成率）</td><td>50</td>
			<td class="right"><?php echo $listArray['point_status'][6]; ?></td></tr>
			<tr><td class="left">顔ピクト（VI）（100点ｘ達成率）</td><td>100</td>
			<td class="right"><?php echo $listArray['point_status'][7]; ?></td></tr>
		</table>
		<br />
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">同友拡充（30点/1入会）</td><td></td>
			<td class="right"><?php echo $listArray['point_status'][8]; ?></td></tr>
			<tr><td class="left">3年未満退会（1社ｘ▲30点）</td><td></td>
			<td class="right"><?php echo $listArray['point_status'][9]; ?></td></tr>
			<tr><td class="left">同友数純増（30点ｘ純増数）</td><td></td>
			<td class="right"><?php echo $listArray['point_status'][10]; ?></td></tr>
		</table>
		<br />
		<p class="bold">＜支部活動＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">支部ビジョン（50点/2回提出）</td><td>50</td>
			<td class="right"><?php echo $listArray['point_status'][11]; ?></td></tr>
			<tr><td class="left">経営に関する勉強会実施（20点/1開催につき）</td><td>40</td>
			<td class="right"><?php echo $listArray['point_status'][12]; ?></td></tr>
			<tr><td class="left">支部法人によるSDGsの活動</td><td>20</td>
			<td class="right"><?php echo $listArray['point_status'][13]; ?></td></tr>
			<tr><td class="left">ロータススタンダード研修[基礎編]浸透率（50点ｘ浸透率）</td><td>50</td>
			<td class="right"><?php echo $listArray['point_status'][14]; ?></td></tr>
			<tr><td class="left">教育委員会推奨研修会</td><td>20</td>
			<td class="right"><?php echo $listArray['point_status'][15]; ?></td></tr>
			<tr><td class="left">支部活動（20点ｘ4規格）</td><td>80</td>
			<td class="right"><?php echo $listArray['point_status'][16]; ?></td></tr>
			<tr><td class="left">同友企業HP設置率（50点ｘ設置率）</td><td>50</td>
			<td class="right"><?php echo $listArray['point_status'][17]; ?></td></tr>
			<tr><td class="left">地域での奉仕活動</td><td>20</td>
			<td class="right"><?php echo $listArray['point_status'][18]; ?></td></tr>
			<tr><td class="left">広報宣伝活動</td><td>20</td>
			<td class="right"><?php echo $listArray['point_status'][19]; ?></td></tr>
		</table>
		<br />
	<?php } else if ($postArray['fiscal_year'] >= 2022) {
		// 2022年 ?>
		<p class="bold">＜基本＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">例会運営要綱の提出</td><td>60</td>
			<td class="right"><?php echo $listArray['point_status'][0]; ?></td></tr>
			<tr><td class="left">例会議事録の提出</td><td>60</td>
			<td class="right"><?php echo $listArray['point_status'][1]; ?></td></tr>
			<tr><td class="left">例会出席率</td><td>120</td>
			<td class="right"><?php echo $listArray['point_status'][2]; ?></td></tr>
			<tr><td class="left">機関会議の出欠</td><td>-100</td>
			<td class="right"><?php echo $listArray['point_status'][3]; ?></td></tr>
			<tr><td class="left">アンケート調査</td><td>30</td>
			<td class="right"><?php echo $listArray['point_status'][4]; ?></td></tr>
			<tr><td class="left">データベース構築</td><td>60</td>
			<td class="right"><?php echo $listArray['point_status'][5]; ?></td></tr>
			<tr><td class="left">ロータスとわかる店づくり</td><td>100</td>
			<td class="right"><?php echo $listArray['point_status'][6]; ?></td></tr>
			<tr><td class="left">統一カラー車の保有</td><td>50</td>
			<td class="right"><?php echo $listArray['point_status'][7]; ?></td></tr>
			<tr><td class="left">VI顔ピクトの掲示</td><td>100</td>
			<td class="right"><?php echo $listArray['point_status'][8]; ?></td></tr>
		</table>
		<br />
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">同友拡充</td><td>x20</td>
			<td class="right"><?php echo $listArray['point_status'][9]; ?></td></tr>
			<tr><td class="left">同友数純増</td><td>x20</td>
			<td class="right"><?php echo $listArray['point_status'][10]; ?></td></tr>
		</table>
		<br />
		<p class="bold">＜支部活動＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">支部ビジョン</td><td>50</td>
			<td class="right"><?php echo $listArray['point_status'][11]; ?></td></tr>
			<tr><td class="left">経営に関する勉強会の実施</td><td>30</td>
			<td class="right"><?php echo $listArray['point_status'][12]; ?></td></tr>
			<tr><td class="left">SDGsの宣言と発表</td><td>30</td>
			<td class="right"><?php echo $listArray['point_status'][13]; ?></td></tr>
			<tr><td class="left">ロータススタンダード研修修了者配置</td><td>50</td>
			<td class="right"><?php echo $listArray['point_status'][14]; ?></td></tr>
			<tr><td class="left">ロータススタンダード研修修了者配置率100％</td><td>10</td>
			<td class="right"><?php echo $listArray['point_status'][15]; ?></td></tr>
			<tr><td class="left">支部活動</td><td>120</td>
			<td class="right"><?php echo $listArray['point_status'][16]; ?></td></tr>
			<tr><td class="left">地域での奉仕活動</td><td>30</td>
			<td class="right"><?php echo $listArray['point_status'][17]; ?></td></tr>
			<tr><td class="left">広報宣伝活動</td><td>30</td>
			<td class="right"><?php echo $listArray['point_status'][18]; ?></td></tr>
		</table>
		<br />
	<?php } else {
		// 2021年以前 ?>
		<p class="bold">＜基本＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">支部ビジョン</td><td>100</td>
			<td class="right"><?php echo $listArray['point_status'][0]; ?></td></tr>
			<tr><td class="left">例会運営要綱の提出</td><td>60</td>
			<td class="right"><?php echo $listArray['point_status'][1]; ?></td></tr>
			<tr><td class="left">例会議事録の提出</td><td>60</td>
			<td class="right"><?php echo $listArray['point_status'][2]; ?></td></tr>
			<tr><td class="left">例会出席率</td><td>120</td>
			<td class="right"><?php echo $listArray['point_status'][3]; ?></td></tr>
			<tr><td class="left">機関会議の出欠</td><td>-100</td>
			<td class="right"><?php echo $listArray['point_status'][4]; ?></td></tr>
			<tr><td class="left">同友拡充</td><td>--</td>
			<td class="right"><?php echo $listArray['point_status'][5]; ?></td></tr>
			<tr><td class="left">アンケート調査</td><td>30</td>
			<td class="right"><?php echo $listArray['point_status'][6]; ?></td></tr>
			<tr><td class="left">データベース構築</td><td>60</td>
			<td class="right"><?php echo $listArray['point_status'][7]; ?></td></tr>
		</table>
		<br />
		<p class="bold">＜本部施策＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">社員教育関連研修会</td><td>30</td>
			<td class="right"><?php echo $listArray['point_status'][8]; ?></td></tr>
			<tr><td class="left">経営に対する取り組み</td><td>30</td>
			<td class="right"><?php echo $listArray['point_status'][9]; ?></td></tr>
			<tr><td class="left">環境への取り組み</td><td>30</td>
			<td class="right"><?php echo $listArray['point_status'][10]; ?></td></tr>
			<tr><td class="left">ロータスと分かる店つくり</td><td>100</td>
			<td class="right"><?php echo $listArray['point_status'][11]; ?></td></tr>
			<tr><td class="left">統一カラー車の保有</td><td>50</td>
			<td class="right"><?php echo $listArray['point_status'][12]; ?></td></tr>
			<tr><td class="left">VI・顔ピクトの掲示</td><td>50</td>
			<td class="right"><?php echo $listArray['point_status'][13]; ?></td></tr>
		</table>
		<br />
		<p class="bold">＜支部活動＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">勉強会実施</td><td>80</td>
			<td class="right"><?php echo $listArray['point_status'][14]; ?></td></tr>
			<tr><td class="left">支部内統一施策実施</td><td>60</td>
			<td class="right"><?php echo $listArray['point_status'][15]; ?></td></tr>
			<tr><td class="left">地域への奉仕活動</td><td>60</td>
			<td class="right"><?php echo $listArray['point_status'][16]; ?></td></tr>
		</table>
		<br />
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">広告宣伝活動</td><td>50</td>
			<td class="right"><?php echo $listArray['point_status'][17]; ?></td></tr>
		</table>
	<?php } ?>
	<br />
	<hr><p class="bold">■ 法人事業部門</p><hr>
	<p class="bold">＜年間計画達成＞</p>
	<table>
		<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
		<?php
		$lms_point = floor((($listArray['data_list']['LM']['result']['year']+$listArray['data_list']['LS']['result']['year'])/($listArray['data_list']['LM']['plan']['year']+$listArray['data_list']['LS']['plan']['year']))*100);
		$lt_point = floor(($listArray['data_list']['LT']['result']['year']/$listArray['data_list']['LT']['plan']['year'])*100);
		$lh_point = floor(($listArray['data_list']['LH']['result']['year']/$listArray['data_list']['LH']['plan']['year'])*100);
		$lo_point = floor(($listArray['data_list']['LO']['result']['year']/$listArray['data_list']['LO']['plan']['year'])*100);
		$le_point = floor(($listArray['data_list']['LE']['result']['year']/$listArray['data_list']['LE']['plan']['year'])*100);
		$ll_point = floor(($listArray['data_list']['LL']['result']['year']/$listArray['data_list']['LL']['plan']['year'])*100);
		$b_point += $lms_point + $lt_point + $lh_point + $lo_point + $le_point + $ll_point;
		?>
		<tr><td class="left">LM+LS</td><td>100</td>
		<td class="right"><?php echo $lms_point ?></td></tr>
		<tr><td class="left">LT</td><td>100</td>
		<td class="right"><?php echo $lt_point ?></td></tr>
		<tr><td class="left">LH自動車</td><td>100</td>
		<td class="right"><?php echo $lh_point ?></td></tr>
		<tr><td class="left">LO</td><td>100</td>
		<td class="right"><?php echo $lo_point ?></td></tr>
		<tr><td class="left">LE</td><td>100</td>
		<td class="right"><?php echo $le_point ?></td></tr>
		<tr><td class="left">LL</td><td>100</td>
		<td class="right"><?php echo $ll_point ?></td></tr>
	</table>
	<br />
	<table>
		<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
		<?php
		$month = $listArray['data_list']['LC_hold_month'];
		$lc_hold_point = floor(($listArray['data_list']['LC_hold_number']/$listArray['lc_hold_number']['lc_year_target_count']*50));
		$b_point += $lc_hold_point;
		?>
		<tr><td class="left">LC保有計画達成 (※<?php echo $month?>月時点)</td><td>50</td>
		<td class="right"><?php echo $lc_hold_point ?></td></tr>
	</table>
	<br />

	<?php
	// 全同友稼働と最低販売基準達成を表示
	printExcutiveWorkingAndMintargetPointTable($postArray, $listArray); ?>

	<?php
	// キャンペーン得点を表示
	printExcutiveCampaignPointTable($postArray, $listArray); ?>	

	<br />

	<?php
	// 2022年：同友会事業部門の内容が変更
	// 2021年：生命共済と団体総合補償保険に計画と実績を入れて得点は計算で算出変更
	if ($postArray['fiscal_year'] >= 2021) {
	
		// 生命共済を計算（達成率が100以上なら30点、それ以外は0.3をかけて切り捨て）
		// 小数点第1位までで計算したいので、1000倍して計算
		$ratio = floor(($listArray['point_status'][$start_index_num+1] / $listArray['point_status'][$start_index_num]) * 1000);
		if ($ratio > 1000) {
			$seimei_point = 30;
		}
		else {
			$seimei_point = floor($ratio * 0.03);
		}
		$b_point += $seimei_point;

		// 団体総合補償保険（達成率が100以上なら30点、それ以外は0.3をかけて切り捨て）
		// 小数点第1位までで計算したいので、1000倍して計算
		$ratio = floor(($listArray['point_status'][$start_index_num+3] / $listArray['point_status'][$start_index_num+2]) * 1000);
		if ($ratio > 1000) {
			$sougou_point = 30;
		}
		else {
			$sougou_point = floor($ratio * 0.03);
		}
		$b_point += $sougou_point;

	?>
	<table>
		<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
		<tr><td class="left">生命共済</td><td>30</td>
		<td class="right"><?php echo $seimei_point; ?></td></tr>
		<tr><td class="left">団体総合補償保険</td><td>30</td>
		<td class="right"><?php echo $sougou_point; ?></td></tr>
		<tr><td class="left">(株)ロータスへの配当</td><td>60</td>
		<td class="right"><?php echo $listArray['point_status'][$start_index_num+4]; ?></td></tr>
	</table>
	<br />
	<p class="bold">＜ロートピア＞</p>
	<table>
		<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
		<tr><td class="left">サマーオートリースキャンペーン</td><td>5</td>
		<td class="right"><?php echo $listArray['point_status'][$start_index_num+5]; ?></td></tr>
		<tr><td class="left">秋のオートリースキャンペーン</td><td>5</td>
		<td class="right"><?php echo $listArray['point_status'][$start_index_num+6]; ?></td></tr>
		<tr><td class="left">春のオートリースキャンペーン</td><td>5</td>
		<td class="right"><?php echo $listArray['point_status'][$start_index_num+7]; ?></td></tr>
		<tr><td class="left">年間支部目標達成</td><td>10</td>
		<td class="right"><?php echo $listArray['point_status'][$start_index_num+8]; ?></td></tr>
	</table>
	<?php }
	// 2021年以前
	else { ?>
	<table>
		<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
		<tr><td class="left">生命共済</td><td>30</td>
		<td class="right"><?php echo $listArray['point_status'][$start_index_num]; ?></td></tr>
		<tr><td class="left">団体総合補償保険</td><td>30</td>
		<td class="right"><?php echo $listArray['point_status'][$start_index_num+9]; ?></td></tr>
		<tr><td class="left">(株)ロータスへの配当</td><td>60</td>
		<td class="right"><?php echo $listArray['point_status'][$start_index_num+2]; ?></td></tr>
	</table>
	<br />
	<p class="bold">＜ロートピア＞</p>
	<table>
		<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
		<tr><td class="left">サマーオートリースキャンペーン</td><td>5</td>
		<td class="right"><?php echo $listArray['point_status'][$start_index_num+3]; ?></td></tr>
		<tr><td class="left">秋のオートリースキャンペーン</td><td>5</td>
		<td class="right"><?php echo $listArray['point_status'][$start_index_num+4]; ?></td></tr>
		<tr><td class="left">春のオートリースキャンペーン</td><td>5</td>
		<td class="right"><?php echo $listArray['point_status'][$start_index_num+5]; ?></td></tr>
		<tr><td class="left">年間支部目標達成</td><td>10</td>
		<td class="right"><?php echo $listArray['point_status'][$start_index_num+6]; ?></td></tr>
	</table>
	<?php } ?>

</div>

<?php
	if ($postArray['fiscal_year'] >= 2025) {
		// 同友拡充部門得点の合計値を取得
		for ($i = 0; $i <= 1; $i++) {
			$d_point += $listArray['point_status'][$i];
		}
		// 同友会事業得点の合計値を取得
		for ($i = 2; $i <= $start_index_num-1; $i++) {
			$e_point += $listArray['point_status'][$i];
		}
	} else {
		// 同友会事業得点の合計値を取得
		for ($i = 0; $i <= $start_index_num-1; $i++) {
			$e_point += $listArray['point_status'][$i];
		}
	}

	// 法人事業得点の合計値を取得
	if ($postArray['fiscal_year'] >= 2021) {
		// 2021年以降
		for ($i = $start_index_num+4; $i <= $start_index_num+8; $i++) {
			$b_point += $listArray['point_status'][$i];
		}
	}
	else {
		// 2021年以前
		for ($i = $start_index_num; $i <= $start_index_num+6; $i++) {
			$b_point += $listArray['point_status'][$i];
		}
	}
	
	$b_point += $listArray['point_status']['other'];
?>

<?php if ($postArray['fiscal_year'] >= 2025) { ?>
	<script>
		// 合計点を書き出す先頭の表に挿入する
		var d_point = <?php echo $d_point;?>;
		var e_point = <?php echo $e_point;?>;
		var b_point = <?php echo $b_point;?>;
		var table = document.getElementById('total_table');
		table.rows[1].cells[0].innerText = d_point;
		table.rows[1].cells[1].innerText = e_point;
		table.rows[1].cells[2].innerText = b_point;
		table.rows[1].cells[3].innerText = d_point + e_point + b_point;
	</script>
<?php } else { ?>
	<script>
		// 合計点を書き出す先頭の表に挿入する
		var e_point = <?php echo $e_point;?>;
		var b_point = <?php echo $b_point;?>;
		var table = document.getElementById('total_table');
		table.rows[1].cells[0].innerText = e_point;
		table.rows[1].cells[1].innerText = b_point;
		table.rows[1].cells[2].innerText = e_point + b_point
	</script>
<?php } ?>
<?php }
//---------------------------------------------
// 支部表彰得点状況 設定画面
//---------------------------------------------
elseif ($page === 'setting') { ?>

<div id="contents_select">
	<form method="POST" action="index.php?reg=point_status&type=view">
		<?php printSubmitButton('保存', 'save') ?>
		<input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>">
</div>
<hr>

<div id="contents">
    <p><?php echo $postArray['fiscal_year'] ?>年度の支部表彰得点状況の変更を行っています。変更後、保存ボタンを押してください。</p><br />

	<?php if ($postArray['fiscal_year'] >= 2025) {
		// 2025年度 ?>
		<hr><p class="bold">■ 同友拡充部門</p><hr>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">同友拡充（30点/1入会）</td><td></td>
			<td><?php printTextBox('point_status_1', 80, 'right', $listArray['point_status'][0]); ?></td></tr>
			<tr><td class="left">同友数純増（30点ｘ純増数）</td><td></td>
			<td><?php printTextBox('point_status_2', 80, 'right', $listArray['point_status'][1]); ?></td></tr>
		</table>
		<br />
		<hr><p class="bold">■ 同友会事業部門</p><hr>
		<p class="bold">＜基本＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">例会議事録の提出（10点/月）</td><td>120</td>
			<td><?php printTextBox('point_status_3', 80, 'right', $listArray['point_status'][2]); ?></td></tr>
			<tr><td class="left">例会出席（10x出席率/月）</td><td>120</td>
			<td><?php printTextBox('point_status_4', 80, 'right', $listArray['point_status'][3]); ?></td></tr>
			<tr><td class="left">機関会議の出欠（-100/欠席）</td><td></td>
			<td><?php printTextBox('point_status_5', 80, 'right', $listArray['point_status'][4]); ?></td></tr>
			<tr><td class="left">アンケート（30点ｘ提出率）</td><td>30</td>
			<td><?php printTextBox('point_status_6', 80, 'right', $listArray['point_status'][5]); ?></td></tr>
			<tr><td class="left">データベース構築（5x入力率/月）</td><td>60</td>
			<td><?php printTextBox('point_status_7', 80, 'right', $listArray['point_status'][6]); ?></td></tr>
			<tr><td class="left">ロータスとわかる店づくり（CI）（100点ｘ達成率）</td><td>100</td>
			<td><?php printTextBox('point_status_8', 80, 'right', $listArray['point_status'][7]); ?></td></tr>
			<tr><td class="left">統一カラー車の保有（50点ｘ達成率）</td><td>50</td>
			<td><?php printTextBox('point_status_9', 80, 'right', $listArray['point_status'][8]); ?></td></tr>
			<tr><td class="left">VI顔ピクトの掲示（100点ｘ達成率）</td><td>100</td>
			<td><?php printTextBox('point_status_10', 80, 'right', $listArray['point_status'][9]); ?></td></tr>
		</table>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">経営者向け勉強会の実施</td><td>120</td>
			<td><?php printTextBox('point_status_11', 80, 'right', $listArray['point_status'][10]); ?></td></tr>
			<tr><td class="left">ロータススタンダード研修</td><td>100</td>
			<td><?php printTextBox('point_status_12', 80, 'right', $listArray['point_status'][11]); ?></td></tr>
			<tr><td class="left">支部活動</td><td>120</td>
			<td><?php printTextBox('point_status_13', 80, 'right', $listArray['point_status'][12]); ?></td></tr>
			<tr><td class="left">地域での奉仕活動</td><td>40</td>
			<td><?php printTextBox('point_status_14', 80, 'right', $listArray['point_status'][13]); ?></td></tr>
		</table>
		<br />
	<?php } else if ($postArray['fiscal_year'] >= 2023) {
		// 2023年 ?>
		<hr><p class="bold">■ 同友会事業部門</p><hr>
		<p class="bold">＜基本＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">例会議事録の提出（10点/月）</td><td>120</td>
			<td><?php printTextBox('point_status_1', 80, 'right', $listArray['point_status'][0]); ?></td></tr>
			<tr><td class="left">例会出席（10x出席率/月）</td><td>120</td>
			<td><?php printTextBox('point_status_2', 80, 'right', $listArray['point_status'][1]); ?></td></tr>
			<tr><td class="left">本部会議への出席（-100/欠席）</td><td></td>
			<td><?php printTextBox('point_status_3', 80, 'right', $listArray['point_status'][2]); ?></td></tr>
			<tr><td class="left">アンケート（30点ｘ提出率）</td><td>30</td>
			<td><?php printTextBox('point_status_4', 80, 'right', $listArray['point_status'][3]); ?></td></tr>
			<tr><td class="left">データベース入力（5x入力率/月）</td><td>60</td>
			<td><?php printTextBox('point_status_5', 80, 'right', $listArray['point_status'][4]); ?></td></tr>
			<tr><td class="left">ロータスとわかる店づくり（CI）（100点ｘ達成率）</td><td>100</td>
			<td><?php printTextBox('point_status_6', 80, 'right', $listArray['point_status'][5]); ?></td></tr>
			<tr><td class="left">統一カラー車（50点ｘ達成率）</td><td>50</td>
			<td><?php printTextBox('point_status_7', 80, 'right', $listArray['point_status'][6]); ?></td></tr>
			<tr><td class="left">顔ピクト（VI）（100点ｘ達成率）</td><td>100</td>
			<td><?php printTextBox('point_status_8', 80, 'right', $listArray['point_status'][7]); ?></td></tr>
		</table>
		<br />
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">同友拡充（30点/1入会）</td><td></td>
			<td><?php printTextBox('point_status_9', 80, 'right', $listArray['point_status'][8]); ?></td></tr>
			<tr><td class="left">3年未満退会（1社ｘ▲30点）</td><td></td>
			<td><?php printTextBox('point_status_10', 80, 'right', $listArray['point_status'][9]); ?></td></tr>
			<tr><td class="left">同友数純増（30点ｘ純増数）</td><td></td>
			<td><?php printTextBox('point_status_11', 80, 'right', $listArray['point_status'][10]); ?></td></tr>
		</table>
		<br />
		<p class="bold">＜支部活動＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">支部ビジョン（50点/2回提出）</td><td>50</td>
			<td><?php printTextBox('point_status_12', 80, 'right', $listArray['point_status'][11]); ?></td></tr>
			<tr><td class="left">経営に関する勉強会実施（20点/1開催につき）</td><td>40</td>
			<td><?php printTextBox('point_status_13', 80, 'right', $listArray['point_status'][12]); ?></td></tr>
			<tr><td class="left">支部法人によるSDGsの活動</td><td>20</td>
			<td><?php printTextBox('point_status_14', 80, 'right', $listArray['point_status'][13]); ?></td></tr>
			<tr><td class="left">ロータススタンダード研修[基礎編]浸透率（50点ｘ浸透率）</td><td>50</td>
			<td><?php printTextBox('point_status_15', 80, 'right', $listArray['point_status'][14]); ?></td></tr>
			<tr><td class="left">教育委員会推奨研修会</td><td>20</td>
			<td><?php printTextBox('point_status_16', 80, 'right', $listArray['point_status'][15]); ?></td></tr>
			<tr><td class="left">支部活動（20点ｘ4規格）</td><td>80</td>
			<td><?php printTextBox('point_status_17', 80, 'right', $listArray['point_status'][16]); ?></td></tr>
			<tr><td class="left">同友企業HP設置率（50点ｘ設置率）</td><td>50</td>
			<td><?php printTextBox('point_status_18', 80, 'right', $listArray['point_status'][17]); ?></td></tr>
			<tr><td class="left">地域での奉仕活動</td><td>20</td>
			<td><?php printTextBox('point_status_19', 80, 'right', $listArray['point_status'][18]); ?></td></tr>
			<tr><td class="left">広報宣伝活動</td><td>20</td>
			<td><?php printTextBox('point_status_20', 80, 'right', $listArray['point_status'][19]); ?></td></tr>
		</table>
	<?php }
	else if ($postArray['fiscal_year'] >= 2022) {
		// 2022年 ?>
		<hr><p class="bold">■ 同友会事業部門</p><hr>
		<p class="bold">＜基本＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">例会運営要綱の提出</td><td>60</td>
			<td><?php printTextBox('point_status_1', 80, 'right', $listArray['point_status'][0]); ?></td></tr>
			<tr><td class="left">例会議事録の提出</td><td>60</td>
			<td><?php printTextBox('point_status_2', 80, 'right', $listArray['point_status'][1]); ?></td></tr>
			<tr><td class="left">例会出席率</td><td>120</td>
			<td><?php printTextBox('point_status_3', 80, 'right', $listArray['point_status'][2]); ?></td></tr>
			<tr><td class="left">機関会議の出欠</td><td>-100</td>
			<td><?php printTextBox('point_status_4', 80, 'right', $listArray['point_status'][3]); ?></td></tr>
			<tr><td class="left">アンケート調査</td><td>30</td>
			<td><?php printTextBox('point_status_5', 80, 'right', $listArray['point_status'][4]); ?></td></tr>
			<tr><td class="left">データベース構築</td><td>60</td>
			<td><?php printTextBox('point_status_6', 80, 'right', $listArray['point_status'][5]); ?></td></tr>
			<tr><td class="left">ロータスとわかる店づくり</td><td>100</td>
			<td><?php printTextBox('point_status_7', 80, 'right', $listArray['point_status'][6]); ?></td></tr>
			<tr><td class="left">統一カラー車の保有</td><td>50</td>
			<td><?php printTextBox('point_status_8', 80, 'right', $listArray['point_status'][7]); ?></td></tr>
			<tr><td class="left">VI顔ピクトの掲示</td><td>100</td>
			<td><?php printTextBox('point_status_9', 80, 'right', $listArray['point_status'][8]); ?></td></tr>
		</table>
		<br />
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">同友拡充</td><td>x20</td>
			<td><?php printTextBox('point_status_10', 80, 'right', $listArray['point_status'][9]); ?></td></tr>
			<tr><td class="left">同友数純増</td><td>x20</td>
			<td><?php printTextBox('point_status_11', 80, 'right', $listArray['point_status'][10]); ?></td></tr>
		</table>
		<br />
		<p class="bold">＜支部活動＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">支部ビジョン</td><td>50</td>
			<td><?php printTextBox('point_status_12', 80, 'right', $listArray['point_status'][11]); ?></td></tr>
			<tr><td class="left">経営に関する勉強会の実施</td><td>30</td>
			<td><?php printTextBox('point_status_13', 80, 'right', $listArray['point_status'][12]); ?></td></tr>
			<tr><td class="left">SDGsの宣言と発表</td><td>30</td>
			<td><?php printTextBox('point_status_14', 80, 'right', $listArray['point_status'][13]); ?></td></tr>
			<tr><td class="left">ロータススタンダード研修修了者配置</td><td>50</td>
			<td><?php printTextBox('point_status_15', 80, 'right', $listArray['point_status'][14]); ?></td></tr>
			<tr><td class="left">ロータススタンダード研修修了者配置率100％査</td><td>10</td>
			<td><?php printTextBox('point_status_16', 80, 'right', $listArray['point_status'][15]); ?></td></tr>
			<tr><td class="left">支部活動</td><td>120</td>
			<td><?php printTextBox('point_status_17', 80, 'right', $listArray['point_status'][16]); ?></td></tr>
			<tr><td class="left">地域での奉仕活動</td><td>30</td>
			<td><?php printTextBox('point_status_18', 80, 'right', $listArray['point_status'][17]); ?></td></tr>
			<tr><td class="left">広報宣伝活動</td><td>30</td>
			<td><?php printTextBox('point_status_19', 80, 'right', $listArray['point_status'][18]); ?></td></tr>
		</table>
	<?php } else {
		// 2021年以前 ?>
		<hr><p class="bold">■ 同友会事業部門</p><hr>
		<p class="bold">＜基本＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">支部ビジョン</td><td>100</td>
			<td><?php printTextBox('point_status_1', 80, 'right', $listArray['point_status'][0]); ?></td></tr>
			<tr><td class="left">例会運営要綱の提出</td><td>60</td>
			<td><?php printTextBox('point_status_2', 80, 'right', $listArray['point_status'][1]); ?></td></tr>
			<tr><td class="left">例会議事録の提出</td><td>60</td>
			<td><?php printTextBox('point_status_3', 80, 'right', $listArray['point_status'][2]); ?></td></tr>
			<tr><td class="left">例会出席率</td><td>120</td>
			<td><?php printTextBox('point_status_4', 80, 'right', $listArray['point_status'][3]); ?></td></tr>
			<tr><td class="left">機関会議の出欠</td><td>-100</td>
			<td><?php printTextBox('point_status_5', 80, 'right', $listArray['point_status'][4]); ?></td></tr>
			<tr><td class="left">同友拡充</td><td></td>
			<td><?php printTextBox('point_status_6', 80, 'right', $listArray['point_status'][5]); ?></td></tr>
			<tr><td class="left">アンケート調査</td><td>30</td>
			<td><?php printTextBox('point_status_7', 80, 'right', $listArray['point_status'][6]); ?></td></tr>
			<tr><td class="left">データベース構築</td><td>60</td>
			<td><?php printTextBox('point_status_8', 80, 'right', $listArray['point_status'][7]); ?></td></tr>
		</table>
		<br />
		<p class="bold">＜本部施策＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">社員教育関連研修会</td><td>30</td>
			<td><?php printTextBox('point_status_9', 80, 'right', $listArray['point_status'][8]); ?></td></tr>
			<tr><td class="left">経営に対する取り組み</td><td>30</td>
			<td><?php printTextBox('point_status_10', 80, 'right', $listArray['point_status'][9]); ?></td></tr>
			<tr><td class="left">環境への取り組み</td><td>30</td>
			<td><?php printTextBox('point_status_11', 80, 'right', $listArray['point_status'][10]); ?></td></tr>
			<tr><td class="left">ロータスと分かる店つくり</td><td>100</td>
			<td><?php printTextBox('point_status_12', 80, 'right', $listArray['point_status'][11]); ?></td></tr>
			<tr><td class="left">統一カラー車の保有</td><td>50</td>
			<td><?php printTextBox('point_status_13', 80, 'right', $listArray['point_status'][12]); ?></td></tr>
			<tr><td class="left">VI・顔ピクトの掲示</td><td>50</td>
			<td><?php printTextBox('point_status_14', 80, 'right', $listArray['point_status'][13]); ?></td></tr>
		</table>
		<br />
		<p class="bold">＜支部活動＞</p>
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">勉強会実施</td><td>80</td>
			<td><?php printTextBox('point_status_15', 80, 'right', $listArray['point_status'][14]); ?></td></tr>
			<tr><td class="left">支部内統一施策実施</td><td>60</td>
			<td><?php printTextBox('point_status_16', 80, 'right', $listArray['point_status'][15]); ?></td></tr>
			<tr><td class="left">地域への奉仕活動</td><td>60</td>
			<td><?php printTextBox('point_status_17', 80, 'right', $listArray['point_status'][16]); ?></td></tr>
		</table>
		<br />
		<table>
			<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
			<tr><td class="left">広告宣伝活動</td><td>50</td>
			<td><?php printTextBox('point_status_18', 80, 'right', $listArray['point_status'][17]); ?></td></tr>
		</table>
		<br />
	<?php }  ?>
	<?php
	// 2022年：同友会事業部門の内容が変更
	// 2021年：生命共済と団体総合補償保険に計画と実績を入れて得点は計算で算出変更
	if ($postArray['fiscal_year'] >= 2021) { ?>
	<hr><p class="bold">■ 法人事業部門</p><hr>
	<table>
		<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">支部計画</td><td width="80px">実績</td></tr>
		<tr><td class="left">生命共済</td><td>30</td>
		<td><?php printTextBox('point_status_'.($start_index_num+1), 80, 'right', $listArray['point_status'][$start_index_num]); ?></td><td><?php printTextBox('point_status_'.($start_index_num+2), 80, 'right', $listArray['point_status'][$start_index_num+1]); ?></td></tr>
		<tr><td class="left">団体総合補償保険</td><td>30</td>
		<td><?php printTextBox('point_status_'.($start_index_num+3), 80, 'right', $listArray['point_status'][$start_index_num+2]); ?></td><td><?php printTextBox('point_status_'.($start_index_num+4), 80, 'right', $listArray['point_status'][$start_index_num+3]); ?></td></tr>
		<tr><td class="left">(株)ロータスへの配当</td><td>60</td>
		<td><?php printTextBox('point_status_'.($start_index_num+5), 80, 'right', $listArray['point_status'][$start_index_num+4]); ?></td></tr>
	</table>
	<br />
	<p class="bold">＜ロートピア＞</p>
	<table>
		<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
		<tr><td class="left">サマーオートリースキャンペーン</td><td>5</td>
		<td><?php printTextBox('point_status_'.($start_index_num+6), 80, 'right', $listArray['point_status'][$start_index_num+5]); ?></td></tr>
		<tr><td class="left">秋のオートリースキャンペーン</td><td>5</td>
		<td><?php printTextBox('point_status_'.($start_index_num+7), 80, 'right', $listArray['point_status'][$start_index_num+6]); ?></td></tr>
		<tr><td class="left">春のオートリースキャンペーン</td><td>5</td>
		<td><?php printTextBox('point_status_'.($start_index_num+8), 80, 'right', $listArray['point_status'][$start_index_num+7]); ?></td></tr>
		<tr><td class="left">年間支部目標達成</td><td>10</td>
		<td><?php printTextBox('point_status_'.($start_index_num+9), 80, 'right', $listArray['point_status'][$start_index_num+8]); ?></td></tr>
	</table>
	<br />
	<hr><p class="bold">■ 順位</p><hr>
	<table>
		<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">順位</td></tr>
		<?php if ($postArray['fiscal_year'] >= 2025) { ?>
			<tr><td class="left">同友拡充部門順位</td>
			<td><?php printTextBox('point_status_'.($start_index_num+10), 80, 'right', $listArray['point_status'][$start_index_num+9]); ?></td></tr>
			<tr><td class="left">同友会事業部門順位</td>
			<td><?php printTextBox('point_status_'.($start_index_num+11), 80, 'right', $listArray['point_status'][$start_index_num+10]); ?></td></tr>
			<tr><td class="left">法人事業部門順位</td>
			<td><?php printTextBox('point_status_'.($start_index_num+12), 80, 'right', $listArray['point_status'][$start_index_num+11]); ?></td></tr>
			<tr><td class="left">総合順位</td>
			<td><?php printTextBox('point_status_'.($start_index_num+13), 80, 'right', $listArray['point_status'][$start_index_num+12]); ?></td></tr>
		<?php } else { ?>
			<tr><td class="left">同友会順位</td>
			<td><?php printTextBox('point_status_'.($start_index_num+10), 80, 'right', $listArray['point_status'][$start_index_num+9]); ?></td></tr>
			<tr><td class="left">法人事業順位</td>
			<td><?php printTextBox('point_status_'.($start_index_num+11), 80, 'right', $listArray['point_status'][$start_index_num+10]); ?></td></tr>
			<tr><td class="left">総合順位</td>
			<td><?php printTextBox('point_status_'.($start_index_num+12), 80, 'right', $listArray['point_status'][$start_index_num+11]); ?></td></tr>
		<?php } ?>
	</table>
	<?php }
	// 2021年以前
	else { ?>
		<hr><p class="bold">■ 法人事業部門</p><hr>
	<table>
		<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
		<tr><td class="left">生命共済</td><td>30</td>
		<td><?php printTextBox('point_status_'.($start_index_num+1), 80, 'right', $listArray['point_status'][$start_index_num]); ?></td></tr>
		<tr><td class="left">団体総合補償保険</td><td>30</td>
		<td><?php printTextBox('point_status_'.($start_index_num+2), 80, 'right', $listArray['point_status'][$start_index_num+1]); ?></td></tr>
		<tr><td class="left">(株)ロータスへの配当</td><td>60</td>
		<td><?php printTextBox('point_status_'.($start_index_num+3), 80, 'right', $listArray['point_status'][$start_index_num+2]); ?></td></tr>
	</table>
	<br />
	<p class="bold">＜ロートピア＞</p>
	<table>
		<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">達成得点</td><td width="80px">得点</td></tr>
		<tr><td class="left">サマーオートリースキャンペーン</td><td>5</td>
		<td><?php printTextBox('point_status_'.($start_index_num+4), 80, 'right', $listArray['point_status'][$start_index_num+3]); ?></td></tr>
		<tr><td class="left">秋のオートリースキャンペーン</td><td>5</td>
		<td><?php printTextBox('point_status_'.($start_index_num+5), 80, 'right', $listArray['point_status'][$start_index_num+4]); ?></td></tr>
		<tr><td class="left">春のオートリースキャンペーン</td><td>5</td>
		<td><?php printTextBox('point_status_'.($start_index_num+6), 80, 'right', $listArray['point_status'][$start_index_num+5]); ?></td></tr>
		<tr><td class="left">年間支部目標達成</td><td>10</td>
		<td><?php printTextBox('point_status_'.($start_index_num+7), 80, 'right', $listArray['point_status'][$start_index_num+6]); ?></td></tr>
	</table>
	<br />
	<hr><p class="bold">■ 順位</p><hr>
	<table>
		<tr class="bg_wet_asphalt"><td width="200px">項目</td><td width="80px">順位</td></tr>
		<tr><td class="left">同友会順位</td>
		<td><?php printTextBox('point_status_'.($start_index_num+8), 80, 'right', $listArray['point_status'][$start_index_num+7]); ?></td></tr>
		<tr><td class="left">法人事業順位</td>
		<td><?php printTextBox('point_status_'.($start_index_num+9), 80, 'right', $listArray['point_status'][$start_index_num+8]); ?></td></tr>
		<tr><td class="left">総合順位</td>
		<td><?php printTextBox('point_status_'.($start_index_num+10), 80, 'right', $listArray['point_status'][$start_index_num+9]); ?></td></tr>
	</table>
	
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