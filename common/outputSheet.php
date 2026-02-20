<?php
/**
 * =================================================================
 * outputSheet.php
 * シート出力用の関数群
 * =================================================================
 */

//--------------------------------
// include
//--------------------------------
require_once("local_config.php");
require_once("config.php");
require_once("mysql.php");
require_once("func.php");

/**
 * ----------------------------------------------------------
 * makeCampaignDownloadSheet()
 * 指定年度の指定キャンペーンの速報シートを生成（※他支部共通）
 * @param $fiscal_year：指定年度
 * @param $campaign：キャンペーン種別
 * ----------------------------------------------------------
 */
function makeCampaignDownloadSheet($fiscal_year, $campaign) {

	$ret = 'success';
	
	define("START_ROW", 5);
	define("START_COL", 3);
	define("AREA_ROW_NUM", local_config::EXCEL_ROW_NUM);

	//クライアント切断時に処理を実行するように指定
	ignore_user_abort(true);

	//スクリプトを強制終了させるまでの許容する最大時間(単位:秒)
	set_time_limit(0);

	// キャンペーン種別判定
	switch($campaign) {
		case 'summer':
			$title = config::SUMMER_CAMPAIGN_NAME;
			$start_month = config::SUMMER_CAMPAIGN_START_MONTH;
			$end_month = config::SUMMER_CAMPAIGN_END_MONTH;
			$before4_month = 4;
			$before3_month = 5;
			$season = '夏';
			break;
		case 'autumn':
			$title = config::AUTUMN_CAMPAIGN_NAME;
			$start_month = config::AUTUMN_CAMPAIGN_START_MONTH;
			$end_month = config::AUTUMN_CAMPAIGN_END_MONTH;
			$before4_month = 8;
			$before3_month = 9;
			$season = '秋';
			break;
		case 'spring':
			$title = config::SPRING_CAMPAIGN_NAME;
			$start_month = config::SPRING_CAMPAIGN_START_MONTH;
			$end_month = config::SPRING_CAMPAIGN_END_MONTH;
			$before4_month = 12;
			$before3_month = 1;
			$season = '春';
			break;
	}

	// 全種目を取得
	$itemList = getItemList('no-insert','',$fiscal_year, false, false, false);
	if (count($itemList) == 0) {
		return 'no_item';
	}
	//printArray($itemList);

	// 補助種目を取得
	$subitemList = getSubItemList('no-insert');
	if (count($subitemList) == 0) {
		return 'no_item';
	}
	//printArray($subitemList);

	// 特殊系種目を取得（LC保有枚数）
	$spitemList = getSpecialItemList();
	if (count($spitemList) == 0) {
		return 'no_item';
	}
	//printArray($spitemList);

	// 地域を取得
	$areaList = getAreaList();
	if (count($areaList) == 0) {
		return 'no_area';
	}
	//printArray($areaList);

	// 地域毎に同友一覧を取得
	if (local_config::FEATURE_OUTPUT_EXCELSHEET_ORDER_TYPE) {
		foreach ($areaList as $areaArray) {
			$executiveList[$areaArray['value']] = getExecutiveList($fiscal_year, 'campaign', $areaArray['value'], 'campaign');
			if (count($executiveList) == 0) {
				return 'no_campaign_executive';
			}
		}
	}
	// 同友コード順の場合は地域関係なく取得
	else {
		$executiveList['all'] = getExecutiveList($fiscal_year, 'campaign', '%', 'campaign');
		if (count($executiveList) == 0) {
			return 'no_campaign_executive';
		}
	}
	//printArray($executiveList);

	//---------------------------------------------------
	// Excel出力 初期設定
	//---------------------------------------------------
	// 出力用Excelファイル名
	//$outputFileName = 'data_'.$campaign.'_'.$fiscal_year.'.xlsx';
	$templateFileName = 'data_'.$campaign.'_'.$fiscal_year.'.xlsx';
	$outputFileName = $campaign.'_'.$fiscal_year.'.xlsx';

	// エクセルの更新時間よりも実績の更新時間が新しいときだけエクセル生成処理を実施する
	$result_updatetime = getUpdateTimestampFile("./tmp/update_timestamp.txt");
	$excel_updatetime = getUpdateTimestampFile("./tmp/".$outputFileName);

	// タイムスタンプを比較
	// エクセルのタイムスタンプが実績更新時間よりも新しい場合は何もしない
	if ($result_updatetime < $excel_updatetime) {
		return $ret;
	}

	// 罫線設定
	$borderSetting = array(
		'borders' => array(
		  'top'     => array('style' => PHPExcel_Style_Border::BORDER_THIN),
		  'bottom'  => array('style' => PHPExcel_Style_Border::BORDER_THIN),
		  'left'    => array('style' => PHPExcel_Style_Border::BORDER_THIN),
		  'right'   => array('style' => PHPExcel_Style_Border::BORDER_THIN)
		)
	);

	// 新しいエクセルファイルを作成する
	//$objPHPExcel = new PHPExcel();

	// テンプレートを読み込み
	// テンプレートファイルがない場合はエラーを通知
	$objReader = PHPExcel_IOFactory::createReader('Excel2007');
	$filepath = './'.config::TEMP_DIRECTORY_NAME.'/'.$templateFileName;
	if (file_exists($filepath)) {
		$objPHPExcelCampaign = $objReader->load($filepath);
	}
	else {
		return 'template_file_error';
	}

	//--------------------------------------
	// キャンペーン計画をシートに出力
	//--------------------------------------
	// キャンペーンの支部計画用シート
	//$objSheet = $objPHPExcel->createSheet(); 	// シートを追加
	$objPHPExcelCampaign->setActiveSheetIndex(0);
	$objSheet = $objPHPExcelCampaign->getActiveSheet();

	// 初期設定
	$objStyle = $objSheet->getDefaultStyle();	// スタイルを取得
	$objStyle->getFont()->setSize(9);			// フォントサイズを9ptにセット

	$objSheet->setTitle($campaign);				// キャンペーン名でシート名設定

	
	$objSheet->setCellValueByColumnAndRow(0, 1, getCurrentDate('/'));	// 現在日を設定
	$objSheet->setCellValueByColumnAndRow(1, 2, '種目');
	$objSheet->setCellValueByColumnAndRow(2, 2, '単位');
	$objSheet->setCellValueByColumnAndRow(3, 2, '全国1同友あたり計画');
	$objSheet->setCellValueByColumnAndRow(4, 2, '支部計画');

	$objSheet->getColumnDimensionByColumn(7)->setWidth(15);
	$objSheet->getColumnDimensionByColumn(8)->setWidth(10);
	$objSheet->getColumnDimensionByColumn(9)->setWidth(40);
	
	$cnt = START_COL;
	foreach ($itemList as $itemArray) {
		$data = getCampaignInfo($fiscal_year, $itemArray['value']);
		if (count($data) > 0) {
			$objSheet->setCellValueByColumnAndRow(1, $cnt, $itemArray['value']);
			$objSheet->setCellValueByColumnAndRow(2, $cnt, $itemArray['unit']);
			$objSheet->setCellValueByColumnAndRow(3, $cnt, $data[$campaign.'_ave']);
			$objSheet->setCellValueByColumnAndRow(4, $cnt, $data[$campaign.'_plan']);
		}
		// 次の種目へ
		$cnt++;
	}

	//--------------------------------------
	// キャンペーン：同友の計画を取得
	//--------------------------------------
	$cnt = START_ROW;
	$p_col = START_COL+7;
	$area_cnt = 1;
	// 同友毎の情報を取得してデータを出力
	foreach ($areaList as $areaArray) {
		// 本部を表示しないようにする
		//if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
			if ($areaArray['value'] === config::HEADOFFICE_NAME){
				continue;
			} 
		//}

		// 地区順同友コード順ではない場合
		if (!local_config::FEATURE_OUTPUT_EXCELSHEET_ORDER_TYPE) {
			$areaArray['value'] = 'all';	// allの中にすべての同友が入っている
		}

		// 同友毎の計画と実績を取得して表示
		foreach ($executiveList[$areaArray['value']] as $executiveArray) {

			//$objSheet->setCellValueByColumnAndRow(7, $cnt, $areaArray['value']);	 // 地域名をセット
			$objSheet->setCellValueByColumnAndRow(7, $cnt, $executiveArray['area']);	// 地域名をセット
			$objSheet->setCellValueByColumnAndRow(8, $cnt, $executiveArray['code']); 	// 同友コードをセット
			$objSheet->setCellValueByColumnAndRow(9, $cnt, $executiveArray['name']); 	// 同友名をセット

			// 全種目でループ
			$p_col = START_COL+7;
			foreach ($itemList as $itemArray) {
				// メイン種目と補助種目と特殊種目のリストを作る
				$related_item = array($itemArray['value']=>$itemArray['name']);

				// 補助種目
				foreach ($subitemList as $subitem) {
					$split_item = preg_split('/:/', $subitem['value']);
					//printArray($split_item);
					if ($split_item[0] === $itemArray['value']) {
						$related_item += [$subitem['value'] => $subitem['name']];
					}
				}
				// 特殊種目
				foreach ($spitemList as $spitem) {
					$split_item = preg_split('/:/', $spitem['value']);
					//printArray($split_item);
					if ($split_item[0] === $itemArray['value']) {
						$related_item += [$spitem['value'] => $spitem['name']];
					}
				}
				//printArray($related_item);

				// 補助種目、特殊種目すべての種目でループ
				foreach ($related_item as $item_key => $item_name) {

					//echo 'p_col='.$p_col.'<br>';

					if ($cnt == START_ROW) {
						$objSheet->setCellValueByColumnAndRow($p_col, 1, $item_name); // 種目名をセット(※最初の行のみ)

						// セルの色付け
						$s_col = PHPExcel_Cell::stringFromColumnIndex($p_col);
						$e_col = PHPExcel_Cell::stringFromColumnIndex($p_col);
						$objStyle = $objSheet->getStyle($s_col.'1:'.$e_col.'1');
						$objStyle->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
						$objStyle->getFill()->getStartColor()->setRGB('ffff33');
					}

					// 種目に該当する提携企業一覧を取得
					$partnerList = getPartnerList($fiscal_year, $item_key);
					foreach ($partnerList as $partnerArray) {

						//echo $partnerArray['name'].'<br>';
						if ($cnt == START_ROW) {
							$objSheet->setCellValueByColumnAndRow($p_col, 2, $partnerArray['name']); // 提携名をセット(※最初の行のみ);
						}

						// 最初のカラム位置をセット
						$month_cnt = $p_col;

						// メイン種目の場合のみ計画取得
						if (strpos($item_key, ':sub_') === false && strpos($item_key, ':spitem') === false) {
							// 計画・実績を取得
							$planData = getCampaignPlanTotalValue($fiscal_year, '', $partnerArray['value'], '', $executiveArray['value']);
							$resultData = getCampaignMonthTotalValue($fiscal_year, $item_key, $partnerArray['value'], '', $executiveArray['value'], $start_month, $end_month, false);

							$objSheet->setCellValueByColumnAndRow($month_cnt, 3, $campaign);
							$objSheet->setCellValueByColumnAndRow($month_cnt, 4, '計画');
							$objSheet->setCellValueByColumnAndRow($month_cnt+1, 4, '実績');
							$objSheet->setCellValueByColumnAndRow($month_cnt, $cnt, $planData[$campaign.'_plan']);
							$objSheet->setCellValueByColumnAndRow($month_cnt+1, $cnt, $resultData['result']);

							$p_col+=2;
						}
						else {
							// 実績だけを取得
							$resultData = getCampaignMonthTotalValue($fiscal_year, $item_key, $partnerArray['value'], '', $executiveArray['value'], $start_month, $end_month, false);

							$objSheet->setCellValueByColumnAndRow($month_cnt, 3, $campaign);
							$objSheet->setCellValueByColumnAndRow($month_cnt, 4, '実績');
							$objSheet->setCellValueByColumnAndRow($month_cnt, $cnt, $resultData['result']);

							$p_col++;
						}						
					}
				}
			}
			// 次の同友の行に移動
			$cnt++;
		}
		// 地域毎の最大は30行まで
		if (local_config::FEATURE_OUTPUT_EXCELSHEET_ORDER_TYPE) {
			$cnt += (AREA_ROW_NUM * $area_cnt + START_ROW) - $cnt;
			$area_cnt++;
		}
		else {
			// 同友コード順の時は初回でループを抜ける
			break;
		}
	}

	// データシートを非表示にする
	$objPHPExcelCampaign->setActiveSheetIndex(1);	// 2番目のシートをアクティブにしてからじゃないと1番目が非表示にできない
	$objSheet->setSheetState(PHPExcel_Worksheet::SHEETSTATE_HIDDEN);

	//--------------------------------------
	// 年間計画：同友の計画と実績を取得
	//--------------------------------------
	$objSheet = $objPHPExcelCampaign->getActiveSheet();

	// 地域、同友コード、同友名の列の幅設定
	$objSheet->getColumnDimensionByColumn(0)->setWidth(15);
	$objSheet->getColumnDimensionByColumn(1)->setWidth(10);
	$objSheet->getColumnDimensionByColumn(2)->setWidth(40);

	$cnt = START_ROW;
	$p_col = START_COL;
	$area_cnt = 1;
	// 同友毎の情報を取得してデータを出力
	foreach ($areaList as $areaArray) {
		// 本部を表示しないようにする
		//if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
			if ($areaArray['value'] === config::HEADOFFICE_NAME){
				continue;
			} 
		//}

		// 地区順同友コード順ではない場合
		if (!local_config::FEATURE_OUTPUT_EXCELSHEET_ORDER_TYPE) {
			$areaArray['value'] = 'all';	// allの中にすべての同友が入っている
		}

		// 同友毎の計画と実績を取得して表示
		foreach ($executiveList[$areaArray['value']] as $executiveArray) {

			//$objSheet->setCellValueByColumnAndRow(0, $cnt, $areaArray['value']);	 // 地域名をセット
			$objSheet->setCellValueByColumnAndRow(0, $cnt, $executiveArray['area']);	// 地域名をセット
			$objSheet->setCellValueByColumnAndRow(1, $cnt, $executiveArray['code']); 	// 同友コードをセット
			$objSheet->setCellValueByColumnAndRow(2, $cnt, $executiveArray['name']); 	// 同友名をセット

			// 全種目でループ
			$p_col = START_COL;
			foreach ($itemList as $itemArray) {
				// メイン種目と補助種目と特殊種目のリストを作る
				$related_item = array($itemArray['value']=>$itemArray['name']);

				// 補助種目
				foreach ($subitemList as $subitem) {
					$split_item = preg_split('/:/', $subitem['value']);
					//printArray($split_item);
					if ($split_item[0] === $itemArray['value']) {
						$related_item += [$subitem['value'] => $subitem['name']];
					}
				}
				// 特殊種目
				foreach ($spitemList as $spitem) {
					$split_item = preg_split('/:/', $spitem['value']);
					//printArray($split_item);
					if ($split_item[0] === $itemArray['value']) {
						$related_item += [$spitem['value'] => $spitem['name']];
					}
				}
				//printArray($related_item);

				// 補助種目、特殊種目すべての種目でループ
				foreach ($related_item as $item_key => $item_name) {

					// 補助種目、特殊種目かどうかを判定
					$hojo_item_flag = false;
					//echo "item_key=".$item_key."<br>";
					if (strpos($item_key, 'sub') !== false || strpos($item_key, 'spitem') !== false){
						$hojo_item_flag = true;	// 補助、特殊種目
						//echo ">>> MATCH =".$item_key."<br>";
					}

					if ($cnt == START_ROW) {
						$objSheet->setCellValueByColumnAndRow($p_col, 1, $item_name); // 種目名をセット(※最初の行のみ)

						// セルの色付け
						$s_col = PHPExcel_Cell::stringFromColumnIndex($p_col);
						$e_col = PHPExcel_Cell::stringFromColumnIndex($p_col);
						$objStyle = $objSheet->getStyle($s_col.'1:'.$e_col.'1');
						$objStyle->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
						$objStyle->getFill()->getStartColor()->setRGB('ffff33');
					}

					// 種目に該当する提携企業一覧を取得
					$partnerList = getPartnerList($fiscal_year, $item_key);
					foreach ($partnerList as $partnerArray) {

						//echo $partnerArray['name'].'<br>';
						if ($cnt == START_ROW) {
							$objSheet->setCellValueByColumnAndRow($p_col, 2, $partnerArray['name']); // 提携名をセット(※最初の行のみ);
						}

	 					// 年間計画：同友の月毎の計画と実績を取得
						$executiveData = getResultAndPlanByExecutive($fiscal_year, $item_key, $partnerArray['value'], $executiveArray['value'], false);
						//printArray($executiveData);

						// 12ヶ月分を表示する場合
						if (local_config::FEATURE_OUTPUT_EXCELSHEET_FOR_ONEYEAR) {

							for ($excel_month = 4; $excel_month <= 12; $excel_month++) {
								$month_cnt = $p_col;
								$objSheet->setCellValueByColumnAndRow($month_cnt, 3, $excel_month.'月');
								if (!$hojo_item_flag) {
									$objSheet->setCellValueByColumnAndRow($month_cnt, 4, '計画');
									$objSheet->setCellValueByColumnAndRow($month_cnt+1, 4, '実績');
									$objSheet->setCellValueByColumnAndRow($month_cnt, $cnt, $executiveData['plan'][$excel_month]);
									$objSheet->setCellValueByColumnAndRow($month_cnt+1, $cnt, $executiveData['result'][$excel_month]);
									$p_col+=2;
								}
								else {
									$objSheet->setCellValueByColumnAndRow($month_cnt, 4, '実績');
									$objSheet->setCellValueByColumnAndRow($month_cnt, $cnt, $executiveData['result'][$excel_month]);
									$p_col+=1;
								}
							}

							for ($excel_month = 1; $excel_month <= 3; $excel_month++) {
								$month_cnt = $p_col;
								$objSheet->setCellValueByColumnAndRow($month_cnt, 3, $excel_month.'月');
								if (!$hojo_item_flag) {
									$objSheet->setCellValueByColumnAndRow($month_cnt, 4, '計画');
									$objSheet->setCellValueByColumnAndRow($month_cnt+1, 4, '実績');
									$objSheet->setCellValueByColumnAndRow($month_cnt, $cnt, $executiveData['plan'][$excel_month]);
									$objSheet->setCellValueByColumnAndRow($month_cnt+1, $cnt, $executiveData['result'][$excel_month]);
									$p_col+=2;
								}
								else {
									$objSheet->setCellValueByColumnAndRow($month_cnt, 4, '実績');
									$objSheet->setCellValueByColumnAndRow($month_cnt, $cnt, $executiveData['result'][$excel_month]);
									$p_col+=1;
								}
							}
						}
						// キャンペーンの終了月を含めた4ヶ月を表示する場合
						else {

						// 後半の月から数えて4ヶ月分を表示
						// 4ヶ月前を表示
						$month_cnt = $p_col;
						$objSheet->setCellValueByColumnAndRow($month_cnt, 3, $before4_month.'月');
						$objSheet->setCellValueByColumnAndRow($month_cnt, 4, '計画');
						$objSheet->setCellValueByColumnAndRow($month_cnt+1, 4, '実績');
						$objSheet->setCellValueByColumnAndRow($month_cnt, $cnt, $executiveData['plan'][$before4_month]);
						$objSheet->setCellValueByColumnAndRow($month_cnt+1, $cnt, $executiveData['result'][$before4_month]);
						$p_col+=2;

						// 3ヶ月前を表示
						$month_cnt = $p_col;
						$objSheet->setCellValueByColumnAndRow($month_cnt, 3, $before3_month.'月');
						$objSheet->setCellValueByColumnAndRow($month_cnt, 4, '計画');
						$objSheet->setCellValueByColumnAndRow($month_cnt+1, 4, '実績');
						$objSheet->setCellValueByColumnAndRow($month_cnt, $cnt, $executiveData['plan'][$before3_month]);
						$objSheet->setCellValueByColumnAndRow($month_cnt+1, $cnt, $executiveData['result'][$before3_month]);
						$p_col+=2;

						// 前半の月を表示
						$month_cnt = $p_col;
						$objSheet->setCellValueByColumnAndRow($month_cnt, 3, $start_month.'月');
						$objSheet->setCellValueByColumnAndRow($month_cnt, 4, '計画');
						$objSheet->setCellValueByColumnAndRow($month_cnt+1, 4, '実績');
						$objSheet->setCellValueByColumnAndRow($month_cnt, $cnt, $executiveData['plan'][$start_month]);
						$objSheet->setCellValueByColumnAndRow($month_cnt+1, $cnt, $executiveData['result'][$start_month]);
						$p_col+=2;
						
						// 後半の月を表示
						$month_cnt = $p_col;
						$objSheet->setCellValueByColumnAndRow($month_cnt, 3, $end_month.'月');
						$objSheet->setCellValueByColumnAndRow($month_cnt, 4, '計画');
						$objSheet->setCellValueByColumnAndRow($month_cnt+1, 4, '実績');
						$objSheet->setCellValueByColumnAndRow($month_cnt, $cnt, $executiveData['plan'][$end_month]);
						$objSheet->setCellValueByColumnAndRow($month_cnt+1, $cnt, $executiveData['result'][$end_month]);
						$p_col+=2;

						}
					}
				}
			}
			// 次の同友の行に移動
			$cnt++;
		}
		// 地域毎の最大は30行まで
		if (local_config::FEATURE_OUTPUT_EXCELSHEET_ORDER_TYPE) {
			$cnt += (AREA_ROW_NUM * $area_cnt + START_ROW) - $cnt;
			$area_cnt++;
		}
		else {
			// 同友コード順の時は初回でループを抜ける
			break;
		}
	}

	// データシートを非表示にする
	$objPHPExcelCampaign->setActiveSheetIndex(2);	// 2番目のシートをアクティブにしてからじゃないと2番目が非表示にできない
	$objSheet->setSheetState(PHPExcel_Worksheet::SHEETSTATE_HIDDEN);

	//---------------------------------------------------
	// Excelファイルを保存
	//---------------------------------------------------
	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcelCampaign, 'Excel2007');
	$objWriter->save('./'.config::TEMP_DIRECTORY_NAME.'/'.$outputFileName);

	return $ret;
}

/**
 * ----------------------------------------------------------
 * makeExecutiveDownloadSheet()
 * 指定年度の同友目標管理シートを出力する
 * @param $fiscal_year：指定年度
 * @param $campaign：キャンペーン種別
 * ----------------------------------------------------------
 */
function makeExecutiveDownloadSheet($fiscal_year, $campaign) {

	$ret = 'success';

	//スクリプトを強制終了させるまでの許容する最大時間(単位:秒)
	set_time_limit(0);

	// キャンペーン種別判定
	switch($campaign) {
		case 'summer':
			$title = config::SUMMER_CAMPAIGN_NAME;
			$start_month = config::SUMMER_CAMPAIGN_START_MONTH;
			$end_month = config::SUMMER_CAMPAIGN_END_MONTH;
			$season = '夏';
			break;
		case 'autumn':
			$title = config::AUTUMN_CAMPAIGN_NAME;
			$start_month = config::AUTUMN_CAMPAIGN_START_MONTH;
			$end_month = config::AUTUMN_CAMPAIGN_END_MONTH;
			$season = '秋';
			break;
		case 'spring':
			$title = config::SPRING_CAMPAIGN_NAME;
			$start_month = config::SPRING_CAMPAIGN_START_MONTH;
			$end_month = config::SPRING_CAMPAIGN_END_MONTH;
			$season = '春';
			break;
	}

	// 全種目を取得
	$itemList = getItemList('no-insert','',$fiscal_year, false, false, false);
	if (count($itemList) == 0) {
		return 'no_item';
	}
	//printArray($itemList);

	// 地域を取得
	$areaList = getAreaList();
	if (count($areaList) == 0) {
		return 'no_area';
	}
	//printArray($areaList);

	// 地域毎に同友一覧を取得
	foreach ($areaList as $areaArray) {
		$executiveList[$areaArray['value']] = getExecutiveList($fiscal_year, 'campaign', $areaArray['value'], 'campaign');
		if (count($executiveList) == 0) {
			return 'no_campaign_executive';
		}
	}
	//printArray($executiveList);

	//---------------------------------------------------
	// Excel出力 初期設定
	//---------------------------------------------------
	// 出力用Excelファイル名
	$templateFileName = 'data_executive_'.$campaign.'_'.$fiscal_year.'.xlsx';
	$outputFileName = $campaign.'_'.$fiscal_year.'_challenge.xlsx';

	// エクセルの更新時間よりも実績の更新時間が新しいときだけエクセル生成処理を実施する
	$result_updatetime = getUpdateTimestampFile("./tmp/update_timestamp.txt");
	$excel_updatetime = getUpdateTimestampFile("./tmp/".$outputFileName);

	// タイムスタンプを比較
	// エクセルのタイムスタンプが実績更新時間よりも新しい場合は何もしない
	if ($result_updatetime < $excel_updatetime) {
		return $ret;
	}

	// 罫線設定
	$borderSetting = array(
		'borders' => array(
		  'top'     => array('style' => PHPExcel_Style_Border::BORDER_THIN),
		  'bottom'  => array('style' => PHPExcel_Style_Border::BORDER_THIN),
		  'left'    => array('style' => PHPExcel_Style_Border::BORDER_THIN),
		  'right'   => array('style' => PHPExcel_Style_Border::BORDER_THIN)
		)
	);

	// テンプレートを読み込み
	$objReader = PHPExcel_IOFactory::createReader('Excel2007');
	$filepath = './'.config::TEMP_DIRECTORY_NAME.'/'.$templateFileName;
	if (file_exists($filepath)) {
		$objPHPExcel = $objReader->load($filepath);
	}
	else {
		return 'template_file_error';
	}

	//--------------------------------------
	// シートに出力
	//--------------------------------------
	$sheetCnt = 0;
	foreach ($areaList as $areaArray) {
		// 本部を表示しないようにする
		//if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
			if ($areaArray['value'] === config::HEADOFFICE_NAME){
				continue;
			} 
		//}

		// 同友毎のキャンペーンと年間の計画と実績を取得して表示
		foreach ($executiveList[$areaArray['value']] as $executiveArray) {
			//echo $executiveArray['user']."<br>";

			// 同友以外の調整項目の場合は飛ばす
			if ($executiveArray['auth'] != config::USER_EXECUTIVE) {
				continue;
			}

			// 対象のシートをアクティブにする（シートは左から順に、0、1，2・・・）
			$objPHPExcel->setActiveSheetIndex(0);	// テンプレートをアクティブ
			$objSheet = addSheet($objPHPExcel, $executiveArray['user'], $sheetCnt);
			$objSheet->setCellValueByColumnAndRow(1, 1, 'ロータス '.$executiveArray['name'].' 殿');
			$objSheet->setCellValueByColumnAndRow(15, 1, getCurrentDate('.').' 速報');
			$objSheet->setCellValueByColumnAndRow(1, 3, $fiscal_year.'年度'.$title.' 目標管理シート');

			//---------------------------------------------------
			// キャンペーン速報を出力
			//---------------------------------------------------
			// 初期化
			$planTotalList   = array();
			$resultTotalList = array();
			$lmlsTotal       = array();
			$columnCnt = 0;
			foreach ($itemList as $itemArray) {
			
				if (strpos($itemArray['value'], 'LC') !== false) {
					continue;
				}

				// 初期化
				if (in_array($itemArray['value'], local_config::CAMPAIGN_ITEM_COMBINE)) {
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
					if (in_array($itemArray['value'], local_config::CAMPAIGN_ITEM_COMBINE)) {
						
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
						$resultData = getCampaignMonthTotalValue($fiscal_year, $itemArray['value'], $partnerArray['value'], '', $executiveArray['value'], $start_month, $end_month, false);
						$resultTotal += $resultData['result'];
					}
				}

				$planTotalList += array($itemArray['value'] => $planTotal);
				$resultTotalList += array($itemArray['value'] => $resultTotal);

				// LM+LS対策：
				if ($fiscal_year >= config::CAMPAIGN_LMLS_ADD_YEAR) {
					if ($itemArray['value'] === 'LT') {
						$columnCnt++;	// LM+LSの列があるのでLTの時に一つ列を飛ばす
					}
				}

				//$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 4, $itemArray['value'].PHP_EOL.'('.$itemArray['unit'].')');
			
				// 分離する場合
				if (in_array($itemArray['value'], local_config::CAMPAIGN_ITEM_COMBINE)) {
					// LMとLSを分離する場合は、それぞれの種目内の企業毎の合計値をExcelには出力するので合計値を算出する
					if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
						foreach ($planTotalList[$itemArray['value']] as $key => $value) {

							$lmlsTotal['plan'][$itemArray['value']]   += $planTotalList[$itemArray['value']][$key];
							$lmlsTotal['result'][$itemArray['value']] += $resultTotalList[$itemArray['value']][$key];							
						}
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 6, $lmlsTotal['plan'][$itemArray['value']]);
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 7, $lmlsTotal['result'][$itemArray['value']]);
						$columnCnt++;
					}
					else {
						// 企業ごとの計画値と実績値を出力
						foreach ($planTotalList[$itemArray['value']] as $key => $value) {
							$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 6, $planTotalList[$itemArray['value']][$key]);
							$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 7, $resultTotalList[$itemArray['value']][$key]);
							$columnCnt++;
						}
					}
				}
				// 統合する場合
				else if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
					$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 6, $planTotalList[$itemArray['value']]);
					$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 7, $resultTotalList[$itemArray['value']]);
					$columnCnt++;
					//echo "columnCnt=".$columnCnt." plan=".$planTotalList[$itemArray['value']]."<br>";
					//echo "columnCnt=".$columnCnt." result=".$resultTotalList[$itemArray['value']]."<br>";
				}
				else {
					$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 6, $planTotalList[$itemArray['value']]);
					$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 7, $resultTotalList[$itemArray['value']]);
					$columnCnt+=2;
				}

			}
			// printArray($planTotalList);
			// printArray($resultTotalList);
			// exit();

			//---------------------------------------------------
			// 年間情報を出力
			//---------------------------------------------------
			// 初期化
			$columnCnt = 0;
			$planTotalList   = array();
			$resultTotalList = array();
			$companyPlanList = array();
			$companyResultList = array();
			
			// エクセルに出力
			$objSheet->setCellValueByColumnAndRow(1, 21, $fiscal_year.'年度年間優績表彰 目標管理シート');

			// 種目ごとの年間合計値を取得
			foreach ($itemList as $itemArray) {
				
				if (strpos($itemArray['value'], ':sub_') !== false || strpos($itemArray['value'], ':spitem') !== false ) {
					continue;
				}

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

					// ★★ 年間計画で統合する種目に該当しない場合 → 分離

					// 種目に該当する提携企業一覧を取得
					$partnerList = getPartnerList($fiscal_year, $itemArray['value']);

					// 提携企業数分の計画、実績、達成率を取得
					foreach ($partnerList as $partnerArray) {
						$data = getExecutiveResultTotalValue($fiscal_year, $itemArray['value'], '%', $partnerArray['value'], $executiveArray['value']);
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
					// ★★ 年間計画で統合する種目に該当する場合 → 統合
					
					$data = getExecutiveResultTotalValue($fiscal_year, $itemArray['value'], '%', 'TOTAL',  $executiveArray['value']);
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

				
				// LM+LS対策：LM+LSの列があるのでLTの時に一つ列を飛ばす
				if ($fiscal_year >= config::CAMPAIGN_LMLS_ADD_YEAR) {
					if ($itemArray['value'] === 'LT') {
						$columnCnt++;
					}
				}
			
				//$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 22, $itemArray['value'].PHP_EOL.'('.$itemArray['unit'].')');

				if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
					$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 24, $planTotalList[$itemArray['value']]);
					$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 25, $resultTotalList[$itemArray['value']]);
					$columnCnt++;
				}
				else if (!in_array($itemArray['value'], local_config::PLAN_ITEM_COMBINE)) {
					// 分離する場合は、提携企業名を表示する
					foreach ($planTotalList[$itemArray['value']] as $key => $value) {
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 24, $planTotalList[$itemArray['value']][$key]);
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 25, $resultTotalList[$itemArray['value']][$key]);
						$columnCnt++;
					}
				}
				else {
					$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 24, $planTotalList[$itemArray['value']]);
					$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 25, $resultTotalList[$itemArray['value']]);
					$columnCnt += 2;
				}
			}
			
			//printArray($planTotalList);

			// シート数をカウント
			$sheetCnt++;
		}
	}
	
	// 同友チャレンジシートの後処理
	// テンプレートで使った先頭のシートを削除し、先頭の同友シートをアクティブにする。
	$objPHPExcel->removeSheetByIndex(0);
	$objPHPExcel->setActiveSheetIndex(0);
	$objSheet = $objPHPExcel->getActiveSheet();
	$objStyle = $objSheet->getStyle('A1:A1');

	//---------------------------------------------------
	// Excelファイルを保存
	//---------------------------------------------------
	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('./'.config::TEMP_DIRECTORY_NAME.'/'.$outputFileName);
	
	return $ret;
}

/**
 * ----------------------------------------------------------
 * makeCampaignDownloadSheet_for_kngw()
 * 指定年度の指定キャンペーンの速報シートを生成（※神奈川支部専用）
 * @param $fiscal_year：指定年度
 * @param $campaign：キャンペーン種別
 * @param $target：Excelのシート種別（sokuho：速報シート, challenge：同友チャレンジシート）
 * ----------------------------------------------------------
 */
function makeCampaignDownloadSheet_for_kngw($fiscal_year, $campaign, $target) {

	$ret = 'success';

	//クライアント切断時に処理を実行するように指定
	ignore_user_abort(true);

	//スクリプトを強制終了させるまでの許容する最大時間(単位:秒)
	set_time_limit(0);

	// キャンペーン種別判定
	switch($campaign) {
		case 'summer':
			$title = config::SUMMER_CAMPAIGN_NAME;
			$start_month = config::SUMMER_CAMPAIGN_START_MONTH;
			$end_month = config::SUMMER_CAMPAIGN_END_MONTH;
			$season = '夏';
			break;
		case 'autumn':
			$title = config::AUTUMN_CAMPAIGN_NAME;
			$start_month = config::AUTUMN_CAMPAIGN_START_MONTH;
			$end_month = config::AUTUMN_CAMPAIGN_END_MONTH;
			$season = '秋';
			break;
		case 'spring':
			$title = config::SPRING_CAMPAIGN_NAME;
			$start_month = config::SPRING_CAMPAIGN_START_MONTH;
			$end_month = config::SPRING_CAMPAIGN_END_MONTH;
			$season = '春';
			break;
	}

	// 全種目を取得
	$itemList = getItemList('no-insert');
	if (count($itemList) == 0) {
		return 'no_item';
	}
	
	// 補助項目の追加
	// キャンペーン速報用にLEB、LH新規を加える
	$itemList[] = array('value'=>'LH:sub_4_1',  'name'=>'LH自動車新規', 'unit'=>'件');
	//$itemList[] = array('value'=>'LE:sub_6_1',  'name'=>'LEバッテリー個数', 'unit'=>'個');

	$itemList[] = array('value'=>'LM:sub_1_1',  'name'=>'LM普通車', 'unit'=>'台');
	$itemList[] = array('value'=>'LM:sub_1_2',  'name'=>'LM軽自動車', 'unit'=>'台');
	$itemList[] = array('value'=>'LM:sub_1_3',  'name'=>'アウトランダー', 'unit'=>'台');
	$itemList[] = array('value'=>'LS:sub_2_1',  'name'=>'LSリース買取（登録）', 'unit'=>'台');
	$itemList[] = array('value'=>'LS:sub_2_2',  'name'=>'LS期間内受注', 'unit'=>'台');
	$itemList[] = array('value'=>'LS:sub_2_3',  'name'=>'LSリース買取（受注）', 'unit'=>'台');
	$itemList[] = array('value'=>'LS:sub_2_4',  'name'=>'LS除外', 'unit'=>'台');
	$itemList[] = array('value'=>'LT:sub_3_1',  'name'=>'LT本数', 'unit'=>'本');
	$itemList[] = array('value'=>'LE:sub_6_2',  'name'=>'GS・バッテリー', 'unit'=>'千円');
	$itemList[] = array('value'=>'LE:sub_6_3',  'name'=>'パイオニアナビ', 'unit'=>'千円');
	$itemList[] = array('value'=>'LC:sub_8_1',  'name'=>'LC回収（発券手続中）', 'unit'=>'枚');
	$itemList[] = array('value'=>'LC:spitem',   'name'=>'LC保有枚数', 'unit'=>'枚');
	
	
	// 同友一覧を取得
	$executiveList = getExecutiveList($fiscal_year, 'campaign', '%', 'all');
	if (count($executiveList) == 0) {
		return 'no_campaign_executive';
	}
	//printArray($executiveList);
	//return 'success';

	// 出力用Excelファイル名
	$campaignFileName = $campaign.'_'.$fiscal_year.'.xlsx';
	$executeFileName  = $campaign.'_'.$fiscal_year.'_challenge.xlsx';

	// エクセルの更新時間よりも実績の更新時間が新しいときだけエクセル生成処理を実施する
	$result_updatetime = getUpdateTimestampFile("./tmp/update_timestamp.txt");
	if ($target === "sokuho") {
		$excel_updatetime = getUpdateTimestampFile("./tmp/".$campaignFileName);
	}
	else {
		$excel_updatetime = getUpdateTimestampFile("./tmp/".$executeFileName);
	}
	// タイムスタンプを比較
	// エクセルのタイムスタンプが実績更新時間よりも新しい場合は何もしない
	if ($result_updatetime < $excel_updatetime) {
		return $ret;
	}

	// テンプレートを読み込み
	// テンプレートファイルがない場合はエラーを通知
	$objReader = PHPExcel_IOFactory::createReader('Excel2007');
	if ($target === "sokuho") {
		$filepath = './'.config::TEMP_DIRECTORY_NAME.'/template_campaign_'.$fiscal_year.'_'.$campaign.'.xlsx';
		if (file_exists($filepath)) {
			$objPHPExcelCampaign = $objReader->load($filepath);
		}
		else {
			return 'template_file_error';
		}
	}
	else {
		$filepath = './'.config::TEMP_DIRECTORY_NAME.'/template_executive_'.$fiscal_year.'_'.$campaign.'.xlsx';
		if (file_exists($filepath)) {
			$objPHPExcelExecutive = $objReader->load($filepath);
		}
		else {
			return 'template_file_error';
		}
	}
	
	// キャンペーン速報のタイトル作成
	if ($target === 'sokuho') {
		$objPHPExcelCampaign->setActiveSheetIndex(0); 										// GURAFUシートをアクティブにする
		$objSheet = $objPHPExcelCampaign->getActiveSheet();
		$objSheet->setCellValueByColumnAndRow(2, 1, $fiscal_year.'年度 '.$title.'報告');	// タイトル
		$objSheet->setCellValueByColumnAndRow(2, 2, getCurrentDate('/'));				   	// 日付
		$lastyear = $fiscal_year - 1;
		$objSheet->setCellValueByColumnAndRow(8, 4,  $lastyear.'年度'.$season);			 	// 昨年度
	}

	// 同友毎のデータを計算し、Excelに出力
	$sheetCnt   = 0;
	$EAST_START_ROW_NUM    = 4;
	$CENTRAL_START_ROW_NUM = 25;
	$WESTL_START_ROW_NUM   = 47;
	$eastCnt    = $EAST_START_ROW_NUM;
	$centralCnt = $CENTRAL_START_ROW_NUM;
	$westCnt    = $WESTL_START_ROW_NUM;
	foreach ($executiveList as $executiveArray) {

		// 脱退済みの同友は速報シートに表示しない
		if ($executiveArray['exitdate'] !== "0000-00-00") {
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
		$planTotalList   = array();
		$resultTotalList = array();
		$companyPlanList = array();
		$companyResultList = array();
		
		// 全種目の計画値と合計値を計算
		foreach ($itemList as $itemArray) {
		
			// 初期化
			$planTotal = 0;
			$resultTotal = 0;
		
			// 種目に該当する提携企業一覧を取得
			$partnerList = getPartnerList($fiscal_year, $itemArray['value']);
	
			// 提携企業数分の計画、実績、達成率を取得
			foreach ($partnerList as $partnerArray) {
			
				// 計画取得
				$planData = getCampaignPlanTotalValue($fiscal_year, '', $partnerArray['value'], '', $executiveArray['value']);
				$planTotal += $planData[$campaign.'_plan'];
				
				// 実績取得
				$resultData = getCampaignMonthTotalValue($fiscal_year, $itemArray['value'], $partnerArray['value'], '', $executiveArray['value'], $start_month, $end_month, false);
				$resultTotal += $resultData['result'];
				
				// 提携企業毎の計画と実績を保存
				$companyPlanList += array($itemArray['value'].'_'.$partnerArray['value'] => $planData[$campaign.'_plan']);
				$companyResultList += array($itemArray['value'].'_'.$partnerArray['value'] => $resultData['result']);

				// LMの上期、下期、年間合計を取得
				if ($itemArray['value'] === 'LM') {
					$resultData = getExecutiveResultTotalValue($fiscal_year, $itemArray['value'], '%', $partnerArray['value'], $executiveArray['value']);
					
					// 上期
					$lm_h1_result =  $resultData[0]['4_result']+$resultData[0]['5_result']+$resultData[0]['6_result']+$resultData[0]['7_result']+$resultData[0]['8_result']+$resultData[0]['9_result'];
					$companyResultList += array($itemArray['value'].'_half1_total' => $lm_h1_result);

					// 下期
					$lm_h2_result =  $resultData[0]['10_result']+$resultData[0]['11_result']+$resultData[0]['12_result']+$resultData[0]['1_result']+$resultData[0]['2_result']+$resultData[0]['3_result'];
					$companyResultList += array($itemArray['value'].'_half2_total' => $lm_h2_result);
					
					// 年間合計
					$lm_total_result =  $lm_h1_result + $lm_h2_result;
					$companyResultList += array($itemArray['value'].'_year_total' => $lm_total_result);
				}

				// LOだけ6月、10月、2月分を企業別に取得
				if ($itemArray['value'] === 'LO') {
					$resultData = getExecutiveResultTotalValue($fiscal_year, $itemArray['value'], '%', $partnerArray['value'], $executiveArray['value']);
					$companyResultList += array($itemArray['value'].'_'.$partnerArray['value'].'_m6' => $resultData[0]['6_result']);	// 6月分
					$companyResultList += array($itemArray['value'].'_'.$partnerArray['value'].'_m10' => $resultData[0]['10_result']);	// 10月分
					$companyResultList += array($itemArray['value'].'_'.$partnerArray['value'].'_m2' => $resultData[0]['2_result']);	// 2月分
				}

				// LCに限り、第2四半期、4～7月、8月～11月、12月～3月を取得
				if ($itemArray['value'] === 'LC') {
					$resultData = getExecutiveResultTotalValue($fiscal_year, $itemArray['value'], '%', $partnerArray['value'], $executiveArray['value']);
					
					// 第2四半期
					$q2_result =  $resultData[0]['7_result']+$resultData[0]['8_result']+$resultData[0]['9_result'];
					$companyResultList += array($itemArray['value'].'_'.$partnerArray['value'].'_q2' => $q2_result);

					$m4_7_result =  $resultData[0]['4_result']+$resultData[0]['5_result']+$resultData[0]['6_result']+$resultData[0]['7_result'];
					$companyResultList += array($itemArray['value'].'_'.$partnerArray['value'].'_m4_7' => $m4_7_result);

					$m8_11_result =  $resultData[0]['8_result']+$resultData[0]['9_result']+$resultData[0]['10_result']+$resultData[0]['11_result'];
					$companyResultList += array($itemArray['value'].'_'.$partnerArray['value'].'_m8_11' => $m8_11_result);

					$m12_3_result =  $resultData[0]['12_result']+$resultData[0]['1_result']+$resultData[0]['2_result']+$resultData[0]['3_result'];
					$companyResultList += array($itemArray['value'].'_'.$partnerArray['value'].'_m12_3' => $m12_3_result);
				}
			}
			$planTotalList += array($itemArray['value'] => $planTotal);
			$resultTotalList += array($itemArray['value'] => $resultTotal);
		}
		// printArray($resultTotalList);
		// return 'success';

		// 速報シートにキャンペーン用累積LC追加
		if(local_config::FEATURE_ADD_LMS_LC_EXCELSHEET) {

			// キャンペーン種別判定
			switch($campaign) {
				case 'summer':
					$loopstart_month = 4;	// 4月から7月までを集計
					$loopend_month = config::SUMMER_CAMPAIGN_END_MONTH;
					break;
				case 'autumn':
					$loopstart_month = 4;	// 4月から11月までを集計
					$loopend_month = config::AUTUMN_CAMPAIGN_END_MONTH;
					break;
				case 'spring':
					$loopstart_month = 1;	// 春の場合は全期間（4月から3月まで）が対象
					$loopend_month = 12;
					break;
			}

			// LCの月毎の枚数を取得
			$data = getExecutiveResultTotalValue($fiscal_year, 'LC', '%', 'TOTAL', $executiveArray['value']);

			// キャンペーン終了月までのLCの枚数を取得（達成賞向けの数値）
			$LC_Cnt = 0;
			for ($i = $loopstart_month; $i <= $loopend_month; $i++) {
				$LC_Cnt += $data[0][$i.'_result'];
			}

			$companyResultList += array('LC_Cnt' => $LC_Cnt);
			if(local_config::FEATURE_ADD_LMS_LC_EXCELSHEET) {
				$companyResultList += array('LC_Period_Cnt' => $LC_Cnt);	// 4月からキャンペーン終了月までのLCの枚数
			}

			// LCキャッシュバック条件用
			$LC_Cnt = 0;
			switch($campaign) {
				case 'summer':
					$LC_Cnt = $data[0]['4_result'] + $data[0]['5_result'] + $data[0]['6_result'] + $data[0]['7_result'] ;
					break;
				case 'autumn':
					$LC_Cnt = $data[0]['8_result'] + $data[0]['9_result'] + $data[0]['10_result'] + $data[0]['11_result'] ;
					break;
				case 'spring':
					$LC_Cnt = $data[0]['12_result'] + $data[0]['1_result'] + $data[0]['2_result'] + $data[0]['3_result'] ;
					break;
			}
			$companyResultList += array('LC_Cashbck_Cnt' => $LC_Cnt);

			// ★★ 特例措置 ★★
			// 2021年度の春キャンペーンのLC実績は第3半期（12月～3月）で計算する ← 実際には2021年夏からだった
			// 2021年度以降もLCキャッシュバックの条件を適用する
			//if ($fiscal_year == 2021 && $campaign === 'spring') {
			if ($fiscal_year >= 2021) {
				$companyResultList['LC_Cnt'] = $companyResultList['LC_Cashbck_Cnt'];
			}

			// LCの6月、10月、2月の合計を表示する
			$companyResultList += array('LC_6m_total'  => $data[0]['6_result']);
			$companyResultList += array('LC_10m_total' => $data[0]['10_result']);
			$companyResultList += array('LC_2m_total'  => $data[0]['2_result']);

			// LCの7月、11月、3月の合計を表示する
			$companyResultList += array('LC_7m_total'  => $data[0]['7_result']);
			$companyResultList += array('LC_11m_total' => $data[0]['11_result']);
			$companyResultList += array('LC_3m_total'  => $data[0]['3_result']);
		}
		// printArray($companyPlanList);
		// printArray($companyResultList);
		// return 'success';
		
		//===================================================
		// キャンペーン速報をExcelに出力
		// 決め打ちなので、シートが少しでも変わると修正必要
		//===================================================
		if ($target === 'sokuho') {
			// ★★★★ KAIINシートをアクティブにする ★★★★
			$objPHPExcelCampaign->setActiveSheetIndex(1);
			$objSheet = $objPHPExcelCampaign->getActiveSheet();
			$objSheet->setCellValueByColumnAndRow(0, 1, $fiscal_year.'年度 '.$title);
			//$objSheet->setCellValueByColumnAndRow(14+$add_lms_lc_shiftnum, 1, $fiscal_year.'年度 '.$title);
			
			// 速報シートKAIINにLMS、キャンペーン用LC追加した場合の処理
			$add_lms_lc_shiftnum = 0;
			if (local_config::FEATURE_ADD_LMS_LC_EXCELSHEET) {
				$add_lms_lc_shiftnum = 5;
			}

			// 東地区
			if ($executiveArray['area'] === '東地区') {
				
				$objSheet->setCellValueByColumnAndRow(0,  $eastCnt, $executiveArray['name']);
				$objSheet->setCellValueByColumnAndRow(14+$add_lms_lc_shiftnum, $eastCnt, $executiveArray['name']);
				
				$objSheet->setCellValueByColumnAndRow(1+$add_lms_lc_shiftnum,  $eastCnt, $planTotalList['LM']);		// LM
				$objSheet->setCellValueByColumnAndRow(2+$add_lms_lc_shiftnum,  $eastCnt, $resultTotalList['LM']);
				$objSheet->setCellValueByColumnAndRow(4+$add_lms_lc_shiftnum,  $eastCnt, $planTotalList['LS']);		// LS
				$objSheet->setCellValueByColumnAndRow(5+$add_lms_lc_shiftnum,  $eastCnt, $resultTotalList['LS']);
				$objSheet->setCellValueByColumnAndRow(7+$add_lms_lc_shiftnum,  $eastCnt, $planTotalList['LT']);		// LT
				$objSheet->setCellValueByColumnAndRow(8+$add_lms_lc_shiftnum,  $eastCnt, $resultTotalList['LT']);
				$objSheet->setCellValueByColumnAndRow(10+$add_lms_lc_shiftnum, $eastCnt, $planTotalList['LH']);		// LH
				$objSheet->setCellValueByColumnAndRow(11+$add_lms_lc_shiftnum, $eastCnt, $resultTotalList['LH']);
				$objSheet->setCellValueByColumnAndRow(15+$add_lms_lc_shiftnum, $eastCnt, $planTotalList['LO']);		// LO
				$objSheet->setCellValueByColumnAndRow(16+$add_lms_lc_shiftnum, $eastCnt, $resultTotalList['LO']);
				$objSheet->setCellValueByColumnAndRow(18+$add_lms_lc_shiftnum, $eastCnt, $planTotalList['LE']);		// LE
				$objSheet->setCellValueByColumnAndRow(19+$add_lms_lc_shiftnum, $eastCnt, $resultTotalList['LE']);
				$objSheet->setCellValueByColumnAndRow(21+$add_lms_lc_shiftnum, $eastCnt, $planTotalList['LL']);		// LL
				$objSheet->setCellValueByColumnAndRow(22+$add_lms_lc_shiftnum, $eastCnt, $resultTotalList['LL']);
				$objSheet->setCellValueByColumnAndRow(24+$add_lms_lc_shiftnum, $eastCnt, $planTotalList['LC']);		// LC
				$objSheet->setCellValueByColumnAndRow(25+$add_lms_lc_shiftnum, $eastCnt, $resultTotalList['LC']);
			}
			
			// 中央地区
			if ($executiveArray['area'] === '中央地区') {
				
				$objSheet->setCellValueByColumnAndRow(0,  $centralCnt, $executiveArray['name']);
				$objSheet->setCellValueByColumnAndRow(14+$add_lms_lc_shiftnum, $centralCnt, $executiveArray['name']);
				
				$objSheet->setCellValueByColumnAndRow(1+$add_lms_lc_shiftnum,  $centralCnt, $planTotalList['LM']);		// LM
				$objSheet->setCellValueByColumnAndRow(2+$add_lms_lc_shiftnum,  $centralCnt, $resultTotalList['LM']);
				$objSheet->setCellValueByColumnAndRow(4+$add_lms_lc_shiftnum,  $centralCnt, $planTotalList['LS']);		// LS
				$objSheet->setCellValueByColumnAndRow(5+$add_lms_lc_shiftnum,  $centralCnt, $resultTotalList['LS']);
				$objSheet->setCellValueByColumnAndRow(7+$add_lms_lc_shiftnum,  $centralCnt, $planTotalList['LT']);		// LT
				$objSheet->setCellValueByColumnAndRow(8+$add_lms_lc_shiftnum,  $centralCnt, $resultTotalList['LT']);
				$objSheet->setCellValueByColumnAndRow(10+$add_lms_lc_shiftnum, $centralCnt, $planTotalList['LH']);		// LH
				$objSheet->setCellValueByColumnAndRow(11+$add_lms_lc_shiftnum, $centralCnt, $resultTotalList['LH']);
				$objSheet->setCellValueByColumnAndRow(15+$add_lms_lc_shiftnum, $centralCnt, $planTotalList['LO']);		// LO
				$objSheet->setCellValueByColumnAndRow(16+$add_lms_lc_shiftnum, $centralCnt, $resultTotalList['LO']);
				$objSheet->setCellValueByColumnAndRow(18+$add_lms_lc_shiftnum, $centralCnt, $planTotalList['LE']);		// LE
				$objSheet->setCellValueByColumnAndRow(19+$add_lms_lc_shiftnum, $centralCnt, $resultTotalList['LE']);
				$objSheet->setCellValueByColumnAndRow(21+$add_lms_lc_shiftnum, $centralCnt, $planTotalList['LL']);		// LL
				$objSheet->setCellValueByColumnAndRow(22+$add_lms_lc_shiftnum, $centralCnt, $resultTotalList['LL']);
				$objSheet->setCellValueByColumnAndRow(24+$add_lms_lc_shiftnum, $centralCnt, $planTotalList['LC']);		// LC
				$objSheet->setCellValueByColumnAndRow(25+$add_lms_lc_shiftnum, $centralCnt, $resultTotalList['LC']);
			}
			
			// 西地区
			if ($executiveArray['area'] === '西地区') {
				
				$objSheet->setCellValueByColumnAndRow(0,  $westCnt, $executiveArray['name']);
				$objSheet->setCellValueByColumnAndRow(14+$add_lms_lc_shiftnum, $westCnt, $executiveArray['name']);
				
				$objSheet->setCellValueByColumnAndRow(1+$add_lms_lc_shiftnum,  $westCnt, $planTotalList['LM']);		// LM
				$objSheet->setCellValueByColumnAndRow(2+$add_lms_lc_shiftnum,  $westCnt, $resultTotalList['LM']);
				$objSheet->setCellValueByColumnAndRow(4+$add_lms_lc_shiftnum,  $westCnt, $planTotalList['LS']);		// LS
				$objSheet->setCellValueByColumnAndRow(5+$add_lms_lc_shiftnum,  $westCnt, $resultTotalList['LS']);
				$objSheet->setCellValueByColumnAndRow(7+$add_lms_lc_shiftnum,  $westCnt, $planTotalList['LT']);		// LT
				$objSheet->setCellValueByColumnAndRow(8+$add_lms_lc_shiftnum,  $westCnt, $resultTotalList['LT']);
				$objSheet->setCellValueByColumnAndRow(10+$add_lms_lc_shiftnum, $westCnt, $planTotalList['LH']);		// LH
				$objSheet->setCellValueByColumnAndRow(11+$add_lms_lc_shiftnum, $westCnt, $resultTotalList['LH']);
				$objSheet->setCellValueByColumnAndRow(15+$add_lms_lc_shiftnum, $westCnt, $planTotalList['LO']);		// LO
				$objSheet->setCellValueByColumnAndRow(16+$add_lms_lc_shiftnum, $westCnt, $resultTotalList['LO']);
				$objSheet->setCellValueByColumnAndRow(18+$add_lms_lc_shiftnum, $westCnt, $planTotalList['LE']);		// LE
				$objSheet->setCellValueByColumnAndRow(19+$add_lms_lc_shiftnum, $westCnt, $resultTotalList['LE']);
				$objSheet->setCellValueByColumnAndRow(21+$add_lms_lc_shiftnum, $westCnt, $planTotalList['LL']);		// LL
				$objSheet->setCellValueByColumnAndRow(22+$add_lms_lc_shiftnum, $westCnt, $resultTotalList['LL']);
				$objSheet->setCellValueByColumnAndRow(24+$add_lms_lc_shiftnum, $westCnt, $planTotalList['LC']);		// LC
				$objSheet->setCellValueByColumnAndRow(25+$add_lms_lc_shiftnum, $westCnt, $resultTotalList['LC']);
			}

			// 本部
			if ($executiveArray['area'] === 'ロータス神奈川') {
				
				$objSheet->setCellValueByColumnAndRow(1+$add_lms_lc_shiftnum,  70, $planTotalList['LM']);		// LM
				$objSheet->setCellValueByColumnAndRow(2+$add_lms_lc_shiftnum,  70, $resultTotalList['LM']);
				$objSheet->setCellValueByColumnAndRow(4+$add_lms_lc_shiftnum,  70, $planTotalList['LS']);		// LS
				$objSheet->setCellValueByColumnAndRow(5+$add_lms_lc_shiftnum,  70, $resultTotalList['LS']);
				$objSheet->setCellValueByColumnAndRow(7+$add_lms_lc_shiftnum,  70, $planTotalList['LT']);		// LT
				$objSheet->setCellValueByColumnAndRow(8+$add_lms_lc_shiftnum,  70, $resultTotalList['LT']);
				$objSheet->setCellValueByColumnAndRow(10+$add_lms_lc_shiftnum, 70, $planTotalList['LH']);		// LH
				$objSheet->setCellValueByColumnAndRow(11+$add_lms_lc_shiftnum, 70, $resultTotalList['LH']);
				$objSheet->setCellValueByColumnAndRow(15+$add_lms_lc_shiftnum, 70, $planTotalList['LO']);		// LO
				$objSheet->setCellValueByColumnAndRow(16+$add_lms_lc_shiftnum, 70, $resultTotalList['LO']);
				$objSheet->setCellValueByColumnAndRow(18+$add_lms_lc_shiftnum, 70, $planTotalList['LE']);		// LE
				$objSheet->setCellValueByColumnAndRow(19+$add_lms_lc_shiftnum, 70, $resultTotalList['LE']);
				$objSheet->setCellValueByColumnAndRow(21+$add_lms_lc_shiftnum, 70, $planTotalList['LL']);		// LL
				$objSheet->setCellValueByColumnAndRow(22+$add_lms_lc_shiftnum, 70, $resultTotalList['LL']);
				$objSheet->setCellValueByColumnAndRow(24+$add_lms_lc_shiftnum, 70, $planTotalList['LC']);		// LC
				$objSheet->setCellValueByColumnAndRow(25+$add_lms_lc_shiftnum, 70, $resultTotalList['LC']);
			}
			
			// ★★★★ LTLLLMCシート ★★★★
			$objPHPExcelCampaign->setActiveSheetIndex(2); 	// LTLLLMCシートをアクティブにする
			$objSheet = $objPHPExcelCampaign->getActiveSheet();

			// 速報シートにキャンペーン用累積LC追加
			if(local_config::FEATURE_ADD_LMS_LC_EXCELSHEET) {
				// キャンペーン種別判定
				switch($campaign) {
					case 'summer':
						$objSheet->setCellValueByColumnAndRow(32,  1, "ＬＣ ４～７計");
						$objSheet->setCellValueByColumnAndRow(32,  3, "４～７計");
						break;
					case 'autumn':
						$objSheet->setCellValueByColumnAndRow(32,  1, "ＬＣ ４～１１計");
						$objSheet->setCellValueByColumnAndRow(32,  3, "４～１１計");
						break;
					case 'spring':
						$objSheet->setCellValueByColumnAndRow(32,  1, "ＬＣ ４～３計");
						$objSheet->setCellValueByColumnAndRow(32,  3, "４～３計");
						break;
				}
			}

			// 東地区
			if ($executiveArray['area'] === '東地区') {
				
				$objSheet->setCellValueByColumnAndRow(0,  $eastCnt, $executiveArray['name']);
				$objSheet->setCellValueByColumnAndRow(11, $eastCnt, $executiveArray['name']);
				$objSheet->setCellValueByColumnAndRow(22, $eastCnt, $executiveArray['name']);
				
				$objSheet->setCellValueByColumnAndRow(1,  $eastCnt, $companyResultList['LT_5']);
				$objSheet->setCellValueByColumnAndRow(2,  $eastCnt, $companyResultList['LT_6']);
				$objSheet->setCellValueByColumnAndRow(4,  $eastCnt, $companyResultList['LH_8']);
				$objSheet->setCellValueByColumnAndRow(5,  $eastCnt, $companyResultList['LH_7']);
				$objSheet->setCellValueByColumnAndRow(7,  $eastCnt, $companyResultList['LO_9']);
				$objSheet->setCellValueByColumnAndRow(8,  $eastCnt, $companyResultList['LO_10']);
				$objSheet->setCellValueByColumnAndRow(12, $eastCnt, $companyResultList['LE_11']);
				$objSheet->setCellValueByColumnAndRow(13, $eastCnt, $companyResultList['LE_12']);
				$objSheet->setCellValueByColumnAndRow(15, $eastCnt, $companyResultList['LL_14'] + $companyResultList['LL_74']);
				$objSheet->setCellValueByColumnAndRow(16, $eastCnt, $companyResultList['LL_13'] + $companyResultList['LL_75']);
				$objSheet->setCellValueByColumnAndRow(18, $eastCnt, $companyResultList['LC_16']);
				$objSheet->setCellValueByColumnAndRow(19, $eastCnt, $companyResultList['LC_15']);
				//$objSheet->setCellValueByColumnAndRow(23, $eastCnt, $companyResultList['LE:sub_6_1_11']);
				//$objSheet->setCellValueByColumnAndRow(24, $eastCnt, $companyResultList['LE:sub_6_1_12']);
				$objSheet->setCellValueByColumnAndRow(23, $eastCnt, $companyPlanList['LEB_86']);	// LEBに変更
				$objSheet->setCellValueByColumnAndRow(24, $eastCnt, $companyResultList['LEB_86']);	// LEBに変更
				$objSheet->setCellValueByColumnAndRow(25, $eastCnt, $companyPlanList['LEB_87']);
				$objSheet->setCellValueByColumnAndRow(26, $eastCnt, $companyResultList['LEB_87']);
				$objSheet->setCellValueByColumnAndRow(28, $eastCnt, $companyResultList['LH:sub_4_1_8']);
				$objSheet->setCellValueByColumnAndRow(29, $eastCnt, $companyResultList['LH:sub_4_1_7']);
				if(local_config::FEATURE_ADD_LMS_LC_EXCELSHEET) {
					$objSheet->setCellValueByColumnAndRow(32, $eastCnt, $companyResultList['LC_Period_Cnt']);
				}
			}
			
			// 中央地区
			if ($executiveArray['area'] === '中央地区') {
				
				$objSheet->setCellValueByColumnAndRow(0,  $centralCnt, $executiveArray['name']);
				$objSheet->setCellValueByColumnAndRow(11, $centralCnt, $executiveArray['name']);
				$objSheet->setCellValueByColumnAndRow(22, $centralCnt, $executiveArray['name']);
				
				$objSheet->setCellValueByColumnAndRow(1,  $centralCnt, $companyResultList['LT_5']);
				$objSheet->setCellValueByColumnAndRow(2,  $centralCnt, $companyResultList['LT_6']);
				$objSheet->setCellValueByColumnAndRow(4,  $centralCnt, $companyResultList['LH_8']);
				$objSheet->setCellValueByColumnAndRow(5,  $centralCnt, $companyResultList['LH_7']);
				$objSheet->setCellValueByColumnAndRow(7,  $centralCnt, $companyResultList['LO_9']);
				$objSheet->setCellValueByColumnAndRow(8,  $centralCnt, $companyResultList['LO_10']);
				$objSheet->setCellValueByColumnAndRow(12, $centralCnt, $companyResultList['LE_11']);
				$objSheet->setCellValueByColumnAndRow(13, $centralCnt, $companyResultList['LE_12']);
				$objSheet->setCellValueByColumnAndRow(15, $centralCnt, $companyResultList['LL_14'] + $companyResultList['LL_74']);
				$objSheet->setCellValueByColumnAndRow(16, $centralCnt, $companyResultList['LL_13'] + $companyResultList['LL_75']);
				$objSheet->setCellValueByColumnAndRow(18, $centralCnt, $companyResultList['LC_16']);
				$objSheet->setCellValueByColumnAndRow(19, $centralCnt, $companyResultList['LC_15']);
				//$objSheet->setCellValueByColumnAndRow(23, $centralCnt, $companyResultList['LE:sub_6_1_11']);
				//$objSheet->setCellValueByColumnAndRow(24, $centralCnt, $companyResultList['LE:sub_6_1_12']);
				$objSheet->setCellValueByColumnAndRow(23, $centralCnt, $companyPlanList['LEB_86']);		// LEBに変更
				$objSheet->setCellValueByColumnAndRow(24, $centralCnt, $companyResultList['LEB_86']);	// LEBに変更
				$objSheet->setCellValueByColumnAndRow(25, $centralCnt, $companyPlanList['LEB_87']);
				$objSheet->setCellValueByColumnAndRow(26, $centralCnt, $companyResultList['LEB_87']);

				$objSheet->setCellValueByColumnAndRow(28, $centralCnt, $companyResultList['LH:sub_4_1_8']);
				$objSheet->setCellValueByColumnAndRow(29, $centralCnt, $companyResultList['LH:sub_4_1_7']);
				if(local_config::FEATURE_ADD_LMS_LC_EXCELSHEET) {
					$objSheet->setCellValueByColumnAndRow(32, $centralCnt, $companyResultList['LC_Period_Cnt']);
				}
			}
			
			// 西地区
			if ($executiveArray['area'] === '西地区') {
				
				$objSheet->setCellValueByColumnAndRow(0,  $westCnt, $executiveArray['name']);
				$objSheet->setCellValueByColumnAndRow(11, $westCnt, $executiveArray['name']);
				$objSheet->setCellValueByColumnAndRow(22, $westCnt, $executiveArray['name']);
				
				$objSheet->setCellValueByColumnAndRow(1,  $westCnt, $companyResultList['LT_5']);
				$objSheet->setCellValueByColumnAndRow(2,  $westCnt, $companyResultList['LT_6']);
				$objSheet->setCellValueByColumnAndRow(4,  $westCnt, $companyResultList['LH_8']);
				$objSheet->setCellValueByColumnAndRow(5,  $westCnt, $companyResultList['LH_7']);
				$objSheet->setCellValueByColumnAndRow(7,  $westCnt, $companyResultList['LO_9']);
				$objSheet->setCellValueByColumnAndRow(8,  $westCnt, $companyResultList['LO_10']);
				$objSheet->setCellValueByColumnAndRow(12, $westCnt, $companyResultList['LE_11']);
				$objSheet->setCellValueByColumnAndRow(13, $westCnt, $companyResultList['LE_12']);
				$objSheet->setCellValueByColumnAndRow(15, $westCnt, $companyResultList['LL_14'] + $companyResultList['LL_74']);
				$objSheet->setCellValueByColumnAndRow(16, $westCnt, $companyResultList['LL_13'] + $companyResultList['LL_75']);
				$objSheet->setCellValueByColumnAndRow(18, $westCnt, $companyResultList['LC_16']);
				$objSheet->setCellValueByColumnAndRow(19, $westCnt, $companyResultList['LC_15']);
				//$objSheet->setCellValueByColumnAndRow(23, $westCnt, $companyResultList['LE:sub_6_1_11']);
				//$objSheet->setCellValueByColumnAndRow(24, $westCnt, $companyResultList['LE:sub_6_1_12']);
				$objSheet->setCellValueByColumnAndRow(23, $westCnt, $companyPlanList['LEB_86']);	// LEBに変更
				$objSheet->setCellValueByColumnAndRow(24, $westCnt, $companyResultList['LEB_86']);	// LEBに変更
				$objSheet->setCellValueByColumnAndRow(25, $westCnt, $companyPlanList['LEB_87']);
				$objSheet->setCellValueByColumnAndRow(26, $westCnt, $companyResultList['LEB_87']);

				$objSheet->setCellValueByColumnAndRow(28, $westCnt, $companyResultList['LH:sub_4_1_8']);
				$objSheet->setCellValueByColumnAndRow(29, $westCnt, $companyResultList['LH:sub_4_1_7']);
				if(local_config::FEATURE_ADD_LMS_LC_EXCELSHEET) {
					$objSheet->setCellValueByColumnAndRow(32, $westCnt, $companyResultList['LC_Period_Cnt']);
				}
			}
			// 支部
			if ($executiveArray['area'] === 'ロータス神奈川') {
				
				$objSheet->setCellValueByColumnAndRow(1,  70, $companyResultList['LT_5']);
				$objSheet->setCellValueByColumnAndRow(2,  70, $companyResultList['LT_6']);
				$objSheet->setCellValueByColumnAndRow(4,  70, $companyResultList['LH_8']);
				$objSheet->setCellValueByColumnAndRow(5,  70, $companyResultList['LH_7']);
				$objSheet->setCellValueByColumnAndRow(7,  70, $companyResultList['LO_9']);
				$objSheet->setCellValueByColumnAndRow(8,  70, $companyResultList['LO_10']);
				$objSheet->setCellValueByColumnAndRow(12, 70, $companyResultList['LE_11']);
				$objSheet->setCellValueByColumnAndRow(13, 70, $companyResultList['LE_12']);
				$objSheet->setCellValueByColumnAndRow(15, 70, $companyResultList['LL_14'] + $companyResultList['LL_74']);
				$objSheet->setCellValueByColumnAndRow(16, 70, $companyResultList['LL_13'] + $companyResultList['LL_75']);
				$objSheet->setCellValueByColumnAndRow(18, 70, $companyResultList['LC_16']);
				$objSheet->setCellValueByColumnAndRow(19, 70, $companyResultList['LC_15']);
				//$objSheet->setCellValueByColumnAndRow(23, 70, $companyResultList['LE:sub_6_1_11']);
				//$objSheet->setCellValueByColumnAndRow(24, 70, $companyResultList['LE:sub_6_1_12']);
				$objSheet->setCellValueByColumnAndRow(23, 70, $companyPlanList['LEB_86']);		// LEBに変更
				$objSheet->setCellValueByColumnAndRow(24, 70, $companyResultList['LEB_86']);	// LEBに変更
				$objSheet->setCellValueByColumnAndRow(25, 70, $companyPlanList['LEB_87']);
				$objSheet->setCellValueByColumnAndRow(26, 70, $companyResultList['LEB_87']);

				$objSheet->setCellValueByColumnAndRow(28, 70, $companyResultList['LH:sub_4_1_8']);
				$objSheet->setCellValueByColumnAndRow(29, 70, $companyResultList['LH:sub_4_1_7']);
				if(local_config::FEATURE_ADD_LMS_LC_EXCELSHEET) {
					$objSheet->setCellValueByColumnAndRow(32, 70, $companyResultList['LC_Period_Cnt']);
				}
			}

			// ★★★★ 補助種目シート ★★★★
			$objPHPExcelCampaign->setActiveSheetIndex(3); 	// 補助種目シートをアクティブにする
			$objSheet = $objPHPExcelCampaign->getActiveSheet();

			// 東地区
			if ($executiveArray['area'] === '東地区') {
				$objSheet->setCellValueByColumnAndRow(1, $eastCnt, $companyResultList['LM:sub_1_1_2']);
				$objSheet->setCellValueByColumnAndRow(2, $eastCnt, $companyResultList['LM:sub_1_1_3']);
				$objSheet->setCellValueByColumnAndRow(3, $eastCnt, $companyResultList['LM:sub_1_1_17']);
				$objSheet->setCellValueByColumnAndRow(5, $eastCnt, $companyResultList['LM:sub_1_2_2']);
				$objSheet->setCellValueByColumnAndRow(6, $eastCnt, $companyResultList['LM:sub_1_2_3']);
				$objSheet->setCellValueByColumnAndRow(7, $eastCnt, $companyResultList['LM:sub_1_2_17']);
				$objSheet->setCellValueByColumnAndRow(9, $eastCnt, $companyResultList['LM:sub_1_3_2']);
				$objSheet->setCellValueByColumnAndRow(10, $eastCnt, $companyResultList['LM:sub_1_3_3']);
				$objSheet->setCellValueByColumnAndRow(11, $eastCnt, $companyResultList['LM:sub_1_3_17']);
				$objSheet->setCellValueByColumnAndRow(13, $eastCnt, $companyResultList['LS:sub_2_1_4']);
				$objSheet->setCellValueByColumnAndRow(15, $eastCnt, $companyResultList['LS:sub_2_2_4']);
				$objSheet->setCellValueByColumnAndRow(17, $eastCnt, $companyResultList['LS:sub_2_3_4']);
				$objSheet->setCellValueByColumnAndRow(19, $eastCnt, $companyResultList['LT:sub_3_1_5']);
				$objSheet->setCellValueByColumnAndRow(20, $eastCnt, $companyResultList['LT:sub_3_1_6']);
				$objSheet->setCellValueByColumnAndRow(22, $eastCnt, $companyResultList['LH:sub_4_1_7']);
				$objSheet->setCellValueByColumnAndRow(23, $eastCnt, $companyResultList['LH:sub_4_1_8']);

				$objSheet->setCellValueByColumnAndRow(25, $eastCnt, $companyResultList['LE:sub_6_1_11']);
				$objSheet->setCellValueByColumnAndRow(26, $eastCnt, $companyResultList['LE:sub_6_1_12']);
				$objSheet->setCellValueByColumnAndRow(28, $eastCnt, $companyResultList['LE:sub_6_2_12']);
				$objSheet->setCellValueByColumnAndRow(30, $eastCnt, $companyResultList['LE:sub_6_3_12']);

				$objSheet->setCellValueByColumnAndRow(32, $eastCnt, $companyResultList['LC:sub_8_1_15']);
				$objSheet->setCellValueByColumnAndRow(33, $eastCnt, $companyResultList['LC:sub_8_1_16']);
				$objSheet->setCellValueByColumnAndRow(35, $eastCnt, $companyResultList['LC:spitem_15']);
				$objSheet->setCellValueByColumnAndRow(36, $eastCnt, $companyResultList['LC:spitem_16']);

				// LO 6月分
				$objSheet->setCellValueByColumnAndRow(38, $eastCnt, $companyResultList['LO_10_m6']);
				$objSheet->setCellValueByColumnAndRow(39, $eastCnt, $companyResultList['LO_9_m6']);

				// LO 10月分
				$objSheet->setCellValueByColumnAndRow(55, $eastCnt, $companyResultList['LO_10_m10']);
				$objSheet->setCellValueByColumnAndRow(56, $eastCnt, $companyResultList['LO_9_m10']);

				// LO 2月分
				$objSheet->setCellValueByColumnAndRow(58, $eastCnt, $companyResultList['LO_10_m2']);
				$objSheet->setCellValueByColumnAndRow(59, $eastCnt, $companyResultList['LO_9_m2']);

				// LC 第2四半期
				$objSheet->setCellValueByColumnAndRow(41, $eastCnt, $companyResultList['LC_15_q2']);
				$objSheet->setCellValueByColumnAndRow(42, $eastCnt, $companyResultList['LC_16_q2']);

				// LSアリーナ店小型実績
				$objSheet->setCellValueByColumnAndRow(44, $eastCnt, $companyResultList['LS:sub_2_4_4']);

				// LC 第1三半期
				$objSheet->setCellValueByColumnAndRow(46, $eastCnt, $companyResultList['LC_15_m4_7']);
				$objSheet->setCellValueByColumnAndRow(47, $eastCnt, $companyResultList['LC_16_m4_7']);

				// LC 第2三半期
				$objSheet->setCellValueByColumnAndRow(49, $eastCnt, $companyResultList['LC_15_m8_11']);
				$objSheet->setCellValueByColumnAndRow(50, $eastCnt, $companyResultList['LC_16_m8_11']);

				// LC 第3三半期
				$objSheet->setCellValueByColumnAndRow(52, $eastCnt, $companyResultList['LC_15_m12_3']);
				$objSheet->setCellValueByColumnAndRow(53, $eastCnt, $companyResultList['LC_16_m12_3']);

				// LC 6月、10月、2月
				$objSheet->setCellValueByColumnAndRow(61, $eastCnt, $companyResultList['LC_6m_total']);
				$objSheet->setCellValueByColumnAndRow(62, $eastCnt, $companyResultList['LC_10m_total']);
				$objSheet->setCellValueByColumnAndRow(63, $eastCnt, $companyResultList['LC_2m_total']);

				// LC 7月、11月、3月
				$objSheet->setCellValueByColumnAndRow(64, $eastCnt, $companyResultList['LC_7m_total']);
				$objSheet->setCellValueByColumnAndRow(65, $eastCnt, $companyResultList['LC_11m_total']);
				$objSheet->setCellValueByColumnAndRow(66, $eastCnt, $companyResultList['LC_3m_total']);

				// LM 上期、下期、年間
				$objSheet->setCellValueByColumnAndRow(67, $eastCnt, $companyResultList['LM_half1_total']);
				$objSheet->setCellValueByColumnAndRow(68, $eastCnt, $companyResultList['LM_half2_total']);
				$objSheet->setCellValueByColumnAndRow(69, $eastCnt, $companyResultList['LM_year_total']);
			}
			// 中央地区
			if ($executiveArray['area'] === '中央地区') {
				$objSheet->setCellValueByColumnAndRow(1, $centralCnt, $companyResultList['LM:sub_1_1_2']);
				$objSheet->setCellValueByColumnAndRow(2, $centralCnt, $companyResultList['LM:sub_1_1_3']);
				$objSheet->setCellValueByColumnAndRow(3, $centralCnt, $companyResultList['LM:sub_1_1_17']);
				$objSheet->setCellValueByColumnAndRow(5, $centralCnt, $companyResultList['LM:sub_1_2_2']);
				$objSheet->setCellValueByColumnAndRow(6, $centralCnt, $companyResultList['LM:sub_1_2_3']);
				$objSheet->setCellValueByColumnAndRow(7, $centralCnt, $companyResultList['LM:sub_1_2_17']);
				$objSheet->setCellValueByColumnAndRow(9, $centralCnt, $companyResultList['LM:sub_1_3_2']);
				$objSheet->setCellValueByColumnAndRow(10, $centralCnt, $companyResultList['LM:sub_1_3_3']);
				$objSheet->setCellValueByColumnAndRow(11, $centralCnt, $companyResultList['LM:sub_1_3_17']);
				$objSheet->setCellValueByColumnAndRow(13, $centralCnt, $companyResultList['LS:sub_2_1_4']);
				$objSheet->setCellValueByColumnAndRow(15, $centralCnt, $companyResultList['LS:sub_2_2_4']);
				$objSheet->setCellValueByColumnAndRow(17, $centralCnt, $companyResultList['LS:sub_2_3_4']);
				$objSheet->setCellValueByColumnAndRow(19, $centralCnt, $companyResultList['LT:sub_3_1_5']);
				$objSheet->setCellValueByColumnAndRow(20, $centralCnt, $companyResultList['LT:sub_3_1_6']);
				$objSheet->setCellValueByColumnAndRow(22, $centralCnt, $companyResultList['LH:sub_4_1_7']);
				$objSheet->setCellValueByColumnAndRow(23, $centralCnt, $companyResultList['LH:sub_4_1_8']);

				$objSheet->setCellValueByColumnAndRow(25, $centralCnt, $companyResultList['LE:sub_6_1_11']);
				$objSheet->setCellValueByColumnAndRow(26, $centralCnt, $companyResultList['LE:sub_6_1_12']);
				$objSheet->setCellValueByColumnAndRow(28, $centralCnt, $companyResultList['LE:sub_6_2_12']);
				$objSheet->setCellValueByColumnAndRow(30, $centralCnt, $companyResultList['LE:sub_6_3_12']);

				$objSheet->setCellValueByColumnAndRow(32, $centralCnt, $companyResultList['LC:sub_8_1_15']);
				$objSheet->setCellValueByColumnAndRow(33, $centralCnt, $companyResultList['LC:sub_8_1_16']);
				$objSheet->setCellValueByColumnAndRow(35, $centralCnt, $companyResultList['LC:spitem_15']);
				$objSheet->setCellValueByColumnAndRow(36, $centralCnt, $companyResultList['LC:spitem_16']);

				// LO 6月分
				$objSheet->setCellValueByColumnAndRow(38, $centralCnt, $companyResultList['LO_10_m6']);
				$objSheet->setCellValueByColumnAndRow(39, $centralCnt, $companyResultList['LO_9_m6']);

				// LO 10月分
				$objSheet->setCellValueByColumnAndRow(55, $centralCnt, $companyResultList['LO_10_m10']);
				$objSheet->setCellValueByColumnAndRow(56, $centralCnt, $companyResultList['LO_9_m10']);

				// LO 2月分
				$objSheet->setCellValueByColumnAndRow(58, $centralCnt, $companyResultList['LO_10_m2']);
				$objSheet->setCellValueByColumnAndRow(59, $centralCnt, $companyResultList['LO_9_m2']);

				// LC 第2四半期
				$objSheet->setCellValueByColumnAndRow(41, $centralCnt, $companyResultList['LC_15_q2']);
				$objSheet->setCellValueByColumnAndRow(42, $centralCnt, $companyResultList['LC_16_q2']);

				// LSアリーナ店小型実績
				$objSheet->setCellValueByColumnAndRow(44, $centralCnt, $companyResultList['LS:sub_2_4_4']);

				// LC 第1三半期
				$objSheet->setCellValueByColumnAndRow(46, $centralCnt, $companyResultList['LC_15_m4_7']);
				$objSheet->setCellValueByColumnAndRow(47, $centralCnt, $companyResultList['LC_16_m4_7']);

				// LC 第2三半期
				$objSheet->setCellValueByColumnAndRow(49, $centralCnt, $companyResultList['LC_15_m8_11']);
				$objSheet->setCellValueByColumnAndRow(50, $centralCnt, $companyResultList['LC_16_m8_11']);

				// LC 第3三半期
				$objSheet->setCellValueByColumnAndRow(52, $centralCnt, $companyResultList['LC_15_m12_3']);
				$objSheet->setCellValueByColumnAndRow(53, $centralCnt, $companyResultList['LC_16_m12_3']);

				// LC 6月、10月、2月
				$objSheet->setCellValueByColumnAndRow(61, $centralCnt, $companyResultList['LC_6m_total']);
				$objSheet->setCellValueByColumnAndRow(62, $centralCnt, $companyResultList['LC_10m_total']);
				$objSheet->setCellValueByColumnAndRow(63, $centralCnt, $companyResultList['LC_2m_total']);

				// LC 7月、11月、3月
				$objSheet->setCellValueByColumnAndRow(64, $centralCnt, $companyResultList['LC_7m_total']);
				$objSheet->setCellValueByColumnAndRow(65, $centralCnt, $companyResultList['LC_11m_total']);
				$objSheet->setCellValueByColumnAndRow(66, $centralCnt, $companyResultList['LC_3m_total']);

				// LM 上期、下期、年間
				$objSheet->setCellValueByColumnAndRow(67, $centralCnt, $companyResultList['LM_half1_total']);
				$objSheet->setCellValueByColumnAndRow(68, $centralCnt, $companyResultList['LM_half2_total']);
				$objSheet->setCellValueByColumnAndRow(69, $centralCnt, $companyResultList['LM_year_total']);
			}
			// 西地区
			if ($executiveArray['area'] === '西地区') {
				$objSheet->setCellValueByColumnAndRow(1, $westCnt, $companyResultList['LM:sub_1_1_2']);
				$objSheet->setCellValueByColumnAndRow(2, $westCnt, $companyResultList['LM:sub_1_1_3']);
				$objSheet->setCellValueByColumnAndRow(3, $westCnt, $companyResultList['LM:sub_1_1_17']);
				$objSheet->setCellValueByColumnAndRow(5, $westCnt, $companyResultList['LM:sub_1_2_2']);
				$objSheet->setCellValueByColumnAndRow(6, $westCnt, $companyResultList['LM:sub_1_2_3']);
				$objSheet->setCellValueByColumnAndRow(7, $westCnt, $companyResultList['LM:sub_1_2_17']);
				$objSheet->setCellValueByColumnAndRow(9, $westCnt, $companyResultList['LM:sub_1_3_2']);
				$objSheet->setCellValueByColumnAndRow(10, $westCnt, $companyResultList['LM:sub_1_3_3']);
				$objSheet->setCellValueByColumnAndRow(11, $westCnt, $companyResultList['LM:sub_1_3_17']);
				$objSheet->setCellValueByColumnAndRow(13, $westCnt, $companyResultList['LS:sub_2_1_4']);
				$objSheet->setCellValueByColumnAndRow(15, $westCnt, $companyResultList['LS:sub_2_2_4']);
				$objSheet->setCellValueByColumnAndRow(17, $westCnt, $companyResultList['LS:sub_2_3_4']);
				$objSheet->setCellValueByColumnAndRow(19, $westCnt, $companyResultList['LT:sub_3_1_5']);
				$objSheet->setCellValueByColumnAndRow(20, $westCnt, $companyResultList['LT:sub_3_1_6']);
				$objSheet->setCellValueByColumnAndRow(22, $westCnt, $companyResultList['LH:sub_4_1_7']);
				$objSheet->setCellValueByColumnAndRow(23, $westCnt, $companyResultList['LH:sub_4_1_8']);

				$objSheet->setCellValueByColumnAndRow(25, $westCnt, $companyResultList['LE:sub_6_1_11']);
				$objSheet->setCellValueByColumnAndRow(26, $westCnt, $companyResultList['LE:sub_6_1_12']);
				$objSheet->setCellValueByColumnAndRow(28, $westCnt, $companyResultList['LE:sub_6_2_12']);
				$objSheet->setCellValueByColumnAndRow(30, $westCnt, $companyResultList['LE:sub_6_3_12']);

				$objSheet->setCellValueByColumnAndRow(32, $westCnt, $companyResultList['LC:sub_8_1_15']);
				$objSheet->setCellValueByColumnAndRow(33, $westCnt, $companyResultList['LC:sub_8_1_16']);
				$objSheet->setCellValueByColumnAndRow(35, $westCnt, $companyResultList['LC:spitem_15']);
				$objSheet->setCellValueByColumnAndRow(36, $westCnt, $companyResultList['LC:spitem_16']);

				// LO 6月分
				$objSheet->setCellValueByColumnAndRow(38, $westCnt, $companyResultList['LO_10_m6']);
				$objSheet->setCellValueByColumnAndRow(39, $westCnt, $companyResultList['LO_9_m6']);

				// LO 10月分
				$objSheet->setCellValueByColumnAndRow(55, $westCnt, $companyResultList['LO_10_m10']);
				$objSheet->setCellValueByColumnAndRow(56, $westCnt, $companyResultList['LO_9_m10']);

				// LO 2月分
				$objSheet->setCellValueByColumnAndRow(58, $westCnt, $companyResultList['LO_10_m2']);
				$objSheet->setCellValueByColumnAndRow(59, $westCnt, $companyResultList['LO_9_m2']);

				// LC 第2四半期
				$objSheet->setCellValueByColumnAndRow(41, $westCnt, $companyResultList['LC_15_q2']);
				$objSheet->setCellValueByColumnAndRow(42, $westCnt, $companyResultList['LC_16_q2']);

				// LSアリーナ店小型実績
				$objSheet->setCellValueByColumnAndRow(44, $westCnt, $companyResultList['LS:sub_2_4_4']);

				// LC 第1三半期
				$objSheet->setCellValueByColumnAndRow(46, $westCnt, $companyResultList['LC_15_m4_7']);
				$objSheet->setCellValueByColumnAndRow(47, $westCnt, $companyResultList['LC_16_m4_7']);

				// LC 第2三半期
				$objSheet->setCellValueByColumnAndRow(49, $westCnt, $companyResultList['LC_15_m8_11']);
				$objSheet->setCellValueByColumnAndRow(50, $westCnt, $companyResultList['LC_16_m8_11']);

				// LC 第3三半期
				$objSheet->setCellValueByColumnAndRow(52, $westCnt, $companyResultList['LC_15_m12_3']);
				$objSheet->setCellValueByColumnAndRow(53, $westCnt, $companyResultList['LC_16_m12_3']);

				// LC 6月、10月、2月
				$objSheet->setCellValueByColumnAndRow(61, $westCnt, $companyResultList['LC_6m_total']);
				$objSheet->setCellValueByColumnAndRow(62, $westCnt, $companyResultList['LC_10m_total']);
				$objSheet->setCellValueByColumnAndRow(63, $westCnt, $companyResultList['LC_2m_total']);

				// LC 7月、11月、3月
				$objSheet->setCellValueByColumnAndRow(64, $westCnt, $companyResultList['LC_7m_total']);
				$objSheet->setCellValueByColumnAndRow(65, $westCnt, $companyResultList['LC_11m_total']);
				$objSheet->setCellValueByColumnAndRow(66, $westCnt, $companyResultList['LC_3m_total']);

				// LM 上期、下期、年間
				$objSheet->setCellValueByColumnAndRow(67, $westCnt, $companyResultList['LM_half1_total']);
				$objSheet->setCellValueByColumnAndRow(68, $westCnt, $companyResultList['LM_half2_total']);
				$objSheet->setCellValueByColumnAndRow(69, $westCnt, $companyResultList['LM_year_total']);
			}

			// 同友に出力する行を次の同友に移動させる
			// 東地区
			if ($executiveArray['area'] === '東地区') {
				$eastCnt++;
			}
			// 中央地区
			if ($executiveArray['area'] === '中央地区') {
				$centralCnt++;
			}
			// 西地区
			if ($executiveArray['area'] === '西地区') {
				$westCnt++;
			}
		} // sokuho

		//===================================================
		// 同友チャレンジシートをExcelに出力
		//===================================================
		// 同友チャレンジシートは管理者のときだけ生成（神奈川だけ役員もOK）
		if ($target === 'challenge') {
			$user_info = getUserInfo($_SESSION['USERID']);
			if ($user_info['auth'] == config::USER_ADMIN || (local_config::$DB_TABLE_PREFIX === "kngw" && $listArray['user_info']['auth'] == config::USER_EXEOFFICER) ) {

				// 同友以外の調整項目の場合は飛ばす
				if ($executiveArray['auth'] != config::USER_EXECUTIVE) {
					continue;
				}
				
				// 対象のシートをアクティブにする（シートは左から順に、0、1，2・・・）
				$objPHPExcelExecutive->setActiveSheetIndex(0);	// テンプレートをアクティブ
				$objSheet = addSheet($objPHPExcelExecutive, $executiveArray['user'], $sheetCnt);
				$objSheet->setCellValueByColumnAndRow(1, 1, 'ロータス '.$executiveArray['name'].' 殿');
				$objSheet->setCellValueByColumnAndRow(1, 3, $fiscal_year.'年度'.$title.' 目標管理シート');
				
				// LM+LS対策
				if ($fiscal_year >= config::CAMPAIGN_LMLS_ADD_YEAR) {
					$objSheet->setCellValueByColumnAndRow(15, 1, getCurrentDate('.').' 速報');
				}
				else {
					$objSheet->setCellValueByColumnAndRow(14, 1, getCurrentDate('.').' 速報');
				}

				// LC 7月、11月、3月
				$objSheet->setCellValueByColumnAndRow(20, 7, $companyResultList['LC_7m_total']);
				$objSheet->setCellValueByColumnAndRow(21, 7, $companyResultList['LC_11m_total']);
				$objSheet->setCellValueByColumnAndRow(22, 7, $companyResultList['LC_3m_total']);
				
				// LM 上期、下期、年間を合算して出力
				$objSheet->setCellValueByColumnAndRow(20, 14, $companyResultList['LM_half1_total']); 	// LM上期(U列)
				$objSheet->setCellValueByColumnAndRow(21, 14, $companyResultList['LM_half2_total']); 	// LM下期(V列)
				$objSheet->setCellValueByColumnAndRow(22, 14, $companyResultList['LM_year_total']); 	// LM年間(W列)
				
				//---------------------------------------------------
				// 同友チャレンジシート
				// キャンペーン速報を出力
				//---------------------------------------------------
				$columnCnt = 0;
				foreach ($itemList as $itemArray) {
				
					if (strpos($itemArray['value'], ':sub_') !== false || strpos($itemArray['value'], ':spitem') !== false ) {
						continue;
					}
					
					// LM+LS対策：
					if ($fiscal_year >= config::CAMPAIGN_LMLS_ADD_YEAR) {
						if ($itemArray['value'] === 'LT') {
							$columnCnt++;	// LM+LSの列があるのでLTの時に一つ列を飛ばす
						}
						// else if ($itemArray['value'] === 'LC') {
						// 	continue;		// LCはキャンペーン速報には表示しない
						// }
					}
				
					$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 4, $itemArray['value'].PHP_EOL.'('.$itemArray['unit'].')');

					if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 6, $planTotalList[$itemArray['value']]);
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 7, $resultTotalList[$itemArray['value']]);
						$columnCnt++;
					}
					else if ($itemArray['value'] === 'LEB') {
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 6, $companyPlanList['LEB_86']);
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt++, 7, $companyResultList['LEB_86']);
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 6, $companyPlanList['LEB_87']);
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt++, 7, $companyResultList['LEB_87']);
						//$columnCnt+=2;
					}
					else {
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 6, $planTotalList[$itemArray['value']]);

						// LCの実績は12～3月の実績
						if ($itemArray['value'] === 'LC' && $fiscal_year >= 2021) {
							$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 7, $companyResultList['LC_Cashbck_Cnt']);
						}
						else {
							$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 7, $resultTotalList[$itemArray['value']]);
						}
						$columnCnt+=2;
					}
				}

				if (local_config::FEATURE_AREA_REACH_AWARD) {
				//---------------------------------------------------
				// 同友チャレンジシート
				// キャッシュバック条件達成状況
				//---------------------------------------------------
				//$campaignInfo = getCampaignInfo($fiscal_year, 'LM');
				// $opt = $campaignInfo[$campaign.'_opt'];
				// $optArray = preg_split("/-/", $opt);
				// $areaMustLCCnt = preg_split("/\,/", $optArray[3]);
				// $areaMustLCCntforAward = preg_split("/\,/", $optArray[4]);

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

				// LCキャッシュバック目標枚数
				if ($fiscal_year < 2021) { // ★★★ 2021年度以降の暫定措置なので2023年度以降は変わるかも
					$objSheet->setCellValueByColumnAndRow(5, 13, $areaMustLCCnt[1]);	// 目標
				}
				//$objSheet->setCellValueByColumnAndRow(6, 13, $companyResultList['LC_15'] + $companyResultList['LC_16']); // 実績（キャンペーン期間中）
				$objSheet->setCellValueByColumnAndRow(6, 13, $companyResultList['LC_Cashbck_Cnt']); // 実績

				// LO単月（6月、10月、2月）実績出力（※ 2021年度から出力）
				if ($fiscal_year >= 2021) {
					$objSheet->setCellValueByColumnAndRow(9, 13, $companyResultList['LO_10_m6'] + $companyResultList['LO_9_m6']); 	// LO 6月分
					$objSheet->setCellValueByColumnAndRow(10, 13, $companyResultList['LO_10_m10'] + $companyResultList['LO_9_m10']);	// LO 10月分
					$objSheet->setCellValueByColumnAndRow(11, 13, $companyResultList['LO_10_m2'] + $companyResultList['LO_9_m2']);	// LO 2月分
				}

				// LC単月（6月、10月、2月）実績出力（※ 2022年度から出力）
				if ($fiscal_year >= 2022) {
					$objSheet->setCellValueByColumnAndRow(12, 13, $companyResultList['LC_6m_total']); 	// LC 6月分
					$objSheet->setCellValueByColumnAndRow(13, 13, $companyResultList['LC_10m_total']);	// LC 10月分
					$objSheet->setCellValueByColumnAndRow(14, 13, $companyResultList['LC_2m_total']);	// LC 2月分
				}

				// パーフェクト賞、準パーフェクト賞(列Aは0)
				$objSheet->setCellValueByColumnAndRow(5, 18, config::PERFECT_AWARD_ITEM_NUM);			// パーフェクト賞
				$objSheet->setCellValueByColumnAndRow(5, 19, config::SECOND_PERFECT_AWARD_ITEM_NUM);	// 準パーフェクト賞
				if ($fiscal_year < 2021) { // ★★★ 2021年度以降の暫定措置なので2023年度以降は変わるかも
					$objSheet->setCellValueByColumnAndRow(6, 18, $areaMustLCCntforAward[1]);		// LC目標（パーフェクト賞）
					$objSheet->setCellValueByColumnAndRow(6, 19, $areaMustLCCntforAward[1]);		// LC目標（準パーフェクト賞）
				}
				$objSheet->setCellValueByColumnAndRow(8, 18, $companyResultList['LC_Cnt']);		// LC実績
				$objSheet->setCellValueByColumnAndRow(8, 19, $companyResultList['LC_Cnt']);		// LC実績
				
				} // FEATURE_AREA_REACH_AWARD

				if (local_config::FEATURE_LC_HOLD_NUMBER) {
				//---------------------------------------------------
				// 年間優績同友表彰基準達成状況
				//---------------------------------------------------
				$lcOption = getLCHoldPromotionInfo($fiscal_year);
				$msg = "最優秀賞".$lcOption['lc_get_item_count_1']."種目の賞金は".$lcOption['lc_get_item_prize_1']."円、".$lcOption['lc_get_item_count_2']."種目の賞金は".$lcOption['lc_get_item_prize_2']."円";
				$objSheet->setCellValueByColumnAndRow(1, 36, $msg);	// 説明
				$msg = "最優秀賞".$lcOption['lc_get_item_count_1']."種目";
				$objSheet->setCellValueByColumnAndRow(1, 34, $msg);	// 項目名
				$objSheet->setCellValueByColumnAndRow(5, 34, $lcOption['lc_get_item_count_1']);		// 種目達成目標
				$objSheet->setCellValueByColumnAndRow(6, 34, $lcOption['lc_get_promotion_count']);	// LC目標枚数
				$msg = "準優秀賞".$lcOption['lc_get_item_count_2']."種目";
				$objSheet->setCellValueByColumnAndRow(1, 35, $msg);	// 項目名
				$objSheet->setCellValueByColumnAndRow(5, 35, $lcOption['lc_get_item_count_2']);		// 種目達成目標
				$objSheet->setCellValueByColumnAndRow(6, 35, $lcOption['lc_get_promotion_count']);	// LC目標枚数
				
				} // FEATURE_LC_HOLD_NUMBER

				//---------------------------------------------------
				// 同友チャレンジシート
				// 年間情報を出力
				//---------------------------------------------------
				// 初期化
				$columnCnt = 0;
				$planTotalList   = array();
				$resultTotalList = array();
				$companyPlanList = array();
				$companyResultList = array();
				
				// エクセルに出力
				$objSheet->setCellValueByColumnAndRow(1, 21, $fiscal_year.'年度年間優績表彰 目標管理シート');

				// 種目ごとの年間合計値を取得
				foreach ($itemList as $itemArray) {
					
					if (strpos($itemArray['value'], ':sub_') !== false || strpos($itemArray['value'], ':spitem') !== false ) {
						continue;
					}

					// 初期化
					$planTotal = 0;
					$resultTotal = 0;

					// 種目に該当する提携企業一覧を取得
					$partnerList = getPartnerList($fiscal_year, $itemArray['value']);
			
					// 提携企業数分の計画、実績、達成率を取得
					foreach ($partnerList as $partnerArray) {
					
						// 月別のデータを取得
						$data = getExecutiveResultTotalValue($fiscal_year, $itemArray['value'], '%', $partnerArray['value'], $executiveArray['value']);
						
						// 12ヶ月分を加算
						for ($i = 1; $i < 13; $i++) {
							$planTotal += $data[0][$i.'_plan'];
							$resultTotal += $data[0][$i.'_result'];
						}

						// 提携企業毎の計画と実績を保存
						$companyPlanList += array($itemArray['value'].'_'.$partnerArray['value'] => $planTotal);
						$companyResultList += array($itemArray['value'].'_'.$partnerArray['value'] => $resultTotal);

						// LEBのときだけリセット
						if ($itemArray['value'] === 'LEB') {
							$planTotal = 0;
							$resultTotal = 0;
						}
					}
					
					// 種目ごとに合計値を保存
					$planTotalList += array($itemArray['value'] => $planTotal);
					$resultTotalList += array($itemArray['value'] => $resultTotal);
					//printArray($companyResultList);

					// LM+LS対策：LM+LSの列があるのでLTの時に一つ列を飛ばす
					if ($fiscal_year >= config::CAMPAIGN_LMLS_ADD_YEAR) {
						if ($itemArray['value'] === 'LT') {
							$columnCnt++;
						}
					}
				
					//$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 22, $itemArray['value'].PHP_EOL.'('.$itemArray['unit'].')');

					if ($itemArray['value'] === 'LM' || $itemArray['value'] === 'LS') {
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 24, $planTotalList[$itemArray['value']]);
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 25, $resultTotalList[$itemArray['value']]);
						$columnCnt++;
					}
					else if ($itemArray['value'] === 'LEB') {
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 24, $companyPlanList['LEB_86']);
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt++, 25, $companyResultList['LEB_86']);
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 24, $companyPlanList['LEB_87']);
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt++, 25, $companyResultList['LEB_87']);
					}
					else {
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 24, $planTotalList[$itemArray['value']]);
						$objSheet->setCellValueByColumnAndRow(2+$columnCnt, 25, $resultTotalList[$itemArray['value']]);
						$columnCnt += 2;
					}
				}
				
				// シート数をカウント
				$sheetCnt++;
				
				//break; // デバッグ用
			} // USER_ADMIN
		} // challenge
	}

	//===================================================
	// 同友チャレンジシートの後処理
	//===================================================
	// テンプレートを削除し、先頭のシートをアクティブにする
	if ($target === 'challenge') {
		if ($user_info['auth'] == config::USER_ADMIN) {
			$objPHPExcelExecutive->removeSheetByIndex(0);
			$objPHPExcelExecutive->setActiveSheetIndex(0);
			$objSheet = $objPHPExcelExecutive->getActiveSheet();
			$objStyle = $objSheet->getStyle('A1:A1');
		} // USER_ADMIN	
	} // challenge
		
	//===================================================
	// キャンペーン速報の最初のシートへの入力
	//===================================================
	if ($target === 'sokuho') {
		// ★GURAFUシートをアクティブにする
		$objPHPExcelCampaign->setActiveSheetIndex(0);
		$objSheet = $objPHPExcelCampaign->getActiveSheet();
		
		$eastCnt    -= $EAST_START_ROW_NUM;
		$centralCnt -= $CENTRAL_START_ROW_NUM;
		$westCnt    -= $WESTL_START_ROW_NUM;

		// 参加同友数を確認
		$eastCnt    = 0;
		$centralCnt = 0;
		$westCnt    = 0;
		foreach ($executiveList as $executiveArray) {
			// キャンペーンに参加している同友のみカウント
			if ($executiveArray[$campaign.'_enterable'] < config::STATUS_RECESS) {
				if ($executiveArray['area'] === '東地区') {
					$eastCnt++;
				}
				else if ($executiveArray['area'] === '中央地区') {
					$centralCnt++;
				}
				else if ($executiveArray['area'] === '西地区') {
					$westCnt++;
				}
			}
		}
		
		// 各支部の参加同友数を入力
		$total = $eastCnt+$westCnt+$centralCnt;
		$objSheet->setCellValueByColumnAndRow(4, 3, '('.$total.'社)');
		$objSheet->setCellValueByColumnAndRow(5, 3, '('.$eastCnt.'社)');
		$objSheet->setCellValueByColumnAndRow(6, 3, '('.$centralCnt.'社)');
		$objSheet->setCellValueByColumnAndRow(7, 3, '('.$westCnt.'社)');

		// 初期化
		$planYearTotalList   = array();
		$resultYearTotalList = array();

		// 昨年の全種目の計画値と合計値を計算
		foreach ($itemList as $itemArray) {
		
			if (strpos($itemArray['value'], ':sub_') !== false) {
				continue;
			}
			
			// 初期化
			$planTotal = 0;
			$resultTotal = 0;
		
			// 種目に該当する提携企業一覧を取得
			$partnerList = getPartnerList($fiscal_year-1, $itemArray['value']);

			// 提携企業数分の計画、実績、達成率を取得
			foreach ($partnerList as $partnerArray) {
			
				// 計画取得
				$planData = getCampaignPlanTotalValue($fiscal_year-1, $itemArray['value'], $partnerArray['value'], '%', 0);
				$planTotal += $planData[$campaign.'_plan'];
				
				// 実績取得
				$resultData = getCampaignMonthTotalValue($fiscal_year-1, $itemArray['value'], $partnerArray['value'], '%', 0, $start_month, $end_month);
				$resultTotal += $resultData['result'];
			}
			$planYearTotalList += array($itemArray['value'] => $planTotal);
			$resultYearTotalList += array($itemArray['value'] => $resultTotal);
		}
		
		// 昨年の計画と実績をExcelに出力
		$lastYearCol = 8;
		// LM+LS対策
		if ($fiscal_year >= config::CAMPAIGN_LMLS_ADD_YEAR) {
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 5, $planYearTotalList['LM']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 6, $resultYearTotalList['LM']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 8, $planYearTotalList['LS']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 9, $resultYearTotalList['LS']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 14, $planYearTotalList['LT']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 15, $resultYearTotalList['LT']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 19, $planYearTotalList['LH']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 20, $resultYearTotalList['LH']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 24, $planYearTotalList['LO']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 25, $resultYearTotalList['LO']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 29, $planYearTotalList['LE']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 30, $resultYearTotalList['LE']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 34, $planYearTotalList['LL']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 35, $resultYearTotalList['LL']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 39, $planYearTotalList['LC']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 40, $resultYearTotalList['LC']);
		}
		else {
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 5, $planYearTotalList['LM']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 6, $resultYearTotalList['LM']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 8, $planYearTotalList['LS']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 9, $resultYearTotalList['LS']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 11, $planYearTotalList['LT']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 12, $resultYearTotalList['LT']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 16, $planYearTotalList['LH']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 17, $resultYearTotalList['LH']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 21, $planYearTotalList['LO']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 22, $resultYearTotalList['LO']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 26, $planYearTotalList['LE']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 27, $resultYearTotalList['LE']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 31, $planYearTotalList['LL']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 32, $resultYearTotalList['LL']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 36, $planYearTotalList['LC']);
			$objSheet->setCellValueByColumnAndRow($lastYearCol, 37, $resultYearTotalList['LC']);
		}

		// 初期化
		$enterableList = array();
		
		// 種目ごとの参加同友数を取得
		$cnt = 2;
		foreach ($itemList as $itemArray) {
		
			// LM+LS対策：LM+LS、LCは飛ばす
			if ($fiscal_year >= config::CAMPAIGN_LMLS_ADD_YEAR) {
				//if ($itemArray['value'] === 'LM+LS' || $itemArray['value'] === 'LC') {
				if ($itemArray['value'] === 'LM+LS') {
					$cnt++;
					continue;
				}
				elseif ($itemArray['value'] === 'LT') {
					$cnt++;	// LTの前にLM+LSがシートにはあるので一つ行を飛ばす
				}
			}

			if (strpos($itemArray['value'], ':sub_') !== false) {
				continue;
			}
		
			// 種目に該当する提携企業一覧を取得
			$partnerList = getPartnerList($fiscal_year, $itemArray['value']);
		
			// 優績判定処理ここから
			// LM、LSの場合はキャンペーン参加同有数をORで判定
			if ($itemArray['value'] === 'LM') {
				$list = getPartnerList($fiscal_year, 'LS');
				$partnerList = array_merge($partnerList, $list);
			}
			elseif ($itemArray['value'] === 'LS') {
				$list = getPartnerList($fiscal_year, 'LM');
				$partnerList = array_merge($partnerList, $list);
			}

			// 分母同友数を取得
			$baseEnterableNum = count(getCampaignExecutiveEnterableList($fiscal_year, '%', $campaign));
			
			// 参加率の計算の場合に限り、退会(休会)中の同友は母数のカウントには含めない
			$ignoreRecessEnterableNum = count(getCampaignExecutiveEnterableList($fiscal_year, '%', $campaign, 'recess'));
			
			// キャンペーン参加同友数を取得（期間中に販売実績がある同友数）
			$enterableNum = getCampaignMonthTotalExecutiveResultCount($fiscal_year, $partnerList, '%', $start_month, $end_month);
			if ($enterableNum > $baseEnterableNum) {
				$enterableNum = $baseEnterableNum;
			}
			
			// 各種目の本部計画値、キャンペーン参加同友数、目標同友会得点を取得
			$campaignInfo = getCampaignInfo($fiscal_year, $itemArray['value']);

			// Excelに計算用情報を出力
			$objSheet->setCellValueByColumnAndRow(11, $cnt, $itemArray['value']);
			$objSheet->setCellValueByColumnAndRow(12, $cnt, $baseEnterableNum);
			$objSheet->setCellValueByColumnAndRow(13, $cnt, $ignoreRecessEnterableNum);
			$objSheet->setCellValueByColumnAndRow(14, $cnt, $enterableNum);
			$objSheet->setCellValueByColumnAndRow(15, $cnt, $campaignInfo[$campaign.'_enterable_upper']);
			$objSheet->setCellValueByColumnAndRow(16, $cnt, $campaignInfo[$campaign.'_target']);
			$objSheet->setCellValueByColumnAndRow(17, $cnt, $campaignInfo[$campaign.'_ave']);
			$objSheet->setCellValueByColumnAndRow(18, $cnt, $campaignInfo[$campaign.'_plan']);

			// 地区対抗戦の参加同友数（実績あり）を書き出し
			if (local_config::FEATURE_AREA_REACH_AWARD) {
				
				// 種目に該当する提携企業一覧を取得
				if ($itemArray['value'] !== 'LM') {
					$partnerList = getPartnerList($fiscal_year, $itemArray['value']);
				}

				// 各地区毎のキャンペーン参加同友数を取得（期間中に販売実績がある同友数）
				$enterableEastNum = getCampaignMonthTotalExecutiveResultCount($fiscal_year, $partnerList, '東地区', $start_month, $end_month, false);
				$enterableCenterNum = getCampaignMonthTotalExecutiveResultCount($fiscal_year, $partnerList, '中央地区', $start_month, $end_month, false);
				$enterableWestNum = getCampaignMonthTotalExecutiveResultCount($fiscal_year, $partnerList, '西地区', $start_month, $end_month, false);

				$objSheet->setCellValueByColumnAndRow(11, $cnt+38, $itemArray['value']);
				$objSheet->setCellValueByColumnAndRow(13, $cnt+38, $enterableEastNum);
				$objSheet->setCellValueByColumnAndRow(14, $cnt+38, $enterableCenterNum);
				$objSheet->setCellValueByColumnAndRow(15, $cnt+38, $enterableWestNum);
			}

			$cnt++;
		}

		// 地区対抗戦用の参加同友数と、賞金額を書き出し
		if (local_config::FEATURE_AREA_REACH_AWARD) {

			// 各種目の本部計画値、キャンペーン参加同友数、目標同友会得点を取得
			$campaignInfo = getCampaignInfo($fiscal_year, 'LM');
			
			// オプションデータ分離
			$opt = $campaignInfo[$campaign.'_opt'];
			$optArray = preg_split("/-/", $opt);
			$areaItemAward = preg_split("/\,/", $optArray[0]);
			$areaTotalAward = preg_split("/\,/", $optArray[1]);
			$areaAllDoyuPrintChk = preg_split("/\,/", $optArray[2]);
			$areaMustLCCnt = preg_split("/\,/", $optArray[3]);
			$areaMustLCCntforAwar = preg_split("/\,/", $optArray[4]);

			//printArray($optArray);
			//printArray($areaItemAward);
			//printArray($areaTotalAward);
			//echo 'cnt='.$cnt;

			// オプションをExcelに書き出し
			$objSheet->setCellValueByColumnAndRow(11, 35, $areaItemAward[1]);
			$objSheet->setCellValueByColumnAndRow(12, 35, $areaItemAward[2]);
			$objSheet->setCellValueByColumnAndRow(13, 35, $areaItemAward[3]);
			$objSheet->setCellValueByColumnAndRow(14, 35, $areaTotalAward[1]);
			$objSheet->setCellValueByColumnAndRow(15, 35, $areaTotalAward[2]);
			$objSheet->setCellValueByColumnAndRow(16, 35, $areaTotalAward[3]);

			// キャンペーン中のLC獲得枚数を出力
			$objSheet->setCellValueByColumnAndRow(13, 50, $areaMustLCCnt[1]);
			$objSheet->setCellValueByColumnAndRow(13, 51, $areaMustLCCntforAwar[1]);
		
			//-------------------------------------
			// 地区対抗戦のための同友の件数を取得
			//-------------------------------------
			if ($areaAllDoyuPrintChk[1] === '1') {
				//-------------------------------------
				// 全同友を対象とする場合
				//-------------------------------------
				// 全同友数を取得(キャンペーンの分母、分母外を取得。休会は除く)
				$baseEastNum = count(getCampaignExecutiveEnterableList($fiscal_year, '東地区', $campaign, 'non_recess'));
				$baseCenterNum = count(getCampaignExecutiveEnterableList($fiscal_year, '中央地区', $campaign, 'non_recess'));
				$baseWestNum = count(getCampaignExecutiveEnterableList($fiscal_year, '西地区', $campaign, 'non_recess'));
			}
			else {
				//-------------------------------------
				// キャンペーンで分母同友に設定されている同友を対象とする場合
				//-------------------------------------
				// 参加率分母同友数を取得（退会(休会)を除いた数値）
				$baseEastNum = count(getCampaignExecutiveEnterableList($fiscal_year, '東地区', $campaign, 'recess'));
				$baseCenterNum = count(getCampaignExecutiveEnterableList($fiscal_year, '中央地区', $campaign, 'recess'));
				$baseWestNum = count(getCampaignExecutiveEnterableList($fiscal_year, '西地区', $campaign, 'recess'));
			}
			// Excelに書き出し
			$objSheet->setCellValueByColumnAndRow(13, 39, $baseEastNum);
			$objSheet->setCellValueByColumnAndRow(14, 39, $baseCenterNum);
			$objSheet->setCellValueByColumnAndRow(15, 39, $baseWestNum);
		}
	}
	
	if ($target === 'sokuho') {
		// キャンペーン速報シートの印刷範囲を指定
		$objPHPExcelCampaign->setActiveSheetIndex(1); 	// KAIINシートをアクティブにする
		$objSheet = $objPHPExcelCampaign->getActiveSheet();
		$objSheet->getPageSetup()->setPrintArea('A1:R72,T1:AI72');
		
		$objPHPExcelCampaign->setActiveSheetIndex(2); 	// LTLLLMCシートをアクティブにする
		$objSheet = $objPHPExcelCampaign->getActiveSheet();
		$objSheet->getPageSetup()->setPrintArea('A1:J71,L1:U71,W1:AH71');

		$objPHPExcelCampaign->setActiveSheetIndex(0); 	// GURAFUシートをアクティブにする
		$objSheet = $objPHPExcelCampaign->getActiveSheet();
	}

	//===================================================
	// 2つのExcelファイルを保存
	//===================================================
	if ($target === "sokuho") {
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcelCampaign, 'Excel2007');
		$objWriter->save('./'.config::TEMP_DIRECTORY_NAME.'/'.$campaignFileName);
	}
	else {
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcelExecutive, 'Excel2007');
		$objWriter->save('./'.config::TEMP_DIRECTORY_NAME.'/'.$executeFileName);
	}
	
	return $ret;
}

/**
 * ----------------------------------------------------------
 * addSheet()
 * シートを追加する
 * @param $excelObj：オブジェクト
 * @param $sheetName：シート名
 * @param $sheetIndex：シートインデックス番号
 * @return success
 * ----------------------------------------------------------
 */
function addSheet(&$excelObj, $sheetName, $sheetCnt) {

	// シートのコピー
	$firstSheet = $excelObj->getSheet(0);
	$copied = $firstSheet->copy();
	$copied->setTitle($sheetName);
	$excelObj->addSheet($copied, null);	// 第2パラメータが null(省略時含む)の場合は最後尾に追加される
	$excelObj->setActiveSheetIndex($sheetCnt+1);
	
	// アクティブシートを返す
	return $excelObj->getActiveSheet();
}
?>