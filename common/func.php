<?php
/**
 * =================================================================
 * 共通関数
 * =================================================================
 */

/**
 * ----------------------------------------------------------
 * printTextBox()
 * テキストボックスを表示する。
 * @param $name：フォーム名称
 * @param $width：フォームの幅
 * @param $textAlign:テキストボックス内のテキストの配置
 * @param $str：初期値
 * ----------------------------------------------------------
 */
function printTextBox($name, $width, $textAlign='', $str='') {
	
	if ($textAlign !== '') {
		echo '<input style="width:'.$width.'px;text-align:'.$textAlign.'" type="text" value="'.$str.'" id="'.$name.'" name="'.$name.'">';
	}
	else {
		echo '<input style="width:'.$width.'px;" type="text" value="'.$str.'" id="'.$name.'" name="'.$name.'">';
	}
}

/**
 * ----------------------------------------------------------
 * printTextAreaBox()
 * メッセージボックスを表示する。
 * @param $name：フォーム名称
 * @param $width：フォームの幅
 * @param $textAlign:テキストボックス内のテキストの配置
 * @param $str：初期値
 * ----------------------------------------------------------
 */
function printTextAreaBox($name, $width, $textAlign='', $str='') {
	
	if ($textAlign !== '') {
		echo '<textarea style="width:'.$width.'px;text-align:'.$textAlign.'" id="'.$name.'" name="'.$name.'" rows="10" cols="80">'.$str.'</textarea>';
	}
	else {
		echo '<textarea style="width:'.$width.'px;" id="'.$name.'" name="'.$name.'" rows="10" cols="80">'.$str.'</textarea>';
	}
}

/**
 * ==========================================================
 * printSelectBox()
 * ドロップダウンリストを表示する。
 * @param $list：ドロップダウンに表示するリスト
 * @param $name：ドロップダウンリストの名称
 * @param $width：フォームの幅
 * @param $selected：選択する値
 * @param $script：JacaScriptの関数名
 * ==========================================================
 */
function printSelectBox($list, $name, $width, $selected='', $script='') {
	
	// マルチバイト正規表現用のエンコーディングを設定
	mb_regex_encoding("UTF-8");
	
	echo '<select style="width:'.$width.'px;" id=\''.$name.'\' name=\''.$name.'\' onChange=\''.$script.'\'>'."\n";
	foreach ($list as $option) {
		echo '<option value="'.$option['value'].'"'."\n";
		
		// 既に設定済みの場合の値がある場合は、その値を選択
		//if (mb_ereg('^'.$option['value'].'$', $selected)) {
		if ($option['value'] === $selected || $option['value'] == $selected) {
			echo ' selected';
		}
		
		echo '>'.$option['name'].'</option>'."\n";
	}
	echo '</select>'."\n";
}

/**
 * ----------------------------------------------------------
 * printCheckBox()
 * チェックボックスを表示する。
 * @param $name：フォーム名称
 * @param $str：表示する文字列
 * @param $value：パラメータ
 * @param $checked：チェック済みかどうか
 * @param $script：スクリプト
 * ----------------------------------------------------------
 */
function printCheckBox($name, $str='', $value, $checked, $script='') {
	
	if ($checked) {
		echo '<input type="checkbox" name="'.$name.'" value="'.$value.'" onclick="'.$script.'" checked>'.$str;
	}
	else {
		echo '<input type="checkbox" name="'.$name.'" value="'.$value.'" onclick="'.$script.'">'.$str;
	}
}

/**
 * ----------------------------------------------------------
 * printRadioButton()
 * ラジオボタンを表示する。
 * @param $name：フォーム名称
 * @param $str：表示する文字列
 * @param $value：パラメータ
 * @param $checked：チェック済みかどうか
 * @param $script：スクリプト
 * ----------------------------------------------------------
 */
function printRadioButton($name, $str, $value, $checked, $script='') {
	
	if ($checked) {
		echo '<input type="radio" name="'.$name.'" value="'.$value.'" onclick="'.$script.'" checked>'.$str;
	}
	else {
		echo '<input type="radio" name="'.$name.'" value="'.$value.'" onclick="'.$script.'">'.$str;
	}
}

/**
 * ----------------------------------------------------------
 * printSubmitButton()
 * サブミットボタンを表示する。
 * @param $submit：サブミットボタンの名称
 * @param $name：ボタンの名称
 * @param $script：JacaScriptの関数名
 * ----------------------------------------------------------
 */
function printSubmitButton($submit, $name='', $script='') {

	// submitボタンを表示
	echo '<input class="submit" type="submit" value="'.$submit.'" id="'.$name.'" name="'.$name.'" onClick=\''.$script.'\'>'."\n";
}

/**
 * ----------------------------------------------------------
 * printButton()
 * ボタンを表示する。
 * @param $submit：ボタンの名称
 * @param $name：ボタンの名称
 * @param $script：JacaScriptの関数名
 * ----------------------------------------------------------
 */
function printButton($submit, $name='', $script='', $style='') {

	// submitボタンを表示
	echo '<input class="submit" style="'.$style.'" type="button" value="'.$submit.'" id="'.$name.'" name="'.$name.'" onClick=\''.$script.'\'>'."\n";
}

/**
 * ==========================================================
 * printPartnerName()
 * 指定した提携企業IDをリストから検索し提携企業の名前を出力
 * @param $list：提携企業るリスト
 * @param $selected：提携企業ID
 * ==========================================================
 */
function printPartnerName($list, $pid) {
	
	// マルチバイト正規表現用のエンコーディングを設定
	mb_regex_encoding("UTF-8");
	foreach ($list as $option) {
		if (mb_ereg('^'.$option['value'].'$', $pid)) {
			echo $option['name'];
			break;
		}
	}
}

/**
 * ----------------------------------------------------------
 * printPartnerInputTimeTable()
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $item：種目
 * @param $campaign：キャンペーン
 * ----------------------------------------------------------
 */
function printPartnerInputTimeTable($postArray, $item, $campaign='') {

	// 提携が指定されている場合は表示しない
	if ($campaign === '' && $postArray['partner'] !== 'TOTAL' && $postArray['partner'] !== 'NONE') {
		return;
	}
	
	// 種目に属する提携企業を取得
	$partnerList = getPartnerList($postArray['fiscal_year'], $item);

	// 更新時間を取得
	echo '<br /><table>';
	echo '<tr><td style="width:200px" class="bg_wet_asphalt">提携企業名</td><td style="width:200px" class="bg_wet_asphalt">更新時間</td></tr>';
	foreach ($partnerList as $partnerArray) {
		echo '<tr>';
		// 合計などの更新時間はないので飛ばす
		if ($partnerArray['value'] === 'TOTAL' || $partnerArray['value'] === 'NONE') {
			continue;
		}
		
		// 提携企業毎の更新時間を取得
		$updatetime = getUpdateTime($postArray['fiscal_year'], $item, $partnerArray['value'], $campaign);
		
		echo '<td class="left">'.$partnerArray['name'].'</td>';
		echo '<td>'.$updatetime.'</td>';
		echo '</tr>';
	}
	echo '</table>';
}

/**
 * ----------------------------------------------------------
 * startProgressMessage()
 * プログレスメッセージの開始時に呼び出す関数
 * @param $msg
 * @return YYYY
 * ----------------------------------------------------------
 */
function startProgressMessage($str) {
	echo '<div id="loading"><div id="contents"><p>'. $str .'<br />';
	echo str_pad(" ",4096)."<br />\n";
	ob_end_flush();
	ob_start('mb_output_handler');
}

function printProgressMessage($str) {
	echo $str;
	ob_flush();
	flush();
}

function endProgressMessage() {
	echo '</p></div></div>
		  <style type="text/css">
		  <!--
		  #loading { display:none; }
		  //-->
		 </style>';
}

/**
 * ----------------------------------------------------------
 * selectClassOfValue()
 * 数値の場合は右寄せ、文字列はそれ以外はセンター揃えにする
 * @param $value：判定する文字列
 * @return string HTMLテキスト
 * ----------------------------------------------------------
 */
function selectClassOfValue($value) {
	if (is_numeric($value)) {
		return "right";
	}
	else {
		return "center";
	}
}

/**
 * ----------------------------------------------------------
 * formatNumber()
 * 数値を表示するための関数
 * @param $number：判定する文字列
 * @param $decimals：桁数
 * @param $type：デフォルトはroundで四捨五入、floor：切り捨て
 * @return string HTMLテキスト
 * ----------------------------------------------------------
 */
function formatNumber($number, $decimals=0, $type='round') {

	if (!is_numeric($number) || is_nan($number)) {
		return '----';
	}

	// 負の値は0にする
	if ( $number < 0 ) {
		//return '0.0';
	}

	// ゼロの時は小数点をつける
	if ( $number == 0) {
		return '0.0';
	}
	
	if ($type === "floor") {
		// 指定した小数点より下を切り捨て
		$number = round($number - 0.5 * pow(0.1, $decimals), $decimals, PHP_ROUND_HALF_UP);
	}
	else {
		// 小数点以下を指定桁数で四捨五入
		$number = number_format($number, $decimals);
	}

	// 整数と小数に分割
	$num = explode('.', $number);

	// 小数部がない場合は、0を付与する
	if (count($num) == 1) { 
		$num[1] = 0;
	}
	
	// 整数はそのままで小数点以下は色を変える
	if ($decimals > 0) {
		$html = $num[0].'<font class="font_gray">.'.$num[1].'</font>';
	}
	else {
		$html = $num[0];
	}
	
	return $html;
}

/**
 * ----------------------------------------------------------
 * getCurrentYear()
 * 現在の年を取得する
 * @return YYYY
 * ----------------------------------------------------------
 */
function getCurrentYear() {
	
	// PHP 5.1.0以降の対策
	date_default_timezone_set('Asia/Tokyo');
	
	$dt = new DateTime();
	//$dt->setTimeZone(new DateTimeZone('Asia/Tokyo'));
	
	return $dt->format('Y');
}

/**
 * ----------------------------------------------------------
 * getCurrentFiscalYear()
 * 現在の年度を取得する
 * @return YYYY
 * ----------------------------------------------------------
 */
function getCurrentFiscalYear() {
	
	// PHP 5.1.0以降の対策
	date_default_timezone_set('Asia/Tokyo');
	
	$dt = new DateTime();
	
	// 1月～3月の場合
	if ($dt->format('m') >= 1 && $dt->format('m') <= 3) {
		return $dt->format('Y')-1;
	}
	else {	
		return $dt->format('Y');
	}
}

/**
 * ----------------------------------------------------------
 * getCurrentMonth()
 * 現在の月を取得する
 * @return mm
 * ----------------------------------------------------------
 */
function getCurrentMonth() {

	// PHP 5.1.0以降の対策
	date_default_timezone_set('Asia/Tokyo');

	$dt = new DateTime();
	//$dt->setTimeZone(new DateTimeZone('Asia/Tokyo'));
	
	return $dt->format('m');
}

/**
 * ----------------------------------------------------------
 * getMonthList()
 * 月名の一覧を取得する
 * @param
 * @return
 * ----------------------------------------------------------
 */
function getMonthList() {

	$monthInfo = array();

	for ($i = 4; $i < 13; $i++) {
		$monthInfo[] = array(
			'value' => $i,
			'name'  => $i
		);
	}

	for ($i = 1; $i < 4; $i++) {
		$monthInfo[] = array(
			'value' => $i,
			'name'  => $i
		);
	}
	
	return $monthInfo;
}

/**
 * ----------------------------------------------------------
 * getCurrentDate()
 * 現在の年月日を取得する
 * @return YYYY-mm-dd
 * ----------------------------------------------------------
 */
function getCurrentDate($separator='-') {

	// PHP 5.1.0以降の対策
	date_default_timezone_set('Asia/Tokyo');

	$dt = new DateTime();
	$dt->setTimeZone(new DateTimeZone('Asia/Tokyo'));
	
	return $dt->format('Y'.$separator.'m'.$separator.'d');
}

/**
 * ----------------------------------------------------------
 * getCurrentTime()
 * 現在日時を取得する
 * @return 
 * ----------------------------------------------------------
 */
function getCurrentTime($separator='') {

	// PHP 5.1.0以降の対策
	date_default_timezone_set('Asia/Tokyo');
	
	$dt = new DateTime();
	$dt->setTimeZone(new DateTimeZone('Asia/Tokyo'));
	
	if ($separator !== '') {
		return $dt->format('Y-m-d-H-i-s');
	}
	else {
		return $dt->format('Y-m-d H:i:s');
	}
}

/**
 * ----------------------------------------------------------
 * convertSpecialChar()
 * 配列内に含まれるHTMLの特殊文字を安全に変換する。
 * @param $baseArray
 * @return $resultArray
 * ----------------------------------------------------------
 */
function convertSpecialChar($baseArray) {

	$resultArray = array();

	foreach ($baseArray as $key => $value) {
		$key   = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
		$value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
		$resultArray += array($key => $value);
	}

	return $resultArray;
}

/**
 * ----------------------------------------------------------
 * convertDoubleByteChar()
 * 全角文字列かどうかを判定し、全角なら半角に変換する
 * @param $str：判定する文字列
 * @param $ret：半角文字列
 * ----------------------------------------------------------
 */
function convertDoubleByteChar($str) {

	$str = mb_convert_kana($str,"a","UTF-8");
	
	// カンマを一度外してみる
	$num = str_replace(",","",$str);
	
	// 数値かどうかを判定し、数値ならばカンマを外した数値を返す
	if (is_numeric($num)){
		return $num;
	}
	else {
		return $str;
	}
}

/**
 * ----------------------------------------------------------
 * checkMenuSelected()
 * パラメータで送られてきたキーとメニュータグを比較して、
 * 選択されているボタンを選択状態にする
 * @param $param：送られてきたパラメータ
 * @param $key：判定するキー
 * @return
 * ----------------------------------------------------------
 */
function checkMenuSelected($param, $key) {

	if ($param === $key) {
		echo 'selected';
	}
}

/**
 * ----------------------------------------------------------
 * checkParameterIsSet()
 * パラメータがセットされているかどうか判定し、セットされて
 * いない場合は、----をセットする
 * @param $param：判定するパラメータ
 * @return
 * ----------------------------------------------------------
 */
function checkParameterIsSet(&$param) {
	if (!isset($param)) {
		$param = '----';
	}
}

/**
 * ----------------------------------------------------------
 * checkDataOpenStatus()
 * データが公開されているかどうかチェックする
 * @param 
 * @return
 * ----------------------------------------------------------
 */
function checkDataOpenStatus($total, $count) {
	
	// 1つだけでもロックされていたらロックと判定する
	//if ($total == $count && $count > 0) {
	if ($count > 0) {
		return ' [確]';
	}
}

/**
 * ----------------------------------------------------------
 * createInputExcelFile()
 * 計画値一括入力用Excelファイルを生成
 * @param $fiscal_year：年度
 * @return
 * ----------------------------------------------------------
 */
function createInputExcelFile($fiscal_year) {

	// 月を表すセルの一覧
	$columnList  = array('E','F','G','H','I','J','K','L','M','N','O','P');
	$monthColumn = array('E1'=>'4月','F1'=>'5月','G1'=>'6月','H1'=>'7月','I1'=>'8月','J1'=>'9月','K1'=>'10月','L1'=>'11月','M1'=>'12月','N1'=>'1月','O1'=>'2月','P1'=>'3月');
	
	// 罫線設定
	$borderSetting = array(
	  'borders' => array(
	    'top'     => array('style' => PHPExcel_Style_Border::BORDER_THIN),
	    'bottom'  => array('style' => PHPExcel_Style_Border::BORDER_THIN),
	    'left'    => array('style' => PHPExcel_Style_Border::BORDER_THIN),
	    'right'   => array('style' => PHPExcel_Style_Border::BORDER_THIN)
	  )
	);

	$fileName = config::INPUT_EXCEL_FILENAME.$fiscal_year.'.xlsx';

	// 新しいエクセルファイルを作成する
	$objPHPExcel = new PHPExcel();
	
	// 0番目のシートをアクティブにする（シートは左から順に、0、1，2・・・）
	$objPHPExcel->setActiveSheetIndex(0);
	
	// アクティブにしたシートの情報を取得
	$objSheet = $objPHPExcel->getActiveSheet();
	
	//---------------------------------------------------
	// Excelの設定
	//---------------------------------------------------
	// シート名設定
	$objSheet->setTitle('計画一括入力用シート');
	
	// デフォルトのスタイル
	$objStyle = $objSheet->getDefaultStyle();	// スタイルを取得
	$objStyle->getFont()->setSize(9);			// フォントサイズを9ptにセット
	
	// 見出し部生成
	$objSheet->getCell('B1')->setValue('種目');
	$objSheet->getCell('C1')->setValue('提携企業');
	$objSheet->getCell('D1')->setValue('同友');
	foreach ($monthColumn as $columnName => $month) {
		$objSheet->setCellValue($columnName, $month);
	}
	
	// 見出しを中央揃え
	$objStyle = $objSheet->getStyle('B1:P1');
	$objStyle->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
	
	// 見出しの背景色をセット
	$objStyle = $objSheet->getStyle('B1:D1');
	$objStyle->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
	$objStyle->getFill()->getStartColor()->setRGB('A6A6A6');
	$objStyle = $objSheet->getStyle('E1:P1');	// 4月～3月
	$objStyle->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
	$objStyle->getFill()->getStartColor()->setRGB('FFFF66');
	
	// セル幅を設定
	$objSheet->getColumnDimension('A')->setWidth(0);
	$objSheet->getColumnDimension('B')->setWidth(10);
	$objSheet->getColumnDimension('C')->setWidth(25);
	$objSheet->getColumnDimension('D')->setWidth(25);
	foreach ($columnList as $column) {
		$objSheet->getColumnDimension($column)->setWidth(15);
	}
	
	//---------------------------------------------------
	// セルの中身(種目、提携、同友)を書き込み
	//---------------------------------------------------
	
	// 種目一覧を取得
	$itemList = getItemList('no-insert');
	if (count($itemList) == 0) {
		return 'no_item';
	}
	
	// エリアを取得
	$areaList = getAreaList('include-honbu');
	if (count($areaList) == 0) {
		return 'no_area';
	}
	
	// エリア毎に同友を読み込み連結
	$executiveList = array();
	foreach ($areaList as $areaArray) {
		$list = getExecutiveList(0, '', $areaArray['value'], 'all');	// リスト取得
		$executiveList = array_merge($executiveList, $list);			// 連結
	}
	if (count($executiveList) == 0) {
		return 'no_executive';
	}
	
	// 同友を同友コード順でソートする
	if (local_config::FEATURE_EXECLUTIVELIST_CODE_ORDER){
		foreach ((array) $executiveList as $key => $value) {
		    $sort[$key] = $value['code'];
		}
		array_multisort($sort, SORT_ASC, $executiveList);
	}
	
	// デバッグ
	//printArray($executiveList);
	
	$cnt = 2;
	$itemStartRow = 0;
	// 全種目でループ
	foreach ($itemList as $itemArray) {
		$objSheet->getCell('B'.$cnt)->setValue($itemArray['value']);				// 種目をセット
		$itemStartRow = $cnt;
		
		// 提携企業を取得
		$partnerList = getPartnerList($fiscal_year, $itemArray['value']);
		
		// 提携企業毎に同友企業をセット
		foreach ($partnerList as $partnerArray) {
		
			if (in_array($itemArray['value'], local_config::PLAN_ITEM_COMBINE)) {
				$objSheet->getCell('C'.$cnt)->setValue('提携企業計画合計値');		// 結合版は「合計値」と表示
			}
			else {
				$objSheet->getCell('C'.$cnt)->setValue($partnerArray['name']);		// 提携企業をセット
			}
			
			// 同友を表示
			foreach ($executiveList as $executiveArray) {
				$objSheet->getCell('D'.$cnt)->setValue($executiveArray['name']);	// 同友をセット
				//echo $executiveArray['name'].'<br />';
				
				// 入力用のカテゴリ値
				$objSheet->getCell('A'.$cnt)->setValue($itemArray['value'].':'.$partnerArray['value'].':'.$executiveArray['value']);
				
				// 4月～3月までの計画値を初期化
				foreach ($columnList as $column) {
					$objSheet->getCell($column.$cnt)->setValue(0);
				}
				$cnt++;
			}
			
			// 結合版は1つの提携分しか表示しない
			if (in_array($itemArray['value'], local_config::PLAN_ITEM_COMBINE)) {
				break;	
			}
		}
		
		// 種目ごとの合計のセルを表示
		$objSheet->mergeCells('B'.$cnt.':D'.$cnt);
		$objSheet->getCell('B'.$cnt)->setValue($itemArray['value'].'合計');
		$objStyle = $objSheet->getStyle('B'.$cnt.':P'.$cnt);
		$objStyle->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
		$objStyle->getFill()->getStartColor()->setRGB('92D050');
		$objStyle->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
		
		// 合計を計算する数式を挿入
		// 4月～3月までの計画値を初期化
		$itemEndRow = $cnt - 1;
		foreach ($columnList as $column) {
			$objSheet->getCell($column.$cnt)->setValue('=SUM('.$column.$itemStartRow.':'.$column.$itemEndRow.')');
		}
		
		// 罫線を引く
		$objStyle = $objSheet->getStyle('B'.$cnt.':P'.$cnt);
		$objStyle->applyFromArray($borderSetting);
		
		// 入力用のカテゴリ値
		$objSheet->getCell('A'.$cnt)->setValue('PASS');
		
		$cnt++;
	}

	// 種目、提携、同友のセルは中央揃え
	$objStyle = $objSheet->getStyle('B2:D'.$cnt);
	$objStyle->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
	
	// 他のセルは右揃え
	$objStyle = $objSheet->getStyle('E2:P'.$cnt);
	$objStyle->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
	
	// 罫線を引く
	$borderRowNum = $cnt - 1;
	$objStyle = $objSheet->getStyle('B1'.':P1');
	$objStyle->applyFromArray($borderSetting);
	$objStyle = $objSheet->getStyle('B1'.':D'.$borderRowNum);
	$objStyle->applyFromArray($borderSetting);
	$objStyle = $objSheet->getStyle('B1'.':P'.$borderRowNum);
	$objStyle->applyFromArray($borderSetting);
	
	// 最終ラインマーク
	$objSheet->getCell('A'.$cnt)->setValue('END');
	
	// "Excel2007" 形式で保存する
	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('./'.config::TEMP_DIRECTORY_NAME.'/'.$fileName);
	
	return 'success';
}

/**
 * ----------------------------------------------------------
 * inputAllMonthPlan()
 * 一括入力用Excelファイルを読み込みデータベースに登録
 * @param $fiscal_year：年度
 * @return
 * ----------------------------------------------------------
 */
function inputAllMonthPlan($fiscal_year) {

	//echo 'upload_filename=' , $_FILES["input_file"]["name"].'<br />';

	// ファイル名チェック
	if (strlen( $_FILES["input_file"]["name"]) == 0) {
		return 'no_input_file';
	}

	// アップロード先パスの生成
	// 時間からファイル名を生成
	// ※コロンはファイル名に使えないので削除、その他の記号も削除して数値だけにする
	$time = getCurrentTime();
	$time = str_replace(' ', '', $time);
	$time = str_replace(':', '', $time);
	$time = str_replace('-', '', $time);
	$filepath = $_SERVER["DOCUMENT_ROOT"].dirname($_SERVER["SCRIPT_NAME"]).'/'.config::TEMP_DIRECTORY_NAME.'/'.$time.'.xlsx';
	
	//echo 'upload_filepath='.$filepath.'<br />';
	
	// ファイルのアップロード
	if ( $_FILES["input_file"]["size"] == 0 ) {
		return 'error_filesize';
	}
	else {
		// アップロードファイルされたテンポラリファイルをファイル格納パスにコピー
		$ret = @move_uploaded_file( $_FILES["input_file"]["tmp_name"], $filepath);
		if ( $ret !== true ) {
			return 'system_error';
		}
	}
	
	// アップロードされた計画ファイルをデータベースに反映
	$ret = writeAllMonthPlan($fiscal_year, $filepath);
	if ( $ret !== 'success' ) {
		return 'input_file_error';
	}
	
	// アップロードされたファイルを削除
	unlink($filepath);

	return 'success';
}

/**
 * ----------------------------------------------------------
 * printJudgeReachColor()
 * 達成率の判定し、色を取得する
 * @param $fiscal_year：年度
 * @return
 * ----------------------------------------------------------
 */
function printJudgeReachColor($result, $diff) {

	$quotient = $result / ($result + $diff);
	$color = '';

	if ($quotient >= config::REACH_PATTERN_4) {
		$color = "bg_skyblue font_red bold";
	}
	else if ($quotient >= config::REACH_PATTERN_3) {
		$color = "bg_skyblue";
	}
	else if ($quotient >= config::REACH_PATTERN_2) {
		$color = "bg_green";
	}
	else if ($quotient >= config::REACH_PATTERN_1) {
		$color = "bg_orange";
	}
	else {
		$color = "bg_red";
	}

	return $color;
}

/**
 * ----------------------------------------------------------
 * printReachPattern()
 * 達成率のカラーパターン表示用
 * @return
 * ----------------------------------------------------------
 */
function printReachPattern() {
	$position_1 = config::REACH_PATTERN_1 * 100;
	$position_2 = config::REACH_PATTERN_2 * 100;
	$position_3 = config::REACH_PATTERN_3 * 100;

	echo '<p>
	<font color="#FFB6B6">■</font>:達成率'.$position_1.'未満&nbsp;
	<font color="#FFB774">■</font>:達成率'.$position_1.'%～&nbsp;
	<font color="#A9FFA1">■</font>:達成率'.$position_2.'%～&nbsp;
	<font color="#94FBFF">■</font>:達成率'.$position_3.'%～
	</p>';
}

/**
 * ----------------------------------------------------------
 * updateTimestampFile()
 * 指定したファイルのタイムスタンプを更新する
 * @return
 * ----------------------------------------------------------
 */
function updateTimestampFile($filepath) {

	touch($filepath);
}

/**
 * ----------------------------------------------------------
 * getUpdateTimestampFile()
 * 指定したファイルの更新時間を取得する
 * @return
 * ----------------------------------------------------------
 */
function getUpdateTimestampFile($filepath) {

	$updateDate = 0;

    if (file_exists($filepath)) {    //ファイル存在チェック
        //タイムスタンプ取得
		$updateDate = filemtime($filepath);
    }
    return $updateDate;
}


/**
 * ----------------------------------------------------------
 * PHP5.4からでないと対応していないUnicodeアンエスケープをPHP5.3でもできるようにしたラッパー関数
 * @param mixed   $value
 * @param int     $options
 * @param boolean $unescapee_unicode
 * ----------------------------------------------------------
 */
function json_xencode($value, $options = 0, $unescapee_unicode = true)
{
	//$v = json_encode($value, $options);
	$v = json_encode($value);

	if ($unescapee_unicode) {
		$v = unicode_encode($v);
		// スラッシュのエスケープをアンエスケープする
		$v = preg_replace('/\\\\\//', '/', $v);
	}
	
	return $v;
}

/**
 * ----------------------------------------------------------
 * Unicodeエスケープされた文字列をUTF-8文字列に戻す。
 * 参考:http://d.hatena.ne.jp/iizukaw/20090422
 * @param unknown_type $str
 * ----------------------------------------------------------
 */
function unicode_encode($str)
{
	return preg_replace_callback("/\\\\u([0-9a-zA-Z]{4})/", "encode_callback", $str);
}

function encode_callback($matches) {
	return mb_convert_encoding(pack("H*", $matches[1]), "UTF-8", "UTF-16");
}

/**
 * ----------------------------------------------------------
 * DBGMSG
 * デバッグメッセージ表示用
 * @param msg：文字列
 * ----------------------------------------------------------
 */
function DBGMSG($msg) {
	echo 'DBGMSG:'.$msg.'<br />';
}

/**
 * ----------------------------------------------------------
 * printArray
 * 配列の表示用デバッグ関数
 * @param list：配列
 * ----------------------------------------------------------
 */
function printArray($list) {
	echo "<pre>";
	print_r($list);
	echo "</pre>";
}
/**
 * =================================================================
 *  Copyright(c)2013 iSKET All Rights Reserved.
 * =================================================================
 */
?>