<?php
/**
 * =================================================================
 * plan.php
 * 年間計画用PHPスクリプト
 * =================================================================
 */

//=================================================================
// ロジック部
//=================================================================

//--------------------------------
// グローバル
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
	'fiscal_year_list' => array(),
	'item_list'        => array(),
	'area_list'        => array(),
	'partner_list'     => array(),
	'update_time'      => array(),
	'data_list'        => array(),
	'min_target'       => array(),
	'executive_promotion'	=> array(),
	'lc_hold_number'   => array()
);
$copyArray = array();

//--------------------------------
// 表示ページを選択
//--------------------------------
$page = selectPageForPlan($getArray, $postArray, $listArray);

/**
 * ----------------------------------------------------------
 * selectPageForPlan()
 * 年間計画で表示するページを選択する
 * @param $getArray：GETで送られてきたパラメータ
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function selectPageForPlan($getArray, &$postArray, &$listArray) {

	$page = '';

	// キャンペーンで表示する種目を統合するのか分離するのかを判定
	if (in_array($postArray['item'], local_config::PLAN_ITEM_COMBINE)) {
		local_config::$FLAG_SEPARATE_PARTNER_PLAN = false;
	}
	else {
		local_config::$FLAG_SEPARATE_PARTNER_PLAN = true;
	}


	// 選択中のメニューに応じた結果を表示
	switch ($getArray['type']) {
		case  'view':
			$page = makePlanResultViewPage($postArray, $listArray);
			break;
		case  'input':
			$page = makePlanInputPage($postArray, $listArray);
			break;
		case  'lock':
			$page = makePlanLockPage($postArray, $listArray);
			break;
		case  'make':
			$page = makePlanMakePage($postArray, $listArray);
			break;
		case  'new':
			$page = makePlanNewPage($postArray, $listArray);
			break;
		default:
			break;
	}
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makePlanResultViewPage()
 * 実績を閲覧するためのページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makePlanResultViewPage($postArray, &$listArray) {

	global $copyArray;
	$page = 'result';
	
	//echo 'makePlanResultViewPage<br />';

	// ログインユーザ情報を取得
	$userInfo = getUserInfo($_SESSION['USERID']);
	
	// 年度一覧を取得
	if ($userInfo['auth'] == config::USER_ADMIN) {
		$listArray['fiscal_year_list'] = getFiscalYearList();
	}
	else {
		$listArray['fiscal_year_list'] = getOpenFiscalYearList();
	}
	if (count($listArray['fiscal_year_list']) == 0) {
		return 'no_data';
	}
	
	// 種目一覧を取得
	$listArray['item_list'] = getItemList();
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

	// LM+LSをitem_listに追加
	array_splice($listArray['item_list'], 3, 0, array(array('value'=>'LM+LS',  'name'=>'LM+LS', 'unit'=>'台')));


	// 提携企業一覧を取得
	if (isset($postArray['fiscal_year'])) {
		if ($postArray['item'] === "LM+LS") {
			// 種目がLM+LSの場合は、提携企業にLMとLSのものを挿入する
			$listArray['partner_list'] = getPartnerList($postArray['fiscal_year'], "LM");
			$listArray['partner_list'] = array_merge($listArray['partner_list'], getPartnerList($postArray['fiscal_year'], "LS"));
		}
		else {
			$listArray['partner_list'] = getPartnerList($postArray['fiscal_year'], $postArray['item']);
		}
	}
	else {
		//$listArray['partner_list'] = getPartnerList(getCurrentFiscalYear(), $postArray['item']);
		$listArray['partner_list'] = getPartnerList($listArray['fiscal_year_list'][0]['value'], $postArray['item']);
	}

	if (count($listArray['partner_list']) == 0) {
		return 'no_partner';
	}
	elseif (count($listArray['partner_list']) > 0 && $postArray['item'] !== 'ALL') {
		// 1社以上ある場合は先頭に合計を追加
		array_unshift($listArray['partner_list'], array('value'=>'TOTAL', 'name'=>'合計'));
	}
	elseif ($postArray['item'] === 'ALL') {
		array_unshift($listArray['partner_list'], array('value'=>'NONE', 'name'=>'----'));
	}
	
	// 表示ボタンを押された場合は、データを読み込み
	if (isset($postArray['command'])) {
	
		// 全体で最も新しい更新時間を取得
		$listArray['update_time']['ALL'] = getUpdateTime($postArray['fiscal_year']);

		// 地区一覧を取得
		$listArray['area_list'] = getAreaList('include-honbu');

		// 年間計画でジャックスを選択した時はジャックスの1/2も一緒に、オリコならオリコの1/2も一緒に表示する
		if (local_config::FEATURE_SELECT_LL_PRINT_TOTAL && $postArray['item'] === 'LL' && $postArray['partner'] !== 'TOTAL') {
			$copyArray['post'] = $postArray;	// LL用にコピーする
			$copyArray['list'] = $listArray;	// LL用にコピーする
			$copyArray['ll_total'] = $listArray;	// LL用にコピーする

			// 現在選択している提携から関連する提携を探す
			$key1 = "";
			$key2 = "";
			foreach ($listArray['partner_list'] as $partnerArray) {
				if ($postArray['partner'] === $partnerArray['value']) {
					// 現在のローン会社を判定する
					if(strpos($partnerArray['name'],'ジャックス') !== false || strpos($partnerArray['name'],'ｼﾞｬｯｸｽ') !== false || strpos($partnerArray['name'],'ロートピア/J') !== false){
						$key1 = "ジャックス";
						$key2 = "ｼﾞｬｯｸｽ";
						$key3 = "ロートピア/J";
					}
					else {
						$key1 = "オリ";
						$key2 = "ｵﾘ";
						$key3 = "ロートピア/O";
					}
					break;
				}
			}
			// 対になるローン会社を判定
			foreach ($listArray['partner_list'] as $partnerArray) {
				if ($postArray['partner'] != $partnerArray['value'] && (strpos($partnerArray['name'], $key1) !== false || strpos($partnerArray['name'],$key2) !== false || strpos($partnerArray['name'],$key3) !== false )) {
					//echo '対==='.$partnerArray['name'].'<br />';
					$copyArray['post']['partner'] = $partnerArray['value'];

					$copyArray['post']['tui_partner'] = $partnerArray['name'];	// 対になるパートナー名を保存
				}
			}

			//$copyArray['post']['partner'] = 'TOTAL';
		} // FEATURE_SELECT_LL_PRINT_TOTAL

		//--------------------------------------------
		// 種目毎の情報取得
		//--------------------------------------------
		foreach ($listArray['item_list'] as $itemListArray) {
			if ($itemListArray['value'] === 'ALL' ) {
				continue;
			}
			
			// 各種目毎の更新時間を取得
			$listArray['update_time'][$itemListArray['value']] = getUpdateTime($postArray['fiscal_year'], $itemListArray['value'], $postArray['partner']);
			if (local_config::FEATURE_SELECT_LL_PRINT_TOTAL && $postArray['item'] === 'LL' && $postArray['partner'] !== 'TOTAL') {
				$copyArray['list']['update_time'][$itemListArray['value']] = $listArray['update_time'][$itemListArray['value']];
			}
			
			// 補助種目は計算しない
			//if (strpos($itemListArray['value'], ':sub_') !== false) {
				//continue;
			//}
			
			// 種目毎の合計値取得
			// 地域は%を指定することで全地域を指定
			if ($postArray['item'] === "LM+LS" && $itemListArray['value'] === "LM+LS" ) {
				
				// 種目を格納する
				$listArray['data_list']["LM+LS"]['ALL'][0]['item'] = 'LM+LS'; 
				$listArray['data_list']["LM+LS"]['ALL_ONLY_ENTERABLE'][0]['item'] = 'LM+LS'; 

				// LM+LSの場合は、LMとLSの結果を合算する
				for ($i = 1; $i <= 12; $i++) {
					$listArray['data_list']["LM+LS"]['ALL'][0][$i.'_plan']   = $listArray['data_list']['LM']['ALL'][0][$i.'_plan']   + $listArray['data_list']['LS']['ALL'][0][$i.'_plan'];
					$listArray['data_list']["LM+LS"]['ALL'][0][$i.'_result'] = $listArray['data_list']['LM']['ALL'][0][$i.'_result'] + $listArray['data_list']['LS']['ALL'][0][$i.'_result'];

					$listArray['data_list']["LM+LS"]['ALL_ONLY_ENTERABLE'][0][$i.'_plan']   = $listArray['data_list']['LM']['ALL_ONLY_ENTERABLE'][0][$i.'_plan']   + $listArray['data_list']['LS']['ALL_ONLY_ENTERABLE'][0][$i.'_plan'];
					$listArray['data_list']["LM+LS"]['ALL_ONLY_ENTERABLE'][0][$i.'_result'] = $listArray['data_list']['LM']['ALL_ONLY_ENTERABLE'][0][$i.'_result'] + $listArray['data_list']['LS']['ALL_ONLY_ENTERABLE'][0][$i.'_result'];
				}
			}
			else {
				// LM+LS以外の場合
				$listArray['data_list'][$itemListArray['value']]['ALL']
					= getAreaTotalValue($postArray['partner'], $itemListArray['value'], '%', $postArray['fiscal_year']);

				// 種目毎の合計値取得、地域は%を指定することで全地域を指定（※キャンペーン参加同友だけで取得）
				$listArray['data_list'][$itemListArray['value']]['ALL_ONLY_ENTERABLE']
					= getAreaTotalValue($postArray['partner'], $itemListArray['value'], '%', $postArray['fiscal_year'], true);
			}
	
			// 年間計画でジャックスを選択した時はジャックスの1/2も一緒に、オリコならオリコの1/2も一緒に表示する
			if (local_config::FEATURE_SELECT_LL_PRINT_TOTAL && $postArray['item'] === 'LL' && $postArray['partner'] !== 'TOTAL') {

				$copyArray['list']['data_list'][$itemListArray['value']]['ALL']
				= getAreaTotalValue($copyArray['post']['partner'], $itemListArray['value'], '%', $postArray['fiscal_year']);

				// LLの選択した提携の両方の合計を取得
				$pidpattern = $postArray['partner'].' OR d.partnerID='.$copyArray['post']['partner'];
				$copyArray['ll_total']['data_list'][$itemListArray['value']]['ALL']
				= getAreaTotalValue($pidpattern, $itemListArray['value'], '%', $postArray['fiscal_year']);

				$copyArray['list']['data_list'][$itemListArray['value']]['ALL_ONLY_ENTERABLE']
				= getAreaTotalValue($copyArray['post']['partner'], $itemListArray['value'], '%', $postArray['fiscal_year'], true);

				// LLの選択した提携の両方の合計を取得
				$pidpattern = $postArray['partner'].' OR d.partnerID='.$copyArray['post']['partner'];	// 対となる提携会社を連結
				$copyArray['ll_total']['data_list'][$itemListArray['value']]['ALL_ONLY_ENTERABLE']
				= getAreaTotalValue($pidpattern, $itemListArray['value'], '%', $postArray['fiscal_year'], true);
			}
		}
		
		//printArray($listArray['update_time']);	// デバッグ用
		
		//--------------------------------------------
		// 地区毎、種目毎に集計
		//--------------------------------------------
		foreach ($listArray['area_list'] as $areaListArray) {
			// 全種目で全体値、地域毎の月別の計画、実績の合計値を取得する
			foreach ($listArray['item_list'] as $itemListArray) {
				if ($itemListArray['value'] === 'ALL') {
					continue;
				}

				// 実績の合計値取得
				if ($postArray['item'] === "LM+LS" && $itemListArray['value'] === "LM+LS" ) {
					// 種目を格納する
					$listArray['data_list']['LM+LS'][$areaListArray['value']][0]['item'] = 'LM+LS'; 

					// LM+LSの場合は、LMとLSの結果を合算する
					for ($i = 1; $i <= 12; $i++) {
						$listArray['data_list']['LM+LS'][$areaListArray['value']][0][$i.'_plan']   = $listArray['data_list']['LM'][$areaListArray['value']][0][$i.'_plan']   + $listArray['data_list']['LS'][$areaListArray['value']][0][$i.'_plan'];
						$listArray['data_list']['LM+LS'][$areaListArray['value']][0][$i.'_result'] = $listArray['data_list']['LM'][$areaListArray['value']][0][$i.'_result'] + $listArray['data_list']['LS'][$areaListArray['value']][0][$i.'_result'];
					}
				}
				else {
					// LM+LS以外の場合
					$listArray['data_list'][$itemListArray['value']][$areaListArray['value']]
						= getAreaTotalValue($postArray['partner'], $itemListArray['value'], $areaListArray['value'], $postArray['fiscal_year']);
				}
				
				// 年間計画でジャックスを選択した時はジャックスの1/2も一緒に、オリコならオリコの1/2も一緒に表示する
				if (local_config::FEATURE_SELECT_LL_PRINT_TOTAL && $postArray['item'] === 'LL' && $postArray['partner'] !== 'TOTAL') {
					$copyArray['list']['data_list'][$itemListArray['value']][$areaListArray['value']]
					= getAreaTotalValue($copyArray['post']['partner'], $itemListArray['value'], $areaListArray['value'], $postArray['fiscal_year']);

					// LLの選択した提携の両方の合計を取得
					$pidpattern = $postArray['partner'].' OR d.partnerID='.$copyArray['post']['partner'];
					$copyArray['ll_total']['data_list'][$itemListArray['value']][$areaListArray['value']]
					= getAreaTotalValue($pidpattern, $itemListArray['value'], $areaListArray['value'], $postArray['fiscal_year']);
				}
			}
		}
		
		//--------------------------------------------
		// 各地区の同友会社の月別の実績を取得する
		//--------------------------------------------
		if ($postArray['item'] !== 'ALL') {
			foreach ($listArray['area_list'] as $areaListArray) {

				if ($postArray['item'] === "LM+LS") {
					// LM+LSの場合は、LMとLSの合計値を取得する
					$listArray['data_list']['executive'][$areaListArray['value']]
						= getExecutiveResultTotalValue($postArray['fiscal_year'], 'LM', $areaListArray['value'], $postArray['partner']);
					$lsData
						= getExecutiveResultTotalValue($postArray['fiscal_year'], 'LS', $areaListArray['value'], $postArray['partner']);

					// ベースとなるLMに対してLSを加算する
					for ($i = 0; $i < count($listArray['data_list']['executive'][$areaListArray['value']]); $i++) {
						for ($m = 1; $m <= 12; $m++) {
							$listArray['data_list']['executive'][$areaListArray['value']][$i][$m.'_plan']   += $lsData[$i][$m.'_plan'];
							$listArray['data_list']['executive'][$areaListArray['value']][$i][$m.'_result'] += $lsData[$i][$m.'_result'];
						}
					}
				}
				else {
					// LM+LS以外の場合
					$listArray['data_list']['executive'][$areaListArray['value']]
						= getExecutiveResultTotalValue($postArray['fiscal_year'], $postArray['item'], $areaListArray['value'], $postArray['partner']);
				}

				//DBGMSG('item='.$postArray['item']);
				//DBGMSG('partner='.$postArray['partner']);
			}
		}
		
		// デバッグ用
		//echo "<pre>";
		//print_r($listArray['partner_list']);
		//print_r($listArray['data_list']);
		//echo "</pre>";
		
	}
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makePlanInputPage()
 * 計画を入力するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makePlanInputPage(&$postArray, &$listArray) {

	$page = 'input';
	
	//echo 'makePlanInputPage<br />';
	
	// selectboxの選択チェック
	if (isset($postArray['command'])) {
		// 種目未選択の場合
		if ($postArray['item'] === 'NONE') {
			return 'error_unselect_item';
		}
	}

	// ログインユーザ情報を取得
	$userInfo = getUserInfo($_SESSION['USERID']);
	
	// 年度一覧を取得
	if ($userInfo['auth'] == config::USER_ADMIN) {
		$listArray['fiscal_year_list'] = getFiscalYearList();
	}
	else {
		$listArray['fiscal_year_list'] = getOpenFiscalYearList();
	}
	if (count($listArray['fiscal_year_list']) == 0) {
		return 'no_data';
	}

	// 今月を取得
	if (!isset($postArray['month'])) {
		$postArray['month'] = getCurrentMonth();
	}

	// 種目一覧を取得
	if ($userInfo['auth'] == config::USER_ADMIN) {
		$listArray['item_list'] = getItemList('non-select');
	}
	else {
		$listArray['item_list'] = getItemList('no-insert', $userInfo['item']);
	}
	if (count($listArray['item_list']) == 0) {
		return 'no_item';
	}

	// 補助種目一覧を取得し、メイン種目と結合
	$subitemList = getSubItemList('no-insert');
	if (count($subitemList) > 0) {
		if ($userInfo['auth'] == config::USER_ADMIN) {
			$listArray['item_list'] = array_merge($listArray['item_list'], $subitemList);	// adminは全種目
		}
		else {
			// ユーザに関連するitemだけマージ
			foreach ($subitemList as $subitemArray) {
				if ($subitemArray['mainitem'] === $userInfo['item']) {
					//echo $subitemArray['mainitem'];
					$listArray['item_list'][] = array('value' => $subitemArray['value'],
													  'name'  => $subitemArray['name'],
													  'unit'  => $subitemArray['unit']);
				}
			}
		}
	}

	if (local_config::FEATURE_LC_HOLD_NUMBER) {
		// 特殊な種目一覧を取得し、メイン種目と結合
		$spitemList = getSpecialItemList();
		if (count($spitemList) > 0) {
			if ($userInfo['auth'] == config::USER_ADMIN) {
				// adminは全部の特殊種目
				$listArray['item_list'] = array_merge($listArray['item_list'], $spitemList);
			}
			else {
				// ユーザに関連するitemだけマージ
				foreach ($spitemList as $spitemArray) {
					if (strpos($spitemArray['value'], $userInfo['item']) !== false) {
						//echo $subitemArray['mainitem'];
						$listArray['item_list'][] = array('value' => $spitemArray['value'],
														'name'  => $spitemArray['name'],
														'unit'  => $spitemArray['unit']);
					}
				}
			}
		}
	}

	// 提携企業一覧を取得
	if (isset($postArray['fiscal_year'])) {
		$listArray['partner_list'] = getPartnerList($postArray['fiscal_year'], $postArray['item'], $userInfo['id']);
	}
	else {
		//$listArray['partner_list'] = getPartnerList(getCurrentFiscalYear(), $postArray['item'], $userInfo['id']);
		if ($userInfo['auth'] == config::USER_ADMIN) {
			$listArray['partner_list'][] = array('value' => 'NONE', 'name' => '----');
		}
		else {
			// 提携企業の場合は自分だけを表示
			$listArray['partner_list'] = getPartnerList($listArray['fiscal_year_list'][0]['value'], $listArray['item_list'][0]['value'], $userInfo['id']);
		}
	}
	
	if (count($listArray['partner_list']) == 0) {
		return 'no_partner';
	}

	// 保存ボタン押下時：データベースに実績を保存
	if (isset($postArray['command']) && $postArray['command'] === 'save' && $postArray['item'] !== 'NONE') {
		if (writeExecutiveResult($postArray) != 'success'){
			return 'db_write_fail';
		}
	}

	// 表示ボタン押下時：同友会社の計画、実績データを読み込み
	if (isset($postArray['command'])  && $postArray['item'] !== 'NONE') {
		
		// ロック状態を取得
		if ($userInfo['auth'] == config::USER_ADMIN) {
			$listArray['data_list']['lock'] = config::STATUS_DATA_UNLOCK;	// 管理者はロックは無関係
		}
		else {
			$listArray['data_list']['lock'] = getLockStatus($postArray['fiscal_year'], $postArray['month'], $postArray['partner']);
		}
		
		// 地区一覧を取得
		$listArray['area_list'] = getAreaList('include-honbu');

		// 地区毎の同友の実績値を取得
		foreach ($listArray['area_list'] as $areaListArray) {

			// 補助種目、特殊種目の場合
			if (strpos($postArray['item'], ':sub_') !== false || strpos($postArray['item'], ':spitem') !== false) {
				$listArray['data_list']['executive'][$areaListArray['value']]
					= getExecutiveResultValue($postArray['partner'], $areaListArray['value'], $postArray['fiscal_year'], $postArray['item']);
			}
			// メイン種目の場合
			else {
				$listArray['data_list']['executive'][$areaListArray['value']]
					= getExecutiveResultValue($postArray['partner'], $areaListArray['value'], $postArray['fiscal_year']);
			}
		}
	}
		
	return $page;
}

/**
 * ----------------------------------------------------------
 * makePlanLockPage()
 * 実績入力締めページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makePlanLockPage(&$postArray, &$listArray) {

	$page = 'lock';
	
	//echo 'makePlanLockPage<br />';

	// selectboxの選択チェック
	if (isset($postArray['command'])) {
		// 種目未選択の場合
		if ($postArray['item'] === 'NONE') {
			return 'error_unselect_item';
		}
	}

	// 年度一覧を取得
	$listArray['fiscal_year_list'] = getFiscalYearList();
	if (count($listArray['fiscal_year_list']) == 0) {
		return 'no_data';
	}

	// 今月を取得
	if (!isset($postArray['month'])) {
		$postArray['month'] = getCurrentMonth();
	}
	
	// 種目一覧を取得
	$listArray['item_list'] = getItemList('non-select');
	if (count($listArray['item_list']) == 0) {
		return 'no_item';
	}

	// 提携企業一覧を取得
	if (isset($postArray['fiscal_year'])) {
		$listArray['partner_list'] = getPartnerList($postArray['fiscal_year'], $postArray['item']);
	}
	else {
		//$listArray['partner_list'] = getPartnerList(getCurrentFiscalYear(), $postArray['item']);
		$listArray['partner_list'][] = array('value' => 'NONE', 'name' => '----');
	}
	if (count($listArray['partner_list']) == 0) {
		return 'no_partner';
	}

	// 実績締めボタン押下時：データベースにロック情報を保存
	if (isset($postArray['command']) && isset($postArray['lock']) && $postArray['item'] !== 'NONE') {
		if (writeDataLockInfo($postArray) != 'success'){
			return 'db_write_fail';
		}
	}
	
	// 実績確認ボタン押下時：同友会社の計画、実績データを読み込み
	if (isset($postArray['command']) &&  $postArray['item'] !== 'NONE') {

		// ロック状態を取得
		$listArray['data_list']['lock'] = getLockStatus($postArray['fiscal_year'], $postArray['month'], $postArray['partner']);
		
		// 地区一覧を取得
		$listArray['area_list'] = getAreaList('include-honbu');

		// 地区毎の同友会社一覧を取得
		foreach ($listArray['area_list'] as $areaListArray) {
			$listArray['data_list']['executive'][$areaListArray['value']]
				= getExecutiveResultValue($postArray['partner'], $areaListArray['value'], $postArray['fiscal_year']);
		}
	}

	return $page;
}

/**
 * ----------------------------------------------------------
 * makePlanMakePage()
 * 計画作成ページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makePlanMakePage(&$postArray, &$listArray) {

	$page = 'make';
	
	//echo 'makePlanMakePage<br />';

	// selectboxの選択チェック
	if (isset($postArray['command'])) {
		// 種目未選択の場合
		if ($postArray['item'] === 'NONE') {
			return 'error_unselect_item';
		}
	}

	// 年度一覧を取得
	$listArray['fiscal_year_list'] = getFiscalYearList();
	if (count($listArray['fiscal_year_list']) == 0) {
		return 'no_data';
	}

	// 今月を取得
	if (!isset($postArray['month'])) {
		$postArray['month'] = getCurrentMonth();
	}

	// 種目一覧を取得
	$listArray['item_list'] = getItemList('non-select');
	if (count($listArray['item_list']) == 0) {
		return 'no_item';
	}

	// 提携企業一覧を取得
	if (isset($postArray['fiscal_year'])) {
		$listArray['partner_list'] = getPartnerList($postArray['fiscal_year'], $postArray['item']);
	}
	else {
		//$listArray['partner_list'] = getPartnerList(getCurrentFiscalYear(), $postArray['item']);
		$listArray['partner_list'][] = array('value' => 'NONE', 'name' => '----');
	}
	if (count($listArray['partner_list']) == 0) {
		return 'no_partner';
	}

	// 保存ボタン押下時：データベースに計画を保存
	if (isset($postArray['command']) && $postArray['command'] === 'save' && $postArray['item'] !== 'NONE') {
		if (writeExecutivePlan($postArray) != 'success'){
			return 'db_write_fail';
		}
	}

	// 表示ボタン押下時：同友会社の計画、実績データを読み込み
	if (isset($postArray['command'])  && $postArray['item'] !== 'NONE') {
		
		// ロック状態を取得
		if (local_config::$FLAG_SEPARATE_PARTNER_PLAN) {
			$listArray['data_list']['lock'] = getLockStatus($postArray['fiscal_year'], $postArray['month'], $postArray['partner']);
		}
		else {
			$listArray['data_list']['lock'] = config::STATUS_DATA_UNLOCK;	// 統合版は常にアンロック
		}
		
		// 地区一覧を取得
		$listArray['area_list'] = getAreaList('include-honbu');

		// 地区毎の同友会社一覧を取得
		foreach ($listArray['area_list'] as $areaListArray) {
			//----------------------------------------
			// 指定年度のデータを取得
			//----------------------------------------
			$listArray['data_list']['executive'][$areaListArray['value']]
				= getExecutiveResultValue($postArray['partner'], $areaListArray['value'], $postArray['fiscal_year']);
			
			//----------------------------------------
			// 指定年度の前年の同月のデータを取得
			//----------------------------------------
			// 結合する種目かどうかを判定し、結合する場合は実績に関連する提携企業のpidを取得してSQL用に結合してから関数に渡す
			$partner_list = "";
			if (in_array($postArray['item'], local_config::PLAN_ITEM_COMBINE)) {
				// 提携企業の実績を合算して表示する場合
				$partnerArray = getPartnerList($postArray['fiscal_year'], $postArray['item']);
				//printArray($partnerArray);
				for ($i = 0; $i < count($partnerArray); $i++) {
					if ($i == 0) { $partner_list = $partnerArray[$i]['value']; }				// 異界メ
					else { $partner_list .= " OR d.partnerID = ".$partnerArray[$i]['value']; }
				}
			}
			else {
				$partner_list = $postArray['partner'];	// 提携企業の実績を個別で表示する場合
			}
			//echo "partner_list=".$partner_list."<br>";

			$listArray['data_list']['executive_lastyear'][$areaListArray['value']]
				= getExecutiveResultValue($partner_list, $areaListArray['value'], $postArray['fiscal_year']-1);
		}
	}
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makePlanNewPage()
 * 計画を新規作成するページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makePlanNewPage($postArray, &$listArray) {
	
	$ret = 'success';
	$page = 'new';
	
	//echo 'makePlanNewPage<br />';
	
	$makeStatus    = '';
	$makeButton    = '';
	$makeCommand   = '';
	$openStatus    = '';
	$openButton    = '';
	$openCommand   = '';
	$deleteButton  = '';
	$deleteCommand = '';
	$deleteScript  = '';
	
	// コマンド実行時
	// 年度のデータを保存するためのテーブルを提携、同友の組み合わせで生成
	if (isset($postArray['command'])) {
		switch ($postArray['command']) {
			case 'create':
				$ret = createData($postArray['fiscal_year']);
				if ($ret === 'success') {
					$ret = createInputExcelFile($postArray['fiscal_year']);	// 計画一括入力用Excelファイル生成
				}

				break;
			case 'delete':
				$ret = deleteData($postArray['fiscal_year']);
				break;
			case 'open':
				$ret = openData($postArray['fiscal_year']);
				break;
			case 'close':
				$ret = closeData($postArray['fiscal_year']);
				break;
			case 'input':
				$ret = inputAllMonthPlan($postArray['fiscal_year']);		// 計画一括入力
				break;
			case 'min_edit':
				$ret = makePlanBranchPrizeSettingPage($postArray, $listArray);
				break;
			default:
				$ret = 'fail';
				break;
		}
		if ($ret !== 'success') {
			return $ret;
		}
	}
	
	// 今年度を取得
	//$current = getCurrentFiscalYear()-1;
	$current = getCurrentFiscalYear()-5;
	
	// 計画作成済み年度を取得
	$makeYearList = getFiscalYearList();

	// 計画公開済み年度を取得
	$openYearList = getOpenFiscalYearList();

	// 今年度を含め5年分の計画を作成
	for ($i = 0; $i < config::MAKE_PLAN_NUM_MAX; $i++) {
	
		// 作成状態をチェック
		$match = false;
		foreach ($makeYearList as $makeYearArray) {
			if ($makeYearArray['value'] == $current + $i) {
				$match = true;
				break;
			}
		}
		if ($match) {
			$makeStatus  = '作成済み';
			$makeButton  = '再構成';
			$makeCommand = 'create';
			$makeScript  = 'return checkMessage(\'データを再構成しますか？\n計画作成後に追加した同友を追加することができます。\');';
			$deleteButton  = '削除';
			$deleteCommand = 'delete';
			$deleteScript  = 'return checkMessage(\'データを削除しますか？\n※削除したデータは復元できません。\');';
			$minTargetEditButton = '編集';
			$minTargetEditCommand  = 'min_edit';
		}
		else {
			$makeStatus  = '未作成';
			$makeButton  = '作成';
			$makeCommand = 'create';
			$makeScript  = 'return checkMessage(\'データを作成しますか？\');';
		}
		
		// 公開状態をチェック
		$match = false;
		foreach ($openYearList as $openYearArray) {
			if ($openYearArray['value'] == $current + $i) {
				$match = true;
				break;
			}
		}
		if ($match) {
			$openStatus  = '公開済み';
			$openButton  = '公開取消';
			$openCommand = 'close';
		}
		else {
			$openStatus  = '未公開';
			$openButton  = '公開';
			$openCommand = 'open';
		}
		
		// データ作成
		$listArray['data_list'][] = array(
			'year' => $current + $i,
			'make_status'    => $makeStatus,
			'make_button'    => $makeButton,
			'make_command'   => $makeCommand,
			'make_script'    => $makeScript,
			'open_status'    => $openStatus,
			'open_button'    => $openButton,
			'open_command'   => $openCommand,
			'delete_button'  => $deleteButton,
			'delete_command' => $deleteCommand,
			'delete_script'  => $deleteScript,
			'min_target_edit_button' => $minTargetEditButton,
			'min_target_edit_command' => $minTargetEditCommand,
		);
	}
		
	return $page;
}

/**
 * ----------------------------------------------------------
 * makePlanBranchPrizeSettingPage()
 * 支部獲得賞金の設定ページ
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return $page：表示するページ種別
 * ----------------------------------------------------------
 */
function makePlanBranchPrizeSettingPage($postArray, &$listArray) {
	
	$ret = 'success';
	$page = '';

	// 保存する時
	if (isset($postArray['save'])) {
		//DBGMSG("makePlanBranchPrizeSettingPage save");
		// 同友最低販売基準値を保存
		if (writeExecutiveMinTargetInfo($postArray) != 'success'){
			return 'db_write_fail';
		}

		// 同友への販促費設定を保存する
		if (local_config::FEATURE_SALES_PROMOTION_FOR_EXECUTIVE) {
			if (writeExecutivePromotionInfo($postArray) != 'success'){
				return 'db_write_fail';
			}
		}

		// 支部への販促費設定を保存する
		if (local_config::FEATURE_SALES_PROMOTION_FOR_BRANCH) {
			if (writeBranchPromotionInfo($postArray, 'ryoritsuB') != 'success'){	// 四半期
				return 'db_write_fail';
			}
			if (writeBranchPromotionInfo($postArray, 'ryoritsuC') != 'success'){	// キャンペーン賞金
				return 'db_write_fail';
			}
			if (writeBranchPromotionInfo($postArray, 'ryoritsuY') != 'success'){	// 年間賞金
				return 'db_write_fail';
			}
			if ( writeOtherBranchPromotionInfo($postArray) != 'success'){			// 販売施策のLOボーナス賞金、LH自動車特別賞、生産性＆ボリューム報奨金
				return 'db_write_fail';
			}			
		}

		// >LC獲得推進費、LC保有支援金をを保存する
		if (local_config::FEATURE_LC_HOLD_NUMBER) {
			if (writeLCHoldPromotionInfo($postArray) != 'success'){
				return 'db_write_fail';
			}
		}

		return $ret; 
	}

	// 全種目を取得(LM+LS対策：2015年以降はLM+LSを表示、LC削除)
	$listArray['item_list'] = getItemList('no-insert','',$postArray['fiscal_year'],true,false,true);
	if (count($listArray['item_list']) == 0) {
		return 'no_item';
	}
	//printArray($listArray['item_list']);

	// 同友最低販売基準値を取得
	$listArray['min_target'] = getExecutiveMinTargetInfo($postArray['fiscal_year']);
	if (count($listArray['min_target']) == 0) {
		return 'no_data';
	}
	//printArray($listArray['min_target']);

	// 同友への販促費を取得する
	if (local_config::FEATURE_SALES_PROMOTION_FOR_EXECUTIVE) {
		$listArray['executive_promotion'] = getExecutivePromotionInfo($postArray['fiscal_year']);
		if (count($listArray['executive_promotion']) == 0) {
			return 'no_data';
		}
	}
	//printArray($listArray['executive_promotion']);

	// 支部への販促費を取得する
	if (local_config::FEATURE_SALES_PROMOTION_FOR_BRANCH) {
		$listArray['branch_promotion'] = getBranchPromotionInfo($postArray['fiscal_year'], 'ryoritsuB');
		if (count($listArray['branch_promotion']) == 0) {
			return 'no_data';
		}
		$listArray['campaign_promotion'] = getBranchPromotionInfo($postArray['fiscal_year'], 'ryoritsuC');
		if (count($listArray['branch_promotion']) == 0) {
			return 'no_data';
		}
		$listArray['year_promotion'] = getBranchPromotionInfo($postArray['fiscal_year'], 'ryoritsuY');
		if (count($listArray['branch_promotion']) == 0) {
			return 'no_data';
		}
		$listArray['other_prize'] = getOtherBranchPromotionInfo($postArray['fiscal_year'], 'ryoritsuY');
		if (count($listArray['other_prize']) == 0) {
			return 'no_data';
		}
	}
	//printArray($listArray['branch_promotion']);
	//printArray($listArray['campaign_promotion']);
	//printArray($listArray['year_promotion']);
	//printArray($listArray['other_prize']);

	// LC獲得推進費と、LC保有支援金を取得する
	if (local_config::FEATURE_LC_HOLD_NUMBER) {
		$listArray['lc_hold_number'] = getLCHoldPromotionInfo($postArray['fiscal_year']);
		if (count($listArray['lc_hold_number']) == 0) {
			return 'no_data';
		}
	}
	//printArray($listArray['lc_hold_number']);

	return 'min_edit';
}

/**
 * ----------------------------------------------------------
 * printAllItemTotalValueTable()
 * 全種目を表示するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @param $start:開始カラム位置
 * @param $num：開始カラムからの表示個数
 * @return
 * ----------------------------------------------------------
 */
function printAllItemTotalValueTable($postArray, $listArray, $start, $num) {

	$colnum = $num * 3 + 1;
	
	// 見出しを表示(最初だけ表示)
	if ($start == 1) {
		echo '<tr><td class="bg_alizarin" colspan="'.$colnum.'">'
			.$postArray['fiscal_year'].'年度 種目別年間実績'.
			'&nbsp;&nbsp;[更新時間：'.$listArray['update_time']['ALL'].']'.
			'</td></tr>';
	}
	
	// 種目の項目を表示
	echo '<tr><td class="bg_wet_asphalt" style="width:60px;" rowspan="2">項目</td>';
		for ($i = $start; $i < $start+$num; $i++) {
			echo '<td class="bg_wet_asphalt" style="width:250px;" colspan="3">'.convertItemName($listArray['item_list'][$i]['value']).' (単位:'.$listArray['item_list'][$i]['unit'].')</td>';
		}
	echo '</tr>';
	
	// 種目毎の計画、実績、%を表示
	echo '<tr>';
		for ($i = $start; $i < $start+$num; $i++) {
			echo '<td class="bg_yellow">計画</td><td class="bg_red">実績</td><td class="bg_green">%</td>';
		}
	echo '</tr>';

	// 本部を最初に表示
	if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
		foreach ($listArray['area_list'] as $areaListArray) {
			if ($areaListArray['value'] === config::HEADOFFICE_NAME){
				echo '<tr><td>'.config::HEADOFFICE_NAME.'</td>';
				for ($item_num = $start; $item_num < $start+$num; $item_num++) {
					
					// 本部の実績には同友全体の実績を入れる
					if (!local_config::FEATURE_REMOVE_UNENTERABLE_EXECUTIVE_FROM_PLAN) {
						// 同友すべての合計
						for ($i = 1; $i <= 12; $i++) {
							$result = $i.'_result';
							$listArray['data_list'][$listArray['item_list'][$item_num]['value']][$areaListArray['value']][0][$result] = $listArray['data_list'][$listArray['item_list'][$item_num]['value']]['ALL'][0][$result];
						}
					}
					else {
						// 分母同友のみ
						for ($i = 1; $i <= 12; $i++) {
							$result = $i.'_result';
							$listArray['data_list'][$listArray['item_list'][$item_num]['value']][$areaListArray['value']][0][$result] = $listArray['data_list'][$listArray['item_list'][$item_num]['value']]['ALL_ONLY_ENTERABLE'][0][$result];
						}
					}
					// 表示
					printTotalValueTable($postArray, $listArray['data_list'][$listArray['item_list'][$item_num]['value']][$areaListArray['value']][0], 'year');
				}
				echo '</tr>';
			}	
		}
	}
	
	// 全体計を表示
	echo '<tr><td>全体計</td>';
		for ($i = $start; $i < $start+$num; $i++) {
			if (!local_config::FEATURE_REMOVE_UNENTERABLE_EXECUTIVE_FROM_PLAN) {
				// 同友すべての合計
				printTotalValueTable($postArray, $listArray['data_list'][$listArray['item_list'][$i]['value']]['ALL'][0], 'year');
			}
			else {
				// 分母同友のみ
				printTotalValueTable($postArray, $listArray['data_list'][$listArray['item_list'][$i]['value']]['ALL_ONLY_ENTERABLE'][0], 'year');
			}
		}
	echo '</tr>';
	
	// 一社平均を表示
	echo '<tr><td>一社平均</td>';
	for ($i = $start; $i < $start+$num; $i++) {
		printTotalValueTable($postArray, $listArray['data_list'][$listArray['item_list'][$i]['value']]['ALL'][0], 'year', $postArray['fiscal_year']);
	}
	echo '</tr>';

	// 地区毎の合計を表示
	foreach ($listArray['area_list'] as $areaListArray) {

		// 本部を表示しない
		if ($areaListArray['value'] === config::HEADOFFICE_NAME){
			continue;
		} 

		echo '<tr><td>'.$areaListArray['value'].'</td>';
		for ($i = $start; $i < $start+$num; $i++) {
			printTotalValueTable($postArray, $listArray['data_list'][$listArray['item_list'][$i]['value']][$areaListArray['value']][0], 'year');
		}
		echo '</tr>';
	}
}

/**
 * ----------------------------------------------------------
 * printOneItemTotalValueTable()
 * 種目ごとの実績を表すテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @param $itemListArray：種目
 * @param $tarm：期間
 * @return
 * ----------------------------------------------------------
 */
function printOneItemTotalValueTable($postArray, &$listArray, $itemListArray, $term) {

	$columnCnt = 22;	// 標準の列数
	$columnItem = 3;
	$columnWidth = 200;
	
	// 提携企業を選択しているかどうか
	if ((!local_config::$FLAG_SEPARATE_PARTNER_PLAN && $postArray['partner'] !== 'NONE' && $postArray['partner'] !== 'TOTAL') ||
		(($postArray['item'] === "LM" || $postArray['item'] === "LS") || strpos($postArray['item'], ':sub_') !== false || strpos($postArray['item'], ':spitem') !== false )) {
		if ($term === 'monthly_all') {
			$columnCnt = 13;	// 月別表示
		}
		else {
			$columnCnt = 8;		// 四半期表示
		}
		$columnItem = 1;
		$columnWidth = 100;
	}
	
	// 見出しを表示(最初だけ表示)
	if ($term === 'monthly_first' || $term === 'monthly_all' || $term === 'half_period' || $term === 'fourth_period') {
		echo '<tr><td  class="bg_belize_hole" colspan="'.$columnCnt.'">'.
			 $postArray['fiscal_year'].'年度 '.convertItemName($itemListArray['value']).'実績 (単位:'.$itemListArray['unit'].')'.
			 '&nbsp;&nbsp;[更新時間：'.$listArray['update_time'][$itemListArray['value']].']'.
			 '</td><tr>';
	}
	
	// 月を表示（半年ずつ）
	echo '<tr><td class="bg_wet_asphalt" style="width:60px;" rowspan="2">項目</td>';
	
	if ($term === 'half_period') {
		echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">第1四半期</td>';
		echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">第2四半期</td>';
		echo '<td class="bg_midnight" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">上半期</td>';
		echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">第3四半期</td>';
		echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">第4四半期</td>';
		echo '<td class="bg_midnight" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">下半期</td>';
		echo '<td class="bg_alizarin" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">年間</td>';
	}
	elseif ($term === 'fourth_period') {
		echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">4-7月期</td>';
		echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">8-11月期</td>';
		echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">12-3月期</td>';
		echo '<td class="bg_alizarin" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">年間</td>';
	}
	else {

		// ロック/アンロック情報を取得
		$openInfoList = getDataLockInfo($postArray['fiscal_year'], $itemListArray['value'], $postArray['partner']);
	
		if ($term === 'monthly_first') {
			for ($i = 4; $i < 10; $i++) {
				$openInfo = checkDataOpenStatus($openInfoList['total'], $openInfoList[$i.'_lock']);
				echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">'.$i.'月'.$openInfo.'</td>';
			}
		}
		else {
			if ($term === 'monthly_all') {
				for ($i = 4; $i < 13; $i++) {
					$openInfo = checkDataOpenStatus($openInfoList['total'], $openInfoList[$i.'_lock']);
					echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">'.$i.'月'.$openInfo.'</td>';
				}
			}
			else {
				// monthly_lastの場合
				for ($i = 10; $i < 13; $i++) {
					$openInfo = checkDataOpenStatus($openInfoList['total'], $openInfoList[$i.'_lock']);
					echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">'.$i.'月'.$openInfo.'</td>';
				}
			}
			for ($i = 1; $i < 4; $i++) {
				$openInfo = checkDataOpenStatus($openInfoList['total'], $openInfoList[$i.'_lock']);
				echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">'.$i.'月'.$openInfo.'</td>';
			}
		}
	}
	echo '</tr>';
	
	// 種目毎の計画、実績、%を表示
	if ($term === 'monthly_all') {
		$columnCnt = 12;
	}
	else if ($term === 'half_period') {
		$columnCnt = 7;
	}
	elseif ($term === 'fourth_period') {
		$columnCnt = 4;
	}
	else {
		$columnCnt = 6;
	}
	echo '<tr>';
	for ($i = 0; $i < $columnCnt; $i++) {
		if (!local_config::$FLAG_SEPARATE_PARTNER_PLAN && $postArray['partner'] !== 'NONE' && $postArray['partner'] !== 'TOTAL' ||
		   (($postArray['item'] === "LM" || $postArray['item'] === "LS") || strpos($postArray['item'], ':sub_') !== false || strpos($postArray['item'], ':spitem') !== false )) {
			echo '<td class="bg_red">実績</td>';
		}
		else {
			echo '<td class="bg_yellow">計画</td><td class="bg_red">実績</td><td class="bg_green">%</td>';
		}
	}
	echo '</tr>';

	// 本部を最初に表示
	if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
		foreach ($listArray['area_list'] as $areaListArray) {
			if ($areaListArray['value'] === config::HEADOFFICE_NAME){

				echo '<tr><td>'.config::HEADOFFICE_NAME.'</td>';
					
				// 本部の実績には同友全体の実績を入れる
				if (!local_config::FEATURE_REMOVE_UNENTERABLE_EXECUTIVE_FROM_PLAN) {
					// 同友すべての合計
					for ($i = 1; $i <= 12; $i++) {
						$result = $i.'_result';
						$listArray['data_list'][$itemListArray['value']][$areaListArray['value']][0][$result] = $listArray['data_list'][$itemListArray['value']]['ALL'][0][$result];
					}
				}
				else {
					// 分母同友のみ
					for ($i = 1; $i <= 12; $i++) {
						$result = $i.'_result';
						$listArray['data_list'][$itemListArray['value']][$areaListArray['value']][0][$result] = $listArray['data_list'][$itemListArray['value']]['ALL_ONLY_ENTERABLE'][0][$result];
					}
				}
				// 表示
				printTotalValueTable($postArray, $listArray['data_list'][$itemListArray['value']][$areaListArray['value']][0], $term);
				
				echo '</tr>';

			} // config::HEADOFFICE_NAME
		}
	}
	//printArray($listArray['data_list'][$itemListArray['value']]['ALL'][0]);
	//printArray($listArray['data_list']);

	// 全体合計を表示
	echo '<tr><td>全体計</td>';
		if (!local_config::FEATURE_REMOVE_UNENTERABLE_EXECUTIVE_FROM_PLAN) {
			printTotalValueTable($postArray, $listArray['data_list'][$itemListArray['value']]['ALL'][0], $term);
		}
		else {
			printTotalValueTable($postArray, $listArray['data_list'][$itemListArray['value']]['ALL_ONLY_ENTERABLE'][0], $term);
		}
	echo '</tr>';

	// 支部1社あたりの平均を表示
	echo '<tr><td>一社平均</td>';
		printTotalValueTable($postArray, $listArray['data_list'][$itemListArray['value']]['ALL'][0], $term, $postArray['fiscal_year']);
	echo '</tr>';
			
	// 地区毎の合計を表示
	foreach ($listArray['area_list'] as $areaListArray) {

		// 本部を表示しない
		if ($areaListArray['value'] === config::HEADOFFICE_NAME){
			continue;
		} 

		echo '<tr><td>'.$areaListArray['value'].'</td>';
		printTotalValueTable($postArray, $listArray['data_list'][$itemListArray['value']][$areaListArray['value']][0], $term);
		echo '</tr>';
	}
}

/**
 * ----------------------------------------------------------
 * printExecutiveResultValueTable()
 * 指定種目で同友会社の月毎の計画、実績を表示する
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @param $area：地域名
 * @param $tarm：期間
 * @return
 * ----------------------------------------------------------
 */
function printExecutiveResultValueTable($postArray, $listArray, $area, $term) {

	$columnCnt = 22;	// 標準の列数
	$columnItem = 3;
	$columnWidth = 200;
	
	// 提携企業を選択しているかどうか
	if (!local_config::$FLAG_SEPARATE_PARTNER_PLAN && $postArray['partner'] !== 'NONE' && $postArray['partner'] !== 'TOTAL' ||
		(($postArray['item'] === "LM" || $postArray['item'] === "LS") || strpos($postArray['item'], ':sub_') !== false || strpos($postArray['item'], ':spitem') !== false )) {
		if ($term === 'monthly_all') {
			$columnCnt = 13;
		}
		else {
			$columnCnt = 8;
		}
		$columnItem = 1;
		$columnWidth = 100;
	}

	// 見出しを表示(最初だけ表示)
	if ($term === 'monthly_first' || $term === 'monthly_all' || $term === 'half_period' || $term === 'fourth_period') {
		echo '<tr><td  class="bg_turquoise" colspan="'.$columnCnt.'">'.$area.'</td></tr>';
	}

	// 月を表示（半年ずつ）
	echo '<tr><td class="bg_wet_asphalt" style="width:180px;" rowspan="2">項目</td>';
	if ($term === 'half_period') {
		echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">第1四半期</td>';
		echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">第2四半期</td>';
		echo '<td class="bg_midnight" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">上半期</td>';
		echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">第3四半期</td>';
		echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">第4四半期</td>';
		echo '<td class="bg_midnight" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">下半期</td>';
		echo '<td class="bg_alizarin" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">年間</td>';
	}
	elseif ($term === 'fourth_period') {
		echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">4-7月期</td>';
		echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">8-11月期</td>';
		echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">12-3月期</td>';
		echo '<td class="bg_alizarin" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">年間</td>';
	}
	else {
		if ($term === 'monthly_first') {
			for ($i = 4; $i < 10; $i++) {
				$openInfo = checkDataOpenStatus($openInfoList['total'], $openInfoList[$i.'_lock']);
				echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">'.$i.'月'.$openInfo.'</td>';
			}
		}
		else {
			if ($term === 'monthly_all') {
				for ($i = 4; $i < 13; $i++) {
					$openInfo = checkDataOpenStatus($openInfoList['total'], $openInfoList[$i.'_lock']);
					echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">'.$i.'月'.$openInfo.'</td>';
				}
			}
			else {
				// monthly_lastの場合
				for ($i = 10; $i < 13; $i++) {
					$openInfo = checkDataOpenStatus($openInfoList['total'], $openInfoList[$i.'_lock']);
					echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">'.$i.'月'.$openInfo.'</td>';
				}
			}
			for ($i = 1; $i < 4; $i++) {
				$openInfo = checkDataOpenStatus($openInfoList['total'], $openInfoList[$i.'_lock']);
				echo '<td class="bg_wet_asphalt" style="width:'.$columnWidth.'px;" colspan="'.$columnItem.'">'.$i.'月'.$openInfo.'</td>';
			}
		}
	}
	echo '</tr>';

	// 種目毎の計画、実績、%を表示
	if ($term === 'monthly_all') {
		$columnCnt = 12;
	}
	else if ($term === 'half_period') {
		$columnCnt = 7;
	}
	elseif ($term === 'fourth_period') {
		$columnCnt = 4;
	}
	else {
		$columnCnt = 6;
	}
	echo '<tr>';
	for ($i = 0; $i < $columnCnt; $i++) {
		if (!local_config::$FLAG_SEPARATE_PARTNER_PLAN && $postArray['partner'] !== 'NONE' && $postArray['partner'] !== 'TOTAL' ||
		(($postArray['item'] === "LM" || $postArray['item'] === "LS") || strpos($postArray['item'], ':sub_') !== false || strpos($postArray['item'], ':spitem') !== false )) {
			echo '<td class="bg_red">実績</td>';
		}
		else {
			echo '<td class="bg_yellow">計画</td><td class="bg_red">実績</td><td class="bg_green">%</td>';
		}
	}
	echo '</tr>';
	
	// 同友会社の毎月の実績を表示
	//printArray($listArray['data_list']);
	foreach ($listArray['data_list']['executive'][$area] as $executiveArray) {
	
		// ログインしているユーザIDの行の背景色を変更
		if ($executiveArray['name'] === $_SESSION["USERNAME"]) {
			echo '<tr class="bg_skyblue">';
		}
		else {
			echo '<tr>';
		}

		// 本部の場合
		if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
			if ($area === config::HEADOFFICE_NAME) {
				$executiveArray = $listArray['data_list'][$postArray['item']][config::HEADOFFICE_NAME][0];
			}
		}

		// 同友の名前と実績を表示
		echo '<td class="left">'.$executiveArray['name'].'</td>';
		printTotalValueTable($postArray, $executiveArray, $term);
		echo '</tr>';
	}
}

/**
 * ----------------------------------------------------------
 * printTotalValueTable()
 * 月毎の合計値と、達成率を含む表を出力する
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $data：合計値データ
 * @param $tarm：期間
 * @param $fiscal_year：平均値を出す場合はセット
 * @return
 * ----------------------------------------------------------
 */
function printTotalValueTable($postArray, $data, $term, $fiscal_year=0) {

	$planTotal = 0;
	$resultTotal = 0;
	
	// 指定年度の同友数を取得
	if ($fiscal_year != 0) {
		$executiveCnt = count(getExecutiveList($fiscal_year, '', '%', ''));
		$decimals = 2;		// 達成率の小数点以下の桁数は2桁
	}
	else {
		$executiveCnt = 1;
		$decimals = 2;		// 小数点以下の桁数
	}
	
	// 年度の合計値を計算
	if ($term === 'year') {
		printSpecifiedPeriodValueTable($postArray, $data, $executiveCnt, $decimals, 4, 3);
		return;
	}
	
	//-------------------------------
	// 月別表示
	//--------------------------------
	if ($term === 'monthly_first') {
		// 4月～9月を表示
		for ($i = 4; $i < 10; $i++) {
			$plan = $i.'_plan';
			$result = $i.'_result';
			if (!local_config::$FLAG_SEPARATE_PARTNER_PLAN && $postArray['partner'] !== 'NONE' && $postArray['partner'] !== 'TOTAL' ||
			(($postArray['item'] === "LM" || $postArray['item'] === "LS") || strpos($postArray['item'], ':sub_') !== false || strpos($postArray['item'], ':spitem') !== false )) {
				echo '<td class="right">'.formatNumber($data[$result]/$executiveCnt, $decimals).'</td>';
			}
			else {
				echo '<td class="right">'.formatNumber($data[$plan]/$executiveCnt, $decimals).'</td>';
				echo '<td class="right">'.formatNumber($data[$result]/$executiveCnt, $decimals).'</td>';
				echo '<td class="right">'.formatNumber($data[$result]*100/$data[$plan], 1).'</td>';
			}
		}
		return;
	}
	elseif ($term === 'monthly_all' || $term === 'monthly_last') {
	
		if ($term === 'monthly_all') {
			// 4月～12月を表示
			for ($i = 4; $i < 13; $i++) {
				$plan = $i.'_plan';
				$result = $i.'_result';
				if (!local_config::$FLAG_SEPARATE_PARTNER_PLAN && $postArray['partner'] !== 'NONE' && $postArray['partner'] !== 'TOTAL' ||
				(($postArray['item'] === "LM" || $postArray['item'] === "LS") || strpos($postArray['item'], ':sub_') !== false || strpos($postArray['item'], ':spitem') !== false )) {
					echo '<td class="right">'.formatNumber($data[$result]/$executiveCnt, $decimals).'</td>';
				}
				else {
					echo '<td class="right">'.formatNumber($data[$plan]/$executiveCnt, $decimals).'</td>';
					echo '<td class="right">'.formatNumber($data[$result]/$executiveCnt, $decimals).'</td>';
					echo '<td class="right">'.formatNumber($data[$result]*100/$data[$plan], 1).'</td>';
				}
			}
		}
		else {
			// 10月～12月を表示
			for ($i = 10; $i < 13; $i++) {
				$plan = $i.'_plan';
				$result = $i.'_result';
				if (!local_config::$FLAG_SEPARATE_PARTNER_PLAN && $postArray['partner'] !== 'NONE' && $postArray['partner'] !== 'TOTAL' ||
				(($postArray['item'] === "LM" || $postArray['item'] === "LS") || strpos($postArray['item'], ':sub_') !== false || strpos($postArray['item'], ':spitem') !== false )) {
					echo '<td class="right">'.formatNumber($data[$result]/$executiveCnt, $decimals).'</td>';
				}
				else {
					echo '<td class="right">'.formatNumber($data[$plan]/$executiveCnt, $decimals).'</td>';
					echo '<td class="right">'.formatNumber($data[$result]/$executiveCnt, $decimals).'</td>';
					echo '<td class="right">'.formatNumber($data[$result]*100/$data[$plan], 1).'</td>';
				}
			}
		}
		// 1月～3月を表示
		for ($i = 1; $i < 4; $i++) {
			$plan = $i.'_plan';
			$result = $i.'_result';
			if (!local_config::$FLAG_SEPARATE_PARTNER_PLAN && $postArray['partner'] !== 'NONE' && $postArray['partner'] !== 'TOTAL' ||
			(($postArray['item'] === "LM" || $postArray['item'] === "LS") || strpos($postArray['item'], ':sub_') !== false || strpos($postArray['item'], ':spitem') !== false )) {
				echo '<td class="right">'.formatNumber($data[$result]/$executiveCnt, $decimals).'</td>';
			}
			else {
				echo '<td class="right">'.formatNumber($data[$plan]/$executiveCnt, $decimals).'</td>';
				echo '<td class="right">'.formatNumber($data[$result]/$executiveCnt, $decimals).'</td>';
				echo '<td class="right">'.formatNumber($data[$result]*100/$data[$plan], 1).'</td>';
			}
		}
	}
	
	//--------------------------------
	// 4ヶ月毎集計表示
	//--------------------------------
	elseif ($term === 'fourth_period') {
		printSpecifiedPeriodValueTable($postArray, $data, $executiveCnt, $decimals, 4, 7);		// 4-7月期
		printSpecifiedPeriodValueTable($postArray, $data, $executiveCnt, $decimals, 8, 11);		// 8-11月期
		printSpecifiedPeriodValueTable($postArray, $data, $executiveCnt, $decimals, 12, 3);		// 12-3月期
		printSpecifiedPeriodValueTable($postArray, $data, $executiveCnt, $decimals, 4, 3);		// 年間表示
	}
	
	//--------------------------------
	// 四半期別表示
	//--------------------------------
	elseif ($term === 'half_period') {
		printSpecifiedPeriodValueTable($postArray, $data, $executiveCnt, $decimals, 4, 6);		// 第1四半期表示
		printSpecifiedPeriodValueTable($postArray, $data, $executiveCnt, $decimals, 7, 9);		// 第2四期表示
		printSpecifiedPeriodValueTable($postArray, $data, $executiveCnt, $decimals, 4, 9);		// 上半期表示
		printSpecifiedPeriodValueTable($postArray, $data, $executiveCnt, $decimals, 10, 12);	// 第3四半期表示
		printSpecifiedPeriodValueTable($postArray, $data, $executiveCnt, $decimals, 1, 3);		// 第4四半期表示
		printSpecifiedPeriodValueTable($postArray, $data, $executiveCnt, $decimals, 10, 3);		// 下半期表示
		printSpecifiedPeriodValueTable($postArray, $data, $executiveCnt, $decimals, 4, 3);		// 年間表示
	}
	else {
		return;
	}
}

/**
 * ----------------------------------------------------------
 * printSpecifiedPeriodValueTable()
 * 指定期間の月の計画、実績を合計し、合計値と達成率を表示する
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $data：合計値データ
 * @param $executeCnt：同友会社数
 * @param $decimals：小数点以下の桁数
 * @param $start：開始月
 * @param $end：終了月
 * @return
 * ----------------------------------------------------------
 */
function printSpecifiedPeriodValueTable($postArray, $data, $executiveCnt, $decimals, $start, $end) {

	$planTotal = 0;
	$resultTotal = 0;

	// 指定期間の合計
	if ($start < $end) {
		// 年内
		for ($i = $start; $i <= $end; $i++) {
			$planTotal += $data[$i.'_plan'];		//
			$resultTotal += $data[$i.'_result'];
		}
	}
	else {
		// 年またぎ
		for ($i = $start; $i <= 12; $i++) {
			$planTotal += $data[$i.'_plan'];
			$resultTotal += $data[$i.'_result'];
		}
		for ($i = 1; $i <= $end; $i++) {
			$planTotal += $data[$i.'_plan'];
			$resultTotal += $data[$i.'_result'];
		}
	}
	
	// 表示
	if (!local_config::$FLAG_SEPARATE_PARTNER_PLAN && $postArray['partner'] !== 'NONE' && $postArray['partner'] !== 'TOTAL' ||
	(($postArray['item'] === "LM" || $postArray['item'] === "LS") || strpos($postArray['item'], ':sub_') !== false || strpos($postArray['item'], ':spitem') !== false )) {
		echo '<td class="right">'.formatNumber($resultTotal/$executiveCnt, $decimals).'</td>';	// 補助種目の時は実績のみ表示
	}
	else {
		echo '<td class="right">'.formatNumber($planTotal/$executiveCnt, $decimals).'</td>';	// 計画
		echo '<td class="right">'.formatNumber($resultTotal/$executiveCnt, $decimals).'</td>';	// 実績
		echo '<td class="right">'.formatNumber($resultTotal*100/$planTotal, 1, 'floor').'</td>';// 達成率
	}
}

/**
 * ----------------------------------------------------------
 * printExecutiveResultInputTable()
 * 指定種目で同友会社の月毎の実績を入力するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @param $area：地域名
 * @param $limit：読み込み専用/入力可能のフラグ
 * @return
 * ----------------------------------------------------------
 */
function printExecutiveResultInputTable($postArray, $listArray, $area, $limit='input') {

	// 本部の時は同友すべての実績の合計値を表示する
	if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
		$honbu_result = 0;
		if ($area === config::HEADOFFICE_NAME){
			foreach ($listArray['area_list'] as $areaListArray) {
				foreach ($listArray['data_list']['executive'][$areaListArray['value']] as $executiveArray) {
					$honbu_result += $executiveArray[$postArray['month'].'_result'];
				}
			}
		} 
	}

	// 見出しを表示(最初だけ表示)
	echo '<tr><td  class="bg_turquoise" colspan="3">'.$area.'</td></tr>';

	// 月を表示
	echo '<tr><td class="bg_wet_asphalt" style="width:200px;" rowspan="2">項目</td>';
	echo '<td class="bg_wet_asphalt" colspan="2">'.$postArray['month'].'月</td>';
	echo '</tr>';
	
	// 種目毎の計画、実績、%を表示
	echo '<tr>';
	echo '<td class="bg_yellow" style="width:120px;" >計画</td><td class="bg_red" style="width:120px;" >実績</td>';
	echo '</tr>';
	
	// 同友会社の指定月の計画、実績入力用のテキストボックスを表示
	foreach ($listArray['data_list']['executive'][$area] as $executiveArray) {
		$plan = $postArray['month'].'_plan';
		$result = $postArray['month'].'_result';
		
		echo '<tr>';
		echo '<td class="left" id="'.$executiveArray['code'].'">'.$executiveArray['name'].'</td>';
		echo '<td class="right">'.formatNumber($executiveArray[$plan], 2).'</td>';
		echo '<td class="right">';
		if ($limit === 'input') {
			printTextBox($executiveArray['eid'].':'.$postArray['partner'].':'.$result, 150, 'right', $executiveArray[$result]);
		}
		else {
			// 読み込み専用で表示
			if ($area === config::HEADOFFICE_NAME){
				echo formatNumber($honbu_result,2);
			}
			else {
				echo formatNumber($executiveArray[$result],2);
			}
		}
		echo '</td>';
		echo '</tr>';
	}
}

/**
 * ----------------------------------------------------------
 * printExecutivePlanInputTable()
 * 指定種目で同友会社の月毎の計画を入力するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @param $area：地域名
 * @param $limit：読み込み専用/入力可能のフラグ
 * @return
 * ----------------------------------------------------------
 */
function printExecutivePlanInputTable($postArray, $listArray, $area, $limit='input') {

	// 見出しを表示(最初だけ表示)
	echo '<tr><td  class="bg_turquoise" colspan="4">'.$area.'</td></tr>';

	// 月を表示
	echo '<tr><td class="bg_wet_asphalt" style="width:200px;" rowspan="2">項目</td>';
	echo '<td class="bg_wet_asphalt" colspan="2">前年同月</td>';
	echo '<td class="bg_wet_asphalt">'.$postArray['month'].'月</td>';
	echo '</tr>';
	
	// 種目毎の計画、実績、%を表示
	echo '<tr>';
	echo '<td class="bg_yellow" style="width:120px;" >計画</td><td class="bg_red" style="width:120px;" >実績</td>';
	echo '<td class="bg_alizarin" style="width:120px;" >今年度計画</td>';
	echo '</tr>';
	
	$plan = $postArray['month'].'_plan';
	$result = $postArray['month'].'_result';

	// 同友会社の指定月の計画、実績入力用のテキストボックスを表示
	//printArray($listArray['data_list']['executive'][$area]);
	foreach ($listArray['data_list']['executive'][$area] as $executiveArray) {

		echo '<tr>';
		echo '<td class="left">'.$executiveArray['name'].'</td>';
		
		// 前年同月のデータを表示
		$foundFlag = false;
		foreach ($listArray['data_list']['executive_lastyear'][$area] as $executiveLastyearArray) {
			// 同じ同友会社IDを持つデータを検索
			if ($executiveLastyearArray['eid'] == $executiveArray['eid']) {
				echo '<td class="right">'.formatNumber($executiveLastyearArray[$plan], 2).'</td>';
				echo '<td class="right">'.formatNumber($executiveLastyearArray[$result], 2).'</td>';
				$foundFlag = true;
				break;
			}
		}
		if ($foundFlag === false) {
			echo '<td class="right">0.00</td>';
			echo '<td class="right">0.00</td>';
		}
		
		// 今年度のデータ入力
		echo '<td class="right">';
		if ($limit === 'input') {
			printTextBox($executiveArray['eid'].':'.$postArray['partner'].':'.$plan, 150, 'right', $executiveArray[$plan]);
		}
		else {
			// 読み込み専用で表示
			echo formatNumber($executiveArray[$plan], 2);
		}
		echo '</td>';
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

<div id="contents_menu">

	<?php
	$userInfo = getUserInfo($_SESSION['USERID']);
	
	// 全ユーザー表示 ?>
	<?php if ($userInfo['user'] != "lh-t") { ?>
		<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'view') ?>"
		value="<?php echo '実績閲覧' ?>"
		onClick="location.href='./index.php?reg=plan&type=view'">
	<?php } // 東海は実績閲覧を非表示 ?>

	<?php
	// 管理者と役員と提携企業に表示
	if ($userInfo['auth'] == config::USER_ADMIN || $userInfo['auth'] == config::USER_EXEOFFICER || $userInfo['auth'] == config::USER_PARTNER) { ?>
	<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'input') ?>"
	value="<?php echo '実績入力' ?>"
	onClick="location.href='./index.php?reg=plan&type=input'">
	<?php } ?>
	
	<?php
	// 管理者のみ表示
	if ($userInfo['auth'] == config::USER_ADMIN) { ?>
	<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'lock') ?>"
	value="<?php echo '実績締め' ?>"
	onClick="location.href='./index.php?reg=plan&type=lock'">
	<?php } // USER_ADMIN ?>

	<?php
	// 管理者と役員のみ表示
	if ($userInfo['auth'] == config::USER_ADMIN || $userInfo['auth'] == config::USER_EXEOFFICER) { ?>
	<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'make') ?>"
	value="<?php echo '計画入力' ?>"
	onClick="location.href='./index.php?reg=plan&type=make'">
	<?php } // USER_ADMIN ?>

	<?php
	// 管理者のみ表示
	if ($userInfo['auth'] == config::USER_ADMIN) { ?>
	<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'new') ?>"
	value="<?php echo '計画作成' ?>"
	onClick="location.href='./index.php?reg=plan&type=new'">
	<?php } // USER_ADMIN ?>

</div>
<hr>

<?php
//---------------------------------------------
// 実績表示画面
//---------------------------------------------
if ($page === 'result') { ?>

<?php
// 全種目を選択している場合のみ実行
if ($postArray['item'] === 'ALL' || count($postArray) == 0) { ?>
<script type="text/javascript">
	window.onload=changeSelectBoxContents;
</script>
<?php } ?>

<div id="contents_select">
	<form id='result_view' method="POST" action="index.php?reg=plan&type=<?php echo $getArray['type']?>">
		<?php printSelectBox($listArray['fiscal_year_list'], 'fiscal_year', 70, $postArray['fiscal_year'], 'changeSelectBoxContents()') ?>年度
		<?php printSelectBox($listArray['item_list'], 'item', 100, $postArray['item'], 'changeSelectBoxContents()') ?>
		<?php printSelectBox($listArray['partner_list'], 'partner', 150, $postArray['partner']); ?>
		
		<?php
		if ($postArray['item'] === 'LC:spitem') {
			echo '<div style="display:none" name="period_btn">';
		}
		else {
			echo '<div style="display:inline" name="period_btn">';
		}
		?>
		<?php
			printSubmitButton('四半期表示', 'half_period');
			printSubmitButton('4ヶ月毎表示', 'fourth_period');
		?>
		</div>
		<?php
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
		if (isset($postArray['partner']) && $postArray['partner'] !== 'NONE' && $postArray['partner'] !== 'TOTAL') {
			$userInfo = getUserInfo('%', $postArray['partner']);
			$targetName = $userInfo['name'].'の';
		}
		else {
			if ($postArray['item'] === 'ALL') {
				$targetName = '全種目の';
			}
			else {
				//$targetName = $postArray['item'].'の';
				$targetName = convertItemName($postArray['item']).'の';
			}
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
	}
	?>

	<?php
	// 全種目を表示
	if (isset($postArray['command']) && $postArray['item'] === 'ALL') { ?>
		<table>
			<?php 
			printAllItemTotalValueTable($postArray, $listArray, 1, 5);	// LM, LS, LT, LH, LOを表示
			printAllItemTotalValueTable($postArray, $listArray, 6, 4);	// LO, LE, LL, LCを表示
			?>
		</table>
		<hr>
		
		<?php
		// 種目別の月毎の合計値を一覧表示
		unset($listArray['item_list'][0]);
		foreach ($listArray['item_list'] as $itemListArray) { 
			// 補助種目は全種目の選択時には表示しない
			if (strpos($itemListArray['value'], ':sub_') !== false || strpos($itemListArray['value'], ':spitem') !== false) {
				continue;
			}
			?>
			<table>
			<?php
			if (isset($postArray['monthly'])) {
				// 月別表示
				if (!local_config::$FLAG_SEPARATE_PARTNER_PLAN && $postArray['partner'] !== 'NONE' && $postArray['partner'] !== 'TOTAL' ||
				(($postArray['item'] === "LM" || $postArray['item'] === "LS") || strpos($postArray['item'], ':sub_') !== false || strpos($postArray['item'], ':spitem') !== false )) {
					printOneItemTotalValueTable($postArray, $listArray, $itemListArray, 'monthly_all');		// 4月～3月(12ヶ月分）
				}
				else {
					printOneItemTotalValueTable($postArray, $listArray, $itemListArray, 'monthly_first');	// 4～9月
					printOneItemTotalValueTable($postArray, $listArray, $itemListArray, 'monthly_last');	// 10～3月
				}
			}
			elseif (isset($postArray['fourth_period'])) {
				// 4ヶ月毎集計表示
				printOneItemTotalValueTable($postArray, $listArray, $itemListArray, 'fourth_period');
			}
			else {
				// 半期表示
				printOneItemTotalValueTable($postArray, $listArray, $itemListArray, 'half_period');
			}
			?>
			</table>
			<?php
			// 提携毎の更新時間を表示
			printPartnerInputTimeTable($postArray, $itemListArray['value']); ?>
			<hr>
		<?php }
	}
	
	// 種目別表示
	elseif (isset($postArray['command']) && $postArray['item'] !== 'ALL') { ?>
		<table>
		<?php
		// 指定された種目の実績の合計を表示
		foreach ($listArray['item_list'] as $itemListArray) {
			if ($itemListArray['value'] === $postArray['item']) {

				// 月別表示
				if (isset($postArray['monthly'])) {
					// 月別表示
					if (!local_config::$FLAG_SEPARATE_PARTNER_PLAN && $postArray['partner'] !== 'NONE' && $postArray['partner'] !== 'TOTAL' ||
					(($postArray['item'] === "LM" || $postArray['item'] === "LS") || strpos($postArray['item'], ':sub_') !== false || strpos($postArray['item'], ':spitem') !== false )) {
						printOneItemTotalValueTable($postArray, $listArray, $itemListArray, 'monthly_all');		// 4月～3月(12ヶ月分）
					}
					else {
						printOneItemTotalValueTable($postArray, $listArray, $itemListArray, 'monthly_first');
						printOneItemTotalValueTable($postArray, $listArray, $itemListArray, 'monthly_last');
					}
				}
				elseif (isset($postArray['fourth_period'])) {
					// 4ヶ月毎集計表示
					printOneItemTotalValueTable($postArray, $listArray, $itemListArray, 'fourth_period');
				}
				else {
					// 半期表示
					printOneItemTotalValueTable($postArray, $listArray, $itemListArray, 'half_period');
				}

				// LLだけの月別表示特別対策
				// 年間計画でジャックスを選択した時はジャックスの1/2も一緒に、オリコならオリコの1/2も一緒に表示する
				if (local_config::FEATURE_SELECT_LL_PRINT_TOTAL && $postArray['item'] === 'LL' && $postArray['partner'] !== 'TOTAL') {

					echo '</table><br /><p><< 関連情報：'.$copyArray['post']['tui_partner'].'の合計 >></p><table>';

					if (isset($postArray['monthly'])) {
						printOneItemTotalValueTable($copyArray['post'], $copyArray['list'], $itemListArray, 'monthly_all');
					}
					elseif (isset($postArray['fourth_period'])) { 
						printOneItemTotalValueTable($copyArray['post'], $copyArray['list'], $itemListArray, 'fourth_period');
					}
					else {
						printOneItemTotalValueTable($copyArray['post'], $copyArray['list'], $itemListArray, 'half_period');
					}

					// 選択した提携の合計（ジャックスならジャックスと1/2の合計、オリコも同様）
					$targetName = preg_replace('/の$/u', '', $targetName);	// 末尾の「の」削除
					echo '</table><br /><p><< 関連情報：'.$targetName.'と'.$copyArray['post']['tui_partner'].'の合計 >></p><table>';

					if (isset($postArray['monthly'])) {
						printOneItemTotalValueTable($copyArray['post'], $copyArray['ll_total'], $itemListArray, 'monthly_all');
					}
					elseif (isset($postArray['fourth_period'])) { 
						printOneItemTotalValueTable($copyArray['post'], $copyArray['ll_total'], $itemListArray, 'fourth_period');
					}
					else {
						printOneItemTotalValueTable($copyArray['post'], $copyArray['ll_total'], $itemListArray, 'half_period');
					}
				}
			}
		}
		
		?>
		</table>
		<?php
		// 提携毎の更新時間を表示
		printPartnerInputTimeTable($postArray, $postArray['item']); ?>
		<hr>
		
		<?php
		// 同友会社の実績を地域毎に表示
		foreach ($listArray['area_list'] as $areaListArray) { ?>
			<table>
			<?php

			// 本部を表示しない
			if ($areaListArray['value'] === config::HEADOFFICE_NAME){
				continue;
			}

			if (isset($postArray['monthly'])) {
				// 月別表示
				if (!local_config::$FLAG_SEPARATE_PARTNER_PLAN && $postArray['partner'] !== 'NONE' && $postArray['partner'] !== 'TOTAL' ||
				(($postArray['item'] === "LM" || $postArray['item'] === "LS") || strpos($postArray['item'], ':sub_') !== false || strpos($postArray['item'], ':spitem') !== false )) {
					printExecutiveResultValueTable($postArray, $listArray, $areaListArray['value'], 'monthly_all');	// 4月～3月(12ヶ月分）
				}
				else {
					printExecutiveResultValueTable($postArray, $listArray, $areaListArray['value'], 'monthly_first');
					printExecutiveResultValueTable($postArray, $listArray, $areaListArray['value'], 'monthly_last');
				}
			}
			elseif (isset($postArray['fourth_period'])) {
				// 4ヶ月毎集計表示
				printExecutiveResultValueTable($postArray, $listArray, $areaListArray['value'], 'fourth_period');
			}
			else {
				// 半期表示
				printExecutiveResultValueTable($postArray, $listArray, $areaListArray['value'], 'half_period');
			}
			?>
			</table>
			<hr>
		<?php }
	}
	
	// 未選択の場合
	else { ?>
		<p>実績を表示する「年度」と「種目」を選択してください。</p>
	<?php
	}
	?>
</div>

<?php }
//---------------------------------------------
// 実績入力画面
//---------------------------------------------
elseif ($page === 'input') { ?>

<?php
// 全種目を選択している場合のみ実行
if ($postArray['item'] === 'ALL' || count($postArray) == 0) { ?>
<script type="text/javascript">
	window.onload=changeSelectBoxContents;
</script>
<?php } ?>

<div id="contents_select">
	<form id='result_input' style="display:inline" method="POST" action="index.php?reg=plan&type=<?php echo $getArray['type'] ?>">
		<?php printSelectBox($listArray['fiscal_year_list'], 'fiscal_year', 70, $postArray['fiscal_year'], 'changeSelectBoxContents()') ?>年度
		<?php printSelectBox(getMonthList(), 'month', 70, $postArray['month']) ?>月
		<?php printSelectBox($listArray['item_list'], 'item', 100, $postArray['item'], 'changeSelectBoxContents()') ?>
		<?php printSelectBox($listArray['partner_list'], 'partner', 150, $postArray['partner']) ?>
		<?php printSubmitButton('入力画面表示') ?>
		<input type="hidden" name="command" value="show">
	</form>
	
	<?php
	// 一括入力ボタンは実績入力画面を表示している場合のみ
	if ($postArray['command'] === 'show'&& $postArray['item'] !== 'NONE' && $listArray['data_list']['lock'] == config::STATUS_DATA_UNLOCK) { ?>
		<?php printButton('一括入力', '', 'openBundleInputWindow()') ?>
	<?php } ?>
	
	<?php
	// 保存ボタンは実績入力画面を表示している場合のみ
	if ($postArray['command'] === 'show'&& $postArray['item'] !== 'NONE' && $listArray['data_list']['lock'] == config::STATUS_DATA_UNLOCK) { ?>
	<form style="display:inline" method="POST" action="index.php?reg=plan&type=<?php echo $getArray['type']?>">
		<?php printSubmitButton('保存') ?>
		<input type="hidden" name="command" value="save">
		<input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>">
		<input type="hidden" name="month" value="<?php echo $postArray['month'] ?>">
		<input type="hidden" name="item" value="<?php echo $postArray['item'] ?>">
		<input type="hidden" name="partner" value="<?php echo $postArray['partner'] ?>">
	<?php } ?>
	
</div>
<hr>

<div id="contents">
	<?php
	// 実績入力画面を表示
	if (isset($postArray['command'])  && $postArray['command'] === 'show' && $postArray['item'] !== 'NONE') { ?>
		<p>[<?php printPartnerName($listArray['partner_list'], $postArray['partner']) ?>の<?php echo $postArray['fiscal_year'].'年度'.$postArray['month'] ?>月の計画と実績]</p><br />
		
		<?php if ($listArray['data_list']['lock'] == config::STATUS_DATA_UNLOCK) {
			echo '<p>実績を入力し、「保存」ボタンを押してください。</p><br />';
		}
		else {
			echo '<p class="font_red">実績入力は締められています。実績の変更が必要な場合は管理者にお問い合わせください。</p><br />';
		} ?>
		
		<?php
		// 同友会社の実績を地域毎に表示
		foreach ($listArray['area_list'] as $areaListArray) { 
			
			// 本部は実績を表示しないようにする
			if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
				if ($areaListArray['value'] === config::HEADOFFICE_NAME){
					continue;
				} 
			}
			?>

			<table>
			
			<?php
			// 実績入力画面を表示
			if ($listArray['data_list']['lock'] == config::STATUS_DATA_UNLOCK) {
				printExecutiveResultInputTable($postArray, $listArray, $areaListArray['value']);
			}
			else {
				printExecutiveResultInputTable($postArray, $listArray, $areaListArray['value'], 'readOnly');
			}
			?>

			</table>
			<hr>
		<?php } ?>
		</form>
	<?php }
	// 保存完了画面を表示
	elseif (isset($postArray['command']) && $postArray['command'] === 'save' && $postArray['item'] !== 'NONE') { ?>
		<p>実績の保存が完了しました。</p><br />

		<?php
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
		<p>実績を入力する「年度」、「月」、「種目」、「提携企業」を選択してください。</p>
	<?php } ?>
</div>

<?php }
//---------------------------------------------
// 実績締め画面
//---------------------------------------------
elseif ($page === 'lock') { ?>

<?php
// 全種目を選択している場合のみ実行
if ($postArray['item'] === 'ALL' || count($postArray) == 0) { ?>
<script type="text/javascript">
	window.onload=changeSelectBoxContents;
</script>
<?php } ?>

<div id="contents_select">
	<form id='input_lock' style="display:inline" method="POST" action="index.php?reg=plan&type=<?php echo $getArray['type'] ?>">
		<?php printSelectBox($listArray['fiscal_year_list'], 'fiscal_year', 70, $postArray['fiscal_year'], 'changeSelectBoxContents()') ?>年度
		<?php printSelectBox(getMonthList(), 'month', 70, $postArray['month'], 'changeSelectBoxContents()') ?>月
		<?php printSelectBox($listArray['item_list'], 'item', 100, $postArray['item'], 'changeSelectBoxContents()') ?>
		<?php printSelectBox($listArray['partner_list'], 'partner', 150, $postArray['partner']); ?>
		<?php printSubmitButton('実績確認') ?>
		<input type="hidden" name="command" value="show">
	</form>
	<?php
	// 実績締めボタンは実績確認画面を表示している場合のみ
	if (isset($postArray['command']) && $postArray['item'] !== 'NONE' && $listArray['data_list']['lock'] == config::STATUS_DATA_UNLOCK) { ?>
	<form style="display:inline" method="POST" action="index.php?reg=plan&type=<?php echo $getArray['type']?>">
		<?php printSubmitButton('実績締め') ?>
		<input type="hidden" name="command" value="lock">
		<input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>">
		<input type="hidden" name="month" value="<?php echo $postArray['month'] ?>">
		<input type="hidden" name="item" value="<?php echo $postArray['item'] ?>">
		<input type="hidden" name="partner" value="<?php echo $postArray['partner'] ?>">
		<input type="hidden" name="lock" value="lock">
	</form>
	<?php } ?>
	<?php
	// 実績が既に湿られている場合は、締め解除ボタンを表示
	if (isset($postArray['command']) && $postArray['item'] !== 'NONE' && $listArray['data_list']['lock'] == config::STATUS_DATA_LOCK) { ?>
	<form style="display:inline" method="POST" action="index.php?reg=plan&type=<?php echo $getArray['type']?>">
		<?php printSubmitButton('締め解除') ?>
		<input type="hidden" name="command" value="show">
		<input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>">
		<input type="hidden" name="month" value="<?php echo $postArray['month'] ?>">
		<input type="hidden" name="item" value="<?php echo $postArray['item'] ?>">
		<input type="hidden" name="partner" value="<?php echo $postArray['partner'] ?>">
		<input type="hidden" name="lock" value="unlock">
	</form>
	<?php } ?>
</div>
<hr>

<div id="contents">
	<?php
	// 実績入力画面を表示
	if (isset($postArray['command']) && $postArray['item'] !== 'NONE') { ?>
		<p>[<?php printPartnerName($listArray['partner_list'], $postArray['partner']) ?>の<?php echo $postArray['fiscal_year'].'年度'.$postArray['month'] ?>月の実績]</p><br />
		
		<?php if ($listArray['data_list']['lock'] == config::STATUS_DATA_UNLOCK) {
			echo '<p>実績入力を締める場合は「実績締め」ボタンを押してください。</p><br />';
		}
		else {
			echo '<p class="font_red">実績は既に締めています。解除する場合は「締め解除」ボタンを押してください。</p><br />';
		} ?>
		
		<?php
		// 同友会社の実績を地域毎に表示
		foreach ($listArray['area_list'] as $areaListArray) {
			
			// 本部は実績入力を表示しないようにする
			if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
				if ($areaListArray['value'] === config::HEADOFFICE_NAME){
					continue;
				} 
			}
			?>
			
			<table>
			<?php
			// 実績画面を表示
			printExecutiveResultInputTable($postArray, $listArray, $areaListArray['value'], 'readOnly');
			?>
			</table>
			<hr>
		<?php } ?>
	<?php }
	// 未選択の場合
	else { ?>
		<p>実績締めを行う「年度」、「月」、「種目」、「提携企業」を選択してください。</p>
	<?php } ?>
</div>

<?php }
//---------------------------------------------
// 計画入力画面
//---------------------------------------------
elseif ($page === 'make') { ?>

<?php
// 全種目を選択している場合のみ実行
if ($postArray['item'] === 'ALL' || count($postArray) == 0) { ?>
<script type="text/javascript">
	window.onload=changeSelectBoxContents;
</script>
<?php } ?>

<div id="contents_select">
	<form id='result_input' style="display:inline" method="POST" action="index.php?reg=plan&type=<?php echo $getArray['type'] ?>">
		<?php printSelectBox($listArray['fiscal_year_list'], 'fiscal_year', 70, $postArray['fiscal_year'], 'changeSelectBoxContents()') ?>年度
		<?php printSelectBox(getMonthList(), 'month', 70, $postArray['month'], 'changeSelectBoxContents()') ?>月
		<?php
		// 選択した種目によって、統合する種目かどうかを判定する
		$planItemCombine = json_encode(local_config::PLAN_ITEM_COMBINE);
		$itemChangeFunc = 'changeSelectBoxContents('.$planItemCombine.')';
		printSelectBox($listArray['item_list'], 'item', 100, $postArray['item'], $itemChangeFunc) ?>
		<?php
		// 結合する種目の場合は、提携企業のドロップダウンを表示しない
		if (!local_config::$FLAG_SEPARATE_PARTNER_PLAN || (local_config::$FLAG_SEPARATE_PARTNER_PLAN && in_array($postArray['item'], local_config::PLAN_ITEM_COMBINE) )) {
			echo '<div id="separate_partner" style="display:none">';
		}
		else {
			echo '<div id="separate_partner" style="display:inline">';
		}
		?>
		<?php printSelectBox($listArray['partner_list'], 'partner', 150, $postArray['partner']); ?>
		</div>
		<?php printSubmitButton('入力画面表示') ?>
		<input type="hidden" name="command" value="show">
	</form>
	<?php
	// 保存ボタンは計画入力画面を表示している場合のみ
	if (isset($postArray['command']) && $postArray['command'] !== 'save' && $postArray['item'] !== 'NONE' && $listArray['data_list']['lock'] == config::STATUS_DATA_UNLOCK) { ?>
	<form style="display:inline" method="POST" action="index.php?reg=plan&type=<?php echo $getArray['type']?>">
		<?php printSubmitButton('保存') ?>
		<input type="hidden" name="command" value="save">
		<input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>">
		<input type="hidden" name="month" value="<?php echo $postArray['month'] ?>">
		<input type="hidden" name="item" value="<?php echo $postArray['item'] ?>">
		<input type="hidden" name="partner" value="<?php echo $postArray['partner'] ?>">
	<?php } ?>
</div>
<hr>

<div id="contents">
	<?php
	// 計画入力画面を表示
	if (isset($postArray['command'])  && $postArray['command'] === 'show' && $postArray['item'] !== 'NONE') { ?>
	
		<?php if (local_config::$FLAG_SEPARATE_PARTNER_PLAN) { ?>
		<p>[<?php printPartnerName($listArray['partner_list'], $postArray['partner']) ?>の<?php echo $postArray['fiscal_year'].'年度'.$postArray['month'] ?>月の計画作成]</p><br />
		<?php } else { ?>
		<p>[<?php echo $postArray['item'] ?>の<?php echo $postArray['fiscal_year'].'年度'.$postArray['month'] ?>月の計画作成]</p><br />
		<?php } ?>
		
		<?php if ($listArray['data_list']['lock'] == config::STATUS_DATA_UNLOCK) {
			echo '<p>計画を入力し、「保存」ボタンを押してください。</p><br />';
		}
		else {
			echo '<p class="font_red">実績締めが既に行われています。計画の変更を行う場合は実績締めを解除してください。</p><br />';
		} ?>
		
		<?php
		// 同友会社の計画を地域毎に表示
		foreach ($listArray['area_list'] as $areaListArray) { ?>
			<table>
			<?php
			// 計画入力画面を表示
			if ($listArray['data_list']['lock'] == config::STATUS_DATA_UNLOCK) {
				printExecutivePlanInputTable($postArray, $listArray, $areaListArray['value']);
			}
			else {
				printExecutivePlanInputTable($postArray, $listArray, $areaListArray['value'], 'readOnly');
			}
			?>
			</table>
			<hr>
		<?php } ?>
		</form>
	<?php }
	// 保存完了画面を表示
	elseif (isset($postArray['command']) && $postArray['command'] === 'save' && $postArray['item'] !== 'NONE') { ?>
		<p>計画の保存が完了しました。</p><br />

		<?php
		// 同友会社の計画を地域毎に表示
		foreach ($listArray['area_list'] as $areaListArray) { ?>
			<table>
			<?php
			// 計画入力画面を表示
			printExecutivePlanInputTable($postArray, $listArray, $areaListArray['value'], 'readOnly');
			?>
			</table>
			<hr>
		<?php } ?>
		
	<?php }
	// 未選択の場合
	else {
		if (local_config::$FLAG_SEPARATE_PARTNER_PLAN) {
			echo '<p>計画を入力する「年度」、「月」、「種目」、「提携企業」を選択してください。</p>';
		}
		else {
			echo '<p>計画を入力する「年度」、「月」、「種目」を選択してください。</p>';
		}
	} ?>
</div>

<?php }
//---------------------------------------------
// 計画作成画面
//---------------------------------------------
elseif ($page === 'new') { ?>

<div id="contents">
	<?php if ($postArray['command'] === 'input') {
		echo '<p>計画の一括入力に成功しました。</p><br />';
	} ?>
	
	<p>計画の作成、公開を設定してください。提携企業、同友を年度途中で追加した場合は「再構成」ボタンを押してください。</p>
	<p>昨年と、今年度を含む5年分の計画の設定を行うことができます。</p><br />
	
	<table>
	<tr>
		<td class="bg_wet_asphalt" style="width:70px;">年度</td>
		<td class="bg_wet_asphalt" style="width:230px;" colspan="2">作成状態</td>
		<?php if (local_config::FEATURE_EXECUTIVE_ITEM_MIN_TARGET) { ?>
		<td class="bg_wet_asphalt" style="width:70px;">設定</td>
		<?php } ?>
		<td class="bg_wet_asphalt" style="width:230px;" colspan="2">公開状態</td>
		<td class="bg_wet_asphalt" style="width:130px;">操作</td>
		<td class="bg_wet_asphalt" style="width:130px;" colspan="2">計画入力ファイル</td>
	</tr>
	
	<?php
	// 去年と今年を含む5年分のデータを表示
	foreach ($listArray['data_list'] as $dataListArray) { ?>
	<tr>
		<td><?php echo $dataListArray['year'] ?></td>
		
		<td style="width:100px;"><?php echo $dataListArray['make_status'] ?></td>
		<td style="width:130px;">
			<form method="POST" action="index.php?reg=plan&type=<?php echo $getArray['type'] ?>" onsubmit="<?php echo $dataListArray['make_script'] ?>">
			<?php printSubmitButton($dataListArray['make_button'], '') ?>
			<input type="hidden" name="command" value="<?php echo $dataListArray['make_command'] ?>">
			<input type="hidden" name="fiscal_year" value="<?php echo $dataListArray['year'] ?>">
			</form>
		</td>

		<?php
		// 同友最低販売基準設定用
		if (local_config::FEATURE_EXECUTIVE_ITEM_MIN_TARGET) { ?>
		<td>
			<?php if ($dataListArray['make_status'] === '作成済み') { ?>
				<form method="POST" action="index.php?reg=plan&type=<?php echo $getArray['type'] ?>">
				<?php printSubmitButton($dataListArray['min_target_edit_button'], '') ?>
				<input type="hidden" name="command" value="<?php echo $dataListArray['min_target_edit_command'] ?>">
				<input type="hidden" name="fiscal_year" value="<?php echo $dataListArray['year'] ?>">
				</form>
			<?php } ?>
		</td>
		<?php } ?>
		
		<td style="width:100px;"><?php echo $dataListArray['open_status'] ?></td>
		<td style="width:130px;">
			<?php if ($dataListArray['make_status'] === '作成済み') { ?>
				<form method="POST" action="index.php?reg=plan&type=<?php echo $getArray['type'] ?>">
				<?php printSubmitButton($dataListArray['open_button']) ?>
				<input type="hidden" name="command" value="<?php echo $dataListArray['open_command'] ?>">
				<input type="hidden" name="fiscal_year" value="<?php echo $dataListArray['year'] ?>">
				</form>
			<?php } ?>
		</td>
		<td>
			<?php if ($dataListArray['make_status'] === '作成済み') { ?>
				<form method="POST" action="index.php?reg=plan&type=<?php echo $getArray['type'] ?>" onsubmit="<?php echo $dataListArray['delete_script'] ?>">
				<?php printSubmitButton($dataListArray['delete_button'], '') ?>
				<input type="hidden" name="command" value="<?php echo $dataListArray['delete_command'] ?>">
				<input type="hidden" name="fiscal_year" value="<?php echo $dataListArray['year'] ?>">
				</form>
			<?php } ?>
		</td>
		<td>
			<?php if ($dataListArray['make_status'] === '作成済み') { ?>
				<form enctype="multipart/form-data" method="POST" action="index.php?reg=plan&type=<?php echo $getArray['type'] ?>">
				<input type="file" name="input_file">
				<?php printSubmitButton('計画一括入力', '') ?>
				<input type="hidden" name="command" value="input">
				<input type="hidden" name="fiscal_year" value="<?php echo $dataListArray['year'] ?>">
				</form>
			<?php } ?>
		</td>
		<td>
			<?php if ($dataListArray['make_status'] === '作成済み') { ?>
				<input class="submit" type="button" value="ダウンロード" onClick="location.href='./<?php echo config::TEMP_DIRECTORY_NAME ?>/<?php echo config::INPUT_EXCEL_FILENAME ?><?php echo $dataListArray['year'] ?>.xlsx'">
			<?php } ?>
		</td>
	</tr>
	<?php } ?>
		
	</table>
</div>

<?php }
//---------------------------------------------
// 同友最低販売基準＆販促費・料率設定画面
//---------------------------------------------
elseif ($page === 'min_edit') { ?>

<div id="contents">
	<p>設定を入力し、「保存」ボタンを押してください。</p><br />

	<form method="POST" action="index.php?reg=plan&type=<?php echo $getArray['type']?>">
	<?php printSubmitButton('保存', 'save') ?>
	<input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>">
	<input type="hidden" name="command" value="min_edit">
	<hr>
	<br />
	<p>同友最低販売基準、同友・支部への販促費と料率を入力してください。</p>
	<p class="font_red">※数値を入力する時にはカンマ(,)を入れないでください。</p>
	<hr><p class="bold">■ 支部・同友企業 ＜＜ 共通設定 ＞＞</p><hr>
	<table>
	<tr class="bg_wet_asphalt">
		<td style="width:80px;">種目</td>
		<td style="width:50px;">単位</td>
		<td style="width:120px;">同友最低販売基準</td>
		<?php if (local_config::FEATURE_SALES_PROMOTION_FOR_EXECUTIVE) { ?>
			<td style="width:120px;">同友への販促費・料率</td>
		<?php } // FEATURE_SALES_PROMOTION_FOR_EXECUTIVE ?>
		<?php if (local_config::FEATURE_SALES_PROMOTION_FOR_BRANCH) { ?>
			<td style="width:120px;">支部への販促費<br /> 四半期販促費</td>
			<td style="width:120px;">支部への販促費<br /> キャンペーン賞金</td>
			<td style="width:120px;">支部への販促費<br /> 年間賞金</td>
		<?php } // FEATURE_SALES_PROMOTION_FOR_BRANCH ?>
	</tr>
	</tr>
	<?php
	foreach ($listArray['item_list'] as $itemArray) {
	?>
	<tr>
		<td><?php echo $itemArray['value'] ?></td>
		<td><?php echo $itemArray['unit'] ?></td>
		
		<td>
		<?php
		//--------------------------------
		// 同友最低販売基準
		printTextBox($itemArray['value'].':min_target', 100, 'right', $listArray['min_target'][$itemArray['value']]); ?>
		</td>

		<?php
		//--------------------------------
		// 同友への販促費に関する項目
		if (local_config::FEATURE_SALES_PROMOTION_FOR_EXECUTIVE) { ?>
		<td class="right" width="180px">
			<?php
			if ($itemArray['value'] === 'LM') {
				echo '半期毎の実績に対し<br />1台に付き一律 ';
				printTextBox($itemArray['value'].':ryoritsuE_1', 50, 'right', $listArray['executive_promotion'][$itemArray['value'].':ryoritsuE_1']); echo '円';
			}
			else if ($itemArray['value'] === 'LS') {
				if ($postArray['fiscal_year'] != 2024) {
					echo '半期毎の実績に対し<br />1台に付き一律 ';
					printTextBox($itemArray['value'].':ryoritsuE_1', 50, 'right', $listArray['executive_promotion'][$itemArray['value'].':ryoritsuE_1']); echo '円';
				}
				else {
					// 2024年は上期と下期でLSの販促費が異なるので、処理を追加
					echo '上半期毎の実績に対し<br />1台に付き一律 ';
					printTextBox($itemArray['value'].':ryoritsuE_1', 50, 'right', $listArray['executive_promotion'][$itemArray['value'].':ryoritsuE_1']); echo '円<br />';
					echo '下半期毎の実績に対し<br />1台に付き一律 ';
					printTextBox($itemArray['value'].':ryoritsuE_2', 50, 'right', $listArray['executive_promotion'][$itemArray['value'].':ryoritsuE_2']); echo '円';
				}
			}
			elseif ($itemArray['value'] === 'LM+LS' || $itemArray['value'] === 'LH' || $itemArray['value'] === 'LC'){
				echo '--------';	// 販促費がない種目
			}
			else {
				echo '半期毎の実績に対し、6ヶ月間で<br />';
				printTextBox($itemArray['value'].':ryoritsuE_1', 50, 'right', $listArray['executive_promotion'][$itemArray['value'].':ryoritsuE_1']);  echo ' 万円以上 ';
				printTextBox($itemArray['value'].':ryoritsuE_2', 50, 'right', $listArray['executive_promotion'][$itemArray['value'].':ryoritsuE_2']);  echo ' %<br />';
				
				printTextBox($itemArray['value'].':ryoritsuE_3', 50, 'right', $listArray['executive_promotion'][$itemArray['value'].':ryoritsuE_3']);  echo ' 万円以上 ';
				printTextBox($itemArray['value'].':ryoritsuE_4', 50, 'right', $listArray['executive_promotion'][$itemArray['value'].':ryoritsuE_4']);  echo ' %<br />';

				printTextBox($itemArray['value'].':ryoritsuE_5', 50, 'right', $listArray['executive_promotion'][$itemArray['value'].':ryoritsuE_5']);  echo ' 万円以上 ';
				printTextBox($itemArray['value'].':ryoritsuE_6', 50, 'right', $listArray['executive_promotion'][$itemArray['value'].':ryoritsuE_6']);  echo ' %<br />';
			}
			?>
		</td>
		<?php } // FEATURE_SALES_PROMOTION_FOR_EXECUTIVE ?>

		<?php
		//--------------------------------
		// 支部への販促費「四半期」に関する項目
		if (local_config::FEATURE_SALES_PROMOTION_FOR_BRANCH) { ?>
		<td class="right" width="180px">
			<?php
			if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
				// 2020年秋キャンペーンから追加（LM、LSの販促費を四半期毎に設定）▼▼▼ ここから
				if ($postArray['fiscal_year'] >= 2020) {
					echo '四半期毎の実績に対し、1台に付き<br />';
					echo '第1四半期 ';
					printTextBox($itemArray['value'].':ryoritsuB_1', 50, 'right', $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_1']); echo '円<br />';
					echo '第2四半期 ';
					printTextBox($itemArray['value'].':ryoritsuB_2', 50, 'right', $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_2']); echo '円<br />';
					echo '第3四半期 ';
					printTextBox($itemArray['value'].':ryoritsuB_3', 50, 'right', $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_3']); echo '円<br />';
					echo '第4四半期 ';
					printTextBox($itemArray['value'].':ryoritsuB_4', 50, 'right', $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_4']); echo '円';
				}
				else {
					echo '半期毎の実績に対し<br />1台に付き一律 ';
					printTextBox($itemArray['value'].':ryoritsuB_1', 50, 'right', $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_1']); echo '円';
				}
				// 2020年秋キャンペーンから追加（LM、LSの販促費を四半期毎に設定）▲▲▲ ここまで
			}
			elseif ($itemArray['value'] === 'LM+LS' || $itemArray['value'] === 'LH' || $itemArray['value'] === 'LC'){
				echo '';	// 販促費がない種目
			}
			else {
				echo '四半期毎の計画に対する実績が<br />';
				printTextBox($itemArray['value'].':ryoritsuB_1', 50, 'right', $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_1']);  echo ' % 以上 ';
				printTextBox($itemArray['value'].':ryoritsuB_2', 50, 'right', $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_2']);  echo ' %<br />';
				
				printTextBox($itemArray['value'].':ryoritsuB_3', 50, 'right', $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_3']);  echo ' % 以上 ';
				printTextBox($itemArray['value'].':ryoritsuB_4', 50, 'right', $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_4']);  echo ' %<br />';

				printTextBox($itemArray['value'].':ryoritsuB_5', 50, 'right', $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_5']);  echo ' % 以上 ';
				printTextBox($itemArray['value'].':ryoritsuB_6', 50, 'right', $listArray['branch_promotion'][$itemArray['value'].':ryoritsuB_6']);  echo ' %<br />';
			}
			?>
		</td>
		<?php
		//--------------------------------
		// 支部への販促費「キャンペーン賞金」に関する項目 ?>
		<td class="right" width="180px">
		<?php
			if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS' || $itemArray['value'] === 'LM+LS' || $itemArray['value'] === 'LH' || $itemArray['value'] === 'LC') {
				echo '';	// 販促費がない種目
			}
			else {
				echo '期間中の計画に対する実績が<br />';
				printTextBox($itemArray['value'].':ryoritsuC_1', 50, 'right', $listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_1']);  echo ' % 以上 ';
				printTextBox($itemArray['value'].':ryoritsuC_2', 50, 'right', $listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_2']);  echo ' %<br />';
				
				printTextBox($itemArray['value'].':ryoritsuC_3', 50, 'right', $listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_3']);  echo ' % 以上 ';
				printTextBox($itemArray['value'].':ryoritsuC_4', 50, 'right', $listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_4']);  echo ' %<br />';

				printTextBox($itemArray['value'].':ryoritsuC_5', 50, 'right', $listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_5']);  echo ' % 以上 ';
				printTextBox($itemArray['value'].':ryoritsuC_6', 50, 'right', $listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_6']);  echo ' %<br />';

				// 2020年秋キャンペーンから追加 ▼▼▼ ここから
				if ($postArray['fiscal_year'] >= 2020) {
				printTextBox($itemArray['value'].':ryoritsuC_7', 50, 'right', $listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_7']);  echo ' % 以上 ';
				printTextBox($itemArray['value'].':ryoritsuC_8', 50, 'right', $listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_8']);  echo ' %<br />';

				printTextBox($itemArray['value'].':ryoritsuC_9', 50, 'right', $listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_9']);  echo ' % 以上 ';
				printTextBox($itemArray['value'].':ryoritsuC_10', 50, 'right', $listArray['campaign_promotion'][$itemArray['value'].':ryoritsuC_10']);  echo ' %<br />';
				}
				// 2020年秋キャンペーンから追加 ▲▲▲ ここまで
			}
			?>
		</td>
		<?php
		//--------------------------------
		// 支部への販促費「年間賞金」に関する項目 ?>
		<td class="right" width="180px">
		<?php
			if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS' || $itemArray['value'] === 'LM+LS' || $itemArray['value'] === 'LH') {
				echo '';	// 販促費がない種目
			}
			else {
				if ($itemArray['value'] !== 'LC') {
					echo '年間の計画に対する実績が<br />';
					printTextBox($itemArray['value'].':ryoritsuY_1', 50, 'right', $listArray['year_promotion'][$itemArray['value'].':ryoritsuY_1']);  echo ' % 以上 ';
					printTextBox($itemArray['value'].':ryoritsuY_2', 50, 'right', $listArray['year_promotion'][$itemArray['value'].':ryoritsuY_2']);  echo ' %<br />';
					
					printTextBox($itemArray['value'].':ryoritsuY_3', 50, 'right', $listArray['year_promotion'][$itemArray['value'].':ryoritsuY_3']);  echo ' % 以上 ';
					printTextBox($itemArray['value'].':ryoritsuY_4', 50, 'right', $listArray['year_promotion'][$itemArray['value'].':ryoritsuY_4']);  echo ' %<br />';

					printTextBox($itemArray['value'].':ryoritsuY_5', 50, 'right', $listArray['year_promotion'][$itemArray['value'].':ryoritsuY_5']);  echo ' % 以上 ';
					printTextBox($itemArray['value'].':ryoritsuY_6', 50, 'right', $listArray['year_promotion'][$itemArray['value'].':ryoritsuY_6']);  echo ' %<br />';
				}
				else {
					echo '年間保有計画計画に対する実績が<br />';
					printTextBox($itemArray['value'].':ryoritsuY_1', 50, 'right', $listArray['year_promotion'][$itemArray['value'].':ryoritsuY_1']);  echo ' % 以上 ';
					printTextBox($itemArray['value'].':ryoritsuY_2', 50, 'right', $listArray['year_promotion'][$itemArray['value'].':ryoritsuY_2']);  echo ' 円<br />';
					
					printTextBox($itemArray['value'].':ryoritsuY_3', 50, 'right', $listArray['year_promotion'][$itemArray['value'].':ryoritsuY_3']);  echo ' % 以上 ';
					printTextBox($itemArray['value'].':ryoritsuY_4', 50, 'right', $listArray['year_promotion'][$itemArray['value'].':ryoritsuY_4']);  echo ' 円<br />';
				}
			}
			?>
		</td>
		<?php } // FEATURE_SALES_PROMOTION_FOR_BRANCH ?>

	</tr>
	<?php
		
	} ?>
	</table>

	<?php
	if (local_config::FEATURE_SALES_PROMOTION_FOR_BRANCH) { ?>
	<br />
	<hr><p class="bold">■ 支部向け設定</p><hr>
	<?php
	//--------------------------------
	// LC保有目標計画枚数
	?>
	<br />
	<p>LC保有目標達成賞金（※ロータスカードの支部年間保有計画枚数を入力してください。）</p>
	<table>
		<tr>
			<td class="bg_wet_asphalt" style="width:150px;">項目</td>
			<td class="bg_wet_asphalt" style="width:80px;">設定値</td>
		</tr>
		<tr>
			<td class="left">支部年間保有計画枚数</td>
			<td><?php printTextBox('lc_year_target_count', 100, 'right', $listArray['lc_hold_number']['lc_year_target_count']); echo ' 枚';?></td>
		</tr>
	</table>
	<?php
	//--------------------------------
	// LM、LSボーナス賞金
	?>
	<br />
	<p>決定したLM、LSのボーナス賞金の「1台あたりの単価」を入力してください。</p>
	<table>
		<tr>
			<td class="bg_wet_asphalt" style="width:150px;">項目</td>
			<td class="bg_wet_asphalt" style="width:80px;">設定値</td>
		</tr>
		<tr>
			<td class="left">LMボーナス賞金 (上半期) ＠台</td>
			<td><?php printTextBox('lm_bonus_prize_1_half', 100, 'right', $listArray['other_prize']['lm_bonus_prize_1_half']); echo ' 円';?></td>
		</tr>
		<tr>
			<td class="left">LMボーナス賞金 (下半期) ＠台</td>
			<td><?php printTextBox('lm_bonus_prize_2_half', 100, 'right', $listArray['other_prize']['lm_bonus_prize_2_half']); echo ' 円';?></td>
		</tr>
		<tr>
			<td class="left">LSボーナス賞金 (上半期) ＠台</td>
			<td><?php printTextBox('ls_bonus_prize_1_half', 100, 'right', $listArray['other_prize']['ls_bonus_prize_1_half']); echo ' 円';?></td>
		</tr>
		<tr>
			<td class="left">LSボーナス賞金 (下半期) ＠台</td>
			<td><?php printTextBox('ls_bonus_prize_2_half', 100, 'right', $listArray['other_prize']['ls_bonus_prize_2_half']); echo ' 円';?></td>
		</tr>
	</table>
	<?php
	//--------------------------------
	// LOボーナス賞金
	?>
	<br />
	<p>決定したLOボーナス賞金を入力してください。</p>
	<table>
		<tr>
			<td class="bg_wet_asphalt" style="width:150px;">項目</td>
			<td class="bg_wet_asphalt" style="width:80px;">設定値</td>
		</tr>
		<tr>
			<td class="left">LOボーナス賞金</td>
			<td><?php printTextBox('lo_year_prize', 100, 'right', $listArray['other_prize']['lo_year_prize']); echo ' 円';?></td>
		</tr>
	</table>
	<br />
	<?php
	//--------------------------------
	// LH自動車特別賞
	?>
	<p>決定したLH自動車特別賞を入力してください。</p>
	<table>
		<tr>
			<td class="bg_wet_asphalt" style="width:150px;">項目</td>
			<td class="bg_wet_asphalt" style="width:80px;">設定値</td>
		</tr>
		<tr>
			<td class="left">キャンペーン優績支部賞（サマー）</td>
			<td><?php printTextBox('lh_summer_prize', 100, 'right', $listArray['other_prize']['lh_summer_prize']); echo ' 円';?></td>
		</tr>
		<tr>
			<td class="left">キャンペーン優績支部賞（秋）</td>
			<td><?php printTextBox('lh_autumn_prize', 100, 'right', $listArray['other_prize']['lh_autumn_prize']); echo ' 円';?></td>
		</tr>
		<tr>
			<td class="left">キャンペーン優績支部賞（春）</td>
			<td><?php printTextBox('lh_spring_prize', 100, 'right', $listArray['other_prize']['lh_spring_prize']); echo ' 円';?></td>
		</tr>
		<tr>
			<td class="left">1,000万円未満同友解消支部賞</td>
			<td><?php printTextBox('lh_doyu_kaisyo_prize', 100, 'right', $listArray['other_prize']['lh_doyu_kaisyo_prize']); echo ' 円';?></td>
		</tr>
	</table>
	<?php
	//--------------------------------
	// 生産性＆ボリューム報奨金
	?>
	<br />
	<?php
	// 2021年以降は賞金額ではなく、順位を入力するように仕様変更
	if ($postArray['fiscal_year'] >= 2021) {
		$seisan_volume_msg = '決定した生産性報奨金、ボリューム報奨金の順位を入力してください。';
		$seisan_volume_unit = '位';
	}
	else {
		$seisan_volume_msg = '決定した生産性報奨金、ボリューム報奨金を入力してください。';
		$seisan_volume_unit = '円';
	}
	?>
	<p><?php echo $seisan_volume_msg; ?></p>
	<table>
		<tr>
			<td class="bg_wet_asphalt" style="width:150px;">項目</td>
			<td class="bg_wet_asphalt" style="width:80px;">LM+LS</td><td class="bg_wet_asphalt" style="width:80px;">LT</td>
			<td class="bg_wet_asphalt" style="width:80px;">LH自動車</td><td class="bg_wet_asphalt" style="width:80px;">LO</td>
			<td class="bg_wet_asphalt" style="width:80px;">LE</td><td class="bg_wet_asphalt" style="width:80px;">LL</td>
		</tr>
		<tr>
			<td class="left">生産性報奨金（上半期）</td>
			<td><?php printTextBox('lms_seisan_prize_1', 100, 'right', $listArray['other_prize']['lms_seisan_prize_1']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('lt_seisan_prize_1', 100, 'right', $listArray['other_prize']['lt_seisan_prize_1']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('lh_seisan_prize_1', 100, 'right', $listArray['other_prize']['lh_seisan_prize_1']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('lo_seisan_prize_1', 100, 'right', $listArray['other_prize']['lo_seisan_prize_1']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('le_seisan_prize_1', 100, 'right', $listArray['other_prize']['le_seisan_prize_1']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('ll_seisan_prize_1', 100, 'right', $listArray['other_prize']['ll_seisan_prize_1']); echo ' '.$seisan_volume_unit;?></td>
		</tr>
		<tr>
			<td class="left">生産性報奨金（下半期）</td>
			<td><?php printTextBox('lms_seisan_prize_2', 100, 'right', $listArray['other_prize']['lms_seisan_prize_2']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('lt_seisan_prize_2', 100, 'right', $listArray['other_prize']['lt_seisan_prize_2']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('lh_seisan_prize_2', 100, 'right', $listArray['other_prize']['lh_seisan_prize_2']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('lo_seisan_prize_2', 100, 'right', $listArray['other_prize']['lo_seisan_prize_2']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('le_seisan_prize_2', 100, 'right', $listArray['other_prize']['le_seisan_prize_2']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('ll_seisan_prize_2', 100, 'right', $listArray['other_prize']['ll_seisan_prize_2']); echo ' '.$seisan_volume_unit;?></td>
		</tr>
		<tr>
			<td class="left">ボリューム報奨金（上半期）</td>
			<td><?php printTextBox('lms_volume_prize_1', 100, 'right', $listArray['other_prize']['lms_volume_prize_1']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('lt_volume_prize_1', 100, 'right', $listArray['other_prize']['lt_volume_prize_1']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('lh_volume_prize_1', 100, 'right', $listArray['other_prize']['lh_volume_prize_1']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('lo_volume_prize_1', 100, 'right', $listArray['other_prize']['lo_volume_prize_1']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('le_volume_prize_1', 100, 'right', $listArray['other_prize']['le_volume_prize_1']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('ll_volume_prize_1', 100, 'right', $listArray['other_prize']['ll_volume_prize_1']); echo ' '.$seisan_volume_unit;?></td>
		</tr>
		<tr>
			<td class="left">ボリューム報奨金（下半期）</td>
			<td><?php printTextBox('lms_volume_prize_2', 100, 'right', $listArray['other_prize']['lms_volume_prize_2']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('lt_volume_prize_2', 100, 'right', $listArray['other_prize']['lt_volume_prize_2']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('lh_volume_prize_2', 100, 'right', $listArray['other_prize']['lh_volume_prize_2']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('lo_volume_prize_2', 100, 'right', $listArray['other_prize']['lo_volume_prize_2']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('le_volume_prize_2', 100, 'right', $listArray['other_prize']['le_volume_prize_2']); echo ' '.$seisan_volume_unit;?></td>
			<td><?php printTextBox('ll_volume_prize_2', 100, 'right', $listArray['other_prize']['ll_volume_prize_2']); echo ' '.$seisan_volume_unit;?></td>
		</tr>
	</table>

	<?php
	//--------------------------------
	// 年間全稼働報奨金
	?>
	<br />
	<p>年間全稼働報奨金（1同友あたりの報奨金）を入力してください。</p>
	<table>
		<tr>
			<td class="bg_wet_asphalt" style="width:150px;">項目</td>
			<td class="bg_wet_asphalt" style="width:80px;">設定値</td>
		</tr>
		<tr>
			<td class="left">1同友あたりの報奨金</td>
			<td><?php printTextBox('min_target_clear_prize', 100, 'right', $listArray['other_prize']['min_target_clear_prize']); echo ' 円';?></td>
		</tr>
	</table>
	<?php } // FEATURE_SALES_PROMOTION_FOR_BRANCH ?>

	<?php
	//--------------------------------
	// 社長賞 特別施策賞金(サマー、秋、春)
	if (local_config::FEATURE_PRESIDENT_PRIZE_STATUS) { ?>
	<br />
	<p>社長賞の各キャンペーンの特別施策賞金を入力してください。</p>
	<table>
		<tr>
			<td class="bg_wet_asphalt" style="width:150px;">項目</td>
			<td class="bg_wet_asphalt" style="width:80px;">LM+LS</td><td class="bg_wet_asphalt" style="width:80px;">LT</td>
			<td class="bg_wet_asphalt" style="width:80px;">LO</td><td class="bg_wet_asphalt" style="width:80px;">LE</td><td class="bg_wet_asphalt" style="width:80px;">LL</td>
		</tr>
		<tr>
			<td class="left">キャンペーン特別施策賞金（サマー）</td>
			<td><?php printTextBox('lms_special_prize_summer', 100, 'right', $listArray['other_prize']['LM+LS_special_prize_summer']); echo ' 円';?></td>
			<td><?php printTextBox('lt_special_prize_summer', 100, 'right', $listArray['other_prize']['LT_special_prize_summer']); echo ' 円';?></td>
			<td><?php printTextBox('lo_special_prize_summer', 100, 'right', $listArray['other_prize']['LO_special_prize_summer']); echo ' 円';?></td>
			<td><?php printTextBox('le_special_prize_summer', 100, 'right', $listArray['other_prize']['LE_special_prize_summer']); echo ' 円';?></td>
			<td><?php printTextBox('ll_special_prize_summer', 100, 'right', $listArray['other_prize']['LL_special_prize_summer']); echo ' 円';?></td>
		</tr>
		<tr>
			<td class="left">キャンペーン特別施策賞金（秋）</td>
			<td><?php printTextBox('lms_special_prize_autumn', 100, 'right', $listArray['other_prize']['LM+LS_special_prize_autumn']); echo ' 円';?></td>
			<td><?php printTextBox('lt_special_prize_autumn', 100, 'right', $listArray['other_prize']['LT_special_prize_autumn']); echo ' 円';?></td>
			<td><?php printTextBox('lo_special_prize_autumn', 100, 'right', $listArray['other_prize']['LO_special_prize_autumn']); echo ' 円';?></td>
			<td><?php printTextBox('le_special_prize_autumn', 100, 'right', $listArray['other_prize']['LE_special_prize_autumn']); echo ' 円';?></td>
			<td><?php printTextBox('ll_special_prize_autumn', 100, 'right', $listArray['other_prize']['LL_special_prize_autumn']); echo ' 円';?></td>
		</tr>
		<tr>
			<td class="left">キャンペーン特別施策賞金（春）</td>
			<td><?php printTextBox('lms_special_prize_spring', 100, 'right', $listArray['other_prize']['LM+LS_special_prize_spring']); echo ' 円';?></td>
			<td><?php printTextBox('lt_special_prize_spring', 100, 'right', $listArray['other_prize']['LT_special_prize_spring']); echo ' 円';?></td>
			<td><?php printTextBox('lo_special_prize_spring', 100, 'right', $listArray['other_prize']['LO_special_prize_spring']); echo ' 円';?></td>
			<td><?php printTextBox('le_special_prize_spring', 100, 'right', $listArray['other_prize']['LE_special_prize_spring']); echo ' 円';?></td>
			<td><?php printTextBox('ll_special_prize_spring', 100, 'right', $listArray['other_prize']['LL_special_prize_spring']); echo ' 円';?></td>
		</tr>
	</table>
	<br />
	<p>社長賞支部部門の賞金額を入力してください。</p>
	<table>
		<tr>
			<td class="bg_wet_asphalt" style="width:150px;">項目</td>
			<td class="bg_wet_asphalt" style="width:80px;">設定値</td>
		</tr>
		<tr>
			<td class="left">社長賞支部部門の賞金額</td>
			<td><?php printTextBox('president_prize', 100, 'right', $listArray['other_prize']['president_prize']); echo ' 円';?></td>
		</tr>
	</table>
	<?php } // FEATURE_PRESIDENT_PRIZE_STATUS ?>

	<br />
	<hr><p class="bold">■ 同友企業向け設定</p><hr>
	<?php
	//--------------------------------
	// LC獲得推進費と、LC保有支援金に関する項目
	if (local_config::FEATURE_LC_HOLD_NUMBER) { ?>
	<p>LC獲得推進費と、LC保有支援金を入力してください。</p>
	<table>
		<tr>
			<td class="bg_wet_asphalt" style="width:120px;">項目</td>
			<td class="bg_wet_asphalt" style="width:80px;">設定値</td>
		</tr>
		<tr>
			<td class="left">LC獲得推進費</td>
			<td><?php printTextBox('lc_get_promotion_cost', 100, 'right', $listArray['lc_hold_number']['lc_get_promotion_cost']); echo ' 円';?></td>
		</tr>
		<tr>
			<td class="left">LC期末保有枚数</td>
			<td><?php printTextBox('lc_hold_support_count', 100, 'right', $listArray['lc_hold_number']['lc_hold_support_count']); echo ' 枚';?></td>
		</tr>
		<tr>
			<td class="left">LC保有支援金</td>
			<td><?php printTextBox('lc_hold_support_cost', 100, 'right', $listArray['lc_hold_number']['lc_hold_support_cost']); echo ' 円';?></td>
		</tr>
	</table>
	<br />
	<?php if (local_config::$DB_TABLE_PREFIX === "kngw") { ?>
	<p>年間優績同友表彰基準の設定値を入力してください。</p>
	<table>
		<tr>
			<td class="bg_wet_asphalt" style="width:120px;">項目</td>
			<td class="bg_wet_asphalt" style="width:80px;">設定値</td>
		</tr>
		<tr>
			<td class="left">最優秀賞</td>
			<td class="right">
			<?php
			echo 'カード新規獲得条件：年間';
			printTextBox('lc_get_promotion_count', 100, 'right', $listArray['lc_hold_number']['lc_get_promotion_count']); echo ' 枚<br />';
			printTextBox('lc_get_item_count_1', 100, 'right', $listArray['lc_hold_number']['lc_get_item_count_1']); echo ' 種目達成に対して';
			printTextBox('lc_get_item_prize_1', 100, 'right', $listArray['lc_hold_number']['lc_get_item_prize_1']); echo ' 円<br />';
			printTextBox('lc_get_item_count_2', 100, 'right', $listArray['lc_hold_number']['lc_get_item_count_2']); echo ' 種目達成に対して';
			printTextBox('lc_get_item_prize_2', 100, 'right', $listArray['lc_hold_number']['lc_get_item_prize_2']); echo ' 円<br />';?>
			</td>
		</tr>
	</table>
	<br />
	<?php } // local_config::$DB_TABLE_PREFIX === "kngw" ?>
	<p>ロータスカード新規獲得優秀賞の設定値を入力してください。</p>
	<table>
		<tr>
			<td class="bg_wet_asphalt" style="width:120px;">項目</td>
			<td class="bg_wet_asphalt" style="width:80px;">設定値</td>
		</tr>
		<tr>
			<td class="left">新規獲得優秀賞</td>
			<td class="right"><?php printTextBox('lc_get_promotion_prize', 100, 'right', $listArray['lc_hold_number']['lc_get_promotion_prize']); echo ' 円<br />';?></td>
		</tr>
	</table>
	<?php } // FEATURE_LC_HOLD_NUMBER ?>

	<?php if (local_config::FEATURE_SALES_PROMOTION_FOR_EXECUTIVE) { ?>
	<br />
	<p>モービルプロショップ認定条件（年間4月～3月でモービルオイルの購入金額）を入力してください。</p>
	<table>
		<tr>
			<td class="bg_wet_asphalt" style="width:120px;">項目</td>
			<td class="bg_wet_asphalt" style="width:80px;">設定値</td>
		</tr>
		<tr>
			<td class="left">モービルプロショップ達成金額(税別)</td>
			<td class="right"><?php printTextBox('LO_proshop_target', 100, 'right', $listArray['other_prize']['LO_proshop_target']); echo ' 万円<br />';?></td>
		</tr>
		<tr>
			<td class="left">準モービルプロショップ達成金額(税別)</td>
			<td class="right"><?php printTextBox('LO_proshop_target2', 100, 'right', $listArray['other_prize']['LO_proshop_target2']); echo ' 万円<br />';?></td>
		</tr>
	</table>
	<br />
	<p>モービルプロショップの特典を入力してください。</p>
	<?php printTextAreaBox('LO_proshop_msg', 500, 'left', $listArray['other_prize']['LO_proshop_msg']); ?>
	<?php } // FEATURE_SALES_PROMOTION_FOR_EXECUTIVE ?>

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