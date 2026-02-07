<?php
/**
 * =================================================================
 * top.php
 * トップページ用PHPスクリプト
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
checkParameterIsSet($getArray['reg']);

//--------------------------------
// リスト初期化
//--------------------------------
$listArray = array (
	'fiscal_year_list' => array(),
	'item_list'        => array()
);

//--------------------------------
// グローバル変数
//--------------------------------
$itemAchievedValue = 0;
$itemClearValue = 0;

// ユーザ情報を取得
$userInfo = getUserInfo($_SESSION['USERID']);

// 機能有効時のみ処理
if (local_config::FEATURE_EXECUTIVE_RESULT_ON_TOP) {

	// 同友の場合は年間成績を表示
	if ($userInfo['auth'] == config::USER_EXECUTIVE) {
	
		// 作成済みのキャンペーンデータの年度を取得
		$listArray['fiscal_year_list'] = getFiscalYearList('campaign');
		if (count($listArray['fiscal_year_list']) == 0) {
			return 'no_data';
		}

		// キャンペーンリストを作成
		$listArray['campaign_list'][] = array('value' => 'NONE',   'name' => '---');
		$listArray['campaign_list'][] = array('value' => 'summer', 'name' => config::SUMMER_CAMPAIGN_NAME);
		$listArray['campaign_list'][] = array('value' => 'autumn', 'name' => config::AUTUMN_CAMPAIGN_NAME);
		$listArray['campaign_list'][] = array('value' => 'spring', 'name' => config::SPRING_CAMPAIGN_NAME);
	
		// 各キャンペーン用の種目を取得
		//$listArray['item_list'] = getItemList('no-insert');
		if (local_config::FEATURE_EXECUTIVE_RESULT_WITH_LC) {
			$listArray['item_list'] = getItemList('no-insert','',$postArray['fiscal_year'],true,false,false); // LC表示
		}
		else {
			$listArray['item_list'] = getItemList('no-insert','',$postArray['fiscal_year'],true,true,false);	// LC非表示
		}
		if (count($listArray['item_list']) == 0) {
			return 'no_item';
		}

		if (local_config::FEATURE_SALES_PROMOTION_FOR_EXECUTIVE) {
			$listArray['item_promo_list'] = getItemList('no-insert','',$postArray['fiscal_year'],false,true,true);	// 同友への販促用
		}
		
		// キャンペーン年間表示用の種目を取得
		$listArray['item_year_list'] = getItemList('no-insert','',$postArray['fiscal_year'],true,false,false);
		if (count($listArray['item_year_list']) == 0) {
			return 'no_item';
		}
	}
}

/**
 * ----------------------------------------------------------
 * printCampaignExecutiveResult()
 * 指定年度のキャンペーンの種目ごとの計画値と合計値を取得
 * @param $fiscal_year：指定年度
 * @param $campaign：キャンペーン種別
 * @param $itemList：種目一覧
 * @param $eid：同友ID
 * ----------------------------------------------------------
 */
function printCampaignOneExecutiveResult($fiscal_year, $campaign, $itemList, $eid) {
	
	// 初期化
	$LL_P1 = '';
	$LL_P2 = '';
	$planTotalList   = array();
	$resultTotalList = array();

	// キャンペーン種別判定
	switch($campaign) {
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

	// 同友一覧を取得
	$executiveList = getExecutiveList($fiscal_year, 'campaign');

	// 同友の指定キャンペーンの種目ごとの合計値を取得
	foreach($executiveList as $executiveArray) {
	
		// ログインしている同友以外は表示しない
		if ($executiveArray['value'] !== $eid) {
			continue;
		}
		
		// 全種目の計画値と合計値を計算
		foreach ($itemList as $itemArray) {
		
			// 初期化
			if (in_array($itemArray['value'], local_config::CAMPAIGN_ITEM_COMBINE)  && !local_config::FEATURE_COMMENDATION_FOR_HYOGO) {
				$planTotal = array();
				$resultTotal = array();
			}
			else {
				$planTotal = 0;
				$resultTotal = 0;
			}
		
			// 種目に該当する提携企業一覧を取得
			$partnerList = getPartnerList($fiscal_year, $itemArray['value']);
	
			// 提携企業数分の計画、実績、達成率を取得
			foreach ($partnerList as $partnerArray) {

				// 分離する種目の場合、提携企業毎に集計しておく
				if (in_array($itemArray['value'], local_config::CAMPAIGN_ITEM_COMBINE) && !local_config::FEATURE_COMMENDATION_FOR_HYOGO) {
					
					// ＜ 分離する場合 ＞

					// 種目がLLの時だけ
					// ジャックスとオリコを取得しておく
					if ($itemArray['value'] === 'LL' && strpos($partnerArray['name'],'ジャックス') !== false ) {
						$LL_P1 = $partnerArray['name'];
					}
					else if ($itemArray['value'] === 'LL' && strpos($partnerArray['name'],'オリ') !== false ) {
						$LL_P2 = $partnerArray['name'];
					}
					
					// ロートピアジの名前の場合は、ジャックス、またはオリコに名前を変更する
					if ($itemArray['value'] === 'LL' && strpos($partnerArray['name'],'ｼﾞｬｯｸｽ') !== false || strpos($partnerArray['name'],'ロートピア/J') ) {
						$partnerArray['name'] = $LL_P1;
					}
					else if ($itemArray['value'] === 'LL' && strpos($partnerArray['name'],'ｵﾘ') !== false || strpos($partnerArray['name'],'ロートピア/O') ) {
						$partnerArray['name'] = $LL_P2;
					}

					// 計画取得
					$planData = getCampaignPlanTotalValue($fiscal_year, '', $partnerArray['value'], '', $executiveArray['value']);
					$planTotal += array($partnerArray['name'] => $planData[$campaign.'_plan']);
					
					// 実績取得
					$resultData = getCampaignMonthTotalValue($fiscal_year, $itemArray['value'], $partnerArray['value'], '', $executiveArray['value'], $start_month, $end_month, false);
					$resultTotal += array($partnerArray['name'] => $resultData['result']);
				}
				else {
					
					// ＜ 統合の場合（=分離しない場合）＞

					// 計画取得
					$planData = getCampaignPlanTotalValue($fiscal_year, '', $partnerArray['value'], '', $executiveArray['value']);
					$planTotal += $planData[$campaign.'_plan'];
					
					// 実績取得
					// 2018年以前はキャンペーン期間中のLC枚数が条件
					if ($itemArray['value'] === 'LC' && $fiscal_year >= 2019 && local_config::$DB_TABLE_PREFIX === "kngw") {
						$data = getExecutiveResultTotalValue($fiscal_year, 'LC', '%', $partnerArray['value'], $executiveArray['value']);
						switch($campaign) {
							case 'summer':
								$LC_CntInCampaign = $data[0]['4_result'] + $data[0]['5_result'] + $data[0]['6_result'] + $data[0]['7_result'];
								break;
							case 'autumn':
								$LC_CntInCampaign = $data[0]['8_result'] + $data[0]['9_result'] + $data[0]['10_result'] + $data[0]['11_result'];
								break;
							case 'spring':
								$LC_CntInCampaign = $data[0]['12_result'] + $data[0]['1_result'] + $data[0]['2_result'] + $data[0]['3_result'];
								break;
						}
						$resultTotal += $LC_CntInCampaign;
					}
					else {
						$resultData = getCampaignMonthTotalValue($fiscal_year, $itemArray['value'], $partnerArray['value'], '', $executiveArray['value'], $start_month, $end_month, false);
						$resultTotal += $resultData['result'];
					}
				}
			}

			$planTotalList += array($itemArray['value'] => $planTotal);
			$resultTotalList += array($itemArray['value'] => $resultTotal);
		}
		break;
	}
	//printArray($planTotalList);
	//printArray($resultTotalList);

	if (!local_config::FEATURE_COMMENDATION_FOR_HYOGO){
		// 2015年以降はLM+LSを作る
		if ($fiscal_year >= config::CAMPAIGN_LMLS_ADD_YEAR) {
			
			$planTotalList['LM+LS'] = $planTotalList['LM'] + $planTotalList['LS'];
			$resultTotalList['LM+LS'] = $resultTotalList['LM'] + $resultTotalList['LS'];
			
			// itemListからLMとLSを削除
			$i = 0;
			foreach ($itemList as $itemArray) {
				if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
					unset($itemList[$i]);
				}
				$i++;
			}
		}
	}
	else {
		// ★兵庫支部専用：LM、LSは残して、LM+LSを削除
		unset($itemList[2]);
	}
	
	// 全種目を表示
	echo '<table>';
	
	// 種目名
	echo '<tr class="bg_wet_asphalt"><td style="width:120px;"></td>';
	foreach ($itemList as $itemArray) {
		$colspan = 1;
		if (in_array($itemArray['value'], local_config::CAMPAIGN_ITEM_COMBINE) && !local_config::FEATURE_COMMENDATION_FOR_HYOGO) {
			$colspan = count($planTotalList[$itemArray['value']]);
			//$colspan = 2;
		}
		echo '<td style="width:80px;" colspan='.$colspan.'>'.$itemArray['value'].'<br />[単位:'.$itemArray['unit'].']</td>';
	}
	echo '</tr>';

	// 分離・統合（分離の場合は提携企業名）
	echo '<tr class="bg_wet_asphalt"><td style="width:120px;"></td>';
	foreach ($itemList as $itemArray) {
		// 分離する場合は、提携企業名を表示する
		if (in_array($itemArray['value'], local_config::CAMPAIGN_ITEM_COMBINE) && !local_config::FEATURE_COMMENDATION_FOR_HYOGO) {
			foreach ($planTotalList[$itemArray['value']] as $key => $value) {
				echo '<td style="width:80px;">'.$key.'</td>';
			}
		}
		else {
			echo '<td style="width:80px;">合計</td>';
		}
	}
	echo '</tr>';
	
	// キャンペーン目標
	echo '<tr><td class="left">キャンペーン目標</td>';
	foreach ($itemList as $itemArray) {
		if (in_array($itemArray['value'], local_config::CAMPAIGN_ITEM_COMBINE) && !local_config::FEATURE_COMMENDATION_FOR_HYOGO) {
			foreach ($planTotalList[$itemArray['value']] as $key => $value) {
				echo '<td class="right bold bg_skyblue">'.formatNumber($planTotalList[$itemArray['value']][$key], 2).'</td>';
			}
		}
		else {
			echo '<td class="right bold bg_skyblue">'.formatNumber($planTotalList[$itemArray['value']], 2).'</td>';
		}
	}
	echo '</tr>';

	// 実績
	echo '<tr><td class="left">キャンペーン速報</td>';
	foreach ($itemList as $itemArray) {
		if (in_array($itemArray['value'], local_config::CAMPAIGN_ITEM_COMBINE) && !local_config::FEATURE_COMMENDATION_FOR_HYOGO) {
			foreach ($planTotalList[$itemArray['value']] as $key => $value) {
				echo '<td class="right">'.formatNumber($resultTotalList[$itemArray['value']][$key], 2).'</td>';
			}
		}
		else {
			echo '<td class="right">'.formatNumber($resultTotalList[$itemArray['value']], 2).'</td>';
		}
	}
	echo '</tr>';

	// 目標まで
	echo '<tr><td class="left">目標まであと</td>';
	foreach ($itemList as $itemArray) {
		if (in_array($itemArray['value'], local_config::CAMPAIGN_ITEM_COMBINE) && !local_config::FEATURE_COMMENDATION_FOR_HYOGO) {
			foreach ($planTotalList[$itemArray['value']] as $key => $value) {
				$diff = $planTotalList[$itemArray['value']][$key] - $resultTotalList[$itemArray['value']][$key];
				printReachScore($diff, $itemArray['value'], $resultTotalList[$itemArray['value']][$key], $planTotalList[$itemArray['value']][$key]);
			}
		}
		else {
			$diff = $planTotalList[$itemArray['value']] - $resultTotalList[$itemArray['value']];
			printReachScore($diff, $itemArray['value'], $resultTotalList[$itemArray['value']], $planTotalList[$itemArray['value']]);
		}
	}
	echo '</tr>';

	// 達成判定
	echo '<tr class="bg_yellow"><td>達成判定</td>';
	foreach ($itemList as $itemArray) {
		
		if (in_array($itemArray['value'], local_config::CAMPAIGN_ITEM_COMBINE) && !local_config::FEATURE_COMMENDATION_FOR_HYOGO) {
			printJudgeReach($fiscal_year, $campaign, $itemArray['value'], $resultTotalList[$itemArray['value']], $planTotalList[$itemArray['value']], true);
		}
		else {
			printJudgeReach($fiscal_year, $campaign, $itemArray['value'], $resultTotalList[$itemArray['value']], $planTotalList[$itemArray['value']]);
		}
	}
	echo '</tr>';

	echo '</table>';

	if (local_config::FEATURE_CHANGE_COLOR_ACCODING_TO_REACH) {
		printReachPattern();	// 達成率のパターン別の色分けサンプルを表示
	}
}

/**
 * ----------------------------------------------------------
 * printReachScore()
 * ----------------------------------------------------------
 */
function printReachScore($diff, $item, $result, $plan) {

	// 目標達成を判定
	if ($diff <= 0) {
		echo '<td class="right">';
	}
	else {
		if (local_config::FEATURE_CHANGE_COLOR_ACCODING_TO_REACH) {
			// 達成度に応じて背景の色を変える
			$quotient = $result / $plan;
			if ($quotient >= config::REACH_PATTERN_4) {
				echo '<td class="right bg_skyblue font_red bold">';
			}
			else if ($quotient >= config::REACH_PATTERN_3) {
				echo '<td class="right bg_skyblue">';
			}
			else if ($quotient >= config::REACH_PATTERN_2) {
				echo '<td class="right bg_green">';
			}
			else if ($quotient >= config::REACH_PATTERN_1) {
				echo '<td class="right bg_orange">';
			}
			else {
				echo '<td class="right bg_red">';
			}
		}
		else {
			echo '<td class="right bg_red">';
		}
	}
	echo formatNumber($diff, 2).'</td>';
}

/**
 * ----------------------------------------------------------
 * printJudgeReach()
 * ----------------------------------------------------------
 */
function printJudgeReach($fiscal_year, $campaign, $item, $result, $plan, $sepalate=false) {
	
	global $itemAchievedValue;
	global $itemClearValue;

	$colspan = 1;

	// 分離・統合によって差分の計算方法を変更
	if ($sepalate) {
		//$colspan = 2;
		$colspan = count($result);
		$r_total = 0;
		$p_total = 0;
		foreach ($result as $key => $value) {
			$r_total += $value;
		}
		foreach ($plan as $key => $value) {
			$p_total += $value;
		}
		$plan = $p_total;
		$result = $r_total;
	}
	// 計画から実績の差分を取得
	$diff = $plan - $result;

	// 目標達成を判定（目標達成カウントにLEB、LCは含めない）
	if ($diff <= 0 && $result > 0 && $plan > 0) {
		echo '<td class="font_red" colspan='.$colspan.'>達成!</td>';
		if ($item !== "LC" && $item !== "LEB") {
			$itemAchievedValue++;	// 種目別の達成件数をカウント
			$itemClearValue++;		// 稼働条件クリアをカウント
		}
	}
	else if ($plan == 0) {
		// 計画がないところはハイフン表示
		echo '<td style="text-align:center" colspan='.$colspan.'>----</td>';
	}
	// 目標は達成しているが、稼働条件を達成しているか判定
	else if ($result > 0) {
		echo '<td colspan='.$colspan.'></td>';
		if ($item !== "LC" && $item !== "LEB") {
			$itemClearValue++;		// 稼働条件クリアをカウント
		}
	}
	else {
		// 稼働判定(準パーフェクト賞はすべての種目で稼働していることが条件。2018年秋キャンペーンのみ)
		if ($fiscal_year == 2018 && $campaign === "autumn"){
			echo '<td class="font_blue" colspan='.$colspan.'>未稼働</td>';
		}
		else {
			echo '<td colspan='.$colspan.'></td>';
		}
	}
}

/**
 * ----------------------------------------------------------
 * printCampaignOneExecutiveLCTarget()
 * 指定年キャンペーンにおけるLCの達成状況を表示
 * @param $fiscal_year：指定年度
 * @param $campaign：キャンペーン
 * @param $eid：同友ID
 * ----------------------------------------------------------
 */
function printCampaignOneExecutiveLCTarget($fiscal_year, $campaign, $eid) {

	// 初期化
	$data = array();
	global $itemAchievedValue;
	global $itemClearValue;

	// キャンペーン種別判定
	switch($campaign) {
		case 'summer':
			$loopstart_month = 4;	// 4月から7月までを集計
			$loopend_month = config::SUMMER_CAMPAIGN_END_MONTH;;
			$start_month = config::SUMMER_CAMPAIGN_START_MONTH;
			$end_month = config::SUMMER_CAMPAIGN_END_MONTH;
			break;
		case 'autumn':
			$loopstart_month = 4;	// 4月から11月までを集計
			$loopend_month = config::AUTUMN_CAMPAIGN_END_MONTH;
			$start_month = config::AUTUMN_CAMPAIGN_START_MONTH;
			$end_month = config::AUTUMN_CAMPAIGN_END_MONTH;
			break;
		case 'spring':
			$loopstart_month = 1;	// 春の場合は全期間（4月から3月まで）が対象
			$loopend_month = 12;
			$start_month = config::SPRING_CAMPAIGN_START_MONTH;
			$end_month = config::SPRING_CAMPAIGN_END_MONTH;
			break;
	}

	// LCの月毎の枚数を取得
	$data = getExecutiveResultTotalValue($fiscal_year, 'LC', '%', 'TOTAL', $eid);
	
	// キャンペーン期間中のLCの枚数を取得（支部特別施策向けの数値）
	if ($fiscal_year <= 2018) {
		// 2018年以前はキャンペーン期間中のLC枚数が条件
		$LC_CntInCampaign = $data[0][$start_month.'_result'] + $data[0][$end_month.'_result'];
	}
	else {
		// 2019年からはそれぞれの三半期
		switch($campaign) {
			case 'summer':
				$LC_CntInCampaign = $data[0]['4_result'] + $data[0]['5_result'] + $data[0]['6_result'] + $data[0]['7_result'];
				break;
			case 'autumn':
				$LC_CntInCampaign = $data[0]['8_result'] + $data[0]['9_result'] + $data[0]['10_result'] + $data[0]['11_result'];
				break;
			case 'spring':
				$LC_CntInCampaign = $data[0]['12_result'] + $data[0]['1_result'] + $data[0]['2_result'] + $data[0]['3_result'];
				break;
		}
	}

	// キャンペーン終了月までのLCの枚数を取得（達成賞向けの数値）
	$LC_Cnt = 0;
	// 2021年度以降はLCキャッシュバックの条件（キャンペーンに対応した三半期の実績）を適用する
	if ($fiscal_year >= 2021) {
		$LC_Cnt = $LC_CntInCampaign;
	}
	else {
		for ($i = $loopstart_month; $i <= $loopend_month; $i++) {
			$LC_Cnt += $data[0][$i.'_result'];
		}
	}
	
	// デバッグ
	//printArray($data);
	//echo 'LC_CntInCampaign='.$LC_CntInCampaign."<br />";
	//echo 'LC_Cnt='.$LC_Cnt."<br />";

	// キャンペーン情報からLC達成目標枚数を取得
	$data = getCampaignInfo($fiscal_year, 'LM');
	if ($data[$campaign.'_opt'] !== '') {
		$opt = $data[$campaign.'_opt'];
		$optArray = preg_split("/-/", $opt);
		$areaMustLCCnt = preg_split("/\,/", $optArray[3]);			// 支部特別施策向け目標
		$areaMustLCCntforAward = preg_split("/\,/", $optArray[4]);	// 達成賞（パーフェクト賞、準パーフェクト賞）向けの目標
	}
	else {
		$areaMustLCCnt[1] = 0;
		$areaMustLCCntforAward[1] = 0;
	}
	
	//===================================
	// LCキャッシュバック条件達成状況
	//===================================
	echo '<&nbsp;LCキャッシュバック条件達成状況&nbsp;>';
	echo '<table>';
	
	// 項目
	echo '<tr class="bg_wet_asphalt"><td style="width:120px;"></td>';
	echo '<td style="width:80px;">目標枚数</td><td style="width:80px;">達成状況</td><td style="width:80px;">達成判定</td>';
	echo '</tr>';
	
	// 支部特別施策向け
	echo '<tr><td class="left">支部特別施策</td>';
	echo '<td class="right">'.formatNumber($areaMustLCCnt[1], 2).'</td>';
	echo '<td class="right">'.formatNumber($LC_CntInCampaign, 2).'</td>';
	// 達成判定
	if ($areaMustLCCnt[1] > $LC_CntInCampaign) {
		$diff = $areaMustLCCnt[1] - $LC_CntInCampaign;	// 目標との差分を取得
		echo '<td class="font_red">あと'.$diff.'枚</td>';
	}
	else {
		echo '<td class="font_red">達成!</td>';
	}
	echo '</tr></table><br />';

	//===================================
	// パーフェクト賞達成状況
	//===================================
	echo '<&nbsp;パーフェクト賞&nbsp;>';
	echo '<table>';

	// 項目
	echo '<tr class="bg_wet_asphalt"><td style="width:120px;"></td>';
	echo '<td style="width:80px;">目標</td><td style="width:80px;">達成状況</td>';
	if ($fiscal_year == 2018 && $campaign === "autumn"){
		echo '<td style="width:80px;">稼働状況</td>';
	}
	echo '<td style="width:80px;">達成判定</td></tr>';

	// パーフェクト賞
	// 達成状況取得
	$Item_diff = config::PERFECT_AWARD_ITEM_NUM - $itemAchievedValue;
	$LC_diff = $areaMustLCCntforAward[1] - $LC_Cnt;
	echo '<tr><td class="left">パーフェクト賞</td>';
	echo '<td class="right">'.config::PERFECT_AWARD_ITEM_NUM.'種目<br />LC '.$areaMustLCCntforAward[1].'枚</td>';
	echo '<td class="right">'.$itemAchievedValue.'種目達成<br />LC '.$LC_Cnt.'枚達成</td>';
	if ($fiscal_year == 2018 && $campaign === "autumn"){
		// 稼働判定(準パーフェクト賞はすべての種目で稼働していることが条件。2018年秋キャンペーンのみ)
		echo '<td class="right">'.$itemClearValue.'種目達成</td>';
	}

	// 達成判定
	if ( $Item_diff > 0 && $LC_diff > 0) {
		echo '<td class="font_red">あと'.$Item_diff.'種目<br />あとLC '.$LC_diff.'枚</td>';
	}
	else if( $Item_diff > 0 && $LC_diff <= 0) {
		echo '<td class="font_red">あと'.$Item_diff.'種目<br />LC目標達成！</td>';
	}
	else if ( $Item_diff <= 0 && $LC_diff > 0) {
		echo '<td class="font_red">種目目標達成！<br />あとLC'.$LC_diff.'枚</td>';
	}
	else {
		echo '<td class="font_red">達成!</td>';
	}
	echo '</tr>';

	// 準パーフェクト賞
	// 達成状況取得
	$Item_diff = config::SECOND_PERFECT_AWARD_ITEM_NUM - $itemAchievedValue;
	$Item_clr_diff = config::PERFECT_AWARD_ITEM_NUM - $itemClearValue;
	$LC_diff = $areaMustLCCntforAward[1] - $LC_Cnt;
	echo '<tr><td class="left">準パーフェクト賞</td>';
	echo '<td class="right">'.config::SECOND_PERFECT_AWARD_ITEM_NUM.'種目<br />LC '.$areaMustLCCntforAward[1].'枚</td>';
	echo '<td class="right">'.$itemAchievedValue.'種目達成<br />LC '.$LC_Cnt.'枚達成</td>';
	if ($fiscal_year == 2018 && $campaign === "autumn"){
		// 稼働判定(準パーフェクト賞はすべての種目で稼働していることが条件。2018年秋キャンペーンのみ)
		echo '<td class="right">'.$itemClearValue.'種目達成</td>';
	}

	// 達成判定
	if ( $Item_diff > 0 && $LC_diff > 0) {
		echo '<td class="font_red">あと'.$Item_diff.'種目<br />あとLC '.$LC_diff.'枚<br />';
	}
	else if( $Item_diff > 0 && $LC_diff <= 0) {
		echo '<td class="font_red">あと'.$Item_diff.'種目<br />LC目標達成！<br />';
	}
	else if ( $Item_diff <= 0 && $LC_diff > 0) {
		echo '<td class="font_red">種目目標達成！<br />あとLC'.$LC_diff.'枚<br />';
	}
	else {
		echo '<td class="font_red">達成!<br />';
	}

	if ($fiscal_year == 2018 && $campaign === "autumn"){
		// 稼働判定(準パーフェクト賞はすべての種目で稼働していることが条件。2018年秋キャンペーンのみ)
		if ($Item_clr_diff == 0) {
			echo '稼働達成</td>';
		}
		else {
			echo 'あと稼働'.$Item_clr_diff.'種目</td>';
		}
	}
	else {
		echo '</td>';
	}

	echo '</tr>';

	echo '</table>';
}

/**
 * ----------------------------------------------------------
 * printOneExecutiveLCHoldNumber()
 * 指定年度の同友のLC保有枚数を取得
 * @param $fiscal_year：指定年度
 * @param $eid：同友ID
 * ----------------------------------------------------------
 */
function printOneExecutiveLCHoldNumber($fiscal_year, $eid) {

	// 初期化
	$LC_Total = 0;
	$LC_NewTotal = 0;
	$data = array();

	// LC保有枚数取得
	$data = getExecutiveResultTotalValue($fiscal_year, 'LC:spitem', '%', 'TOTAL', $eid);
	//printArray($data);

	// 今月の月を取得
	date_default_timezone_set('Asia/Tokyo');
	$dt = new DateTime();
	$c_month = $dt->format('n');

	//DBGMSG("fiscal_year=".$fiscal_year." c_month=".$c_month);

	// 最新月の情報を取得
	// 最新月のデータが0の場合はまだ未入力として前月のものを表示
	$LC_Total = $data[0][$c_month.'_result'];
	if ($LC_Total == 0) {
		if ($c_month == 1) {
			$c_month = 12;
		}
		else {
			$c_month--;
		}
		$LC_Total = $data[0][$c_month.'_result'];
	}
	//$LC_Total = 210; // デバッグ用

	// LC関連の設定値を取得
	$lcHoldPromotionData = getLCHoldPromotionInfo($fiscal_year);
	//printArray($lcHoldPromotionData);

	// LC保有支援金を計算
	if ($lcHoldPromotionData['lc_hold_support_count'] <= $LC_Total) {
		$lcHoldPrize = number_format($lcHoldPromotionData['lc_hold_support_cost'] * $LC_Total);
	}
	else {
		$lcHoldPrize = number_format($lcHoldPromotionData['lc_hold_support_cost'] * $lcHoldPromotionData['lc_hold_support_count']);
		$lcHoldPrize = '目標達成時の賞金額は'.$lcHoldPrize;
	}

	// LC保有支援金達成状況
	$remain = $lcHoldPromotionData['lc_hold_support_count'] - $LC_Total;
	if ($remain > 0) {
		$LC_status = '目標まであと'.$remain.'枚';
	}
	else {
		$LC_status = '<font color="red">達成！</font>';
	}

	// LC獲得推進費
	$data = getExecutiveResultTotalValue($fiscal_year, 'LC', '%', 'TOTAL', $eid);
	for ($i = 1; $i <= 12; $i++){
		$LC_NewTotal += $data[0][$i.'_result'];
	}
	//printArray($data);
	$lcNewPrize = $lcHoldPromotionData['lc_get_promotion_cost'] * $LC_NewTotal;

	// LC獲得推進費
	echo '<p class="bold">＜ LC獲得推進費 ＞</p>';
	echo '<p>年間の新規獲得枚数に対し、一律単価で支払いが発生。</p>';
	echo '<p>計算方法：期間実績 ✕ 単価('.$lcHoldPromotionData['lc_get_promotion_cost'].'円)';
	echo '<table>';
	echo '<tr><td style="width:150px" class="left">LC獲得推進費（新規 '.$LC_NewTotal.'枚）</td><td style="width:70px;" class="right">'.number_format($lcNewPrize).'円</td></tr>';
	echo '</table><br />';

	echo '<p class="bold">＜ LC保有支援金（'.$c_month.'月時点）＞</p>';
	echo '<p>期末時点のロータスカード保有枚数'.$lcHoldPromotionData['lc_hold_support_count'].'枚以上の同友企業に対して支援金が発生。</p>';
	echo '<p>計算方法：期間実績 ✕ 単価('.$lcHoldPromotionData['lc_hold_support_cost'].'円)</p>';
	
	// LC保有支援金
	echo '<table>';
	echo '<tr class="bg_wet_asphalt">';
	echo '<td style="width:80px;">LC保有枚数</td><td style="width:80px;">状況</td><td style="width:80px;">獲得賞金額</td>';
	echo '</tr><tr>';

	echo '<td class="right">'.number_format($LC_Total).'枚</td>';
	echo '<td class="right">'.$LC_status.'</td>';
	echo '<td class="right">'.$lcHoldPrize.'円</td>';
	echo '</tr></table><br />';
}

/**
 * ----------------------------------------------------------
 * printOneExecutiveResult()
 * 指定年度の種目ごとの年間の計画値と合計値を取得
 * @param $fiscal_year：指定年度
 * @param $itemList：種目一覧
 * @param $eid：同友ID
 * ----------------------------------------------------------
 */
function printOneExecutiveResult($fiscal_year, $itemList, $eid) {

	global $itemAchievedValue;

	// 初期化
	$planTotalList   = array();
	$resultTotalList = array();
	$data = array();

	// 種目ごとの合計値を取得
	foreach ($itemList as $itemArray) {
		
		// 初期化
		if (!in_array($itemArray['value'], local_config::PLAN_ITEM_COMBINE)) {
			$planTotal = array();
			$resultTotal = array();
		}
		else {
			$planTotal = 0;
			$resultTotal = 0;
		}

		// 計画と実績を取得
		if (!in_array($itemArray['value'], local_config::PLAN_ITEM_COMBINE)) {

			// 種目に該当する提携企業一覧を取得
			$partnerList = getPartnerList($fiscal_year, $itemArray['value']);

			// 提携企業数分の計画、実績、達成率を取得
			foreach ($partnerList as $partnerArray) {
				$data = getExecutiveResultTotalValue($fiscal_year, $itemArray['value'], '%', $partnerArray['value'], $eid);
				//printArray($data);

				// 種目がLLの時だけ
				// ジャックスとオリコを取得しておく
				if ($itemArray['value'] === 'LL' && strpos($partnerArray['name'],'ジャックス') !== false ) {
					$LL_P1 = $partnerArray['name'];
				}
				else if ($itemArray['value'] === 'LL' && strpos($partnerArray['name'],'オリ') !== false ) {
					$LL_P2 = $partnerArray['name'];
				}
				
				// ロートピアジの名前の場合は、ジャックス、またはオリコに名前を変更する
				if ($itemArray['value'] === 'LL' && strpos($partnerArray['name'],'ｼﾞｬｯｸｽ') !== false || strpos($partnerArray['name'],'ロートピア/J') ) {
					$partnerArray['name'] = $LL_P1;
				}
				else if ($itemArray['value'] === 'LL' && strpos($partnerArray['name'],'ｵﾘ') !== false || strpos($partnerArray['name'],'ロートピア/O') ) {
					$partnerArray['name'] = $LL_P2;
				}
				
				// 12ヶ月分を加算
				for ($i = 1; $i < 13; $i++) {
					$planTotal[$partnerArray['name']] += $data[0][$i.'_plan'];
					$resultTotal[$partnerArray['name']] += $data[0][$i.'_result'];
				}
			}
		}
		else {
			$data = getExecutiveResultTotalValue($fiscal_year, $itemArray['value'], '%', 'TOTAL', $eid);
			//printArray($data);
			
			// 12ヶ月分を加算
			for ($i = 1; $i < 13; $i++) {
				$planTotal += $data[0][$i.'_plan'];
				$resultTotal += $data[0][$i.'_result'];
			}
		}
		
		// 種目ごとに合計値を保存
		$planTotalList += array($itemArray['value'] => $planTotal);
		$resultTotalList += array($itemArray['value'] => $resultTotal);
	}
	
	// 2015年以降はLM+LSを作り、LM、LSを削除
	if ($fiscal_year >= config::CAMPAIGN_LMLS_ADD_YEAR) {
		
		$planTotalList['LM+LS'] = $planTotalList['LM'] + $planTotalList['LS'];
		$resultTotalList['LM+LS'] = $resultTotalList['LM'] + $resultTotalList['LS'];
		
		// itemListからLMとLSを削除（2015年移行の対策）
		$i = 0;
		foreach ($itemList as $itemArray) {
			if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
				unset($itemList[$i]);
			}
			$i++;
		}
	}
	// デバッグ
	//printArray($planTotalList);
	//printArray($resultTotalList);

	// 全種目を表示
	echo '<table>';
	
	// 種目名
	echo '<tr class="bg_wet_asphalt"><td style="width:120px;"></td>';
	foreach ($itemList as $itemArray) {
		$colspan = 1;
		if (!in_array($itemArray['value'], local_config::PLAN_ITEM_COMBINE)) {
			// 分離する時
			$colspan = 2;
		}
		echo '<td style="width:80px;" colspan='.$colspan.'>'.$itemArray['value'].'<br />[単位:'.$itemArray['unit'].']</td>';
	}
	echo '</tr>';

	// 分離・統合（分離の場合は提携企業名）
	echo '<tr class="bg_wet_asphalt"><td style="width:120px;"></td>';
	foreach ($itemList as $itemArray) {
		// 分離する場合は、提携企業名を表示する
		if (!in_array($itemArray['value'], local_config::PLAN_ITEM_COMBINE)) {
			foreach ($planTotalList[$itemArray['value']] as $key => $value) {
				echo '<td style="width:80px;">'.$key.'</td>';
			}
		}
		else {
			echo '<td style="width:80px;">合計</td>';
		}
	}
	echo '</tr>';

	// 同友最低販売基準の表示
	if (local_config::FEATURE_EXECUTIVE_ITEM_MIN_TARGET) {
		// 同友最低販売基準を取得
		$mintarget = getExecutiveMinTargetInfo($fiscal_year);
		//printArray($mintarget);
		echo '<tr><td class="left">同友最低販売基準</td>';
		foreach ($itemList as $itemArray) {
			$colspan = 1;
			if (!in_array($itemArray['value'], local_config::PLAN_ITEM_COMBINE)) {
				// 分離する時
				$colspan = 2;
			}
			echo '<td class="right bold" colspan='.$colspan.'>'.formatNumber($mintarget[$itemArray['value']], 2).'</td>';
		}
		echo '</tr>';
	}
	
	// 年間目標
	echo '<tr><td class="left">年間目標</td>';
	foreach ($itemList as $itemArray) {
		if (!in_array($itemArray['value'], local_config::PLAN_ITEM_COMBINE)) {
			// 分離する時
			foreach ($planTotalList[$itemArray['value']] as $key => $value) {
				echo '<td class="right bold bg_skyblue">'.formatNumber($planTotalList[$itemArray['value']][$key], 2).'</td>';
			}
		}
		else {
			echo '<td class="right bold bg_skyblue">'.formatNumber($planTotalList[$itemArray['value']], 2).'</td>';
		}
	}
	echo '</tr>';

	// 実績
	echo '<tr><td class="left">現時点の実績</td>';
	foreach ($itemList as $itemArray) {
		if (!in_array($itemArray['value'], local_config::PLAN_ITEM_COMBINE)) {
			// 分離する時
			foreach ($planTotalList[$itemArray['value']] as $key => $value) {
				echo '<td class="right">'.formatNumber($resultTotalList[$itemArray['value']][$key], 2).'</td>';
			}
		}
		else {
			echo '<td class="right">'.formatNumber($resultTotalList[$itemArray['value']], 2).'</td>';
		}
	}
	echo '</tr>';

	// 目標まで
	echo '<tr><td class="left">目標まであと</td>';
	foreach ($itemList as $itemArray) {
		if (!in_array($itemArray['value'], local_config::PLAN_ITEM_COMBINE)) {
			// 分離する時
			foreach ($planTotalList[$itemArray['value']] as $key => $value) {
				$diff = $planTotalList[$itemArray['value']][$key] - $resultTotalList[$itemArray['value']][$key];
				printReachScore($diff, $itemArray['value'], $resultTotalList[$itemArray['value']][$key], $planTotalList[$itemArray['value']][$key]);
			}
		}
		else {
			$diff = $planTotalList[$itemArray['value']] - $resultTotalList[$itemArray['value']];
			printReachScore($diff, $itemArray['value'], $resultTotalList[$itemArray['value']], $planTotalList[$itemArray['value']]);
		}

		// LC獲得枚数だけ保存
		if ($itemArray['value'] === 'LC') {
			$LC_Cnt = $resultTotalList[$itemArray['value']];
		}
	}
	echo '</tr>';

	// 達成判定
	$itemAchievedValue = 0;
	echo '<tr class="bg_yellow"><td>達成判定</td>';
	foreach ($itemList as $itemArray) {

		if (!in_array($itemArray['value'], local_config::PLAN_ITEM_COMBINE)) {
			// 分離する時
			printJudgeReach($fiscal_year, $campaign, $itemArray['value'], $resultTotalList[$itemArray['value']], $planTotalList[$itemArray['value']], true);
		}
		else {
			printJudgeReach($fiscal_year, $campaign, $itemArray['value'], $resultTotalList[$itemArray['value']], $planTotalList[$itemArray['value']]);
		}
	}
	echo '</tr>';
	echo '</table>';

	if (local_config::FEATURE_CHANGE_COLOR_ACCODING_TO_REACH) {
		printReachPattern();	// 達成率のパターン別の色分けサンプルを表示
	}

	//----------------------------------------
	// 年間優績同友表彰基準に関する情報を表示
	if (local_config::FEATURE_LC_HOLD_NUMBER  && local_config::$DB_TABLE_PREFIX === "kngw") {
		// 設定値取得
		$lcOption = getLCHoldPromotionInfo($fiscal_year);

		echo '<br /><p><&nbsp;年間優績同友表彰基準達成状況&nbsp;></p>';
		echo '<table>';
		echo '<tr class="bg_wet_asphalt"><td style="width:120px;"></td>';
		echo '<td style="width:80px;">目標</td><td style="width:80px;">達成状況</td><td style="width:80px;">達成判定</td>';
		echo '</tr>';

		// 最優秀賞
		$Item_diff = $lcOption['lc_get_item_count_1'] - $itemAchievedValue;
		$LC_diff = $lcOption['lc_get_promotion_count'] - $LC_Cnt;
		echo '<tr><td class="left">最優秀賞'.$lcOption['lc_get_item_count_1'].'種目<br />賞金：'.$lcOption['lc_get_item_prize_1'].'円</td>';
		if ($fiscal_year >= 2021) {	// 2021年度以降の暫定措置なので2023年度以降は変わるかも
			echo '<td class="right">'.$lcOption['lc_get_item_count_1'].'種目</td>';
			echo '<td class="right">'.$itemAchievedValue.'種目達成</td>';
		}
		else {
			echo '<td class="right">'.$lcOption['lc_get_item_count_1'].'種目<br />LC '.$lcOption['lc_get_promotion_count'].'枚</td>';
			echo '<td class="right">'.$itemAchievedValue.'種目達成<br />LC '.$LC_Cnt.'枚達成</td>';
		}
		// 達成判定
		if ( $Item_diff > 0 && $LC_diff > 0) {
			if ($fiscal_year >= 2021) {
				echo '<td class="font_red">あと'.$Item_diff.'種目</td>'; // 2021年度以降の暫定措置なので2023年度以降は変わるかも
			}
			else {
				echo '<td class="font_red">あと'.$Item_diff.'種目<br />あとLC '.$LC_diff.'枚</td>';
			}
			
		}
		else if( $Item_diff > 0 && $LC_diff <= 0) {
			if ($fiscal_year >= 2021) {
				echo '<td class="font_red">あと'.$Item_diff.'種目</td>'; // 2021年度以降の暫定措置なので2023年度以降は変わるかも
			}
			else {
				echo '<td class="font_red">あと'.$Item_diff.'種目<br />LC目標達成！</td>';
			}
		}
		else if ( $Item_diff <= 0 && $LC_diff > 0) {
			if ($fiscal_year >= 2021) {
				echo '<td class="font_red">種目目標達成！</td>'; // 2021年度以降の暫定措置なので2023年度以降は変わるかも
			}
			else {
				echo '<td class="font_red">種目目標達成！<br />あとLC'.$LC_diff.'枚</td>';
			}
		}
		else {
			echo '<td class="font_red">達成!</td>';
		}
		echo '</tr>';

		// 準優秀賞
		$Item_diff = $lcOption['lc_get_item_count_2'] - $itemAchievedValue;
		$LC_diff = $lcOption['lc_get_promotion_count'] - $LC_Cnt;
		echo '<tr><td class="left">準優秀賞'.$lcOption['lc_get_item_count_2'].'種目<br />賞金：'.$lcOption['lc_get_item_prize_2'].'円</td>';
		if ($fiscal_year >= 2021) {	// 2021年度以降の暫定措置なので2023年度以降は変わるかも
			echo '<td class="right">'.$lcOption['lc_get_item_count_2'].'種目</td>';
			echo '<td class="right">'.$itemAchievedValue.'種目達成</td>';
		}
		else {
			echo '<td class="right">'.$lcOption['lc_get_item_count_2'].'種目<br />LC '.$lcOption['lc_get_promotion_count'].'枚</td>';
			echo '<td class="right">'.$itemAchievedValue.'種目達成<br />LC '.$LC_Cnt.'枚達成</td>';
		}
		// 達成判定
		if ( $Item_diff > 0 && $LC_diff > 0) {
			if ($fiscal_year >= 2021) {
				echo '<td class="font_red">あと'.$Item_diff.'種目</td>'; // 2021年度以降の暫定措置なので2023年度以降は変わるかも
			}
			else {
				echo '<td class="font_red">あと'.$Item_diff.'種目<br />あとLC '.$LC_diff.'枚</td>';
			}
		}
		else if( $Item_diff > 0 && $LC_diff <= 0) {
			if ($fiscal_year >= 2021) {
				echo '<td class="font_red">あと'.$Item_diff.'種目</td>'; // 2021年度以降の暫定措置なので2023年度以降は変わるかも
			}
			else {
				echo '<td class="font_red">あと'.$Item_diff.'種目<br />LC目標達成！</td>';
			}
		}
		else if ( $Item_diff <= 0 && $LC_diff > 0) {
			if ($fiscal_year >= 2021) {
				echo '<td class="font_red">種目目標達成！</td>'; // 2021年度以降の暫定措置なので2023年度以降は変わるかも
			}
			else {
				echo '<td class="font_red">種目目標達成！<br />あとLC'.$LC_diff.'枚</td>';
			}
		}
		else {
			echo '<td class="font_red">達成!</td>';
		}
		echo '</tr>';
		echo '</table>';

		if ($fiscal_year >= 2021) {
			// 2021年度以降は非表示（★★★ 暫定措置なので2023年度以降は変わるかも ★★★）
			echo '<br /><p></p>';
		}
		else {
			echo '<br /><p><&nbsp;ロータスカード新規獲得優秀賞&nbsp;></p>';
			echo '<p>年間においてロータスカードの実績を1番多く獲得した同友に対して「<font color="red">'.$lcOption['lc_get_promotion_prize'].'円</font>」の賞金を表彰する。</p>';
		}
	}
}

/**
 * ----------------------------------------------------------
 * printOneExecutiveQuarterResult()
 * 指定年度の種目ごと、四半期別の計画値と合計値を取得
 * @param $fiscal_year：指定年度
 * @param $itemList：種目一覧
 * @param $eid：同友ID
 * ----------------------------------------------------------
 */
function printOneExecutiveQuarterResult($fiscal_year, $itemList, $eid) {

	// 初期化
	$planTotalList   = array();
	$resultTotalList = array();
	$data = array();

	// 種目ごとの合計値を取得
	foreach ($itemList as $itemArray) {
		$data = getExecutiveResultTotalValue($fiscal_year, $itemArray['value'], '%', 'TOTAL', $eid);
		//printArray($data);
		
		// 初期化
		$planTotal = array();
		$resultTotal = array();
		
		// 四半期ごとに合計値を作成
		// 第一四半期から第三四半期
		$quarter = 1;
		for ($i = 4; $i <= 12; $i++) {
			$planTotal[$quarter] += $data[0][$i.'_plan'];
			$resultTotal[$quarter] += $data[0][$i.'_result'];

			if ($i % 3 == 0) {
				$quarter++;
			}
		}
		// 第四四半期
		$quarter = 4;
		for ($i = 1; $i <= 3; $i++) {
			$planTotal[$quarter] += $data[0][$i.'_plan'];
			$resultTotal[$quarter] += $data[0][$i.'_result'];
		}
		
		// 四半期、種目ごとに合計値を保存
		$planTotalList += array($itemArray['value'] => $planTotal);
		$resultTotalList += array($itemArray['value'] => $resultTotal);
	}
	// デバッグ
	//printArray($planTotalList);
	//printArray($resultTotalList);
	
	// 2015年以降はLM+LSを作り、LM、LSを削除
	if ($fiscal_year >= config::CAMPAIGN_LMLS_ADD_YEAR) {
		
		for ($i = 1; $i <= 4; $i++) {
			$planTotalList['LM+LS'][$i] = $planTotalList['LM'][$i] + $planTotalList['LS'][$i];
			$resultTotalList['LM+LS'][$i] = $resultTotalList['LM'][$i] + $resultTotalList['LS'][$i];
		}
		
		// itemListからLMとLSを削除（2015年移行の対策）
		$i = 0;
		foreach ($itemList as $itemArray) {
			if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
				unset($itemList[$i]);
			}
			$i++;
		}
	}
	// デバッグ
	//printArray($planTotalList);
	//printArray($resultTotalList);

	// 四半期毎の成績を表示
	for ($i = 1; $i <= 4; $i++) {
		echo '<『第'.$i.'四半期』&nbsp;目標管理シート&nbsp;>';
		
		// 全種目を表示
		echo '<table>';
		
		// 種目名
		echo '<tr class="bg_wet_asphalt"><td style="width:120px;"></td>';
		foreach ($itemList as $itemArray) {
			echo '<td style="width:80px;">'.$itemArray['value'].'<br />[単位:'.$itemArray['unit'].']</td>';
		}
		echo '</tr>';
		
		// 計画
		echo '<tr><td class="left">年間目標</td>';
		foreach ($itemList as $itemArray) {
			echo '<td class="right">'.formatNumber($planTotalList[$itemArray['value']][$i], 2).'</td>';
		}
		echo '</tr>';

		// 実績
		echo '<tr><td class="left">現時点の実績</td>';
		foreach ($itemList as $itemArray) {
			echo '<td class="right">'.formatNumber($resultTotalList[$itemArray['value']][$i], 2).'</td>';
		}
		echo '</tr>';

		// 目標まで
		echo '<tr><td class="left">目標まであと</td>';
		foreach ($itemList as $itemArray) {
			$diff = $planTotalList[$itemArray['value']][$i] - $resultTotalList[$itemArray['value']][$i];
			// 目標達成を判定
			if ($diff <= 0) {
				echo '<td class="right">';
			}
			else {
				if (local_config::FEATURE_CHANGE_COLOR_ACCODING_TO_REACH) {
					// 達成度に応じて背景の色を変える
					$quotient = $resultTotalList[$itemArray['value']][$i] / $planTotalList[$itemArray['value']][$i];
					if ($quotient >= config::REACH_PATTERN_4) {
						echo '<td class="right bg_skyblue font_red bold">';
					}
					else if ($quotient >= config::REACH_PATTERN_3) {
						echo '<td class="right bg_skyblue">';
					}
					else if ($quotient >= config::REACH_PATTERN_2) {
						echo '<td class="right bg_green">';
					}
					else if ($quotient >= config::REACH_PATTERN_1) {
						echo '<td class="right bg_orange">';
					}
					else {
						echo '<td class="right bg_red">';
					}
				}
				else {
					echo '<td class="right bg_red">';
				}
			}
			echo formatNumber($diff, 2).'</td>';
		}
		echo '</tr>';

		// 達成判定
		echo '<tr class="bg_yellow"><td>達成判定</td>';
		foreach ($itemList as $itemArray) {
			$diff = $planTotalList[$itemArray['value']][$i] - $resultTotalList[$itemArray['value']][$i];
			// 目標達成を判定
			if ($diff <= 0 && $resultTotalList[$itemArray['value']][$i] > 0) {
				echo '<td class="font_red">達成!</td>';
			}
			else {
				echo '<td></td>';
			}
		}
		echo '</tr>';

		echo '</table><br />';
	}
	if (local_config::FEATURE_CHANGE_COLOR_ACCODING_TO_REACH) {
		printReachPattern();	// 達成率のパターン別の色分けサンプルを表示
	}
}

/**
 * ----------------------------------------------------------
 * printOneExecutivePromotionResult()
 * 同友への販促費の獲得状況を表示
 * @param $fiscal_year：指定年度
 * @param $itemList：種目一覧
 * @param $eid：同友ID
 * ----------------------------------------------------------
 */
function printOneExecutivePromotionResult($fiscal_year, $itemList, $eid) {
	
	// 初期化
	$resultTotalList = array();
	$data = array();

	// 不要な種目を削除
	$i = 0;
	foreach ($itemList as $itemArray) {
		if ($itemArray['value'] === 'LH') {
			unset($itemList[$i]);
		}
		$i++;
	}

	// LM未登録店のpidを探す
	$lm_ignore_partnerID = 0;
	$lm_partnerList = getPartnerList($fiscal_year, 'LM');
	//printArray($lm_partnerList);
	foreach ($lm_partnerList as $partnerArray) {
		if (strpos($partnerArray['name'], '未登録') != false) {
			//printArray($partnerArray);
			$lm_ignore_partnerID = $partnerArray['value'];
		}
	}

	// 種目ごとの合計値を取得
	foreach ($itemList as $itemArray) {
		$data = getExecutiveResultTotalValue($fiscal_year, $itemArray['value'], '%', 'TOTAL', $eid);
		//printArray($data);

		// LMの時だけ未登録店の数値を引く（※未登録店の販売数は販促費に含まれないため）
		if ($itemArray['value'] === 'LM') {
			$lm_ignore_data = getExecutiveResultTotalValue($fiscal_year, $itemArray['value'], '%', $lm_ignore_partnerID, $eid);
			for ($i = 1; $i <=12; $i++) {
				$data[0][$i.'_result'] = $data[0][$i.'_result'] - $lm_ignore_data[0][$i.'_result'];
			}
		}
		
		// 初期化
		$resultTotal = array();
		
		// 半期ごとに合計値を作成
		// 上半期
		for ($i = 4; $i <= 9; $i++) {
			$resultTotal[0] += $data[0][$i.'_result'];
		}
		// 下半期
		for ($i = 1; $i <= 3; $i++) {
			$resultTotal[1] += $data[0][$i.'_result'];
		}
		for ($i = 10; $i <= 12; $i++) {
			$resultTotal[1] += $data[0][$i.'_result'];
		}
		
		// 四半期、種目ごとに合計値を保存
		$resultTotalList += array($itemArray['value'] => $resultTotal);
	}
	// デバッグ
	//printArray($planTotalList);
	//printArray($resultTotalList);

	// 設定取得
	$promotionInfo = getExecutivePromotionInfo($fiscal_year);
	//printArray($promotionInfo);

	// 上半期、下半期でループを回す
	for ($half_period = 0; $half_period < 2; $half_period++) {

		if ($half_period == 0) {
			echo '<『同友推進費』&nbsp;目標管理シート（上半期）&nbsp;>';
		}
		else {
			echo '<『同友推進費』&nbsp;目標管理シート（下半期）&nbsp;>';
		}
		
		echo '<table>';
		
		// 種目名
		echo '<tr class="bg_wet_asphalt"><td style="width:120px;"></td>';
		foreach ($itemList as $itemArray) {
			echo '<td style="width:80px;">'.$itemArray['value'].'</td>';
		}
		echo '</tr>';

		// 上半期実績
		echo '<tr><td class="left">実績(単位：千円)</td>';
		foreach ($itemList as $itemArray) {
			echo '<td class="right">'.formatNumber($resultTotalList[$itemArray['value']][$half_period],2).'</td>';
		}
		echo '</tr>';

		// 獲得同友推進費
		echo '<tr><td class="left">獲得同友推進費(単位：円)</td>';
		$itemCond = array();
		foreach ($itemList as $itemArray) {
			$prize = 0;
			if ($itemArray['value'] === 'LM'){
				$prize = $promotionInfo[$itemArray['value'].':ryoritsuE_1'] * round($resultTotalList[$itemArray['value']][$half_period]);
			}
			else if ($itemArray['value'] === 'LS') {
				if ($fiscal_year != 2024) {
					$prize = $promotionInfo[$itemArray['value'].':ryoritsuE_1'] * round($resultTotalList[$itemArray['value']][$half_period]);
				}
				else {
					// 2024年は上期と下期でLSの販促費が異なるので、処理を追加
					if ($half_period == 0) {
						$prize = $promotionInfo[$itemArray['value'].':ryoritsuE_1'] * round($resultTotalList[$itemArray['value']][$half_period]);
					}
					else {
						$prize = $promotionInfo[$itemArray['value'].':ryoritsuE_2'] * round($resultTotalList[$itemArray['value']][$half_period]);
					}
				}
			}
			else {
				// 該当する料率を判定
				for ($i = 5; $i > 0; $i=$i-2) {
					if ($resultTotalList[$itemArray['value']][$half_period] >= $promotionInfo[$itemArray['value'].':ryoritsuE_'.$i] * 10) {
						$j = $i + 1;
						$prize =  ($resultTotalList[$itemArray['value']][$half_period]*1000) * ($promotionInfo[$itemArray['value'].':ryoritsuE_'.$j]/100);
						//DBGMSG($itemArray['value'].' .$i='.$i.' result='.$resultTotalList[$itemArray['value']][$half_period].' 料率='.$promotionInfo[$itemArray['value'].':ryoritsuE_'.$j]);
						$itemCond[$itemArray['value']] = $i;	// 種目毎の条件を満たした位置を保存
						break;
					}
					else {
						$itemCond[$itemArray['value']] = -1;		// 種目毎の条件を一つも満たせない場合は-1を入れる
					}
				}
			}
			echo '<td class="right bg_yellow font_red bold">'.formatNumber(round($prize),0).'</td>';
		}
		echo '</tr>';
		//printArray($itemCond);

		// 次の目標
		// 販促費の表と色を合わせる
		echo '<tr><td class="left">次の目標(単位：千円)</td>';
		foreach ($itemList as $itemArray) {
			if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
				echo '<td class="right">----</td>';
			}
			else {
				if ($itemCond[$itemArray['value']] == 3) {
					echo '<td class="right bg_skyblue">'.formatNumber($promotionInfo[$itemArray['value'].':ryoritsuE_5']*10, 2).'</td>';
				}
				elseif($itemCond[$itemArray['value']] == 1) {
					echo '<td class="right bg_green">'.formatNumber($promotionInfo[$itemArray['value'].':ryoritsuE_3']*10, 2).'</td>';
				}
				elseif($itemCond[$itemArray['value']] == -1) {
					echo '<td class="right bg_red">'.formatNumber($promotionInfo[$itemArray['value'].':ryoritsuE_1']*10, 2).'</td>';
				}
				else {
					echo '<td class="right font_red bold">全達成！</td>';
				}
			}
		}
		echo '</tr>';

		// 次の目標まで
		echo '<tr><td class="left">次の目標まで(単位：千円)</td>';
		foreach ($itemList as $itemArray) {
			if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
				echo '<td class="right">----</td>';
			}
			else {
				if ($itemCond[$itemArray['value']] < 5) {
					$i = $itemCond[$itemArray['value']] + 2;
					$target = $promotionInfo[$itemArray['value'].':ryoritsuE_'.$i]*10;
					$remain = $target - $resultTotalList[$itemArray['value']][$half_period];
					echo '<td class="right">'.formatNumber($remain, 2).'</td>';
				}
				else {
					echo '<td class="right">----</td>';
				}
			}
		}
		echo '</tr>';

		// 目標達成時の賞金
		echo '<tr><td class="left">目標達成時の賞金額(単位：円)</td>';
		foreach ($itemList as $itemArray) {
			if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
				echo '<td class="right">----</td>';
			}
			else {
				if ($itemCond[$itemArray['value']] < 5) {
					$i = $itemCond[$itemArray['value']] + 2;
					$j = $i + 1;
					$targetPrize = ($promotionInfo[$itemArray['value'].':ryoritsuE_'.$i]*10000) * ($promotionInfo[$itemArray['value'].':ryoritsuE_'.$j]/100);;
					echo '<td class="right">'.formatNumber(round($targetPrize), 0).'</td>';
				}
				else {
					echo '<td class="right">----</td>';
				}
			}
		}
		echo '</tr>';

		echo '</table>';
		echo '<br />';
	}

	// 販促費を表で表示
	echo '<p class="bold">● LM、LS</p>';
	echo '半期毎の実績台数に対し、<br />';
	echo '<font class="bold">LM</font>：1台に付き一律 <font class="bold font_red">'.formatNumber($promotionInfo['LM:ryoritsuE_1']).' 円</font><br />';
	echo '<font class="bold">LS</font>：1台に付き一律 <font class="bold font_red">'.formatNumber($promotionInfo['LS:ryoritsuE_1']).' 円</font><br /><br />';
	echo '<p class="bold">● LT、LO、LE、LL</p>';
	echo '半期毎の実績台数に対し、<br />';
	echo '<font class="bold font_blue">期間実績 ✕ 料率 = 同友推進費（円単位未満四捨五入）</font><br /><br />';
	echo '<table>';
	echo '<tr><td colspan="2">LT</td><td colspan="2">LO</td><td colspan="2">LE</td><td colspan="2">LL</td></tr>';
	echo '<tr><td>(6ヶ月)</td><td>料率</td><td>(6ヶ月)</td><td>料率</td><td>(6ヶ月)</td><td>料率</td><td>(6ヶ月)</td><td>料率</td></tr>';
	echo '<tr class="right bg_red">';
	foreach ($itemList as $itemArray) {
		if ($itemArray['value'] !== 'LM' && $itemArray['value'] !== 'LS') {
			echo '<td class=>'.formatNumber($promotionInfo[$itemArray['value'].':ryoritsuE_1']).' 万円以上</td><td>'.$promotionInfo[$itemArray['value'].':ryoritsuE_2'].' %</td>';
		}
	}
	echo '</tr><tr class="right bg_green">';
	foreach ($itemList as $itemArray) {
		if ($itemArray['value'] !== 'LM' && $itemArray['value'] !== 'LS') {
			echo '<td>'.formatNumber($promotionInfo[$itemArray['value'].':ryoritsuE_3']).' 万円以上</td><td>'.$promotionInfo[$itemArray['value'].':ryoritsuE_4'].' %</td>';
		}
	}
	echo '</tr><tr class="right bg_skyblue">';
	foreach ($itemList as $itemArray) {
		if ($itemArray['value'] !== 'LM' && $itemArray['value'] !== 'LS') {
			echo '<td>'.formatNumber($promotionInfo[$itemArray['value'].':ryoritsuE_5']).' 万円以上</td><td>'.$promotionInfo[$itemArray['value'].':ryoritsuE_6'].' %</td>';
		}
	}
	echo '</tr>';
	echo '</table>';
	echo '<br />';
}

/**
 * ----------------------------------------------------------
 * printOneExecutiveMobileProshopTarget()
 * モービルプロショップ認定条件を表示
 * @param $fiscal_year：指定年度
 * @param $eid：同友ID
 * ----------------------------------------------------------
 */
function printOneExecutiveMobileProshopTarget($fiscal_year, $eid) {

	// 設定値を取得
	$other_prize = getOtherBranchPromotionInfo($fiscal_year);
	$proshop_target = formatNumber($other_prize['LO_proshop_target'] * 10);		// モービルプロショップ
	$proshop_target2 = formatNumber($other_prize['LO_proshop_target2'] * 10);	// 準モービルプロショップ

	// LOの提携企業を取得
	$pid = 0;
	$partnerList = getPartnerList($fiscal_year, 'LO');
	foreach ($partnerList as $partnerArray) {
		if ($partnerArray['user'] === config::LOM) {	// モービルを取得
			$pid = $partnerArray['value'];
			break;
		}
	}

	// モービルオイルの販売実績を取得
	$data = getExecutiveResultTotalValue($fiscal_year, 'LO', '%', $pid, $eid);
	$result = 0;
	for ($i = 1; $i <= 12; $i++) {
		$result += $data[0][$i.'_result'];
	}

	// モービルプロショップ目標までの差分
	$diff = ($other_prize['LO_proshop_target'] * 10) - $result;
	if ($diff <= 0) {
		$diff = '<font color="red">プロショップ認定</font>';
	}
	else {
		$diff = formatNumber($diff, 2).' 千円';
	}

	// 準モービルプロショップ目標までの差分
	$diff2 = ($other_prize['LO_proshop_target2'] * 10) - $result;
	if ($diff2 <= 0) {
		$diff2 = '<font color="red">準プロショップ認定</font>';
	}
	else {
		$diff2 = formatNumber($diff, 2).' 千円';
	}

	echo '<p>モービルプロショップ認定条件（年間4月～3月でモービルオイルの購入金額）</p>';
	echo '<table>';
	echo '<tr class="bg_wet_asphalt"><td style="width:120px;">目標購入金額</td><td style="width:120px;">現状の購入金額</td><td style="width:120px;">目標まで</td></tr>';
	echo '<tr>';
	echo '<td class="right">'.$proshop_target.' 千円</td>';
	echo '<td class="right">'.formatNumber($result, 2).' 千円</td>';
	echo '<td class="right">'.$diff.'</td>';
	echo '</tr>';
	echo '<tr>';
	echo '<td class="right">'.$proshop_target2.' 千円</td>';
	echo '<td class="right">'.formatNumber($result, 2).' 千円</td>';
	echo '<td class="right">'.$diff2.'</td>';
	echo '</tr>';
	echo '</table><br />';

	// メッセージを分解し、改行記号を<br>に変換
	$msg = str_replace("\n", "<br />", $other_prize['LO_proshop_msg']);
	echo '<p class="bold"><モービルプロショップ特典>></p>';
	echo $msg;
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

//-------------------
// 管理者の場合
//-------------------
if ($userInfo['auth'] == config::USER_ADMIN || $userInfo['auth'] == config::USER_EXEOFFICER) { ?>
	<center>メニューから操作を選択してください。</center>
<?php }
//-------------------
// 提携企業の場合
//-------------------
elseif ($userInfo['auth'] == config::USER_PARTNER || $userInfo['auth'] == config::USER_COOPERATION) { ?>
	
	<br />
	<center>メニューから操作を選択してください。</center>
	<br />
	
<?php }
//-------------------
// 同友の場合
//-------------------
else {

// 本部からの同友向けメッセージの表示	
if (local_config::FEATURE_MSG_FUNCTION) {
	
	// メッセージを分解し、改行記号を<br>に変換、リンクはクリックできるように変換
	$msg = getMsgforExecutive();
	$msgArray = explode('##', $msg);
	$msgArray[1] = str_replace("\n", "<br />", $msgArray[1]);
	$msgArray[1] = preg_replace('/(https?|ftp)(:)(\/\/[\w\/:%#\$&\?\(\)~\.=\+\-]+)/', '<a href="$1$2$3">$1$2$3</a>', $msgArray[1]);

	echo '<div id="contents">';
	echo '<p class="bold">■ 事務局から全同友へのメッセージ  < 更新時間：'.$msgArray[0].'></p><br />';
	if (strlen($msgArray[1]) > 0) {
		echo '<p><h1>'.$msgArray[1].'</h1></p>';
		echo '<img src="./images/face/'.$msgArray[2].'.png">';
    }
    else {
        echo '<p>メッセージはありません。</p>';
	}
	echo '<br /><br /><br />';

	echo '<p class="bold">■ 事務局からの個別同友向けメッセージ  < 更新時間：'.$msgArray[0].'></p><br />';
	$msg = getEachExecutiveMsg($userInfo['id']);
	$msg = str_replace("\n", "<br />", $msg);
	$msg = preg_replace('/(https?|ftp)(:)(\/\/[\w\/:%#\$&\?\(\)~\.=\+\-]+)/', '<a href="$1$2$3">$1$2$3</a>', $msg);
	if (strlen($msg) > 0) {
		echo '<p><h1>'.$msg.'</h1></p>';
    }
    else {
        echo '<p>メッセージはありません。</p>';
	}

	echo '</div><br /><hr>';
} ?>

<?php
// 同友の進捗状況の表示
if (local_config::FEATURE_EXECUTIVE_RESULT_ON_TOP) { ?>

<div id="contents_select">
	<form style="display:inline" method="POST" action="index.php?reg=top">
		<?php printSelectBox($listArray['fiscal_year_list'], 'fiscal_year', 70, $postArray['fiscal_year']) ?>年度
		<?php printSelectBox($listArray['campaign_list'], 'campaign', 180, $postArray['campaign']) ?>
		<?php printSubmitButton('表示') ?>
		<input type="hidden" name="command" value="show">
	</form>
</div>
<hr>

<div id="contents">
	<?php
	// キャンペーン種別判定
	switch($postArray['campaign']) {
		case 'summer':
			$title = config::SUMMER_CAMPAIGN_NAME;
			break;
		case 'autumn':
			$title = config::AUTUMN_CAMPAIGN_NAME;
			break;
		case 'spring':
			$title = config::SPRING_CAMPAIGN_NAME;
			break;
		default:
			return;
	}

	// キャンペーンの公開/未公開を判定
	$campaignDataInfo = getCampaignDataInfo($postArray['fiscal_year']);	// キャンペーン情報を取得
	if ($campaignDataInfo[$postArray['campaign'].'_open'] == config::STATUS_DATA_CLOSE) {
		echo '<p>'.$postArray['fiscal_year'].'年度 '.$title.'のデータは公開されていません。</p>';
		return;
	}

	// 表示
	if (isset($postArray['command']) && $postArray['campaign'] !== 'NONE') {
		echo '<font class="bold">'.$userInfo['name'].'殿</font>';
		echo '<hr><font class="bold">■&nbsp;【支部施策】'.$postArray['fiscal_year'].'年度キャンペーン&nbsp;■</font><hr>';
		
		//------------------------------
		// 同友の個別成績を表示
		echo '<『'.$title.'』&nbsp;目標管理シート&nbsp;>';
		printCampaignOneExecutiveResult($postArray['fiscal_year'], $postArray['campaign'], $listArray['item_list'], $userInfo['id']);
		echo '<br />';

		//------------------------------
		// LCキャッシュバック条件の達成状況を表示
		if (local_config::FEATURE_AREA_REACH_AWARD && local_config::$DB_TABLE_PREFIX === "kngw") {
			printCampaignOneExecutiveLCTarget($postArray['fiscal_year'], $postArray['campaign'],  $userInfo['id']);
			echo '<br />';
		}

		//------------------------------
		// 年間目標と成績を表示
		echo '<hr><font class="bold">■&nbsp;【支部施策】'.$postArray['fiscal_year'].'年度&nbsp;通算成績&nbsp;■</font><hr>';
		echo '<『年間優績表彰』&nbsp;目標管理シート&nbsp;>';
		printOneExecutiveResult($postArray['fiscal_year'], $listArray['item_year_list'], $userInfo['id']);
		echo '<br />';

		//------------------------------
		// 四半期目標と成績を表示
		if (local_config::FEATURE_EXECUTIVE_QUARTER_RESULT_ON_TOP) {
			echo '<hr><font class="bold">■&nbsp;【支部施策】'.$postArray['fiscal_year'].'年度&nbsp;四半期別成績&nbsp;■</font><hr>';
			printOneExecutiveQuarterResult($postArray['fiscal_year'], $listArray['item_year_list'], $userInfo['id']);
		}

		//------------------------------
		// 同友への販促費を表示
		if (local_config::FEATURE_SALES_PROMOTION_FOR_EXECUTIVE) {
			echo '<hr><font class="bold">■&nbsp;【本部施策】'.$postArray['fiscal_year'].'年度&nbsp;「同友推進費」獲得状況&nbsp;■</font><hr>';
			printOneExecutivePromotionResult($postArray['fiscal_year'], $listArray['item_promo_list'], $userInfo['id']);
		}

		//------------------------------
		// LC保有枚数を表示
		if (local_config::FEATURE_LC_HOLD_NUMBER) {
			echo '<hr><font class="bold">■&nbsp;【本部施策】'.$postArray['fiscal_year'].'年度&nbsp;LC保有枚数&nbsp;■</font><hr>';
			printOneExecutiveLCHoldNumber($postArray['fiscal_year'], $userInfo['id']);
		}

		//------------------------------
		// モービルプロショップ関連を表示
		if (local_config::FEATURE_SALES_PROMOTION_FOR_EXECUTIVE) {
			echo '<hr><font class="bold">■&nbsp;【本部施策】'.$postArray['fiscal_year'].'年度&nbsp;モービルプロショップ認定条件&nbsp;■</font><hr>';
			printOneExecutiveMobileProshopTarget($postArray['fiscal_year'], $userInfo['id']);
		}
	}
	else {
		echo '<p>チャレンジシートを表示することができます。</p>';
		echo '<p>年度とキャンペーンを選択し、「表示」ボタンを押してください。</p>';
	}
	?>
</div>

<?php } else { ?>
	<br />
	<center>メニューから操作を選択してください。</center>
	<br />
<?php } ?>

<?php } ?>

<?php
// ログイン履歴の表示
if (local_config::FEATURE_SAVE_LOGIN_HISTORY) { ?>
<hr><br />
<div id="contents">
	<p><< ログイン履歴 >></p>
	<?php
	$loginHistoryArray = array();
	$loginHistoryArray = getLoginHistory($userInfo['id']);
	// 既にあるログイン履歴と連結
	for ($i = 0; $i < count($loginHistoryArray); $i++) {
		echo $loginHistoryArray[$i].'<br />';
	}
	?>
</div>
<?php } ?>

<?php
/**
 * =================================================================
 *  Copyright(c)2017 iSKET All Rights Reserved.
 * =================================================================
 */
?>