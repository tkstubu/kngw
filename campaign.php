<?php
/**
 * =================================================================
 * campaign.php
 * キャンペーン用PHPスクリプト
 * =================================================================
 */

//=================================================================
// ロジック部
//=================================================================

//--------------------------------
// グローバル変数
//--------------------------------
$FLAG_CAMPAIGN_SEPARATE_PARTNER = false;

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
	'campaign_list'    => array(),
	'item_list'        => array(),
	'none_item_list'   => array(),
	'area_list'        => array(),
	'partner_list'     => array(),
	'executive_list'   => array(),
	'data_list'        => array(),
	'p_prize'          => array(),
	'msg_list'         => array()
);


//--------------------------------
// 表示ページを選択
//--------------------------------
$page = selectPageForCampaign($getArray, $postArray, $listArray);

/**
 * ----------------------------------------------------------
 * selectPageForPlan()
 * 年間計画で表示するページを選択する
 * @param $getArray：GETで送られてきたパラメータ
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return variable
 * ----------------------------------------------------------
 */
function selectPageForCampaign($getArray, &$postArray, &$listArray) {

	$page = '';
	global $FLAG_CAMPAIGN_SEPARATE_PARTNER;

	// キャンペーンで表示する種目を統合するのか分離するのかを判定
	if (in_array($postArray['item'], local_config::CAMPAIGN_ITEM_COMBINE)) {
		$FLAG_CAMPAIGN_SEPARATE_PARTNER = true;
	}
	else {
		$FLAG_CAMPAIGN_SEPARATE_PARTNER = false;
	}

	// 選択中のメニューに応じた結果を表示
	switch ($getArray['type']) {
		case  'view':
			$page = makeCampaignViewPage($postArray, $listArray);
			break;
		case  'input':
			$page = makeCanpaignInputPage($postArray, $listArray);
			break;
		case  'create':
			$page = makeCanpaignCreatePage($postArray, $listArray);
			break;
		case  'edit':
			$page = makeCanpaignEditPage($postArray, $listArray);
			break;
		case  'graph_edit':
			$page = makeCanpaignGraphEditPage($postArray, $listArray);
			break;
		case  'area_reach':
			$page = makeCanpaignAreaReachRankPage($postArray, $listArray);
			break;
		case  'download':
			$page = makeCanpaignDownloadSheetPage($postArray, $listArray);
			break;
		default:
			break;
	}
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makeCampaignViewPage()
 * キャンペーンを閲覧するためのページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return variable
 * ----------------------------------------------------------
 */
function makeCampaignViewPage(&$postArray, &$listArray) {

	$page = 'view';

	// ログインユーザ情報を取得
	$listArray['user_info'] = getUserInfo($_SESSION['USERID']);
	
	// 作成済みのキャンペーンデータの年度を取得
	$listArray['fiscal_year_list'] = getFiscalYearList('campaign');
	if (count($listArray['fiscal_year_list']) == 0) {
		return 'no_data';
	}

	// 参加分母同友数を取得
	if (isset($postArray['fiscal_year'])) {
		$listArray['executive_list'] = getExecutiveList($postArray['fiscal_year'], 'campaign', '%', 'all');
	}
	else {
		$listArray['executive_list'] = getExecutiveList($listArray['fiscal_year_list'][0]['value'], 'campaign', '%', 'all');
	}
	if (count($listArray['executive_list']) == 0) {
		return 'no_campaign_executive';
	}
	
	// キャンペーンリストを作成
	$listArray['campaign_list'][] = array('value' => 'ALL',    'name' => '全キャンペーン');
	$listArray['campaign_list'][] = array('value' => 'summer', 'name' => config::SUMMER_CAMPAIGN_NAME);
	$listArray['campaign_list'][] = array('value' => 'autumn', 'name' => config::AUTUMN_CAMPAIGN_NAME);
	$listArray['campaign_list'][] = array('value' => 'spring', 'name' => config::SPRING_CAMPAIGN_NAME);
	
	// LM+LS対策：POSTで指定されていない場合は、最も新しい作成年度を設定しておく
	if (!isset($postArray['fiscal_year'])) {
		$postArray['fiscal_year'] = $listArray['fiscal_year_list'][0]['value'];
	}

	// LM+LS対策：全種目を取得(2015年移行はLM+LSを表示、LC表示, LEB表示)
	// ※LC、LEBは優績シミュレートを表示しない
	$listArray['item_list'] = getItemList('','',config::CAMPAIGN_LMLS_ADD_YEAR, true, false, false);
	if (count($listArray['item_list']) == 0) {
		return 'no_item';
	}

	// LM+LS対策：2014年までの種目を取得
	$oldItemYear = config::CAMPAIGN_LMLS_ADD_YEAR - 1;
	$listArray['2014_item_list'] = getItemList('','',$oldItemYear);
	if (count($listArray['2014_item_list']) == 0) {
		return 'no_item';
	}
	
	// LM+LS対策：2014年以前で表示するとき$postArray['item']に$postArray['2014_item']の値に代入
	if ($postArray['fiscal_year'] < config::CAMPAIGN_LMLS_ADD_YEAR) {
		$postArray['item'] = $postArray['2014_item'];
	}
	
	//printArray($listArray['item_list']);

	// 補助種目一覧を取得し、メイン種目と結合
	//$subitemList = getSubItemList('no-insert');
	//if (count($subitemList) > 0) {
	//	$listArray['item_list'] = array_merge($listArray['item_list'], $subitemList);
	//}
	
	// 種目を選択させない用
	$listArray['none_item_list'][] = array('value' => 'NONE', 'name' => '----');
	
	// 地区一覧を取得
	$listArray['area_list'] = getAreaList();
	if (count($listArray['area_list']) == 0) {
		return 'no_area';
	}
	
	// グラフに追加ボタンが押された時はデータベースに達成率を保存する
	if ($postArray['command'] === 'graph_input') {
		// データベースに保存
		$ret = writeCampaignGraphData($postArray);
		if ($ret === 'error_graph_data_max') {
			$listArray['msg_list'][0] = '<font class="font_red">グラフの期間数が最大に達しています。グラフを編集してください。</font>';
		}
		elseif ($ret !== 'success') {
			return 'db_write_fail';
		}
		else {
			$listArray['msg_list'][0] = 'グラフに期間を追加しました。';
		}
	}
	return $page;
}

/**
 * ----------------------------------------------------------
 * makeCanpaignInputPage()
 * キャンペーンを計画するためのページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return variable
 * ----------------------------------------------------------
 */
function makeCanpaignInputPage($postArray, &$listArray) {

	$page = 'input';
	
	// 作成済みのキャンペーンデータの年度を取得
	$listArray['fiscal_year_list'] = getFiscalYearList('campaign');
	if (count($listArray['fiscal_year_list']) == 0) {
		return 'no_data';
	}

	// 参加分母同友を取得
	if (isset($postArray['fiscal_year'])) {
		$listArray['executive_list'] = getExecutiveList($postArray['fiscal_year'], 'campaign', '%', 'all');
	}
	else {
		$listArray['executive_list'] = getExecutiveList($listArray['fiscal_year_list'][0]['value'], 'campaign', '%', 'all');
	}
	if (count($listArray['executive_list']) == 0) {
		return 'no_campaign_executive';
	}
	
	// キャンペーンリストを作成
	$listArray['campaign_list'][] = array('value' => 'NONE',   'name' => '---');
	$listArray['campaign_list'][] = array('value' => 'summer', 'name' => config::SUMMER_CAMPAIGN_NAME);
	$listArray['campaign_list'][] = array('value' => 'autumn', 'name' => config::AUTUMN_CAMPAIGN_NAME);
	$listArray['campaign_list'][] = array('value' => 'spring', 'name' => config::SPRING_CAMPAIGN_NAME);

	// 全種目を取得
	$listArray['item_list'] = getItemList('non-select');
	if (count($listArray['item_list']) == 0) {
		return 'no_item';
	}
	
	// 地区一覧を取得
	$listArray['area_list'] = getAreaList();
	if (count($listArray['area_list']) == 0) {
		return 'no_area';
	}
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makeCanpaignCreatePage()
 * キャンペーンを作成するためのページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return variable
 * ----------------------------------------------------------
 */
function makeCanpaignCreatePage($postArray, &$listArray) {

	$page = 'create';
	$ret = '';
	
	$status = array();

	// コマンド実行時
	if (isset($postArray['command'])) {
		switch ($postArray['command']) {
			case 'create':
				//$ret = createData($postArray['fiscal_year']);
				//if ($ret === 'success') {
				//	$ret = createInputExcelFile($postArray['fiscal_year']);	// 計画一括入力用Excelファイル生成
				//}
				
				// 年間計画が既に作成済みかどうかチェック
				$dataList = getFiscalYearList();
				foreach ($dataList as $data) {
					if ($data['value'] === $postArray['fiscal_year']) {
						$ret = createCampaignData($postArray['fiscal_year']);	// キャンペーンデータの生成
					}
				}
				if (strlen($ret) == 0) {
					$ret = 'campaign_create_failed';
				}
				//echo 'ret='.$ret;
				break;
			case 'delete':
				$ret = deleteCampaignData($postArray['fiscal_year']);
				break;
			case 'open':
				$ret = openCampaignData($postArray['fiscal_year'], $postArray['campaign']);
				break;
			case 'close':
				$ret = closeCampaignData($postArray['fiscal_year'], $postArray['campaign']);
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
	$current = getCurrentFiscalYear()-1;

	// 計画作成済み年度を取得
	$makeYearList = getFiscalYearList("campaign");
	
	// 昨年と、今年度を含む6年分の計画を作成
	for ($i = $current; $i < $current + config::MAKE_PLAN_NUM_MAX; $i++) {
	
		// 指定年度のキャンペーンデータを取得
		$campaignInfo = getCampaignDataInfo($i);
		
		// デバッグ用
		//echo "<pre>";
		//print_r($campaignInfo);
		//echo "</pre>";
		
		if (strlen($campaignInfo['fiscal_year']) == 0 ) {
			$status['make']   = array('status' => '未作成', 'button' => '作成', 'command' => 'create', 'script' => 'return checkMessage(\'データを作成しますか？\');');
			$status['delete'] = array();
			$status['summer_open'] = array('status' => '未公開');
			$status['autumn_open'] = array('status' => '未公開');
			$status['spring_open'] = array('status' => '未公開');
			$status['edit'] = array();
		}
		else {
			$status['make']   = array('status' => '作成済み', 'button' => '再構成', 'command' => 'create', 'script' => 'return checkMessage(\'データを再構成しますか？\n計画作成後に追加した同友を追加することができます。\');');
			$status['delete'] = array('button' => '削除', 'command' => 'delete', 'script' => 'return checkMessage(\'データを削除しますか？\n※削除したデータは復元できません。\');');
			$status['edit'] = array('button' => '編集', 'command' => 'edit');
			
			// 公開/非公開状態の判定
			// サマーキャンペーン
			if ($campaignInfo['summer_open'] == config::STATUS_DATA_OPEN) {
				$status['summer_open'] = array('status' => '公開済み', 'button' => '公開取消', 'command' => 'close');
			}
			elseif ($campaignInfo['summer_open'] == config::STATUS_DATA_CLOSE) {
				$status['summer_open'] = array('status' => '未公開', 'button' => '公開', 'command' => 'open');
			}
			
			// 秋のキャンペーン
			if ($campaignInfo['autumn_open'] == config::STATUS_DATA_OPEN) {
				$status['autumn_open'] = array('status' => '公開済み', 'button' => '公開取消', 'command' => 'close');
			}
			elseif ($campaignInfo['autumn_open'] == config::STATUS_DATA_CLOSE) {
				$status['autumn_open'] = array('status' => '未公開', 'button' => '公開', 'command' => 'open');
			}
			
			// 春のキャンペーン
			if ($campaignInfo['spring_open'] == config::STATUS_DATA_OPEN) {
				$status['spring_open'] = array('status' => '公開済み', 'button' => '公開取消', 'command' => 'close');
			}
			elseif ($campaignInfo['spring_open'] == config::STATUS_DATA_CLOSE) {
				$status['spring_open'] = array('status' => '未公開', 'button' => '公開', 'command' => 'open');
			}
		}
		
		// データ作成
		$listArray['data_list'][] = array(
			'year' => $i,
			'make_status'         => $status['make']['status'],
			'make_button'         => $status['make']['button'],
			'make_command'        => $status['make']['command'],
			'make_script'         => $status['make']['script'],
			'delete_button'       => $status['delete']['button'],
			'delete_command'      => $status['delete']['command'],
			'delete_script'       => $status['delete']['script'],
			'summer_open_status'  => $status['summer_open']['status'],
			'summer_open_button'  => $status['summer_open']['button'],
			'summer_open_command' => $status['summer_open']['command'],
			'autumn_open_status'  => $status['autumn_open']['status'],
			'autumn_open_button'  => $status['autumn_open']['button'],
			'autumn_open_command' => $status['autumn_open']['command'],
			'spring_open_status'  => $status['spring_open']['status'],
			'spring_open_button'  => $status['spring_open']['button'],
			'spring_open_command' => $status['spring_open']['command'],
			'edit_button'         => $status['edit']['button'],
			'edit_command'        => $status['edit']['command']
		);
	}
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makeCanpaignEditPage()
 * キャンペーン内容を編集するためのページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return variable
 * ----------------------------------------------------------
 */
function makeCanpaignEditPage($postArray, &$listArray) {

	$page = 'edit';

	// コマンド実行時
	if (isset($postArray['save'])) {
		
		// データ保存
		$ret = updateCampaignInfo($postArray);
		if ($ret !== 'success') {
			return 'db_write_fail';
		}
		
		// 計画作成画面に戻る
		//$url = './index.php?reg=campaign&type=create';
		//header("Location:".$url);
		//exit;
		
		return makeCanpaignCreatePage($postArray, $listArray);
	}

	// 全種目を取得(LM+LS対策：2015年移行はLM+LSを表示、LC、LEBは設定はしないので削除)
	$listArray['item_list'] = getItemList('no-insert','',$postArray['fiscal_year'],true,true,true);
	if (count($listArray['item_list']) == 0) {
		return 'no_item';
	}
	
	// キャンペーン参加同友を取得
	$listArray['executive_list'] = getExecutiveList($postArray['fiscal_year'], 'campaign');
	if (count($listArray['executive_list']) == 0) {
		return 'no_campaign_executive';
	}
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makeCanpaignGraphEditPage()
 * キャンペーン進捗グラフを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return variable
 * ----------------------------------------------------------
 */
function makeCanpaignGraphEditPage($postArray, &$listArray) {
	
	$page = 'graph_edit';

	// 削除ボタンを押した時にはデータベースから該当集計日の情報を削除
	if (isset($postArray['command']) && $postArray['command'] === 'delete') {
	
		$ret = deleteCampaignGraphData($postArray['id']);
		if ($ret !== 'success') {
			return 'db_write_fail';
		}
	}

	// グラフのデータを取得
	$listArray['data_list'] = getCampaignGraphData($postArray['fiscal_year'], $postArray['campaign']);
	if (count($listArray['data_list']) == 0) {
		return 'no_graph_data';
	}
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * makeCanpaignAreaReachRankPage()
 * キャンペーン毎の地区対抗戦達成率賞のページを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return variable
 * ----------------------------------------------------------
 */
 function makeCanpaignAreaReachRankPage($postArray, &$listArray) {
	
	$page = 'area_reach';

	// 作成済みのキャンペーンデータの年度を取得
	$listArray['fiscal_year_list'] = getFiscalYearList('campaign');
	if (count($listArray['fiscal_year_list']) == 0) {
		return 'no_data';
	}

	// 参加分母同友数を取得
	if (isset($postArray['fiscal_year'])) {
		$listArray['executive_list'] = getExecutiveList($postArray['fiscal_year'], 'campaign', '%', 'all');
	}
	else {
		$listArray['executive_list'] = getExecutiveList($listArray['fiscal_year_list'][0]['value'], 'campaign', '%', 'all');
	}
	if (count($listArray['executive_list']) == 0) {
		return 'no_campaign_executive';
	}

	// キャンペーンリストを作成
	$listArray['campaign_list'][] = array('value' => 'NONE',   'name' => '---');
	$listArray['campaign_list'][] = array('value' => 'summer', 'name' => config::SUMMER_CAMPAIGN_NAME);
	$listArray['campaign_list'][] = array('value' => 'autumn', 'name' => config::AUTUMN_CAMPAIGN_NAME);
	$listArray['campaign_list'][] = array('value' => 'spring', 'name' => config::SPRING_CAMPAIGN_NAME);

	// LM+LS対策：POSTで指定されていない場合は、最も新しい作成年度を設定しておく
	if (!isset($postArray['fiscal_year'])) {
		$postArray['fiscal_year'] = $listArray['fiscal_year_list'][0]['value'];
	}

	// LM+LS対策：全種目を取得(2015年移行はLM+LSを表示、LCはキャンペーンの地区対抗戦に含まれていないので削除、LEBは対抗戦に表示)
	$listArray['item_list'] = getItemList('','',config::CAMPAIGN_LMLS_ADD_YEAR, true, true, false);
	if (count($listArray['item_list']) == 0) {
		return 'no_item';
	}

	// LM+LS対策：2014年までの種目を取得
	$oldItemYear = config::CAMPAIGN_LMLS_ADD_YEAR - 1;
	$listArray['2014_item_list'] = getItemList('','',$oldItemYear);
	if (count($listArray['2014_item_list']) == 0) {
		return 'no_item';
	}
	
	// LM+LS対策：2014年以前で表示するとき$postArray['item']に$postArray['2014_item']の値に代入
	if ($postArray['fiscal_year'] < config::CAMPAIGN_LMLS_ADD_YEAR) {
		$postArray['item'] = $postArray['2014_item'];
	}

	// 地区一覧を取得
	$listArray['area_list'] = getAreaList();
	if (count($listArray['area_list']) == 0) {
		return 'no_area';
	}

	return $page;
}

/**
 * ----------------------------------------------------------
 * makeCanpaignDownloadSheetPage()
 * キャンペーン内容を編集するためのページを構成するための情報を取得
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @return variable
 * ----------------------------------------------------------
 */
function makeCanpaignDownloadSheetPage($postArray, &$listArray) {
	
	$page = 'download';

	// 作成済みのキャンペーンデータの年度を取得
	$listArray['fiscal_year_list'] = getFiscalYearList('campaign');
	if (count($listArray['fiscal_year_list']) == 0) {
		return 'no_data';
	}

	// 速報エクセルをアップロードする場合
	if ($postArray['upload']) {
		//printArray($_FILES);
		// ファイル名チェック
		if (strlen( $_FILES["input_file"]["name"]) == 0) {
			return 'no_input_file';
		}
		// ファイルサイズチェック
		if ( $_FILES["input_file"]["size"] == 0 ) {
			return 'error_filesize';
		}
		else {
			// アップロードファイルされたテンポラリファイルをファイル格納パスにコピー
			$filepath = $_SERVER["DOCUMENT_ROOT"].dirname($_SERVER["SCRIPT_NAME"]).'/'.config::TEMP_DIRECTORY_NAME.'/'.$_FILES["input_file"]["name"];
			//echo $filepath;

			// 以前のファイルを別名にて保存
			if (file_exists($filepath)) {
				$ctime = getCurrentTime('-');
				$rename_filepath = $filepath.'_bk'.$ctime;
				//echo $rename_filepath;
				if (!rename($filepath, $rename_filepath)){
					return 'file_backup_error';
				}
			}

			// 新規ファイルのアップロード
			$ret = @move_uploaded_file( $_FILES["input_file"]["tmp_name"], $filepath);
			chmod($filepath, 0666);	// 上書きできるように権限を付与
			if ( $ret !== true ) {
				return 'system_error';
			}

			// アップロードしたファイルをテンプレートとした生成済みファイルを削除
			// 正規表現でキャンペーン種別と年度を抜き出す
			preg_match("/([\d]{4})/", $_FILES["input_file"]["name"], $year);
			//printArray($year);

			$delete_filename = "";

			// 速報シートの場合
			if (strpos($_FILES["input_file"]["name"],'executive') === false) {
				if (strpos($_FILES["input_file"]["name"],'summer') !== false) {
					$delete_filename = "summer_".$year[0].".xlsx";
				}
				else if (strpos($_FILES["input_file"]["name"],'autumn') !== false){ 
					$delete_filename = "autumn_".$year[0].".xlsx";
				}
				else if (strpos($_FILES["input_file"]["name"],'spring') !== false) {
					$delete_filename = "spring_".$year[0].".xlsx";
				}
			}
			// 同友管理シートの場合
			else {
				if (strpos($_FILES["input_file"]["name"],'summer') !== false) {
					$delete_filename = "summer_".$year[0]."_challenge.xlsx";
				}
				else if (strpos($_FILES["input_file"]["name"],'autumn') !== false){ 
					$delete_filename = "autumn_".$year[0]."_challenge.xlsx";
				}
				else if (strpos($_FILES["input_file"]["name"],'spring') !== false) {
					$delete_filename = "spring_".$year[0]."_challenge.xlsx";
				}
			}
			// ファイル削除
			//echo $delete_filename."<br>";
			$filepath = $_SERVER["DOCUMENT_ROOT"].dirname($_SERVER["SCRIPT_NAME"]).'/'.config::TEMP_DIRECTORY_NAME.'/'.$delete_filename;

			// ファイルが存在する場合、削除実行
			if (file_exists($filepath)) {
				if (!unlink($filepath)){
					return 'file_delete_error';
				}
			}
		}
	}

	// キャンペーンリストを作成
	$listArray['campaign_list'][] = array('value' => 'NONE',   'name' => '---');
	$listArray['campaign_list'][] = array('value' => 'summer', 'name' => config::SUMMER_CAMPAIGN_NAME);
	$listArray['campaign_list'][] = array('value' => 'autumn', 'name' => config::AUTUMN_CAMPAIGN_NAME);
	$listArray['campaign_list'][] = array('value' => 'spring', 'name' => config::SPRING_CAMPAIGN_NAME);
	
	// Excelファイルの生成
	// キャンペーン速報と同友目標管理シートの両方を生成
	if (isset($postArray['fiscal_year']) && $postArray['campaign'] !== NONE) {
	
		if ($postArray['sokuho']) {
			// キャンペーン速報の生成
			if (local_config::$DB_TABLE_PREFIX === "kngw") {
				//$ret = makeCampaignDownloadSheet_for_kngw($postArray['fiscal_year'], $postArray['campaign'], "sokuho");
				$ret = makeCampaignDownloadSheet_for_kngw($postArray['fiscal_year'], $postArray['campaign'], "sokuho");
				if ($ret !== 'success') {
					return $ret;
				}
			}
			else {
				$ret = makeCampaignDownloadSheet($postArray['fiscal_year'], $postArray['campaign'], "sokuho");
				if ($ret !== 'success') {
					return $ret;
				}
			}
		}
		else if ($postArray['challenge']) {
			// 同友チャレンジシートの生成
			if (local_config::$DB_TABLE_PREFIX === "kngw") {
				$ret = makeCampaignDownloadSheet_for_kngw($postArray['fiscal_year'], $postArray['campaign'], "challenge");
				if ($ret !== 'success') {
					return $ret;
				}
			}
			else {
				$ret = makeExecutiveDownloadSheet($postArray['fiscal_year'], $postArray['campaign'], "challenge");
				if ($ret !== 'success') {
					return $ret;
				}
			}
		}
	}
	
	return $page;
}

/**
 * ----------------------------------------------------------
 * printCampaignTotalScoreTable()
 * 指定年度の全キャンペーンを表示するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * @param $campaign：キャンペーン種別
 * ----------------------------------------------------------
 */
function printCampaignTotalScoreTable($postArray, $listArray, $campaign) {

	$title = '';
	$start_month = '';
	$end_month = '';

	// ログインユーザ情報を取得
	$listArray['user_info'] = getUserInfo($_SESSION['USERID']);
	
	// キャンペーン情報を取得
	$campaignInfo = getCampaignDataInfo($postArray['fiscal_year']);
	
	// LM+LS対策：2014年以前の種目に変更
	if ($postArray['fiscal_year'] < config::CAMPAIGN_LMLS_ADD_YEAR) {
		$listArray['item_list'] = $listArray['2014_item_list'];
	}

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
	
	// 分母同友数を取得（参加率の計算以外で利用する数値、基準同友に基づく）
	$baseEnterableNum = count(getCampaignExecutiveEnterableList($postArray['fiscal_year'], '%', $campaign));

	// 参加率分母同友数を取得（退会(休会)を除いた数値）
	$ignoreRecessEnterableNum = count(getCampaignExecutiveEnterableList($postArray['fiscal_year'], '%', $campaign, 'recess'));
	
	echo '<table>';
	
	// 見出し
	echo '<tr class="bg_belize_hole"><td colspan="12">'.$postArray['fiscal_year'].'年度'.$title.'</td></tr>';
	// 公開されていない場合は終了
	if ($campaignInfo[$campaign.'_open'] == config::STATUS_DATA_CLOSE && $listArray['user_info']['auth'] != config::USER_ADMIN) {
		echo '<tr><td style="width:200px;">データが公開されていません。</td></tr>';
		echo '</table>';
		echo '<hr>';
		return;
	}
	
	// 項目
	echo '<tr class="bg_wet_asphalt">
			<td style="width:70px;">種目</td>
			<td style="width:70px;">単位</td>
			<td style="width:70px;">全国1同友<br />あたり計画</td>
			<td style="width:100px;">支部計画</td>
			<td style="width:70px;">分母同友数</td>
			<td class="bg_midnight" style="width:100px;">実績</td>
			<td class="bg_orange" style="width:70px;">参加同友数</td>
			<td class="bg_yellow" style="width:70px;">生産性得点</td>
			<td class="bg_red" style="width:70px;">参加率得点<br />(上限100点)</td>
			<td class="bg_green" style="width:70px;">達成率得点<br />(上限150点)</td>
			<td class="bg_alizarin" style="width:70px;">合計得点</td>
			<td class="bg_alizarin" style="width:70px;">優績判定</td>
		  </tr>';
	
	// 種目毎の数値を計算して表示
	$lmlsAveNum    = 0;
	$lmlsPlanNum   = 0;
	$lmlsResultNum = 0;
	unset($listArray['item_list'][0]);
	foreach ($listArray['item_list'] as $itemArray) {
	
		// 補助種目は表示しない、LEB、LCも表示しない
		if (strpos($itemArray['value'], ':sub_') !== false || $itemArray['value'] === 'LEB' || $itemArray['value'] === 'LC') {
			continue;
		}
	
		echo '<tr>';
		// 種目と単位
		echo '<td>'.$itemArray['value'].'</td><td>'.$itemArray['unit'].'</td>';
		
		// 各種目の計画値を取得
		$campaignInfo = getCampaignInfo($postArray['fiscal_year'], $itemArray['value']);
		
		$ave = $campaign.'_ave';
		$plan = $campaign.'_plan';
		
		// LM+LS対策：LM、LSの計画値は保存しておく
		if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
			$lmlsAveNum += $campaignInfo[$ave];
			$lmlsPlanNum += $campaignInfo[$plan];
		}
		else if ($itemArray['value'] === 'LM+LS') {
			$campaignInfo[$ave] = $lmlsAveNum;
			$campaignInfo[$plan] = $lmlsPlanNum;
		}
		
		// 全国1同友あたりの計画
		echo '<td class="right">'.formatNumber($campaignInfo[$ave], 1).'</td>';
		// 支部計画
		echo '<td class="right">'.formatNumber(floor($campaignInfo[$plan])).'</td>';
		
		// 分母同友数
		echo '<td>'.$ignoreRecessEnterableNum.'</td>';
		
		// 種目に該当する提携企業一覧を取得
		$partnerList = getPartnerList($postArray['fiscal_year'], $itemArray['value']);
		
		$resultNum = 0;
		$enterableNum = 0;
		foreach ($partnerList as $partnerArray) {
			// 指定したキャンペーンの実績を取得
			$resultData = getCampaignMonthTotalValue($postArray['fiscal_year'], $itemArray['value'], $partnerArray['value'], '%', 0, $start_month, $end_month);
			
			// 実績加算
			$resultNum += $resultData['result'];
			
			// LM+LS対策：LM、LSの実績値は保存しておく
			if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
				$lmlsResultNum += $resultData['result'];
			}
		
			// 参加同友は最大値を取得
			//if ($enterableNum < $resultData['count']) {
			//	$enterableNum = $resultData['count'];
			//}
		}
		
		// LM+LS対策：LM+LSの時は値をLMとLSを合算した値を戻す
		if ($itemArray['value'] === 'LM+LS') {
			$resultNum = $lmlsResultNum;
		}
		
		// LM、LSの場合は参加同友数をORで判定する
		if ($itemArray['value'] === 'LM') {
			$list = getPartnerList($postArray['fiscal_year'], 'LS');
			$partnerList = array_merge($partnerList, $list);
		}
		elseif ($itemArray['value'] === 'LS') {
			$list = getPartnerList($postArray['fiscal_year'], 'LM');
			$partnerList = array_merge($partnerList, $list);
		}
		
		// LM+LS対策：LM、LSの実績値は保存しておく
		if ($itemArray['value'] === 'LM+LS') {
			$lmList = getPartnerList($postArray['fiscal_year'], 'LM');
			$lsList = getPartnerList($postArray['fiscal_year'], 'LS');
			$partnerList = array_merge($lmList, $lsList);
		}
		//printArray($partnerList);
		
		// 実績表示
		echo '<td class="right">'.formatNumber($resultNum, 2).'</td>';
		
		// LM+LS対策：2015年移行はLM、LSをハイフンで表示、LEBもハイフンで表示
		if (($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') && $postArray['fiscal_year'] >= config::CAMPAIGN_LMLS_ADD_YEAR) {
			echo '<td>--</td><td>--</td><td>--</td><td>--</td><td>--</td><td>--</td>';
			continue;
		}
		
		// 参加同友数
		$enterableNum = getCampaignMonthTotalExecutiveResultCount($postArray['fiscal_year'], $partnerList, '%', $start_month, $end_month);
		if ($enterableNum > $baseEnterableNum) {
			$enterableNum = $baseEnterableNum;
		}
		echo '<td>'.$enterableNum.'</td>';
		
		// 生産性計算
		// 計算式：四捨五入(実績 / 分母同友数) / 全国1同友あたり計画
		//echo '生産性計算['.$itemArray['value'].']：実績='.$resultNum.' 分母同友数='.$baseEnterableNum.' 全国1同友あたり計画='.$campaignInfo[$ave].'<br>'; // デバッグ用
		$productPoint = round($resultNum / $baseEnterableNum, 1) / $campaignInfo[$ave] * 100;
		$productPoint = floor($productPoint);	// 切り捨て
		echo '<td>'.$productPoint.'</td>';
		
		// 参加率計算
		// 計算式：参加同友数 / 分母同友数
		// 参加率の計算の場合に限り、退会(休会)中の同友は母数のカウントには含めない
		//echo '参加率計算['.$itemArray['value'].']：参加同友数='.$enterableNum.' 分母同友数='.$ignoreRecessEnterableNum.'<br>'; // デバッグ用
		$enterablePoint = ($enterableNum / $ignoreRecessEnterableNum)*100;
		$enterablePoint = floor($enterablePoint);
		if ($enterablePoint > config::ENTERABLE_POINT_MAX) {
			$enterablePoint = config::ENTERABLE_POINT_MAX;
		}
		echo '<td>'.$enterablePoint.'</td>';
		
		// 達成率計算
		$reachPoint = ($resultNum / $campaignInfo[$plan])*100;
		$reachPoint = floor($reachPoint);
		if ($reachPoint > config::REACH_POINT_MAX) {
			$reachPoint = config::REACH_POINT_MAX;
		}
		echo '<td>'.$reachPoint.'</td>';
		
		// 合計点
		$totalPoint = $productPoint+$enterablePoint+$reachPoint;
		echo '<td>'.$totalPoint.'</td>';
		
		// 優績判定
		if ($reachPoint >= config::EXCELLENT_REACH_POINT_MAX && $totalPoint >= config::EXCELLENT_POINT_10_MAX) {
			echo '<td class="font_red">優績</td>';
		}
		else {
			echo '<td></td>';
		}
		echo '</tr>';
	}

	echo '</table>';
	echo '<hr>';
}

/**
 * ----------------------------------------------------------
 * printCampaignTotalValueTable()
 * 指定年度のキャンペーンを表示するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
function printCampaignTotalValueTable($postArray, $listArray) {

	$title = '';
	global $FLAG_CAMPAIGN_SEPARATE_PARTNER;

	// ログインユーザ情報を取得
	$listArray['user_info'] = getUserInfo($_SESSION['USERID']);
	
	// キャンペーン情報を取得
	$campaignDataInfo = getCampaignDataInfo($postArray['fiscal_year']);
	
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
	}

	// 公開されていない場合は終了
	if ($campaignDataInfo[$postArray['campaign'].'_open'] == config::STATUS_DATA_CLOSE && ($listArray['user_info']['auth'] != config::USER_ADMIN && $listArray['user_info']['auth'] != config::USER_EXEOFFICER) ) {
		echo '<p>'.$postArray['fiscal_year'].'年度 '.$title.'のデータは公開されていません。</p>';
		return;
	}

	// LM+LS対策：2014年以前の種目に変更
	if ($postArray['fiscal_year'] < config::CAMPAIGN_LMLS_ADD_YEAR) {
		$listArray['item_list'] = $listArray['2014_item_list'];
	}

	// 表示種目判定
	if ($postArray['item'] === 'ALL') {
		echo '<p>'.$postArray['fiscal_year'].'年度 '.$title.'の全種目の実績を表示しています。</p><br />';
	}
	else {
		echo '<p>'.$postArray['fiscal_year'].'年度 '.$title.'の'.convertItemName($postArray['item']).'の実績を表示しています。</p><br />';
	}
	
	//$starttime = microtime(true);
	//echo $starttime;

	//----------------------
	// 進捗グラフ
	//----------------------
	// グラフは全種目の場合のみ表示
	if ($postArray['item'] === 'ALL') {
		printCampaignProgressGraph($postArray);
	}

	// 補助種目一覧を取得し、メイン種目と結合
	$subitemList = getSubItemList('no-insert');
	if (count($subitemList) > 0) {
		$listArray['item_list'] = array_merge($listArray['item_list'], $subitemList);
	}

	//----------------------
	// 実績表示
	//----------------------
	// 全種目でループ
	$resultData = array();
	$lmResultData = array();
	$lsResultData = array();
	$areaResultData = array();
	$preResultData = array();
	$lmlsExcutiveResult = array();

	unset($listArray['item_list'][0]);
	foreach ($listArray['item_list'] as $itemArray) {

		// キャンペーンで分離する種目に該当する場合 → 計画、実績、達成率を標示
		// キャンペーンで分離する種目に該当しない場合 → 実績のみ標示
		if (in_array($itemArray['value'], local_config::CAMPAIGN_ITEM_COMBINE)) {
			$FLAG_CAMPAIGN_SEPARATE_PARTNER = true;
		}
		else {
			$FLAG_CAMPAIGN_SEPARATE_PARTNER = false;
		}

		// LM+LS対策：LM+LSの時はLM、LSでもループを回してデータを取得する
		if ($postArray['item'] !== 'ALL' && $postArray['item'] === 'LM+LS' && $itemArray['value'] !== 'LM+LS') {
			if ($itemArray['value'] !== 'LM' && $itemArray['value'] !== 'LS') {
				continue;
			}
			if ($itemArray['value'] === 'LM') {
				echo '<div style="display:none">';	// LM、LSを表示しないようにするため
			}
		}
		else if ($postArray['item'] === 'LM+LS' && $itemArray['value'] === 'LM+LS') {
			echo '</div>';	// LM、LSを表示しないようにしたdisplay:noneを元に戻して、LM+LSは表示するため
		}
		else {
			// 個別種目を表示する場合は指定している種目以外は表示しない
			if ($postArray['item'] !== 'ALL' && ($postArray['item'] !== $itemArray['value'])) {
				if (strpos($itemArray['value'], $postArray['item']) === false) {	// メイン種目の名称が含まれているかどうか判定
					continue;
				}
				else if ($itemArray['value'] === 'LM+LS' || $itemArray['value'] === 'LEB') {	// LM+LSだと含まれているのであえて特別に除去
					continue;
				}
				else if ($postArray['item'] === 'LE' && strpos($itemArray['value'], 'LEB:') !== false) {		// LEを選んでいるときはLEBを表示しない
					continue;
				}
				else if ($postArray['item'] === 'LEB' && strpos($itemArray['value'], 'LE:') !== false) {		// LEBを選んでいるときはLEを表示しない
					continue;
				}
			}
		}
		
		// 補助種目かどうか判定
		$flagSubItem = false;
		if (strpos($itemArray['value'], ':sub_') !== false) {
			$flagSubItem = true;
			$FLAG_CAMPAIGN_SEPARATE_PARTNER = false;	// 補助種目の時はオフにする
		}
		
		// 種目に該当する提携企業一覧を取得
		$partnerList = getPartnerList($postArray['fiscal_year'], $itemArray['value']);
		
		// カラム数をカウント
		if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
			$columnCnt = 7 + count($partnerList) * 3;
		}
		else {
			// LM+LS対策：LM+LSの時はLMとLSの列を作るだけ
			if ($itemArray['value'] === 'LM+LS') {
				$columnCnt = 9;
			}
			else {
				$columnCnt = 7 + count($partnerList);
			}
		}
		
		// 補助種目の場合はカラム数を減らす(優績などが不要のため)
		if ($flagSubItem) {
			$columnCnt -= 5;
		}
		
		// 更新時間を取得
		$update_time = getUpdateTime($postArray['fiscal_year'], $itemArray['value'], 'TOTAL', $postArray['campaign']);
		
		// 実績テーブル表示開始
		echo '<table>';
		echo '<tr class="bg_belize_hole"><td colspan="'.$columnCnt.'">'.convertItemName($itemArray['value']).'実績 (単位:'.$itemArray['unit'] .')'.
		     '&nbsp;&nbsp;[更新時間：'.$update_time.']</td></tr>';
		echo '<tr><td class="bg_wet_asphalt" style="width:100px;" rowspan="2">項目</td>';
		
		// 提携企業数分の列を追加
		foreach ($partnerList as $partnerArray) {
			if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
				echo '<td class="bg_wet_asphalt" style="width:250px;" colspan="3">'.$partnerArray['name'].'</td>';
			}
			else {
				echo '<td class="bg_wet_asphalt" style="width:120px;">'.$partnerArray['name'].'</td>';
			}
		}
		
		// LM+LS対策：LM+LSの時はLMとLSの列を作るだけ
		if ($itemArray['value'] === 'LM+LS') {
			echo '<td class="bg_wet_asphalt" style="width:120px;">LM</td>';
			echo '<td class="bg_wet_asphalt" style="width:120px;">LS</td>';
		}
		
		// 合計、優績関連の表示
		if (!$flagSubItem) {
			echo '<td class="bg_alizarin" style="width:250px;"colspan="3">合計</td>';

			// その他
			echo '<td class="bg_wet_asphalt" style="width:70px;" rowspan="2">分母<br />同友数</td>
				  <td class="bg_wet_asphalt" style="width:70px;" rowspan="2">参加同友数<br />(基準内)</td>';
			
			echo '<td class="bg_wet_asphalt" style="width:300px;" rowspan="2">優績シミュレーション</td>
				  </tr>';
		}
		else {
			// 補助種目は合計のみ
			echo '<td class="bg_alizarin" style="width:80px;">合計</td>';
		}
		
		// 提携企業数分の列を追加
		echo '<tr>';
		foreach ($partnerList as $partnerArray) {
		
			if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
				echo '<td class="bg_yellow" style="width:100px;">計画</td>
					  <td class="bg_red" style="width:100px;">実績</td>
					  <td class="bg_green" style="width:50px;">%</td>';
			}
			else {
				echo '<td class="bg_red" style="width:100px;">実績</td>';
			}
		}

		// LM+LS対策：LM+LSの時はLMとLSの列を作るだけ
		if ($itemArray['value'] === 'LM+LS') {
			echo '<td class="bg_red" style="width:100px;">実績</td>';
			echo '<td class="bg_red" style="width:100px;">実績</td>';
		}
		
		// 合計の実績の列を追加
		if (!$flagSubItem) {
			echo '<td class="bg_yellow" style="width:100px;">計画</td>
				  <td class="bg_red" style="width:100px;">実績</td>
				  <td class="bg_green" style="width:50px;">%</td>';
		}
		else {
			echo '<td class="bg_red" style="width:100px;">実績</td>';
		}
		echo '</tr>';

		//----------------------
		// 本部データ
		//----------------------
		echo '<tr><td>本部</td>';
		// LM+LS対策：LM+LSの値は保存しておいたLM、LSの値で生成
		if ($itemArray['value'] === 'LM+LS') {
			$resultData = array(
				'plan'                    => $honbu_lmResultData['plan']                   + $honbu_lsResultData['plan'],
				'result'                  => $honbu_lmResultData['result']                  + $honbu_lsResultData['result'],
				'base_enterable'          => $honbu_lmResultData['base_enterable'],
				'ignore_recess_enterable' => $honbu_lmResultData['ignore_recess_enterable'],
				'enterable'               => $honbu_lmResultData['enterable'],
				'type'                    => 'view'
			);
			echo '<td class="right">'.formatNumber($honbu_lmResultData['result'], 2).'</td>';	// LM実績
			echo '<td class="right">'.formatNumber($honbu_lsResultData['result'], 2).'</td>';	// LS実績
			echo '<td class="right">'.formatNumber($resultData['plan'], 2).'</td>';		// LM+LS計画合計
			echo '<td class="right">'.formatNumber($resultData['result'], 2).'</td>';	// LM+LS実績合計
			$ratio = ($resultData['result'] /  $resultData['plan']) * 100;
			if(is_nan($ratio) || is_infinite($ratio)) { 
				echo '<td class="center">----</td>';
			}
			else {
				echo '<td class="right">'.formatNumber($ratio, 1).'</td>';
			}
			echo '<td>'.$resultData['ignore_recess_enterable'].'</td>';
			echo '<td>'.$resultData['enterable'].'</td>';
		}
		else {
			$resultData = printCampaignPartnerResult($postArray['fiscal_year'], $postArray['campaign'], $itemArray['value'], '本部', $partnerList, false, "honbu");
			// LM+LS対策：LM、LSの結果を保存しておく
			if ($itemArray['value'] === 'LM') {
				$honbu_lmResultData = $resultData;
			}
			elseif ($itemArray['value'] === 'LS') {
				$honbu_lsResultData = $resultData;
			}
		}

		//----------------------
		// 優績判定
		//----------------------
		if (!$flagSubItem) {
			
			// LM+LS対策：2015年以降はLM、LSは優績判定をしない
			// LC、LEBも優績判定をしない。
			if ((($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') && $postArray['fiscal_year'] >= config::CAMPAIGN_LMLS_ADD_YEAR) 
			    || ($itemArray['value'] === 'LEB' || $itemArray['value'] === 'LC')) {
				$rowCnt = 2 + count($listArray['area_list']);
				echo '<td rowspan="'.$rowCnt.'">--------</td>';
			}
			else {
				$rowCnt = 2 + count($listArray['area_list']);
				$campaignExcellentPlan = getCampaignExcellentPlan($postArray['fiscal_year'], $postArray['campaign'], $itemArray, $resultData);
				echo '<td rowspan="'.$rowCnt.'" class="left">'.$campaignExcellentPlan.'</td>';
				
				// 進捗グラフ保存用達成率をhiddenで設定
				$reachRate = floor(($resultData['result'] / $resultData['plan']) * 100);
				echo '<input type="hidden" name="graph:'.$itemArray['value'].'" value="'.$reachRate.'">';
				echo '</tr>';
			}
		}

		//----------------------
		// 支部計
		//----------------------
		echo '<tr><td>支部計</td>';
		// LM+LS対策：LM+LSの値は保存しておいたLM、LSの値で生成
		if ($itemArray['value'] === 'LM+LS') {
			$resultData = array(
				'plan'                    => $lmResultData['plan']                    + $lsResultData['plan'],
				'result'                  => $lmResultData['result']                  + $lsResultData['result'],
				'base_enterable'          => $lmResultData['base_enterable'],
				'ignore_recess_enterable' => $lmResultData['ignore_recess_enterable'],
				'enterable'               => $lmResultData['enterable'],
				'type'                    => 'view'
			);
			echo '<td class="right">'.formatNumber($lmResultData['result'], 2).'</td>';	// LM実績
			echo '<td class="right">'.formatNumber($lsResultData['result'], 2).'</td>';	// LS実績
			echo '<td class="right">'.formatNumber($resultData['plan'], 2).'</td>';		// LM+LS計画合計
			echo '<td class="right">'.formatNumber($resultData['result'], 2).'</td>';	// LM+LS実績合計
			$ratio = ($resultData['result'] /  $resultData['plan']) * 100;
			if(is_nan($ratio) || is_infinite($ratio)) { 
				echo '<td class="center">---</td>';
			}
			else {
				echo '<td class="right">'.formatNumber($ratio, 1).'</td>';
			}
			echo '<td>'.$resultData['ignore_recess_enterable'].'</td>';
			echo '<td>'.$resultData['enterable'].'</td>';
		}
		else {
			$resultData = printCampaignPartnerResult($postArray['fiscal_year'], $postArray['campaign'], $itemArray['value'], '%', $partnerList);
			// LM+LS対策：LM、LSの結果を保存しておく
			if ($itemArray['value'] === 'LM') {
				$lmResultData = $resultData;
			}
			elseif ($itemArray['value'] === 'LS') {
				$lsResultData = $resultData;
			}
		}
		echo '</tr>';
		
		//----------------------
		// 全エリアの情報を取得
		//----------------------
		foreach ($listArray['area_list'] as $areaArray) {
			echo '<tr><td>'.$areaArray['name'].'</td>';
			// LM+LS対策：LM+LSの値は保存しておいたLM、LSの値で生成
			if ($itemArray['value'] === 'LM+LS') {
				//printArray($areaResultData);
				echo '<td class="right">'.formatNumber($areaResultData['LM'][$areaArray['value']]['result'], 2).'</td>';	// LM実績
				echo '<td class="right">'.formatNumber($areaResultData['LS'][$areaArray['value']]['result'], 2).'</td>';	// LS実績
				$result = $areaResultData['LM'][$areaArray['value']]['result']+$areaResultData['LS'][$areaArray['value']]['result'];
				$plan   = $areaResultData['LM'][$areaArray['value']]['plan']+$areaResultData['LS'][$areaArray['value']]['plan'];
				echo '<td class="right">'.formatNumber($plan, 2).'</td>';
				echo '<td class="right">'.formatNumber($result, 2).'</td>';
				$ratio = ($result /  $plan) * 100;
				if(is_nan($ratio) || is_infinite($ratio)) { 
					echo '<td class="center">----</td>';
				}
				else {
					echo '<td class="right">'.formatNumber($ratio, 1).'</td>';
				}
				echo '<td>'.$areaResultData['LM'][$areaArray['value']]['ignore_recess_enterable'].'</td>';
				echo '<td>'.$areaResultData['LM'][$areaArray['value']]['enterable'].'</td>';
			}
			else {
				// 各エリア事の計画、実績を表示
				$resultData = printCampaignPartnerResult($postArray['fiscal_year'], $postArray['campaign'], $itemArray['value'], $areaArray['value'], $partnerList);
				
				// LM+LS対策：LM+LSの値は保存しておく
				if ($itemArray['value'] === 'LM') {
					$areaResultData['LM'][$areaArray['value']] = $resultData;
				}
				elseif ($itemArray['value'] === 'LS') {
					$areaResultData['LS'][$areaArray['value']] = $resultData;
				}
			}
			echo '</tr>';
		}
		
		//----------------------
		// 昨年の支部計を取得
		//----------------------
		echo '<tr><td>前年度</td>';
		// LM+LS対策：LM+LSの値は保存しておいたLM、LSの値で生成
		if ($itemArray['value'] === 'LM+LS') {
			echo '<td class="right">'.formatNumber($preResultData['LM']['result'], 2).'</td>';	// LM実績
			echo '<td class="right">'.formatNumber($preResultData['LS']['result'], 2).'</td>';	// LS実績
			$result = $preResultData['LM']['result']+$preResultData['LS']['result'];
			$plan   = $preResultData['LM']['plan']+$preResultData['LS']['plan'];
			echo '<td class="right">'.formatNumber($plan, 2).'</td>';
			echo '<td class="right">'.formatNumber($result, 2).'</td>';
			$ratio = ($result /  $plan) * 100;
			if(is_nan($ratio) || is_infinite($ratio)) { 
				echo '<td class="center">----</td>';
			}
			else {
				echo '<td class="right">'.formatNumber($ratio, 1).'</td>';
			}
			echo '<td>'.$preResultData['LM']['ignore_recess_enterable'].'</td>';
			echo '<td>'.$preResultData['LM']['enterable'].'</td>';
		}
		else {
			// 昨年の計画、実績を取得
			$resultData = printCampaignPartnerResult($postArray['fiscal_year']-1, $postArray['campaign'], $itemArray['value'], '%', $partnerList, true);
				
			// LM+LS対策：LM+LSの値は保存しておく
			if ($itemArray['value'] === 'LM') {
				$preResultData['LM'] = $resultData;
			}
			elseif ($itemArray['value'] === 'LS') {
				$preResultData['LS'] = $resultData;
			}
		}
		echo '</tr>';
		
		echo '</table>';

		// 提携毎の更新時間を表示
		printPartnerInputTimeTable($postArray, $itemArray['value'], $postArray['campaign']);
		
		echo '<hr>';
		
		//$endtime = microtime(true);
		//echo $endtime - $starttime;
		
		// LM+LS対策：LM、LSの補助種目の同友の計算はLM+LSを個別に表示するときには計算しないようにする
		if ($postArray['item'] === 'LM+LS') {
			if (strpos($itemArray['value'], ':sub_') !== false) {
				continue;
			}
		}
		
		//---------------------------------------------
		// 種目が選択されている場合は、同友も表示
		//---------------------------------------------
		if ($postArray['item'] !== 'ALL') {
			
			// エリア毎に表示
			foreach ($listArray['area_list'] as $areaArray) {

				// 本部は実績を表示しないようにする
				if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
					if ($areaArray['value'] === config::HEADOFFICE_NAME){
						continue;
					} 
				}
			
				// カラム数をカウント
				if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
					$columnCnt = 5 + count($partnerList) * 3;
				}
				else if ($postArray['item'] === 'LM+LS') {
					$columnCnt = 7;
				}
				else {
					$columnCnt = 5 + count($partnerList);
				}
				
				// 補助種目の場合は実績のみ表示
				if ($flagSubItem) {
					$columnCnt -= 3;
				}
			
				echo '<table>';
				echo '<tr class="bg_turquoise"><td colspan="'.$columnCnt.'">'.$areaArray['name'].'</td></tr>';
				echo '<tr><td class="bg_wet_asphalt" style="width:180px;" rowspan="2">項目</td>';
				
				if (!$flagSubItem) {
					echo '<td class="bg_wet_asphalt" style="width:100px;" rowspan="2">キャンペーン<br />分母同友</td>';
				}	
				
				// 提携企業数分の列を追加
				if ($postArray['item'] === 'LM+LS') {
					echo '<td class="bg_wet_asphalt" style="width:120px;">LM</td>';
					echo '<td class="bg_wet_asphalt" style="width:120px;">LS</td>';
				}
				else {
					foreach ($partnerList as $partnerArray) {
					
						if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
							echo '<td class="bg_wet_asphalt" style="width:250px;" colspan="3">'.$partnerArray['name'].'</td>';
						}
						else {
							echo '<td class="bg_wet_asphalt" style="width:120px;">'.$partnerArray['name'].'</td>';
						}
					}
				}
				
				// 合計
				if (!$flagSubItem) {
					echo '<td class="bg_alizarin" style="width:250px;" colspan="3">合計</td></tr>';
				}
				else {
					echo '<td class="bg_alizarin" style="width:80px;">合計</td></tr>';
				}
				
				// 提携企業数分の列を追加
				echo '<tr>';
				if ($postArray['item'] === 'LM+LS') {
					echo '<td class="bg_red" style="width:100px;">実績</td>';
					echo '<td class="bg_red" style="width:100px;">実績</td>';
				}
				else {
					foreach ($partnerList as $partnerArray) {
						if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
							echo '<td class="bg_yellow" style="width:100px;">計画</td>';
						}
						echo '<td class="bg_red" style="width:100px;">実績</td>';
						if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
							echo '<td class="bg_green" style="width:50px;">%</td>';
						}
					}
				}
				
				// 合計の列を追加
				if (!$flagSubItem) {
					echo '<td class="bg_yellow" style="width:100px;">計画</td>
						  <td class="bg_red" style="width:100px;">実績</td>
						  <td class="bg_green" style="width:50px;">%</td>';
				}
				else {
					echo '<td class="bg_red" style="width:100px;">実績</td>';
				}
				echo '</tr>';
				
				// 同友毎の計画と実績を表示
				printCampaignExecutiveResult($postArray['fiscal_year'], $postArray['campaign'], $itemArray['value'], $areaArray['value'], $partnerList, '', $lmlsExcutiveResult);
				
				echo '</table>';
				echo '<hr>';
			}
		}
	}
}


/**
 * ----------------------------------------------------------
 * printCampaignPlanInputTable()
 * 指定年度のキャンペーンを計画入力ページを表示するテーブルを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
function printCampaignPlanInputTable($postArray, $listArray) {

	$title = '';
	$planTotal = 0;
	$resultTotal = 0;
	global $FLAG_CAMPAIGN_SEPARATE_PARTNER;
	
	// 保存ボタンが押された時
	if (isset($postArray['command']) && $postArray['command'] === 'save') {
		if (writeExecutiveCampaignPlan($postArray) != 'success'){
			return 'db_write_fail';
		}
	}

	// デバッグ用
	//printArray($postArray);
	//printArray($listArray);
	
	// キャンペーン種別判定
	switch($postArray['campaign']) {
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
	
	// 分母同友数を取得（参加率の計算以外で利用する数値、基準同友に基づく）
	$baseEnterableNum = count(getCampaignExecutiveEnterableList($postArray['fiscal_year'], '%', $postArray['campaign']));

	// 参加率の計算の場合に限り、退会(休会)中の同友は母数のカウントには含めない
	$ignoreRecessEnterableNum = count(getCampaignExecutiveEnterableList($postArray['fiscal_year'], '%', $postArray['campaign'], 'recess'));
	
	// タイトル表示
	if (isset($postArray['command']) && $postArray['command'] === 'input') {
		echo '<p>'.$postArray['fiscal_year'].'年度 '.$title.'の'.$postArray['item'].'の計画を入力し、「保存して計算」ボタンを押してください。</p><br />';
	}
	
	//$starttime = microtime(true);
	//echo $starttime;

	// 全種目でループ
	unset($listArray['item_list'][0]);
	foreach ($listArray['item_list'] as $itemArray) {
		
		// 個別種目を表示する場合は指定以外は表示しない
		if ($postArray['item'] !== 'ALL' && ($postArray['item'] !== $itemArray['value'])) {
			continue;
		}

		//echo "選択種目=".$itemArray['value']."<br>";
		
		// 指定種目の情報を取得
		$itemInfo = getItemList('no-insert', $itemArray['value']);
		
		// 種目に該当する提携企業一覧を取得
		$partnerList = getPartnerList($postArray['fiscal_year'], $itemArray['value']);
		
		// テーブルの列数計算
		if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
			$columnCnt = 3 + count($partnerList) * 2;
		}
		else {
			$columnCnt = 5;
		}
		
		// 実績テーブル表示開始
		echo '<table>';
		
		echo '<tr class="bg_belize_hole"><td colspan="'.$columnCnt.'">'.convertItemName($itemInfo[0]['value']).'計画 (単位:'.$itemInfo[0]['unit'] .')</td></tr>';
		echo '<tr><td class="bg_wet_asphalt" style="width:180px;" rowspan="2">項目</td>';
		
		
		if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
			// 提携企業数分の列を追加
			foreach ($partnerList as $partnerArray) {
				echo '<td class="bg_wet_asphalt" style="width:200px;" colspan="2">'.$partnerArray['name'].'</td>';
			}
		}
		else {
			// 合計を表示
			echo '<td class="bg_wet_asphalt" style="width:200px;" colspan="2">種目合計</td>';
		}

		echo '<td class="bg_wet_asphalt" style="width:70px;" rowspan="2">分母<br />同友数</td>';
		echo '<td class="bg_wet_asphalt" style="width:300px;" rowspan="2">優績シミュレーション<br />※「保存して計算」ボタンを押して更新</td>
			  </tr>';
		
		// 提携企業数分の列を追加
		echo '<tr>';
		if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
			foreach ($partnerList as $partnerArray) {
				echo '<td class="bg_yellow" style="width:100px;">昨年度実績</td>
					  <td class="bg_red" style="width:100px;">今年度計画</td>';
			}
		}
		else {
			echo '<td class="bg_yellow" style="width:100px;">昨年度実績</td>
				  <td class="bg_red" style="width:100px;">今年度計画</td>';
		}
		echo '</tr>';
		
		//-----------------------------------
		// 支部全体の計画状況を表示
		//-----------------------------------
		echo '<tr><td>支部全体</td>';
		foreach ($partnerList as $partnerArray) {
		
			// 昨年度の実績を取得
			$resultData = getCampaignMonthTotalValue($postArray['fiscal_year']-1, $itemArray['value'], $partnerArray['value'], '%', 0, $start_month, $end_month);
			$resultTotal += $resultData['result'];
			if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
				echo '<td>'.formatNumber($resultData['result'], 2).'</td>';
			}
			
			// 今年度の計画を取得する
			$planData = getCampaignPlanTotalValue($postArray['fiscal_year'], $itemArray['value'], $partnerArray['value'], '%', 0);
			$planTotal += $planData[$postArray['campaign'].'_plan'];
			if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
				echo '<td>'.formatNumber($planData[$postArray['campaign'].'_plan'], 2).'</td>';
			}
		}
		
		// 合計表示
		if (!$FLAG_CAMPAIGN_SEPARATE_PARTNER) {
			echo '<td>'.formatNumber($resultTotal, 2).'</td>';
			echo '<td>'.formatNumber($planTotal, 2).'</td>';
		}
		
		// キャンペーン参加分母同友数（基準内）
		echo '<td>'.$ignoreRecessEnterableNum.'</td>';
		
		// 優績目標
		// 今年度の計画を実績としてシミュレートする
		$data = array(
				'result'                  => $planTotal,
				'base_enterable'          => $baseEnterableNum,
				'ignore_recess_enterable' => $ignoreRecessEnterableNum,
				'enterable'               => $baseEnterableNum,
				'type'                    => 'input'
				);
		
		// 優績シミュレート
		$campaignExcellentPlan = getCampaignExcellentPlan($postArray['fiscal_year'], $postArray['campaign'], $itemArray, $data);
		echo '<td class="left">'.$campaignExcellentPlan.'</td>';
		echo '</tr>';
		
		
		echo '</table>';
		
		// プランの入力が完了していない場合は警告表示
		if ($planTotal == 0) {
			echo '<br /><p class="font_red">※キャンペーン計画の保存が完了していません。「保存して計算」を押してください。</p><br />';
		}
		
		echo '<hr>';
		
		//$endtime = microtime(true);
		//echo $endtime - $starttime;
		
		//-----------------------------------
		// 同友毎の計画値入力画面を表示
		//-----------------------------------
		// エリア毎に表示
		foreach ($listArray['area_list'] as $areaArray) {

			// 本部は計画を表示しないようにする
			if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
				if ($areaArray['value'] === config::HEADOFFICE_NAME){
					continue;
				} 
			}
		
			// テーブルの列数計算
			if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
				$columnCnt = 2 + count($partnerList) * 4;
			}
			else {
				$columnCnt = 3 + count($partnerList);
			}
		
			echo '<table>';
			echo '<tr class="bg_turquoise"><td colspan="'.$columnCnt.'">'.$areaArray['name'].'</td></tr>';
			echo '<tr><td class="bg_wet_asphalt" style="width:180px;" rowspan="2">項目</td>';
			echo '<td class="bg_wet_asphalt" style="width:100px;" rowspan="2">キャンペーン<br />分母同友</td>';
			
			// 提携企業数分の列を追加
			foreach ($partnerList as $partnerArray) {
				if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
					echo '<td class="bg_wet_asphalt" style="width:250px;" colspan="4">'.$partnerArray['name'].'</td>';
				}
				else {
					echo '<td class="bg_wet_asphalt" style="width:120px;">'.$partnerArray['name'].'</td>';
				}
			}
			if (!$FLAG_CAMPAIGN_SEPARATE_PARTNER) {
				echo '<td class="bg_alizarin" style="width:100px;" rowspan="2">今年度<br />キャンペーン計画</td>';
			}
			echo '</tr>';
			
			// 提携企業数分の列を追加
			echo '<tr>';
			foreach ($partnerList as $partnerArray) {
				if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
					echo '<td class="bg_yellow" style="width:100px;">昨年計画</td>';
				}
				echo '<td class="bg_red" style="width:100px;">昨年実績</td>';
				if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
					echo '<td class="bg_green" style="width:50px;">%</td>';
					echo '<td class="bg_alizarin" style="width:100px;">今年度<br />キャンペーン計画</td>';
				}
			}
			echo '</tr>';
			
			$lmlsExcutiveResult = array();
			// 提携企業数分の昨年の計画と実績を表示
			if ($postArray['command'] === 'input') {
				// 入力可能なテキストボックスを表示
				//echo $postArray['fiscal_year'].":".$postArray['campaign'].":".$itemArray['value'].":".$areaArray['value'];
				//printArray($partnerList);
				printCampaignExecutiveResult($postArray['fiscal_year'], $postArray['campaign'], $itemArray['value'], $areaArray['value'], $partnerList, 'input', $lmlsExcutiveResult);
			}
			else {
				// Read Onlyで表示
				printCampaignExecutiveResult($postArray['fiscal_year'], $postArray['campaign'], $itemArray['value'], $areaArray['value'], $partnerList, 'save', $lmlsExcutiveResult);
			}
			
			echo '</table>';
			echo '<hr>';
		}
	}
}

/**
 * ----------------------------------------------------------
 * printCampaignPartnerResult()
 * 指定年度の提携企業のキャンペーンを状況を表示するテーブルを表示
 * @param $fiscal_year：指定年度
 * @param $campaign：キャンペーン種別
 * @param $item：種目
 * @param $area：地域
 * @param $partnerList：提携企業一覧
 * @param $print：参加同友数を表示するかどうか
 * @param $target：honbu 本部用
 * @return array
 * ----------------------------------------------------------
 */
function printCampaignPartnerResult($fiscal_year, $campaign, $item, $area, $partnerList, $print=false, $target="") {

	$returnData = array();
	$planTotal = 0;
	$resultTotal = 0;
	$start_month = '';
	$end_month = '';
	global $FLAG_CAMPAIGN_SEPARATE_PARTNER;
	
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

	// 提携企業数分の計画、実績、達成率を取得
	$enterableNum = 0;
	$r_jaccs = array();
    $r_orico = array();
	foreach ($partnerList as $partnerArray) {
	
		// 計画
		$planColumn = $campaign.'_plan';
		$planData = getCampaignPlanTotalValue($fiscal_year, $item, $partnerArray['value'], $area);
		$planTotal += $planData[$planColumn];
		if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
			if ($area === config::HEADOFFICE_NAME || formatNumber($planData[$planColumn], 2) === "----") {
				echo '<td class="center">----</td>';
			}
			else {
				echo '<td class="right">'.formatNumber($planData[$planColumn], 2).'</td>';
			}
		}
		
		// 実績
		if ($area === config::HEADOFFICE_NAME) {
			// 本部の実績は支部の合計にする
			$resultData = getCampaignMonthTotalValue($fiscal_year, $item, $partnerArray['value'], '%', 0, $start_month, $end_month);
		}
		else {
			$resultData = getCampaignMonthTotalValue($fiscal_year, $item, $partnerArray['value'], $area, 0, $start_month, $end_month);
		}

		if (local_config::FEATURE_SELECT_LL_PRINT_TOTAL && $item === 'LL') {
			// ジャックスとジャックス1/2、オリコとオリコ1/2は合算してから四捨五入するために
			// ジャックスとオリコでそれぞれ月別の合計値を保存しておく
			if(strpos($partnerArray['name'],'ジャックス') !== false || strpos($partnerArray['name'],'ｼﾞｬｯｸｽ') !== false || strpos($partnerArray['name'],'ロートピア/J') !== false ){
				$r_jaccs[$start_month] += $resultData[$start_month.'_result'];
				$r_jaccs[$end_month]   += $resultData[$end_month.'_result'];
			}
			else {
				$r_orico[$start_month] += $resultData[$start_month.'_result'];
				$r_orico[$end_month]   += $resultData[$end_month.'_result'];
			}
		}
		else {
			$resultTotal += $resultData['result'];	// 月毎の実績を合算
		}
		echo '<td class="right">'.formatNumber($resultData['result'], 2).'</td>';
		
		// 達成率
		if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
			if ($planData[$planColumn] > 0) {
				$ratio = ($resultData['result'] /  $planData[$planColumn]) * 100;
				if(is_nan($ratio) || is_infinite($ratio)) { 
					echo '<td class="center">----</td>';
				}
				else {
					echo '<td class="right">'.formatNumber($ratio, 1).'</td>';
				}
			}
			else {
				echo '<td class="center">----</td>';
			}
		}
		
		// 参加数の最大値を取得
		//if ($enterableNum < $resultData['count']) {
		//	$enterableNum = $resultData['count'];
		//}
	}

	// 本部データを表示する場合
	if ($target === "honbu") {
		$campaignInfo = getCampaignInfo($fiscal_year, $item);
		$planTotal = $campaignInfo[$planColumn];
	}

	// LLの時だけ
	// ジャックスとジャックス1/2、オリコとオリコ1/2は合算してから四捨五入する
	if (local_config::FEATURE_SELECT_LL_PRINT_TOTAL && $item === 'LL') {
		$resultTotal = round($r_jaccs[$start_month]) + round($r_jaccs[$end_month])
		             + round($r_orico[$start_month]) + round($r_orico[$end_month]);
	}

	// 補助種目かどうか判定
	$flagSubItem = false;
	if (strpos($item, ':sub_') !== false) {
		$flagSubItem = true;
	}

	// LM、LSの場合はキャンペーン参加同有数をORで判定
	if ($item === 'LM') {
		$list = getPartnerList($fiscal_year, 'LS');
		$partnerList = array_merge($partnerList, $list);
	}
	elseif ($item === 'LS') {
		$list = getPartnerList($fiscal_year, 'LM');
		$partnerList = array_merge($partnerList, $list);
	}
	
	// 合計の計画、実績、%を表示
	if (!$flagSubItem) {
		// 計画値があり、計画値を表示する種目に設定されているか、またはLM、LS以外、または分離する種目に設定されている場合は計画値を表示する。
		if ($planTotal >= 0 && (in_array($item, local_config::PLAN_OPEN_ITEM) || ($item !== 'LM' && $item !== 'LS') || in_array($item, local_config::CAMPAIGN_ITEM_COMBINE) )){ 
			// 計画がある場合
			echo '<td class="right">'.formatNumber($planTotal, 2).'</td>';
			echo '<td class="right">'.formatNumber($resultTotal, 2).'</td>';
			$ratio = ($resultTotal /  $planTotal) * 100;
			if(is_nan($ratio) || is_infinite($ratio)) { 
				echo '<td class="center">----</td>';
			}
			else {
				echo '<td class="right">'.formatNumber($ratio, 1).'</td>';
			}
		}
		else {
			// 計画がない場合
			echo '<td class="center">----</td>';
			echo '<td class="right">'.formatNumber($resultTotal, 2).'</td>';
			echo '<td class="center">----</td>';
		}

		// 本部の場合は支部の合計で分母同友数をカウントする
		if ($area === config::HEADOFFICE_NAME) {
			$area = "%";
		}

		// 分母同友数を取得（参加率の計算以外で利用する数値、基準同友に基づく）
		$baseEnterableNum = count(getCampaignExecutiveEnterableList($fiscal_year, $area, $campaign));
		// 参加率の計算の場合に限り、退会(休会)中の同友は母数のカウントには含めない
		$ignoreRecessEnterableNum = count(getCampaignExecutiveEnterableList($fiscal_year, $area, $campaign, 'recess'));
		echo '<td>'.$ignoreRecessEnterableNum.'</td>';
		
		// キャンペーン参加同友数を取得（期間中に販売実績がある同友数）
		$enterableNum = getCampaignMonthTotalExecutiveResultCount($fiscal_year, $partnerList, $area, $start_month, $end_month);
		if ($enterableNum > $baseEnterableNum) {
			$enterableNum = $baseEnterableNum;
		}
		
		// LM+LS対策：2015年からはLM、LSは参加同友数を表示しない
		if (($item === 'LM' || $item === 'LS') && 
		    ($fiscal_year >= config::CAMPAIGN_LMLS_ADD_YEAR || ($fiscal_year == config::CAMPAIGN_LMLS_ADD_YEAR-1 && $print == true))
		   ) {
			echo '<td>--</td>';
		}
		else {
			echo '<td>'.$enterableNum.'</td>';
		}
		
	}
	else {
		// 補助種目の場合は合計のみ
		echo '<td class="right">'.formatNumber($resultTotal, 2).'</td>';
	}
	
	// 戻り値生成
	$returnData = array(
		'plan'                    => $planTotal,
		'result'                  => $resultTotal,
		'base_enterable'          => $baseEnterableNum,
		'ignore_recess_enterable' => $ignoreRecessEnterableNum,
		'enterable'               => $enterableNum,
		'type'                    => 'view'
	);
	
	return $returnData;
}

/**
 * ----------------------------------------------------------
 * printCampaignExecutiveResult()
 * 指定年度の同友のキャンペーン状況を表示するテーブルを表示
 * 計画入力時は入力フォームを表示
 * @param $fiscal_year：指定年度
 * @param $campaign：キャンペーン種別
 * @param $item：種目
 * @param $area：地域
 * @param $partnerList：提携企業一覧
 * @param $type：表示種別（計画入力時はinputを指定）
 * @return array
 * ----------------------------------------------------------
 */
function printCampaignExecutiveResult($fiscal_year, $campaign, $item, $area, $partnerList, $type='', &$lmlsResult) {

	$planTotal = 0;
	$resultTotal = 0;
	global $FLAG_CAMPAIGN_SEPARATE_PARTNER;

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
	$executiveList = getExecutiveList($fiscal_year, 'campaign', '%', 'all');
	//printArray($executiveList);

	// 補助種目かどうか判定
	$flagSubItem = false;
	if (strpos($item, ':sub_') !== false) {
		$flagSubItem = true;
	}
	
	// キャンペーンの計画が未入力かどうか判定
	$flagInputFinished = false;
	if ($type === 'input') {
		foreach ($partnerList as $partnerArray) {
			$planData = getCampaignPlanTotalValue($fiscal_year, $item, $partnerArray['value'], '%');
			//printArray($planData);
			if ($planData[$campaign.'_plan'] > 0) {
				$flagInputFinished = true;	// 入力済みの項目有り
				break;
			}
		}
	}
	elseif ($type === 'save') {
		$flagInputFinished = true;	// 保存時は常にキャンペーンの側
	}

	// 指定した地域と一致する同友だけ処理
	foreach($executiveList as $executiveInfo) {
	
		// 脱退済みの同友は表示しない
		if ($executiveInfo['exitdate'] !== "0000-00-00") {

			// 退会済みの同友であっても、年度内は実績報告には表示する
			$expire = preg_split("/-/", $executiveInfo['exitdate']);
			if ( ($expire[0] <= $fiscal_year+1 && $expire[1] <= 3) || ($expire[0] <= $fiscal_year && $expire[1] > 3)){ 
				// 表示する
			}
			else {
				continue;
			}
		}

		// 初期化
		$planTotal = 0;
		$resultTotal = 0;
	
		if ($executiveInfo['area'] === $area) {
			// ログインしているユーザIDの行の背景色を変更
			if ($executiveInfo['name'] === $_SESSION["USERNAME"]) {
				echo '<tr class="bg_skyblue">';
			}
			else {
				echo '<tr>';
			}

			// 同友名称
			echo '<td class="left" id='.$executiveInfo['code'].'>'.$executiveInfo['name'].'</td>';
			
			if (!$flagSubItem) {
				// キャンペーンの参加の有無
				if ($executiveInfo[$campaign.'_enterable'] == 1) {
					echo '<td>◯</td>';
				}
				else {
					echo '<td></td>';
				}
			}
			
			// LM+LS対策：LM+LSの専用表示
			if ($item === 'LM+LS') {
				echo '<td class="right">'.formatNumber($lmlsResult[$executiveInfo['value']]['LM']['result'], 2).'</td>';
				echo '<td class="right">'.formatNumber($lmlsResult[$executiveInfo['value']]['LS']['result'], 2).'</td>';
				// LM、LSの合計値を取得
				$lmlsTotalPlanNum = $lmlsResult[$executiveInfo['value']]['LM']['plan']+$lmlsResult[$executiveInfo['value']]['LS']['plan'];
				$lmlsTotalResultNum = $lmlsResult[$executiveInfo['value']]['LM']['result']+$lmlsResult[$executiveInfo['value']]['LS']['result'];
				echo '<td class="right">'.formatNumber($lmlsTotalPlanNum, 2).'</td>';
				echo '<td class="right">'.formatNumber($lmlsTotalResultNum, 2).'</td>';
				echo '<td class="right">'.formatNumber(($lmlsTotalResultNum / $lmlsTotalPlanNum) * 100, 1).'</td>';
				continue;
			}
	
			// 提携企業数分の計画、実績、達成率を取得
			foreach ($partnerList as $partnerArray) {

				$last_year = 0;
				// 計画作成時は昨年のデータを取得
				if ($type === 'input' || $type === 'save') {
					$last_year = 1;
				}
			
				// 計画取得
				$planData = getCampaignPlanTotalValue($fiscal_year-$last_year, '', $partnerArray['value'], '', $executiveInfo['value']);
				$planTotal += $planData[$campaign.'_plan'];
				if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
					echo '<td class="right">'.formatNumber($planData[$campaign.'_plan'], 2).'</td>';
				}
				
				// 実績取得
				$resultData = getCampaignMonthTotalValue($fiscal_year-$last_year, $item, $partnerArray['value'], '', $executiveInfo['value'], $start_month, $end_month, false);
				$resultTotal += $resultData['result'];
				echo '<td class="right">'.formatNumber($resultData['result'], 2).'</td>';
				
				// LM+LS対策：計画と実績を保存
				$lmlsResult[$executiveInfo['value']][$item]['plan'] += $planData[$campaign.'_plan'];
				$lmlsResult[$executiveInfo['value']][$item]['result'] += $resultData['result'];
				
				// 分離版のみ
				if ($FLAG_CAMPAIGN_SEPARATE_PARTNER) {
					// 達成率計算
					if ($planData[$campaign.'_plan'] > 0) {
						echo '<td class="right">'.formatNumber(($resultData['result'] / $planData[$campaign.'_plan']) * 100, 1).'</td>';
					}
					else {
						echo '<td class="center">----</td>';
					}
					
					// 計画入力フォームを表示
					if ($type === 'input' || $type === 'save') {

						// 今年度のキャンペーン計画が入っていない場合は、今年度の年間計画から計画値を取得
						if ($flagInputFinished) {
							// 今年度のキャンペーン計画を取得
							$planData = getCampaignPlanTotalValue($fiscal_year, '', $partnerArray['value'], '', $executiveInfo['value']);
						}
						else {
							// 今年度の年間計画を取得
							$data = getExecutiveResultTotalValue($fiscal_year, $item, $area, $partnerArray['value'], $executiveInfo['value']);
							//printArray($data);
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
							$planData[$campaign.'_plan'] = $data[0][$start_month.'_plan'] + $data[0][$end_month.'_plan'];
						}

						if ($type === 'input') {
							echo '<td>';
							printTextBox($executiveInfo['value'].':'.$partnerArray['value'].':plan', '140', 'right', $planData[$campaign.'_plan']);
						}
						else {
							echo '<td class="right">';
							echo $planData[$campaign.'_plan'];
						}
						echo '</td>';
					}
				}
			}
			
			// 統合型は最初の提携企業に計画の合計値を入力する
			if (!$FLAG_CAMPAIGN_SEPARATE_PARTNER) {
				foreach ($partnerList as $partnerArray) {
					// 計画入力フォームを表示
					if ($type === 'input' || $type === 'save') {
						// 今年の計画を取得
						if ($flagInputFinished) {
							// キャンペーンの計画を取得
							$planData = getCampaignPlanTotalValue($fiscal_year, '', $partnerArray['value'], '', $executiveInfo['value']);
						}
						else {
							// 年間計画を取得
							$data = getExecutiveResultTotalValue($fiscal_year, $item, $area, 'TOTAL', $executiveInfo['value']);
							//printArray($data);
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
							$planData[$campaign.'_plan'] = $data[0][$start_month.'_plan'] + $data[0][$end_month.'_plan'];
						}
						
						// inputの場合はテキストボックス、それ以外はテキスト表示
						if ($type === 'input') {
							echo '<td>';
							printTextBox($executiveInfo['value'].':'.$partnerArray['value'].':plan', '140', 'right', $planData[$campaign.'_plan']);
						}
						else {
							echo '<td class="right">';
							echo $planData[$campaign.'_plan'];
						}
						echo '</td>';
					}
					
					break;	// 最初だけで終わり
				}
			}
			
			// 同友の提携企業毎の計画、実績の合計、達成率を表示
			if ($type === '') {
				if (!$flagSubItem) { 
					// 計画値があり、計画値を表示する種目に設定されているか、またはLM、LS以外、または分離する種目に設定されている場合は計画値を表示する。
					if ($planTotal >= 0 && (in_array($item, local_config::PLAN_OPEN_ITEM) || (($item !== 'LM' && $item !== 'LS') || in_array($item, local_config::CAMPAIGN_ITEM_COMBINE)) )){ 
						echo '<td class="right">'.formatNumber($planTotal, 2).'</td>';
						echo '<td class="right">'.formatNumber($resultTotal, 2).'</td>';
						echo '<td class="right">'.formatNumber(($resultTotal / $planTotal) * 100, 1, 'floor').'</td>';
					}
					else {
						// 計画がない場合
						echo '<td class="center">----</td>';
						echo '<td class="right">'.formatNumber($resultTotal, 2).'</td>';
						echo '<td class="center">----</td>';
					}
				}
				else {
					echo '<td class="right">'.formatNumber($resultTotal, 2).'</td>';
				}
			}
			echo '</tr>';
			
			// LM+LS用に合計値を保存
			$lmlsResult[$executiveInfo['value']][$item]['total_plan'] += $planTotal;
			$lmlsResult[$executiveInfo['value']][$item]['total_result'] += $resultTotal;
		}
	}
}

/**
 * ----------------------------------------------------------
 * printCampaignProgressGraph()
 * 指定年度の指定キャンペーンの進捗グラフを表示
 * @param $postArray：POSTで送られてきたパラメータ
 * ----------------------------------------------------------
 */
function printCampaignProgressGraph($postArray) {

	$rank = 1;	// グラフ幅ランク

	// データ読み込み
	$graphData = getCampaignGraphData($postArray['fiscal_year'], $postArray['campaign']);
	if (count($graphData) == 0){
		echo '<div id="graph">グラフを表示するためのデータがありません。</div>';
		return;
	}

	// グラフ表示
	echo '<div id="graph">';

	//-----------------------------
	// 種目一覧を表示
	//-----------------------------
	// LM+LS対策：全種目を取得(2015年移行はLM+LSを表示、LM、LS、LC、LEB削除)
	$itemList = getItemList('no-insert','',$postArray['fiscal_year'],true,true,true);
	
	foreach ($itemList as $itemArray) {
		
		// LM+LS対策：グラフにはLM、LSを表示しない
		if (($itemArray['name'] === 'LM' || $itemArray['name'] === 'LS') && $postArray['fiscal_year'] >= config::CAMPAIGN_LMLS_ADD_YEAR) {
			continue;
		}
		echo '<div style="padding-top:10px;height:23px;">'.$itemArray['name'].'</div>';
	}
	
	//-----------------------------
	// 日付ラベルを表示
	//-----------------------------
	$dateCnt = 0;
	$graphColor = array_reverse(config::$CAMPAIGN_GRAPH_COLOR);
	foreach ($graphData as $graphDataArray) {

		// 日付を表示
		$dateHeight = 10 + 20 * $dateCnt;
		$dateArray = explode('-', $graphDataArray['date']);
		$date = $dateArray[1].'/'.$dateArray[2];
		echo '<div style="position: absolute; top: '.$dateHeight.'px; left: 850px;"><font color='.$graphColor[$dateCnt].'>■</font>'.$date.'</div>';
		$dateCnt++;
	}
	
	// グラフ最大値計測
	$max = 0;
	foreach ($graphData as $graphDataArray) {
		$itemArray = explode('-', $graphDataArray['data']);	// 種目毎に分解
		foreach ($itemArray as $value) {
			// 種目名と達成率に分解
			$itemDataArray = explode(':', $value);
			$reachRate = $itemDataArray[1];
			
			if ($max < $reachRate) {
				$max = $reachRate;	// 最大値を格納
			}
		}
	}

	// ランク計測
	// 500%が今のところ最大
	if (100 < $max && $max <= 150) {
		$rank = 2/3;
	}
	elseif (150 < $max && $max <= 200) {
		$rank = 1/2;
	}
	elseif (200 < $max && $max <= 250) {
		$rank = 2/5;
	}
	elseif (250 < $max && $max <= 300) {
		$rank = 1/3;
	}
	elseif (300 < $max && $max <= 350) {
		$rank = 2/7;
	}
	elseif (350 < $max && $max <= 400) {
		$rank = 1/4;
	}
	elseif (400 < $max && $max <= 450) {
		$rank = 2/9;
	}
	elseif (450 < $max) {
		$rank = 1/5;
	}
	
	//---------------------------------------------------------------
	// 日付ごとのデータを表示
	// 新しいデータを一番下にして上に古いデータで色を重ね塗りしていく
	//---------------------------------------------------------------
	$graphData = array_reverse($graphData);	// 順序を逆順に
	$colorNo = count(config::$CAMPAIGN_GRAPH_COLOR) - count($graphData);
	$dateCnt = 0;
	foreach ($graphData as $graphDataArray) {
	
		$itemArray = explode('-', $graphDataArray['data']);	// 種目毎に分解
		
		$itemCnt = 0;
		foreach ($itemArray as $value) {
		
			// 高さを計算
			$height = 18 + 33 * $itemCnt;
		
			// 種目名と達成率に分解
			$itemDataArray = explode(':', $value);
			$reachRate = $itemDataArray[1];
			
			// LM+LS対策：グラフにはLM、LSを表示しない
			if (($itemDataArray[0] === 'LM' || $itemDataArray[0] === 'LS') 
			    && $postArray['fiscal_year'] >= config::CAMPAIGN_LMLS_ADD_YEAR) {
				continue;
			}
			
			// グラフ最大幅の設定
			if ($reachRate > 500) {
				$reachRate = 500;
			}
			
			// 某グラフ表示
			echo '<div style="width:'.$reachRate*7.6*$rank.'px; height:20px;background:'.config::$CAMPAIGN_GRAPH_COLOR[$colorNo+$dateCnt].';position: absolute; top: '.$height.'px; left: 50px;"></div>';
			$itemCnt++;
		}
		$dateCnt++;
	}

	// メモリ数値表示
	echo '<div style="position: absolute; top: -20px; left: 43px;">%</div>';
	echo '<div style="position: absolute; top: -20px; left: 195px;">'. 20/$rank .'%</div>';
	echo '<div style="position: absolute; top: -20px; left: 347px;">'. 40/$rank .'%</div>';
	echo '<div style="position: absolute; top: -20px; left: 499px;">'. 60/$rank .'%</div>';
	echo '<div style="position: absolute; top: -20px; left: 651px;">'. 80/$rank .'%</div>';
	echo '<div style="position: absolute; top: -20px; left: 800px;">'. 100/$rank .'%</div>';

	// メモリ線表示
	echo '<div style="width:152px;height:100%;border:1px solid #888; position: absolute; top: -1px; left: 50px;"></div>';
	echo '<div style="width:304px;height:100%;border:1px solid #888; position: absolute; top: -1px; left: 50px;"></div>';
	echo '<div style="width:456px;height:100%;border:1px solid #888; position: absolute; top: -1px; left: 50px;"></div>';
	echo '<div style="width:608px;height:100%;border:1px solid #888; position: absolute; top: -1px; left: 50px;"></div>';
	echo '<div style="width:759px;height:100%;border:1px solid #888; position: absolute; top: -1px; left: 50px;"></div>';
	
	// グラフの上限が100%になっても、100%は常に表示
	$linePos = (759 / (100/$rank)) * 100;
	echo '<div style="width:'.$linePos.'px;height:100%;border-right:2px dotted #ff0000; position: absolute; top: -1px; left: 50px;"></div>';
	
	if ($rank != 1 && $rank != 2/5) {
		$charPos = (757 / (100/$rank)) * 100 + 40;
		echo '<div style="position: absolute; top: -20px; left: '.$charPos.'px;">100%</div>';
	}
		
	echo '</div>';
}

/**
 * ----------------------------------------------------------
 * printCampaignAreaReachRank()
 * 指定年度のキャンペーンの地区対抗戦の達成率賞をの状況を表示
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $listArray：データベースの各テーブルのデータ
 * ----------------------------------------------------------
 */
 function printCampaignAreaReachRank($postArray, $listArray) {

	$title = '';

	// キャンペーン情報を取得
	$campaignInfo = getCampaignDataInfo($postArray['fiscal_year']);

	// キャンペーン種別判定
	$campaign = $postArray['campaign'];
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

	//------------------------------------
	// 全地域の情報を取得
	//------------------------------------
	unset($listArray['item_list'][0]);
	if (local_config::$DB_TABLE_PREFIX === "kngw" || local_config::$DB_TABLE_PREFIX === "sample") {
		unset($listArray['area_list'][count($listArray['area_list'])-1]);	// 神奈川支部の時だけ最後の支部名を取り除く
	}

	// 対象会員数を取得
	$baseEnterableNum['ALL'] = 0;
	$ignoreRecessEnterableNum['ALL'] = 0;
	foreach ($listArray['area_list'] as $areaArray) {

		if (isset($postArray['AllDoyu'])) {
			// 全同友数を取得(キャンペーンの分母、分母外を取得。休会は除く)
			//$ignoreRecessEnterableNum[$areaArray['value']] = count(getExecutiveList($postArray['fiscal_year'], 'campaign', $areaArray['value']));
			$ignoreRecessEnterableNum[$areaArray['value']] = count(getCampaignExecutiveEnterableList($postArray['fiscal_year'], $areaArray['value'], $campaign, 'non_recess'));

			//$tmp= getCampaignExecutiveEnterableList($postArray['fiscal_year'], $areaArray['value'], $campaign, 'non_recess');
			//var_dump($tmp);
		}
		elseif (isset($postArray['onlyBunboDoyu'])) {
			// 参加率分母同友数を取得（退会(休会)を除いた数値）
			$ignoreRecessEnterableNum[$areaArray['value']] = count(getCampaignExecutiveEnterableList($postArray['fiscal_year'], $areaArray['value'], $campaign, 'recess'));
		}
		
		// 支部計を計算
		$ignoreRecessEnterableNum['ALL'] += $ignoreRecessEnterableNum[$areaArray['value']];
	}

	// 種目毎の実績と計画を取得
	$dataArray = array();
	foreach ($listArray['item_list'] as $itemArray) {

		// LM+LS対策：LM+LSの時にはLSの参加同友数を代入
		if ($itemArray['value'] === 'LM+LS') {
			//$enterableNum[$itemArray['value']]['ALL'] = $enterableNum['LS']['ALL'];
			$lmList = getPartnerList($postArray['fiscal_year'], 'LM');
			$lsList = getPartnerList($postArray['fiscal_year'], 'LS');
			$partnerList = array_merge($lmList, $lsList);

			// 参加率を計算するための同友数を取得($joinをfalseにして参加同友に設定されてなくてもカウントする。期中退会・休会以外をカウント)
			$enterableNum[$itemArray['value']]['ALL'] = getCampaignMonthTotalExecutiveResultCount($postArray['fiscal_year'], $partnerList, '%', $start_month, $end_month, false);
		}
		else {
			// 提携企業一覧を取得
			$partnerList = getPartnerList($postArray['fiscal_year'], $itemArray['value']);

			// 参加率を計算するための同友数を取得($joinをfalseにして参加同友に設定されてなくてもカウントする。期中退会・休会以外をカウント)
			$enterableNum[$itemArray['value']]['ALL'] = getCampaignMonthTotalExecutiveResultCount($postArray['fiscal_year'], $partnerList, '%', $start_month, $end_month, false);
			//if ($enterableNum[$itemArray['value']]['ALL'] > $baseEnterableNum['ALL']) {
			//	$enterableNum[$itemArray['value']]['ALL'] = $baseEnterableNum['ALL'];
			//}
		}

		foreach ($listArray['area_list'] as $areaArray) {

			// LM+LS対策：LM+LSの時にはLSの参加同友数を代入
			//if ($itemArray['value'] === 'LM+LS') {
			//	$enterableNum[$itemArray['value']][$areaArray['value']] = $enterableNum['LS'][$areaArray['value']];
			//}
			//else {
				$enterableNum[$itemArray['value']][$areaArray['value']] = getCampaignMonthTotalExecutiveResultCount($postArray['fiscal_year'], $partnerList, $areaArray['value'], $start_month, $end_month, false);
				//if ($enterableNum[$itemArray['value']][$areaArray['value']] > $baseEnterableNum[$areaArray['value']]) {
				//	$enterableNum[$itemArray['value']][$areaArray['value']] = $baseEnterableNum[$areaArray['value']];
				//}
			//}
		}

		// 地域毎に実績を集計
		$allAreaResultNum = 0;
		$allAreaPlanNum = 0;
		foreach ($listArray['area_list'] as $areaArray) {

			// LM+LS対策：LM+LSの時には保存しておいた値を格納
			if ($itemArray['value'] === 'LM+LS') {
				$dataArray['result'][$itemArray['value']][$areaArray['value']] = $dataArray['result']['LM'][$areaArray['value']] + $dataArray['result']['LS'][$areaArray['value']];
				$dataArray['plan'][$itemArray['value']][$areaArray['value']] = $dataArray['plan']['LM'][$areaArray['value']] + $dataArray['plan']['LS'][$areaArray['value']];
				continue;
			}

			$resultNum = 0;
			$planNum = 0;
			foreach ($partnerList as $partnerArray) {
				// 指定したキャンペーンの実績と計画を取得
				$resultData = getCampaignMonthTotalValue($postArray['fiscal_year'], $itemArray['value'], $partnerArray['value'], $areaArray['value'], 0, $start_month, $end_month);
				$planData   = getCampaignPlanTotalValue($postArray['fiscal_year'], $itemArray['value'], $partnerArray['value'], $areaArray['value'], 0);
				
				// 実績と計画加算
				$resultNum += $resultData['result'];
				$planNum += $planData[$campaign.'_plan'];
			}

			// 表示用の配列に実績と計画を種目別地域ごとに格納
			$dataArray['result'][$itemArray['value']][$areaArray['value']] = $resultNum;
			$dataArray['plan'][$itemArray['value']][$areaArray['value']] = $planNum;
			// 地域毎の実績値を加算
			$allAreaResultNum += $resultNum;
			$allAreaPlanNum += $planNum;
		}
		// 全地域の合計値
		if ($itemArray['value'] === 'LM+LS') {
			// LM+LS対策：LM+LSの時には保存しておいた値を格納
			$dataArray['result'][$itemArray['value']]['ALL'] = $dataArray['result']['LM']['ALL'] + $dataArray['result']['LS']['ALL'];
			$dataArray['plan'][$itemArray['value']]['ALL'] = $dataArray['plan']['LM']['ALL'] + $dataArray['plan']['LS']['ALL'];
		}
		else {
			$dataArray['result'][$itemArray['value']]['ALL'] = $allAreaResultNum;
			$dataArray['plan'][$itemArray['value']]['ALL'] = $allAreaPlanNum;
		}
	}

	//------------------------------------
	// 地区対抗戦のテーブル表示
	//------------------------------------
	//printArray($enterableNum);				// デバッグ用
	//printArray($ignoreRecessEnterableNum);	// デバッグ用
	echo '<p>'.$postArray['fiscal_year'].'年度 '.$title.'「地区対抗戦 達成率賞」</p>';
	echo '<p class="font_red">※LEBは参考表示により、対抗戦の結果には含まれていません</p><br>';
	echo '<table>';

	// 対象会員数表示
	echo '<tr><td colspan="2">(対象会員数)</td><td>'.$ignoreRecessEnterableNum['ALL'].'</td>';
	foreach ($listArray['area_list'] as $areaArray) {
		echo '<td>('.$ignoreRecessEnterableNum[$areaArray['value']].')</td>';
	}
	echo '</tr>';
	
	// カラム項目
	echo '<tr class="bg_wet_asphalt">
			<td style="width:70px;">種目</td>
			<td style="width:70px;">区分</td>
			<td style="width:150px;">支部計</td>';
	foreach ($listArray['area_list'] as $areaArray) {
		echo '<td style="width:150px;">('.$areaArray['value'].')</td>';
	}
	echo '</tr>';

	// 全種目表示
	// 数値は小数点第一位を四捨五入し、整数で表示
	$ranking = array();
	foreach ($listArray['item_list'] as $itemArray) {

		$rankValue = array();

		// LMとLSは表示しない
		if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
			continue;
		}

		// 各種目の計画値を取得
		$campaignInfo = getCampaignInfo($postArray['fiscal_year'], $itemArray['value']);
		
		// 種目表示
		if ($itemArray['value'] === 'LEB') {
			echo '<tr><td rowspan="5">'.$itemArray['value'].'<br><p class="font_red">※参考表示</p></td>';
		}
		else {
			echo '<tr><td rowspan="5">'.$itemArray['value'].'</td>';
		}

		echo '<td>計画</td>';
		echo '<td>'.formatNumber($dataArray['plan'][$itemArray['value']]['ALL']).'</td>';
		foreach ($listArray['area_list'] as $areaArray) {
			echo '<td>'.formatNumber($dataArray['plan'][$itemArray['value']][$areaArray['value']]).'</td>';
		}
		echo '</tr><tr>';
		echo '<td>実績</td>';
		echo '<td>'.formatNumber($dataArray['result'][$itemArray['value']]['ALL']).'</td>';
		foreach ($listArray['area_list'] as $areaArray) {
			echo '<td>'.formatNumber($dataArray['result'][$itemArray['value']][$areaArray['value']]).'</td>';
		}
		echo '</tr><tr>';
		echo '<td>達成率</td>';
		$reach = $dataArray['result'][$itemArray['value']]['ALL'] / $dataArray['plan'][$itemArray['value']]['ALL']*100;
		echo '<td>'.formatNumber($reach, 1).'</td>';
		foreach ($listArray['area_list'] as $areaArray) {
			$reach = $dataArray['result'][$itemArray['value']][$areaArray['value']] / $dataArray['plan'][$itemArray['value']][$areaArray['value']]*100;
			echo '<td>'.formatNumber($reach, 1).'</td>';

			$rankValue[$areaArray['value']] = $reach;	// 「達成✕参加」用
		}
		echo '</tr><tr>';
		echo '<td>参加率</td>';
		$enterable = ($enterableNum[$itemArray['value']]['ALL'] / $ignoreRecessEnterableNum['ALL']) * 100;
		echo '<td>'.formatNumber($enterable, 1).'</td>';
		foreach ($listArray['area_list'] as $areaArray) {
			$enterable = ($enterableNum[$itemArray['value']][$areaArray['value']] / $ignoreRecessEnterableNum[$areaArray['value']])*100;
			echo '<td>'.formatNumber($enterable, 1).'</td>';

			$rankValue[$areaArray['value']] = $rankValue[$areaArray['value']] * $enterable;	// 「達成✕参加」用
		}
		echo '</tr><tr class="bg_yellow">';
		echo '<td>達成✕参加</td>';
		echo '<td></td>';
		//「達成✕参加」の順位を付ける
		arsort($rankValue);	// 「達成✕参加」の値でソート
		$i = 1;
		foreach ($rankValue as $key => $value) {
				$ranking[$itemArray['value']][$key] = $i++;	// 地域名で順位を付ける
		}
		// ランキングを表示
		foreach ($listArray['area_list'] as $areaArray) {
			echo '<td>'.$ranking[$itemArray['value']][$areaArray['value']].'</td>';
		}
		echo '</tr>';
	}

	// 地域別の合計ポイント計算
	$totalPoint = array();
	foreach ($listArray['area_list'] as $areaArray) {
		$totalPoint[$areaArray['value']] = 0;
		foreach ($listArray['item_list'] as $itemArray) {

			// LEBは対抗戦の合計値に入れない
			if ($itemArray['value'] === 'LEB') {
				continue;
			}

			$totalPoint[$areaArray['value']] += $ranking[$itemArray['value']][$areaArray['value']];
		}
	}

	echo '<tr class="bg_green">';
	echo '<td colspan="2">合計ポイント</td>';
	echo '<td></td>';
	foreach ($listArray['area_list'] as $areaArray) {
		echo '<td>'.$totalPoint[$areaArray['value']].'</td>';
	}
	echo '</tr>';

	// 合計ポイントでランク付け
	asort($totalPoint);
	//printArray($totalPoint);
	$rank = 1;
	$rankArray = array();
	$loop = 0;
	$prePoint = 0;
	$samePoint = 1;
	foreach ($totalPoint as $key => $value) {
		if ($loop == 0) {
			$totalPointRanking[$key] = $rank;		// 先頭の地域には1位を入れる
		}
		else {
			// 点数が同じ場合はランクを進めず、同じ順位を入れる
			if ($prePoint == $value) {
				$totalPointRanking[$key] = $rank;	// 地域名で順位を付ける
				$samePoint++;
			}
			// 点数が異なる場合だけ順位を進める
			else {
				if ($samePoint == 2) {
					$rank = 3;
				}
				else {
					$rank++;
				}
				$totalPointRanking[$key] = $rank;	// 地域名で順位を付ける
			}
		}
		$rankArray[$loop] = $rank;
		$prePoint = $value;
		$loop++;
	}

	// 合計ポイントのランキングを表示
	echo '<tr class="bg_alizarin">';
	echo '<td colspan="2">順位</td>';
	echo '<td></td>';
	foreach ($listArray['area_list'] as $areaArray) {
		echo '<td>'.$totalPointRanking[$areaArray['value']].'</td>';
	}
	echo '</tr>';

	// 賞金を表示
	echo '<tr class="bg_belize_hole">';
	echo '<td colspan="2">賞金</td>';
	echo '<td></td>';

	// 地区対抗戦 賞金情報を取得
	$data = getCampaignInfo($postArray['fiscal_year'], 'LM');
	$opt = $data[$campaign.'_opt'];
	// オプションデータ分離
	$optArray = preg_split("/-/", $opt);
	$areaItemAward = preg_split("/\,/", $optArray[0]);
	$areaTotalAward = preg_split("/\,/", $optArray[1]);
	$areaTotalAward_org = $areaTotalAward; // コピーを残す
	// 賞金計算
	$awardPrice = array();
	// 種目別の賞金を計算
	foreach ($listArray['item_list'] as $itemArray) {

		// LEBは賞金計算しない
		if ($itemArray['value'] === 'LEB') {
			continue;
		}

		foreach ($listArray['area_list'] as $areaArray) {
			$awardPrice[$areaArray['value']] += $areaItemAward[$ranking[$itemArray['value']][$areaArray['value']]];
		}
	}
	// ランキングの重複によって賞金を按分する処理
	if ($rankArray[0] == 1 && $rankArray[1] == 1 && $rankArray[2] == 1) {
		$areaTotalAward[1] = 20000;$areaTotalAward[2] = 20000;$areaTotalAward[3] = 20000;
	}
	elseif ($rankArray[0] == 1 && $rankArray[1] == 1 && $rankArray[2] == 3) {
		$areaTotalAward[1] = 25000;$areaTotalAward[2] = 25000;$areaTotalAward[3] = 10000;
	}
	elseif ($rankArray[0] == 1 && $rankArray[1] == 2 && $rankArray[2] == 2) {
		$areaTotalAward[1] = 30000;$areaTotalAward[2] = 15000;$areaTotalAward[3] = 15000;
	}
	//printArray($areaTotalAward);

	// 地域別の賞金を計算
	foreach ($listArray['area_list'] as $areaArray) {
		$awardPrice[$areaArray['value']] += $areaTotalAward[$totalPointRanking[$areaArray['value']]];
		echo '<td>'.$awardPrice[$areaArray['value']].'</td>';	// 賞金合計を表示
	}
	echo '</tr>';

	echo '</table><br />';

	echo '<br /><p>☆地区対抗（種目別）</p>';
	echo '<p>各地区各種目ごとの達成率に参加率をかけ、順位付けをする。</p>';
	echo '<p>1位 '.formatNumber($areaItemAward[1]).'円  2位 '.formatNumber($areaItemAward[2]).'円  3位 '.formatNumber($areaItemAward[3]).'円</p>';

	echo '<br /><p>☆地区対抗（総合成績）</p>';
	echo '<p>6種目順位の合計数の少ないものから順位付けする。</p>';
	echo '<p>1位 '.formatNumber($areaTotalAward_org[1]).'円  2位 '.formatNumber($areaTotalAward_org[2]).'円  3位 '.formatNumber($areaTotalAward_org[3]).'円</p>';
}

/**
 * ----------------------------------------------------------
 * getCampaignExcellentPlan()
 * 指定年度のキャンペーンの状況をシミュレート
 * @param $fiscal_year：指定年度
 * @param $campaign：キャンペーン種別
 * @param $item：種目
 * @param $resultData：実績データ
 * ----------------------------------------------------------
 */
function getCampaignExcellentPlan($fiscal_year, $campaign, $item, $resultData) {

	// LM+LS対策：LM+LSの計画値はLM、LSの合算値
	if ($item['value'] === 'LM+LS') {
		$lmCampaignInfo = getCampaignInfo($fiscal_year, 'LM');
		$lsCampaignInfo = getCampaignInfo($fiscal_year, 'LS');
		
		$campaignInfo = array(
			'fiscal_year'            => $lmCampaignInfo['fiscal_year'],
			'item'                   => 'LM+LS',
			'summer_ave'             => $lmCampaignInfo['summer_ave'] + $lsCampaignInfo['summer_ave'],
			'summer_plan'            => $lmCampaignInfo['summer_plan'] + $lsCampaignInfo['summer_plan'],
			'summer_enterable_under' => $lmCampaignInfo['summer_enterable_under'],
			'summer_enterable_upper' => $lmCampaignInfo['summer_enterable_upper'],
			'summer_target'          => $lmCampaignInfo['summer_target'],
			'autumn_ave'             => $lmCampaignInfo['autumn_ave'] + $lsCampaignInfo['autumn_ave'],
			'autumn_plan'            => $lmCampaignInfo['autumn_plan'] + $lsCampaignInfo['autumn_plan'],
			'autumn_enterable_under' => $lmCampaignInfo['autumn_enterable_under'],
			'autumn_enterable_upper' => $lmCampaignInfo['autumn_enterable_upper'],
			'autumn_target'          => $lmCampaignInfo['autumn_target'],
			'spring_ave'             => $lmCampaignInfo['spring_ave'] + $lsCampaignInfo['spring_ave'],
			'spring_plan'            => $lmCampaignInfo['spring_plan'] + $lsCampaignInfo['spring_plan'],
			'spring_enterable_under' => $lmCampaignInfo['spring_enterable_under'],
			'spring_enterable_upper' => $lmCampaignInfo['spring_enterable_upper'],
			'spring_target'          => $lmCampaignInfo['spring_target'],
		);
		//printArray($campaignInfo);
	}
	else {
		// 各種目の本部計画値、キャンペーン参加同友数、目標同友会得点を取得
		$campaignInfo = getCampaignInfo($fiscal_year, $item['value']);
	}

	$ave    = $campaign.'_ave';
	$plan   = $campaign.'_plan';
	
	$under  = $campaignInfo[$campaign.'_enterable_under'];
	$upper  = $campaignInfo[$campaign.'_enterable_upper'];
	$target = $campaignInfo[$campaign.'_target'];
	
	// デバッグ
	// echo '生産性=('.$resultData['result'].'/'.$resultData['base_enterable'].')/'.$campaignInfo[$ave].'<br />';
	// echo '参加率='.$resultData['enterable'].'/'.$resultData['ignore_recess_enterable'].'<br />';
	// echo '達成率='.$resultData['result'].'/'.$campaignInfo[$plan].'<br />';
	
	// 生産性計算
	// 計算式：四捨五入(実績 / 分母同友数) / 全国1同友あたり計画
	//$productPoint = ($resultData['result'] / ($resultData['base_enterable'] * $campaignInfo[$ave]))*100;
	$productPoint = round($resultData['result'] / $resultData['base_enterable'], 1) / $campaignInfo[$ave] * 100;
	$productPoint = floor($productPoint);	// 切り捨て
	//echo '分母'.$resultData['base_enterable'];

	// 参加率計算
	// 計算式：参加同友数 / 分母同友数
	// 参加率の計算の場合に限り、退会(休会)中の同友は母数のカウントには含めない
	$enterablePoint = ($resultData['enterable']*100 / $resultData['ignore_recess_enterable']) ;
	$enterablePoint = floor($enterablePoint);
	if ($enterablePoint > config::ENTERABLE_POINT_MAX) {
		$enterablePoint = config::ENTERABLE_POINT_MAX;
	}
	
	// 達成率計算
	// 計算式：実績 / 計画
	$reachPoint = ($resultData['result'] / $campaignInfo[$plan])*100;
	$reachPoint = floor($reachPoint);
	if ($reachPoint > config::REACH_POINT_MAX) {
		$reachPoint = config::REACH_POINT_MAX;
	}
	
	// 合計点
	$totalPoint = $productPoint+$enterablePoint+$reachPoint;

	//-------------------------------------------
	// ここから優績シミュレート
	//-------------------------------------------
	
	// 優績判定
	$P = $resultData['base_enterable'] * $campaignInfo[$ave];	// 生産性計算時の分母
	$R = $campaignInfo[$plan];									// 達成率計算時の分母

	// キャンペーンの設定をしていない場合はシミュレートを実施しない
	if ($P != 0 || $R != 0) {
	
	// 実績をXとすると、合計値Tを満たすXの値は
	// X = (TPR / (R+P)) / 100
	// で表すことができる。（P：生産性得点 R：達成率得点）
	// かつ、優績達成のためには X >= R を満たさなければならない。
	
	// 優績シミュレーション 「上限参加同友数」の場合
	$upperEnterablePoint = floor($upper/$resultData['ignore_recess_enterable']*100);
	$T = config::$EXCELLENT_POINT[$target] - $upperEnterablePoint - 2;	// 目標より少し低い点数を獲得するために必要な実績を算出する
	$remainUpperPoint = (($T*$P*$R) / ($R+$P)) / 100;
	$remainUpperPoint = ceil($remainUpperPoint);

	// 優績になる実績値を導出
	// ループでインクリメントしながら優績を獲得できる最低値の実績を探す
	$score = 0;
	$T = config::$EXCELLENT_POINT[$target] - $upperEnterablePoint;
	while (1) {
		$remainUpperPoint++;
		$score = floor(round($remainUpperPoint / $resultData['base_enterable'], 1) / $campaignInfo[$ave] * 100) + floor(($remainUpperPoint/$campaignInfo[$plan])*100);
		if ($score >= $T) {
			break;
		}
	}

	if ($remainUpperPoint < $R) {
		$remainUpperPoint = $R - $resultData['result'];	// 達成率が不足している場合は、達成率を満たす数値を算出する
	}
	else {
		$remainUpperPoint = $remainUpperPoint - $resultData['result'];
	}
	//echo 'Sim Param T='.$T.' P='.$P.' R='.$R.' remainUpperPoint='.$remainUpperPoint.'<br />';	// デバッグ用
	
	// 優績シミュレーション 「下限参加同友数」の場合
	$underEnterablePoint = floor($under/$resultData['ignore_recess_enterable']*100);
	$T = config::$EXCELLENT_POINT[$target] - $underEnterablePoint - 2;	// 目標より少し低い点数を獲得するために必要な実績を算出する
	$remainUnderPoint = (($T*$P*$R) / ($R+$P)) / 100;
	$remainUnderPoint = ceil($remainUnderPoint);

	// 優績になる実績値を導出
	// ループでインクリメントしながら優績を獲得できる最低値の実績を探す
	$score = 0;
	$T = config::$EXCELLENT_POINT[$target] - $underEnterablePoint;
	while (1) {
		$remainUnderPoint++;
		$score = floor(round($remainUnderPoint / $resultData['base_enterable'], 1) / $campaignInfo[$ave] * 100) + floor(($remainUnderPoint/$campaignInfo[$plan])*100);
		if ($score >= $T) {
			break;
		}
	}

	if ($remainUnderPoint < $R) {
		$remainUnderPoint = $R - $resultData['result'];	// 達成率が不足している場合は、達成率を満たす数値を算出する
	}
	else {
		$remainUnderPoint = $remainUnderPoint - $resultData['result'];
	}

	// デバッグ用
	// echo config::$EXCELLENT_POINT[$target].'<br />';
	// echo $underEnterablePoint.'<br />';
	// echo '達成に必要な値='.$remainUnderPoint.'<br />';
	// echo '目標の合計値='.$resultData['result'].'<br />';

	//echo 'Sim Param T='.$T.' P='.$P.' R='.$R.' remainUnderPoint='.$remainUnderPoint.'<br />';	// デバッグ用
	
	//-------------------------------------------
	// 優績判定(15点、10点、5点の目標で判定)
	//-------------------------------------------
	$excllentMsg = '';
	$remainMsg = '';
	if ($target == 15) {
		if ($totalPoint >= config::EXCELLENT_POINT_15_MAX && $reachPoint >= config::EXCELLENT_REACH_POINT_MAX) {
			$excllentMsg = ' 達成！';
		}
		else {
			$remainMsg = 'まで';
		}
	}
	elseif ($target == 10) {
		if ($totalPoint >= config::EXCELLENT_POINT_10_MAX && $reachPoint >= config::EXCELLENT_REACH_POINT_MAX) {
			$excllentMsg = ' 達成！';
		}
		else {
			$remainMsg = 'まで';
		}
	}
	elseif ($target == 5) {
		if ($totalPoint >= config::EXCELLENT_POINT_10_MAX) {
			$excllentMsg = ' 達成！';
		}
		else {
			$remainMsg = 'まで';
		}
	}

	// 特別キャンペーン（2020年の夏に対応）
	if (local_config::FEATURE_FIXED_POINT_AND_RATE) {
		foreach (config::CAMPAIGN_FIXED_RATE_TYPE as $value) {
			if ($value === $fiscal_year.'_'.$campaign) {
				$target = 10;
				$remainMsg = '';
				$excllentMsg = ' 達成！';
			}
		}
	}
	
	// 出力用HTML生成
	$pointStr = '合計得点:'.$totalPoint.'点 (生:'.$productPoint.' 参:'.$enterablePoint.' 達:'.$reachPoint.')<br /><br />';
	
	// 優績を達成している場合にまだ残っていたらゼロにする
	if ($excllentMsg !== '') {
		if ($remainUpperPoint > 0) {
			$remainUpperPoint = 0;
		}
	}

	$plan = $pointStr.
			'[優績'.$target.'点'.$remainMsg.']'.
			'<font class="font_red">'.$excllentMsg.'</font><br />'.
			'参加同友数'.$upper.'('.$upperEnterablePoint.'%)の場合：あと'.formatNumber($remainUpperPoint).$item['unit'].'<br />'.
			'参加同友数'.$under.'('.$underEnterablePoint.'%)の場合：あと'.formatNumber($remainUnderPoint).$item['unit'].'
			';
	}
	else {
	
		$plan = "優績シミュレートを有効にする場合は<br>「キャンペーン → 計画作成」<br>から設定してください。";
	
	} // 優績シミュレートはここまで ▲▲▲

	// 結果
	return $plan;
}

//=================================================================
// デザイン部
//=================================================================
?>

<?php
//echo '<p>GET '; var_dump($getArray).'</p>';
//echo '<p>POST '; var_dump($postArray).'</p>';
?>

<div id="contents_menu">

	<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'view') ?>"
	value="<?php echo '実績閲覧' ?>"
	onClick="location.href='./index.php?reg=campaign&type=view'">

	<?php
	// ユーザ情報取得
	$listArray['user_info'] = getUserInfo($_SESSION['USERID']);
	
	// 管理者と役員のみ表示
	if ($listArray['user_info']['auth'] == config::USER_ADMIN || $userInfo['auth'] == config::USER_EXEOFFICER) { ?>
		<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'input') ?>"
		value="<?php echo '計画入力' ?>"
		onClick="location.href='./index.php?reg=campaign&type=input'">
	<?php } ?>

	<?php
	// 管理者のみ表示
	if ($listArray['user_info']['auth'] == config::USER_ADMIN) { ?>
		<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'create') ?>"
		value="<?php echo '計画作成' ?>"
		onClick="location.href='./index.php?reg=campaign&type=create'">
	<?php } ?>
	
	<!-- <?php
	if ($listArray['user_info']['auth'] == config::USER_ADMIN || $listArray['user_info']['auth'] == config::USER_EXEOFFICER || $listArray['user_info']['auth'] == config::USER_EXECUTIVE) { ?>
		<?php
		// 地区対抗戦 達成率賞を表示するボタン
		if (local_config::FEATURE_AREA_REACH_AWARD) { ?>
		<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'area_reach') ?>"
		value="<?php echo '地区対抗戦' ?>"
		onClick="location.href='./index.php?reg=campaign&type=area_reach'">
		<?php } // FEATURE_AREA_REACH_AWARD ?>
	<?php }  ?> -->

	<?php
	if ($listArray['user_info']['auth'] == config::USER_ADMIN || $listArray['user_info']['auth'] == config::USER_EXEOFFICER || $listArray['user_info']['auth'] == config::USER_EXECUTIVE) { ?>
		<?php
		// キャンペーン速報をダウンロードする機能
		if (local_config::FEATURE_DOWNLOAD_CAMPAIGN_SHEET) { ?>
		<input type="button" class="menu <?php checkMenuSelected($getArray['type'], 'download') ?>"
		value="<?php echo '速報ダウンロード' ?>"
		onClick="location.href='./index.php?reg=campaign&type=download'">
		<?php } // FEATURE_DOWNLOAD_CAMPAIGN_SHEET ?>
	<?php }  ?>

</div>
<hr>

<?php
//---------------------------------------------
// キャンペーン表示画面
//---------------------------------------------
if ($page === 'view') { ?>

<script type="text/javascript">
	window.onload=changeSelectItemBoxDisplay;
</script>

<div id="contents_select">
	<form style="display:inline" method="POST" action="index.php?reg=campaign&type=<?php echo $getArray['type'] ?>">
		<?php printSelectBox($listArray['fiscal_year_list'], 'fiscal_year', 70, $postArray['fiscal_year'], 'changeSelectItemBoxDisplay()') ?>年度
		<?php printSelectBox($listArray['campaign_list'], 'campaign', 180, $postArray['campaign'], 'changeSelectItemBoxDisplay()') ?>
		<span id="all_item" style="width:100px;">
		<?php printSelectBox($listArray['item_list'], 'item', 100, $postArray['item']) ?>
		</span>
		<span id="2014_item" style="width:100px;display:none">
		<?php printSelectBox($listArray['2014_item_list'], '2014_item', 100, $postArray['item']) ?>
		</span>
		<span id="none_item" style="width:100px;display:none">
		<?php printSelectBox($listArray['none_item_list'], 'none_item', 100) ?>
		</span>
		<?php printSubmitButton('表示') ?>
		<input type="hidden" name="command" value="show">
	</form>
	<?php
	// グラフ編集、追加ボタンは管理者のみ
	if ($listArray['user_info']['auth'] == config::USER_ADMIN) {
	// グラフ編集ボタンは実績を表示している場合のみ
	if (($postArray['command'] === 'show' || $postArray['command'] === 'graph_input') && $postArray['campaign'] !== 'ALL' && $postArray['item'] === 'ALL') { ?>
	<form style="display:inline" method="POST" action="index.php?reg=campaign&type=graph_edit">
		<?php printSubmitButton('グラフを編集') ?>
		<input type="hidden" name="command" value="graph_edit">
		<input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>">
		<input type="hidden" name="campaign" value="<?php echo $postArray['campaign'] ?>">
		<input type="hidden" name="item" value="<?php echo $postArray['item'] ?>">
	</form>
	<form style="display:inline" method="POST" action="index.php?reg=campaign&type=<?php echo $getArray['type'] ?>" onsubmit="return checkMessage('データをグラフに追加しますか？');">
		<?php printSubmitButton('グラフに追加') ?>
		<input type="hidden" name="command" value="graph_input">
		<input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>">
		<input type="hidden" name="campaign" value="<?php echo $postArray['campaign'] ?>">
		<input type="hidden" name="item" value="<?php echo $postArray['item'] ?>">
	<?php }
	}
	?>
</div>
<hr>

<div id="contents">
	<?php
	// キャンペーン全体の画面を表示
	if (isset($postArray['command'])  && $postArray['command'] === 'show' && $postArray['campaign'] === 'ALL') { ?>
		<p><?php echo $postArray['fiscal_year'] ?>年度のキャンペーンの実績を表示しています。</p><br />
		<?php 
		printCampaignTotalScoreTable($postArray, $listArray, 'summer');	// 指定年度のサマーキャンペーンの実績を表示
		printCampaignTotalScoreTable($postArray, $listArray, 'autumn');	// 指定年度の秋のキャンペーンの実績を表示
		printCampaignTotalScoreTable($postArray, $listArray, 'spring');	// 指定年度の春のキャンペーンの実績を表示
		?>
	<?php }
	// キャンペーン個別の結果を表示
	elseif (isset($postArray['command'])  && $postArray['command'] === 'show' && $postArray['campaign'] !== 'ALL') { ?>
		<?php
		printCampaignTotalValueTable($postArray, $listArray);	// 指定キャンペーンの実績を表示
		?>
	<?php }
	// グラフ追加後の表示
	elseif (isset($postArray['command'])  && $postArray['command'] === 'graph_input' && $postArray['campaign'] !== 'ALL') { ?>
		<?php
		echo '<p>'.$listArray['msg_list'][0].'</p><br />';
		printCampaignTotalValueTable($postArray, $listArray);	// 指定キャンペーンの実績を表示
		?>
	<?php }
	// 未選択の場合
	else { ?>
		<p>実績を表示するキャンペーンの「年度」「キャンペーン名」「種目」を選択してください。</p><br />
	<?php } ?>
	
	<?php
	// グラフに追加ボタンが表示されている時はformタグを閉じる
	if (($postArray['command'] === 'show' || $postArray['command'] === 'graph_input') && $postArray['campaign'] !== 'ALL' && $postArray['item'] === 'ALL') { ?>
		</form>
	<?php } ?>
</div>

<?php }
//---------------------------------------------
// キャンペーン計画入力 画面
//---------------------------------------------
elseif ($page === 'input') { ?>

<div id="contents_select">
	<form style="display:inline" method="POST" action="index.php?reg=campaign&type=<?php echo $getArray['type'] ?>">
		<?php printSelectBox($listArray['fiscal_year_list'], 'fiscal_year', 70, $postArray['fiscal_year']) ?>年度
		<?php printSelectBox($listArray['campaign_list'], 'campaign', 180, $postArray['campaign']) ?>
		<?php printSelectBox($listArray['item_list'], 'item', 100, $postArray['item']) ?>
		<?php printSubmitButton('入力画面表示') ?>
		<input type="hidden" name="command" value="input">
	</form>
	<?php
	// 保存ボタンは計画入力画面を表示している場合のみ
	if ($postArray['command'] === 'input' && $postArray['campaign'] !== 'NONE' && $postArray['item'] !== 'NONE') { ?>
	<form style="display:inline" method="POST" action="index.php?reg=campaign&type=<?php echo $getArray['type']?>">
		<?php printSubmitButton('保存して計算') ?>
		<input type="hidden" name="command" value="save">
		<input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>">
		<input type="hidden" name="campaign" value="<?php echo $postArray['campaign'] ?>">
		<input type="hidden" name="item" value="<?php echo $postArray['item'] ?>">
	<?php } ?>
	<?php
	// 一括入力ボタンは計画入力画面を表示している場合のみ
	if ($postArray['command'] === 'input' && $postArray['campaign'] !== 'NONE' && $postArray['item'] !== 'NONE') { ?>
		<?php printButton('一括入力', '', 'openBundleInputWindow()') ?>
	<?php } ?>
</div>
<hr>

<div id="contents">

	<?php
	// 計画入力画面を表示
	if (isset($postArray['command'])  && $postArray['command'] === 'input' && $postArray['campaign'] !== 'NONE' && $postArray['item'] !== 'NONE') { ?>
		
		<?php
		printCampaignPlanInputTable($postArray, $listArray);	// 計画入力テーブルを表示
		?>
	
	<?php }
	// 保存完了画面を表示
	elseif (isset($postArray['command']) && $postArray['command'] === 'save') { ?>
		<p>計画の保存が完了しました。計画を変更する場合は「入力画面表示」ボタンを押してください。</p><br />	

		<?php
		printCampaignPlanInputTable($postArray, $listArray);	// 計画入力テーブル(ReadOnly)を表示
		?>
		
	<?php }
	// 未選択の場合
	else { ?>
		<p>計画を作成するキャンペーンの「年度」と「種目」を選択してください。</p><br />
	<?php } ?>
	
	</from>
</div>

<?php }
//---------------------------------------------
// キャンペーン計画作成 画面
//---------------------------------------------
elseif ($page === 'create') { ?>

<div id="contents">
	<p>キャンペーンの作成、公開を設定してください。提携企業、同友を年度の途中で追加した場合は「再構成」ボタンを押してください。</p>
	<p>昨年と、今年度を含む5年分の計画の設定を行うことができます。</p><br />

	<table>
	<tr>
		<td class="bg_wet_asphalt" style="width:70px;">年度</td>
		<td class="bg_wet_asphalt" style="width:150px;" >キャンペーン名</td>
		<td class="bg_wet_asphalt" style="width:230px;" colspan="2">作成状態</td>
		<td class="bg_wet_asphalt" style="width:130px;">編集</td>
		<td class="bg_wet_asphalt" style="width:230px;" colspan="2">公開状態</td>
		<td class="bg_wet_asphalt" style="width:130px;">操作</td>
	</tr>
	
	<?php
	// 去年と今年を含む5年分のデータを表示
	foreach ($listArray['data_list'] as $dataListArray) { ?>
	<tr>
		<td rowspan="3"><?php echo $dataListArray['year'] ?></td>
		<td><?php echo config::SUMMER_CAMPAIGN_NAME ?></td>
		
		<td style="width:100px;" rowspan="3"><?php echo $dataListArray['make_status'] ?></td>
		<td style="width:130px;" rowspan="3">
			<form method="POST" action="index.php?reg=campaign&type=<?php echo $getArray['type'] ?>" onsubmit="<?php echo $dataListArray['make_script'] ?>">
			<?php printSubmitButton($dataListArray['make_button'], '') ?>
			<input type="hidden" name="command" value="<?php echo $dataListArray['make_command'] ?>">
			<input type="hidden" name="fiscal_year" value="<?php echo $dataListArray['year'] ?>">
			</form>
		</td>

		<td>
			<?php if ($dataListArray['make_status'] === '作成済み') { ?>
				<form method="POST" action="index.php?reg=campaign&type=edit">
				<?php printSubmitButton($dataListArray['edit_button']) ?>
				<input type="hidden" name="command" value="<?php echo $dataListArray['edit_command'] ?>">
				<input type="hidden" name="fiscal_year" value="<?php echo $dataListArray['year'] ?>">
				<input type="hidden" name="campaign" value="summer">
				</form>
			<?php } ?>
		</td>
		
		<td style="width:100px;"><?php echo $dataListArray['summer_open_status'] ?></td>
		<td style="width:130px;">
			<?php if ($dataListArray['make_status'] === '作成済み') { ?>
				<form method="POST" action="index.php?reg=campaign&type=<?php echo $getArray['type'] ?>">
				<?php printSubmitButton($dataListArray['summer_open_button']) ?>
				<input type="hidden" name="command" value="<?php echo $dataListArray['summer_open_command'] ?>">
				<input type="hidden" name="fiscal_year" value="<?php echo $dataListArray['year'] ?>">
				<input type="hidden" name="campaign" value="summer">
				</form>
			<?php } ?>
		</td>
		
		<td rowspan="3">
			<?php if ($dataListArray['make_status'] === '作成済み') { ?>
				<form method="POST" action="index.php?reg=campaign&type=<?php echo $getArray['type'] ?>" onsubmit="<?php echo $dataListArray['delete_script'] ?>">
				<?php printSubmitButton($dataListArray['delete_button'], '') ?>
				<input type="hidden" name="command" value="<?php echo $dataListArray['delete_command'] ?>">
				<input type="hidden" name="fiscal_year" value="<?php echo $dataListArray['year'] ?>">
				</form>
			<?php } ?>
		</td>
	</tr>
	
	<tr>
		<td><?php echo config::AUTUMN_CAMPAIGN_NAME ?></td>

		<td>
			<?php if ($dataListArray['make_status'] === '作成済み') { ?>
				<form method="POST" action="index.php?reg=campaign&type=edit">
				<?php printSubmitButton($dataListArray['edit_button']) ?>
				<input type="hidden" name="command" value="<?php echo $dataListArray['edit_command'] ?>">
				<input type="hidden" name="fiscal_year" value="<?php echo $dataListArray['year'] ?>">
				<input type="hidden" name="campaign" value="autumn">
				</form>
			<?php } ?>
		</td>
		
		<td style="width:100px;"><?php echo $dataListArray['autumn_open_status'] ?></td>
		<td style="width:130px;">
			<?php if ($dataListArray['make_status'] === '作成済み') { ?>
				<form method="POST" action="index.php?reg=campaign&type=<?php echo $getArray['type'] ?>">
				<?php printSubmitButton($dataListArray['autumn_open_button']) ?>
				<input type="hidden" name="command" value="<?php echo $dataListArray['autumn_open_command'] ?>">
				<input type="hidden" name="fiscal_year" value="<?php echo $dataListArray['year'] ?>">
				<input type="hidden" name="campaign" value="autumn">
				</form>
			<?php } ?>
		</td>
		
	</tr>
	
	<tr>
		<td><?php echo config::SPRING_CAMPAIGN_NAME ?></td>

		<td>
			<?php if ($dataListArray['make_status'] === '作成済み') { ?>
				<form method="POST" action="index.php?reg=campaign&type=edit">
				<?php printSubmitButton($dataListArray['edit_button']) ?>
				<input type="hidden" name="command" value="<?php echo $dataListArray['edit_command'] ?>">
				<input type="hidden" name="fiscal_year" value="<?php echo $dataListArray['year'] ?>">
				<input type="hidden" name="campaign" value="spring">
				</form>
			<?php } ?>
		</td>

		<td style="width:100px;"><?php echo $dataListArray['spring_open_status'] ?></td>
		<td style="width:130px;">
			<?php if ($dataListArray['make_status'] === '作成済み') { ?>
				<form method="POST" action="index.php?reg=campaign&type=<?php echo $getArray['type'] ?>">
				<?php printSubmitButton($dataListArray['spring_open_button']) ?>
				<input type="hidden" name="command" value="<?php echo $dataListArray['spring_open_command'] ?>">
				<input type="hidden" name="fiscal_year" value="<?php echo $dataListArray['year'] ?>">
				<input type="hidden" name="campaign" value="spring">
				</form>
			<?php } ?>
		</td>

	</tr>
	<?php } ?>
	
	</table>
	
</div>

<?php }
//---------------------------------------------
// キャンペーン編集画面
//---------------------------------------------
elseif ($page === 'edit') { ?>

<div id="contents">
	<p>キャンペーンの全国1同友あたりの計画と、支部計画を入力し、「編集完了」ボタンを押してください。</p><br />

	<form method="POST" action="index.php?reg=campaign&type=<?php echo $getArray['type']?>">
	<?php printSubmitButton('編集完了', 'save') ?>
	<input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>">
	<input type="hidden" name="campaign" value="<?php echo $postArray['campaign'] ?>">
	<hr>
	
	<?php
	// キャンペーン種別判定
	switch ($postArray['campaign']) {
		case 'summer':
			echo '<p>'.$postArray['fiscal_year'].'年度の'.config::SUMMER_CAMPAIGN_NAME.'の計画</p>';
			break;
		case 'autumn':
			echo '<p>'.$postArray['fiscal_year'].'年度の'.config::AUTUMN_CAMPAIGN_NAME.'の計画</p>';
			break;
		case 'spring':
			echo '<p>'.$postArray['fiscal_year'].'年度の'.config::SPRING_CAMPAIGN_NAME.'の計画</p>';
			break;
	}
	?>
	<br />
	<table>
		<tr>
			<td class="bg_wet_asphalt" style="width:80px;">種目</td>
			<td class="bg_wet_asphalt" style="width:80px;">単位</td>
			<td class="bg_wet_asphalt" style="width:100px;">全国1同友<br />あたり計画</td>
			<td class="bg_wet_asphalt" style="width:100px;">支部計画</td>
			<td class="bg_wet_asphalt" style="width:50px;">参加同友<br />(下限)</td>
			<td class="bg_wet_asphalt" style="width:50px;">参加同友<br />(上限)</td>
			<td class="bg_wet_asphalt" style="width:80px;">目標同友会得点<br />(ボーナス得点)</td>
		</tr>
		
		<?php 
		$lmlsAveNum = 0;
		$lmlsPlanNum = 0;
		$lmlsEnterableUnderNum = 0;
		$lmlsEnterableUpperNum = 0;
		$lmlsTargetNum = 0;
		foreach ($listArray['item_list'] as $itemArray) {
			// データベースから現在の値を取得
			$data = getCampaignInfo($postArray['fiscal_year'], $itemArray['value']);
			if (count($data) > 0) {
				$aveNum  = $data[$postArray['campaign'].'_ave'];
				$planNum = $data[$postArray['campaign'].'_plan'];
				$enterableUnderNum = $data[$postArray['campaign'].'_enterable_under'];
				$enterableUpperNum = $data[$postArray['campaign'].'_enterable_upper'];
				$targetNum         = $data[$postArray['campaign'].'_target'];
				
				// LM、LSの値は保存しておく
				if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
					$lmlsAveNum += $aveNum;
					$lmlsPlanNum += $planNum;
				}
				// LMの上限、下限、目標得点はLM+LSの値なので保存しておく
				if ($itemArray['value'] === 'LM') {
					$lmlsEnterableUnderNum = $enterableUnderNum;
					$lmlsEnterableUpperNum = $enterableUpperNum;
					$lmlsTargetNum = $targetNum;
				}
			}
			else {
				$aveNum  = 0;
				$planNum = 0;
			}
			
			// LM+LSの時に一時的に保存しておいたLM+LS用の値を戻して表示
			if ($itemArray['value'] === 'LM+LS') {
				$aveNum  = round($lmlsAveNum, 1);
				$planNum = round($lmlsPlanNum, 1);
				$enterableUnderNum = $lmlsEnterableUnderNum;
				$enterableUpperNum = $lmlsEnterableUpperNum;
				$targetNum = $lmlsTargetNum;
			}
		?>
		<tr>
			<td><?php echo $itemArray['value'] ?></td>
			<td><?php echo $itemArray['unit'] ?></td>
			
			<?php if ($itemArray['value'] === 'LM+LS') { ?>
			<td class="right"><?php echo $aveNum ?></td>
			<td class="right"><?php echo $planNum ?></td>
			<?php } else { ?>
			<td><?php printTextBox($itemArray['value'].'_ave', 100, 'right', $aveNum) ?></td>
			<td><?php printTextBox($itemArray['value'].'_plan', 100, 'right', $planNum) ?></td>
			<?php } ?>
			
			<?php
			// LM、LSは--で表示
			if (($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') && $postArray['fiscal_year'] >= config::CAMPAIGN_LMLS_ADD_YEAR) { ?>
				<td>--</td>
				<td>--</td>
				<td>--</td>
			<?php } else { ?>
				<td><?php printTextBox($itemArray['value'].'_enterable_under', 50, 'right', $enterableUnderNum) ?></td>
				<td><?php printTextBox($itemArray['value'].'_enterable_upper', 50, 'right', $enterableUpperNum) ?></td>
				<td><?php printSelectBox(config::$EXECUTIVE_BONUS_POINT, $itemArray['value'].'_target', 50, $targetNum) ?></td>
			<?php } ?>
		</tr>
		<?php } // foreach END
		?>
	</table>
	
	<br />
	<?php
	//-------------------------------------
	// 地区対抗達成率賞の設定
	//-------------------------------------
	if (local_config::FEATURE_AREA_REACH_AWARD) { ?>
	<?php
	// 達成率賞は全種目向けなので代表してLMに入れておく 
	$data = getCampaignInfo($postArray['fiscal_year'], 'LM');
	//printArray($data);
	if ($data[$postArray['campaign'].'_opt'] === '') {
		// 仮データを作る(DB未設定時に利用)
		$opt = 'areaItemAward,30000,15000,5000-areaTotalAward,30000,20000,10000-areaAllDoyuPrintChk,1-areaMustLCCnt,0-areaMustLCCntforAward,0';
	}
	else {
		$opt = $data[$postArray['campaign'].'_opt'];
	}
	// オプションデータ分離
	$optArray = preg_split("/-/", $opt);
	$areaItemAward = preg_split("/\,/", $optArray[0]);
	$areaTotalAward = preg_split("/\,/", $optArray[1]);
	$areaAllDoyuPrintChk = preg_split("/\,/", $optArray[2]);
	$areaMustLCCnt = preg_split("/\,/", $optArray[3]);
	$areaMustLCCntforAward = preg_split("/\,/", $optArray[4]);

	//printArray($optArray);
	//printArray($areaAllDoyuPrintChk);

	?>
	<p>地区対抗達成率賞の設定をしてください。</p>
	<table>
		<tr class="bg_wet_asphalt">
				<td style="width:150px;">項目</td>
				<td style="width:100px;">1位</td>
				<td style="width:100px;">2位</td>
				<td style="width:100px;">3位</td>
		</tr>
		<tr>
			<td>地区対抗達成率賞</td>
			<td><?php printTextBox('areaItemAward1', 100, 'right', $areaItemAward[1]) ?></td>
			<td><?php printTextBox('areaItemAward2', 100, 'right', $areaItemAward[2]) ?></td>
			<td><?php printTextBox('areaItemAward3', 100, 'right', $areaItemAward[3]) ?></td>
		</tr>
		<tr>
			<td>地区対抗総合成績</td>
			<td><?php printTextBox('areaTotalAward1', 100, 'right', $areaTotalAward[1]) ?></td>
			<td><?php printTextBox('areaTotalAward2', 100, 'right', $areaTotalAward[2]) ?></td>
			<td><?php printTextBox('areaTotalAward3', 100, 'right', $areaTotalAward[3]) ?></td>
		</tr>
	</table>
	<?php
	// 全同友を対象とするかどうか
	if ($areaAllDoyuPrintChk[1] === '1') {
		printCheckBox('areaAwardCheck', '全同友数を分母として速報シートを出力する', '1', '1');
	}
	else {
		printCheckBox('areaAwardCheck', '全同友数を分母として速報シートを出力する', '1');
	}?>

	<br /><br />
	<p>LCのキャッシュバック条件となるLCの枚数を設定をしてください。</p>
	<table>
		<tr class="bg_wet_asphalt">
				<td style="width:150px;">項目</td>
				<td style="width:100px;">LC必須枚数</td>
		</tr>
		<tr>
			<td>支部特別施策向け</td>
			<td><?php printTextBox('areaMustLCCnt', 100, 'right', $areaMustLCCnt[1]) ?></td>
		</tr>
		<tr>
			<td>達成賞向け</td>
			<td><?php printTextBox('areaMustLCCntforAward', 100, 'right', $areaMustLCCntforAward[1]) ?></td>
		</tr>
	</table>
	<?php } // END:FEATURE_AREA_REACH_AWARD ?>
	<br />
	<br />
	<p>同友一覧です。生産性、および参加率の分母となる同友の設定を確認してください。</p>
	<p class="font_red">※年度の計画に含まれる同友は前年度の1月1日時点で退会していない同友となります。<br>例：2019年度の計画に含まれる同友は2019年1月1日時点で退会していない同友</p>
	<br />
	<p><span style="width:120px;height:10px;">分母：</span>キャンペーンに参加している同友です。（※参加率、生産性ともにカウント対象）</p>
	<p><span style="width:120px;height:10px;">分母外：</span>キャンペーンに不参加の同友です。（※参加率、生産性ともにカウントしません）</p>
	<p><span style="width:120px;height:10px;">期中退会(休会)：</span>年度の計画に含まれている同友だが退会した同友、または休会している同友です。（※生産性のみカウントし、参加率には影響しません）</p>
	<p><span style="width:120px;height:10px;">期中退会(基準外)：</span>基準同友条件を満たさないまま期中退会した同友です。（※参加率、生産性ともにカウントしません）</p>
	<br />
	
	<table>
		<tr class="bg_wet_asphalt">
			<td style="width:50px;">コード</td>
			<td style="width:200px;">名称</td>
			<td style="width:100px;">地域</td>
			<td style="width:200px;">分母同友</td>
		</tr>
		<?php
		foreach ($listArray['executive_list'] as $executiveArray) {
			if ($executiveArray['auth'] != config::USER_EXECUTIVE) {
				continue;	// 同友以外の調整項目は飛ばす
			}
		?>
			<tr>
				<td><?php echo $executiveArray['code'] ?></td>
				<td class="left"><?php echo $executiveArray['name'] ?></td>
				<td><?php echo $executiveArray['area'] ?></td>
				<td>
				<?php
				echo '<input type="hidden" name="enterable:'.$executiveArray['value'].'" value="'.config::STATUS_UNENTERABLE.'">';
				// 同友のキャンペーン参加情報を取得
				$executiveCampaignInfo = getCampaignDataInfo($postArray['fiscal_year'], $executiveArray['value']);
				if ($executiveCampaignInfo[$postArray['campaign'].'_enterable'] == config::STATUS_ENTERABLE) {
					//printCheckBox('enterable:'.$executiveArray['value'], '', config::STATUS_ENTERABLE, true);
					printRadioButton('enterable:'.$executiveArray['value'], '分母', '1', true, config::ALERT_MSG1);
					printRadioButton('enterable:'.$executiveArray['value'], '分母外', '0', false, config::ALERT_MSG1);
					printRadioButton('enterable:'.$executiveArray['value'], '期中退会(休会)', '2', false, config::ALERT_MSG1);
					printRadioButton('enterable:'.$executiveArray['value'], '期中退会(基準外)', '3', false, config::ALERT_MSG1);
				}
				elseif ($executiveCampaignInfo[$postArray['campaign'].'_enterable'] == config::STATUS_UNENTERABLE) {
					//printCheckBox('enterable:'.$executiveArray['value'], '', config::STATUS_ENTERABLE, true);
					printRadioButton('enterable:'.$executiveArray['value'], '分母', '1', false, config::ALERT_MSG1);
					printRadioButton('enterable:'.$executiveArray['value'], '分母外', '0', true, config::ALERT_MSG1);
					printRadioButton('enterable:'.$executiveArray['value'], '期中退会(休会)', '2', false, config::ALERT_MSG1);
					printRadioButton('enterable:'.$executiveArray['value'], '期中退会(基準外)', '3', false, config::ALERT_MSG1);
				}
				elseif ($executiveCampaignInfo[$postArray['campaign'].'_enterable'] == config::STATUS_RECESS) {
					//printCheckBox('enterable:'.$executiveArray['value'], '', config::STATUS_ENTERABLE, true);
					printRadioButton('enterable:'.$executiveArray['value'], '分母', '1', false, config::ALERT_MSG1);
					printRadioButton('enterable:'.$executiveArray['value'], '分母外', '0', false, config::ALERT_MSG1);
					printRadioButton('enterable:'.$executiveArray['value'], '期中退会(休会)', '2', true, config::ALERT_MSG1);
					printRadioButton('enterable:'.$executiveArray['value'], '期中退会(基準外)', '3', false, config::ALERT_MSG1);
				}
				else {
					//printCheckBox('enterable:'.$executiveArray['value'], '', config::STATUS_ENTERABLE, false);
					printRadioButton('enterable:'.$executiveArray['value'], '分母', '1', false, config::ALERT_MSG1);
					printRadioButton('enterable:'.$executiveArray['value'], '分母外', '0', false, config::ALERT_MSG1);
					printRadioButton('enterable:'.$executiveArray['value'], '期中退会(休会)', '2', false, config::ALERT_MSG1);
					printRadioButton('enterable:'.$executiveArray['value'], '期中退会(基準外)', '3', true, config::ALERT_MSG1);
				}
				?>
				</td>
			</tr>
		<?php } ?>
	</table>

	</form>
</div>

<?php }
//---------------------------------------------
// キャンペーン進捗グラフ編集画面
//---------------------------------------------
elseif ($page === 'graph_edit') { ?>

<div id="contents">
	<p>キャンペーンの達成率グラフの集計日の一覧を編集し、「編集完了」ボタンを押してください。</p>
	<p>最大で7つの集計日をグラフに表示することができます。</p><br />

	<form method="POST" action="index.php?reg=campaign&type=view">
	<?php printSubmitButton('編集完了') ?>
	<input type="hidden" name="command" value="show">
	<input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>">
	<input type="hidden" name="campaign" value="<?php echo $postArray['campaign'] ?>">
	<input type="hidden" name="item" value="<?php echo $postArray['item'] ?>">
	</form>
	<hr>
	
	<?php
	// キャンペーン種別判定
	switch ($postArray['campaign']) {
		case 'summer':
			echo '<p>'.$postArray['fiscal_year'].'年度の'.config::SUMMER_CAMPAIGN_NAME.'のグラフに表示される集計日一覧</p>';
			break;
		case 'autumn':
			echo '<p>'.$postArray['fiscal_year'].'年度の'.config::AUTUMN_CAMPAIGN_NAME.'のグラフに表示される集計日一覧</p>';
			break;
		case 'spring':
			echo '<p>'.$postArray['fiscal_year'].'年度の'.config::SPRING_CAMPAIGN_NAME.'のグラフに表示される集計日一覧</p>';
			break;
	}
	?>
	<br />
	<table>
		<tr class="bg_wet_asphalt">
			<td style="width:50px;">No.</td>
			<td style="width:200px;">集計日</td>
			<td style="width:130px;">操作</td>
		</tr>
		<?php
		$cnt = 1;
		foreach ($listArray['data_list'] as $graphDataArray) { ?>
			<tr>
				<td><?php echo $cnt ?></td>
				<td><?php echo $graphDataArray['date'] ?></td>
				<td>
					<form method="POST" action="index.php?reg=campaign&type=graph_edit" onsubmit="return checkMessage('グラフから集計日を削除しますか？');">
					<?php printSubmitButton('削除') ?>
					<input type="hidden" name="command" value="delete">
					<input type="hidden" name="id" value="<?php echo $graphDataArray['id'] ?>">
					<input type="hidden" name="fiscal_year" value="<?php echo $postArray['fiscal_year'] ?>">
					<input type="hidden" name="campaign" value="<?php echo $postArray['campaign'] ?>">
					<input type="hidden" name="item" value="<?php echo $postArray['item'] ?>">
					</form>
				</td>
			</tr>
		<?php
			$cnt++;
		} ?>
	</table>
	
</div>

<?php }
//---------------------------------------------
// キャンペーン地区対抗戦 達成率賞画面
//---------------------------------------------
elseif ($page === 'area_reach') { ?>

<div id="contents_select">
	<form style="display:inline" method="POST" action="index.php?reg=campaign&type=<?php echo $getArray['type'] ?>">
		<?php printSelectBox($listArray['fiscal_year_list'], 'fiscal_year', 70, $postArray['fiscal_year']) ?>年度
		<?php printSelectBox($listArray['campaign_list'], 'campaign', 180, $postArray['campaign']) ?>
		<?php
		if ($listArray['user_info']['auth'] == config::USER_ADMIN) {
			printSubmitButton('全同友表示', 'AllDoyu');
			printSubmitButton('分母同友のみ表示', 'onlyBunboDoyu');
		}
		else {
			printSubmitButton('全同友表示', 'AllDoyu');
			//printSubmitButton('分母同友のみ表示', 'onlyBunboDoyu');
		} ?>
		<input type="hidden" name="command" value="area_reach">
	</form>
</div>
<hr>

<div id="contents">

	<?php
	// 表示ボタン押下
	if (isset($postArray['command'])  && $postArray['command'] === 'area_reach' && $postArray['campaign'] !== 'NONE') { ?>	
		<p class="font_red">※年度、キャンペーンを変更する場合はもう一度「表示」ボタンを押してください。</p><br />
		
		<?php
		printCampaignAreaReachRank($postArray, $listArray);
		?>

	<?php }
	// 未選択の場合
	else { ?>
		<p>地区対抗戦 達成率を生成する年度とキャンペーンをを選択し、「表示」ボタンを押してください。</p>
	<?php } ?>

</div>

<?php } 
//---------------------------------------------
// 速報シートダウンロード画面
//---------------------------------------------
elseif ($page === 'download') { ?>

<div id="contents_select">
	<form enctype="multipart/form-data" style="display:inline" method="POST" action="index.php?reg=campaign&type=<?php echo $getArray['type'] ?>">
		<table style="display:inline;"><tr style="border: none;"><td style="border: none;">
		<?php printSelectBox($listArray['fiscal_year_list'], 'fiscal_year', 70, $postArray['fiscal_year']) ?>年度
		<?php printSelectBox($listArray['campaign_list'], 'campaign', 180, $postArray['campaign']) ?>
		<?php printSubmitButton('速報シート', 'sokuho') ?>

		<?php
		$listArray['user_info'] = getUserInfo($_SESSION['USERID']);

		// 同友目標シート
		if ($listArray['user_info']['auth'] == config::USER_ADMIN || $listArray['user_info']['auth'] == config::USER_EXEOFFICER) {
			printSubmitButton('同友目標シート', 'challenge');
		}?>
		<input type="hidden" name="command" value="download">
		</td></tr></table>

		<?php
		if ($listArray['user_info']['auth'] == config::USER_ADMIN){  ?>
			<?php
			// キャンペーン速報用Excelシートをアップロード
			if (local_config::FEATURE_DOWNLOAD_CAMPAIGN_SHEET) { ?>
			&nbsp;<table style="display:inline"><tr>
			<td>&nbsp;速報用Excelの更新&nbsp;
			<input type="file" name="input_file">
			<?php printSubmitButton('アップロード', 'upload') ?>
			</td></tr></table>
			<?php } // FEATURE_DOWNLOAD_CAMPAIGN_SHEET ?>
		<?php }  ?>
	</form>
</div>
<hr>

<div id="contents">

	<?php
	// 速報シートアップロードボタン押下
	if ($postArray['upload']) {
		echo '<p>アップロードが完了しました。</p>';
	}
	// ダウンロードボタン押下
	else if (isset($postArray['command'])  && $postArray['command'] === 'download' && $postArray['campaign'] !== 'NONE') { ?>
	
		<p class="font_red">※年度、キャンペーンを変更する場合はもう一度Excel出力「速報シート」「同友目標シート」ボタンを押してください。</p><br />
		
		<?php
		// キャンペーン速報シートのダウンロードボタンを表示する
		if ($postArray['sokuho']) { ?>
			<p>キャンペーン速報ダウンロード</p>
			<input class="submit" type="button" value="ダウンロード"
			onClick="location.href='./<?php echo config::TEMP_DIRECTORY_NAME ?>/<?php echo $postArray['campaign'] ?>_<?php echo $postArray['fiscal_year'] ?>.xlsx'">
			<br />
		<?php } ?>

		<?php
		// 同友目標管理シートのダウンロードボタンを表示する
		if ($postArray['challenge']) {
			if ($listArray['user_info']['auth'] == config::USER_ADMIN || $listArray['user_info']['auth'] == config::USER_EXEOFFICER) { ?>
			<p>同友目標管理シート</p>
			<input class="submit" type="button" value="ダウンロード"
			onClick="location.href='./<?php echo config::TEMP_DIRECTORY_NAME ?>/<?php echo $postArray['campaign'] ?>_<?php echo $postArray['fiscal_year'] ?>_challenge.xlsx'">
			<?php }
		} ?>

	<?php }
	// 未選択の場合
	else { ?>
		<p>出力したいExcelファイルの種類のボタンを押してください。</p>
		<p class="font_red">※Excelファイルの作成が完了するまで1分程度かかります。操作せずお待ちください。</p><br><br>

		<?php
		if ($listArray['user_info']['auth'] == config::USER_ADMIN){
			if (local_config::$DB_TABLE_PREFIX === "kngw") { ?>
				<p>--------------------------------------------------------------------------</p>
				<p class="bold">【速報用Excelのアップロード方法】</p>
				<p>1. 速報でご利用のExcelファイルを用意します</p><br>
				<p>2. ファイル名を以下のようにします（2019年度の例）</p>
				<p>夏用 → template_campaign_2019_summer.xlsx</p>
				<p>秋用 → template_campaign_2019_autumn.xlsx</p>
				<p>春用 → template_campaign_2019_spring.xlsx</p><br>
				<p>3. 「ファイルを選択」し「アップロード」をクリックします</p>
				<p>※ファイル名の年度、シーズンを間違えないようご注意ください</p>
				<p>※間違った場合別のキャンペーンExcelが上書きされます</p><br>
				<p>4. ダウンロードして正常に数字が反映されているかご確認ください</p><br>
				<p>5. 修正する場合は上記を繰り返してください</p>
				<br>
				<p>--------------------------------------------------------------------------</p>
				<p class="bold">★同友目標シートのアップロード手順（2020/2/20）</p>
				<p>1. 過去にシステムからダウンロードした同友目標シートを用意します</p><br>
				<p>2. テンプレートにするシート同友1社分のみとし残りのシートは削除</p><br>
				<p>3. そのシート名を「0」とする</p><br>
				<p>4. 1～7行目、21行目～25行目はシステムから値が入るため変更不可となります</p><br>
				<p>5. テンプレートとして修正をした後にファイル名を以下のようにします（2019年度の例）</p>
				<p>夏用 → template_executive_2019_summer.xlsx</p>
				<p>秋用 → template_executive_2019_autumn.xlsx</p>
				<p>春用 → template_executive_2019_spring.xlsx</p><br>
				<p>6. 「ファイルを選択」し「アップロード」をクリックします</p>
				<p>※ファイル名の年度、シーズンを間違えないようご注意ください</p>
				<p>※間違った場合別のExcelが上書きされます</p><br>
				<p>7. ダウンロードして正常にテンプレートが反映されているかご確認ください</p><br>
				<p>8. 修正する場合は2.～7.をを繰り返してください</p>
			<?php }
			else { ?>
				<p>--------------------------------------------------------------------------</p>
				<p class="bold">★速報シートのアップロード手順（2020/1/15）</p>
				<p>1. 過去にシステムからダウンロードした速報シートを用意します</p><br>
				<p>2. 1番目のシートとして「（シーズン名）」、2番目のシートとして「monthly」があることを確認</p>
				<p>※（シーズン名）はsummer、autumn、springのいずれか</p>
				<p>※別のシーズン名が入っている場合はシート名を書き換える</p>
				<p>※この2つのシートはダウンロード後は非表示となっています</p>
				<p>※提携、同友などが変更になった場合はこの2つのシートについては全選択してセルの値をクリアしてください</p><br>
				<p>3. 上記2つのシートを参照するかたちで別のシートに速報用のエクセルを組みます</p><br>
				<p>4. ファイル名を以下のようにします（2019年度の例）</p>
				<p>夏用 → data_summer_2019.xlsx</p>
				<p>秋用 → data_autumn_2019.xlsx</p>
				<p>春用 → data_spring_2019.xlsx</p><br>
				<p>5. 「ファイルを選択」し「アップロード」をクリックします</p>
				<p>※ファイル名の年度、シーズンを間違えないようご注意ください</p>
				<p>※間違った場合別のキャンペーンExcelが上書きされます</p><br>
				<p>6. ダウンロードして正常に数字が反映されているかご確認ください</p><br>
				<p>7. 修正する場合は3.～6.をを繰り返してください</p><br>
				<p class="bold">【Excel作成時の注意点】</p>
				<p>・「条件付き書式」は書式が誤動作することがあります</p>
				<p>・「ウィンドウ枠の固定」でスクロールが動作しなくなることがあります</p>
				<p>上記が発生した場合は一旦「ウィンドウ枠の固定」を解除してみてください</p>
				<br>
				<p>--------------------------------------------------------------------------</p>
				<p class="bold">★同友目標シートのアップロード手順（2020/2/20）</p>
				<p>1. 過去にシステムからダウンロードした同友目標シートを用意します</p><br>
				<p>2. テンプレートにするシート同友1社分のみとし残りのシートは削除</p><br>
				<p>3. そのシート名を「0」とする</p><br>
				<p>4. 1～7行目、21行目～25行目はシステムから値が入るため変更不可となります</p><br>
				<p>5. テンプレートとして修正をした後にファイル名を以下のようにします（2019年度の例）</p>
				<p>夏用 → data_executive_summer_2019.xlsx</p>
				<p>秋用 → data_executive_autumn_2019.xlsx</p>
				<p>春用 → data_executive_spring_2019.xlsx</p><br>
				<p>6. 「ファイルを選択」し「アップロード」をクリックします</p>
				<p>※ファイル名の年度、シーズンを間違えないようご注意ください</p>
				<p>※間違った場合別のExcelが上書きされます</p><br>
				<p>7. ダウンロードして正常にテンプレートが反映されているかご確認ください</p><br>
				<p>8. 修正する場合は2.～7.をを繰り返してください</p>

			<?php } ?>
		<?php } ?>
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
 *  Copyright(c)2013 iSKET All Rights Reserved.
 * =================================================================
 */
?>