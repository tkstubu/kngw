<?php
/**
 * =================================================================
 * commendation.php
 * 表彰（★兵庫専用★） PHPスクリプト
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
	'fiscal_year_list' 	=> array(),
	'campaign_list' 	=> array(),
	'item_list' 		=> array(),
	'partner_list' 		=> array(),
	'executive_list' 	=> array(),
	'campaign_info' 	=> array(),
	'data_list'        	=> array()
);

//--------------------------------
// 表示ページを選択
//--------------------------------
$page = selectPageForCommendation($getArray, $postArray, $listArray);

/**
 * ----------------------------------------------------------
 * selectPageForCommendation()
 * 本部年間支援施策の状況表示と設定で表示するページを選択する
 * @param $getArray：GETで送られてきたパラメータ
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function selectPageForCommendation($getArray, &$postArray, &$listArray) {

	$page = '';

	// 選択中のメニューに応じた結果を表示
	switch ($getArray['type']) {
		case  'view':
            $page = makeBranchPrizeViewPage($postArray, $listArray);
			break;
		default:
			break;
	}
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * selectPageForCommendationResult()
 * 表彰状況を表示する
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makeBranchPrizeViewPage(&$postArray, &$listArray) {
	
	$page = 'view';

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
	// 使用しない種目を削除
	foreach ($listArray['item_list'] as $key => $itemListArray) {
		if ($itemListArray['value'] === 'ALL' || $itemListArray['value'] === 'LC' || $itemListArray['value'] === 'LEB') {
			unset($listArray['item_list'][$key]);
		}
	}

	// キャンペーンリストを作成
	$listArray['campaign_list'][] = array('value' => 'summer', 'name' => config::SUMMER_CAMPAIGN_NAME);
	$listArray['campaign_list'][] = array('value' => 'autumn', 'name' => config::AUTUMN_CAMPAIGN_NAME);
	$listArray['campaign_list'][] = array('value' => 'spring', 'name' => config::SPRING_CAMPAIGN_NAME);
	
	//------------------------------------------------
	// 表示ボタンを押された場合、データを読み込み
	//------------------------------------------------
	if (isset($postArray['command'])) {

		// キャンペーン種別判定
		switch($postArray['campaign']) {
			case 'summer':
				$start_month = config::SUMMER_CAMPAIGN_START_MONTH;
				$end_month = config::SUMMER_CAMPAIGN_END_MONTH;
				break;
			case 'autumn':
				$start_month = config::AUTUMN_CAMPAIGN_START_MONTH;
				$end_month = config::AUTUMN_CAMPAIGN_END_MONTH;
				break;
			case 'spring':
				$start_month = config::SPRING_CAMPAIGN_START_MONTH;
				$end_month = config::SPRING_CAMPAIGN_END_MONTH;
				break;
		}

		// キャンペーン目標を取得
		foreach($listArray['item_list'] as $itemInfo) {
			$listArray['campaign_info'][$itemInfo['value']] = getCampaignInfo($postArray['fiscal_year'], $itemInfo['value']);
		}

		// 同友一覧を取得
		if (isset($postArray['fiscal_year'])) {
			$listArray['executive_list'] = getExecutiveList($postArray['fiscal_year'], 'campaign', '%');
			if (count($listArray['executive_list']) == 0) {
				return 'no_executive';
			}
		}

		// 全同友の必要な種目の計画と実績を取得
		foreach($listArray['executive_list'] as $executiveInfo) {

			// 脱退済みの同友のデータは取得しない
			if ($executiveInfo['exitdate'] !== "0000-00-00") {

				// 退会済みの同友であっても、年度内は実績報告には表示する
				$expire = preg_split("/-/", $executiveInfo['exitdate']);
				if ( ($expire[0] <= $postArray['fiscal_year']+1 && $expire[1] <= 3) || ($expire[0] <= $fiscal_year && $expire[1] > 3)){ 
					// 表示する
				}
				else {
					continue;
				}
			}

			// 必要な種目毎の計画と実績を取得
			foreach($listArray['item_list'] as $itemInfo) {

				// 初期化
				$planTotal = 0;
				$resultTotal = 0;

				// 種目に該当する提携企業一覧を取得
				$listArray['partner_list'] = getPartnerList($postArray['fiscal_year'], $itemInfo['value']);

				// 提携企業数分の計画、実績を取得
				foreach ($listArray['partner_list'] as $partnerArray) {

					// 計画取得
					$planData = getCampaignPlanTotalValue($postArray['fiscal_year'], '', $partnerArray['value'], '', $executiveInfo['value']);
					$planTotal += $planData[$postArray['campaign'].'_plan'];

					// 実績取得
					$resultData = getCampaignMonthTotalValue($postArray['fiscal_year'], $itemInfo['value'], $partnerArray['value'], '', $executiveInfo['value'], $start_month, $end_month, false);
					$resultTotal += $resultData[$start_month.'_result'] + $resultData[$end_month.'_result'];
				}

				// 表示で必要な同友の情報を保存
				$listArray['data_list'][$executiveInfo['value']][$itemInfo['value']]['plan']   = $planTotal;
				$listArray['data_list'][$executiveInfo['value']][$itemInfo['value']]['result'] = $resultTotal;
			}
		}
		// デバッグ
		//printArray($listArray['data_list']);
    }

	return $page;
}

/**
 * ----------------------------------------------------------
 * sortByKey()
 * 多次元配列の指定したキーの値を基準にソートする
 * ----------------------------------------------------------
 */
function sortByKey($key_name, $sort_order, $array) {
    foreach ($array as $key => $value) {
        $standard_key_array[$key] = $value[$key_name];
    }

    array_multisort($standard_key_array, $sort_order, $array);

    return $array;
}

/**
 * ----------------------------------------------------------
 * createArrayForSort()
 * ソートの基準となるキーに対応する値の配列を作成
 * ----------------------------------------------------------
 */
function createArrayForSort($key_name, $array) {
    foreach ($array as $key => $value) {
            $standard_key_array[$key] = $value[$key_name];
    }

    return $standard_key_array;
}

/**
 * ----------------------------------------------------------
 * printCommendationPointResultTable()
 * 総合優績賞（ポイント）を表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return
 * ----------------------------------------------------------
 */
function printCommendationPointResultTable($postArray, $listArray) {

	$calData = array();
	$sortedCalData = array();

	// 各同友の各種目の達成率ポイントを計算
	foreach($listArray['data_list'] as $key => $value) {

		$totalPoint = 0;

		foreach ($value as $item => $data) {
			$point = floor(($data["result"] / $data["plan"]) * 1000) / 10;
			if ($point >= config::COMMENDATION_ACHIEVEMENT_RATIO_MAX) {
				$totalPoint += 300;	// 達成率が300%を超える場合は、300%を上限とする
			}
			else {
				$totalPoint += $point;
			}
		}
		$calData[]= array("eid" => $key, "point" => $totalPoint, "data" => $value);
	}

	// 達成率ポイントの合計値で降順ソートする
	$sortedCalData = sortByKey('point', SORT_DESC, $calData);

	// デバッグ
	//printArray($sortedCalData);

	echo '<table>';
	echo '<tr class="bg_wet_asphalt"><td>順位</td><td>同友名</td><td>ポイント</td></tr>';

	// 上位10位までを表示
	$rank = 1;
	$pre_result = 0;

	for ($i = 0; $i < config::COMMENDATION_TOP_NUM; $i++) {

		// 一つ前の実績と現在の実績が同一かどうか確認し、同一ならランキングはそのまま、異なる場合はランキングを加算する
		if ($i > 0 && $sortedCalData[$i]["point"] != $pre_result) {
			$rank = $i+1;
		}

		// 該当する同友を検索
		$index = array_search($sortedCalData[$i]["eid"], array_column( $listArray['executive_list'], 'value'));
		echo '<tr><td class="right" style="width:30px">'.($i+1).'</td>';
		echo '<td class="left" style="width:200px">'.$listArray['executive_list'][$index]["name"].'</td>';
		echo '<td class="right" style="width:80px">'. formatNumber($sortedCalData[$i]["point"], 1, "floor").'</td></tr>';

		$pre_result = $sortedCalData[$i]["point"];	// 実績を保存
	}
	echo '</table>';
}

/**
 * ----------------------------------------------------------
 * printCommendationVolumeResultTable()
 * 総合ボリューム賞を表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return
 * ----------------------------------------------------------
 */
function printCommendationVolumeResultTable($postArray, $listArray) {

	$rankingData = array();
	$calData = array();
	$sortedCalData = array();
	$itemList = array('LM',"LS","LT","LH","LO","LE","LL");

	// 各種目ごとの順位を算出するためのソート用配列の作成
	foreach($listArray['data_list'] as $key => $value) {
		$calData[]= array("eid" => $key, "LM" => $value["LM"]["result"], "LS" => $value["LS"]["result"], "LT" => $value["LT"]["result"], 
		                                 "LH" => $value["LH"]["result"], "LO" => $value["LO"]["result"], "LE" => $value["LE"]["result"], 
										 "LL" => $value["LL"]["result"]);
	}

	// 各種目の実績値順でソート
	foreach ($itemList as $item) {
		$sortedCalData[$item] = sortByKey($item, SORT_DESC, $calData);
	}

	// ランキング生成用配列の準備
	foreach($calData as $data) {
		$rankingData[]= array("eid" => $data["eid"], "volume" => 0, "operation" => 1);
	}

	// 各同友ごとの種目毎の順位を取得
	// 実績がない場合は順位は最下位
	foreach ($itemList as $item) {

		$rank = 1;			// 順位用（実績が同順位タイ）
		$index = 0;			// 配列カウント用

		foreach($sortedCalData[$item] as $data) {

			$volumePoint = 0;	
			$operationPoint = 1;
			
			// もし実績がゼロの場合は最下位（同友数を設定する）
			if ($data[$item] == config::COMMENDATION_NO_RESULT) {
				$volumePoint += count($calData);

				if ($item === "LM") {
					if ($data["LS"] < config::COMMENDATION_RESULT_MIN) {
						$operationPoint = 0;
					}
				}
				else if ($item === "LS") {
					if ($data["LM"] < config::COMMENDATION_RESULT_MIN) {
						$operationPoint = 0;
					}
				}
				else {
					$operationPoint = 0;		// 稼働判定(一度でも実績0があれば未稼働)
				}
			}
			else {
				// 実績がゼロではない場合
				// 一つ前の実績と現在の実績が同一かどうか確認し、同一ならランキングはそのまま、異なる場合はランキングを加算する
				if ($index > 0 && $data[$item] != $pre_result) {
					$rank = $index+1;
				}

				$volumePoint = $rank;
				//echo $item.":".$data["eid"]."=".$rank."<br>";	// デバッグ用：ボリューム賞の順位を表示
			}

			// ランキング用配列に格納
			$cnt = array_search($data["eid"], array_column($rankingData, 'eid')); // eidが入っている配列のインデックスを取得する
			$rankingData[$cnt]["volume"] += $volumePoint;
			if ($operationPoint == 0) {
				$rankingData[$cnt]["operation"] += $operationPoint;
			}

			// 一つ前の実績値を保存
			$pre_result = $data[$item];
			$index++;
		}
	}

	// 稼働判定があり、かつボリューム値の合計値で降順ソートする
	$volume_array = createArrayForSort('volume', $rankingData);
	$operation_array = createArrayForSort('operation', $rankingData);
	array_multisort($operation_array, SORT_DESC, $volume_array, SORT_ASC, $rankingData);

	// デバッグ
	//printArray($sortedCalData);
	//printArray($rankingData);

	echo '<table>';
	echo '<tr class="bg_wet_asphalt"><td>順位</td><td>同友名</td><td>ポイント</td></tr>';

	// 上位10位までを表示
	$rank = 1;
	$pre_result = 0;
	for ($i = 0; $i < config::COMMENDATION_TOP_NUM; $i++) {

		// 一つ前の実績と現在の実績が同一かどうか確認し、同一ならランキングはそのまま、異なる場合はランキングを加算する
		if ($i > 0 && $rankingData[$i]["volume"] != $pre_result) {
			$rank = $i+1;
		}

		// 該当する同友を検索
		$index = array_search($rankingData[$i]["eid"], array_column( $listArray['executive_list'], 'value'));
		echo '<tr><td class="right" style="width:30px">'.($rank).'</td>';
		echo '<td class="left" style="width:200px">'.$listArray['executive_list'][$index]["name"].'</td>';
		echo '<td class="right" style="width:80px">'. formatNumber($rankingData[$i]["volume"], 1, "floor").'</td></tr>';

		$pre_result = $rankingData[$i]["volume"];	// 実績を保存
	}
	echo '</table>';
}

/**
 * ----------------------------------------------------------
 * printCommendationAchievementResultTable()
 * 種目達成賞を表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return
 * ----------------------------------------------------------
 */
function printCommendationAchievementResultTable($postArray, $listArray) {

	$calData = array();

	// 稼働状況、達成種目数、達成率ポイントを計算する
	foreach($listArray['data_list'] as $key => $value) {

		$totalPoint = 0;
		$reachItemNum = 0;
		$operationPoint = 0;

		foreach ($value as $item => $data) {

			// 各同友の各種目の達成率ポイントを計算
			$point = floor(($data["result"] / $data["plan"]) * 1000) / 10;
			if ($point >= config::COMMENDATION_ACHIEVEMENT_RATIO_MAX) {
				$totalPoint += 300;	// 達成率が300%を超える場合は、300%を上限とする
			}
			else {
				$totalPoint += $point;
			}

			// 達成種目数を取得
			if ($point >= 100) {
				$reachItemNum++;
			}

			// 稼働状況判定
			if ($reachItemNum == config::COMMENDATION_ITEM_NUM_MAX) {
				$operationPoint = 1;
			}
		}

		// データ格納
		$calData[]= array("eid" => $key, "point" => $totalPoint, "reach_item_num" => $reachItemNum, "operation" => $operationPoint, "data" => $value);
	}

	// 稼働判定があり、かつポイントの合計値で降順ソートする
	$operation_array = createArrayForSort('operation', $calData);
	$reach_array = createArrayForSort('reach_item_num', $calData);
	$point_array = createArrayForSort('point', $calData);
	array_multisort($operation_array, SORT_DESC, $reach_array, SORT_DESC, $point_array, SORT_DESC, $calData);

	// デバッグ
	//printArray($calData);

	echo '<table>';
	echo '<tr class="bg_wet_asphalt"><td>達成数</td><td>同友名</td><td>ポイント</td></tr>';

	// 上位10位までを表示
	for ($i = 0; $i < count($calData); $i++) {
		$index = array_search($calData[$i]["eid"], array_column( $listArray['executive_list'], 'value'));
		echo '<tr><td class="right" style="width:50px">'.$calData[$i]["reach_item_num"].'</td>';
		echo '<td class="left" style="width:200px">'.$listArray['executive_list'][$index]["name"].'</td>';
		echo '<td class="right" style="width:80px">'. formatNumber($calData[$i]["point"], 1, "floor").'</td></tr>';
	}
	echo '</table>';

}

/**
 * ----------------------------------------------------------
 * printCommendationAchievementRatioResultTable()
 * 種目達成率賞（獲得同友）を表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return
 * ----------------------------------------------------------
 */
function printCommendationAchievementRatioResultTable($postArray, $listArray) {

	$calData = array();
	$calDataZero = array();

	// 稼働状況、達成種目数、達成率ポイントを計算する
	foreach($listArray['data_list'] as $key => $value) {

		$reachItemNum	   = 0;
		$operationPoint	   = 0;
		$pointDistribution = array(config::COMMENDATION_ACHIEVEMENT_RATIO_1 => 0, config::COMMENDATION_ACHIEVEMENT_RATIO_2 => 0, 
		                           config::COMMENDATION_ACHIEVEMENT_RATIO_3 => 0, config::COMMENDATION_ACHIEVEMENT_RATIO_4 => 0);

		foreach ($value as $item => $data) {

			// 各同友の各種目の達成率ポイントを計算
			$point = floor(($data["result"] / $data["plan"]) * 1000) / 10;
			if ($point >= config::COMMENDATION_ACHIEVEMENT_RATIO_4) {
				$pointDistribution[config::COMMENDATION_ACHIEVEMENT_RATIO_4]++;
			}
			else if ($point >= config::COMMENDATION_ACHIEVEMENT_RATIO_3) {
				$pointDistribution[config::COMMENDATION_ACHIEVEMENT_RATIO_3]++;
			}
			else if ($point >= config::COMMENDATION_ACHIEVEMENT_RATIO_2) {
				$pointDistribution[config::COMMENDATION_ACHIEVEMENT_RATIO_2]++;
			}
			else if ($point >= config::COMMENDATION_ACHIEVEMENT_RATIO_1) {
				$pointDistribution[config::COMMENDATION_ACHIEVEMENT_RATIO_1]++;
			}

			// 稼働種目数を取得
			if ($data["result"] == config::COMMENDATION_NO_RESULT) {
				if ($item === "LM") {
					if ($value["LS"]["result"] >= config::COMMENDATION_RESULT_MIN) {
						$reachItemNum++;
					}
				}
				else if ($item === "LS") {
					if ($value["LM"]["result"] >= config::COMMENDATION_RESULT_MIN) {
						$reachItemNum++;
					}
				}
			}
			else {
				$reachItemNum++;
			}

			// 稼働状況判定
			if ($reachItemNum == config::COMMENDATION_ITEM_NUM_MAX) {
				$operationPoint = 1;
			}
		}

		// 達成率の判定個数
		$total = $pointDistribution[config::COMMENDATION_ACHIEVEMENT_RATIO_1] + $pointDistribution[config::COMMENDATION_ACHIEVEMENT_RATIO_2]+ $pointDistribution[config::COMMENDATION_ACHIEVEMENT_RATIO_3] + $pointDistribution[config::COMMENDATION_ACHIEVEMENT_RATIO_4];

		// データ格納（稼働がある同友と、稼働がない同友でソート方法が異なるので分離）
		if ($total > 0) {
			$calData[]= array("eid" => $key, "reach_item_num" => $reachItemNum, "operation" => $operationPoint, "total" => $total, "dist" => $pointDistribution, "data" => $value);
		}
		else {
			$calDataZero[]= array("eid" => $key, "reach_item_num" => $reachItemNum, "operation" => $operationPoint, "total" => $total, "dist" => $pointDistribution, "data" => $value);
		}
	}

	// 稼働判定があり、かつ値の合計値で降順ソートする
	$operation_array = createArrayForSort('operation', $calData);
	$reach_array = createArrayForSort('reach_item_num', $calData);
	$total_array = createArrayForSort('total', $calData);
	array_multisort($operation_array, SORT_DESC, $total_array, SORT_DESC, $reach_array, SORT_DESC, $calData);

	// デバッグ
	//printArray($calData);

	// 表示
	echo '<table>';
	echo '<tr class="bg_wet_asphalt"><td>同友名</td><td>稼働判定</td><td>稼働数</td><td>110</td><td>120</td><td>150</td><td>200</td><td>計</td></tr>';

	for ($i = 0; $i < count($calData); $i++) {
		if ($calData[$i]["operation"] == 0) {
			$class = "bg_yellow";
		}
		$index = array_search($calData[$i]["eid"], array_column( $listArray['executive_list'], 'value'));
		echo '<tr class="'.$class.'"><td class="left" style="width:200px">'.$listArray['executive_list'][$index]["name"].'</td>';
		echo '<td class="right" style="width:50px">'.$calData[$i]["operation"].'</td>';
		echo '<td class="right" style="width:50px">'.$calData[$i]["reach_item_num"].'</td>';
		echo '<td class="right" style="width:50px">'.$calData[$i]["dist"][config::COMMENDATION_ACHIEVEMENT_RATIO_1].'</td>';
		echo '<td class="right" style="width:50px">'.$calData[$i]["dist"][config::COMMENDATION_ACHIEVEMENT_RATIO_2].'</td>';
		echo '<td class="right" style="width:50px">'.$calData[$i]["dist"][config::COMMENDATION_ACHIEVEMENT_RATIO_3].'</td>';
		echo '<td class="right" style="width:50px">'.$calData[$i]["dist"][config::COMMENDATION_ACHIEVEMENT_RATIO_4].'</td>';
		echo '<td class="right" style="width:50px">'.$calData[$i]["total"].'</td></tr>';
	}

	for ($i = 0; $i < count($calDataZero); $i++) {
		$index = array_search($calDataZero[$i]["eid"], array_column( $listArray['executive_list'], 'value'));
		echo '<tr class="bg_skyblue"><td class="left" style="width:200px">'.$listArray['executive_list'][$index]["name"].'</td>';
		echo '<td class="right" style="width:50px">'.$calDataZero[$i]["operation"].'</td>';
		echo '<td class="right" style="width:50px">'.$calDataZero[$i]["reach_item_num"].'</td>';
		echo '<td class="right" style="width:50px">'.$calDataZero[$i]["dist"][config::COMMENDATION_ACHIEVEMENT_RATIO_1].'</td>';
		echo '<td class="right" style="width:50px">'.$calDataZero[$i]["dist"][config::COMMENDATION_ACHIEVEMENT_RATIO_2].'</td>';
		echo '<td class="right" style="width:50px">'.$calDataZero[$i]["dist"][config::COMMENDATION_ACHIEVEMENT_RATIO_3].'</td>';
		echo '<td class="right" style="width:50px">'.$calDataZero[$i]["dist"][config::COMMENDATION_ACHIEVEMENT_RATIO_4].'</td>';
		echo '<td class="right" style="width:50px">'.$calDataZero[$i]["total"].'</td></tr>';
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
// 表彰状況 表示画面
//---------------------------------------------
if ($page === 'view') { ?>

<div id="contents_select">
	<form id='result_view' method="POST" action="index.php?reg=commendation&type=<?php echo $getArray['type']?>">
		<?php printSelectBox($listArray['fiscal_year_list'], 'fiscal_year', 70, $postArray['fiscal_year'], 'changeSelectBoxContents()') ?>年度
		<?php printSelectBox($listArray['campaign_list'], 'campaign', 180, $postArray['campaign'], 'changeSelectItemBoxDisplay()') ?>
		<?php printSubmitButton('表示', 'show') ?>
		<input type="hidden" name="command" value="show">
	</form>
</div>
<hr>

<div id="contents">
	<?php
	echo '<p>表彰内容を閲覧したい年度を選択し、「表示」ボタンを押してください。</p><br />';
	if (isset($postArray['command'])) {
	?>
		<hr><font class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の総合優績賞（ポイント）</font><hr>
		<?php printCommendationPointResultTable($postArray, $listArray); ?>

		<hr><font class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の総合ボリューム賞</font><hr>
		<?php printCommendationVolumeResultTable($postArray, $listArray); ?>

		<hr><font class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の種目達成賞</font><hr>
		<?php printCommendationAchievementResultTable($postArray, $listArray); ?>

		<hr><font class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の種目達成率賞（獲得同友）</font><hr>
		<?php printCommendationAchievementRatioResultTable($postArray, $listArray); ?>

	<?php } ?>
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