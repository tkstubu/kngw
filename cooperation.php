<?php
/**
 * =================================================================
 * cooperation.php
 * 協力会社 PHPスクリプト
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
	'area_list'        	=> array(),
	'data_list'        	=> array(),
	'setting' 			=> array(),
);

//--------------------------------
// 表示ページを選択
//--------------------------------
$page = selectPageForCooperation($getArray, $postArray, $listArray);

/**
 * ----------------------------------------------------------
 * selectPageForCooperation()
 * 協力会社の状況表示と設定で表示するページを選択する
 * @param $getArray：GETで送られてきたパラメータ
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function selectPageForCooperation($getArray, &$postArray, &$listArray) {

	$page = '';

	// 選択中のメニューに応じた結果を表示
	switch ($getArray['type']) {
		case  'view':
            $page = makeCooperationViewPage($postArray, $listArray);
            break;
        case  'input':
            $page = makeCooperationInputPage($postArray, $listArray);
            break;
        case  'setting':
            $page = makeCooperationSettingPage($postArray, $listArray);
            break;
		default:
			break;
	}
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makeCooperationViewPage()
 * 協力企業の実績入力ページを表示する
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makeCooperationViewPage(&$postArray, &$listArray) {

    $page = 'view';

	// 年度一覧を取得
	$listArray['fiscal_year_list'] = getFiscalYearList();
	if (count($listArray['fiscal_year_list']) == 0) {
		return 'no_data';
	}

	// 提携企業一覧を取得
	$listArray['partner_list'] = getPartnerList(0,  'cooperation');
	if (count($listArray['partner_list']) == 0) {
		return 'no_partner';
	}

	// 協力企業がログインしている場合は、その企業以外はすべてリストから削除する
	$userInfo = getUserInfo($_SESSION['USERID']);
	if ($userInfo['auth'] == 6) {
		for ($i = 0; $i < count($listArray['partner_list']); $i++) {
			if ($listArray['partner_list'][$i]['value'] != $userInfo['id']) {
				unset($listArray['partner_list'][$i]);
			}
		}
	}

	// 表示ボタンを押された場合は、データを読み込み
	if (isset($postArray['command'])) {
		// 地区一覧を取得
		$listArray['area_list'] = getAreaList();
		
		// エリア一覧で支部を先頭に移動させる
		$cnt = count($listArray['area_list']);
		$sort_array[] = $cnt - 1;
		for ($i = 0; $i < count($listArray['area_list'])-1; $i++) {
			$sort_array[] = $i;
		} 
		$sort = array_fill_keys($sort_array, null);
		$listArray['area_list'] = array_replace($sort, $listArray['area_list']);

		// 全体の合計値取得、地域は%を指定することで全地域を指定
		$listArray['data_list']['ALL'] = getAreaTotalValue($postArray['partner'], 'cooperation', '%', $postArray['fiscal_year']);

		// 地区毎の同友の実績値を取得
		foreach ($listArray['area_list'] as $areaListArray) {
			// // 地区毎に集計
			// $listArray['data_list'][$areaListArray['value']]
			// 	= getAreaTotalValue($postArray['partner'], 'cooperation', $areaListArray['value'], $postArray['fiscal_year']);

			$listArray['data_list']['executive'][$areaListArray['value']]
				= getExecutiveResultValue($postArray['partner'], $areaListArray['value'], $postArray['fiscal_year'], 'cooperation');
		}

		// 設定値を読み込み
		$listArray['setting'][ $postArray['partner']] = getCooperationInfo($postArray['fiscal_year'], $postArray['partner']);
		
		// デバッグ
		//printArray($listArray['setting']); 
		//printArray($listArray['data_list']);
	}

    return $page;
}

/**
 * ----------------------------------------------------------
 * makeCooperationInputPage()
 * 協力企業の状況を表示する
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makeCooperationInputPage(&$postArray, &$listArray) {

    $page = 'input';

	// 年度一覧を取得
	$listArray['fiscal_year_list'] = getFiscalYearList();
	if (count($listArray['fiscal_year_list']) == 0) {
		return 'no_data';
    }
    
	// 今月を取得
	if (!isset($postArray['month'])) {
		$postArray['month'] = getCurrentMonth();
	}

	// 提携企業一覧を取得
	$listArray['partner_list'] = getPartnerList(0,  'cooperation');
	if (count($listArray['partner_list']) == 0) {
		return 'no_partner';
	}
	// 協力企業がログインしている場合は、その企業以外はすべてリストから削除する
	$userInfo = getUserInfo($_SESSION['USERID']);
	if ($userInfo['auth'] == 6) {
		for ($i = 0; $i < count($listArray['partner_list']); $i++) {
			if ($listArray['partner_list'][$i]['value'] != $userInfo['id']) {
				unset($listArray['partner_list'][$i]);
			}
		}
	}

	//---------------------------------------------------
	// 保存ボタン押下時：データベースに入力値を保存
	//---------------------------------------------------
	if (isset($postArray['command']) && $postArray['command'] === 'save') {
		if (writeExecutiveResult($postArray) != 'success'){
			return 'db_write_fail';
		}
	}

	// 入力画面表示ボタンを押された場合は、データを読み込み
	if (isset($postArray['command'])) {
		// 地区一覧を取得
		$listArray['area_list'] = getAreaList();

		// エリア一覧で支部を先頭に移動させる
		$cnt = count($listArray['area_list']);
		$sort_array[] = $cnt - 1;
		for ($i = 0; $i < count($listArray['area_list'])-1; $i++) {
			$sort_array[] = $i;
		} 
		$sort = array_fill_keys($sort_array, null);
		$listArray['area_list'] = array_replace($sort, $listArray['area_list']);

		// 地区毎の同友の実績値を取得
		foreach ($listArray['area_list'] as $areaListArray) {
			$listArray['data_list']['executive'][$areaListArray['value']]
					= getExecutiveResultValue($postArray['partner'], $areaListArray['value'], $postArray['fiscal_year'], 'cooperation');
		}

		// 設定値を読み込み
		$listArray['setting'][ $postArray['partner']] = getCooperationInfo($postArray['fiscal_year'], $postArray['partner']);
	}

    return $page;
}

/**
 * ----------------------------------------------------------
 * makeCooperationSettingPage()
 * 協力企業の設定画面を表示する
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makeCooperationSettingPage(&$postArray, &$listArray) {

    $page = 'setting';

	// 年度一覧を取得
	$listArray['fiscal_year_list'] = getFiscalYearList();
	if (count($listArray['fiscal_year_list']) == 0) {
		return 'no_data';
    }
    
    // 提携企業一覧を取得
	$listArray['partner_list'] = getPartnerList(0,  'cooperation');
	if (count($listArray['partner_list']) == 0) {
		return 'no_partner';
	}
	// 協力企業がログインしている場合は、その企業以外はすべてリストから削除する
	$userInfo = getUserInfo($_SESSION['USERID']);
	if ($userInfo['auth'] == 6) {
		for ($i = 0; $i < count($listArray['partner_list']); $i++) {
			if ($listArray['partner_list'][$i]['value'] != $userInfo['id']) {
				unset($listArray['partner_list'][$i]);
			}
		}
	}

	// 保存ボタンが押された時
	if (isset($postArray['save'])) {
		// 設定値を保存する
		writeCooperationInfo($postArray);
	}
	else {
		// 設定値を読み込む
		$listArray['setting'][ $postArray['partner']] = getCooperationInfo($postArray['fiscal_year'], $postArray['partner']);
		//printArray($listArray['setting']); // デバッグ
	}

    return $page;
}

/**
 * ----------------------------------------------------------
 * printRawValueTable()
 * 協力会社の実績を表すテーブルを表示(★直接入力の場合)
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @param $tarm：期間
 * @return
 * ----------------------------------------------------------
 */
function printRawValueTable($postArray, &$listArray, $term) {

	$planTotal = array();
	$resultTotal = array();
	$shibuTotal = array();

	$columnWidth = 150;

	if ($term === 'monthly') {
		$columnItem = 25;
	}
	elseif ($term === 'half_period') {
		$columnItem = 15;
	}
	elseif ($term === 'fourth_period') {
		$columnItem = 9;
	}

	// 単位を取得
	$r_unit = $listArray['setting'][ $postArray['partner']]['r_unit'];
	$p_unit = $listArray['setting'][ $postArray['partner']]['p_unit'];
	$sp_unit = $listArray['setting'][ $postArray['partner']]['sp_unit'];

	//========================================
	// 同友毎の実績テーブルを表示
	//========================================
	// 月別を表示
	if ($term === 'monthly')  {
		foreach ($listArray['area_list'] as $areaListArray) {

			// 単位を取得
			if ($areaListArray['value'] === local_config::SHIBU_AREA_NAME) {
				$bg_class = "bg_wet_asphalt";
				$datalist = $listArray['data_list']['ALL'];
			}
			else {
				$bg_class = "bg_turquoise";
				$datalist = $listArray['data_list']['executive'][$areaListArray['value']];
			}

			echo '<table>';
			echo '<tr><td class="'.$bg_class.'" colspan="19">'.$areaListArray['value'].'の実績【単位 同友取引実績：'.$r_unit.'、同友手数料：'.$p_unit.'、支部手数料：'.$sp_unit.'】</td></tr>';
			
			//-------------------------------
			// 1列目（4月～9月）
			//-------------------------------
			echo '<tr><td class="bg_wet_asphalt" style="width:60px;" rowspan="2">項目</td>';
			for ($i = 4; $i <= 9; $i++) {
				echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="3">'.$i.'月</td>';
			}
			echo '</tr>';

			// 実績と金額の欄を表示
			echo '<tr>';
			for ($i = 1; $i <= 6; $i++) {
				echo '<td class="bg_yellow">同友<br>取引実績</td><td class="bg_red">同友<br>手数料</td><td class="bg_green">支部<br>手数料</td>';
			}
			echo '</tr>';
			
			// 実績と金額を表示
			foreach ($datalist as $executiveArray) {
				if ($areaListArray['value'] === local_config::SHIBU_AREA_NAME) {
					echo '<tr><td class="left">全体計</td>';
				}
				else {
					echo '<tr><td class="left">'.$executiveArray['name'].'</td>';
				}
				for ($i = 4; $i <= 9; $i++) {
					echo '<td class="right">'.formatNumber($executiveArray[$i.'_plan'], 2).'</td>';
					echo '<td class="right">'.formatNumber($executiveArray[$i.'_result'], 2).'</td>';
					echo '<td class="right">'.formatNumber($executiveArray[$i.'_shibu'], 2).'</td>';

					$planTotal[$executiveArray['eid']]   += $executiveArray[$i.'_plan'];
					$resultTotal[$executiveArray['eid']] += $executiveArray[$i.'_result'];
					$shibuTotal[$executiveArray['eid']]  += $executiveArray[$i.'_shibu'];
				}
			}

			//-------------------------------
			// 2列目（10月～3月）
			//-------------------------------
			echo '<tr><td class="bg_wet_asphalt" style="width:60px;" rowspan="2">項目</td>';
			for ($i = 10; $i <= 12; $i++) {
				echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="3">'.$i.'月</td>';
			}
			for ($i = 1; $i <= 3; $i++) {
				echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="3">'.$i.'月</td>';
			}
			echo '<td class="bg_alizarin" style="width:'.$columnWidth.'px;" colspan="3">合計</td>';

			// 実績と金額の欄を表示
			echo '<tr>';
			for ($i = 1; $i <= 7; $i++) {
				echo '<td class="bg_yellow">同友<br>取引実績</td><td class="bg_red">同友<br>手数料</td><td class="bg_green">支部<br>手数料</td>';
			}
			echo '</tr>';

			// 実績と金額を表示
			foreach ($datalist as $executiveArray) {
				if ($areaListArray['value'] === local_config::SHIBU_AREA_NAME) {
					echo '<tr><td class="left">全体計</td>';
				}
				else {
					echo '<tr><td class="left">'.$executiveArray['name'].'</td>';
				}
				for ($i = 10; $i <= 12; $i++) {
					echo '<td class="right">'.formatNumber($executiveArray[$i.'_plan'], 2).'</td>';
					echo '<td class="right">'.formatNumber($executiveArray[$i.'_result'], 2).'</td>';
					echo '<td class="right">'.formatNumber($executiveArray[$i.'_shibu'], 2).'</td>';

					$planTotal[$executiveArray['eid']]   += $executiveArray[$i.'_plan'];
					$resultTotal[$executiveArray['eid']] += $executiveArray[$i.'_result'];
					$shibuTotal[$executiveArray['eid']]  += $executiveArray[$i.'_shibu'];
				}

				for ($i = 1; $i <= 3; $i++) {
					echo '<td class="right">'.formatNumber($executiveArray[$i.'_plan'], 2).'</td>';
					echo '<td class="right">'.formatNumber($executiveArray[$i.'_result'], 2).'</td>';
					echo '<td class="right">'.formatNumber($executiveArray[$i.'_shibu'], 2).'</td>';

					$planTotal[$executiveArray['eid']]   += $executiveArray[$i.'_plan'];
					$resultTotal[$executiveArray['eid']] += $executiveArray[$i.'_result'];
					$shibuTotal[$executiveArray['eid']]  += $executiveArray[$i.'_shibu'];
				}

				// 年間合計を表示
				echo '<td class="right">'.formatNumber($planTotal[$executiveArray['eid']], 2).'</td>';
				echo '<td class="right">'.formatNumber($resultTotal[$executiveArray['eid']], 2).'</td>';
				echo '<td class="right">'.formatNumber($shibuTotal[$executiveArray['eid']], 2).'</td>';
				echo '</tr>';
			}
			echo '</table><hr>';
		}
	}
	// 四半期
	elseif ($term === 'half_period') {
		foreach ($listArray['area_list'] as $areaListArray) {
			// 単位を取得
			if ($areaListArray['value'] === local_config::SHIBU_AREA_NAME) {
				$bg_class = "bg_wet_asphalt";
				$r_unit = $listArray['setting'][ $postArray['partner']]['sr_unit'];
				$p_unit = $listArray['setting'][ $postArray['partner']]['sp_unit'];
				$datalist = $listArray['data_list']['ALL'];
			}
			else {
				$bg_class = "bg_turquoise";
				$r_unit = $listArray['setting'][ $postArray['partner']]['r_unit'];
				$p_unit = $listArray['setting'][ $postArray['partner']]['p_unit'];
				$datalist = $listArray['data_list']['executive'][$areaListArray['value']];
			}

			echo '<table>';
			echo '<tr><td class="'.$bg_class.'" colspan="22">'.$areaListArray['value'].'の実績【単位 同友取引実績：'.$r_unit.'、同友手数料：'.$p_unit.'、支部手数料：'.$sp_unit.'】</td></tr>';
			echo '<tr><td class="bg_wet_asphalt" style="width:60px;" rowspan="2">項目</td>';
			echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="3">第1四半期</td>';
			echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="3">第2四半期</td>';
			echo '<td class="bg_midnight" style="width:'.$columnWidth.'px;" colspan="3">上半期</td>';
			echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="3">第3四半期</td>';
			echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="3">第4四半期</td>';
			echo '<td class="bg_midnight" style="width:'.$columnWidth.'px;" colspan="3">下半期</td>';
			echo '<td class="bg_alizarin" style="width:'.$columnWidth.'px;" colspan="3">年間</td>';

			// 実績と金額の欄を表示
			echo '<tr>';
			for ($i = 1; $i <= 7; $i++) {
				echo '<td class="bg_yellow">同友<br>取引実績</td><td class="bg_red">同友<br>手数料</td><td class="bg_green">支部<br>手数料</td>';
			}
			echo '</tr>';

			// 四半期なので3ヶ月毎に集計する
			foreach ($datalist as $executiveArray) {
				// 同友取引実績
				$q1 = $executiveArray['4_plan']  + $executiveArray['5_plan']+$executiveArray['6_plan'];
				$q2 = $executiveArray['7_plan']  + $executiveArray['8_plan']+$executiveArray['9_plan'];
				$q3 = $executiveArray['10_plan'] + $executiveArray['11_plan']+$executiveArray['12_plan'];
				$q4 = $executiveArray['1_plan']  + $executiveArray['2_plan']+$executiveArray['3_plan'];
				$half1 = $q1+$q2;
				$half2 = $q3+$q4;
				$total = $q1+$q2+$q3+$q4;

				// 同友手数料
				$q1k = $executiveArray['4_result'] + $executiveArray['5_result'] + $executiveArray['6_result'];
				$q2k = $executiveArray['7_result'] + $executiveArray['8_result'] + $executiveArray['9_result'];
				$q3k = $executiveArray['10_result'] + $executiveArray['11_result'] + $executiveArray['12_result'];
				$q4k = $executiveArray['1_result'] + $executiveArray['2_result'] + $executiveArray['3_result'];
				$half1k = $q1k+$q2k;
				$half2k = $q3k+$q4k;
				$totalk = $q1k+$q2k+$q3k+$q4k;

				// 支部手数料
				$q1s = $executiveArray['4_shibu'] + $executiveArray['5_shibu'] + $executiveArray['6_shibu'];
				$q2s = $executiveArray['7_shibu'] + $executiveArray['8_shibu'] + $executiveArray['9_shibu'];
				$q3s = $executiveArray['10_shibu'] + $executiveArray['11_shibu'] + $executiveArray['12_shibu'];
				$q4s = $executiveArray['1_shibu'] + $executiveArray['2_shibu'] + $executiveArray['3_shibu'];
				$half1s = $q1s+$q2s;
				$half2s = $q3s+$q4s;
				$totals = $q1s+$q2s+$q3s+$q4s;


				if ($areaListArray['value'] === local_config::SHIBU_AREA_NAME) {
					echo '<tr><td class="left">全体計</td>';
				}
				else {
					echo '<tr><td class="left">'.$executiveArray['name'].'</td>';
				}
				echo '<td class="right">'.formatNumber($q1, 2).'</td><td class="right">'.formatNumber($q1k, 2).'</td><td class="right">'.formatNumber($q1s, 2).'</td>';
				echo '<td class="right">'.formatNumber($q2, 2).'</td><td class="right">'.formatNumber($q2k, 2).'</td><td class="right">'.formatNumber($q2s, 2).'</td>';
				echo '<td class="right">'.formatNumber($half1k, 2).'</td><td class="right">'.formatNumber($half1k, 2).'</td><td class="right">'.formatNumber($half1s, 2).'</td>';
				echo '<td class="right">'.formatNumber($q3, 2).'</td><td class="right">'.formatNumber($q3k, 2).'</td><td class="right">'.formatNumber($q3s, 2).'</td>';
				echo '<td class="right">'.formatNumber($q4, 2).'</td><td class="right">'.formatNumber($q4k, 2).'</td><td class="right">'.formatNumber($q4s, 2).'</td>';
				echo '<td class="right">'.formatNumber($half2, 2).'</td><td class="right">'.formatNumber($half2k, 2).'</td><td class="right">'.formatNumber($half2s, 2).'</td>';
				echo '<td class="right">'.formatNumber($total, 2).'</td><td class="right">'.formatNumber($totalk, 2).'</td><td class="right">'.formatNumber($totals, 2).'</td>';
				echo '</tr>';
			}
			echo '</table><hr>';
		}		
	}
	// 三半期
	elseif ($term === 'fourth_period') {
		foreach ($listArray['area_list'] as $areaListArray) {
			// 単位を取得
			if ($areaListArray['value'] === local_config::SHIBU_AREA_NAME) {
				$bg_class = "bg_wet_asphalt";
				$r_unit = $listArray['setting'][ $postArray['partner']]['sr_unit'];
				$p_unit = $listArray['setting'][ $postArray['partner']]['sp_unit'];
				$datalist = $listArray['data_list']['ALL'];
			}
			else {
				$bg_class = "bg_turquoise";
				$r_unit = $listArray['setting'][ $postArray['partner']]['r_unit'];
				$p_unit = $listArray['setting'][ $postArray['partner']]['p_unit'];
				$datalist = $listArray['data_list']['executive'][$areaListArray['value']];
			}

			echo '<table>';
			echo '<tr><td class="'.$bg_class.'" colspan="14">'.$areaListArray['value'].'の実績【単位 同友取引実績：'.$r_unit.'、同友手数料：'.$p_unit.'、支部手数料：'.$sp_unit.'】</td></tr>';
			echo '<tr><td class="bg_wet_asphalt" style="width:60px;" rowspan="2">項目</td>';
			echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="3">4-7月期</td>';
			echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="3">8-11月期</td>';
			echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="3">12-3月期</td>';
			echo '<td class="bg_alizarin" style="width:'.$columnWidth.'px;" colspan="3">年間</td>';

			// 実績と金額の欄を表示
			echo '<tr>';
			for ($i = 1; $i <= 4; $i++) {
				echo '<td class="bg_yellow">同友<br>取引実績</td><td class="bg_red">同友<br>手数料</td><td class="bg_green">支部<br>手数料</td>';
			}
			echo '</tr>';

			// 三半期なので4ヶ月毎に集計する
			foreach ($datalist as $executiveArray) {
				// 実績
				$q1 = $executiveArray['4_plan']  + $executiveArray['5_plan'] + $executiveArray['6_plan']  + $executiveArray['7_plan'];
				$q2 = $executiveArray['8_plan']  + $executiveArray['9_plan'] + $executiveArray['10_plan'] + $executiveArray['11_plan'];
				$q3 = $executiveArray['12_plan'] + $executiveArray['1_plan'] + $executiveArray['2_plan']  + $executiveArray['3_plan'];
				$total = $q1+$q2+$q3;

				// 金額
				$q1k = $executiveArray['4_result']  + $executiveArray['5_result'] + $executiveArray['6_result']  + $executiveArray['7_result'];
				$q2k = $executiveArray['8_result']  + $executiveArray['9_result'] + $executiveArray['10_result'] + $executiveArray['11_result'];
				$q3k = $executiveArray['12_result'] + $executiveArray['1_result'] + $executiveArray['2_result']  + $executiveArray['3_result'];
				$totalk = $q1k+$q2k+$q3k;

				// 金額
				$q1s = $executiveArray['4_shibu']  + $executiveArray['5_shibu'] + $executiveArray['6_shibu']  + $executiveArray['7_shibu'];
				$q2s = $executiveArray['8_shibu']  + $executiveArray['9_shibu'] + $executiveArray['10_shibu'] + $executiveArray['11_shibu'];
				$q3s = $executiveArray['12_shibu'] + $executiveArray['1_shibu'] + $executiveArray['2_shibu']  + $executiveArray['3_shibu'];
				$totals = $q1s+$q2s+$q3s;


				if ($areaListArray['value'] === local_config::SHIBU_AREA_NAME) {
					echo '<tr><td class="left">全体計</td>';
				}
				else {
					echo '<tr><td class="left">'.$executiveArray['name'].'</td>';
				}
				echo '<td class="right">'.formatNumber($q1, 2).'</td><td class="right">'.formatNumber($q1k, 2).'</td><td class="right">'.formatNumber($q1s, 2).'</td>';
				echo '<td class="right">'.formatNumber($q2, 2).'</td><td class="right">'.formatNumber($q2k, 2).'</td><td class="right">'.formatNumber($q2s, 2).'</td>';
				echo '<td class="right">'.formatNumber($q3, 2).'</td><td class="right">'.formatNumber($q3k, 2).'</td><td class="right">'.formatNumber($q3s, 2).'</td>';
				echo '<td class="right">'.formatNumber($total, 2).'</td><td class="right">'.formatNumber($totalk, 2).'</td><td class="right">'.formatNumber($totals, 2).'</td>';
				echo '</tr>';
			}
			echo '</table><hr>';
		}
	}
}


/**
 * ----------------------------------------------------------
 * printUnitValueTable()
 * 協力会社の実績を表すテーブルを表示(★単価入力の場合)
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @param $tarm：期間
 * @return
 * ----------------------------------------------------------
 */
function printUnitValueTable($postArray, &$listArray, $term) {

	$columnWidth = 150;

	if ($term === 'monthly') {
		$columnItem = 25;
	}
	elseif ($term === 'half_period') {
		$columnItem = 15;
	}
	elseif ($term === 'fourth_period') {
		$columnItem = 9;
	}

	// 単位を取得
	$r_unit = $listArray['setting'][ $postArray['partner']]['r_unit'];
	$p_unit = $listArray['setting'][ $postArray['partner']]['p_unit'];

	//========================================
	// 同友毎の実績テーブルを表示
	//========================================
	// 月別を表示
	if ($term === 'monthly')  {
		foreach ($listArray['area_list'] as $areaListArray) {

			// 単位を取得
			if ($areaListArray['value'] === local_config::SHIBU_AREA_NAME) {
				$bg_class = "bg_wet_asphalt";
				$r_unit = $listArray['setting'][ $postArray['partner']]['sr_unit'];
				$p_unit = $listArray['setting'][ $postArray['partner']]['sp_unit'];
			}
			else {
				$bg_class = "bg_turquoise";
				$r_unit = $listArray['setting'][ $postArray['partner']]['r_unit'];
				$p_unit = $listArray['setting'][ $postArray['partner']]['p_unit'];
			}

			echo '<table>';
			echo '<tr><td class="'.$bg_class.'" colspan="27">'.$areaListArray['value'].'の実績【単位：'.$r_unit.'】と金額【単位：'.$p_unit.'】</td></tr>';
			echo '<tr><td class="bg_wet_asphalt" style="width:60px;" rowspan="2">項目</td>';

			// 月を表示
			for ($i = 4; $i <= 12; $i++) {
				echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="2">'.$i.'月</td>';
			}
			for ($i = 1; $i <= 3; $i++) {
				echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="2">'.$i.'月</td>';
			}
			echo '<td class="bg_alizarin" style="width:'.$columnWidth.'px;" colspan="2">合計</td>';
			echo '</tr>';

			// 実績と金額の欄を表示
			echo '<tr>';
			for ($i = 1; $i <= 13; $i++) {
				echo '<td class="bg_yellow">実績</td><td class="bg_red">金額</td>';
			}
			echo '</tr>';
			
			// 実績と金額を表示
			if ($areaListArray['value'] === local_config::SHIBU_AREA_NAME) {
				// 支部の表示のときは合計から計算する
				foreach ($listArray['data_list']['executive'][$areaListArray['value']] as $executiveArray) {
					$planTotal = 0;
					$resultTotal = 0;

					echo '<tr><td class="left">'.$executiveArray['name'].'</td>';
					for ($i = 4; $i <= 12; $i++) {
						echo '<td class="right">'.formatNumber($listArray['data_list']['ALL'][0][$i.'_result'], 2).'</td>';
						echo '<td class="right">'.formatNumber($listArray['data_list']['ALL'][0][$i.'_result']*$listArray['setting'][$postArray['partner']]['s_month_'.$i], 2).'</td>';
						
						// 合計値に加算
						$planTotal += $listArray['data_list']['ALL'][0][$i.'_result'];
						$resultTotal += $listArray['data_list']['ALL'][0][$i.'_result']*$listArray['setting'][$postArray['partner']]['s_month_'.$i];
					}
					for ($i = 1; $i <= 3; $i++) {
						echo '<td class="right">'.formatNumber($listArray['data_list']['ALL'][0][$i.'_result'], 2).'</td>';
						echo '<td class="right">'.formatNumber($listArray['data_list']['ALL'][0][$i.'_result']*$listArray['setting'][$postArray['partner']]['s_month_'.$i], 2).'</td>';
					
						// 合計値に加算
						$planTotal += $listArray['data_list']['ALL'][0][$i.'_result'];
						$resultTotal += $listArray['data_list']['ALL'][0][$i.'_result']*$listArray['setting'][$postArray['partner']]['s_month_'.$i];
					}
					// 年間合計を表示
					echo '<td class="right">'.formatNumber($planTotal, 2).'</td>';
					echo '<td class="right">'.formatNumber($resultTotal, 2).'</td>';
					echo '</tr>';
				}
			}
			else {
				foreach ($listArray['data_list']['executive'][$areaListArray['value']] as $executiveArray) {
					$planTotal = 0;
					$resultTotal = 0;

					echo '<tr><td class="left">'.$executiveArray['name'].'</td>';
					for ($i = 4; $i <= 12; $i++) {
						echo '<td class="right">'.formatNumber($executiveArray[$i.'_result'], 2).'</td>';
						echo '<td class="right">'.formatNumber($executiveArray[$i.'_result'] * $listArray['setting'][$postArray['partner']]['e_month_'.$i], 2).'</td>';
					
						// 合計値に加算
						$planTotal += $executiveArray[$i.'_result'];
						$resultTotal += $executiveArray[$i.'_result'] * $listArray['setting'][$postArray['partner']]['e_month_'.$i];
					}
					for ($i = 1; $i <= 3; $i++) {
						echo '<td class="right">'.formatNumber($executiveArray[$i.'_result'], 2).'</td>';
						echo '<td class="right">'.formatNumber($executiveArray[$i.'_result'] * $listArray['setting'][$postArray['partner']]['e_month_'.$i], 2).'</td>';
					
						// 合計値に加算
						$planTotal += $executiveArray[$i.'_result'];
						$resultTotal += $executiveArray[$i.'_result'] * $listArray['setting'][$postArray['partner']]['e_month_'.$i];
					}
					// 年間合計を表示
					echo '<td class="right">'.formatNumber($planTotal, 2).'</td>';
					echo '<td class="right">'.formatNumber($resultTotal, 2).'</td>';
					echo '</tr>';
				}
			}
			echo '</table><hr>';
		}
	}
	// 四半期
	elseif ($term === 'half_period') {
		foreach ($listArray['area_list'] as $areaListArray) {

			// 単位を取得
			if ($areaListArray['value'] === local_config::SHIBU_AREA_NAME) {
				$bg_class = "bg_wet_asphalt";
				$r_unit = $listArray['setting'][ $postArray['partner']]['sr_unit'];
				$p_unit = $listArray['setting'][ $postArray['partner']]['sp_unit'];
			}
			else {
				$bg_class = "bg_turquoise";
				$r_unit = $listArray['setting'][ $postArray['partner']]['r_unit'];
				$p_unit = $listArray['setting'][ $postArray['partner']]['p_unit'];
			}

			echo '<table>';
			echo '<tr><td class="'.$bg_class.'" colspan="15">'.$areaListArray['value'].'の実績【単位：'.$r_unit.'】と金額【単位：'.$p_unit.'】</td></tr>';
			echo '<tr><td class="bg_wet_asphalt" style="width:60px;" rowspan="2">項目</td>';
			echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="2">第1四半期</td>';
			echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="2">第2四半期</td>';
			echo '<td class="bg_midnight" style="width:'.$columnWidth.'px;" colspan="2">上半期</td>';
			echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="2">第3四半期</td>';
			echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="2">第4四半期</td>';
			echo '<td class="bg_midnight" style="width:'.$columnWidth.'px;" colspan="2">下半期</td>';
			echo '<td class="bg_alizarin" style="width:'.$columnWidth.'px;" colspan="2">年間</td>';

			// 実績と金額の欄を表示
			echo '<tr>';
			for ($i = 1; $i <= 7; $i++) {
				echo '<td class="bg_yellow">実績</td><td class="bg_red">金額</td>';
			}
			echo '</tr>';

			// 四半期なので3ヶ月毎に集計する
			if ($areaListArray['value'] === local_config::SHIBU_AREA_NAME) {
				foreach ($listArray['data_list']['executive'][$areaListArray['value']] as $executiveArray) {
					// 実績
					$q1 = $listArray['data_list']['ALL'][0]['4_result']+$listArray['data_list']['ALL'][0]['5_result']+$listArray['data_list']['ALL'][0]['6_result'];
					$q2 = $listArray['data_list']['ALL'][0]['7_result']+$listArray['data_list']['ALL'][0]['8_result']+$listArray['data_list']['ALL'][0]['9_result'];
					$q3 = $listArray['data_list']['ALL'][0]['10_result']+$listArray['data_list']['ALL'][0]['11_result']+$listArray['data_list']['ALL'][0]['12_result'];
					$q4 = $listArray['data_list']['ALL'][0]['1_result']+$listArray['data_list']['ALL'][0]['2_result']+$listArray['data_list']['ALL'][0]['3_result'];
					$half1 = $q1+$q2;
					$half2 = $q3+$q4;
					$total = $q1+$q2+$q3+$q4;

					// 金額
					$q1k = $listArray['data_list']['ALL'][0]['4_result']*$listArray['setting'][$postArray['partner']]['s_month_4'] + $listArray['data_list']['ALL'][0]['5_result']*$listArray['setting'][$postArray['partner']]['s_month_5'] + $listArray['data_list']['ALL'][0]['6_result']*$listArray['setting'][$postArray['partner']]['s_month_6'];
					$q2k = $listArray['data_list']['ALL'][0]['7_result']*$listArray['setting'][$postArray['partner']]['s_month_7'] + $listArray['data_list']['ALL'][0]['8_result']*$listArray['setting'][$postArray['partner']]['s_month_8'] + $listArray['data_list']['ALL'][0]['9_result']*$listArray['setting'][$postArray['partner']]['s_month_9'];
					$q3k = $listArray['data_list']['ALL'][0]['10_result']*$listArray['setting'][$postArray['partner']]['s_month_10'] + $listArray['data_list']['ALL'][0]['11_result']*$listArray['setting'][$postArray['partner']]['s_month_11'] + $listArray['data_list']['ALL'][0]['12_result']*$listArray['setting'][$postArray['partner']]['s_month_12'];
					$q4k = $listArray['data_list']['ALL'][0]['1_result']*$listArray['setting'][$postArray['partner']]['s_month_1'] + $listArray['data_list']['ALL'][0]['2_result']*$listArray['setting'][$postArray['partner']]['s_month_2'] + $listArray['data_list']['ALL'][0]['3_result']*$listArray['setting'][$postArray['partner']]['s_month_3'];
					$half1k = $q1k+$q2k;
					$half2k = $q3k+$q4k;
					$totalk = $q1k+$q2k+$q3k+$q4k;

					echo '<tr><td class="left">'.$executiveArray['name'].'</td>';
					echo '<td class="right">'.formatNumber($q1, 2).'</td><td class="right">'.formatNumber($q1k, 2).'</td>';
					echo '<td class="right">'.formatNumber($q2, 2).'</td><td class="right">'.formatNumber($q2k, 2).'</td>';
					echo '<td class="right">'.formatNumber($half1k, 2).'</td><td class="right">'.formatNumber($half1k, 2).'</td>';
					echo '<td class="right">'.formatNumber($q3, 2).'</td><td class="right">'.formatNumber($q3k, 2).'</td>';
					echo '<td class="right">'.formatNumber($q4, 2).'</td><td class="right">'.formatNumber($q4k, 2).'</td>';
					echo '<td class="right">'.formatNumber($half2, 2).'</td><td class="right">'.formatNumber($half2k, 2).'</td>';
					echo '<td class="right">'.formatNumber($total, 2).'</td><td class="right">'.formatNumber($totalk, 2).'</td>';
					echo '</tr>';
				}
			}
			else {
				foreach ($listArray['data_list']['executive'][$areaListArray['value']] as $executiveArray) {
					// 実績
					$q1 = $executiveArray['4_result']  + $executiveArray['5_result']+$executiveArray['6_result'];
					$q2 = $executiveArray['7_result']  + $executiveArray['8_result']+$executiveArray['9_result'];
					$q3 = $executiveArray['10_result'] + $executiveArray['11_result']+$executiveArray['12_result'];
					$q4 = $executiveArray['1_result']  + $executiveArray['2_result']+$executiveArray['3_result'];
					$half1 = $q1+$q2;
					$half2 = $q3+$q4;
					$total = $q1+$q2+$q3+$q4;

					// 金額
					$q1k = $executiveArray['4_result']  * $listArray['setting'][$postArray['partner']]['e_month_4']  + $executiveArray['5_result']  * $listArray['setting'][$postArray['partner']]['e_month_5']  + $executiveArray['6_result']  * $listArray['setting'][$postArray['partner']]['e_month_6'];
					$q2k = $executiveArray['7_result']  * $listArray['setting'][$postArray['partner']]['e_month_7']  + $executiveArray['8_result']  * $listArray['setting'][$postArray['partner']]['e_month_8']  + $executiveArray['9_result']  * $listArray['setting'][$postArray['partner']]['e_month_8'];
					$q3k = $executiveArray['10_result'] * $listArray['setting'][$postArray['partner']]['e_month_10'] + $executiveArray['11_result'] * $listArray['setting'][$postArray['partner']]['e_month_11'] + $executiveArray['12_result'] * $listArray['setting'][$postArray['partner']]['e_month_12'];
					$q4k = $executiveArray['1_result']  * $listArray['setting'][$postArray['partner']]['e_month_1']  + $executiveArray['2_result']  * $listArray['setting'][$postArray['partner']]['e_month_2']  + $executiveArray['3_result']  * $listArray['setting'][$postArray['partner']]['e_month_3'];
					$half1k = $q1k+$q2k;
					$half2k = $q3k+$q4k;
					$totalk = $q1k+$q2k+$q3k+$q4k;

					echo '<tr><td class="left">'.$executiveArray['name'].'</td>';
					echo '<td class="right">'.formatNumber($q1, 2).'</td><td class="right">'.formatNumber($q1k, 2).'</td>';
					echo '<td class="right">'.formatNumber($q2, 2).'</td><td class="right">'.formatNumber($q2k, 2).'</td>';
					echo '<td class="right">'.formatNumber($half1k, 2).'</td><td class="right">'.formatNumber($half1k, 2).'</td>';
					echo '<td class="right">'.formatNumber($q3, 2).'</td><td class="right">'.formatNumber($q3k, 2).'</td>';
					echo '<td class="right">'.formatNumber($q4, 2).'</td><td class="right">'.formatNumber($q4k, 2).'</td>';
					echo '<td class="right">'.formatNumber($half2, 2).'</td><td class="right">'.formatNumber($half2k, 2).'</td>';
					echo '<td class="right">'.formatNumber($total, 2).'</td><td class="right">'.formatNumber($totalk, 2).'</td>';
					echo '</tr>';
				}
			}
			echo '</table><hr>';
		}		
	}
	// 三半期
	elseif ($term === 'fourth_period') {
		foreach ($listArray['area_list'] as $areaListArray) {

			// 単位を取得
			if ($areaListArray['value'] === local_config::SHIBU_AREA_NAME) {
				$bg_class = "bg_wet_asphalt";
				$r_unit = $listArray['setting'][ $postArray['partner']]['sr_unit'];
				$p_unit = $listArray['setting'][ $postArray['partner']]['sp_unit'];
			}
			else {
				$bg_class = "bg_turquoise";
				$r_unit = $listArray['setting'][ $postArray['partner']]['r_unit'];
				$p_unit = $listArray['setting'][ $postArray['partner']]['p_unit'];
			}

			echo '<table>';
			echo '<tr><td class="'.$bg_class.'" colspan="9">'.$areaListArray['value'].'の実績【単位：'.$r_unit.'】と金額【単位：'.$p_unit.'】</td></tr>';
			echo '<tr><td class="bg_wet_asphalt" style="width:60px;" rowspan="2">項目</td>';
			echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="2">4-7月期</td>';
			echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="2">8-11月期</td>';
			echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="2">12-3月期</td>';
			echo '<td class="bg_alizarin" style="width:'.$columnWidth.'px;" colspan="2">年間</td>';

			// 実績と金額の欄を表示
			echo '<tr>';
			for ($i = 1; $i <= 4; $i++) {
				echo '<td class="bg_yellow">実績</td><td class="bg_red">金額</td>';
			}
			echo '</tr>';

			// 三半期なので4ヶ月毎に集計する
			if ($areaListArray['value'] === local_config::SHIBU_AREA_NAME) {
				foreach ($listArray['data_list']['executive'][$areaListArray['value']] as $executiveArray) {
					// 実績
					$q1 = $listArray['data_list']['ALL'][0]['4_result']+$listArray['data_list']['ALL'][0]['5_result']+$listArray['data_list']['ALL'][0]['6_result']+$listArray['data_list']['ALL'][0]['7_result'];
					$q2 = $listArray['data_list']['ALL'][0]['8_result']+$listArray['data_list']['ALL'][0]['9_result']+$listArray['data_list']['ALL'][0]['10_result']+$listArray['data_list']['ALL'][0]['11_result'];
					$q3 = $listArray['data_list']['ALL'][0]['12_result']+$listArray['data_list']['ALL'][0]['1_result']+$listArray['data_list']['ALL'][0]['2_result']+$listArray['data_list']['ALL'][0]['3_result'];
					$total = $q1+$q2+$q3;

					// 金額
					$q1k = $listArray['data_list']['ALL'][0]['4_result']*$listArray['setting'][$postArray['partner']]['s_month_4'] + $listArray['data_list']['ALL'][0]['5_result']*$listArray['setting'][$postArray['partner']]['s_month_5'] + $listArray['data_list']['ALL'][0]['6_result']*$listArray['setting'][$postArray['partner']]['s_month_6'] + $listArray['data_list']['ALL'][0]['7_result']*$listArray['setting'][$postArray['partner']]['s_month_7'];
					$q2k = $listArray['data_list']['ALL'][0]['8_result']*$listArray['setting'][$postArray['partner']]['s_month_8'] + $listArray['data_list']['ALL'][0]['9_result']*$listArray['setting'][$postArray['partner']]['s_month_9'] + $listArray['data_list']['ALL'][0]['10_result']*$listArray['setting'][$postArray['partner']]['s_month_10'] + $listArray['data_list']['ALL'][0]['11_result']*$listArray['setting'][$postArray['partner']]['s_month_11'];
					$q3k = $listArray['data_list']['ALL'][0]['12_result']*$listArray['setting'][$postArray['partner']]['s_month_12'] + $listArray['data_list']['ALL'][0]['1_result']*$listArray['setting'][$postArray['partner']]['s_month_1'] + $listArray['data_list']['ALL'][0]['2_result']*$listArray['setting'][$postArray['partner']]['s_month_2'] + $listArray['data_list']['ALL'][0]['3_result']*$listArray['setting'][$postArray['partner']]['s_month_3'];
					$totalk = $q1k+$q2k+$q3k;

					echo '<tr><td>全体計</td>';
					echo '<td class="right">'.formatNumber($q1, 2).'</td><td class="right">'.formatNumber($q1k, 2).'</td>';
					echo '<td class="right">'.formatNumber($q2, 2).'</td><td class="right">'.formatNumber($q2k, 2).'</td>';
					echo '<td class="right">'.formatNumber($q3, 2).'</td><td class="right">'.formatNumber($q3k, 2).'</td>';
					echo '<td class="right">'.formatNumber($total, 2).'</td><td class="right">'.formatNumber($totalk, 2).'</td>';
					echo '</tr>';
				}
			}
			else {
				foreach ($listArray['data_list']['executive'][$areaListArray['value']] as $executiveArray) {
					// 実績
					$q1 = $executiveArray['4_result']  + $executiveArray['5_result'] + $executiveArray['6_result']  + $executiveArray['7_result'];
					$q2 = $executiveArray['8_result']  + $executiveArray['9_result'] + $executiveArray['10_result'] + $executiveArray['11_result'];
					$q3 = $executiveArray['12_result'] + $executiveArray['1_result'] + $executiveArray['2_result']  + $executiveArray['3_result'];
					$total = $q1+$q2+$q3;

					// 金額
					$q1k = $executiveArray['4_result']  * $listArray['setting'][$postArray['partner']]['e_month_4']  + $executiveArray['5_result'] * $listArray['setting'][$postArray['partner']]['e_month_5'] + $executiveArray['6_result']  * $listArray['setting'][$postArray['partner']]['e_month_6']  + $executiveArray['7_result']  * $listArray['setting'][$postArray['partner']]['e_month_7'];
					$q2k = $executiveArray['8_result']  * $listArray['setting'][$postArray['partner']]['e_month_8']  + $executiveArray['9_result'] * $listArray['setting'][$postArray['partner']]['e_month_9'] + $executiveArray['10_result'] * $listArray['setting'][$postArray['partner']]['e_month_10'] + $executiveArray['11_result'] * $listArray['setting'][$postArray['partner']]['e_month_11'];
					$q3k = $executiveArray['12_result'] * $listArray['setting'][$postArray['partner']]['e_month_12'] + $executiveArray['1_result'] * $listArray['setting'][$postArray['partner']]['e_month_1'] + $executiveArray['2_result']  * $listArray['setting'][$postArray['partner']]['e_month_2']  + $executiveArray['3_result']  * $listArray['setting'][$postArray['partner']]['e_month_3'];
					$totalk = $q1k+$q2k+$q3k;

					echo '<tr><td class="left">'.$executiveArray['name'].'</td>';
					echo '<td class="right">'.formatNumber($q1, 2).'</td><td class="right">'.formatNumber($q1k, 2).'</td>';
					echo '<td class="right">'.formatNumber($q2, 2).'</td><td class="right">'.formatNumber($q2k, 2).'</td>';
					echo '<td class="right">'.formatNumber($q3, 2).'</td><td class="right">'.formatNumber($q3k, 2).'</td>';
					echo '<td class="right">'.formatNumber($total, 2).'</td><td class="right">'.formatNumber($totalk, 2).'</td>';
					echo '</tr>';
				}
			}
			echo '</table><hr>';
		}
	}
}

/**
 * ----------------------------------------------------------
 * printExecutiveResultInputTable()
 * 同友会社の月毎の実績を入力するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @param $area：地域名
 * @param $limit：読み込み専用/入力可能のフラグ
 * @return
 * ----------------------------------------------------------
 */
function printExecutiveResultInputTable($postArray, $listArray, $area, $limit='input') {

	// ロータス支部の場合は支部用に切り替え
	if ($area === local_config::SHIBU_AREA_NAME) {
		//if ($listArray['setting'][$postArray['partner']]['main'] != config::COOPERATION_SETTING_TYPE_RAW) {
			return;
		//}
		$bg_class = "bg_wet_asphalt";
		$r_unit = 'sr_unit';
		$p_unit = 'sp_unit';
	}
	else {
		$bg_class = "bg_turquoise";
		$r_unit = 'r_unit';
		$p_unit = 'p_unit';
	}

	// 見出しを表示(最初だけ表示)
	echo '<tr><td  class="'.$bg_class.'" colspan="4">'.$area.'</td></tr>';

	// 月を表示
	echo '<tr><td class="bg_wet_asphalt" style="width:200px;" rowspan="2">項目</td>';
	echo '<td class="bg_wet_asphalt" colspan="3">'.$postArray['month'].'月</td>';
	echo '</tr>';
	
	// 種目毎の計画、実績、%を表示
	echo '<tr>';
	if ($listArray['setting'][$postArray['partner']]['main'] == config::COOPERATION_SETTING_TYPE_RAW) {
		echo '<td class="bg_red" style="width:120px;" >同友取引実績<br> [単位：'.$listArray['setting'][ $postArray['partner']][$r_unit].']</td>';
		echo '<td class="bg_red" style="width:120px;" >同友手数料<br> [単位：'.$listArray['setting'][ $postArray['partner']][$p_unit].']</td>';
		echo '<td class="bg_red" style="width:120px;" >支部手数料<br> [単位：'.$listArray['setting'][ $postArray['partner']][$p_unit].']</td>';
	}
	else {
		echo '<td class="bg_red" style="width:120px;" >取引実績 [単位：'.$listArray['setting'][ $postArray['partner']][$r_unit].']</td>';
	}
	echo '</tr>';
	
	// 同友会社の指定月の計画、実績入力用のテキストボックスを表示
	foreach ($listArray['data_list']['executive'][$area] as $executiveArray) {
		$dr_unit = $postArray['month'].'_plan';
		$dp_unit = $postArray['month'].'_result';
		$dps_unit = $postArray['month'].'_shibu';
		
		echo '<tr>';
		echo '<td class="left" id="'.$executiveArray['code'].'">'.$executiveArray['name'].'</td>';

		//-------------------------------
		// 入力画面を表示
		//-------------------------------
		if ($limit === 'input') {
			// 直接入力の場合は、取引実績も入力する
			if ($listArray['setting'][$postArray['partner']]['main'] == config::COOPERATION_SETTING_TYPE_RAW) {
				echo '<td class="right">';
				printTextBox($executiveArray['eid'].':'.$postArray['partner'].':'.$dr_unit, 150, 'right', $executiveArray[$dr_unit]);	// 取引実績
				echo '</td>';
			}

			// 手数料の入力
			echo '<td class="right">';
			printTextBox($executiveArray['eid'].':'.$postArray['partner'].':'.$dp_unit, 150, 'right', $executiveArray[$dp_unit]);		// 手数料
			echo '</td>';

			// 直接入力の場合は、支部の手数料も入力する
			if ($listArray['setting'][$postArray['partner']]['main'] == config::COOPERATION_SETTING_TYPE_RAW) {
				echo '<td class="right">';
				printTextBox($executiveArray['eid'].':'.$postArray['partner'].':'.$dps_unit, 150, 'right', $executiveArray[$dps_unit]);	// 取引実績
				echo '</td>';
			}
		}
		//-------------------------------
		// 表示のみ
		//-------------------------------
		else {
			// 同友取引実績
			if ($listArray['setting'][$postArray['partner']]['main'] == config::COOPERATION_SETTING_TYPE_RAW) {
				echo '<td class="right">';
				echo formatNumber($executiveArray[$dr_unit], 2);
				echo '</td>';
			}

			// 同友手数料
			echo '<td class="right">';
			echo formatNumber($executiveArray[$dp_unit], 2);
			echo '</td>';

			// 支部手数料
			if ($listArray['setting'][$postArray['partner']]['main'] == config::COOPERATION_SETTING_TYPE_RAW) {
				echo '<td class="right">';
				echo formatNumber($executiveArray[$dps_unit], 2);
				echo '</td>';
			}
		}
		echo '</tr>';
	}
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
	$userInfo = getUserInfo($_SESSION['USERID']);
?>

<div id="contents_menu">
	<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'view') ?>"
	value="<?php echo '実績閲覧' ?>"
	onClick="location.href='./index.php?reg=cooperation&type=view'">

	<?php
	// 実績入力、設定は管理者と協力企業
	if ($userInfo['auth'] == config::USER_ADMIN  || $userInfo['auth'] == config::USER_COOPERATION) { ?>
		<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'input') ?>"
		value="<?php echo '実績入力' ?>"
		onClick="location.href='./index.php?reg=cooperation&type=input'">  

		<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'setting') ?>"
		value="<?php echo '設定' ?>"
		onClick="location.href='./index.php?reg=cooperation&type=setting'">
	<?php } ?>
</div>
<hr>

<?php
//---------------------------------------------
// 協力企業  実績表示画面
//---------------------------------------------
if ($page === 'view') { ?>

<div id="contents_select">
    <form style="display:inline" method="POST" action="index.php?reg=cooperation&type=<?php echo $getArray['type'] ?>">
		<?php printSelectBox($listArray['fiscal_year_list'], 'fiscal_year', 70, $postArray['fiscal_year']) ?>年度
        <?php printSelectBox($listArray['partner_list'], 'partner', 150, $postArray['partner']); ?>
		<?php
			printSubmitButton('四半期表示', 'half_period');
			printSubmitButton('4ヶ月毎表示', 'fourth_period');
			printSubmitButton('月別表示', 'monthly');
		?>
		<input type="hidden" name="command" value="show">
	</form>
</div>
<hr>

<div id="contents">
	<?php
	if (isset($postArray['command'])) {
	
		$targetName = "";
	
		// 提携企業名を取得
		if (isset($postArray['partner'])) {
			$userInfo = getUserInfo('%', $postArray['partner']);
			$targetName = $userInfo['name'].'の';
		}
	
		if (isset($postArray['monthly'])) {
			echo '<p>'.$targetName.'月別の実績を表示しています。</p><br />';
		}
		elseif (isset($postArray['fourth_period'])) {
			echo '<p>'.$targetName.'4ヶ月毎に集計した実績を表示しています。</p><br />';
		}
		else {
			echo '<p>'.$targetName.'上半期、下半期、四半期の実績を表示しています。</p><br />';
		}

		// 月別表示
		if (isset($postArray['monthly'])) {
			// 月別表示
			if ($listArray['setting'][$postArray['partner']]['main'] == config::COOPERATION_SETTING_TYPE_RAW) {
				printRawValueTable($postArray, $listArray, 'monthly');			// 直接入力
			}
			else{
				printUnitValueTable($postArray, $listArray, 'monthly');			// 単価入力
			}
		}
		elseif (isset($postArray['fourth_period'])) {
			// 三半期表示
			if ($listArray['setting'][$postArray['partner']]['main'] == config::COOPERATION_SETTING_TYPE_RAW) {
				printRawValueTable($postArray, $listArray, 'fourth_period');	// 直接入力
			}
			else {
				printUnitValueTable($postArray, $listArray, 'fourth_period');	// 単価入力
			}
		}
		else {
			// 四半期表示
			if ($listArray['setting'][$postArray['partner']]['main'] == config::COOPERATION_SETTING_TYPE_RAW) {
				printRawValueTable($postArray, $listArray, 'half_period');		// 直接入力
			}
			else {
				printUnitValueTable($postArray, $listArray, 'half_period');		// 単価入力
			}
		}
	}
	?>
</div>

<?php }
//---------------------------------------------
// 協力企業  実績入力画面
//---------------------------------------------
else if ($page === 'input') { ?>

<div id="contents_select">
    <form style="display:inline" method="POST" action="index.php?reg=cooperation&type=<?php echo $getArray['type'] ?>">
		<?php printSelectBox($listArray['fiscal_year_list'], 'fiscal_year', 70, $postArray['fiscal_year']) ?>年度
        <?php printSelectBox(getMonthList(), 'month', 70, $postArray['month']) ?>月
        <?php printSelectBox($listArray['partner_list'], 'partner', 150, $postArray['partner']); ?>
		<?php printSubmitButton('入力画面表示') ?>
		<input type="hidden" name="command" value="show">
	</form>

	<?php
	// 保存ボタンは実績入力画面を表示している場合のみ
	if ($postArray['command'] === 'show') { ?>
        <form style="display:inline" method="POST" action="index.php?reg=cooperation&type=<?php echo $getArray['type']?>">
			<?php printSubmitButton('保存', 'save'); ?>
            <input type="hidden" name="command" value="save">
            <input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>">
			<input type="hidden" name="month" value="<?php echo $postArray['month'] ?>">
            <input type="hidden" name="partner" value="<?php echo $postArray['partner'] ?>">
			<input type="hidden" name="item" value="cooperation">
    <?php } ?>
	<?php
	// 一括入力ボタンは計画入力画面を表示している場合のみ
	if ($postArray['command'] === 'show') { ?>
		<?php printButton('一括入力', '', 'openBundleInputWindow()') ?>
	<?php } ?>
</div>
<hr>

<div id="contents">
	<?php
	// 実績入力画面を表示
	if (isset($postArray['command'])  && $postArray['command'] === 'show') { ?>
		<p>[<?php printPartnerName($listArray['partner_list'], $postArray['partner']) ?>の<?php echo $postArray['fiscal_year'].'年度'.$postArray['month'] ?>月の実績]</p><br />
		<?php
		echo "<p>実績を入力し、「保存」ボタンを押してください。</p><br />";
		
		// 同友会社の実績を地域毎に表示
		foreach ($listArray['area_list'] as $areaListArray) { ?>
			<table>
			<?php
			// 実績確認画面を表示
			printExecutiveResultInputTable($postArray, $listArray, $areaListArray['value']);
			?>
			</table>
			<hr>
		<?php } ?>
	<?php }
	elseif (isset($postArray['command'])  && $postArray['command'] === 'save') {
		echo "<p>実績の保存が完了しました。</p><br />";

		// 同友会社の実績を地域毎に表示
		foreach ($listArray['area_list'] as $areaListArray) { ?>
			<table>
			<?php
			// 実績入力画面を表示
			printExecutiveResultInputTable($postArray, $listArray, $areaListArray['value'], 'readOnly');
			?>
			</table>
			<hr>
		<?php } ?>
	<?php }
	// 未選択の場合
	else { ?>
		<p>実績を入力する「年度」、「月」、「協力企業」を選択してください。</p>
	<?php } ?>
	</form>
</div>

<?php }
//---------------------------------------------
// 協力企業 設定画面
//---------------------------------------------
elseif ($page === 'setting') { ?>

<div id="contents_select">
    <form style="display:inline" method="POST" action="index.php?reg=cooperation&type=<?php echo $getArray['type'] ?>">
		<?php printSelectBox($listArray['fiscal_year_list'], 'fiscal_year', 70, $postArray['fiscal_year']) ?>年度
        <?php printSelectBox($listArray['partner_list'], 'partner', 150, $postArray['partner']); ?>
		<?php printSubmitButton('設定画面表示') ?>
		<input type="hidden" name="command" value="show">
	</form>

    <?php
	// 保存ボタンは設定入力画面を表示している場合のみ
	if ($postArray['command'] === 'show') { ?>
        <form style="display:inline" method="POST" action="index.php?reg=cooperation&type=<?php echo $getArray['type']?>">
			<?php printSubmitButton('保存', 'save'); ?>
            <input type="hidden" name="command" value="save">
            <input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>">
            <input type="hidden" name="partner" value="<?php echo $postArray['partner'] ?>">
    <?php } ?>
</div>
<hr>

<div id="contents">

	<?php if (!isset($postArray['command'])) { ?>
		<p>年度と協力企業を選択し、「設定画面表示」ボタンを押してください。</p>
	<?php } 
	else if (isset($postArray['command'])  && $postArray['command'] === 'show' ) { ?>
        <!-- <p>選択した年度における設定を入力してください。</p><br />
        <p>【直接入力】：実績入力画面において金額を直接入力する場合はこちらを選択してください。</p>
        <p>【単価入力】：実績入力画面において数量を入力し、設定した単価で計算する場合はこちらを選択してください。</p><br> -->

        <hr>
		<?php
		if ($listArray['setting'][$postArray['partner']]["main"] == config::COOPERATION_SETTING_TYPE_RAW) {
			printRadioButton('main', '直接入力', config::COOPERATION_SETTING_TYPE_RAW, true);
		}
		else {
			printRadioButton('main', '直接入力', config::COOPERATION_SETTING_TYPE_RAW, true);	// 常にチェック（※単位設定をなくしたので常に直接入力モードにするため）
			//printRadioButton('main', '直接入力', config::COOPERATION_SETTING_TYPE_RAW, false);
		} ?>
		<!-- <p>※直接入力を選択する場合、単位のみ設定して「保存」ボタンを押してください。</p> -->
		<!-- <br /><br />
        <hr>-->
		<?php
		// if ($listArray['setting'][$postArray['partner']]['main'] == config::COOPERATION_SETTING_TYPE_UNIT) {
		// 	printRadioButton('main', '単価入力', config::COOPERATION_SETTING_TYPE_UNIT, true);
		// }
		// else {
		// 	printRadioButton('main', '単価入力', config::COOPERATION_SETTING_TYPE_UNIT, false);
		// } ?>
        <!-- <p>※月別の単価入力を入力して、「保存」ボタンを押してください。</p><br />-->

        <?php
		// echo "<p>".$postArray['fiscal_year']."年度の月別の手数料の単価を入力してください。<br>";
		// echo "<table>";
		// echo '<tr class="bg_wet_asphalt"><td>対象</td><td>4月</td><td>5月</td><td>6月</td><td>7月</td><td>8月</td><td>9月</td><td>10月</td><td>11月</td><td>12月</td><td>1月</td><td>2月</td><td>3月</td></tr>';
		
		// echo "<tr>";
		// echo '<td style="width:100px;">同友</td>';
		// for ($i = 4; $i <= 12; $i++) {
		// 	$index = 'e_month_'.$i;
		// 	echo "<td>";
		// 	printTextBox($index, 50, '', $listArray['setting'][$postArray['partner']]["e_month_".$i]);
		// 	echo "</td>";
		// }
		// for ($i = 1; $i <= 3; $i++) {
		// 	$index = 'e_month_'.$i;
		// 	echo "<td>";
		// 	printTextBox($index, 50, '', $listArray['setting'][$postArray['partner']]["e_month_".$i]);
		// 	echo "</td>";
		// }
		// echo "</tr>";

		// echo "<tr>";
		// echo '<td style="width:100px;">支部</td>';
		// for ($i = 4; $i <= 12; $i++) {
		// 	$index = 's_month_'.$i;
		// 	echo "<td>";
		// 	printTextBox($index, 50, '', $listArray['setting'][$postArray['partner']]["s_month_".$i]);
		// 	echo "</td>";
		// }
		// for ($i = 1; $i <= 3; $i++) {
		// 	$index = 's_month_'.$i;
		// 	echo "<td>";
		// 	printTextBox($index, 50, '', $listArray['setting'][$postArray['partner']]["s_month_".$i]);
		// 	echo "</td>";
		// }
		// echo "</tr>";

		// echo "</table>";
        ?>
		<br><br>
		<p>取引で使用する単位を入力してください。</p>
		<table>
		<tr class="bg_wet_asphalt"><td style="width:100px;">対象</td><td style="width:80px;">取引実績</td><td style="width:80px;">支払手数料</td></tr>
		<tr><td>同友</td>
		<td><?php printTextBox('r_unit', 100, '', $listArray['setting'][$postArray['partner']]['r_unit']); ?></td>
		<td><?php printTextBox('p_unit', 100, '', $listArray['setting'][$postArray['partner']]['p_unit']); ?></td>
		</tr>
		<tr><td>支部</td>
		<td><?php //printTextBox('sr_unit', 100, '', $listArray['setting'][$postArray['partner']]['sr_unit']);?></td>
		<td><?php printTextBox('sp_unit', 100, '', $listArray['setting'][$postArray['partner']]['sp_unit']); ?></td>
		</tr>
		</table>
		</form>
	<?php }
	else { ?>
		<p>設定が保存されました。</p>
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
 *  Copyright(c)20120 incloop All Rights Reserved.
 * =================================================================
 */
?>