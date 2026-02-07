<?php
/**
 * =================================================================
 * president_prize.php
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
	'branch_promotion' => array(),
	'prize_list'       => array(),
	'point'            => 0,
	'baseEnterableNum' => 0
);

//--------------------------------
// 表示ページを選択
//--------------------------------
$page = selectPageForPresidentPrize($getArray, $postArray, $listArray);

/**
 * ----------------------------------------------------------
 * selectPageForPresidentPrize()
 * 社長賞の表示と設定で表示するページを選択する
 * @param $getArray：GETで送られてきたパラメータ
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function selectPageForPresidentPrize($getArray, &$postArray, &$listArray) {

	$page = '';

	// 選択中のメニューに応じた結果を表示
	switch ($getArray['type']) {
		case  'view':
            $page = makePresidentPrizeViewPage($postArray, $listArray);
			break;
		case  'setting':
            $page = makePresidentPrizeSettingPage($postArray, $listArray);
			break;
		default:
			break;
	}
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makePresidentPrizeViewPage()
 * 社長賞を閲覧するためのページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makePresidentPrizeViewPage($postArray, &$listArray) {

	$page = "view";

	// ログインユーザ情報を取得
	$listArray['user_info'] = getUserInfo($_SESSION['USERID']);

	// 年度一覧を取得
	$listArray['fiscal_year_list'] = getFiscalYearList();
	if (count($listArray['fiscal_year_list']) == 0) {
		return 'no_data';
	}

	//----------------------
    // 保存する時
	if (isset($postArray['save'])) {

	}

	//----------------------
	// 表示する時
	if (isset($postArray['show'])) {

		// 種目一覧を取得(LM+LSあり)
		$listArray['item_list'] = getItemList('no-insert', $item='', $postArray['fiscal_year'], true, false, true);
		if (count($listArray['item_list']) == 0) {
			return 'no_item';
		}

		// LC保有枚数を追加
		array_unshift($listArray['item_list'], array('value'=>'LC:spitem',  'name'=>'LC保有枚数', 'unit'=>'枚'));

		// 同友一覧を取得
		if (isset($postArray['fiscal_year'])) {
			$listArray['executive_list'] = getExecutiveList($postArray['fiscal_year'], 'campaign', '%');
			if (count($listArray['executive_list']) == 0) {
				return 'no_executive';
			}
		}
		//printArray($listArray['executive_list']);

		//====================
		// 実績・計画値の取得
		//====================
		// 種目ごとの年間、四半期、月別の実績と計画地を取得(※分母同友のみ)
		foreach ($listArray['item_list']  as $itemArray) {		
			$listArray['data_list'][$itemArray['value']] = getResultAndPlanByItem($postArray['fiscal_year'], $itemArray['value'], 'TOTAL', '%', true);
		}
		
		// LM未登録点のPIDを取得
		$lmmID = 0;
		$partnerList = getPartnerList($postArray['fiscal_year'], 'LM');
		foreach ($partnerList as $partnerArray) {
			if ($partnerArray['name'] === 'LM未登録店') {
				$lmmID = $partnerArray['value'];
			}
		}
		//DBGMSG('$lmmID='.$lmmID);

		// LM未登録店のデータを取得
		$listArray['data_list']['LM-M'] = getResultAndPlanByItem($postArray['fiscal_year'], 'LM', $lmmID, '%', true);

		//====================
		// 設定値の取得
		//====================
		// 支部への販促費「四半期販促費」を取得する
		$listArray['branch_promotion'] = getBranchPromotionInfo($postArray['fiscal_year'],  'ryoritsuB');
		if (count($listArray['branch_promotion']) == 0) {
			return 'no_data';
		}
		// 支部への販促費「キャンペーン賞金」の設定値を取得する
		$listArray['campaign_promotion'] = getBranchPromotionInfo($postArray['fiscal_year'],  'ryoritsuC');
		if (count($listArray['campaign_promotion']) == 0) {
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

		// 支部向けの決定した賞金を取得する
		$listArray['other_prize'] = getOtherBranchPromotionInfo($postArray['fiscal_year'], 'ryoritsuY');
		if (count($listArray['other_prize']) == 0) {
			return 'no_data';
		}

		// 手動入力している得点の読み込み
		$listArray['point_status'] = getPointStatusInfo($postArray['fiscal_year']);
		if (count($listArray['point_status']) == 0) {
			return 'no_data';
		}
		
		// デバッグ
		//printArray($listArray['data_list']);
		//printArray($listArray['branch_promotion']);
		//printArray($listArray['campaign_promotion']);
		//printArray($listArray['year_promotion']);
		//printArray($listArray['lc_hold_number']);
		//printArray($listArray['other_prize']);
	}

	return $page;
}

/**
 * ----------------------------------------------------------
 * makePresidentPrizeSettingPage()
 * 社長賞の設定画面を表示するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makePresidentPrizeSettingPage($postArray, &$listArray) {

    $page = "setting";

    return $page;
}

/**
 * ----------------------------------------------------------
 * printLMLSBonusPrizeTable()
 * 1.LM・LS販促費・ボーナス賞金に関するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
function printLMLSBonusPrizeTable($postArray, &$listArray) {

	echo '<p class="bold">＜4-6月＞</p>';
	printLMLSBonusPrizeQuarterTable($postArray,$listArray, 1);

	echo '<p class="bold">＜7-9月＞</p>';
	printLMLSBonusPrizeQuarterTable($postArray,$listArray, 2);

	echo '<p class="bold">＜10-12月＞</p>';
	printLMLSBonusPrizeQuarterTable($postArray,$listArray, 3);

	echo '<p class="bold">＜1-3月＞</p>';
	printLMLSBonusPrizeQuarterTable($postArray,$listArray, 4);

	// LM、LSボーナス賞金計算
	$lm_bonus_prize_1_half = $listArray['other_prize']['lm_bonus_prize_1_half'] * ($listArray['data_list']['LM']['result']['1_quarter']+$listArray['data_list']['LM']['result']['2_quarter']-$listArray['data_list']['LM-M']['result']['1_quarter']-$listArray['data_list']['LM-M']['result']['2_quarter']);
	$lm_bonus_prize_2_half = $listArray['other_prize']['lm_bonus_prize_2_half'] * ($listArray['data_list']['LM']['result']['3_quarter']+$listArray['data_list']['LM']['result']['4_quarter']-$listArray['data_list']['LM-M']['result']['3_quarter']-$listArray['data_list']['LM-M']['result']['4_quarter']);
	$ls_bonus_prize_1_half = $listArray['other_prize']['ls_bonus_prize_1_half'] * ($listArray['data_list']['LS']['result']['1_quarter']+$listArray['data_list']['LS']['result']['2_quarter']);
	$ls_bonus_prize_2_half = $listArray['other_prize']['ls_bonus_prize_2_half'] * ($listArray['data_list']['LS']['result']['3_quarter']+$listArray['data_list']['LS']['result']['4_quarter']);

	echo '<br /><p class="bold">＜ボーナス賞金＞</p>';
	echo '<table>';
	echo '<tr class="bg_wet_asphalt"><td width="50px">種目</td><td width="150px">上期ボーナス賞金 (円)</td><td width="150px">下期ボーナス賞金 (円)</td></tr>';
	echo '<tr><td>LM</td><td class="right bg_yellow" width="100px">'.formatNumber($lm_bonus_prize_1_half).'</td><td class="right bg_yellow" width="100px">'.formatNumber($lm_bonus_prize_2_half).'</td></tr>';
	echo '<tr><td>LS</td><td class="right bg_yellow" width="100px">'.formatNumber($ls_bonus_prize_1_half).'</td><td class="right bg_yellow" width="100px">'.formatNumber($ls_bonus_prize_2_half).'</td></tr>';
	echo '</table><br />';

	// 獲得賞金を加算
	$listArray['prize_list']['LM'] += round($lm_bonus_prize_1_half);
	$listArray['prize_list']['LM'] += round($lm_bonus_prize_2_half);
	$listArray['prize_list']['LS'] += round($ls_bonus_prize_1_half);
	$listArray['prize_list']['LS'] += round($ls_bonus_prize_2_half);
}

/**
 * ----------------------------------------------------------
 * printLMLSBonusPrizeQuarterTable()
 * 1.LM・LS販促費の四半期用のテーブルを作る
 * printLMLSBonusPrizeTable()から呼び出すだけの関数
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
function printLMLSBonusPrizeQuarterTable($postArray, &$listArray, $quarter) {

	$c_listArray = $listArray;

	echo '<table>';
	echo '<tr class="bg_wet_asphalt"><td width="50px">種目</td><td width="150px">実績(カッコ内は未登録店)</td><td width="100px">販促費(円)</td></tr>';
	// LM
	echo '<tr><td>LM</td>';
	echo '<td class="right">'.$c_listArray['data_list']['LM']['result'][$quarter.'_quarter'].'台 ('.$c_listArray['data_list']['LM-M']['result'][$quarter.'_quarter'].'台)</td>';

	// 賞金計算
	if ($postArray['fiscal_year'] >= 2020) {
		$prize = ($c_listArray['data_list']['LM']['result'][$quarter.'_quarter']-$c_listArray['data_list']['LM-M']['result'][$quarter.'_quarter']) * $c_listArray['branch_promotion']['LM:ryoritsuB_'.$quarter];
	}
	else {
		$prize = ($c_listArray['data_list']['LM']['result'][$quarter.'_quarter']-$c_listArray['data_list']['LM-M']['result'][$quarter.'_quarter']) * $c_listArray['branch_promotion']['LM:ryoritsuB_1'];
	}
	
	echo '<td class="right bg_yellow">'.formatNumber($prize).'</td></tr>';
	
	// LMの獲得賞金を加算
	$listArray['prize_list']['LM'] += round($prize);

	// LS
	echo '<tr><td>LS</td>';
	echo '<td class="right">'.$c_listArray['data_list']['LS']['result'][$quarter.'_quarter'].'台 (0台)</td>';
	$prize = $c_listArray['data_list']['LS']['result'][$quarter.'_quarter'] * $c_listArray['branch_promotion']['LS:ryoritsuB_'.$quarter];
	echo '<td class="right bg_yellow">'.formatNumber($prize).'</td></tr>';
	echo '</table>';

	// LMの獲得賞金を加算
	$listArray['prize_list']['LS'] += round($prize);
}

/**
 * ----------------------------------------------------------
 * printQuarterPrizeTable()
 * 2.四半期販促費に関するテーブルを表示
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
function printQuarterPrizeTable($postArray, &$listArray) {

	$tableHeader = '<tr class="bg_wet_asphalt"><td width="50px">種目</td><td width="100px">支部計画 (千円)</td><td width="100px">実績 (千円)</td><td width="80px">達成率</td><td width="80px">料率</td><td width="100px">販促金額 (円)</td></tr>';

	echo '<p class="bold">＜第1四半期 (4月～6月)＞</p>';
	echo '<table>';
	echo $tableHeader;
	printQuarterPrizeItemTable($postArray['fiscal_year'], $listArray, 'LT', 1);
	printQuarterPrizeItemTable($postArray['fiscal_year'], $listArray, 'LO', 1);
	printQuarterPrizeItemTable($postArray['fiscal_year'], $listArray, 'LE', 1);
	printQuarterPrizeItemTable($postArray['fiscal_year'], $listArray, 'LL', 1);
	echo '</table><br />';

	echo '<p class="bold">＜第2四半期 (7月～9月)＞</p>';
	echo '<table>';
	echo $tableHeader;
	printQuarterPrizeItemTable($postArray['fiscal_year'], $listArray, 'LT', 2);
	printQuarterPrizeItemTable($postArray['fiscal_year'], $listArray, 'LO', 2);
	printQuarterPrizeItemTable($postArray['fiscal_year'], $listArray, 'LE', 2);
	printQuarterPrizeItemTable($postArray['fiscal_year'], $listArray, 'LL', 2);
	echo '</table><br />';

	echo '<p class="bold">＜第3四半期 (10月～12月)＞</p>';
	echo '<table>';
	echo $tableHeader;
	printQuarterPrizeItemTable($postArray['fiscal_year'], $listArray, 'LT', 3);
	printQuarterPrizeItemTable($postArray['fiscal_year'], $listArray, 'LO', 3);
	printQuarterPrizeItemTable($postArray['fiscal_year'], $listArray, 'LE', 3);
	printQuarterPrizeItemTable($postArray['fiscal_year'], $listArray, 'LL', 3);
	echo '</table><br />';

	echo '<p class="bold">＜第4四半期 (1月～3月)＞</p>';
	echo '<table>';
	echo $tableHeader;
	printQuarterPrizeItemTable($postArray['fiscal_year'], $listArray, 'LT', 4);
	printQuarterPrizeItemTable($postArray['fiscal_year'], $listArray, 'LO', 4);
	printQuarterPrizeItemTable($postArray['fiscal_year'], $listArray, 'LE', 4);
	printQuarterPrizeItemTable($postArray['fiscal_year'], $listArray, 'LL', 4);
	echo '</table><br />';
}

/**
 * ----------------------------------------------------------
 * printQuarterPrizeItemTable()
 * 2.四半期販促費に関する種目ごとのテーブルを表示
 * @param $fiscal_year：年度
 * @param $listArray：データベースの各テーブルのデータ
 * @param $item     ：種目
 * @param $quarter  ：四半期
 * ----------------------------------------------------------
 */
function printQuarterPrizeItemTable($fiscal_year, &$listArray, $item, $quarter) {

	$c_listArray = $listArray;

	// 特別キャンペーン(達成率に関わらず一律の料率を与える)
	// ※2020年のCOLVIC-19のための対策を2020年サマーキャンペーンにおいて実施
	if (local_config::FEATURE_FIXED_POINT_AND_RATE) {
		$cnt = 0;
		$flag_fixed_rate_type = false;
		foreach (config::QUARTER_FIXED_RATE_TYPE as $value) {
			if ($value == $fiscal_year.'_'.$quarter) {
				// もし年度とキャンペーン種別が一致する組み合わせがあれば達成率に関わらず固定料率を設定する
				$c_listArray['branch_promotion'][$item.':ryoritsuB_2'] = config::QUARTER_FIXED_RATE[$cnt][$item];
				$c_listArray['branch_promotion'][$item.':ryoritsuB_4'] = config::QUARTER_FIXED_RATE[$cnt][$item];
				$c_listArray['branch_promotion'][$item.':ryoritsuB_6'] = config::QUARTER_FIXED_RATE[$cnt][$item];
				$flag_fixed_rate_type = true;
				break;
			}
			$cnt++;
		}
		//printArray($c_listArray['branch_promotion']);
	} // FEATURE_FIXED_POINT_AND_RATE

	// 達成率を計算
	if ($fiscal_year == 2023 && $quarter == 3 && $item === "LO") {
		// 2023年第3四半期のLOは特別措置により四半期販促費の計画値は以下の数値を適用する
		// 10月、11月の臨時目標値（計画値の90%）＋12月の計画値
		$plan = round($c_listArray['data_list'][$item]['plan']['10'] * 0.9, 0) + round($c_listArray['data_list'][$item]['plan']['11'] * 0.9, 0) + $c_listArray['data_list'][$item]['plan']['12'];
	}
	else {
		$plan   = $c_listArray['data_list'][$item]['plan'][$quarter.'_quarter'];
	}
	$result = $c_listArray['data_list'][$item]['result'][$quarter.'_quarter'];
	$reach  = floor(($result/$plan)*1000) / 10;

	// 料率を判定
	if ($reach >= $c_listArray['branch_promotion'][$item.':ryoritsuB_1']) {
		$rate = $c_listArray['branch_promotion'][$item.':ryoritsuB_2'];
	}
	elseif ($reach >= $c_listArray['branch_promotion'][$item.':ryoritsuB_3']) {
		$rate = $c_listArray['branch_promotion'][$item.':ryoritsuB_4'];
	}
	elseif ($reach >= $c_listArray['branch_promotion'][$item.':ryoritsuB_5']) {
		$rate = $c_listArray['branch_promotion'][$item.':ryoritsuB_6'];
	}
	else {
		$rate = 0;

		if (local_config::FEATURE_FIXED_POINT_AND_RATE) {
			if ($flag_fixed_rate_type) {
				$rate = $c_listArray['branch_promotion'][$item.':ryoritsuB_2'];	//  
			}
		} // FEATURE_FIXED_POINT_AND_RATE
	}

	// 販促費計算(実績は1000円単位なので1000倍してから計算する)
	// 実績は1000円単位で小数点なしで計算する（社長賞シミュレーションのExcelでも小数点はナシの数値で計算する by カナメ山内）
	$prize = round(($result*1000) * ($rate/100));

	// 獲得賞金を加算
	$listArray['prize_list'][$item] += round($prize);		// 種目毎の賞金額を加算
	$listArray['prize_list']['quarter'] += round($prize);	// 四半期の賞金額を加算

	// テーブルを表示
	echo '<tr><td>'.$item.'</td><td class="right">'.formatNumber($plan).'</td>';
	echo '<td class="right">'.formatNumber($result).'</td>';
	echo '<td class="right">'.formatNumber($reach, 1, 'floor').' %</td>';
	echo '<td class="right">'.$rate.' %</td>';
	//echo '<td class="right bg_yellow">'.formatNumber($prize).'</td></tr>';
	echo '<td class="right bg_yellow">'.$prize.'</td></tr>';
}

/**
 * ----------------------------------------------------------
 * printLCPrizeTable()
 * 3.LC保有目標達成賞金に関するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
function printLCPrizeTable($postArray, &$listArray) {

	// 最新のLC保有枚数を取得
	$fiscal_year = $postArray['fiscal_year'];
	$lc_result = getLCHoldNumber($fiscal_year);

	$plan   = $listArray['lc_hold_number']['lc_year_target_count'];	// 期末保有計画
	$result = $lc_result['LC_hold_number'];	// 最新のLC保有枚数
	$month  =  $lc_result['LC_hold_month'];	// 上記保有枚数の獲得月

	// LC保有枚数達成率を計算
	//$result = 7000;	// デバッグ用
	$reach = ($result / $plan) * 100;

	// 報奨金の計算
	if ($reach >= $listArray['year_promotion']['LC:ryoritsuY_1']) {
		$prize = $result * $listArray['year_promotion']['LC:ryoritsuY_2'];
	}
	elseif ($reach >= $listArray['year_promotion']['LC:ryoritsuY_3']) {
		$prize = $result * $listArray['year_promotion']['LC:ryoritsuY_4'];
	}
	else {
		$prize = 0;
	}

	// 獲得賞金を加算
	$listArray['prize_list']['LC'] += round($prize);

	echo '<p class="bold">＜LC保有枚数 ※'.$fiscal_year.'年度 '.$month.'月入力分までを表示中＞</p>';
	echo '<table>';
	echo '<tr class="bg_wet_asphalt">
		  <td>期末保有計画</td><td>期末保有枚数</td><td>達成率</td><td>賞金額(円)</td>
		  </tr>';
	echo '
	<tr>
		<td class="right" width="100px">'.number_format($plan, 0).'</td>
		<td class="right" width="100px">'.number_format($result, 0).'</td>
		<td class="right" width="70px">'.number_format(floor($reach*10)/10, 1).'%</td>
		<td class="right bg_yellow" width="100px">'.number_format(round($prize)).'</td>
	</tr>';

	echo '</table><br />';
}

/**
 * ----------------------------------------------------------
 * printCampaignPrizeTable()
 * 4、5、6のキャンペーン賞金と点数に関するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @param $campaign ：キャンペーン種別
 * ----------------------------------------------------------
 */
function printCampaignPrizeTable($postArray, &$listArray, $campaign) {

	$tableHeader = '<tr class="bg_wet_asphalt"><td width="50px">種目</td><td width="80px">全国1同友<br>あたり計画<br>(千円)</td>
					<td width="80px">支部計画<br>(千円)</td><td width="80px">実績<br>(千円)</td><td width="50px">参加同友数<br>(基準内)</td>
					<td width="80px">生産性<br>得点</td><td width="80px">参加率<br>得点</td><td width="80px">達成率<br>得点<br>(上限150点)</td>
					<td width="50px" colspan="2">合計得点<br>優績は達成率100%<br>以上かつ300点以上</td><td width="80px">同友会得点<br>ボーナス得点</td>
					<td width="50px">料率</td><td width="80px">賞金額<br>(円)</td><td width="80px">特別施策<br>賞金</td>
					</tr>';

	echo '<table>';
	echo $tableHeader;
	printCampaignPrizeItemTable($listArray, $postArray['fiscal_year'], $campaign, 'LM+LS');
	printCampaignPrizeItemTable($listArray, $postArray['fiscal_year'], $campaign, 'LT');
	printCampaignPrizeItemTable($listArray, $postArray['fiscal_year'], $campaign, 'LH');
	printCampaignPrizeItemTable($listArray, $postArray['fiscal_year'], $campaign, 'LO');
	printCampaignPrizeItemTable($listArray, $postArray['fiscal_year'], $campaign, 'LE');
	printCampaignPrizeItemTable($listArray, $postArray['fiscal_year'], $campaign, 'LL');
	echo '</table><br />';
}

/**
 * ----------------------------------------------------------
 * printCampaignPrizeItemTable()
 * 4、5、6のキャンペーン賞金と点数に関する種目ごとのテーブルを表示
 * @param $listArray：データベースの各テーブルのデータ
 * @param $fiscal_year：年度
 * @param $campaign   ：キャンペーン種別
 * @param $item       ：種目
 * ----------------------------------------------------------
 */
function printCampaignPrizeItemTable(&$listArray, $fiscal_year, $campaign, $item) {

	$rate = 0;
	$prize = 0;
	$sp_prize = 0;
	$classRate = 'class="right"';
	$classPrize = 'class="right bg_yellow"';
	$classSPPrize = 'class="right bg_yellow"';

	$c_listArray = $listArray;

	// 特別キャンペーン(達成率に関わらず一律の料率を与える)
	// ※2020年のCOLVIC-19のための対策を2020年サマーキャンペーンにおいて実施
	if (local_config::FEATURE_FIXED_POINT_AND_RATE) {
		$cnt = 0;
		$flag_fixed_rate_type = false;
		foreach (config::CAMPAIGN_FIXED_RATE_TYPE as $value) {
			if ($value == $fiscal_year.'_'.$campaign) {
				// もし年度とキャンペーン種別が一致する組み合わせがあれば達成率に関わらず固定料率を設定する
				$c_listArray['campaign_promotion'][$item.':ryoritsuC_2'] = config::CAMPAIGN_FIXED_RATE[$cnt][$item];
				$c_listArray['campaign_promotion'][$item.':ryoritsuC_4'] = config::CAMPAIGN_FIXED_RATE[$cnt][$item];
				$c_listArray['campaign_promotion'][$item.':ryoritsuC_6'] = config::CAMPAIGN_FIXED_RATE[$cnt][$item];
				$flag_fixed_rate_type = true;
				break;
			}
			$cnt++;
		}
		//printArray($c_listArray['campaign_promotion']);
	} // FEATURE_FIXED_POINT_AND_RATE

	$campaignPoint = getCalcCampeignPoint($fiscal_year, $campaign, $item);
	$point = getCalcCampeignBonusPoint($campaignPoint, $fiscal_year, $campaign);

	// 他で計算するために販売基準同友数をListArrayに保存
	if ($listArray['baseEnterableNum'] == 0) {
		$listArray['baseEnterableNum'] = $campaignPoint['baseEnterableNum'];
	}

	// 達成率・料率計算はLT、LO、LE、LLのみ
	if ($item === 'LT' || $item === 'LO' || $item === 'LE' || $item === 'LL' ) {
		// 達成率を計算
		$plan   = $campaignPoint['campaign_plan'];
		$result = $campaignPoint['campaign_result'];
		$reach  = floor(($result/$plan)*1000) / 10;

		// 料率を判定
		if ($reach >= $c_listArray['campaign_promotion'][$item.':ryoritsuC_1']) {
			$rate = $c_listArray['campaign_promotion'][$item.':ryoritsuC_2'];
		}
		elseif ($reach >= $c_listArray['campaign_promotion'][$item.':ryoritsuC_3']) {
			$rate = $c_listArray['campaign_promotion'][$item.':ryoritsuC_4'];
		}
		elseif ($reach >= $c_listArray['campaign_promotion'][$item.':ryoritsuC_5']) {
			$rate = $c_listArray['campaign_promotion'][$item.':ryoritsuC_6'];
		}
		// 2020年秋キャンペーンから追加 ▼▼▼ ここから
		elseif ($reach >= $c_listArray['campaign_promotion'][$item.':ryoritsuC_7'] && (($fiscal_year == 2020 && ($campaign === "autumn" || $campaign === "spring")) || ($fiscal_year >= 2021)) ) {
			$rate = $c_listArray['campaign_promotion'][$item.':ryoritsuC_8'];
		}
		elseif ($reach >= $c_listArray['campaign_promotion'][$item.':ryoritsuC_9'] && (($fiscal_year == 2020 && ($campaign === "autumn" || $campaign === "spring")) || ($fiscal_year >= 2021)) ) {
			$rate = $c_listArray['campaign_promotion'][$item.':ryoritsuC_10'];
		}
		// 2020年秋キャンペーンから追加 ▲▲▲ ここまで
		else {
			$rate = 0;

			if (local_config::FEATURE_FIXED_POINT_AND_RATE) {
				if ($flag_fixed_rate_type) {
					$rate = $c_listArray['campaign_promotion'][$item.':ryoritsuC_2'];
				}
			} // FEATURE_FIXED_POINT_AND_RATE
		}

		//$rate = $rate.' %';
		// 販促費計算(実績は1000円単位なので1000倍してから計算する)
		// 料率と参加率を加える
		$prize = ($result*1000) * ($rate/100) * round($campaignPoint['enterableNum']/$campaignPoint['ignoreRecessEnterableNum'],3);

	}
	elseif ($item === 'LM+LS') {
		$rate = '----';
		$prize = '----';
		$classPrize = '';
	}
	elseif ($item === 'LH') {
		$rate = '優績支部賞';
		$prize = $listArray['other_prize']['lh_'.$campaign.'_prize'];	// LHボーナス賞金
	}

	// 参加率が100%の場合、賞金額の10%を加算し、小数点以下第一位を四捨五入して整数で表示
	// 参加率が100%以外の場合は小数点以下第一位を四捨五入して整数で表示
	if ($campaignPoint['enterable'] >= config::ENTERABLE_POINT_MAX && $item !== 'LH') {
		$prize = round($prize + ($prize * 0.1), 0);
	}
	else {
		$prize = round($prize, 0);
	}

	// 特別キャンペーン(達成率に関わらず一律の料率を与える)
	// 特別キャンペーンの場合は、参加率などを考慮せず、実績ｘ料率のみで計算する
	if (local_config::FEATURE_FIXED_POINT_AND_RATE && $item !== 'LH') {
		if ($flag_fixed_rate_type) {
			$prize = round(($result*1000) * ($rate/100));
		}
	}

	// 優績判定
	$jadge = '';
	if ($point >= config::$EXECUTIVE_BONUS_POINT[1]['value']) {

		$jadge = '<font color="red">優績</font>';

		if (local_config::FEATURE_FIXED_POINT_AND_RATE) {
			// 特別キャンペーンに該当する場合は、優績判定を行わない
			foreach (config::CAMPAIGN_FIXED_RATE_TYPE as $value) {
				if ($value == $fiscal_year.'_'.$campaign) {
					$jadge = '<font color="gray">--</font>';
					break;
				}
			}
		} // FEATURE_FIXED_POINT_AND_RATE
	}

	// 種目ごとの単位を付与
	if ($item === 'LM+LS') {
		$unit = '台';
	}
	else {
		$unit = '';
	}

	// 特別施策賞金
	if ($item === 'LH') {
		$sp_prize = '----';
		$classSPPrize = '';
	}
	else {
		$sp_prize = $listArray['other_prize'][$item.'_special_prize_'.$campaign];
	}

	// 合計得点・賞金用
	$listArray['point'] += (int)$point;							// 獲得ポイントを加算
	$listArray['prize_list'][$item] += round($prize);			// 種目別の獲得賞金を加算
	$listArray['prize_list'][$item] += round($sp_prize);		// 種目別の特別施策賞金を加算
	$listArray['prize_list']['sp_prize'] += round($sp_prize);	// 特別施策賞金の合計
	if ($item !== 'LH') {
		$listArray['prize_list']['campaign'] += round($prize);	// キャンペーンの賞金
	}else{
		$listArray['prize_list']['lh_prize'] += round($prize);	// LH賞金の合計
	}

	echo '
	<tr>
		<td>'.$item.'</td>
		<td class="right">'.formatNumber($campaignPoint['campaign_plan_ave'],1).' '.$unit.'</td>
		<td class="right">'.formatNumber($campaignPoint['campaign_plan']).' '.$unit.'</td>
		<td class="right">'.formatNumber($campaignPoint['campaign_result']).' '.$unit.'</td>
		<td>'.$campaignPoint['enterableNum'].'</td>
		<td>'.$campaignPoint['product'].'</td>
		<td>'.$campaignPoint['enterable'].'</td>
		<td>'.$campaignPoint['reach'].'</td>
		<td>'.$campaignPoint['total'].'</td>
		<td>'.$jadge.'</td>
		<td>'.$point.'</td>
		<td '.$classRate.'>'.$rate.' %</td>
		<td '.$classPrize.'>'.formatNumber($prize).'</td>
		<td '.$classSPPrize.'>'.formatNumber($sp_prize).'</td>
	</tr>';
}

/**
 * ----------------------------------------------------------
 * printYearPrizeTable()
 * 7.年間賞金と点数に関するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
function printYearPrizeTable($postArray, &$listArray) {

	$tableHeader = '<tr class="bg_wet_asphalt"><td width="50px">種目</td>
					<td width="80px">支部計画<br>(千円)</td><td width="80px">実績<br>(千円)</td><td width="50px">同友会得点<br>(基準内)</td>
					<td width="80px">料率</td><td width="80px">年間賞金<br>(円)</td>
					</tr>';

	echo '<table>';
	echo $tableHeader;
	printYearPrizeItemTable($postArray, $listArray, 'LM+LS');
	printYearPrizeItemTable($postArray, $listArray, 'LT');
	printYearPrizeItemTable($postArray, $listArray, 'LH');
	printYearPrizeItemTable($postArray, $listArray, 'LO');
	printYearPrizeItemTable($postArray, $listArray, 'LE');
	printYearPrizeItemTable($postArray, $listArray, 'LL');
	printYearPrizeItemTable($postArray, $listArray, 'LC');
	echo '</table><br />';
}

/**
 * ----------------------------------------------------------
 * printYearPrizeItemTable()
 * 7.年間賞金と点数に関する種目ごとのテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
function printYearPrizeItemTable($postArray, &$listArray, $item) {

	$classRate = 'class="right"';
	$classPrize = 'class="right bg_yellow"';

	// 同友会得点を計算
	$point = 0;
	if ($item !== 'LC') {
		$plan   = $listArray['data_list'][$item]['plan']['year'];
		$result = $listArray['data_list'][$item]['result']['year'];

		if ($plan > 0) {
			$point = floor(($result/$plan) * 100);
		}
	}
	else {
		// 最新のLC保有枚数を取得
		$fiscal_year = $postArray['fiscal_year'];
		$lc_result = getLCHoldNumber($fiscal_year);

		$plan   = $listArray['lc_hold_number']['lc_year_target_count'];	// 期末保有計画
		$result = $lc_result['LC_hold_number'];	// 最新のLC保有枚数

		if ($plan > 0) {
			$point = floor(($result/$plan) * 50);
		}
	}
	
	// 達成率・料率計算はLT、LO、LE、LLのみ
	if ($item === 'LT' || $item === 'LO' || $item === 'LE' || $item === 'LL' ) {
		// 達成率を計算
		$reach  = floor(($result/$plan)*1000) / 10;

		// 料率を判定
		if ($reach >= $listArray['year_promotion'][$item.':ryoritsuY_1']) {
			$rate = $listArray['year_promotion'][$item.':ryoritsuY_2'];
		}
		elseif ($reach >= $listArray['year_promotion'][$item.':ryoritsuY_3']) {
			$rate = $listArray['year_promotion'][$item.':ryoritsuY_4'];
		}
		elseif ($reach >= $listArray['year_promotion'][$item.':ryoritsuY_5']) {
			$rate = $listArray['year_promotion'][$item.':ryoritsuY_6'];
		}
		else {
			$rate = 0;
		}
		$rate = $rate.' %';
		// 販促費計算(実績は1000円単位なので1000倍してから計算する)
		// 料率と参加率を加える
		$prize = round(($result*1000) * ($rate/100));	
	}
	elseif ($item === 'LM+LS' || $item === 'LH' || $item === 'LC' ) {
		$rate = '----';
		$prize = '----';
		$classPrize = '';
	}

	// 種目ごとの単位を付与
	if ($item === 'LM+LS') {
		$unit = '台';
	}
	else {
		$unit = '';
	}

	// 合計得点・賞金用
	$listArray['point'] += $point;			// 獲得ポイントを加算
	$listArray['prize_list'][$item] += round($prize);	// 種目別の獲得賞金を加算
	$listArray['prize_list']['year'] += round($prize);	// 年間賞金額

	echo '
	<tr>
		<td>'.$item.'</td>
		<td class="right">'.formatNumber($plan).' '.$unit.'</td>
		<td class="right">'.formatNumber($result).' '.$unit.'</td>
		<td>'.$point.'</td>
		<td '.$classRate.'>'.$rate.'</td>
		<td '.$classPrize.'>'.formatNumber($prize).'</td>
	</tr>';
}

/**
 * ----------------------------------------------------------
 * printProductPrizeTable()
 * 8.生産性・ボリューム報奨金に関するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
function printProductPrizeTable($postArray, &$listArray) {

	// 2021年以降は賞金額ではなく、順位を入力するように仕様変更
	if ($postArray['fiscal_year'] >= 2021) {
		// 0で初期化(支部数が増えても大丈夫なように要素数を70にしておく)
		$seisan_prize = array_fill(0,70,0);
		$volume_prize = array_fill(0,70,0);
		// 順位からの賞金変換テーブル
		//$seisan_prize = array(0, 5000,4000,3000,1500,1500,1500,1500,1500,1500,1500);
		//$volume_prize = array(0, 150000,100000,75000,40000,40000,40000,40000,40000,40000,40000);
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
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['lms_seisan_prize_1']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['lt_seisan_prize_1']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['lh_seisan_prize_1']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['lo_seisan_prize_1']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['le_seisan_prize_1']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['ll_seisan_prize_1']).'</td>
	</tr>';	

	echo '
	<tr>
	<td style="width:150px;" class="left">生産性報奨金（下半期）</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['lms_seisan_prize_2']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['lt_seisan_prize_2']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['lh_seisan_prize_2']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['lo_seisan_prize_2']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['le_seisan_prize_2']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['ll_seisan_prize_2']).'</td>
	</tr>';	

	echo '
	<tr>
	<td style="width:150px;" class="left">ボリューム報奨金（上半期）</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['lms_volume_prize_1']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['lt_volume_prize_1']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['lh_volume_prize_1']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['lo_volume_prize_1']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['le_volume_prize_1']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['ll_volume_prize_1']).'</td>
	</tr>';	

	echo '
	<tr>
	<td style="width:150px;" class="left">ボリューム報奨金（下半期）</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['lms_volume_prize_2']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['lt_volume_prize_2']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['lh_volume_prize_2']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['lo_volume_prize_2']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['le_volume_prize_2']).'</td>
	<td style="width:80px;" class="right bg_yellow">'.number_format($listArray['other_prize']['ll_volume_prize_2']).'</td>
	</tr>';	

	echo '</table><br />';
}

/**
 * ----------------------------------------------------------
 * printWorkingPrizeTable()
 * 9.年間全稼働報奨金に関するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
function printWorkingPrizeTable($postArray, &$listArray) {

	echo '<table id="working_prize">';
	echo '<tr>
	<td class="bg_wet_asphalt" style="width:150px;">項目</td>
	<td class="bg_wet_asphalt" style="width:80px;">LM+LS</td><td class="bg_wet_asphalt" style="width:80px;">LT</td>
	<td class="bg_wet_asphalt" style="width:80px;">LH自動車</td><td class="bg_wet_asphalt" style="width:80px;">LO</td>
	<td class="bg_wet_asphalt" style="width:80px;">LE</td><td class="bg_wet_asphalt" style="width:80px;">LL</td>
	</tr>';

	echo '
	<tr>
	<td style="width:150px;" class="left">年間全稼働報奨金</td>
	<td style="width:80px;" class="right bg_yellow">円</td>
	<td style="width:80px;" class="right bg_yellow">円</td>
	<td style="width:80px;" class="right bg_yellow">円</td>
	<td style="width:80px;" class="right bg_yellow">円</td>
	<td style="width:80px;" class="right bg_yellow">円</td>
	<td style="width:80px;" class="right bg_yellow">円</td>
	</tr>';	
	echo '</table><br />';
}

/**
 * ----------------------------------------------------------
 * printLHPrizeTable()
 * 10.LH自動車特別賞に関するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
function printLHPrizeTable($postArray, &$listArray) {

	// 獲得賞金
	$prize = $listArray['other_prize']['lh_doyu_kaisyo_prize'];
	// 獲得賞金を加算
	$listArray['prize_list']['LH'] += round($prize);
	$listArray['prize_list']['lh_prize'] += round($prize);

	echo '<table>';
	echo '<tr class="bg_wet_asphalt"><td width="180px">項目</td><td width="80px">賞金額 (円)</td></tr>';
	echo '
	<tr>
		<td class="left">1,000万円未満同友解消支部賞</td>
		<td class="right bg_yellow">'.formatNumber($prize).'</td>
	</tr>';
	echo '</table><br />';
}

/**
 * ----------------------------------------------------------
 * printWorkingPrizeTable()
 * 11.全同友稼働に関するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
function printWorkingTable($postArray, &$listArray) {

	$executiveData = array();
	$item_list = $listArray['item_list'];
	$e_count = 0;
	$e_item_overzero_count = array();
	$e_item_min_count = array();

	// 種目からLC、LC:spitemを削除
	$i = 0;
	foreach ($item_list as $itemArray) {
		if ($itemArray['value'] === 'LC' || $itemArray['value'] === 'LC:spitem') {
			unset($item_list[$i]);
		}
		$i++;
	}
	//printArray($item_list);

	// 同友最低販売基準を取得
	$min_target = getExecutiveMinTargetInfo($postArray['fiscal_year']);
	//printArray($min_target);

	//-------------------------------
	// 同友毎の販売数を取得
	foreach ($listArray['executive_list'] as $executiveArray) {
		// キャンペーン参加同友のみ実績を取得
		if($executiveArray['summer_enterable'] != config::STATUS_ENTERABLE || 
		   $executiveArray['autumn_enterable'] != config::STATUS_ENTERABLE || 
		   $executiveArray['spring_enterable'] != config::STATUS_ENTERABLE ) {
			continue;
		}

		// 初期化
		$resultTotalList = array();
		$data = array();

		// 種目ごとの合計値を取得
		foreach ($item_list as $itemArray) {
			$data = getExecutiveResultTotalValue($postArray['fiscal_year'], $itemArray['value'], '%', 'TOTAL', $executiveArray['value']);
			//$data = getResultAndPlanByExecutive($postArray['fiscal_year'], $itemArray['value'], 'TOTAL', $executiveArray['value']);
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

	// 種目からLM、LSを削除
	$i = 1;
	foreach ($item_list as $itemArray) {
		if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
			unset($item_list[$i]);
		}
		$i++;
	}

	//-------------------------------
	// 全同友稼働数、最低販売基準同友数のカウント
	foreach ($listArray['executive_list'] as $executiveArray) {
		// キャンペーン参加同友のみ実績を取得
		if($executiveArray['summer_enterable'] != config::STATUS_ENTERABLE || 
		   $executiveArray['autumn_enterable'] != config::STATUS_ENTERABLE || 
		   $executiveArray['spring_enterable'] != config::STATUS_ENTERABLE ) {
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
	if ($postArray['fiscal_year'] < 2019) {

	echo '
	<table>
		<tr class="bg_wet_asphalt"><td width="50px">種目</td><td width="80px">稼働<br>同友数</td><td width="80px">同友会<br>得点</td><td width="80px">最低販売<br>基準達成<br>同友数</td><td width="80px">同友会<br>得点</td></tr>';
	
	$workingPrizeArray = array();
	foreach ($item_list  as $itemArray) {
		// 稼働同友数
		echo '<tr><td class="left">'.$itemArray['value'].'</td>';
		echo '<td>'.$e_item_overzero_count[$itemArray['value']].' / '.$e_count.'</td>';	// 稼働同友数は最低販売基準を達成している同友数で計算する
		$point = floor(($e_item_overzero_count[$itemArray['value']] / $e_count)*10);	// 年間全稼働褒賞金は販売基準対象の全同友が最低販売基準を達成した時に発生
		echo '<td>'.$point.'</td>';
		$listArray['point'] += $point;													// 法人事業合計に加算

		// 最低販売基準達成同有数
		echo '<td>'.$e_item_min_count[$itemArray['value']].'</td>';
		$point = floor(($e_item_min_count[$itemArray['value']] / $e_count)*20);		// 最低販売基準達成同友数の同友会得点計算
		echo '<td>'.$point.'</td></tr>';
		$listArray['point'] += $point;												// 法人事業合計に加算
	
		// 種目ごとの得点を保存
		if ($point == 20) {
			// 年間全稼働報奨金を計算
			$workingPrizeArray[$itemArray['value']] = $listArray['other_prize']['min_target_clear_prize'] * $e_count;
		}
		else {
			$workingPrizeArray[$itemArray['value']] = 0;
		}

		// 獲得賞金を加算
		$listArray['prize_list'][$itemArray['value']] += round($workingPrizeArray[$itemArray['value']]); 	// 種目別の獲得賞金を加算
		$listArray['prize_list']['working'] += round($workingPrizeArray[$itemArray['value']]); 				// 年間全稼働報奨金の合計
	}
	echo '</table>';
	} // if fiscal_year < 2019
	else {

		echo '<table><tr class="bg_wet_asphalt"><td width="50px">種目</td><td width="80px">最低販売<br>基準達成<br>同友数</td><td width="80px">支部<br>販売基準<br>同友数</td><td width="80px">同友会<br>得点</td></tr>';
		
		$workingPrizeArray = array();
		foreach ($item_list  as $itemArray) {

			echo '<tr><td class="left">'.$itemArray['value'].'</td>';		// 種目
			echo '<td>'.$e_item_min_count[$itemArray['value']].'</td>';		// 最低販売基準達成同友数
			echo '<td>'.$e_count.'</td>';									// 支部販売基準同友数
			$point = floor(($e_item_min_count[$itemArray['value']] / $e_count)*30);		// 最低販売基準達成同友数の同友会得点計算
			echo '<td>'.$point.'</td></tr>';
			$listArray['point'] += $point;												// 法人事業合計に加算
		
			// 種目ごとの得点を保存
			if ($point == 30) {
				// 年間全稼働報奨金を計算
				$workingPrizeArray[$itemArray['value']] = $listArray['other_prize']['min_target_clear_prize'] * $e_count;
			}
			else {
				$workingPrizeArray[$itemArray['value']] = 0;
			}
	
			// 獲得賞金を加算
			$listArray['prize_list'][$itemArray['value']] += round($workingPrizeArray[$itemArray['value']]); 	// 種目別の獲得賞金を加算
			$listArray['prize_list']['working'] += round($workingPrizeArray[$itemArray['value']]); 				// 年間全稼働報奨金の合計
		}
		echo '</table>';
	} // else fiscal_year < 2019
	?>

	<script>
		// 最低販売基準達成同有数の得点が20点の場合、年間全稼働報奨金を計算してテーブルに挿入する
		var working_prize_table = document.getElementById('working_prize');
		var point = [];

		point[0] = <?php echo $workingPrizeArray['LM+LS']; ?>;
		point[1] = <?php echo $workingPrizeArray['LT']; ?>;
		point[2] = <?php echo $workingPrizeArray['LH']; ?>;
		point[3] = <?php echo $workingPrizeArray['LO']; ?>;
		point[4] = <?php echo $workingPrizeArray['LE']; ?>;
		point[5] = <?php echo $workingPrizeArray['LL']; ?>;

		for (i = 0; i < 6; i++) {
			working_prize_table.rows[1].cells[i+1].innerText = point[i].toLocaleString();
		}
	</script>

<?php
}

/**
 * ----------------------------------------------------------
 * printLotasMutualAidPointTable()
 * 13.ロータス共済制度に関するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
function printLotasMutualAidPointTable($postArray, &$listArray) {

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

	// 獲得賞金
	if ($postArray['fiscal_year'] >= 2021) {
		// 生命共済を計算（達成率が100以上なら30点、それ以外は0.3をかけて切り捨て）
		// 小数点第1位までで計算したいので、1000倍して計算
		$ratio = floor(($listArray['point_status'][$start_index_num+1] / $listArray['point_status'][$start_index_num]) * 1000);
		if ($ratio > 1000) {
			$point = 30;
		}
		else {
			$point = floor($ratio * 0.03);
		}
	}
	else {
		$point = $listArray['point_status'][18];
	}



	// 獲得賞金を加算
	$listArray['point'] += round($point);	

	echo '<table>';
	echo '<tr class="bg_wet_asphalt"><td width="180px">項目</td><td width="80px">同友会得点</td></tr>';
	echo '
	<tr>
		<td class="left">ロータス共済制度</td>
		<td>'.formatNumber($point).'</td>
	</tr>';
	echo '</table><br />';
}

/**
 * ----------------------------------------------------------
 * printLotasCompensationPointTable()
 * 14.ロータス団体総合補償制度に関するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
function printLotasCompensationPointTable($postArray, &$listArray) {

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

	// 獲得賞金
	if ($postArray['fiscal_year'] >= 2021) {
		// 団体総合補償保険（達成率が100以上なら30点、それ以外は0.3をかけて切り捨て）
		// 小数点第1位までで計算したいので、1000倍して計算
		$ratio = floor(($listArray['point_status'][$start_index_num+3] / $listArray['point_status'][$start_index_num+2]) * 1000);
		if ($ratio > 1000) {
			$point = 30;
		}
		else {
			$point = floor($ratio * 0.03);
		}
	}
	else {
		$point = $listArray['point_status'][19];
	}

	// 獲得賞金を加算
	$listArray['point'] += round($point);	

	echo '<table>';
	echo '<tr class="bg_wet_asphalt"><td width="180px">項目</td><td width="80px">同友会得点</td></tr>';
	echo '
	<tr>
		<td class="left">ロータス団体総合補償制度</td>
		<td>'.formatNumber($point).'</td>
	</tr>';
	echo '</table><br />';
}

/**
 * ----------------------------------------------------------
 * printLotasAllotmentPointTable()
 * 15.(株)ロータスへの配当に関するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
function printLotasAllotmentPointTable($postArray, &$listArray) {

	// 獲得賞金
	if ($postArray['fiscal_year'] >= 2025) {
		$point = $listArray['point_status'][18];
	}
	else if ($postArray['fiscal_year'] >= 2023) {
		$point = $listArray['point_status'][24];
	}
	else if ($postArray['fiscal_year'] >= 2022) {
		$point = $listArray['point_status'][23];
	}
	else if ($postArray['fiscal_year'] >= 2021) {
		$point = $listArray['point_status'][22];
	}
	else {
		$point = $listArray['point_status'][20];
	}

	// 獲得賞金を加算
	$listArray['point'] += round($point);	

	echo '<table>';
	echo '<tr class="bg_wet_asphalt"><td width="180px">項目</td><td width="80px">同友会得点</td></tr>';
	echo '
	<tr>
		<td class="left">(株)ロータスへの配当</td>
		<td>'.formatNumber($point).'</td>
	</tr>';
	echo '</table><br />';
}

/**
 * ----------------------------------------------------------
 * printLotopiaPointTable()
 * 16.ロートピア商品に関するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
function printLotopiaPointTable($postArray, &$listArray) {

	// 獲得賞金
	if ($postArray['fiscal_year'] >= 2025) {
		$point_summer = $listArray['point_status'][19];
		$point_autumn = $listArray['point_status'][20];
		$point_spring = $listArray['point_status'][21];
		$point_year   = $listArray['point_status'][22];
	}
	else if ($postArray['fiscal_year'] >= 2023) {
		$point_summer = $listArray['point_status'][25];
		$point_autumn = $listArray['point_status'][26];
		$point_spring = $listArray['point_status'][27];
		$point_year   = $listArray['point_status'][28];
	}
	else if ($postArray['fiscal_year'] >= 2022) {
		$point_summer = $listArray['point_status'][24];
		$point_autumn = $listArray['point_status'][25];
		$point_spring = $listArray['point_status'][26];
		$point_year   = $listArray['point_status'][27];
	}
	else if ($postArray['fiscal_year'] >= 2021) {
		$point_summer = $listArray['point_status'][23];
		$point_autumn = $listArray['point_status'][24];
		$point_spring = $listArray['point_status'][25];
		$point_year   = $listArray['point_status'][26];
	}
	else {
		$point_summer = $listArray['point_status'][21];
		$point_autumn = $listArray['point_status'][22];
		$point_spring = $listArray['point_status'][23];
		$point_year   = $listArray['point_status'][24];
	}

	// 獲得賞金を加算
	$listArray['point'] += round($point_summer);
	$listArray['point'] += round($point_autumn);
	$listArray['point'] += round($point_spring);
	$listArray['point'] += round($point_year);

	echo '<table>';
	echo '<tr class="bg_wet_asphalt"><td width="180px">項目</td><td width="80px">同友会得点</td></tr>';
	echo '
	<tr><td class="left">サマーオートリースキャンペーン</td><td>'.$point_summer.'</td></tr>
	<tr><td class="left">秋のオートリースキャンペーン</td><td>'.$point_autumn.'</td></tr>
	<tr><td class="left">春のオートリースキャンペーン</td><td>'.$point_spring.'</td></tr>
	<tr><td class="left">年間支部目標達成</td><td>'.$point_year.'</td></tr>';
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
// 社長賞状況 表示画面
//---------------------------------------------
if ($page === 'view') { ?>

<div id="contents_select">

<form style="display:inline" id='result_view' method="POST" action="index.php?reg=president_prize&type=view">
	<?php printSelectBox($listArray['fiscal_year_list'], 'fiscal_year', 70, $postArray['fiscal_year'], 'changeSelectBoxContents()') ?>年度
	<?php printSubmitButton('表示', 'show') ?>
</form>
</div>
<hr>

<div id="contents">
	<?php
	if (isset($postArray['show'])) {
		echo '<p>'.$postArray['fiscal_year'].'年度の社長賞の状況を表示しています。</p><br />';
	}
	else {
		echo '<p>社長賞の状況を表示する年度を選択して「表示」ボタンを押してください。</p><br />';
		exit;
	} ?>

	<table id='total_table'>
		<tr class="bg_alizarin"><td width="100px">法人事業合計点</td><td width="100px">総合計 (円)</td></tr>
		<tr><td>0</td><td>0</td></tr>
	</table>
	<br />
	<table id='total_table_item'>
		<tr class="bg_wet_asphalt"><td width="240px" colspan="2">種目別内訳 (円)</td><td width="240px" colspan="2">タイトル別内訳 (円)</td></tr>
		<tr><td class="left">LM</td><td class="right" width="80px">0</td>
		    <td class="left">LM・LS・ボーナス</td><td class="right" width="80px">0</td></tr>
		<tr><td class="left">LS</td><td class="right">0</td>
		    <td class="left">四半期販促費</td><td class="right">0</td></tr>
		<tr><td class="left">LM+LS</td><td class="right">0</td>
		    <td class="left">LC保有目標達成賞金</td><td class="right">0</td></tr>
		<tr><td class="left">LT</td><td class="right">0</td>
		    <td class="left">キャンペーン賞金</td><td class="right">0</td></tr>
		<tr><td class="left">LH自動車</td><td class="right">0</td>
		    <td class="left">年間賞金</td><td class="right">0</td></tr>
		<tr><td class="left">LO</td><td class="right">0</td>
		    <td class="left">LOボーナス</td><td class="right">0</td></tr>
		<tr><td class="left">LE</td><td class="right">0</td>
		    <td class="left">生産性・ボリューム報奨金</td><td class="right">0</td></tr>
		<tr><td class="left">LL</td><td class="right">0</td>
		    <td class="left">年間全稼働褒賞金</td><td class="right">0</td></tr>
		<tr><td class="left">LC</td><td class="right">0</td>
		    <td class="left">LH特別賞</td><td class="right">0</td></tr>
		<tr><td class="left">社長賞支部部門</td><td class="right">0</td>
		    <td class="left">特別施策</td><td class="right">0</td></tr>
	</table>
	<br />
	<br />

	<?php $cnt=1; ?>
	<hr><p class="bold">■ <?php echo $cnt++; ?>.LM・LS販促費・ボーナス賞金</p><hr>
	<?php printLMLSBonusPrizeTable($postArray, $listArray); ?>

	<hr><p class="bold">■ <?php echo $cnt++; ?>.四半期販促費</p><hr>
	<?php printQuarterPrizeTable($postArray, $listArray); ?>

	<hr><p class="bold">■ <?php echo $cnt++; ?>.LC保有目標達成賞金</p><hr>
	<?php printLCPrizeTable($postArray, $listArray); ?>

	<hr><p class="bold">■ <?php echo $cnt++; ?>.サマーキャンペーン</p><hr>
	<?php printCampaignPrizeTable($postArray, $listArray, 'summer'); ?>

	<hr><p class="bold">■ <?php echo $cnt++; ?>.秋のキャンペーン</p><hr>
	<?php printCampaignPrizeTable($postArray, $listArray, 'autumn'); ?>

	<hr><p class="bold">■ <?php echo $cnt++; ?>.春のキャンペーン</p><hr>
	<?php printCampaignPrizeTable($postArray, $listArray, 'spring'); ?>

	<hr><p class="bold">■ <?php echo $cnt++; ?>.年間</p><hr>
	<?php printYearPrizeTable($postArray, $listArray); ?>

	<hr><p class="bold">■ <?php echo $cnt++; ?>.生産性・ボリューム報奨金</p><hr>
	<?php printProductPrizeTable($postArray, $listArray); ?>

	<hr><p class="bold">■ <?php echo $cnt++; ?>.年間全稼働報奨金</p><hr>
	<?php printWorkingPrizeTable($postArray, $listArray); ?>

	<hr><p class="bold">■ <?php echo $cnt++; ?>.LH自動車特別賞</p><hr>
	<?php printLHPrizeTable($postArray, $listArray); ?>

	<?php if ($postArray['fiscal_year'] < 2019) { ?>
		<hr><p class="bold">■ <?php echo $cnt++; ?>.全同友稼働  12.最低販売基準達成</p><hr>
	<?php } else { ?>
		<hr><p class="bold">■ <?php echo $cnt++; ?>.全同友最低販売基準達成</p><hr>
	<?php } ?>
	<?php printWorkingTable($postArray, $listArray); ?>

	<hr><p class="bold">■ <?php echo $cnt++; ?>.ロータス共済制度</p><hr>
	<?php printLotasMutualAidPointTable($postArray, $listArray); ?>

	<hr><p class="bold">■ <?php echo $cnt++; ?>.ロータス団体総合補償制度</p><hr>
	<?php printLotasCompensationPointTable($postArray, $listArray); ?>

	<hr><p class="bold">■ <?php echo $cnt++; ?>.(株)ロータスへの配当</p><hr>
	<?php printLotasAllotmentPointTable($postArray, $listArray); ?>

	<hr><p class="bold">■ <?php echo $cnt++; ?>.ロートピア商品</p><hr>
	<?php printLotopiaPointTable($postArray, $listArray); ?>

</div>

<script type="text/javascript">

	var totalprize = 0;

	// 種目別、タイトル別の内訳を表示
	var table = document.getElementById('total_table_item');

	var prize = <?php echo $listArray['prize_list']['LM'];?>;	 // LMの賞金額
	totalprize += prize;
	table.rows[1].cells[1].innerText = prize.toLocaleString();
	var prize = <?php echo $listArray['prize_list']['LS'];?>;	 // LSの賞金額
	totalprize += prize;
	table.rows[2].cells[1].innerText = prize.toLocaleString();
	var prize = <?php echo $listArray['prize_list']['LM+LS'];?>; // LM+LSの賞金額
	totalprize += prize;
	table.rows[3].cells[1].innerText = prize.toLocaleString();
	var prize = <?php echo $listArray['prize_list']['LT'];?>;	 // LTの賞金額
	totalprize += prize;
	table.rows[4].cells[1].innerText = prize.toLocaleString();
	var prize = <?php echo $listArray['prize_list']['LH'];?>;	 // LHの賞金額
	totalprize += prize;
	table.rows[5].cells[1].innerText = prize.toLocaleString();
	var prize = <?php echo $listArray['prize_list']['LO'] + $listArray['other_prize']['lo_year_prize'];?>;	 // LOの賞金額
	totalprize += prize;
	table.rows[6].cells[1].innerText = prize.toLocaleString();
	var prize = <?php echo $listArray['prize_list']['LE'];?>;	 // LEの賞金額
	totalprize += prize;
	table.rows[7].cells[1].innerText = prize.toLocaleString();
	var prize = <?php echo $listArray['prize_list']['LL'];?>;	 // LLの賞金額
	totalprize += prize;
	table.rows[8].cells[1].innerText = prize.toLocaleString();
	var prize = <?php echo $listArray['prize_list']['LC'];?>;	 // LCの賞金額
	totalprize += prize;
	table.rows[9].cells[1].innerText = prize.toLocaleString();
	var prize = <?php echo $listArray['other_prize']['president_prize'];?>;	 // 社長賞支部部門の賞金額
	totalprize += prize;
	table.rows[10].cells[1].innerText = prize.toLocaleString();

	// LM・LS・ボーナス
	var prize1 = <?php echo $listArray['prize_list']['LM'];?>;	 // LMの賞金額
	var prize2 = <?php echo $listArray['prize_list']['LS'];?>;	 // LSの賞金額
	table.rows[1].cells[3].innerText = (prize1+prize2).toLocaleString();

	// 四半期販促費
	var prize = <?php echo $listArray['prize_list']['quarter'];?>;
	table.rows[2].cells[3].innerText = prize.toLocaleString();

	// LC保有目標達成賞金
	var prize = <?php echo $listArray['prize_list']['LC'];?>;
	table.rows[3].cells[3].innerText = prize.toLocaleString();

	// キャンペーン賞金
	var prize = <?php echo $listArray['prize_list']['campaign'];?>;
	table.rows[4].cells[3].innerText = prize.toLocaleString();

	// 年間賞金
	var prize = <?php echo $listArray['prize_list']['year'];?>;
	table.rows[5].cells[3].innerText = prize.toLocaleString();

	// LOボーナス
	var prize = <?php echo $listArray['other_prize']['lo_year_prize'];?>;
	table.rows[6].cells[3].innerText = prize.toLocaleString();

	// 生産性・ボリューム褒賞金
	var prize = <?php echo $listArray['prize_list']['seisan_volume'];?>;
	table.rows[7].cells[3].innerText = prize.toLocaleString();

	// 年間全可動褒賞金
	var prize = <?php echo $listArray['prize_list']['working'];?>;
	table.rows[8].cells[3].innerText = prize.toLocaleString();

	// LH特別賞
	var prize = <?php echo $listArray['prize_list']['lh_prize'];?>;
	table.rows[9].cells[3].innerText = prize.toLocaleString();

	// 特別施策
	var prize = <?php echo $listArray['prize_list']['sp_prize'];?>;
	table.rows[10].cells[3].innerText = prize.toLocaleString();

	// 合計点、総合計を表示
	var table = document.getElementById('total_table');
	var point = <?php echo $listArray['point'];?>;
	table.rows[1].cells[0].innerText = point.toLocaleString();
	table.rows[1].cells[1].innerText = totalprize.toLocaleString();
</script>

<?php }
//---------------------------------------------
// 社長賞状況 表示画面
//---------------------------------------------
elseif ($page === 'setting') { ?>

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