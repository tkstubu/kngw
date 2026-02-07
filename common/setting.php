<?php
/**
 * =================================================================
 * 設定情報アクセス用関数
 * =================================================================
 */

//--------------------------------
// include
//--------------------------------
require_once("local_config.php");
require_once("config.php");
require_once("mysql.php");

/**
 * ----------------------------------------------------------
 * printSystemName()
 * システム名を表示する
 * @param
 * @return
 * ----------------------------------------------------------
 */
function printSystemName() {

	// データベース接続
	$db = new DB(config::DB_NAME);

	// SQL文生成
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_SETTING .'
			WHERE name LIKE "system_name"
			';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$title = $data['value1'];
	}
	
	// データベースクローズ
	$db->close();
	
	// 結果表示
	if (strlen($title) > 0) {
		echo $title;
	}
	else {
		echo 'タイトル未設定';
	}
}

/**
 * ----------------------------------------------------------
 * getSettingInfo()
 * システム情報を取得する
 * @param
 * @return $settingInfo：設定情報
 * ----------------------------------------------------------
 */
function getSettingInfo() {

	$settingInfo = array();

	// データベース接続
	$db = new DB(config::DB_NAME);

	// SQL文生成
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_SETTING .'
			';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);
	
	// データ取得
	while ($data = $db->getData()) {
		$settingInfo[$data['name']] = array(
			'id'       => $data['id'],
			'name'     => $data['name'],
			'value1'   => $data['value1'],
			'value2'   => $data['value2']
		);
	}
	
	// データベースクローズ
	$db->close();
	
	return $settingInfo;
}

/**
 * ----------------------------------------------------------
 * setSettingInfo()
 * システム情報を保存する
 * @param : $postArray：設定情報
 * @return ：success 成功 failed 失敗
 * ----------------------------------------------------------
 */
function setSettingInfo($postArray) {
	
	$ret = 'success';
	
	unset($postArray['save']);
	
	// データチェック
	$itemCnt = 0;
	$areaCnt = 0;
	foreach ($postArray as $key => $value) {
		$keyArray = explode(':', $key);

		// 種目は最低1つ以上入力が必要
		if (strpos($keyArray[0], 'item') !== false) {
			if (strlen($value) > 0) {
				$itemCnt++;
			}
		}
		
		// 地域は最低1つ以上入力が必要
		if (strpos($keyArray[0], 'area') !== false) {
			if (strlen($value) > 0) {
				$areaCnt++;
			}
		}
	}
	if ($itemCnt == 0) {
		return 'no_item';	// 種目が未設定
	}
	if ($areaCnt == 0) {
		return 'no_area';	// 地域が未設定
	}
	
	// データベースに接続
	$db = new DB(config::DB_NAME);

	// データベースに保存
	foreach ($postArray as $key => $value) {
	
		$name = '';
		$value1 = '';
		$value2 = '';
	
		$keyArray = explode(':', $key);
		
		// 要素に応じて分解
		if (count($keyArray) == 1) {
			$name = 'system_name';
			$value1 = $value;
		}
		else {
			if ($keyArray[1] === 'value1') {
				$name = $keyArray[0];
				$value1 = $postArray[$keyArray[0].':value1'];
				$value2 = $postArray[$keyArray[0].':value2'];
			}
			else {
				continue;
			}
		}

		// SQL生成
		// 設定値のテーブルはあらかじめ用意しているので、UPDATEで対応可能
		$sql = sprintf("UPDATE %s	SET		value1  = '%s',
											value2  = '%s'
									WHERE	name	= '%s'
									",
									local_config::$DB_TABLE_PREFIX.config::DB_TABLE_SETTING,
									$db->escapeString($value1),
									$db->escapeString($value2),
									$db->escapeString($name)
									);
	
		// SQL実行
		//echo $sql.'<br />';
		$ret = $db->exec($sql);
		
	}
	
	// データベースクローズ
	$db->close();

	// 戻り値
	if ($ret === 'failed') {
		return $ret;
	}
	else {
		return 'success';
	}
}

?>