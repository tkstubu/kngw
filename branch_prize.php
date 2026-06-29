<?php
/**
 * =================================================================
 * branch_prize.php
 * 本部年間支援施策の状況表示と設定用 PHPスクリプト
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
	'fiscal_year_list' => array(),
	'data_list'        => array()
);

//--------------------------------
// 表示ページを選択
//--------------------------------
$page = selectPageForBranchPrize($getArray, $postArray, $listArray);

/**
 * ----------------------------------------------------------
 * selectPageForBranchPrize()
 * 本部年間支援施策の状況表示と設定で表示するページを選択する
 * @param $getArray：GETで送られてきたパラメータ
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function selectPageForBranchPrize($getArray, &$postArray, &$listArray) {

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
 * makeBranchPrizeViewPage()
 * 本部年間支援施策の獲得状況を表示する
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
	// 販促費では使用しない種目を削除
	foreach ($listArray['item_list'] as $key => $itemListArray) {
		if ($itemListArray['value'] === 'ALL' || $itemListArray['value'] === 'LH' || $itemListArray['value'] === 'LC' || $itemListArray['value'] === 'LEB') {
			unset($listArray['item_list'][$key]);
		}
	}

	// 提携企業一覧を取得
	if (isset($postArray['fiscal_year'])) {
		$listArray['partner_list'] = getPartnerList($postArray['fiscal_year'], $postArray['item']);
		if (count($listArray['partner_list']) == 0) {
			return 'no_partner';
		}
	}

	// 同友一覧を取得
	if (isset($postArray['fiscal_year'])) {
		$listArray['executive_list'] = getExecutiveList($postArray['fiscal_year'], 'campaign', '%');
		if (count($listArray['executive_list']) == 0) {
			return 'no_executive';
		}
	}
	
	// 表示ボタンを押された場合、データを読み込み
	if (isset($postArray['command'])) {

		// 種目毎の情報取得
		foreach ($listArray['item_list'] as $itemArray) {
			// 種目毎の月別の計画値と実績を取得。地域は%を指定することで全地域を指定
			$listArray['data_list'][$itemArray['value']] = getResultAndPlanByItem($postArray['fiscal_year'], $itemArray['value'], 'TOTAL', '%', true);
			//printArray($listArray['data_list']);
		// 	$data = getAreaTotalValue('TOTAL', $itemArray['value'], '%', $postArray['fiscal_year'], true);

		// 	// 初期化
		// 	$planTotal = array();
		// 	$resultTotal = array();
			
		// 	// 四半期ごとに合計値を作成
		// 	// 第一四半期から第三四半期
		// 	$quarter = 1;
		// 	for ($i = 4; $i <= 12; $i++) {
		// 		$planTotal[$quarter] += $data[0][$i.'_plan'];
		// 		$resultTotal[$quarter] += $data[0][$i.'_result'];

		// 		if ($i % 3 == 0) {
		// 			$quarter++;
		// 		}
		// 	}
		// 	// 第四四半期
		// 	$quarter = 4;
		// 	for ($i = 1; $i <= 3; $i++) {
		// 		$planTotal[$quarter] += $data[0][$i.'_plan'];
		// 		$resultTotal[$quarter] += $data[0][$i.'_result'];
		// 	}
			
		// 	// 四半期、種目ごとに合計値を保存
		// 	$listArray['data_list'][$itemArray['value']]['plan'] = $planTotal;
		// 	$listArray['data_list'][$itemArray['value']]['result'] = $resultTotal;

		// 	// 年間の実績を作成
		// 	for ($i = 1; $i <= 12; $i++) {
		// 		$listArray['data_list'][$itemArray['value']]['plan']['year'] += $data[0][$i.'_plan'];
		// 		$listArray['data_list'][$itemArray['value']]['result']['year'] += $data[0][$i.'_result'];
		// 	}
		}
		// printArray($listArray['data_list']);

		// LM未登録点のPIDを取得
		$lmmID = 0;
		$partnerList = getPartnerList($postArray['fiscal_year'], 'LM');
		foreach ($partnerList as $partnerArray) {
			if ($partnerArray['name'] === 'LM未登録店') {
				$lmmID = $partnerArray['value'];
			}
		}
		// LM未登録店のデータを取得
		$listArray['data_list']['LM-M'] = getResultAndPlanByItem($postArray['fiscal_year'], 'LM', $lmmID, '%', true);

		// LC保有枚数を取得
		$listArray['data_list'] += getLCHoldNumber($postArray['fiscal_year']);

		// 支部への販促費「四半期販促費」を取得する
		$listArray['branch_promotion'] = getBranchPromotionInfo($postArray['fiscal_year'],  'ryoritsuB');
		if (count($listArray['branch_promotion']) == 0) {
			return 'no_data';
		}

		// 支部への販促費「年間賞金」の設定値を取得する
		$listArray['year_promotion'] = getBranchPromotionInfo($postArray['fiscal_year'],  'ryoritsuY');
		if (count($listArray['year_promotion']) == 0) {
			return 'no_data';
		}

		// 支部への販促費「LC保有目標達成賞金」の設定値を取得する
		$listArray['lc_hold_number'] =  getLCHoldPromotionInfo($postArray['fiscal_year']);
		if (count($listArray['lc_hold_number']) == 0) {
			return 'no_data';
		}

		// 支部への販促費「販売施策のLOボーナス賞金、LH自動車特別賞、生産性＆ボリューム報奨金」の設定値を取得する
		$listArray['other_prize'] = getOtherBranchPromotionInfo($postArray['fiscal_year']);
		if (count($listArray['other_prize']) == 0) {
			return 'no_data';
		}

		// 分母同友数を取得
		// キャンペーンを通して分母同友数は変化しないので
		$baseEnterableNum = count(getCampaignExecutiveEnterableList($postArray['fiscal_year'], '%', 'summer'));
		$listArray['baseEnterableNum'] = $baseEnterableNum;
	}

	//printArray($listArray['fiscal_year_list']);
	//printArray($listArray['item_list']);
	//printArray($listArray['data_list']);
	//printArray($listArray['branch_promotion']);

	return $page;
}

/**
 * ----------------------------------------------------------
 * getAdjustedExecutiveLSQuarterResult()
 * LSの販促費の計算は、LSの実績値から補助科目の実績値を引いた値を使用する
 * @param $fiscal_year：年度
 * @param $executive_id：同友ID
 * @param $quarter：四半期
 * @return $adjusted_result：調整後のLS実績値
 * ----------------------------------------------------------
 */
function getAdjustedExecutiveLSQuarterResult($fiscal_year, $executive_id, $quarter) {

    $ls_data = getExecutiveResultTotalValue($fiscal_year, 'LS', '%', 'TOTAL', $executive_id);
    $sub_data1 = getExecutiveResultTotalValue($fiscal_year, 'LS:sub_2_1', '%', 'TOTAL', $executive_id); // 補助科目 2-1
    $sub_data2 = getExecutiveResultTotalValue($fiscal_year, 'LS:sub_2_4', '%', 'TOTAL', $executive_id); // 補助科目 2-4
    $sub_data3 = getExecutiveResultTotalValue($fiscal_year, 'LS:sub_2_5', '%', 'TOTAL', $executive_id); // 補助科目 2-5

    $ls_result = 0;
    $sub_result1 = 0;
    $sub_result2 = 0;
    $sub_result3 = 0;

    switch ($quarter) {
        case 1:
            $ls_result = $ls_data[0]['4_result'] + $ls_data[0]['5_result'] + $ls_data[0]['6_result'];
            $sub_result1 = $sub_data1[0]['4_result'] + $sub_data1[0]['5_result'] + $sub_data1[0]['6_result'];
            $sub_result2 = $sub_data2[0]['4_result'] + $sub_data2[0]['5_result'] + $sub_data2[0]['6_result'];
            $sub_result3 = $sub_data3[0]['4_result'] + $sub_data3[0]['5_result'] + $sub_data3[0]['6_result'];
            break;
        case 2:
            $ls_result = $ls_data[0]['7_result'] + $ls_data[0]['8_result'] + $ls_data[0]['9_result'];
            $sub_result1 = $sub_data1[0]['7_result'] + $sub_data1[0]['8_result'] + $sub_data1[0]['9_result'];
            $sub_result2 = $sub_data2[0]['7_result'] + $sub_data2[0]['8_result'] + $sub_data2[0]['9_result'];
            $sub_result3 = $sub_data3[0]['7_result'] + $sub_data3[0]['8_result'] + $sub_data3[0]['9_result'];
            break;
        case 3:
            $ls_result = $ls_data[0]['10_result'] + $ls_data[0]['11_result'] + $ls_data[0]['12_result'];
            $sub_result1 = $sub_data1[0]['10_result'] + $sub_data1[0]['11_result'] + $sub_data1[0]['12_result'];
            $sub_result2 = $sub_data2[0]['10_result'] + $sub_data2[0]['11_result'] + $sub_data2[0]['12_result'];
            $sub_result3 = $sub_data3[0]['10_result'] + $sub_data3[0]['11_result'] + $sub_data3[0]['12_result'];
            break;
        case 4:
            $ls_result = $ls_data[0]['1_result'] + $ls_data[0]['2_result'] + $ls_data[0]['3_result'];
            $sub_result1 = $sub_data1[0]['1_result'] + $sub_data1[0]['2_result'] + $sub_data1[0]['3_result'];
            $sub_result2 = $sub_data2[0]['1_result'] + $sub_data2[0]['2_result'] + $sub_data2[0]['3_result'];
            $sub_result3 = $sub_data3[0]['1_result'] + $sub_data3[0]['2_result'] + $sub_data3[0]['3_result'];
            break;
    }

    return max(0, $ls_result - ($sub_result1 + $sub_result2 + $sub_result3));
}

/**
 * ----------------------------------------------------------
 * printBranchPrizeResultTable()
 * 支部報奨金の実績結果を示すテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @param $type：表示種
 * @param $period：四半期表示期間
 * @return
 * ----------------------------------------------------------
 */
function printBranchPrizeResultTable($postArray, $listArray, $type, $period_num='') {

	// 四半期販促費の場合
	if ($type === 'ryoritsuB') {
		echo '<p><< 第'.$period_num.'四半期 >></p>';
	}

	// 期間を示す添字を作成
	$period = $period_num.'_quarter';

	echo '<table>';
	echo '<tr class="bg_wet_asphalt">
			<td>種目</td><td>単位</td><td>支部計画(千円)</td><td>実績(千円)</td><td>達成率</td><td>獲得報奨金(円)</td><td>適用料率(%)</td><td>目標達成率(%)</td><td>目標まで(千円)</td><td>目標達成時の報奨金(円)</td>
			</tr>';

	// 種目ごとに計画、実績、達成率を表示
	$itemCond = 0;
	$targetReach = 0;
	$targetRate = 0;
	$targetPrize = 0;
	$applicableReach = 0;
	foreach ($listArray['item_list'] as $itemArray) {

		// 特別キャンペーン(達成率に関わらず一律の料率を与える)
		// ※2020年のCOLVIC-19のための対策を2020年サマーキャンペーンにおいて実施
		if (local_config::FEATURE_FIXED_POINT_AND_RATE) {

			if ($itemArray['value'] !== 'LM' && $itemArray['value'] !== 'LS') {	// LMとLS以外に対して適用
				$cnt = 0;
				$flag_fixed_rate_type = false;
				foreach (config::QUARTER_FIXED_RATE_TYPE as $value) {
					if ($value == $postArray['fiscal_year'].'_'.$period_num) {
						// もし年度とキャンペーン種別が一致する組み合わせがあれば達成率に関わらず固定料率を設定する
						$listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_2'] = config::QUARTER_FIXED_RATE[$cnt][$itemArray['value']];
						$listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_4'] = config::QUARTER_FIXED_RATE[$cnt][$itemArray['value']];
						$listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_6'] = config::QUARTER_FIXED_RATE[$cnt][$itemArray['value']];
						$flag_fixed_rate_type = true;
						break;
					}
					$cnt++;
				}
			}
			//printArray($listArray['branch_promotion']);
		} // FEATURE_FIXED_POINT_AND_RATE

		// 達成率を計算
		if ($postArray['fiscal_year'] == 2023 && $period_num == 3 && $itemArray['value'] === 'LO') {
			$plan   = round($listArray['data_list'][$itemArray['value']]['plan']['10'] * 0.9, 0) + round($listArray['data_list'][$itemArray['value']]['plan']['11'] * 0.9, 0) + $listArray['data_list'][$itemArray['value']]['plan']['12'];
		}
		else {
			$plan   = $listArray['data_list'][$itemArray['value']]['plan'][$period];
		}
		$result = $listArray['data_list'][$itemArray['value']]['result'][$period];
		$reach  = ( $result / $plan) * 100;

		// 報奨金を計算
		$prize = 0;
		if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
			if ($itemArray['value'] === 'LS') {
				$adjusted_ls_result = 0;
				foreach ($listArray['executive_list'] as $executiveArray) {
					$adjusted_ls_result += getAdjustedExecutiveLSQuarterResult($postArray['fiscal_year'], $executiveArray['value'], $period_num);
				}
			}
			// 2020年秋キャンペーンから追加
			// LSの販促費の計算は、LSの実績値から補助科目の実績値を引いた値を使用する
			if ($postArray['fiscal_year'] >= 2020) {
				if ($itemArray['value'] === 'LS') {
					$prize = $adjusted_ls_result * $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_'.$period_num];
				}
				else {
					$prize = $listArray['data_list'][$itemArray['value']]['result'][$period] * $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_'.$period_num];
				}
			}
			else {
				if ($itemArray['value'] === 'LS') {
					$prize = $adjusted_ls_result * $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_1'];
				}
				else {
					$prize = $listArray['data_list'][$itemArray['value']]['result'][$period] * $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_1'];
				}
			}
			$applicableReach = '----';
		}
		else {
			// 該当する料率を判定
			for ($i = 1; $i <= 5; $i=$i+2) {
				if ($reach >= $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_'.$i] || $flag_fixed_rate_type) {
					$j = $i + 1;
					$prize =  ($result*1000) * ($listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_'.$j]/100);	// resultは1000円単位なので1000を掛ける
					$applicableReach = $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_'.$j]; //現在の料率
					$itemCond = $i;	// 種目毎の条件を満たした位置を保存
					break;
				}
				else {
					$applicableReach = 0;
					$itemCond = -1;	// 種目毎の条件を一つも満たせない場合は-1を入れる
				}
			}
		}

		// 目標達成率を計算
		if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
			$targetReach = '----';
		}
		else {
			if ($itemCond == 1) {
				$targetReach = '<font color="red">達成</font>';
				
			}
			elseif ($itemCond <= 5 && $itemCond > 0) {
				$target = $itemCond - 2;
				$targetReach = $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_'.$target];
				$target++;
				$targetRate  = $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_'.$target];
			}
			else {
				$targetReach = $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_5'];
				$targetRate  = $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_6'];
			} 
		}

		// 次の目標までの販売金額を算出
		if ($itemCond != 1 && ($itemArray['value'] !== 'LM' && $itemArray['value'] !== 'LS')) {
			$targetAmount = number_format(($targetReach * $plan)/100 - $result);

			// 達成時の報奨金を計算
			$targetPrize  = number_format(($plan/10) * $targetReach * $targetRate);
		}
		else {
			$targetAmount = '----';
			$targetPrize = '----';
		}

		//DBGMSG($itemArray['value'].' .$i='.$i.' reach='.$reach.' 料率='.$listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_'.$j].' itemCnd='.$itemCond);
		//DBGMSG('targetRate:'.$targetRate);

		// 特別キャンペーン(達成率に関わらず一律の料率を与える)
		$cell_color = "";
		if (local_config::FEATURE_FIXED_POINT_AND_RATE) {
			if ($flag_fixed_rate_type) {
				$cell_color = "bg_gray";
				$targetReach = "";
				$targetAmount = "";
			}
		}
		
		echo '
		<tr>
			<td width="50px">'.$itemArray['value'].'</td>
			<td width="50px">'.$itemArray['unit'].'</td>
			<td class="right" width="100px">'.number_format($plan, 0).'</td>
			<td class="right" width="100px">'.number_format($result, 0).'</td>
			<td class="right" width="70px">'.formatNumber($reach, 1, 'floor').'%</td>
			<td class="right bg_yellow" width="100px">'.number_format(round($prize)).'</td>
			<td class="right" width="50px">'.$applicableReach.'</td>
			<td class="right '.$cell_color.'" width="50px">'.$targetReach.'</td>
			<td class="right '.$cell_color.'" width="100px">'.$targetAmount.'</td>
			<td class="right" width="100px">'.$targetPrize.'</td>
		</tr>';
	}
	echo '</table><br />';
}

/**
 * ----------------------------------------------------------
 * printBranchLMLSPrizeResultTable()
 * LM、LSのボーナス賞金の実績結果を示すテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @param $period：1 上半期、2 下半期
 * @return
 * ----------------------------------------------------------
 */
function printBranchLMLSPrizeResultTable($postArray, $listArray, $period='') {

	//printArray($listArray['data_list']);

	if ($period == 1) {
		echo '<p><< 上半期 >></p>';
		$title_period1 = 1;
		$title_period2 = 2;
		$quarter1 = '1_quarter';
		$quarter2 = '2_quarter';

	}
	elseif ($period == 2) {
		echo '<p><< 下半期 >></p>';
		$title_period1 = 3;
		$title_period2 = 4;
		$quarter1 = '3_quarter';
		$quarter2 = '4_quarter';
	}

	$lm_result_1 = $listArray['data_list']['LM']['result'][$quarter1] - $listArray['data_list']['LM-M']['result'][$quarter1];	// 未登録店の登録を引く
	$lm_result_2 = $listArray['data_list']['LM']['result'][$quarter2] - $listArray['data_list']['LM-M']['result'][$quarter2];			// 未登録店の登録を引く
	$lm_result   = $lm_result_1 + $lm_result_2;
	$lm_prize    = $lm_result * $listArray['other_prize']['lm_bonus_prize_'.$period.'_half'];

	$ls_result_1 = $listArray['data_list']['LS']['result'][$quarter1];
	$ls_result_2 = $listArray['data_list']['LS']['result'][$quarter2];
	$ls_result   = $ls_result_1 + $ls_result_2;
	$ls_prize    = $ls_result * $listArray['other_prize']['ls_bonus_prize_'.$period.'_half'];
	
	echo '<table>';
	echo '<tr class="bg_wet_asphalt"><td>種目</td><td>第'.$title_period1.'四半期実績(台)</td><td>第'.$title_period2.'四半期実績(台)</td><td>実績合計(台)</td><td>1台あたりの賞金額(円)</td><td>獲得ボーナス(円)</td></tr>';
	echo '
	<tr>
		<td width="50px">LM</td>
		<td class="right" width="50px">'.formatNumber($lm_result_1).'</td>
		<td class="right" width="50px">'.formatNumber($lm_result_2).'</td>
		<td class="right" width="50px">'.formatNumber($lm_result).'</td>
		<td class="right" width="50px">'.formatNumber($listArray['other_prize']['lm_bonus_prize_'.$period.'_half']).'</td>
		<td class="right bg_yellow" width="50px">'.formatNumber($lm_prize).'</td>
	</tr>
	<tr>
		<td width="50px">LS</td>
		<td class="right" width="50px">'.formatNumber($ls_result_1).'</td>
		<td class="right" width="50px">'.formatNumber($ls_result_2).'</td>
		<td class="right" width="50px">'.formatNumber($ls_result).'</td>
		<td class="right" width="50px">'.formatNumber($listArray['other_prize']['ls_bonus_prize_'.$period.'_half']).'</td>
		<td class="right bg_yellow" width="50px">'.formatNumber($ls_prize).'</td>
	</tr>';
	echo '</table><br />';
}

/**
 * ----------------------------------------------------------
 * printBranchYearPrizeResultTable()
 * 支部報奨金の年間賞金の実績結果を示すテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return
 * ----------------------------------------------------------
 */
function printBranchYearPrizeResultTable($postArray, $listArray) {

	echo '<table>';
	echo '<tr class="bg_wet_asphalt">
			<td>種目</td><td>単位</td><td>支部計画(千円)</td><td>実績(千円)</td><td>達成率</td><td>獲得報奨金(円)</td><td>適用料率(%)</td><td>目標達成率(%)</td><td>目標まで(千円)</td><td>目標達成時の報奨金(円)</td>
			</tr>';

	// 種目ごとに計画、実績、達成率を表示
	$itemCond = 0;
	$applicableReach = 0;
	$targetReach = 0;
	$targetRate = 0;
	$targetPrize = 0;
	foreach ($listArray['item_list'] as $itemArray) {
		if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
			continue;
		}

		// 達成率を計算
		$result = $listArray['data_list'][$itemArray['value']]['result']['year'];
		$plan   = $listArray['data_list'][$itemArray['value']]['plan']['year'];
		$reach  = ($result / $plan) * 100;

		// 報奨金を計算
		$prize = 0;
		// 該当する料率を判定
		for ($i = 1; $i <= 5; $i=$i+2) {
			if ($reach >= $listArray['year_promotion'][$itemArray['value'].':ryoritsuY_'.$i]) {
				$j = $i + 1;
				$prize =  ($result*1000) * ($listArray['year_promotion'][$itemArray['value'].':ryoritsuY_'.$j]/100);
				$applicableReach = $listArray['year_promotion'][$itemArray['value'].':ryoritsuY_'.$j]; //現在の料率
				$itemCond = $i;	// 種目毎の条件を満たした位置を保存
				break;
			}
			else {
				$applicableReach = 0;
				$itemCond = -1;	// 種目毎の条件を一つも満たせない場合は-1を入れる
			}
		}

		// 目標を達成率を計算
		if ($itemCond == 1) {
			$targetReach = '<font color="red">達成</font>';
			
		}
		elseif ($itemCond <= 5 && $itemCond > 0) {
			$target = $itemCond - 2;
			$targetReach = $listArray['year_promotion'][$itemArray['value'].':ryoritsuY_'.$target];
			$target++;
			$targetRate  = $listArray['year_promotion'][$itemArray['value'].':ryoritsuY_'.$target];
		}
		else {
			$targetReach = $listArray['year_promotion'][$itemArray['value'].':ryoritsuY_5'];
			$targetRate  = $listArray['year_promotion'][$itemArray['value'].':ryoritsuY_6'];
		} 

		// 次の目標までの販売金額を算出
		$targetAmount = number_format(($targetReach * $plan)/100 - $result);

		// 達成時の報奨金を計算
		$targetPrize  = ($plan/10) * $targetReach * $targetRate;

		echo '
		<tr>
			<td width="50px">'.$itemArray['value'].'</td>
			<td width="50px">'.$itemArray['unit'].'</td>
			<td class="right" width="100px">'.number_format($plan, 0).'</td>
			<td class="right" width="100px">'.number_format($result, 0).'</td>
			<td class="right" width="70px">'.number_format($reach, 1).'%</td>
			<td class="right bg_yellow" width="100px">'.number_format(round($prize)).'</td>
			<td class="right" width="50px">'.$applicableReach.'</td>
			<td class="right" width="50px">'.$targetReach.'</td>
			<td class="right" width="100px">'.$targetAmount.'</td>
			<td class="right" width="100px">'.number_format(round($targetPrize)).'</td>
		</tr>';
	}
	echo '</table><br />';
}

/**
 * ----------------------------------------------------------
 * printBranchLCPrizeResultTable()
 * LC保有目標達成賞金を表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return
 * ----------------------------------------------------------
 */
function printBranchLCPrizeResultTable($postArray, $listArray) {

	$plan = $listArray['lc_hold_number']['lc_year_target_count'];
	$result = $listArray['data_list']['LC_hold_number'];
	$month = $listArray['data_list']['LC_hold_month'];

	echo '<table>';
	echo '<tr class="bg_wet_asphalt">
		  <td>支部年間保有<br />計画枚数(枚)</td><td>実績(枚) <br />※'.$postArray['fiscal_year'].'年度 '.$month.'月時点</td><td>達成率</td><td>獲得報奨金(円)</td><td>目標(%)</td><td>目標まで(枚)</td><td>目標達成時の報奨金(円)</td>
		  </tr>';

	// LC保有枚数達成率を計算
	//$result = 5903;	// デバッグ用
	$reach = ($result / $plan) * 100;

	// 報奨金の計算
	if ($reach >= $listArray['year_promotion']['LC:ryoritsuY_1']) {
		$prize        = $result * $listArray['year_promotion']['LC:ryoritsuY_2'];
		$targetReach  = '<font color="red">達成</font>';
		$targetAmount = "----";
		$targetPrize  = "----";
	}
	elseif ($reach >= $listArray['year_promotion']['LC:ryoritsuY_3']) {
		$prize        = $result * $listArray['year_promotion']['LC:ryoritsuY_4'];
		$targetReach  = $listArray['year_promotion']['LC:ryoritsuY_1'];
		$targetAmount = ($plan/100)*$listArray['year_promotion']['LC:ryoritsuY_1'] - $result;
		$targetPrize  = ($plan/100)*$listArray['year_promotion']['LC:ryoritsuY_1'] * $listArray['year_promotion']['LC:ryoritsuY_2'];
	}
	else {
		$prize = 0;
		$targetReach  = $listArray['year_promotion']['LC:ryoritsuY_3'];
		$targetAmount = ($plan/100)*$listArray['year_promotion']['LC:ryoritsuY_3'] - $result;
		$targetPrize  = ($plan/100)*$listArray['year_promotion']['LC:ryoritsuY_3'] * $listArray['year_promotion']['LC:ryoritsuY_4'];
	}

	echo '
	<tr>
		<td class="right" width="100px">'.number_format($plan, 0).'</td>
		<td class="right" width="100px">'.number_format($result, 0).'</td>
		<td class="right" width="70px">'.number_format($reach, 1).'%</td>
		<td class="right bg_yellow" width="100px">'.number_format(round($prize)).'</td>
		<td class="right" width="100px">'.$targetReach.'</td>
		<td class="right" width="100px">'.$targetAmount.'</td>
		<td class="right" width="100px">'.number_format(round($targetPrize)).'</td>
	</tr>';

	echo '</table><br />';
}

/**
 * ----------------------------------------------------------
 * printBranchSeisanVolumePrizeResultTable()
 * 生産性＆ボリューム報奨金を表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return
 * ----------------------------------------------------------
 */
function printBranchSeisanVolumePrizeResultTable($postArray, $listArray) {

	// 2021年以降は賞金額ではなく、順位を入力するように仕様変更
	if ($postArray['fiscal_year'] >= 2021) {
		// 0で初期化(支部数が増えても大丈夫なように要素数を70にしておく)
		$seisan_prize = array_fill(0,70,0);
		$volume_prize = array_fill(0,70,0);
		// 順位からの賞金変換テーブル
		$seisan_prize = config::SEISAN_PRIZE;
		$volume_prize = config::VOLUME_PRIZE;

		// 賞金額の入れ直し
		// LM+LS
		$listArray['other_prize']['lms_seisan_prize_1'] = $listArray['baseEnterableNum'] * $seisan_prize[$listArray['other_prize']['lms_seisan_prize_1']];
		$listArray['other_prize']['lms_seisan_prize_2'] = $listArray['baseEnterableNum'] * $seisan_prize[$listArray['other_prize']['lms_seisan_prize_2']];
		$listArray['other_prize']['lms_volume_prize_1'] = $volume_prize[$listArray['other_prize']['lms_volume_prize_1']];
		$listArray['other_prize']['lms_volume_prize_2'] = $volume_prize[$listArray['other_prize']['lms_volume_prize_2']];
		// LT
		$listArray['other_prize']['lt_seisan_prize_1'] = $listArray['baseEnterableNum'] * $seisan_prize[$listArray['other_prize']['lt_seisan_prize_1']];
		$listArray['other_prize']['lt_seisan_prize_2'] = $listArray['baseEnterableNum'] * $seisan_prize[$listArray['other_prize']['lt_seisan_prize_2']];
		$listArray['other_prize']['lt_volume_prize_1'] = $volume_prize[$listArray['other_prize']['lt_volume_prize_1']];
		$listArray['other_prize']['lt_volume_prize_2'] = $volume_prize[$listArray['other_prize']['lt_volume_prize_2']];
		// LH
		$listArray['other_prize']['lh_seisan_prize_1'] = $listArray['baseEnterableNum'] * $seisan_prize[$listArray['other_prize']['lh_seisan_prize_1']];
		$listArray['other_prize']['lh_seisan_prize_2'] = $listArray['baseEnterableNum'] * $seisan_prize[$listArray['other_prize']['lh_seisan_prize_2']];
		$listArray['other_prize']['lh_volume_prize_1'] = $volume_prize[$listArray['other_prize']['lh_volume_prize_1']];
		$listArray['other_prize']['lh_volume_prize_2'] = $volume_prize[$listArray['other_prize']['lh_volume_prize_2']];
		// LO
		$listArray['other_prize']['lo_seisan_prize_1'] = $listArray['baseEnterableNum'] * $seisan_prize[$listArray['other_prize']['lo_seisan_prize_1']];
		$listArray['other_prize']['lo_seisan_prize_2'] = $listArray['baseEnterableNum'] * $seisan_prize[$listArray['other_prize']['lo_seisan_prize_2']];
		$listArray['other_prize']['lo_volume_prize_1'] = $volume_prize[$listArray['other_prize']['lo_volume_prize_1']];
		$listArray['other_prize']['lo_volume_prize_2'] = $volume_prize[$listArray['other_prize']['lo_volume_prize_2']];
		// LE
		$listArray['other_prize']['le_seisan_prize_1'] = $listArray['baseEnterableNum'] * $seisan_prize[$listArray['other_prize']['le_seisan_prize_1']];
		$listArray['other_prize']['le_seisan_prize_2'] = $listArray['baseEnterableNum'] * $seisan_prize[$listArray['other_prize']['le_seisan_prize_2']];
		$listArray['other_prize']['le_volume_prize_1'] = $volume_prize[$listArray['other_prize']['le_volume_prize_1']];
		$listArray['other_prize']['le_volume_prize_2'] = $volume_prize[$listArray['other_prize']['le_volume_prize_2']];
		// LL
		$listArray['other_prize']['ll_seisan_prize_1'] = $listArray['baseEnterableNum'] * $seisan_prize[$listArray['other_prize']['ll_seisan_prize_1']];
		$listArray['other_prize']['ll_seisan_prize_2'] = $listArray['baseEnterableNum'] * $seisan_prize[$listArray['other_prize']['ll_seisan_prize_2']];
		$listArray['other_prize']['ll_volume_prize_1'] = $volume_prize[$listArray['other_prize']['ll_volume_prize_1']];
		$listArray['other_prize']['ll_volume_prize_2'] = $volume_prize[$listArray['other_prize']['ll_volume_prize_2']];
	}

	// 種目毎の獲得賞金を加算
	$listArray['prize_list']['LM+LS'] += round($listArray['other_prize']['lms_seisan_prize_1']);
	$listArray['prize_list']['LM+LS'] += round($listArray['other_prize']['lms_seisan_prize_2']);
	$listArray['prize_list']['LM+LS'] += round($listArray['other_prize']['lms_volume_prize_1']);
	$listArray['prize_list']['LM+LS'] += round($listArray['other_prize']['lms_volume_prize_2']);
	$listArray['prize_list']['LT'] += round($listArray['other_prize']['lt_seisan_prize_1']);
	$listArray['prize_list']['LT'] += round($listArray['other_prize']['lt_seisan_prize_2']);
	$listArray['prize_list']['LT'] += round($listArray['other_prize']['lt_volume_prize_1']);
	$listArray['prize_list']['LT'] += round($listArray['other_prize']['lt_volume_prize_2']);
	$listArray['prize_list']['LH'] += round($listArray['other_prize']['lh_seisan_prize_1']);
	$listArray['prize_list']['LH'] += round($listArray['other_prize']['lh_seisan_prize_2']);
	$listArray['prize_list']['LH'] += round($listArray['other_prize']['lh_volume_prize_1']);
	$listArray['prize_list']['LH'] += round($listArray['other_prize']['lh_volume_prize_2']);
	$listArray['prize_list']['LO'] += round($listArray['other_prize']['lo_seisan_prize_1']);
	$listArray['prize_list']['LO'] += round($listArray['other_prize']['lo_seisan_prize_2']);
	$listArray['prize_list']['LO'] += round($listArray['other_prize']['lo_volume_prize_1']);
	$listArray['prize_list']['LO'] += round($listArray['other_prize']['lo_volume_prize_2']);
	$listArray['prize_list']['LE'] += round($listArray['other_prize']['le_seisan_prize_1']);
	$listArray['prize_list']['LE'] += round($listArray['other_prize']['le_seisan_prize_2']);
	$listArray['prize_list']['LE'] += round($listArray['other_prize']['le_volume_prize_1']);
	$listArray['prize_list']['LE'] += round($listArray['other_prize']['le_volume_prize_2']);
	$listArray['prize_list']['LL'] += round($listArray['other_prize']['ll_seisan_prize_1']);
	$listArray['prize_list']['LL'] += round($listArray['other_prize']['ll_seisan_prize_2']);
	$listArray['prize_list']['LL'] += round($listArray['other_prize']['ll_volume_prize_1']);
	$listArray['prize_list']['LL'] += round($listArray['other_prize']['ll_volume_prize_2']);

	// 生産性・ボリューム褒賞金の合計
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['lms_seisan_prize_1']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['lms_seisan_prize_2']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['lms_volume_prize_1']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['lms_volume_prize_2']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['lt_seisan_prize_1']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['lt_seisan_prize_2']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['lt_volume_prize_1']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['lt_volume_prize_2']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['lh_seisan_prize_1']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['lh_seisan_prize_2']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['lh_volume_prize_1']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['lh_volume_prize_2']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['lo_seisan_prize_1']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['lo_seisan_prize_2']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['lo_volume_prize_1']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['lo_volume_prize_2']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['le_seisan_prize_1']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['le_seisan_prize_2']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['le_volume_prize_1']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['le_volume_prize_2']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['ll_seisan_prize_1']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['ll_seisan_prize_2']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['ll_volume_prize_1']);
	$listArray['prize_list']['seisan_volume'] += round($listArray['other_prize']['ll_volume_prize_2']);

	echo '<table>';
	echo '<tr>
	<td class="bg_wet_asphalt" style="width:150px;">項目</td>
	<td class="bg_wet_asphalt" style="width:80px;">LM+LS<br>[単位:円]</td><td class="bg_wet_asphalt" style="width:80px;">LT<br>[単位:円]</td>
	<td class="bg_wet_asphalt" style="width:80px;">LH自動車<br>[単位:円]</td><td class="bg_wet_asphalt" style="width:80px;">LO<br>[単位:円]</td>
	<td class="bg_wet_asphalt" style="width:80px;">LE<br>[単位:円]</td><td class="bg_wet_asphalt" style="width:80px;">LL<br>[単位:円]</td>
	</tr>';

	echo '
	<tr>
	<td style="width:150px;" class="left">生産性報奨金（上半期）</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['lms_seisan_prize_1']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['lt_seisan_prize_1']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['lh_seisan_prize_1']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['lo_seisan_prize_1']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['le_seisan_prize_1']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['ll_seisan_prize_1']).'</td>
	</tr>';	

	echo '
	<tr>
	<td style="width:150px;" class="left">生産性報奨金（下半期）</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['lms_seisan_prize_2']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['lt_seisan_prize_2']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['lh_seisan_prize_2']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['lo_seisan_prize_2']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['le_seisan_prize_2']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['ll_seisan_prize_2']).'</td>
	</tr>';	

	echo '
	<tr>
	<td style="width:150px;" class="left">ボリューム報奨金（上半期）</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['lms_volume_prize_1']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['lt_volume_prize_1']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['lh_volume_prize_1']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['lo_volume_prize_1']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['le_volume_prize_1']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['ll_volume_prize_1']).'</td>
	</tr>';	

	echo '
	<tr>
	<td style="width:150px;" class="left">ボリューム報奨金（下半期）</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['lms_volume_prize_2']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['lt_volume_prize_2']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['lh_volume_prize_2']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['lo_volume_prize_2']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['le_volume_prize_2']).'</td>
	<td style="width:80px;" class="right">'.number_format($listArray['other_prize']['ll_volume_prize_2']).'</td>
	</tr>';	

	echo '</table><br />';
}

/**
 * ----------------------------------------------------------
 * printExcutiveReachMinTargetTable()
 * 最低販売基準を達成している同友一覧を表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return
 * ----------------------------------------------------------
 */
function printExcutiveReachMinTargetTable($postArray, $listArray) {

	$minCount = array();
	$executiveData = array();

	// 種目取得
	$itemList = getItemList();
	if (count($itemList) == 0) {
		return 'no_item';
	}

	// 使用しない種目を削除
	foreach ($itemList as $key => $itemListArray) {
		if ($itemListArray['value'] === 'ALL' || $itemListArray['value'] === 'LC' || $itemListArray['value'] === 'LEB') {
			unset($itemList[$key]);
		}
	}

	// 同友最低販売基準を取得
	$mintarget = getExecutiveMinTargetInfo($postArray['fiscal_year']);
	//printArray($mintarget);

	// 同友毎の販売数を取得して、最低販売基準と比較
	foreach ($listArray['executive_list'] as $executiveArray) {
		// 初期化
		$resultTotalList = array();
		$data = array();

		// 種目ごとの合計値を取得
		foreach ($itemList as $itemArray) {
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
	array_unshift($itemList, array('value'=>'LM+LS',  'name'=>'LM+LS', 'unit'=>'台'));
	// 種目からLM、LSを削除
	$i = 0;
	foreach ($itemList as $itemArray) {
		if ($itemArray['value'] === 'ALL' || $itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
			unset($itemList[$i]);
		}
		$i++;
	}

	// 販売基準を達成しているかどうか判定し、カウント
	$executive_count = 0;
	foreach ($listArray['executive_list'] as $executiveArray) {
		// キャンペーン参加同友のみ実績を取得
		if($executiveArray['summer_enterable'] != config::STATUS_ENTERABLE || 
		   $executiveArray['autumn_enterable'] != config::STATUS_ENTERABLE || 
		   $executiveArray['spring_enterable'] != config::STATUS_ENTERABLE ) {
			continue;
		}
		foreach ($itemList as $itemArray) {
			if ($executiveData[$executiveArray['value']][$itemArray['value']] >= $mintarget[$itemArray['value']]) {
				$minCount[$itemArray['value']]++;
			}
		}

		$executive_count++;	// 販売基準の対象同友のみカウント
	}

	// $executive_count = 0;
	// foreach ($listArray['executive_list'] as $executiveArray) {
	// 	if($executiveArray['enterable'] == config::STATUS_ENTERABLE) {
	// 		$executive_count++;	// 販売基準の対象同友のみカウント
	// 	}
	// }

	// 全体の販売基準超え件数を表示
	echo '<table>';
	echo '<tr class="bg_wet_asphalt"><td style="width:180px;"></td>';
	foreach ($itemList as $itemArray) {
		echo '<td style="width:100px;">'.$itemArray['value'].'<br />[単位:'.$itemArray['unit'].']</td>';
	}
	echo '</tr>';
	echo '<tr><td class="left">同友最低販売基準 達成状況</td>';
	foreach ($itemList as $itemArray) {
		if ($minCount[$itemArray['value']] >= $executive_count) {
			echo '<td class="font_red">';	// 達成した場合は赤字
		}
		else {
			echo '<td>';
		}
		echo $minCount[$itemArray['value']].'/'.$executive_count.'</td>';
		
	}
	echo '</tr>';
	echo '<tr><td class="left">獲得報奨金（円）</td>';
	foreach ($itemList as $itemArray) {
		if ($minCount[$itemArray['value']] >= $executive_count) {
			$min_prize = $executive_count * $listArray['other_prize']['min_target_clear_prize'];
			echo '<td>'.formatNumber($min_prize).'円</td>';
		}
		else {
			echo '<td>0円</td>';
		}
	}
	echo '</tr>';
	echo '</table><br />';

	// 同友毎の状況を表示
	echo '<table>';
	echo '<tr class="bg_wet_asphalt"><td style="width:50px;" rowspan="2">同友<br />コード</td><td rowspan="2">販売基準<br />対象</td><td style="width:200px;"  rowspan="2">同友名</td>';
	foreach ($itemList as $itemArray) {
		echo '<td style="width:100px;">'.$itemArray['value'].'<br />[単位:'.$itemArray['unit'].']</td>';
	}
	echo '</tr><tr class="bg_wet_asphalt">';
	foreach ($itemList as $itemArray) {
		echo '<td>最低販売基準 '.$mintarget[$itemArray['value']].'</td>';
	}
	echo '</tr>';

	foreach ($listArray['executive_list'] as $executiveArray) {
		echo '<tr><td>'.$executiveArray['code'].'</td>';
		if($executiveArray['enterable'] == config::STATUS_ENTERABLE) {
			echo '<td>○</td>';	// 販売基準の対象同友
		}
		else{
			echo '<td></td>';	// 販売基準の対象外の同友
		}
		echo '<td class="left">'.$executiveArray['name'].'</td>';
		foreach ($itemList as $itemArray) {
			$e_result = $executiveData[$executiveArray['value']][$itemArray['value']];
			$min = $mintarget[$itemArray['value']];
			if ( $e_result >= $min) {
				echo '<td class="font_red">'.formatNumber($e_result).'</td>';
			}
			else {
				echo '<td>'.formatNumber($e_result).'</td>';
			}
		}
		echo '</tr>';
	}
	echo '</table><br />';
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
	<form id='result_view' method="POST" action="index.php?reg=branch_prize&type=<?php echo $getArray['type']?>">
		<?php printSelectBox($listArray['fiscal_year_list'], 'fiscal_year', 70, $postArray['fiscal_year'], 'changeSelectBoxContents()') ?>年度
		<?php printSubmitButton('表示', 'show') ?>
		<input type="hidden" name="command" value="show">
	</form>
</div>
<hr>

<div id="contents">
	<?php
	echo '<p>販促費を閲覧したい年度を選択し、「表示」ボタンを押してください。</p><br />';
	if (isset($postArray['command'])) {
	?>
		<hr><font class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の販促費獲得状況「四半期販促費」</font><hr>
		<p>計算方法：期間実績✕料率=賞金（円単位未満四捨五入)</p><br />
		<?php
			printBranchPrizeResultTable($postArray, $listArray, 'ryoritsuB', '1');
			printBranchPrizeResultTable($postArray, $listArray, 'ryoritsuB', '2');
			printBranchPrizeResultTable($postArray, $listArray, 'ryoritsuB', '3');
			printBranchPrizeResultTable($postArray, $listArray, 'ryoritsuB', '4');
		?>

		<hr><font class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度のLM・LSのボーナス賞金</font><hr>
		<p>計算方法：期間実績✕賞金単価=賞金（円単位未満四捨五入)</p><br />
		<?php printBranchLMLSPrizeResultTable($postArray, $listArray, 1); // 上半期 ?>
		<?php printBranchLMLSPrizeResultTable($postArray, $listArray, 2); // 下半期 ?>

		<hr><font class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度 LC保有目標達成賞金</font><hr>
		<p>計算方法：支部年間保有計画枚数の達成率に応じて、期末時点の保有合計枚数✕単価</p><br />
		<?php printBranchLCPrizeResultTable($postArray, $listArray); ?>

		<hr><font class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の販促費獲得状況「年間賞金」</font><hr>
		<p>計算方法：期間実績✕料率✕参加率(%)=賞金（円単位未満四捨五入)</p><br />
		<?php printBranchYearPrizeResultTable($postArray, $listArray); ?>

		<hr><font class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度 LOボーナス賞金</font><hr>
		<p>年度終了時に本社販売事業部会議にて決定</p><br />
		<table>
			<tr><td class="bg_wet_asphalt">項目</td><td class="bg_wet_asphalt">獲得報奨金(円)</td></tr>
			<tr>
			<td style="width:80px;" class="left">獲得賞金額</td>
			<td style="width:80px;" class="right"><?php echo number_format($listArray['other_prize']['lo_year_prize'])."円"?></td>
			</tr>
		</table><br />

		<hr><font class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度 LH自動車特別賞</font><hr>
		<p>キャンペーンにおいて、LH自動車優績を獲得した支部法人の達成順位に応じて決定</p><br />
		<table>
		<tr><td class="bg_wet_asphalt">項目</td><td class="bg_wet_asphalt">獲得報奨金(円)</td></tr>
			<tr>
			<td style="width:200px;" class="left">キャンペーン優績支部賞（サマー）</td>
			<td style="width:80px;" class="right"><?php echo number_format($listArray['other_prize']['lh_summer_prize'])."円"?></td>
			</tr>
			<tr>
			<td style="width:200px;" class="left">キャンペーン優績支部賞（秋）</td>
			<td style="width:80px;" class="right"><?php echo number_format($listArray['other_prize']['lh_autumn_prize'])."円"?></td>
			</tr>
			<tr>
			<td style="width:200px;" class="left">キャンペーン優績支部賞（春）</td>
			<td style="width:80px;" class="right"><?php echo number_format($listArray['other_prize']['lh_spring_prize'])."円"?></td>
			</tr>
			<tr>
			<td style="width:200px;" class="left">1,000万円未満同友解消支部賞</td>
			<td style="width:80px;" class="right"><?php echo number_format($listArray['other_prize']['lh_doyu_kaisyo_prize'])."円"?></td>
			</tr>	
		</table><br />

		<hr><font class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度 生産性＆ボリューム報奨金</font><hr>
		<p>半期実績の生産性、実績において上位10位以内の支部法人に対して発生</p><br />
		<?php printBranchSeisanVolumePrizeResultTable($postArray, $listArray); ?>

		<hr><font class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度 最低販売基準達成 同友一覧</font><hr>
		<p>年間において、各種目で支部内全同友（販売基準対象）が同友最低販売基準をクリアした場合、種目毎に販売基準同友数 ✕ 1,000円の褒賞金</p><br />
		<?php printExcutiveReachMinTargetTable($postArray, $listArray); ?>

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