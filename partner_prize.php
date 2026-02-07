<?php
/**
 * =================================================================
 * partner_prize.php
 * 提携企業
 * 販促費表示、設定用のファンクション
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
	'partner_list'     => array(),
	'executive_list'   => array(),
	'area_list'        => array(),
	'data_list'        => array(),
	'p_prize'          => array()
);

//--------------------------------
// 表示ページを選択
//--------------------------------
$page = selectPagePartnerPrize($getArray, $postArray, $listArray);

/**
 * ----------------------------------------------------------
 * selectPagePartnerPrize()
 * 地元提携表彰状況の表示と設定で表示するページを選択する
 * @param $getArray：GETで送られてきたパラメータ
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function selectPagePartnerPrize($getArray, &$postArray, &$listArray) {

	$page = '';

	// 選択中のメニューに応じた結果を表示
	switch ($getArray['type']) {
		case  'view':
            $page = makePartnerPrizeViewPage($postArray, $listArray);
			break;
		case  'setting':
            $page = makePartnerPrizeSettingPage($postArray, $listArray);
			break;
		default:
			break;
	}
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makePartnerPrizeViewPage()
 * 実績を閲覧するためのページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makePartnerPrizeViewPage($postArray, &$listArray) {

	$page = "view";
	
	// 前年度の設定値を読み込む場合
	// セッティングページに再び遷移させる
	if (isset($postArray['last_setting_read'])) {
		$page = makePartnerPrizeSettingPage($postArray, $listArray);
		return $page;
	}

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
	// 販促費では使用しない種目を削除
	$i = 0;
	foreach ($listArray['item_list'] as $itemListArray) {
		if ($itemListArray['value'] === 'ALL' || $itemListArray['value'] === 'LH' || $itemListArray['value'] === 'LEB' || $itemListArray['value'] === 'LC') {
			unset($listArray['item_list'][$i]);
		}
		$i++;
	}

	// 提携企業一覧を取得
	if (isset($postArray['fiscal_year'])) {
		$listArray['partner_list'] = getPartnerList($postArray['fiscal_year'], $postArray['item']);
		if (count($listArray['partner_list']) == 0) {
			return 'no_partner';
		}
	}

	// 保存ボタンが押された時
	if (isset($postArray['save'])) {

		//if (local_config::FEATURE_PARTNER_PRIZE_ONE_SETTING) {
			// 本部からの報奨金設定をセーブするのは神奈川支部のみ
			//if (local_config::$DB_TABLE_PREFIX === "kngw") {

				// 本部からの報奨金設定を保存
				if (writePartnerPrizeInfo($postArray, 'LM') != 'success') {
					return 'db_write_fail';
				}
				if (writePartnerPrizeInfo($postArray, 'LS') != 'success') {
					return 'db_write_fail';
				}
				if (writePartnerPrizeInfo($postArray, 'LTB') != 'success') {
					return 'db_write_fail';
				}
				if (writePartnerPrizeInfo($postArray, 'LTY') != 'success') {
					return 'db_write_fail';
				}
				if (writePartnerPrizeInfo($postArray, 'LOP') != 'success') {
					return 'db_write_fail';
				}
				if (writePartnerPrizeInfo($postArray, 'LEP') != 'success') {
					return 'db_write_fail';
				}
				if (writePartnerPrizeInfo($postArray, 'LEG') != 'success') {
					return 'db_write_fail';
				}
			//}
		//}
        
		// 地元提携の設定を保存
		if (writePartnerPrizeInfo($postArray, 'LLM') != 'success') {
			return 'db_write_fail';
		}
		if (writePartnerPrizeInfo($postArray, 'LLS') != 'success') {
			return 'db_write_fail';
		}
		if (writePartnerPrizeInfo($postArray, 'LLTB') != 'success') {
			return 'db_write_fail';
		}
		if (writePartnerPrizeInfo($postArray, 'LLTY') != 'success') {
			return 'db_write_fail';
		}
		if (writePartnerPrizeInfo($postArray, 'LLHT') != 'success') {
			return 'db_write_fail';
		}
		if (writePartnerPrizeInfo($postArray, 'LLHA') != 'success') {
			return 'db_write_fail';
		}
		if (writePartnerPrizeInfo($postArray, 'LLOP') != 'success') {
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
    
	// 表示ボタンを押された場合、データを読み込み
	// 本部年間施策「キャンペーン賞金」用
	if (isset($postArray['show'])) {

		// 種目毎の情報取得
		foreach ($listArray['item_list'] as $itemArray) {
			// 種目毎の実績を取得。地域は%を指定することで全地域を指定
			$listArray['data_list'][$itemArray['value']] = getResultAndPlanByItem($postArray['fiscal_year'], $itemArray['value'], 'TOTAL', '%', true);
			//printArray($listArray['data_list'][$itemArray['value']]);
		}
    }
    
	// 設定値読み込み
	if (isset($postArray['show'])) {

		// 支部への販促費「キャンペーン賞金」の設定値を取得する
		$listArray['campaign_promotion'] = getBranchPromotionInfo($postArray['fiscal_year'],  'ryoritsuC');
		if (count($listArray['campaign_promotion']) == 0) {
			return 'no_data';
		}
		// 提携の設定値を読み込み
		$listArray['p_prize']['LM'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LM');
		$listArray['p_prize']['LS'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LS');
		$listArray['p_prize']['LTB'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LTB');
		$listArray['p_prize']['LTY'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LTY');
		$listArray['p_prize']['LOP'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LOP');
		$listArray['p_prize']['LEP'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LEP');
        $listArray['p_prize']['LEG'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LEG');
        
		// 地元提携の設定値を読み込み
		$listArray['p_prize']['LLM'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLM');
		$listArray['p_prize']['LLS'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLS');
		$listArray['p_prize']['LLTB'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLTB');
		$listArray['p_prize']['LLTY'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLTY');
		$listArray['p_prize']['LLHT'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLHT');
		$listArray['p_prize']['LLHA'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLHA');
		$listArray['p_prize']['LLOP'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLOP');
		$listArray['p_prize']['LLEP'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLEP');
		$listArray['p_prize']['LLEG'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLEG');
		$listArray['p_prize']['LLLJ'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLLJ');
        $listArray['p_prize']['LLLO'] = getPartnerPrizeInfo($postArray['fiscal_year'], 'LLLO');
        
        //printArray($listArray['data_list']); // デバッグ用
    }

    return $page;
}

/**
 * ----------------------------------------------------------
 * makePartnerPrizeSettingPage()
 * 設定するためのページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makePartnerPrizeSettingPage($postArray, &$listArray) {

	$page = "setting";

	// 前年設定読み込みの場合は前年のデータを読み込む
	$fiscal_year = $postArray['fiscal_year'];
	if (isset($postArray['last_setting_read'])) {
		$fiscal_year--;
	}

    // 提携の設定値を読み込み
    $listArray['p_prize']['LM'] = getPartnerPrizeInfo($fiscal_year, 'LM');
    $listArray['p_prize']['LS'] = getPartnerPrizeInfo($fiscal_year, 'LS');
    $listArray['p_prize']['LTB'] = getPartnerPrizeInfo($fiscal_year, 'LTB');
    $listArray['p_prize']['LTY'] = getPartnerPrizeInfo($fiscal_year, 'LTY');
    $listArray['p_prize']['LOP'] = getPartnerPrizeInfo($fiscal_year, 'LOP');
    $listArray['p_prize']['LEP'] = getPartnerPrizeInfo($fiscal_year, 'LEP');
    $listArray['p_prize']['LEG'] = getPartnerPrizeInfo($fiscal_year, 'LEG');

	// 地元提携の設定値を読み込み
	$listArray['p_prize']['LLM'] = getPartnerPrizeInfo($fiscal_year, 'LLM');
	$listArray['p_prize']['LLS'] = getPartnerPrizeInfo($fiscal_year, 'LLS');
	$listArray['p_prize']['LLTB'] = getPartnerPrizeInfo($fiscal_year, 'LLTB');
	$listArray['p_prize']['LLTY'] = getPartnerPrizeInfo($fiscal_year, 'LLTY');
	$listArray['p_prize']['LLHT'] = getPartnerPrizeInfo($fiscal_year, 'LLHT');
	$listArray['p_prize']['LLHA'] = getPartnerPrizeInfo($fiscal_year, 'LLHA');
	$listArray['p_prize']['LLOP'] = getPartnerPrizeInfo($fiscal_year, 'LLOP');
	$listArray['p_prize']['LLEP'] = getPartnerPrizeInfo($fiscal_year, 'LLEP');
	$listArray['p_prize']['LLEG'] = getPartnerPrizeInfo($fiscal_year, 'LLEG');
	$listArray['p_prize']['LLLJ'] = getPartnerPrizeInfo($fiscal_year, 'LLLJ');
	$listArray['p_prize']['LLLO'] = getPartnerPrizeInfo($fiscal_year, 'LLLO');

	//printArray($listArray['p_prize']['LEP']); // デバッグ用

    return $page;
}

/**
 * ----------------------------------------------------------
 * printCampaignPrizeResultTable()
 * 支部報奨金のキャンペーン賞金の実績結果を示すテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return
 * ----------------------------------------------------------
 */
function printCampaignPrizeResultTable($postArray, $listArray, $campaign) {
	
	$title = '';
	$start_month = '';
	$end_month = '';

	// キャンペーン種別判定
	switch($campaign) {
		case 'summer':
			$title = config::SUMMER_CAMPAIGN_NAME;
			$start_month = config::SUMMER_CAMPAIGN_START_MONTH;
			$end_month = config::SUMMER_CAMPAIGN_END_MONTH;
			break;
		case 'autumn':
			$title = config::AUTUMN_CAMPAIGN_NAME;
			$start_month = config::AUTUMN_CAMPAIGN_START_MONTH;
			$end_month = config::AUTUMN_CAMPAIGN_END_MONTH;
			break;
		case 'spring':
			$title = config::SPRING_CAMPAIGN_NAME;
			$start_month = config::SPRING_CAMPAIGN_START_MONTH;
			$end_month = config::SPRING_CAMPAIGN_END_MONTH;
			break;
	}

	// 参加率分母同友数を取得（退会(休会)を除いた数値）
	$ignoreRecessEnterableNum = count(getCampaignExecutiveEnterableList($postArray['fiscal_year'], '%', $campaign, 'recess'));

	echo '<p><< '.$title.' >></p>';
	echo '<table>';
	echo '<tr class="bg_wet_asphalt">
			<td>種目</td><td>単位</td><td>支部計画(千円)</td><td>実績(千円)</td><td>達成率</td><td>参加率</td><td>獲得報奨金(円)</td><td>適用料率(%)</td><td>目標達成率(%)</td><td>目標まで(千円)</td><td>目標達成時の報奨金(円)</td>
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

		// 特別キャンペーン(達成率に関わらず一律の料率を与える)
		// ※2020年のCOLVIC-19のための対策を2020年サマーキャンペーンにおいて実施
		if (local_config::FEATURE_FIXED_POINT_AND_RATE) {
			$cnt = 0;
			$flag_fixed_rate_type = false;
			foreach (config::CAMPAIGN_FIXED_RATE_TYPE as $value) {
				if ($value === $postArray['fiscal_year'].'_'.$campaign) {
					// もし年度とキャンペーン種別が一致する組み合わせがあれば達成率に関わらず固定料率を設定する
					$listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_2'] = config::CAMPAIGN_FIXED_RATE[$cnt][$itemArray['value']];
					$listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_4'] = config::CAMPAIGN_FIXED_RATE[$cnt][$itemArray['value']];
					$listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_6'] = config::CAMPAIGN_FIXED_RATE[$cnt][$itemArray['value']];
					$flag_fixed_rate_type = true;
					break;
				}
				$cnt++;
			}
			//printArray($listArray['campaign_promotion']);
		} // FEATURE_FIXED_POINT_AND_RATE

		// キャンペーンの参加同友数を取得
		$partnerList = getPartnerList($postArray['fiscal_year'], $itemArray['value']);
		$enterableNum = getCampaignMonthTotalExecutiveResultCount($postArray['fiscal_year'], $partnerList, '%', $start_month, $end_month);
	
		// 参加率計算
		// 計算式：参加同友数 / 分母同友数
		// 参加率の計算の場合に限り、退会(休会)中の同友は母数のカウントには含めない
		$enterablePoint = round($enterableNum / $ignoreRecessEnterableNum, 3);
		//echo '参加率('.$enterablePoint.')='.$enterableNum.' / '.$ignoreRecessEnterableNum.'<br>';

		// 達成率を計算
		$result = $listArray['data_list'][$itemArray['value']]['result'][$campaign];
		$plan   = $listArray['data_list'][$itemArray['value']]['campaign_info'][$campaign.'_plan'];	// 本部指定の支部計画を取得
		$reach  = ( $result / $plan) * 100;

		//echo $itemArray['value'].':result = '.$result.'<br>';

		// 報奨金を計算
		$prize = 0;
		// 該当する料率を判定

		// 2020年秋キャンペーンから追加 ▼▼▼ ここから
		// 2020年秋キャンペーンから賞金の基準となる項目が3から5に増加
		if ( ($postArray['fiscal_year'] == 2020 && ($campaign === "autumn" || $campaign === "spring")) || $postArray['fiscal_year'] >= 2021 ) {
			$max_index_num = 9;
		}
		else {
			$max_index_num = 5;
		}
		// 2020年秋キャンペーンから追加 ▲▲▲ ここまで
		
		for ($i = 1; $i <= $max_index_num; $i=$i+2) {
			if ($reach >= $listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_'.$i] || $flag_fixed_rate_type) {
				$j = $i + 1;
				$prize =  ($result*1000) * ($listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_'.$j]/100);
				$applicableReach = $listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_'.$j]; // 現在の料率
				$itemCond = $i;	// 種目毎の条件を満たした位置を保存
				break;
			}
			else {
				$applicableReach = 0;
				$itemCond = -1;	// 種目毎の条件を一つも満たせない場合は-1を入れる
			}
		}

		//echo $itemArray['value'].':result x rate = '.$prize.'<br>';

		
		// 目標達成率を計算
		if ($itemCond == 1) {
			$targetReach = '<font color="red">達成</font>';
			
		}
		elseif ($itemCond <= $max_index_num && $itemCond > 0) {
			$target = $itemCond - 2;
			$targetReach = $listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_'.$target];
			$target++;
			$targetRate  = $listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_'.$target];
		}
		else {
			$targetReach = $listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_'.$max_index_num];
			$targetRate  = $listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_'.$max_index_num-1];
		}

		// 次の目標までの販売金額を算出
		$targetAmount = number_format(($targetReach * $plan)/100 - $result);

		// 達成時の報奨金を計算
		$targetPrize  = ($plan/10) * $targetReach * $targetRate;

		// 現在の参加率を賞金に乗算（100%の場合は10%加算）
		// 特別キャンペーンの場合は、参加率などを考慮せず、実績ｘ料率のみで計算する
		if (local_config::FEATURE_FIXED_POINT_AND_RATE) {
			if (!$flag_fixed_rate_type) {
				if ($enterablePoint >= 1) {
					$prize = $prize * 1.1;
					$targetPrize = $targetPrize * 1.1;
				}
				else {
					$prize = $prize * $enterablePoint;
					$targetPrize = $targetPrize * $enterablePoint;
				}
			}
		}

		//echo $itemArray['value'].':result x rate x enterablePoint = '.$prize.'  enterablePoint='.$enterablePoint.'<br>';

		// コロナ対応特別キャンペーン(達成率に関わらず一律の料率を与える)
		$cell_color = "";
		$targetPrize_for_cell = number_format(round($targetPrize));
		if (local_config::FEATURE_FIXED_POINT_AND_RATE) {
			if ($flag_fixed_rate_type) {
				$cell_color = "bg_gray";
				$targetReach = "";
				$targetAmount = "";
				$targetPrize_for_cell = "";
			}
		}
		
		echo '
		<tr>
			<td width="50px">'.$itemArray['value'].'</td>
			<td width="50px">'.$itemArray['unit'].'</td>
			<td class="right" width="100px">'.number_format($plan, 0).'</td>
			<td class="right" width="100px">'.number_format($result, 0).'</td>
			<td class="right" width="70px">'.number_format($reach, 1).'%</td>
			<td class="right" width="70px">'.number_format($enterablePoint*100, 1).'%</td>
			<td class="right bg_yellow" width="100px">'.number_format(round($prize)).'</td>
			<td class="right" width="50px">'.$applicableReach.'</td>
			<td class="right '.$cell_color.'" width="50px">'.$targetReach.'</td>
			<td class="right '.$cell_color.'" width="100px">'.$targetAmount.'</td>
			<td class="right '.$cell_color.'" width="100px">'.$targetPrize_for_cell.'</td>
		</tr>';
	}
	echo '</table><br />';

	//-------------------------------------------------
	// 2020年秋キャンペーン向け臨時特別施策
	if ($postArray['fiscal_year'] == 2020 && ($campaign === "autumn" || $campaign === "spring") ) {

		// 販売基準同友数の取得
		// 販売基準を達成しているかどうか判定し、カウントする
		$executive_count = 0;
		$listArray['executive_list'] = getExecutiveList($postArray['fiscal_year'], 'campaign', '%');
		foreach ($listArray['executive_list'] as $executiveArray) {
			// キャンペーン参加同友のみ実績を取得
			if($executiveArray['summer_enterable'] != config::STATUS_ENTERABLE || 
			   $executiveArray['autumn_enterable'] != config::STATUS_ENTERABLE || 
			   $executiveArray['spring_enterable'] != config::STATUS_ENTERABLE ) {
				continue;
			}
			$executive_count++;	// 販売基準の対象同友のみカウント
		}

		// キャンペーンボーナス得点の計算
		$point_10_cnt = 0;
		$point_15_cnt = 0;
		$total_clear_cnt = 0;

		// 種目一覧を作成
		$item_list = array("LM+LS","LT","LH","LO","LE","LL");
		
		foreach ($item_list as $item) {
			$campaignPoint = getCalcCampeignPoint($postArray['fiscal_year'], $campaign, $item);
			$bounsPoint = getCalcCampeignBonusPoint($campaignPoint);
			if ($bounsPoint == 15) {
				$point_15_cnt++;
			}
			else if ($bounsPoint == 10) {
				$point_10_cnt++;
			}
			//echo $item.' = '.$bounsPoint.' <br />';	// デバッグ用
		}
		$total_clear_cnt = $point_10_cnt+$point_15_cnt;	// 合計ボーナスクリア種目数

		// 賞金計算
		// 1種目あたりの賞金（10点と15点のときに賞金が発生）
		$prize_Array = array(10 => array(0, 4000,5000,6000,7000,8000,9000),
							 15 => array(0, 6000,7000,8000,9000,10000,11000));
		// 優績項目数 ｘ 単価 ｘ 支部販売基準同友数 = 賞金
		$prize = 0;
		$prize = $prize_Array[10][$point_10_cnt+$point_15_cnt] * $point_10_cnt * $executive_count 	// 10点の賞金額
		       + $prize_Array[15][$point_10_cnt+$point_15_cnt] * $point_15_cnt * $executive_count;	// 15点の賞金額

		// 表示
		echo '<table>';
		echo '
			<tr>
				<td class="bg_wet_asphalt">臨時特別施策 キャンペーン優績獲得賞金</td>
				<td>販売基準同友数：'.$executive_count.'</td>
				<td>キャンペーン優績獲得数：'.$total_clear_cnt.' 種目(15点：'.$point_15_cnt.' 種目、10点：'.$point_10_cnt.' 種目)</td>
				<td class="right bg_yellow">合計獲得賞金額：'.number_format($prize).' 円</td>
			</tr>';
		echo '</table><br />';
	}
	//-------------------------------------------------
}

/**
 * ----------------------------------------------------------
 * printPartnerPrize()
 * 提携の賞金を表示する関数
 * @param $item        種目
 * @param $num         項目数
 * @param $postArray   POSTで送られてきた設定値
 * @param $listArray   設定値
 * @return array $lcHoldPromotionArray：支部への販促費
 * ----------------------------------------------------------
 */
function printPartnerPrize($item, $num, $postArray, $listArray) {

	//printArray($postArray);
	$plan   = 0;
	$result = 0;
	$diff   = 0;
	$share  = -1;
	$target_unit  = "----";
	$target_cost  = "----";
	$target_rate  = "----";
	$target_prize = "----";
	$prize_cost   = "----";
	$prize_rate   = "----";
	$memo = "";
	$clearFlag  = false;
	$prize_result = 0;

    // 達成基準リスト（順番を変えるとJavaScriptに影響でるので注意！！）
    $base_list = array(
        array('value' => 'none',   		   'name' => '----'),
        array('value' => 'unit',   		   'name' => '目標数'),
		array('value' => 'cost',   		   'name' => '目標金額'),
		array('value' => 'shop',           'name' => '稼動店舗数'),
		array('value' => 'share_LL',       'name' => 'シェア（LL用）'),
		array('value' => 'sys_plan',       'name' => '計画値（S参照：年間計画）'),
		array('value' => 'doyu_plan',      'name' => '同友別計画値（S参照：年間計画）'),
		array('value' => 'sys_camp_honbu', 'name' => '本部目標（S参照：キャンペーン）'),
		array('value' => 'sys_camp_area',  'name' => '地区目標（S参照：キャンペーン）'),
		array('value' => 'sys_camp_douyu', 'name' => '同友別目標（S参照：キャンペーン）'),
		array('value' => 'sys_lastresult', 'name' => '前年実績値（S参照）'),
		array('value' => 'sys_2y_lastresult', 'name' => '前々年実績値（S参照）'),
        array('value' => 'up',             'name' => '↑ 共通')
    );

    // 賞金基準リスト
    $prizebase_list = array(
        array('value' => 'none',     'name' => '----'),
        array('value' => 'unit',     'name' => '単位あたりの賞金'),
        array('value' => 'achieve',  'name' => '単位の賞金✕達成率'),
		array('value' => 'rate',     'name' => '実績に対する料率'),
		array('value' => 'diffrate', 'name' => '前年(前々年)を超えた実績に対する料率'),
		array('value' => 'single',   'name' => '褒賞金(固定)'),
		array('value' => 'shop',     'name' => '稼働同友数あたりの賞金'),
		array('value' => 'shop_over','name' => '指定稼働同友数以上に賞金'),
		array('value' => 'up',       'name' => '↑ いずれか')
	);

    // 実績基準リスト
    $resultbase_list = array(
		array('value' => 'none',   'name' => '----'),
		array('value' => 'up',     'name' => '↑使用'),
		array('value' => 'minus',  'name' => '↑から→を引く'),
		array('value' => 'double', 'name' => '↑：条件、→：賞金'),
		array('value' => 'input',  'name' => '条件：システム、賞金：手動')
    );

    echo '<table>
    <tr class="bg_wet_asphalt">
	<td rowspan="2">期間</td><td colspan="4">達成条件</td><td colspan="3">目標賞金基準</td><td rowspan="2">対象実績</td><td rowspan="2">目標賞金<br />基準まで</td>
	<td rowspan="2">達成時の賞金<br />(円)</td><td rowspan="2">現在の賞金額<br />(円)</td><td rowspan="2" style="white-space:pre-line">メモ</td></tr>
    <tr>
    <td>達成基準</td><td>目標数</td><td>金額(千円)</td><td>比率(%)</td>
    <td>賞金基準</td><td>賞金額(円)</td><td>料率(%)</td></tr>';

    // 各行ごとに賞金を計算
    for ($i = 0; $i < $num; $i++) {

		// 初期化
		$target_unit  = "----";
		$target_cost  = "----";
		$target_rate  = "----";
		$target_prize = "----";
		$prize_cost   = "----";
		$prize_rate   = "----";

		// 設定が無効のものは処理しない
        if ($listArray[$item.'_enable_'.$i] === "disable" || $listArray[$item.'_enable_'.$i] == null) {
            continue;
        }

		// 指定期間からタイトル取得
        $period = $listArray[$item.'_period_'.$i];
        switch ($period) {
            case 'summer':
				$title = config::SUMMER_CAMPAIGN_NAME;
                break;
            case 'autumn':
                $title = config::AUTUMN_CAMPAIGN_NAME;
                break;
            case 'spring':
                $title = config::SPRING_CAMPAIGN_NAME;
                break;
            case '1_trimester':
                $title = '三半期（4～7月）';
                break;
            case '2_trimester':
                $title = '三半期（8～11月）';
                break;
            case '3_trimester':
                $title = '三半期（12～3月）';
                break;
            case '1_half':
                $title = '上半期（4～9月）';
                break;
            case '2_half':
                $title = '下半期（10～3月）';
                break;
            case 'year':
                $title = '年間（4～3月）';
				break;
			case '1':
                $title = '1月（単月）';
				break;
			case '2':
                $title = '2月（単月）';
				break;
			case '3':
                $title = '3月（単月）';
				break;
			case '4':
                $title = '4月（単月）';
				break;
			case '4':
                $title = '4月（単月）';
				break;
			case '5':
                $title = '5月（単月）';
				break;
			case '6':
                $title = '6月（単月）';
				break;
			case '7':
                $title = '7月（単月）';
				break;
			case '8':
                $title = '8月（単月）';
				break;
			case '9':
                $title = '9月（単月）';
				break;
			case '10':
                $title = '10月（単月）';
				break;
			case '11':
                $title = '11月（単月）';
				break;
			case '12':
                $title = '12月（単月）';
				break;
			case 'until_7':
				$title = '7月まで';
				break;
			case 'until_11':
				$title = '11月まで';
				break;
		}

        // 基準リスト取得
        $base_name = "";
        foreach ($base_list as $key => $value) {
            if ($value['value'] === $listArray[$item.'_base_'.$i]) {
                $base_name = $value['name'];
                break;
            }
        }

        // 賞金基準リスト取得
        $prize_base_name = "";
        foreach ($prizebase_list as $key => $value) {
            if ($value['value'] === $listArray[$item.'_prizebase_'.$i]) {
                $prize_base_name = $value['name'];
                break;
            }
		}
		
		// メモを取得
		$memo =  $listArray[$item.'_memo_'.$i];

		// 本部施策か、地元施策かを判定(本部の場合はtrueにする)
		$honbu = false;
		if ($item === 'LM' || $item === 'LS' || $item === 'LTB' || $item === 'LTY' || $item === 'LOP' ||
		    $item === 'LEP' || $item === 'LEG' || $item === 'LLLJ' || $item === 'LLLO') {
			$honbu = true;
		}

        //------------------------------------------------------
        // 計画・実績取得
		//------------------------------------------------------
		$result = 0;
		$input  = 0;

        // 対象期間の実績値を取得
        // 実績を取得するのは種目と提携が設定されている時
		$data = array();
        if ($listArray[$item.'_item_'.$i] !== "NONE" && $listArray[$item.'_partner_'.$i] !== "NONE") {
			$data = getResultAndPlanByItem($listArray[$item.'_fiscal_year_'.$i], $listArray[$item.'_item_'.$i], $listArray[$item.'_partner_'.$i], $listArray[$item.'_area_'.$i], $honbu);

			// 各同友の実績の合計を取得
			$result = $data['result'][$period];

			// 達成基準によって取得する目標値を変更
			$base_switch_conditions = $listArray[$item.'_base_'.$i];
			switch ($base_switch_conditions) {
				case 'sys_plan':
					$plan = $data['plan'][$period];						// 同友毎に割り当てた年間計画の合計
					break;
				case 'sys_camp_honbu':
					$plan = $data['campaign_info'][$period.'_plan'];	// 本部からのキャンペーン支部計画
					break;
				default:
					$plan = $data['plan'][$period];						// デフォルトは年間計画
					break;
			}
		}

		$resultbase = 0;
		$resultbase = $listArray[$item.'_resultbase_'.$i];	// 対象実績の条件取得
		if ($listArray[$item.'_input_result_'.$i] !== '' && $resultbase != 'input') {
			// もし手動入力値があれば、手動入力の値を優先する
			$result = $listArray[$item.'_input_result_'.$i];
		}
		elseif ($resultbase === 'input') {
			$input  = $listArray[$item.'_input_result_'.$i];	// システムを条件、手動入力を実績として使う場合
		}
		
		// 前年の実績を参照する場合、前年の実績値を取得
		// 前々年の場合も対応
		if ($listArray[$item.'_base_'.$i] === 'sys_lastresult' || $listArray[$item.'_base_'.$i] === 'sys_2y_lastresult') {
			$data = array();
			
			// 前年のときは1年前のデータ、前々年の場合は2年前に設定
			if ($listArray[$item.'_base_'.$i] === 'sys_lastresult') {
				$lastyear = $listArray[$item.'_fiscal_year_'.$i] - 1;
			}
			else if ($listArray[$item.'_base_'.$i] === 'sys_2y_lastresult') {
				$lastyear = $listArray[$item.'_fiscal_year_'.$i] - 2;
			}

			// 指定した年のデータ取得
			if ($listArray[$item.'_item_'.$i] !== "NONE" && $listArray[$item.'_partner_'.$i] !== "NONE") {
				$data = getResultAndPlanByItem($lastyear, $listArray[$item.'_item_'.$i], $listArray[$item.'_partner_'.$i], $listArray[$item.'_area_'.$i], $honbu);

				$plan = $data['result'][$period];	// 同友毎に割り当てられた実績の合計
			}
		}

		// 次の行のデータが関連データ(現在の行からの減算用、賞金計算用)かどうか判定
		$nextMinusNum = 0;		// minusの場合の実績から引く数値
		$nextPrizeNum = 0;		// doubleの場合の賞金計算用
		$totalMinusNum = 0;
		$double_minul_loop = true;
		$double_minul_loop_cnt = 0;
		$prise_double_flag = false;

		// ベースとなる行から続くdoubleとminusがなくなるまでループ
		while ($double_minul_loop) {
			// 次の行に進める
			$double_minul_loop_cnt++;
			$nextrow = $i + $double_minul_loop_cnt;
			$previousrow = $nextrow - 1;

			if ($listArray[$item.'_resultbase_'.$previousrow] !== 'minus') {
				$previousResultBase = $listArray[$item.'_resultbase_'.$previousrow];	// 一つ前の状態保持用（minusをどこから引くのかを判定する。minusは保持しない。）
			}
			$nextresultbase = $listArray[$item.'_resultbase_'.$nextrow];			// 次の状態

			switch ($nextresultbase) {
				case 'minus':
					// 1行目の実績から2行目の実績を引く
					if ($listArray[$item.'_input_result_'.$nextrow] != 0) {
						$nextMinusNum = $listArray[$item.'_input_result_'.$nextrow];
					}
					else {
						$nextdata = getResultAndPlanByItem($listArray[$item.'_fiscal_year_'.$nextrow], $listArray[$item.'_item_'.$nextrow], $listArray[$item.'_partner_'.$nextrow], $listArray[$item.'_area_'.$nextrow], $honbu);
						
						// 次のデータの期間が設定されているかどうか判定
						$next_period = $listArray[$item.'_period_'.$nextrow];
						if ($next_period !== 'none') {
							$nextMinusNum = $nextdata['result'][$next_period];
						}
						else {
							$nextMinusNum = $nextdata['result'][$period];
						}
					}

					// 一つ前の状態で減算する先を変更
					if ($previousResultBase === 'double') {
						$nextPrizeNum = $nextPrizeNum - $nextMinusNum;	// 賞金の対象実績からから引く
					}
					else {
						$result = $result - $nextMinusNum;	// 実績から引く
					}

					// 実績から引く数値の合計値
					$totalMinusNum += $nextMinusNum;
					break;
					
				case 'double':
					// 賞金は別の結果で計算するためのフラグを立てる
					$prise_double_flag = true;

					// 賞金を計算するための実績を取得する
					if ($listArray[$item.'_input_result_'.$nextrow] != 0) {
						$nextPrizeNum = $listArray[$item.'_input_result_'.$nextrow];
					}
					else {
						$nextdata = getResultAndPlanByItem($listArray[$item.'_fiscal_year_'.$nextrow], $listArray[$item.'_item_'.$nextrow], $listArray[$item.'_partner_'.$nextrow], $listArray[$item.'_area_'.$nextrow], $honbu);
						
						// 次のデータの期間が設定されているかどうか判定
						$next_period = $listArray[$item.'_period_'.$nextrow];
						if ($next_period !== 'none') {
							$nextPrizeNum = $nextdata['result'][$next_period];
						}
						else {
							$nextPrizeNum = $nextdata['result'][$period];
						}
					}
					break;

				default:
					// minusでもdoubleでもない場合はループを抜ける
					$double_minul_loop = false;
					break;
			}
		}

		// データベースから取得した実績値と計画値を保存しておく
		$db_result = $result;
		$db_plan   = $plan;

        //------------------------------------------------------
        // 達成基準クリアチェック
        //------------------------------------------------------
		$clearFlag  = false;
		$diffmsg = "";
		$unitmsg = "";
		$target_unit = 0;
		$target_cost = 0;
		$t_rate = 0;
		$t_reachrate = 0; // 実績結果の達成率
		$prize = 0;
		$rowCnt = $i;
		$share  = -1;
		while (1) {

			// 設定が ↑OR または ↑＋ の場合は基準リストと、賞金基準リストが変わるので再取得 
			if ($listArray[$item.'_enable_'.$rowCnt] === 'or' || $listArray[$item.'_enable_'.$rowCnt] === 'plus') {
				// 基準リスト取得
				foreach ($base_list as $key => $value) {
					if ($value['value'] === $listArray[$item.'_base_'.$rowCnt]) {
						$base_name = $value['name'];
						break;
					}
				}

				// 賞金基準リスト取得
				foreach ($prizebase_list as $key => $value) {
					if ($value['value'] === $listArray[$item.'_prizebase_'.$rowCnt]) {
						$prize_base_name = $value['name'];
						break;
					}
				}
			}

			// 種目を取得
			if ($listArray[$item.'_item_'.$rowCnt] === 'NONE') {
				$target_item = $listArray[$item.'_item_'.$i];
			}
			else {
				$target_item = $listArray[$item.'_item_'.$rowCnt];
			}

			// 達成個数を取得
			if ($listArray[$item.'_target_unit_'.$rowCnt] === '') {
				$target_unit = $listArray[$item.'_target_unit_'.$i];
			}
			else {
				$target_unit = $listArray[$item.'_target_unit_'.$rowCnt];
			}

			// 達成額を取得
			if ($listArray[$item.'_target_cost_'.$rowCnt] === '') {
				$target_cost = $listArray[$item.'_target_cost_'.$i];
			}
			else {
				$target_cost = $listArray[$item.'_target_cost_'.$rowCnt];
			}

			// 達成比率を取得
			if ($listArray[$item.'_target_rate_'.$rowCnt] === '') {
				$target_rate = $listArray[$item.'_target_rate_'.$i];
			}
			else {
				$target_rate = $listArray[$item.'_target_rate_'.$rowCnt];
			}

			if ($target_rate === '') {
				$t_rate = 1;					// 比率が設定されていない時は1
				$target_rate = "----";
			}
			else {
				$t_rate = $target_rate/100;		// 比率は%で設定されるので、小数に変換
			}

			//------------------------------------------------------
			// 達成基準ごとに達成判定
			//------------------------------------------------------
			
			// 条件が継続している場合は上の行の条件を使用する
			$base_switch_conditions = $listArray[$item.'_base_'.$rowCnt];
			if ($base_switch_conditions === 'up') {
				$base_switch_conditions = $listArray[$item.'_base_'.$i];
			}

			switch ($base_switch_conditions) {
				case 'unit':
					// 達成目標個数に比率を乗算
					$t = $target_unit*$t_rate;
					$diff = $t - $result;		// 達成までの差分を計算
					if ($diff <= 0) {
						$diffmsg = '<font color="red">達成！</font>';
						$clearFlag = true;

						// 実績の達成率を計算
						$t_reachrate = $result / $target_unit;
					}
					break;

				case 'cost':
					// 達成目標金額に比率を乗算
					$t = $target_cost*$t_rate;
					$diff = intval($t) - intval($result);		// 達成までの差分を計算
					if ($diff <= 0) {
						$diffmsg = '<font color="red">達成！</font>';
						$clearFlag = true;

						// 実績の達成率を計算
						$t_reachrate = $result / $target_cost;
					}
					break;

				case 'shop':
					// 同友一覧を取得（本部の施策の場合は基準同友、地元の時は全同友を取得）
					if ($honbu) {
						$listArray['executive_list'] = getExecutiveList($listArray[$item.'_fiscal_year_'.$rowCnt], 'campaign', $listArray[$item.'_area_'.$rowCnt]);	// 本部の時は分母同友
					}
					else {
						$listArray['executive_list'] = getExecutiveList($listArray[$item.'_fiscal_year_'.$rowCnt], '', $listArray[$item.'_area_'.$rowCnt]);			// 地元の時は全体
					}
					if (count($listArray['executive_list']) == 0) {
						return 'no_executive';
					}
					// 可動店舗数を取得
					$e_cnt = 0;
					foreach ($listArray['executive_list'] as $executiveArray) {
						$data = getResultAndPlanByExecutive($listArray[$item.'_fiscal_year_'.$rowCnt], $listArray[$item.'_item_'.$rowCnt], $listArray[$item.'_partner_'.$rowCnt], $executiveArray['value']);
						//printArray($data);

						// もしLMで次の行で未登録店を実績から減らす場合
						$lm_next_row = $rowCnt+1;
						// $a = $listArray[$item.'_item_'.$rowCnt];
						// $b = $listArray[$item.'_resultbase_'.$lm_next_row];
						// $c = $listArray[$item.'_partner_'.$lm_next_row];
						if ($listArray[$item.'_item_'.$rowCnt] === 'LM' && $listArray[$item.'_resultbase_'.$lm_next_row] === 'minus') {
							$nextdata = getResultAndPlanByExecutive($listArray[$item.'_fiscal_year_'.$rowCnt], $listArray[$item.'_item_'.$rowCnt], $listArray[$item.'_partner_'.$lm_next_row], $executiveArray['value']);

							if ($data['result'][$period] - $nextdata['result'][$period] > 0) {
								$e_cnt++;	// LM未登録店の実績をマイナスしても、販売実績があればカウントする
							}
						}
						// LM未登録店の実績をマイナスしなくても良い場合
						else {
							if ($data['result'][$period] > 0) {
								$e_cnt++;	// 販売実績があればカウントする
							}
						}
					}
					$result = $e_cnt;	// 販売実績件数を取得

					// 目標達成判定
					$t = $target_unit*$t_rate;
					$diff = $t - $result;		// 達成までの差分を計算
					if ($diff <= 0) {
						$diffmsg = '<font color="red">達成！</font>';
						$clearFlag = true;

						// 実績の達成率を計算
						$t_reachrate = $result / $target_unit;
					}
					break;

				case 'doyu_plan':		// 同友別目標達成
				case 'sys_camp_douyu':	// 同友別に割り当てたキャンペーン中の目標達成
					// 同友一覧を取得（本部の施策の場合は基準同友、地元の時は全同友を取得）
					if ($honbu) {
						$listArray['executive_list'] = getExecutiveList($listArray[$item.'_fiscal_year_'.$rowCnt], 'campaign', $listArray[$item.'_area_'.$rowCnt]);	// 本部の時は分母同友
					}
					else {
						$listArray['executive_list'] = getExecutiveList($listArray[$item.'_fiscal_year_'.$rowCnt], '', $listArray[$item.'_area_'.$rowCnt]);			// 地元の時は全体
					}
					if (count($listArray['executive_list']) == 0) {
						return 'no_executive';
					}
					// 目標達成同友数を取得
					$e_cnt = 0;
					foreach ($listArray['executive_list'] as $executiveArray) {

						// まずは実績を取得
						$data = getResultAndPlanByExecutive($listArray[$item.'_fiscal_year_'.$rowCnt], $listArray[$item.'_item_'.$rowCnt], $listArray[$item.'_partner_'.$rowCnt], $executiveArray['value']);

						// キャンペーン期間中の同友の計画値を取得する場合、計画値のみ変更
						if ($base_switch_conditions === 'sys_camp_douyu') {
							if ($listArray[$item.'_partner_'.$rowCnt] === 'TOTAL' ) {
								// 種目合計の場合は提携企業毎の計画値を合計する
								$data['plan'][$period] = 0;
								$partnerList = getPartnerList($postArray['fiscal_year'], $listArray[$item.'_item_'.$rowCnt]);
								foreach ($partnerList as $partnerArray) {
									$campaign_plan_data = getCampaignPlanTotalValue($listArray[$item.'_fiscal_year_'.$rowCnt], $listArray[$item.'_item_'.$rowCnt], $partnerArray['value'], '%', $executiveArray['value']);
									$data['plan'][$period] += $campaign_plan_data[$period.'_plan'];
								}
							}
							else {
								// 提携企業が指定されている場合は、その提携の計画値のみ取得
								$campaign_plan_data = getCampaignPlanTotalValue($listArray[$item.'_fiscal_year_'.$rowCnt], $listArray[$item.'_item_'.$rowCnt], $listArray[$item.'_partner_'.$rowCnt], '%', $executiveArray['value']);
								$data['plan'][$period] = $campaign_plan_data[$period.'_plan'];
							}
						}

						//printArray($data);
						if ($target_unit > 0) {
							if ($data['result'][$period] >= $target_unit) {
								$e_cnt++;	// 実績が設定した数値を上回っている同友数をカウントする
							}
						}
						else if  ($target_cost > 0) {
							if ($data['result'][$period] >= $target_cost) {
								$e_cnt++;	// 実績が設定した金額を上回っている同友数をカウントする
							}
						}
						else {
							if ($data['result'][$period] >= $data['plan'][$period] && $data['plan'][$period] > 0) {
								$e_cnt++;	// 実績が計画を上回っている同友数をカウントする
							}
						}
					}
					$result = $e_cnt;	// 実績が計画を上回っている目標達成同友数を取得

					// 目標達成判定
					$t = $target_unit*$t_rate;
					$diff = $t - $result;		// 達成までの差分を計算
					if ($diff <= 0) {
						$diffmsg = '<font color="red">達成！</font>';
						$clearFlag = true;

						// 実績の達成率を計算
						$t_reachrate = $result / $target_unit;
					}
					break;
				
				case 'sys_camp_area':	// 地区目標を達成したかどうかを判定する時
					// 同友一覧を取得（本部の施策の場合は基準同友、地元の時は全同友を取得）
					if ($honbu) {
						$listArray['executive_list'] = getExecutiveList($listArray[$item.'_fiscal_year_'.$rowCnt], 'campaign', $listArray[$item.'_area_'.$rowCnt]);	// 本部の時は分母同友
					}
					else {
						$listArray['executive_list'] = getExecutiveList($listArray[$item.'_fiscal_year_'.$rowCnt], '', $listArray[$item.'_area_'.$rowCnt]);			// 地元の時は全体
					}
					if (count($listArray['executive_list']) == 0) {
						return 'no_executive';
					}

					// キャンペーン用に割り当てられた同友の計画値の合計を取得
					$plan = 0;
					foreach ($listArray['executive_list'] as $executiveArray) {
						if ($listArray[$item.'_partner_'.$rowCnt] === 'TOTAL' ) {
							// 種目合計の場合は提携企業毎の計画値を合計する
							$partnerList = getPartnerList($postArray['fiscal_year'], $listArray[$item.'_item_'.$rowCnt]);
							foreach ($partnerList as $partnerArray) {
								$campaign_plan_data = getCampaignPlanTotalValue($listArray[$item.'_fiscal_year_'.$rowCnt], $listArray[$item.'_item_'.$rowCnt], $partnerArray['value'], '%', $executiveArray['value']);
								$plan += $campaign_plan_data[$period.'_plan'];
							}
						}
						else {
							// 提携企業が指定されている場合は、その提携の計画値のみ取得
							$campaign_plan_data = getCampaignPlanTotalValue($listArray[$item.'_fiscal_year_'.$rowCnt], $listArray[$item.'_item_'.$rowCnt], $listArray[$item.'_partner_'.$rowCnt], '%', $executiveArray['value']);
							$plan += $campaign_plan_data[$period.'_plan'];
						}
					}

					// 目標達成判定
					if (strpos($target_item, "LM") !== false || strpos($target_item, "LS") !== false || strpos($target_item, "LC") !== false) {
						$t = $plan * $t_rate;	// 達成目標個数に比率を乗算
						if ($target_unit === '') { $target_unit = $t; }
					}
					else {
						$t = $plan * $t_rate;	// 達成目標金額に比率を乗算
						if ($target_cost === '') { $target_cost = $t; }
					}

					$diff = intval($t) - intval($result);	// 達成までの差分を計算

					// 目標個数や目標金額も設定されている場合は＆を取れるようにする
					if ($target_unit === '') { $target_unit = 0; }
					if ($target_cost === '') { $target_cost = 0; }

					if ($diff <= 0 && $result >= $target_unit && $result >= $target_cost) {
						$diffmsg = '<font color="red">達成！</font>';
						$clearFlag = true;

						// 実績の達成率を計算
						$t_reachrate = $result / $target_cost;
					}
					break;

				case 'share_LL':
					if ($listArray[$item.'_input_result_'.$rowCnt] !== '') {
						// もし手動入力値があれば、手動入力の値を優先する
						$share = $listArray[$item.'_input_result_'.$rowCnt];
					}
					else {
						// LL全体の実績合計値を取得
						//$data = getResultAndPlanByItem($listArray[$item.'_fiscal_year_'.$rowCnt], $listArray[$item.'_item_'.$rowCnt], 'TOTAL', '%', $honbu);
						$data = getResultAndPlanByItem($listArray[$item.'_fiscal_year_'.$rowCnt], 'LL', 'TOTAL', '%', $honbu);
						$denominator = $data['result'][$period];	// LL全体分母
						//printArray($data);

						$share = floor(($result/$denominator)*100);	// シェア率(%)
						//$share = 45;	// デバッグ用

						//echo 'result = '.$result.' denominator = '.$denominator.' share='.$share.'% target_rate='.$target_rate.'<br>';
					}
					// 達成までの差分を計算
					$diff = $target_rate - $share;
					if ($diff <= 0) {
						$diffmsg = '<font color="red">達成！</font>';
						$clearFlag = true;

						// 実績の達成率を計算
						$t_reachrate = $result / ($denominator*$target_rate);
					}
					break;

				case 'sys_plan':
				case 'sys_lastresult':
				case 'sys_2y_lastresult':
				case 'sys_camp_honbu':
					//if ($target_item === "LM" || $target_item === "LS" || $target_item === "LC") {
					if (strpos($target_item, "LM") !== false || strpos($target_item, "LS") !== false || strpos($target_item, "LC") !== false) {
						$t = $plan * $t_rate;	// 達成目標個数に比率を乗算
						if ($target_unit === '') { $target_unit = $t; }
					}
					else {
						$t = $plan * $t_rate;	// 達成目標金額に比率を乗算
						if ($target_cost === '') { $target_cost = $t; }
					}

					$diff = intval($t) - intval($result);	// 達成までの差分を計算

					// 目標個数や目標金額も設定されている場合は＆を取れるようにする
					if ($target_unit === '') { $target_unit = 0; }
					if ($target_cost === '') { $target_cost = 0; }

					if ($diff <= 0 && $result >= $target_unit && $result >= $target_cost) {
						$diffmsg = '<font color="red">達成！</font>';
						$clearFlag = true;

						// 実績の達成率を計算
						$t_reachrate = $result / $target_cost;
					}
					break;

				default:
					break;
			}

			// 単位判定
			switch ($base_switch_conditions) {
				case 'unit':
					if (strpos($target_item, "LM") !== false || strpos($target_item, "LS") !== false) {
						$unitmsg = "台";
					}
					elseif (strpos($target_item, "LT") !== false) {
						$unitmsg = "本";
					}
					elseif (strpos($target_item, "LE") !== false) {
						$unitmsg = "個";
					}
					elseif (strpos($target_item, "LC") !== false) {
						$unitmsg = "枚";
					}
					break;
				case 'sys_plan':
				case 'sys_lastresult':
				case 'sys_2y_lastresult':
				case 'sys_camp_honbu':
				case 'sys_camp_area':
					if (strpos($target_item, "LM") !== false || strpos($target_item, "LS") !== false) {
						$unitmsg = "台";
					}
					elseif (strpos($target_item, "LE") !== false) {
						$unitmsg = "個";
					}
					elseif (strpos($target_item, "LC") !== false) {
						$unitmsg = "枚";
					}
					else {
						$unitmsg = "千円";
					}
					break;
				case 'cost':
					$unitmsg = "千円";
					break;
				case 'shop':
				case 'doyu_plan':
					$unitmsg = "店舗";
					break;
				case 'cost':
					$unitmsg = "千円";
					break;
				case 'share_LL':
					$unitmsg = "%";
					break;
			}

			// 目標を達成していないときの表示
			if (!$clearFlag) {
				$diffmsg = 'あと'.formatNumber($diff,0).$unitmsg;
			}


			//------------------------------------------------------
			// 賞金計算
			//------------------------------------------------------
			$target_prize = 0;
			$copy_target_unit = $target_unit;

			// 設定が ↑＋ の場合は現在の賞金額を覚えておく
			if ($listArray[$item.'_enable_'.$rowCnt] === 'plus') {
				$copy_prize = $prize;
			}

			// 賞金計算用の実績を取得
			if ($prise_double_flag) {
				$prize_result = $nextPrizeNum;	// 次の行の数値で賞金計算をする場合
				//$target_unit  = $nextPrizeNum;	// 達成時の賞金額を次の行の賞金用の数字で計算するためにコピーする
			}
			elseif ($resultbase === 'input') {
				$prize_result = $input;			// 実績で計算する場合
			}
			else {
				$prize_result = $result;		// 実績で計算する場合
			}
			
			// 条件が継続している場合は上の行の条件を使用する
			$prizebase_switch_conditions = $listArray[$item.'_prizebase_'.$rowCnt];	// 賞金基準を取得
			if ($prizebase_switch_conditions === 'up') {
				$prizebase_switch_conditions = $listArray[$item.'_prizebase_'.$i];
			}
			switch ($prizebase_switch_conditions) {
				case 'unit';
					$prize_cost = $listArray[$item.'_prize_cost_'.$rowCnt];
					if ($clearFlag) {
						$prize = $prize_result * $prize_cost;										// 現在の賞金を計算
					}
					else {
						if ($prise_double_flag) {
							// 次の行で賞金計算をする場合は現在の個数を賞金計算の元とする
							//$target_prize = ($nextPrizeNum*$t_rate) * $prize_cost;					// 達成時の賞金額を計算
							$target_prize = $nextPrizeNum * $prize_cost;	// 次の行で賞金計算をする場合は達成比率が賞金額とは関係ないので乗算しないように修正
						}
						else {
							$target_prize = ($target_unit*$t_rate) * $prize_cost;					// 達成時の賞金額を計算
						}
					}
					break;
				case 'achieve';
					$prize_cost = $listArray[$item.'_prize_cost_'.$rowCnt];							// 賞金額を取得
					if ($clearFlag) {
						$prize = $prize_result * ($prize_cost*$t_reachrate);						// 現在の賞金を計算（達成率を乗算）
					}
					else {
						
						if ($prise_double_flag) {
							// 次の行で賞金計算をする場合は現在の個数を賞金計算の元とする
							$target_prize = ($nextPrizeNum*$t_rate) * ($prize_cost*$t_rate);		// 達成時の賞金額を計算
						}
						else {
							$target_prize = ($target_unit*$t_rate) * ($prize_cost*$t_rate);			// 達成時の賞金額を計算
						}
					}
					break;
				case 'rate';
					$prize_rate = $listArray[$item.'_prize_rate_'.$rowCnt];
					if ($clearFlag) {
						// 参加同友数が判定の時で実績ベースの料率の場合は売上などの実績で判定する
						if ($base_switch_conditions === 'shop') {
							$prize = ($db_result * ($prize_rate/100)) * 1000;						// 現在の賞金を計算
						}
						else {
							$prize = ($prize_result * ($prize_rate/100)) * 1000;					// 現在の賞金を計算
						}
					}
					else {
						// 参加同友数が判定の時で実績ベースの料率の場合は売上などの実績で判定する
						if ($base_switch_conditions === 'shop') {
							$target_prize = (($db_result*$t_rate) * ($prize_rate/100)) * 1000;		// 達成時の賞金額を計算
						}
						else {
							if ($prise_double_flag) {
								$target_prize = (($prize_result*$t_rate) * ($prize_rate/100)) * 1000;	// 達成時の賞金額を計算
							}
							else {
								$target_prize = (($target_cost*$t_rate) * ($prize_rate/100)) * 1000;	// 達成時の賞金額を計算
							}
						}
					}
					// もし賞金額欄に入力があり、賞金額よりも高い場合は賞金額欄の賞金を優先する
					$prize_cost = $listArray[$item.'_prize_cost_'.$rowCnt];
					if ($prize_cost > $prize ) {
						$prize = $prize_cost;
					}
					break;
				case 'diffrate';
					$prize_rate = $listArray[$item.'_prize_rate_'.$rowCnt];
					if ($clearFlag) {
						//$prize = (($prize_result-$target_cost) * ($prize_rate/100)) * 1000;			// 現在の賞金を計算
						$prize = (($prize_result-$plan) * ($prize_rate/100)) * 1000;					// 現在の賞金を計算(2020.12.9修正 前年実績を上回った実績に対して料率を乗算する)
					}
					else {
						//$target_prize = (($target_cost*($t_rate-1)) * ($prize_rate/100)) * 1000;		// 達成時の賞金額を計算
						$target_prize = (($plan*($t_rate-1)) * ($prize_rate/100)) * 1000;				// 達成時の賞金額を計算(2020.12.9修正 前年実績を上回った実績に対して料率を乗算する)
					}
					// もし賞金額欄に入力があり、賞金額よりも高い場合は賞金額欄の賞金を優先する
					$prize_cost = $listArray[$item.'_prize_cost_'.$rowCnt];
					if ($prize_cost > $prize ) {
						if ($clearFlag) {
							$prize = $prize_cost;
						}
						else {
							$target_prize = $prize_cost;
						}
					}
					break;

				case 'single';
					$prize_cost = $listArray[$item.'_prize_cost_'.$rowCnt];
					if ($clearFlag) {
						$prize = $prize_cost;			// 現在の賞金を計算
					}
					else {
						$target_prize = $prize_cost;	// 達成時の賞金額を計算
					}
					break;

				case 'shop';	// 稼働同友数で賞金決定
				case 'shop_over':
					$prize_cost = $listArray[$item.'_prize_cost_'.$rowCnt];	// 賞金額を取得

					// 同友一覧を取得（本部の施策の場合は基準同友、地元の時は全同友を取得）
					if ($honbu) {
						$listArray['executive_list'] = getExecutiveList($listArray[$item.'_fiscal_year_'.$rowCnt], 'campaign', $listArray[$item.'_area_'.$rowCnt]);	// 本部の時は分母同友
					}
					else {
						$listArray['executive_list'] = getExecutiveList($listArray[$item.'_fiscal_year_'.$rowCnt], '', $listArray[$item.'_area_'.$rowCnt]);			// 地元の時は全体
					}
					if (count($listArray['executive_list']) == 0) {
						return 'no_executive';
					}
					// 可動店舗数を取得
					$e_cnt = 0;
					foreach ($listArray['executive_list'] as $executiveArray) {
						$data = getResultAndPlanByExecutive($listArray[$item.'_fiscal_year_'.$rowCnt], $listArray[$item.'_item_'.$rowCnt], $listArray[$item.'_partner_'.$rowCnt], $executiveArray['value']);
						if ($data['result'][$period] > 0) {
							$e_cnt++;	// 販売実績があればカウントする
						}
					}

					if ($clearFlag) {
						// 稼働店舗数で賞金を計算する場合
						if ($prizebase_switch_conditions === 'shop') {
							$prize = $prize_cost * $e_cnt;	// 現在の賞金を計算
						}
						// 指定稼働店舗数以上で賞金を計算する場合
						else if ($prizebase_switch_conditions === 'shop_over') {
							$prize = $prize_cost * ($e_cnt - $t + 1);	// 現在の賞金を計算
						}
					}
					else {
						// 稼働店舗数で賞金を計算する場合
						if ($prizebase_switch_conditions === 'shop') {
							$target_prize = $prize_cost * count($listArray['executive_list']);	// 達成時の賞金額を計算
						}
						// 指定稼働店舗数以上で賞金を計算する場合
						else if ($prizebase_switch_conditions === 'shop_over') {
							$target_prize = $prize_cost * 1;	// 達成時の賞金額を計算
						}
					}
					break;
				default:
					break;
			}

			// 設定が ↑＋ の場合は達成時の賞金に現在の賞金額を加算する
			if ($listArray[$item.'_enable_'.$rowCnt] === 'plus') {
				if ($target_prize == 0 && $clearFlag) {
					$prize = $copy_prize + $prize;	// 達成した時はこれまでの賞金＋達成時の賞金
					$target_prize = $prize;
				}
				else {
					$target_prize = $copy_prize + $target_prize;
				}
			}

			$target_unit = $copy_target_unit;	// コピーしておいた目標数を戻す


			//------------------------------------------------------
			// 達成しているかどうかで一つの条件のまとまりを判定
			//------------------------------------------------------
			
			// 行を進める前の$iの値を保存しておく(AND条件やOR条件が最後にあって、そこからさらに他の行が続いている場合のため)
			$pre_i = $i;

			// もし達成してない場合はenableかnullになるまですべて飛ばす
			if ($clearFlag == false) {
				$flag_group_end = false;	
				while(1) {
					$rowCnt++;
					if ($listArray[$item.'_base_'.$rowCnt] !== "up" && ($listArray[$item.'_enable_'.$rowCnt] === "enable" || $listArray[$item.'_enable_'.$rowCnt] == null)) {
						$i = $rowCnt - 1;
						$flag_group_end = true;	// 施策のグループ終了を示す
						break;
					}
					else if ($listArray[$item.'_enable_'.$rowCnt] === "or" || $listArray[$item.'_enable_'.$rowCnt] === "and") {
						$i = $rowCnt - 1;
						$flag_group_end = true;	// 施策のグループ終了を示す
						break;
					}
					else if ($listArray[$item.'_enable_'.$rowCnt] === "plus") {
						$i = $rowCnt - 1;
						break;
					}
				}
				// 一つの施策のグループを抜けてテーブルを表示する
				if ($flag_group_end) {
					break;
				}
			}
			// 達成している場合
			else {
				// 次の行の達成条件をチェックするので達成フラグをクリア
				$clearFlag  = false;

				// minus（実績マイナス）とdouble（複数条件）の場合の行は飛ばす
				while (1) {
					$rowCnt++;
					if ($listArray[$item.'_resultbase_'.$rowCnt] === 'minus' || $listArray[$item.'_resultbase_'.$rowCnt] === 'double') {
						continue;
					}
					else {
						break;
					}
				}

				// 次の行がup(=共通)かつ、でなければループ終了
				// $a = $listArray[$item.'_base_'.$rowCnt];
				// $b = $listArray[$item.'_enable_'.$rowCnt];
				if ($listArray[$item.'_base_'.$rowCnt] !== "up" && $listArray[$item.'_enable_'.$rowCnt] !== "plus" ) {
					$i = $rowCnt - 1;
					break;	// while(1)ループ終了
				}
			}
		}

		// シェアを計算した時だけ実績をシェア(%)にする
		if ($share != -1) {
			$result = $share;
		}

	    //---------------------------
        // テーブル表示
		//---------------------------
		if ($listArray[$item.'_enable_'.$pre_i] === 'and') {
			echo '<tr id='.$item.'_'.$pre_i.'_and>';
		}
		else if ($listArray[$item.'_enable_'.$pre_i] === 'or') {
			echo '<tr id='.$item.'_'.$pre_i.'_or>';
		}
		else {
			echo '<tr>';
		}
        echo '<td width="150px" class="left">'.$title.'</td>';
        echo '<td width="100px">'.$base_name.'</td>';
        echo '<td width="80px" class="'.selectClassOfValue($target_unit).'">'.formatNumber($target_unit).'</td>';
        echo '<td width="80px" class="'.selectClassOfValue($target_cost).'">'.formatNumber($target_cost).'</td>';
        echo '<td width="80px">'.$target_rate.'</td>';
        echo '<td width="80px">'.$prize_base_name.'</td>';
        echo '<td width="80px">'.formatNumber($prize_cost).'</td>';
		echo '<td width="80px">'.$prize_rate.'</td>';
		if ((strpos($target_item, "LM") !== false || strpos($target_item, "LS") !== false) && $unitmsg === "台") {
			echo '<td width="80px" class="'.selectClassOfValue($result).'">'.formatNumber($result,2).' ('.$totalMinusNum.')'.$unitmsg.'</td>';
		}
		else {
			echo '<td width="80px" class="'.selectClassOfValue($result).'">'.formatNumber($result,2).$unitmsg.'</td>';
		}
		// 達成率の背景カラー取得
		$bg_color = printJudgeReachColor($result, $diff);
		echo '<td width="80px" class="'.$bg_color.'">'.$diffmsg.'</td>';
		echo '<td width="80px" class="'.selectClassOfValue($target_prize).'">'.formatNumber($target_prize).'</td>';
        echo '<td width="80px" class="'.selectClassOfValue($prize).'">'.formatNumber($prize).'</td>';
        echo '<td width="150px" class="left">'.$memo.'</td>';
        echo '</tr>';

		// AND条件の場合、1行目が達成で2行目が未達成の時の場合に限り、1行目の賞金額を達成時の賞金に移動させる
		if ($listArray[$item.'_enable_'.$pre_i] === 'and') {
			$tmp = $item.'_'.$pre_i.'_and';
			echo '<script language=javascript>checkAndCondition("'.$tmp.'")</script>';
		}
		else if ($listArray[$item.'_enable_'.$pre_i] === 'or') {
			$tmp = $item.'_'.$pre_i.'_or';
			echo '<script language=javascript>checkOrCondition("'.$tmp.'")</script>';
		}
    }
	echo '</table>';
	if (local_config::FEATURE_CHANGE_COLOR_ACCODING_TO_REACH) {
		printReachPattern();	// 達成率のパターン別の色分けサンプルを表示
	}
    echo '</br>';
}



 /**
 * ----------------------------------------------------------
 * printPartnerPrizeSetting()
 * 提携の設定用の関数
 * @param $item        種目
 * @param $postArray   POSTで送られてきた設定値
 * @param $listArray   設定値
 * ----------------------------------------------------------
 */
function printPartnerPrizeSetting($item, $postArray, $listArray) {

    //-------------------------
    // 年度一覧を取得
    //-------------------------
	$listArray['fiscal_year_list'] = getFiscalYearList();
	if (count($listArray['fiscal_year_list']) == 0) {
		return 'no_data';
    }

    //-------------------------
    // 種目一覧を取得
    //-------------------------
	$listArray['item_list'] = getItemList('non-select');
	if (count($listArray['item_list']) == 0) {
		return 'no_item';
	}
	
	// 補助種目一覧を取得し、メイン種目と結合
	$subitemList = getSubItemList('no-insert');
	if (count($subitemList) > 0) {
		$listArray['item_list'] = array_merge($listArray['item_list'], $subitemList);
	}

	if (local_config::FEATURE_LC_HOLD_NUMBER) {
		// 特殊な種目一覧を取得し、メイン種目と結合
		$spitemList = getSpecialItemList();
		if (count($subitemList) > 0) {
			$listArray['item_list'] = array_merge($listArray['item_list'], $spitemList);
		}
	}

    //-------------------------
    // 提携企業一覧を取得
    //-------------------------
	// 提携企業一覧を取得
	if (isset($postArray['fiscal_year'])) {
		$listArray['partner_list'] = getPartnerList($postArray['fiscal_year'], $postArray['item']);
	}
	else {
		$listArray['partner_list'] = getPartnerList($listArray['fiscal_year_list'][0]['value'], $postArray['item']);
	}
	if (count($listArray['partner_list']) == 0) {
		return 'no_partner';
	}
	elseif (count($listArray['partner_list']) > 0 && $postArray['item'] !== 'ALL') {
		// 1社以上ある場合は先頭に合計を追加
		array_unshift($listArray['partner_list'], array('value'=>'TOTAL', 'name'=>'合計'));
	}
	array_unshift($listArray['partner_list'], array('value'=>'NONE', 'name'=>'----'));

    //-------------------------
    // 地区一覧を取得
    //-------------------------
	$listArray['area_list'] = getAreaList();
	if (count($listArray['area_list']) == 0) {
		return 'no_item';
	}
	array_unshift($listArray['area_list'], array('value'=>'%', 'name'=>'全地域'));
    
    //-------------------------
    // セレクトボックス作成
    //-------------------------

    // 有効/無効設定リスト
    $enable_list = array(
        array('value' => 'disable',    'name' =>'---'),
		array('value' => 'enable',     'name' =>'基本'),
		array('value' => 'or',         'name' =>'↑OR'),
		array('value' => 'and',        'name' =>'↑AND'),
		array('value' => 'plus',       'name' =>'↑＋')
    );

    // 期間設定リスト
    $period_list = array(
		array('value' => 'none',        'name' => '----'),
        array('value' => 'summer',      'name' => config::SUMMER_CAMPAIGN_NAME),
        array('value' => 'autumn',      'name' => config::AUTUMN_CAMPAIGN_NAME),
        array('value' => 'spring',      'name' => config::SPRING_CAMPAIGN_NAME),
        array('value' => '1_trimester', 'name' => '三半期（4～7月）'),
        array('value' => '2_trimester', 'name' => '三半期（8～11月）'),
        array('value' => '3_trimester', 'name' => '三半期（12～3月）'),
        array('value' => '1_half',      'name' => '上半期（4～9月）'),
        array('value' => '2_half',      'name' => '下半期（10～3月）'),
		array('value' => 'year',        'name' => '年間（4～3月）'),
		array('value' => '1',     		'name' => '1月（単月）'),
		array('value' => '2',           'name' => '2月（単月）'),
		array('value' => '3',           'name' => '3月（単月）'),
		array('value' => '4',           'name' => '4月（単月）'),
		array('value' => '5',           'name' => '5月（単月）'),
		array('value' => '6',           'name' => '6月（単月）'),
		array('value' => '7',           'name' => '7月（単月）'),
		array('value' => '8',           'name' => '8月（単月）'),
		array('value' => '9',           'name' => '9月（単月）'),
		array('value' => '10',          'name' => '10月（単月）'),
		array('value' => '11',          'name' => '11月（単月）'),
		array('value' => '12',          'name' => '12月（単月）'),
		array('value' => 'until_7',     'name' => '7月まで'),
		array('value' => 'until_11',    'name' => '11月まで')
    );

    // 基準リスト（順番を変えるとJavaScriptに影響でるので注意！！）
    $base_list = array(
        array('value' => 'none',   		      'name' => '----'),
        array('value' => 'unit',   		      'name' => '目標数'),
		array('value' => 'cost',   		      'name' => '目標金額'),
		array('value' => 'shop',              'name' => '稼動店舗数'),
		array('value' => 'share_LL',          'name' => 'シェア（LL用）'),
		array('value' => 'sys_plan',          'name' => '計画値（S参照：年間計画）'),
		array('value' => 'doyu_plan',         'name' => '同友別計画値（S参照：年間計画）'),
		array('value' => 'sys_camp_honbu',    'name' => '本部目標（S参照：キャンペーン）'),
		array('value' => 'sys_camp_area',     'name' => '地区目標（S参照：キャンペーン）'),
		array('value' => 'sys_camp_douyu',    'name' => '同友別目標（S参照：キャンペーン）'),
		array('value' => 'sys_lastresult', 	  'name' => '前年実績値（S参照）'),
		array('value' => 'sys_2y_lastresult', 'name' => '前々年実績値（S参照）'),
        array('value' => 'up',                'name' => '↑ 共通')
    );

    // 賞金基準リスト
    $prizebase_list = array(
        array('value' => 'none',     'name' => '----'),
        array('value' => 'unit',     'name' => '単位あたりの賞金'),
        array('value' => 'achieve',  'name' => '単位の賞金✕達成率'),
		array('value' => 'rate',     'name' => '実績に対する料率'),
		array('value' => 'diffrate', 'name' => '前年(前々年)を超えた実績に対する料率'),
		array('value' => 'single',   'name' => '褒賞金(固定)'),
		array('value' => 'shop',     'name' => '稼働同友数あたりの賞金'),
		array('value' => 'shop_over','name' => '指定稼働同友数以上に賞金'),
		array('value' => 'up',       'name' => '↑ いずれか')
    );

    // 実績基準リスト
    $resultbase_list = array(
		array('value' => 'none',   'name' => '----'),
		array('value' => 'up',     'name' => '↑使用'),
		array('value' => 'minus',  'name' => '↑から→を引く'),
		array('value' => 'double', 'name' => '↑：条件、→：賞金'),
		array('value' => 'input',  'name' => '条件：システム、賞金：手動')
    );

    // 補助項目も含めた実績の一覧

    // 全種目を選択している場合のみ実行
    // if ($postArray['item'] === 'ALL' || count($postArray) == 2) {
    //     echo '<script type="text/javascript">
    //             window.onload=changeSelectBoxContents;
    //           </script>';
    // }

    // テーブルカラム設定
    echo '<table id="prizetable_'.$item.'">
          <tr class="bg_wet_asphalt">
          <td rowspan="2">設定</td><td rowspan="2">期間</td><td colspan="4">達成条件</td><td colspan="3">条件達成時の賞金</td><td colspan="2">対象実績</td><td rowspan="2">メモ</td><td rowspan="2">操作</td></tr>
          <tr>
          <td>達成基準</td><td>数値</td><td>金額(千円)</td><td>比率(%)</td>
          <td>賞金基準</td><td>賞金額(円)</td><td>料率(%)</td>
          <td>システム参照</td><td>手動入力</td></tr>';
    echo '</tr>';
    
    // 設定値入力テーブル表示
    //for ($i = 0; $i < 30; $i++) {
	$i = 0;
	while(1) {

		// 設定が入っている行だけ表示
		// ※設定がない場合は、最低1行だけ表示する
		$null_flag = false;
		if ($listArray[$item.'_enable_'.$i] === null) {
			$null_flag = true;
			$listArray[$item.'_enable_'.$i] = 'disable';
		}

		if ($null_flag && $i > 0) {
			break;	// 1行以上表示されている場合
		}

        echo '<tr>';
        
        // 有効/無効
		echo '<td>';
        printSelectBox($enable_list, $item.'_enable_'.$i, '50', $listArray[$item.'_enable_'.$i]);
        echo '</td>';

        // 判定期間
        echo '<td>';
        printSelectBox($period_list, $item.'_period_'.$i, '150', $listArray[$item.'_period_'.$i]);
        echo '</td>';

        // 達成基準リスト
        echo '<td>';
        printSelectBox($base_list, $item.'_base_'.$i, '100', $listArray[$item.'_base_'.$i], 'changeResultDropdownList("'.$item.'", '.$i.')');
        echo '</td>';

        // 目標台数
        echo '<td>';
        printTextBox($item.'_target_unit_'.$i, 80, 'right', $listArray[$item.'_target_unit_'.$i]);
        echo '</td>';
        // 目標金額
        echo '<td>';
        printTextBox($item.'_target_cost_'.$i, 80, 'right', $listArray[$item.'_target_cost_'.$i]);
        echo '</td>';
        // 前年比率 または 達成基準に対する比率を設定
        echo '<td>';
        printTextBox($item.'_target_rate_'.$i, 80, 'right', $listArray[$item.'_target_rate_'.$i]);
        echo '</td>';

        // 賞金基準
        echo '<td>';
        printSelectBox($prizebase_list, $item.'_prizebase_'.$i, '150', $listArray[$item.'_prizebase_'.$i]);
        echo '</td>';

        // 賞金額
        echo '<td>';
        printTextBox($item.'_prize_cost_'.$i, 80, 'right', $listArray[$item.'_prize_cost_'.$i]);
        echo '</td>';
        // 料率
        echo '<td>';
        printTextBox($item.'_prize_rate_'.$i, 80, 'right', $listArray[$item.'_prize_rate_'.$i]);
        echo '</td>';

        // 実績リスト
        echo '<td>';
        printSelectBox($resultbase_list, $item.'_resultbase_'.$i, '120', $listArray[$item.'_resultbase_'.$i]);
		printSelectBox($listArray['fiscal_year_list'], $item.'_fiscal_year_'.$i, 60, $listArray[$item.'_fiscal_year_'.$i]);
		printSelectBox($listArray['item_list'], $item.'_item_'.$i, 100, $listArray[$item.'_item_'.$i]);
		printSelectBox($listArray['partner_list'], $item.'_partner_'.$i, 150, $listArray[$item.'_partner_'.$i]);
		printSelectBox($listArray['area_list'], $item.'_area_'.$i, 70, $listArray[$item.'_area_'.$i]);
        echo '</td>';
        echo '<td>';
        printTextBox($item.'_input_result_'.$i, 80, 'right', $listArray[$item.'_input_result_'.$i]);
        echo '</td>';

        // メモ
        echo '<td>';
        printTextBox($item.'_memo_'.$i, 150, 'left', $listArray[$item.'_memo_'.$i]);
        echo '</td>';

		// 行に関する操作
		echo '<td>';
		printButton('追', $item.'_add_'.$i, 'addNewPartnerPrizeSettingLine("'.$item.'", "'.$i.'")', 'width:30px');	// 追加ボタン
		printButton('削', $item.'_del_'.$i, 'deletePartnerPrizeSettingLine("'.$item.'", "'.$i.'")', 'width:30px');	// 削除ボタン
		echo '</td>';

		echo '</tr>';

		// 無効、またはnullになった時点で終了
		// 最低1行は表示する
		if ($null_flag) {
		 	break;
		}
		 else {
		 	$i++;
		}
    }

    echo '</table>';
	echo '<br />';
}

//=================================================================
// デザイン部
//=================================================================

//---------------------------------------------
// コンテンツ内メニュー
//---------------------------------------------
?>

<?php
//echo '<p>GET '; printArray($getArray).'</p>';
//echo '<p>POST '; printArray($postArray).'</p>';
?>

<?php
//---------------------------------------------
// 支部表彰得点状況 表示画面
//---------------------------------------------
if ($page === 'view') { ?>

<div id="contents_select">

<form style="display:inline" id='result_view' method="POST" action="index.php?reg=partner_prize&type=<?php echo $getArray['type']?>">
	<?php printSelectBox($listArray['fiscal_year_list'], 'fiscal_year', 70, $postArray['fiscal_year'], 'changeSelectBoxContents()') ?>年度
	<?php printSubmitButton('表示', 'show'); ?>
</form>

<?php
// 設定ボタンは管理者・提携のみ
if (($listArray['user_info']['auth'] == config::USER_ADMIN || $listArray['user_info']['auth'] == config::USER_PARTNER) && isset($postArray['show'])) { ?>
	<form id='change_setting' style="display:inline" method="POST" action="index.php?reg=partner_prize&type=setting">
		<?php printSubmitButton('設定', 'setting', 'setFiscalYearToHiddenParam()'); ?>
		<!--<input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>"> -->
	</form>
<?php } ?>

</div>
<hr>

<div id="contents">
	<?php
	echo '<p>本部支援施策を閲覧したい年度を選択肢し、「表示」ボタンを押してください。</p>';
	if (($listArray['user_info']['auth'] != config::USER_EXECUTIVE || $listArray['user_info']['auth'] == config::USER_PARTNER) && isset($postArray['show'])) {
		echo '<p>※実績個数、単価、料率などを変更する場合は「設定」ボタンを押してください。</p><br />';
	}

    if (isset($postArray['show'])) { ?>

		<hr><font class="bold">■ <?php echo $postArray['fiscal_year'] ?>年度の本部年間施策「キャンペーン賞金」</font><hr>
		<p>計算方法：期間実績✕料率✕参加率(%)=賞金（円単位未満四捨五入)<br />※参加率100%の場合、10%のボーナス加算</p><br />
		<?php
			printCampaignPrizeResultTable($postArray, $listArray, 'summer');
			printCampaignPrizeResultTable($postArray, $listArray, 'autumn');
			printCampaignPrizeResultTable($postArray, $listArray, 'spring');
		?>

		<?php
		//---------------------------------
		// 【LM】支援施策
        ?>
        <hr><p class="bold">■ LM（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
		<p class="bold">【LM：本部】三菱自動車工業（株）の販促費獲得状況</p>
		<?php printPartnerPrize('LM', 100, $postArray, $listArray['p_prize']['LM']); ?>
        <br />
		<p class="bold">【LM：地元】三菱自動車工業（株）販促費獲得状況</p>
		<?php printPartnerPrize('LLM', 100, $postArray, $listArray['p_prize']['LLM']); ?>
		<?php
		//---------------------------------
		// 【LS】支援施策
        ?>
        <hr><p class="bold">■ LS（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
        <p class="bold">【LS：本部】スズキ（株）の販促費獲得状況</p>
		<?php printPartnerPrize('LS', 100, $postArray, $listArray['p_prize']['LS']); ?>
        <br />
		<p class="bold">【LS：地元】スズキ（株）の販促費獲得状況</p>
		<?php printPartnerPrize('LLS', 100, $postArray, $listArray['p_prize']['LLS']); ?>
		<?php
		//---------------------------------
		// 【LT】ブリヂストンタイヤジャパン（株）支援施策
        ?>
        <hr><p class="bold">■ LT ブリヂストン（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
        <p class="bold">【LT：本部】ブリヂストンタイヤジャパン（株）の販促費獲得状況</p>
		<?php printPartnerPrize('LTB', 100, $postArray, $listArray['p_prize']['LTB']); ?>
        <br />
        <p class="bold">【LT：地元】ブリヂストンタイヤジャパン（株）の販促費獲得状況</p>
		<?php printPartnerPrize('LLTB', 100, $postArray, $listArray['p_prize']['LLTB']); ?>
        <br />

		<?php
		//---------------------------------
		// 【LT】横浜ゴム（株）支援施策
		?>
		<hr><p class="bold">■ LT ヨコハマタイヤ（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
		<p class="bold">【LT：本部】横浜ゴム（株）の販促費獲得状況</p>
		<?php printPartnerPrize('LTY', 100, $postArray, $listArray['p_prize']['LTY']); ?>
        <br />
        <p class="bold">【LT：地元】横浜ゴム（株）の販促費獲得状況</p>
		<?php printPartnerPrize('LLTY', 100, $postArray, $listArray['p_prize']['LLTY']); ?>
        <br />

		<?php
		//---------------------------------
		// 【LH】東京海上日動火災保険、あいおいニッセイ同和損保 支援施策
		?>
		<hr><p class="bold">■ LH 東京海上日動火災保険、あいおいニッセイ同和損保（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
		<p class="bold">【LH：地元】東京海上日動火災保険の販促費獲得状況</p>
		<?php printPartnerPrize('LLHT', 100, $postArray, $listArray['p_prize']['LLHT']); ?>
		<br />
        <p class="bold">【LH：地元】あいおいニッセイ同和損保の販促費獲得状況</p>
		<?php printPartnerPrize('LLHA', 100, $postArray, $listArray['p_prize']['LLHA']); ?>

		<?php
		//---------------------------------
		// 【LO】EMGルブリカンツ（同）＆パルスター（株）支援施策
		?>
		<hr><p class="bold">■ LO（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
		<p class="bold">【LO：本部】EMGルブリカンツ（同）＆パルスター（株）の販促費獲得状況</p>
		<?php printPartnerPrize('LOP', 100, $postArray, $listArray['p_prize']['LOP']); ?>
		<br />
        <p class="bold">【LO：地元】EMGルブリカンツ（同）＆パルスター（株）の販促費獲得状況</p>
		<?php printPartnerPrize('LLOP', 100, $postArray, $listArray['p_prize']['LLOP']); ?>
		<?php
		//---------------------------------
		// 【LE】パナソニック（株）支援施策
		?>
		<hr><p class="bold">■ LE パナソニック（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
		<p class="bold">【LE：本部】パナソニック（株）の販促費獲得状況</p>
		<?php printPartnerPrize('LEP', 100, $postArray, $listArray['p_prize']['LEP']); ?>
        <br />
        <p class="bold">【LE：地元】パナソニック（株）の販促費獲得状況</p>
		<?php printPartnerPrize('LLEP', 100, $postArray, $listArray['p_prize']['LLEP']); ?>
        <br />

		<?php
		//---------------------------------
		// 【LE】（株）ジーエス・ユアサ バッテリー支援施策
		?>
		<hr><p class="bold">■ LE ジーエス・ユアサ（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
		<p class="bold">【LE：本部】（株）ジーエス・ユアサの販促費獲得状況</p>
		<?php printPartnerPrize('LEG', 100, $postArray, $listArray['p_prize']['LEG']); ?>
        <br />
        <p class="bold">【LE：地元】（株）ジーエス・ユアサの販促費獲得状況</p>
		<?php printPartnerPrize('LLEG', 100, $postArray, $listArray['p_prize']['LLEG']); ?>
        <br />

        <?php
        //---------------------------------
        // 【LL】（株）ジャックス 支援施策
        ?>
		<hr><p class="bold">■ LL ジャックス（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
        <p class="bold">【LL：地元】（株）ジャックス の販促費獲得状況</p>
		<?php printPartnerPrize('LLLJ', 100, $postArray, $listArray['p_prize']['LLLJ']); ?>
        <br />

        <?php
        //---------------------------------
        // 【LL】（株）オリエントコーポレーション 支援施策
        ?>
		<hr><p class="bold">■ LL オリエントコーポレーション（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
        <p class="bold">【LL：地元】（株）オリエントコーポレーションの販促費獲得状況</p>
		<?php printPartnerPrize('LLLO', 100, $postArray, $listArray['p_prize']['LLLO']); ?>
        <br />


    
    <?php } ?>
</div>

<script>
/**
 * =============================================
 * submit時にドロップダウンボックスに設定されている
 * fiscal_yearの値を取得してhiddenでセットする
 * =============================================
 */
function setFiscalYearToHiddenParam(){
	var myform = document.getElementById('change_setting');
	var selectbox = document.getElementById('fiscal_year');
	var year = selectbox.value;

	// エレメントを作成
	var ele = document.createElement('input');
    // データを設定
    ele.setAttribute('type', 'hidden');
    ele.setAttribute('name', 'fiscal_year');
    ele.setAttribute('value', year);
    // 要素を追加
    myform.appendChild(ele);
	// form送信
	myform.submit();
}
</script>

<?php }
//---------------------------------------------
// 支援施策 設定画面
//---------------------------------------------
elseif ($page === 'setting') { ?>

<form id='result_view' method="POST" action="index.php?reg=partner_prize&type=view">

<div id="contents_select">
    <?php printSubmitButton('保存', 'save'); ?>
	<?php printSubmitButton('前年度設定読込', 'last_setting_read'); ?>
    <input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>">
    <br /><br />
    <p><?php echo $postArray['fiscal_year'] ?>年度の販促費設定・実績を入力し、「保存」ボタンを押してください。</p><br />
	<p>前年度の設定を読み込んだ場合は「保存」ボタンを押すまで設定は反映されません。</p>
</div>
<hr>

<div id="contents">

<?php
	//---------------------------------
	// 【LM】三菱自動車工業支援施策
	if ($listArray['user_info']['auth'] == config::USER_ADMIN || $listArray['user_info']['user'] === config::LMK || $listArray['user_info']['user'] === config::LMS) { ?>
		<hr><p class="bold">■ LM（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
		<p class="bold">【LM：本部】三菱自動車の支援施策の設定</p>
		<?php printPartnerPrizeSetting('LM', $postArray, $listArray['p_prize']['LM']); ?>
		<br />
        <p class="bold">【LM：地元】三菱自動車の支援施策の設定</p>
		<?php printPartnerPrizeSetting('LLM', $postArray, $listArray['p_prize']['LLM']); ?>
	<?php }

	//---------------------------------
	// 【LS】スズキ支援施策
	if ($listArray['user_info']['auth'] == config::USER_ADMIN || $listArray['user_info']['user'] === config::LS) { ?>
		<hr><p class="bold">■ LS（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
		<p class="bold">【LS：本部】スズキ（株）の支援施策の設定</p>
		<?php printPartnerPrizeSetting('LS', $postArray, $listArray['p_prize']['LS']); ?>
		<br />
        <p class="bold">【LS：地元】スズキ（株）の支援施策の設定</p>
		<?php printPartnerPrizeSetting('LLS', $postArray, $listArray['p_prize']['LLS']); ?>
	<?php }

	//---------------------------------
	// 【LT】ブリヂストン支援施策
	if ($listArray['user_info']['auth'] == config::USER_ADMIN || $listArray['user_info']['user'] === config::LTB) { ?>
		<hr><p class="bold">■ LT ブリヂストン（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
		<p class="bold">【LT：本部】ブリヂストンタイヤジャパン（株）の支援施策の設定</p>
		<?php printPartnerPrizeSetting('LTB', $postArray, $listArray['p_prize']['LTB']); ?>
		<br />
        <p class="bold">【LT：地元】ブリヂストン支援施策の設定</p>
		<?php printPartnerPrizeSetting('LLTB', $postArray, $listArray['p_prize']['LLTB']); ?>
	<?php }

	//---------------------------------
	// 【LT】横浜ゴム（株）支援施策
	if ($listArray['user_info']['auth'] == config::USER_ADMIN || $listArray['user_info']['user'] === config::LTY) { ?>
		<hr><p class="bold">■ LT ヨコハマタイヤ（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
		<p class="bold">【LT：本部】横浜ゴム（株）の支援施策の設定</p>
		<?php printPartnerPrizeSetting('LTY', $postArray, $listArray['p_prize']['LTY']); ?>
		<br />
        <p class="bold">【LT：地元】横浜ゴム（株）支援施策の設定</p>
		<?php printPartnerPrizeSetting('LLTY', $postArray, $listArray['p_prize']['LLTY']); ?>
	<?php }

	//---------------------------------
	// 【LH】東京海上日動火災保険、あいおいニッセイ同和損保（株）支援施策
	if ($listArray['user_info']['auth'] == config::USER_ADMIN || $listArray['user_info']['user'] === config::LHT) { ?>
		<hr><p class="bold">■ LH 東京海上日動火災保険、あいおいニッセイ同和損保（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
		<p class="bold">【LH：地元】東京海上日動火災保険の支援施策の設定</p>
		<?php printPartnerPrizeSetting('LLHT', $postArray, $listArray['p_prize']['LLHT']); ?>
		<br />
        <p class="bold">【LH：地元】あいおいニッセイ同和損保支援施策の設定</p>
		<?php printPartnerPrizeSetting('LLHA', $postArray, $listArray['p_prize']['LLHA']); ?>
	<?php }

	//---------------------------------
	// 【LO】EMGルブリカンツ（同）＆パルスター（株）支援施策支援施策
	if ($listArray['user_info']['auth'] == config::USER_ADMIN || $listArray['user_info']['user'] === config::LOP) { ?>
		<hr><p class="bold">■ LO（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
		<p class="bold">【LO：本部】</p>
		<?php printPartnerPrizeSetting('LOP', $postArray, $listArray['p_prize']['LOP']); ?>
		<br />
        <p class="bold">【LO：地元】</p>
		<?php printPartnerPrizeSetting('LLOP', $postArray, $listArray['p_prize']['LLOP']); ?>
	<?php }

	//---------------------------------
	// 【LE】パナソニック（株） 支援施策
	if ($listArray['user_info']['auth'] == config::USER_ADMIN || $listArray['user_info']['user'] === config::LEP) { ?>
		<hr><p class="bold">■ LE パナソニック（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
		<p class="bold">【LE：本部】パナソニック（株）の支援施策の設定</p>
		<?php printPartnerPrizeSetting('LEP', $postArray, $listArray['p_prize']['LEP']); ?>
		<br />
        <p class="bold">【LE：地元】パナソニック（株）の支援施策の設定</p>
		<?php printPartnerPrizeSetting('LLEP', $postArray, $listArray['p_prize']['LLEP']); ?>
	<?php }

	//---------------------------------
	// 【LE】（株）ジーエス・ユアサ 支援施策
	if ($listArray['user_info']['auth'] == config::USER_ADMIN || $listArray['user_info']['user'] === config::LEG) { ?>
		<hr><p class="bold">■ LE ジーエス・ユアサ（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
		<p class="bold">【LE：本部】（株）ジーエス・ユアサの支援施策の設定</p>
		<?php printPartnerPrizeSetting('LEG', $postArray, $listArray['p_prize']['LEG']); ?>
		<br />
        <p class="bold">【LE：地元】（株）ジーエス・ユア支援施策の設定</p>
		<?php printPartnerPrizeSetting('LLEG', $postArray, $listArray['p_prize']['LLEG']); ?>
	<?php }
    
	//---------------------------------
	// 【LL】（株）ジャックス 支援施策
	if ($userInfo['auth'] == config::USER_ADMIN || $userInfo['user'] === config::LLJ) { ?>
		<hr><p class="bold">■ LL ジャックス（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
		<p class="bold">【LL：地元】（株）ジャックス 支援施策の設定</p>
		<?php printPartnerPrizeSetting('LLLJ', $postArray, $listArray['p_prize']['LLLJ']); ?>
	<?php }

	//---------------------------------
	// 【LL】（株）オリエントコーポレーション 支援施策
	if ($userInfo['auth'] == config::USER_ADMIN || $userInfo['user'] === config::LLO) { ?>
		<hr><p class="bold">■ LL オリエントコーポレーション（<?php echo $postArray['fiscal_year'] ?>年度）</p><hr>
		<p class="bold">【LL：地元】（株）オリエントコーポレーションの支援施策の設定</p>
		<?php printPartnerPrizeSetting('LLLO', $postArray, $listArray['p_prize']['LLLO']); ?>
	<?php } ?>
    
</div>
</form>

<script>
/**
 * =============================================
 * 達成基準でシステム参照を選択した時に
 * システム参照のドロップダウンを合計だけに変更するスクリプト
 * =============================================
 */
function changeResultDropdownList(item, cnt){
	
	// nameを生成
	target_select_name = item + "_base_" + cnt;
	target_result_name = item + "_partner_" + cnt;
	target_area_name = item + "_area_" + cnt;

	var selectbox = document.getElementsByName(target_select_name);
	var resultbox = document.getElementsByName(target_result_name);
	var areabox = document.getElementsByName(target_area_name);

	// セレクトボックスが「sys_camp_honbu」の場合
	// ここでインデックス指定しているので、「sys_camp_honbu」が7番目じゃないとバグになるので注意
	if (selectbox[0].selectedIndex == 7) {
		window.alert('注意！\nキャンペーンの本部目標は「合計」「全地域」のみ選択可能です。');
		resultbox[0].selectedIndex = 1;	// 合計にする
		areabox[0].selectedIndex = 0;	// 全地域にする
	}
}

/**
 * =============================================
 * 設定行を増やす場合のスクリプト
 * =============================================
 */
function addNewPartnerPrizeSettingLine(item, cnt) {

	// ID、name属性の名称一覧
	var id_list = ['enable', 'period', 'base', 'target_unit', 'target_cost', 'target_rate',
	               'prizebase', 'prize_cost', 'prize_rate', 'resultbase', 'fiscal_year', 
				   'item', 'partner', 'area', 'input_result', 'memo', 'add', 'del'];

	// テーブルには見出しがあるのでcntを2つ進める
	add_rowcnt = parseInt(cnt) + 3;

	// 該当行を含むテーブルを判定する
	var table_id = 'prizetable_' + item;
	var table = document.getElementById(table_id);

	// 最大100行まで
	if (table.rows.length > 101) {
		alert('これ以上の行の追加はできません。');
		return;
	}

	// 該当行の次に追加
	var tr = table.insertRow(add_rowcnt);

	// 変更が必要な最初のidの番号
	var start_row_num = parseInt(cnt) + 1;
	// 最後の行のid番号を取得(見出し分(2行)と追加した1行、スタートが0なので全部で-4)
	var last_row_num = table.rows.length - 4;

	//-----------------------------------------------------
	// 追加することで下にズレた行の数字をインクリメントする処理
	//-----------------------------------------------------
	var newname_cnt = last_row_num + 1;
	for (var row_cnt = last_row_num; row_cnt >= start_row_num; row_cnt--) {

		// 用意しているIDの名称の数だけループさせて、すべて変更する
		for (i = 0; i < id_list.length; i++) {
			var attr_id = item + '_' + id_list[i] + '_' + row_cnt;
			var attr    = document.getElementById(attr_id);

			// 置き換え
			var new_attr_id = item + '_' + id_list[i] + '_' + newname_cnt;
			attr.setAttribute('id', new_attr_id);
			attr.setAttribute('name', new_attr_id);

			if (id_list[i] == 'base') {
				// baseの時は関数も変更
				var new_function = 'changeResultDropdownList("'+ item +'",' + newname_cnt + ')';
				attr.setAttribute('onchange', new_function);
			}
			else if (id_list[i] == 'add') {
				// baseの時は関数も変更
				var new_function = 'addNewPartnerPrizeSettingLine("'+ item +'",' + newname_cnt + ')';
				attr.setAttribute('onclick', new_function);
			}
			else if (id_list[i] == 'del') {
				// baseの時は関数も変更
				var new_function = 'deletePartnerPrizeSettingLine("'+ item +'",' + newname_cnt + ')';
				attr.setAttribute('onclick', new_function);
			}
		}
		// 次のIDへ
		newname_cnt--;
	}

	//-----------------------------------------------------
	// 追加した行に関する処理
	//-----------------------------------------------------
	// 追加した行のセルの内容をコピーしていく
	copy_row_num = start_row_num + 1;
	copy_tr = table.rows[2];	// 最初の行を取得

	// セルに最初の行のHTMLをコピー
	for (td_num = 0; td_num < copy_tr.cells.length; td_num++) {
		// コピー元の行のHTMLを取得
		var copy_html = copy_tr.cells[td_num].innerHTML;

		// idとnameの数字を追加する行の数字に置換する
		for (i = 0; i < id_list.length; i++) {
			var search_attr_id = item + '_' + id_list[i] + '_0';				// 検索用
			var new_attr_id = item + '_' + id_list[i] + '_' + start_row_num;	// 置換用
			copy_html = copy_html.replace(new RegExp(search_attr_id, "g"), new_attr_id);
		}
	
		// セルに挿入
		var td_name = tr.insertCell(td_num);
		td_name.innerHTML = copy_html;
	}

	// 用意しているIDの名称の数だけループさせて、すべて変更する
	for (i = 0; i < id_list.length; i++) {
		var attr_id = item + '_' + id_list[i] + '_' + start_row_num;
		var attr    = document.getElementById(attr_id);

		if (id_list[i] != 'add' && id_list[i] != 'del') {
			attr.setAttribute('value', "");
			attr.selectedIndex = 0;
		}

		if (id_list[i] == 'base') {
			// baseの時は関数も変更
			var new_function = 'changeResultDropdownList("'+ item +'",' + newname_cnt + ')';
			attr.setAttribute('onchange', new_function);
		}
		else if (id_list[i] == 'add') {
			// addの時は関数も変更
			var new_function = 'addNewPartnerPrizeSettingLine("'+ item +'",' + newname_cnt + ')';
			attr.setAttribute('onclick', new_function);
		}
		else if (id_list[i] == 'del') {
			// delの時は関数も変更
			var new_function = 'deletePartnerPrizeSettingLine("'+ item +'",' + newname_cnt + ')';
			attr.setAttribute('onclick', new_function);
		}

		attr
	}
}

/**
 * =============================================
 * 設定行を削除する場合のスクリプト
 * =============================================
 */
function deletePartnerPrizeSettingLine(item, cnt) {

	// ID、name属性の名称一覧
	var id_list = ['enable', 'period', 'base', 'target_unit', 'target_cost', 'target_rate',
	               'prizebase', 'prize_cost', 'prize_rate', 'resultbase', 'fiscal_year', 
				   'item', 'partner', 'area', 'input_result', 'memo', 'add', 'del'];

	// テーブルには見出しがあるのでcntを2つ進める
	delete_rowcnt = parseInt(cnt) + 2;

	// 該当行を含むテーブルを判定する
	var table_id = 'prizetable_' + item;
	var table = document.getElementById(table_id);

	// 該当行の削除
	// 最後の1行になった時は消さない
	if (table.rows.length > 3) {
		table.deleteRow(delete_rowcnt);
	}
	else {
		alert('これ以上の行の削除はできません。');
		return;
	}
	
	// 削除した行以降の行数取得(見出し分(2行)を除く)
	var row_len = table.rows.length - cnt - 2;	

	// 削除した行以降のセル要素の数字をデクリメントする
	var newname_cnt = parseInt(cnt);
	for (var row_cnt = 1; row_cnt <= row_len; row_cnt++) {

		// 用意しているIDの名称の数だけループさせて、すべて変更する
		for (i = 0; i < id_list.length; i++) {
			// 削除した行の次の行の要素を取得
			var nextname_cnt = parseInt(cnt) + row_cnt;
			var attr_id = item + '_' + id_list[i] + '_' + nextname_cnt;
			var attr    = document.getElementById(attr_id);

			// 置き換え
			var new_attr_id = item + '_' + id_list[i] + '_' + newname_cnt;
			attr.setAttribute('id', new_attr_id);
			attr.setAttribute('name', new_attr_id);

			if (id_list[i] == 'base') {
				// baseの時は関数も変更
				var new_function = 'changeResultDropdownList("'+ item +'",' + newname_cnt + ')';
				attr.setAttribute('onchange', new_function);
			}
			else if (id_list[i] == 'add') {
				// baseの時は関数も変更
				var new_function = 'addNewPartnerPrizeSettingLine("'+ item +'",' + newname_cnt + ')';
				attr.setAttribute('onclick', new_function);
			}
			else if (id_list[i] == 'del') {
				// baseの時は関数も変更
				var new_function = 'deletePartnerPrizeSettingLine("'+ item +'",' + newname_cnt + ')';
				attr.setAttribute('onclick', new_function);
			}
		}

		// 次のIDへ
		newname_cnt++;
	}
}

</script>

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