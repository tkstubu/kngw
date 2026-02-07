<?php
/**
 * =================================================================
 * readDB.php
 * データベース読み込み用関数
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
 * getUserInfo()
 * ユーザIDに設定されているユーザ情報を取得
 * @param userID 情報を取得するユーザID
 * @return array $userInfo：ユーザ情報
 * ----------------------------------------------------------
 */
function getUserInfo($userID, $id=0) {

	$userInfo = array();

	// ログイン履歴を残さないモード用
	if (strpos($userID, "_nolog") !== false) {
		$userIDArray = preg_split("/_/", $userID);
		$userID = $userIDArray[0];
		$_SESSION["NOLOG"] = true;
	}
	else {
		$_SESSION["NOLOG"] = false;
	}

	// データベース接続
	$db = new DB(config::DB_NAME);

	// SQL文生成
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .'
			WHERE user LIKE "'.$userID.'"
			';
	
	// idの指定がある場合
	if ($id > 0) {
		$sql = $sql. ' AND id = ' . $id;
	}

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);
	
	// データ取得
	while ($data = $db->getData()) {
		$userInfo = array(
			'id'        => $data['id'],
			'name'      => htmlspecialchars_decode($data['name'], ENT_QUOTES),
			'kana'      => htmlspecialchars_decode($data['kana'], ENT_QUOTES),
			'user'      => $data['user'],
			'password'  => $data['password'],
			'auth'      => $data['auth'],
			'item'      => $data['item'],
			'code'      => $data['code'],
			'area'      => $data['area'],
			'enterable' => $data['enterable']
		);
	}
	
	// データベースクローズ
	$db->close();
	
	// ユーザ情報通知
	if (count($userInfo) > 0) {
		return $userInfo;
	}
	else {
		return '';
	}
}

/**
 * ----------------------------------------------------------
 * getItemList()
 * 種目一覧を取得
 * @param $command 取得用コマンド
 * @param $item 取得する種目名
 * @param $fiscal_year 年度（種目の切り替えのため）
 * @param $lmls_opt true LM+LSの表示 false 非表示
 * @param $lc_opt true LCを削除 false 削除しない
 * @param $leb_opt true LEBを削除 false 削除しない
 * @return array $itemList 種目一覧
 * ----------------------------------------------------------
 */
function getItemList($command='', $item='', $fiscal_year=2014, $lmls_opt=false, $lc_opt=false, $leb_opt=false) {

	$itemList = array();

	// データベース接続
	$db = new DB(config::DB_NAME);

	// SQL文生成
	// GROUP BYで同じレコードをまとめる
	$sql = 'SELECT name, value1, value2
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_SETTING .'
			WHERE name like "item%"
			AND   value1 not like ""
			';
	
	// itemが指定されている場合は指定種目のみ情報を取得
	if ($item !== '') {
		$sql = $sql. ' AND value1 like "'.$item.'"';
	}

	$sql .= ' ORDER BY item_order ASC';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// コマンド別の処理
	if ($command === '') {
		$itemList[] = array('value'=>'ALL',  'name'=>'全種目', 'unit'=>'');
	}
	elseif ($command === 'non-select') {
		$itemList[] = array('value'=>'NONE',  'name'=>'----', 'unit'=>'');
	}
	elseif ($command === 'no-insert') {
	}
	
	// データ取得
	while ($data = $db->getData()) {
		$itemList[] = array(
			'value' => $data['value1'],
			'name'  => $data['value1'],
			'unit'  => $data['value2']
		);
	}

	// デバッグ用
	//var_dump($itemList);
	
	// データベースクローズ
	$db->close();

	//-------------------------------------------
	// LM+LS対策：
	if ($fiscal_year >= config::CAMPAIGN_LMLS_ADD_YEAR) {
		// LC削除
		if ($lc_opt) {
			$i = 0;
			foreach ($itemList as $itemArray) {
				if ($itemArray['name'] === 'LC') {
					array_splice($itemList, $i, 1);	// LCを削除
				}
				$i++;
			}
		}

		// LEB削除
		if ($leb_opt) {
			$i = 0;
			foreach ($itemList as $itemArray) {
				if ($itemArray['name'] === 'LEB') {
					array_splice($itemList, $i, 1);	// LEBを削除
				}
				$i++;
			}
		}
		
		// LM+LSの対応($optionがtrueなら場所替え、falseなら削除)
		$i = 0;
		$ls_position = 0;
		foreach ($itemList as $itemArray) {
			// LSの位置を取得
			if ($itemArray['name'] === 'LS') {
				$ls_position = $i+1;
			}
			
			// LM+LSを見つけたらLSの次に挿入して、見つけた位置のものは削除
			if ($itemArray['name'] !== 'LM+LS') {
				$i++;
				continue;
			}
			else {
				unset($itemList[$i]);	// LM+LSを削除
				
				if ($lmls_opt) {
					array_splice($itemList, $ls_position, 0, 1);
					$itemList[$ls_position] = $itemArray;
				}
			}
		}
	}
	else {
		$i = 0;
		foreach ($itemList as $itemArray) {
			if ($itemArray['name'] === 'LM+LS') {
				unset($itemList[$i]);	// LM+LSを削除
				break;
			}
			$i++;
		}
	}
	//-------------------------------------------
	
	// 配列を詰め直して連番にする
	$itemList = array_merge($itemList);

	return $itemList;
}

/**
 * ----------------------------------------------------------
 * getSubItemList()
 * 補助種目一覧を取得
 * @param $command 取得用コマンド
 * @param $subitem 取得する種目名
 * @return array $itemList 種目一覧
 * ----------------------------------------------------------
 */
function getSubItemList($command='', $subitem='') {

	$itemList = array();

	// データベース接続
	$db = new DB(config::DB_NAME);

	// SQL文生成
	// GROUP BYで同じレコードをまとめる
	$sql = 'SELECT name, value1, value2
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_SETTING .'
			WHERE (name like "item%" OR name like "sub%")
			AND   value1 not like ""
			';
	
	// subitemが指定されている場合は指定種目のみ情報を取得
	if ($subitem !== '') {
		$sql = $sql. ' AND value1 like "'.$subitem.'"';
	}

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// コマンド別の処理
	if ($command === '') {
		$itemList[] = array('value'=>'ALL',  'name'=>'全種目', 'unit'=>'');
	}
	elseif ($command === 'non-select') {
		$itemList[] = array('value'=>'NONE',  'name'=>'----', 'unit'=>'');
	}
	elseif ($command === 'no-insert') {
	}
	
	// データ取得
	$mainItemArray = array();
	while ($data = $db->getData()) {
		
		$subItemArray = array();
		
		// 補助種目の場合はvalueの値をメイン種目と関連付けておく
		if (strpos($data['name'], 'sub') !== false) {
			$subItemArray = explode('_', $data['name']);
			$value = $mainItemArray[$subItemArray[1]-1].':'.$data['name'];		// 種目_Sの形にする
		}
		else {
			$mainItemArray[] = $data['value1'];
			continue;
		}
	
		$itemList[] = array(
			'value' => $value,
			'name'  => $data['value1'],
			'unit'  => $data['value2'],
			'mainitem' => $mainItemArray[$subItemArray[1]-1]	// メイン種目
		);
	}

	// デバッグ用
	//var_dump($itemList);
	
	// データベースクローズ
	$db->close();

	// 配列を詰め直して連番にする
	$itemList = array_merge($itemList);
	
	return $itemList;
}

/**
 * ----------------------------------------------------------
 * getSpecialItemList()
 * 特別な種目一覧を取得（LC保有枚数など）
 * @param $command 取得用コマンド
 * @param $subitem 取得する種目名
 * @return array $itemList 種目一覧
 * ----------------------------------------------------------
 */
function getSpecialItemList() {

	$itemList = array();

	// データベース接続
	$db = new DB(config::DB_NAME);

	// SQL文生成
	// GROUP BYで同じレコードをまとめる
	$sql = 'SELECT name, value1, value2
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_SETTING .'
			WHERE name like "%:spitem"
			AND   value1 not like ""
			';
	
	$db->exec($sql);
	
	// データ取得
	while ($data = $db->getData()) {
		$itemList[] = array(
			'value' => $data['name'],
			'name'  => $data['value1'],
			'unit'  => $data['value2']
		);
	}

	// デバッグ用
	//var_dump($itemList);
	
	// データベースクローズ
	$db->close();

	// 配列を詰め直して連番にする
	$itemList = array_merge($itemList);
	
	return $itemList;
}

/**
 * ----------------------------------------------------------
 * convertItemName()
 * 補助種目のvalueから名称を取得
 * @param $subitem 取得する種目名
 * @return array $name
 * ----------------------------------------------------------
 */
function convertItemName($item) {

	$name = '';
	$search = '';
	
	// 補助種目、特別種目の時だけ変換する
	if (strpos($item, ':sub_') !== false) {
		// 補助種目を分解
		$subItemArray = explode(':', $item);
		$search = $subItemArray[1];
	}
	else if (strpos($item, ':spitem') !== false) {
		$search = $item;
	}
	else { 
		return $item;
	}
	
	// データベース接続
	$db = new DB(config::DB_NAME);
	
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_SETTING .'
			WHERE name like "'.$search.'"';
	
	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$name = $data['value1'];
	}

	// データベースクローズ
	$db->close();

	return $name;
}

/**
 * ----------------------------------------------------------
 * getAreaList()
 * 地区一覧を取得
 * @param $command：操作コマンド(include-honbu：本部を取得)
 * @return array $areaList：地区一覧
 * ----------------------------------------------------------
 */
function getAreaList($command='') {

	$areaList = array();

	// データベース接続
	$db = new DB(config::DB_NAME);

	// SQL文生成
	// GROUP BYで同じレコードをまとめる
	$sql = 'SELECT name, value1
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_SETTING .'
			WHERE name like "area%"
			AND   value1 not like ""';
	
	// コマンド判定
	if ($command === 'include-honbu') {
		$sql .= ' AND value1 LIKE "%"';			// 本部を含めたすべての地域
	}
	else {
		$sql .= ' AND value1 NOT LIKE "'.config::HEADOFFICE_NAME.'"';	// デフォルトの設定では本部を除く
	}
	
	$sql .= ' ORDER BY item_order ASC';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$areaList[] = array(
			'value' => $data['value1'],
			'name'  => htmlspecialchars_decode($data['value1'], ENT_QUOTES)
		);
	}

	// デバッグ用
	//var_dump($areaList);
	
	// データベースクローズ
	$db->close();
	
	return $areaList;
}

/**
 * ----------------------------------------------------------
 * getPartnerList()
 * 指定年度の提携企業一覧を取得
 * 全種目を跨いで包含した場合のユニークな提携企業を取得する
 * @param $fiscal_year 年度
 * @param $item 種目名
 * @param $pid 提携企業ID（セットしたIDだけを取得、adminは全て取得）
 * @return array $partnerList 提携企業一覧
 * ----------------------------------------------------------
 */
function getPartnerList($fiscal_year=0, $item='', $pid=1) {

	$partnerList = array();

	// 参照テーブルの設定
	$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA;
	
	// itemの指定がない場合は全ての種目
	if (strlen($item) == 0 || $item === 'ALL') {
		$item = '%';
		$auth = config::USER_PARTNER;
	}

	// 協力企業の一覧を取得（脱退していない）
	if (strpos($item, 'cooperation') !== false) {
		$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_COOPERATION;	// 参照テーブルの変更
		$item = '-';
		$auth = config::USER_COOPERATION;
	}

	// itemが補助種目の場合
	if (strpos($item, ':sub_') !== false) {
	
		$subItemArray = explode(':', $item);			// メイン種目の取り出し
		$subItemNo = explode('_', $subItemArray[1]);	// 補助種目が何番目のものかを取り出し(例：sub_1_2の場合、1がメイン種目との関連付け、2がテーブルNo)
		
		// 参照テーブルの変更
		$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SUBITEM.$subItemNo[2];
		
		// 種目の設定
		$item = $subItemArray[0];
	}

	// itemが特別種目の場合
	if (strpos($item, ':spitem') !== false) {
		$itemArray = explode(':', $item);	// 種目の取り出し
		$item = $itemArray[0];				// 種目の設定
		$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SPITEM;	// 参照テーブルの変更
	}

	// データベース接続
	$db = new DB(config::DB_NAME);

	// 既存のデータに存在する提携企業一覧を取得
	if ($fiscal_year != 0) {

		// SQL文生成
		// GROUP BYで同じレコードをまとめる
		$sql = 'SELECT u.id			AS id,
					   u.name		AS name,
					   u.user		AS user,
					   u.item		AS item
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u,'
				      . $dataTableName .' d
				WHERE  d.partnerID = u.id
				AND    (u.item like "'.$item.'")
	            AND    d.fiscal_year='.$fiscal_year;
		
		// admin以外が指定された場合はそのIDの提携企業の情報のみ取得
		if ($pid > 1) {
			$sql = $sql.' AND d.partnerID = '.$pid;
		}
		
		$sql = $sql.' GROUP BY d.partnerID';
		//$sql = $sql.' ORDER BY d.partnerID ASC';
		$sql = $sql.' ORDER BY kana ASC';
	}
	// ユーザ一覧から脱退していない提携企業一覧を取得
	else {
		$sql = 'SELECT id,
					   name,
					   item,
					   user
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER.'
				WHERE  YEAR(exitdate) = 0
				AND    auth = '.$auth.'
				ORDER BY kana ASC';
	}
	
	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$partnerList[] = array(
			'value'       => $data['id'],
			'name'        => htmlspecialchars_decode($data['name'], ENT_QUOTES),
			'user'        => $data['user'],
			'item'        => $data['item']
		);
	}

	// デバッグ用
	//var_dump($partnerList);
	
	// データベースクローズ
	$db->close();
	
	return $partnerList;
}

/**
 * ----------------------------------------------------------
 * getExecutiveList()
 * 指定年度の同友会社一覧を取得
 * 全種目を跨いで包含した場合のユニークな同友会社を取得する
 * @param $fiscal_year 年度
 * @param $campaign キャンペーンの場合
 * @param $area 同友のエリア
 * @param $auth 同友種別（all：すべて、campaign:年度のキャンペーンの生産性のカウントに含まれるもの、entry：指定した年度までに脱退していない同友）
 * @return array $executiveList 同友会社一覧
 * ----------------------------------------------------------
 */
function getExecutiveList($fiscal_year=0, $campaign='', $area='%', $auth='') {

	$executiveList = array();

	// データベース接続
	$db = new DB(config::DB_NAME);

	//WHERE  YEAR(exitdate) = 0 OR  (YEAR(exitdate) >= "'.$fiscal_year.'" AND exitdate <= "'.$fiscal_year.'-03-31")

	if ($fiscal_year != 0 && $auth === 'campaign' ) {	// 生産性用
		$next_fiscal_year = $fiscal_year + 1;	// 年度末で脱退した同友を含めるようにするため
		$sql = 'SELECT id,
					   name,
					   user,
					   area,
					   code,
					   auth,
					   enterable,
					   enterdate,
					   exitdate
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER.'
				WHERE  (YEAR(exitdate) = 0 OR  (YEAR(exitdate) >= "'.$fiscal_year.'" AND exitdate <= "'.$next_fiscal_year.'-03-31"))
				AND    (auth = '.config::USER_EXECUTIVE.' OR auth = '.config::USER_ADJUSTMENT.')
				AND    area like "'.$area.'"
				ORDER BY code ASC
				';
	}
	elseif ($fiscal_year != 0 && $auth === 'entry' ) {	// キャンペーンデータ作成用
		// $sql = 'SELECT id,
		// 			   name,
		// 			   user,
		// 			   area,
		// 			   code,
		// 			   auth,
		// 			   enterable,
		// 			   enterdate,
		// 			   exitdate
		// 		FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER.'
		// 		WHERE  (YEAR(exitdate) = 0 OR  (YEAR(exitdate) >= "'.$fiscal_year.'" AND exitdate <= "'.$fiscal_year.'-12-31"))
		// 		AND    YEAR(enterdate) <= "'.$fiscal_year.'"
		// 		AND    (auth = '.config::USER_EXECUTIVE.' OR auth = '.config::USER_ADJUSTMENT.')
		// 		AND    area like "'.$area.'"
		// 		ORDER BY code ASC
		// 		';
		
		// AND    YEAR(enterdate) <= "'.$fiscal_year.'" を削除している。
		// 例：2020年1月～3月に登録した同友は、2019年度の再構成ではリストに表示されなくなってしまうため。
		// 2019年度の計画は2019年1月の段階で存在している同友で作るのだが、2020年1月～3月に入ってきた同友は春キャンに参加するということか？
		$sql = 'SELECT id,
					   name,
					   user,
					   area,
					   code,
					   auth,
					   enterable,
					   enterdate,
					   exitdate
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER.'
				WHERE  (YEAR(exitdate) = 0 OR  (YEAR(exitdate) >= "'.$fiscal_year.'" AND exitdate <= "'.$fiscal_year.'-12-31"))
				AND    (auth = '.config::USER_EXECUTIVE.' OR auth = '.config::USER_ADJUSTMENT.')
				AND    area like "'.$area.'"
				ORDER BY code ASC
				';
	}
	else {
		// 既存のデータに存在する同友会社一覧を取得
		if ($fiscal_year != 0) {
			// SQL文生成
			// GROUP BYで同じレコードをまとめる
			$sql = 'SELECT u.id		AS id,
						u.name		AS name,
						u.user		AS user,
						u.area		AS area,
						u.code		AS code,
						u.auth		AS auth,
						u.enterable	AS enterable,
						u.enterdate	AS enterdate,
						u.exitdate	AS exitdate';
			
					if ($campaign === 'campaign') {
						$sql = $sql.'
						,
						MIN(d.summer_enterable) AS summer_enterable,
						MIN(d.autumn_enterable) AS autumn_enterable,
						MIN(d.spring_enterable) AS spring_enterable';
					}
					
					$sql = $sql.' FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u,';
					
					// キャンペーンと通常でテーブルを変更
					if ($campaign === 'campaign') {
						$sql = $sql.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA .' d';
					}
					else {
						$sql = $sql.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA .' d';
					}
					
					$sql = $sql.'
					WHERE  d.executiveID = u.id
					AND    area like "'.$area.'"
					AND    d.fiscal_year = '.$fiscal_year;
					
					// 全てでない場合は同友のみで調整項目は取得しない
					if ($auth !== 'all' ) {
						$sql = $sql.'
						AND u.auth = '.config::USER_EXECUTIVE;
					}
					
					$sql = $sql.'
					GROUP BY d.executiveID
					ORDER BY code ASC
					';
		}
		// ユーザ一覧から脱退していない同友会社一覧を取得
		else {
			// 調整、本部も含めて全て取得
			if ($auth === 'all' ) {
				$sql = 'SELECT id,
							name,
							user,
							area,
							code,
							auth,
							enterable,
							enterdate,
							exitdate
						FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER.'
						WHERE  YEAR(exitdate) = 0
						AND    (auth = '.config::USER_EXECUTIVE.' OR auth = '.config::USER_ADJUSTMENT.' OR auth = '.config::USER_HEADOFFICE.')
						AND    area like "'.$area.'"
						ORDER BY code ASC
						';
			}
			// 同友のみ取得
			else
			{
				$sql = 'SELECT id,
							name,
							user,
							area,
							code,
							auth,
							enterable,
							enterdate,
							exitdate
						FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER.'
						WHERE  YEAR(exitdate) = 0
						AND    auth = '.config::USER_EXECUTIVE.'
						AND    area like "'.$area.'"
						ORDER BY code ASC
						';
			}
		}
	}

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$executiveList[] = array(
			'value'       => $data['id'],
			'name'        => htmlspecialchars_decode($data['name'], ENT_QUOTES),
			'user'        => $data['user'],
			'area'        => $data['area'],
			'code'        => $data['code'],
			'auth'        => $data['auth'],
			'enterable'   => $data['enterable'],
			'enterdate'   => $data['enterdate'],
			'exitdate'    => $data['exitdate'],
			'summer_enterable' => $data['summer_enterable'],
			'autumn_enterable' => $data['autumn_enterable'],
			'spring_enterable' => $data['spring_enterable']
		);
	}

	// デバッグ用
	//var_dump($executiveList);
	
	// データベースクローズ
	$db->close();
	
	return $executiveList;
}

/**
 * ----------------------------------------------------------
 * getExeOfficerList()
 * 同友役員一覧を取得
 * @return array $ExeOfficerList 同友役員一覧
 * ----------------------------------------------------------
 */
function getExeOfficerList() {
	
	$ExeOfficerList = array();

	// データベース接続
	$db = new DB(config::DB_NAME);

	// 既存のデータに存在する同友役員一覧を取得
	$sql = 'SELECT id,
					name,
					user,
					area,
					code,
					enterable,
					enterdate,
					exitdate
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER.'
			WHERE  YEAR(exitdate) = 0
			AND    auth = '.config::USER_EXEOFFICER.'
			ORDER BY code ASC
			';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$ExeOfficerList[] = array(
			'value'       => $data['id'],
			'name'        => htmlspecialchars_decode($data['name'], ENT_QUOTES),
			'user'        => $data['user'],
			'area'        => $data['area'],
			'code'        => $data['code'],
			'enterable'   => $data['enterable'],
			'enterdate'   => $data['enterdate'],
			'exitdate'    => $data['exitdate'],
			'summer_enterable' => $data['summer_enterable'],
			'autumn_enterable' => $data['autumn_enterable'],
			'spring_enterable' => $data['spring_enterable']
		);
	}

	// デバッグ用
	//var_dump($ExeOfficerList);
	
	// データベースクローズ
	$db->close();
	
	return $ExeOfficerList;
}

/**
 * ----------------------------------------------------------
 * getAdjustmentList()
 * 調整項目一覧を取得
 * @return array $adjustmentList 調整項目一覧
 * ----------------------------------------------------------
 */
function getAdjustmentList() {

	$adjustmentList = array();

	// データベース接続
	$db = new DB(config::DB_NAME);

	// 既存のデータに存在する同友会社一覧を取得
	$sql = 'SELECT id,
				   name,
				   user,
				   area,
				   code,
				   enterable,
				   enterdate,
				   exitdate
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER.'
			WHERE  YEAR(exitdate) = 0
			AND    auth = '.config::USER_ADJUSTMENT.'
			ORDER BY code ASC
			';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$adjustmentList[] = array(
			'value'       => $data['id'],
			'name'        => htmlspecialchars_decode($data['name'], ENT_QUOTES),
			'user'        => $data['user'],
			'area'        => $data['area'],
			'code'        => $data['code'],
			'enterable'   => $data['enterable'],
			'enterdate'   => $data['enterdate'],
			'exitdate'    => $data['exitdate'],
			'summer_enterable' => $data['summer_enterable'],
			'autumn_enterable' => $data['autumn_enterable'],
			'spring_enterable' => $data['spring_enterable']
		);
	}

	// デバッグ用
	//var_dump($adjustmentList);
	
	// データベースクローズ
	$db->close();
	
	return $adjustmentList;
}


/**
 * ----------------------------------------------------------
 * getCooperationList()
 * 協力企業一覧を取得
 * @return array $CooperationList 協力企業一覧
 * ----------------------------------------------------------
 */
function getCooperationList() {
	
	$CooperationList = array();

	// データベース接続
	$db = new DB(config::DB_NAME);

	// 既存のデータに存在する同友役員一覧を取得
	$sql = 'SELECT id,
					name,
					user,
					area,
					code,
					enterable,
					enterdate,
					exitdate
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER.'
			WHERE  YEAR(exitdate) = 0
			AND    auth = '.config::USER_COOPERATION.'
			ORDER BY code ASC
			';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$CooperationList[] = array(
			'value'       => $data['id'],
			'name'        => htmlspecialchars_decode($data['name'], ENT_QUOTES),
			'user'        => $data['user'],
			'area'        => $data['area'],
			'code'        => $data['code'],
			'enterable'   => $data['enterable'],
			'enterdate'   => $data['enterdate'],
			'exitdate'    => $data['exitdate'],
			'summer_enterable' => $data['summer_enterable'],
			'autumn_enterable' => $data['autumn_enterable'],
			'spring_enterable' => $data['spring_enterable']
		);
	}

	// デバッグ用
	//var_dump($CooperationList);
	
	// データベースクローズ
	$db->close();
	
	return $CooperationList;
}

/**
 * ----------------------------------------------------------
 * getResultDataInfo()
 * 指定年度、同友会社ID、提携企業IDが一致するデータを取得
 * @param $fiscal_year 年度
 * @param $eid 同友会社ID
 * @param $pid 提携企業ID
 * @param $subitem 種目
 * @return array $dataArray データ
 * ----------------------------------------------------------
 */
function getResultDataInfo($fiscal_year, $eid, $pid, $subitem='') {

	$dataArray = array();

	// データベース接続
	$db = new DB(config::DB_NAME);

	if ($subitem === '') {
		// SQL文生成
		$sql = 'SELECT *
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA .'
				WHERE fiscal_year = '.$fiscal_year.'
				AND   executiveID = '.$eid.'
				AND   partnerID   = '.$pid.'
				';
	}
	else if (strpos($subitem, 'cooperation') !== false) {
		// 協力企業の場合のSQL文生成
		$sql = 'SELECT *
		FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_COOPERATION .'
		WHERE fiscal_year = '.$fiscal_year.'
		AND   executiveID = '.$eid.'
		AND   partnerID   = '.$pid.'
		';
	}
	else if (strpos($subitem, ':sub_') !== false) {
	
		// 補助種目が何番目のものかを取り出し
		// 例：sub_1_2の場合、1がメイン種目との関連付け、2がテーブルNo
		$subItemNo = explode('_', $subitem);
		
		// 参照テーブルの変更
		$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SUBITEM.$subItemNo[2];
	
		// SQL文生成
		$sql = 'SELECT *
				FROM '.$dataTableName .'
				WHERE fiscal_year = '.$fiscal_year.'
				AND   executiveID = '.$eid.'
				AND   partnerID   = '.$pid.'
				';
	}
	else {
			// 特殊種目の場合のSQL文生成
			$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SPITEM .'
			WHERE fiscal_year = '.$fiscal_year.'
			AND   executiveID = '.$eid.'
			AND   partnerID   = '.$pid.'
			';
	}

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);
	
	// データ取得
	while ($data = $db->getData()) {
		$dataArray = array(
			'fiscal_year' => $data['fiscal_year'],
			'executiveID' => $data['executiveID'],
			'partnerID'   => $data['partnerID']
		);
	}
	
	// データベースクローズ
	$db->close();
	
	// ユーザ情報通知
	return $dataArray;
}

/**
 * ----------------------------------------------------------
 * getDataLockInfo()
 * 指定年度、種目、提携企業IDを持つデータベースの月全体の締め状態を取得
 * @param $fiscal_year 年度
 * @param $item 種目
 * @param $pid 提携企業ID
 * @return boolean 0:アンロック 1:ロック
 * ----------------------------------------------------------
 */
function getDataLockInfo($fiscal_year, $item, $pid) {
	
	$openInfo = array();
	
	// データベース接続
	$db = new DB(config::DB_NAME);
	
	// SQL文生成
	$sql = 'SELECT COUNT(*) AS total,
				   COUNT(4_lock = 1 or NULL)  AS 4_lock,
				   COUNT(5_lock = 1 or NULL)  AS 5_lock,
				   COUNT(6_lock = 1 or NULL)  AS 6_lock,
				   COUNT(7_lock = 1 or NULL)  AS 7_lock,
				   COUNT(8_lock = 1 or NULL)  AS 8_lock,
				   COUNT(9_lock = 1 or NULL)  AS 9_lock,
				   COUNT(10_lock = 1 or NULL) AS 10_lock,
				   COUNT(11_lock = 1 or NULL) AS 11_lock,
				   COUNT(12_lock = 1 or NULL) AS 12_lock,
				   COUNT(1_lock = 1 or NULL)  AS 1_lock,
				   COUNT(2_lock = 1 or NULL)  AS 2_lock,
				   COUNT(3_lock = 1 or NULL)  AS 3_lock
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u,'
			      .local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA .' d
			WHERE (d.partnerID = u.id AND u.item like "'.$item.'")
            AND    d.fiscal_year='.$fiscal_year;

	// pidが指定されている場合
	if ($pid !== 'NONE' && $pid !== 'TOTAL') {
		$sql = $sql. ' AND d.partnerID ='.$pid;
	}

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);
	
	// データ取得
	while ($data = $db->getData()) {
		$openInfo = array(
			'total'   => $data['total'],
			'4_lock'  => $data['4_lock'],
			'5_lock'  => $data['5_lock'],
			'6_lock'  => $data['6_lock'],
			'7_lock'  => $data['7_lock'],
			'8_lock'  => $data['8_lock'],
			'9_lock'  => $data['9_lock'],
			'10_lock' => $data['10_lock'],
			'11_lock' => $data['11_lock'],
			'12_lock' => $data['12_lock'],
			'1_lock'  => $data['1_lock'],
			'2_lock'  => $data['2_lock'],
			'3_lock'  => $data['3_lock']
		);
	}
	
	// データベースクローズ
	$db->close();

	return $openInfo;
}

/**
 * ----------------------------------------------------------
 * getLockStatus()
 * 指定年度、月、提携企業IDを持つデータベースの締め情報を取得
 * @param $fiscal_year 年度
 * @param $month 月
 * @param $pid 提携企業ID
 * @return boolean 0:アンロック 1:ロック
 * ----------------------------------------------------------
 */
function getLockStatus($fiscal_year, $month=0, $pid=0) {

	$lockStatus = 0;

	// データベース接続
	$db = new DB(config::DB_NAME);

	// SQL文生成
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA .'
			WHERE fiscal_year = '.$fiscal_year.'
			';
	
	// pid
	if ($pid > 0) {
		$sql = $sql . ' AND partnerID = '.$pid;
	}

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);
	
	$columnStr = $month.'_lock';
	
	// データ取得
	while ($data = $db->getData()) {
		//echo $data[$columnStr];
		$lockStatus = $data[$columnStr];
		if ($lockStatus == 1) {
			break;
		}
	}
	
	// データベースクローズ
	$db->close();
	
	//echo 'lockStatus='.$lockStatus.'<br />';
	
	return $lockStatus;
}

/**
 * ----------------------------------------------------------
 * getFiscalYearList()
 * 計画作成済み年度一覧を取得
 * @param $dataType 年間/キャンペーンを区別
 * @return array $fiscalYearList：計画作成済み年度一覧
 * ----------------------------------------------------------
 */
function getFiscalYearList($dataType="") {

	$fiscalYearList = array();

	// データベース接続
	$db = new DB(config::DB_NAME);
	
	// データ種別判定
	if ($dataType === 'campaign') {
		// SQL文生成
		// GROUP BYで同じレコードをまとめる
		$sql = 'SELECT fiscal_year
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA .'
				GROUP BY fiscal_year
				ORDER BY fiscal_year DESC
				';
	}
	else {
		// SQL文生成
		// GROUP BYで同じレコードをまとめる
		$sql = 'SELECT fiscal_year
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA .'
				GROUP BY fiscal_year
				ORDER BY fiscal_year DESC
				';
	}

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$fiscalYearList[] = array(
			'value' => $data['fiscal_year'],
			'name'  => $data['fiscal_year']
		);
	}

	// デバッグ用
	//var_dump($fiscalYearList);
	
	// データベースクローズ
	$db->close();
	
	return $fiscalYearList;
}

/**
 * ----------------------------------------------------------
 * getOpenFiscalYearList()
 * 計画公開済み年度一覧を取得
 * @return array $openYearList：計画公開済み年度一覧
 * ----------------------------------------------------------
 */
function getOpenFiscalYearList() {

	$openYearList = array();
	
	// データベース接続
	$db = new DB(config::DB_NAME);

	// SQL文生成
	// GROUP BYで同じレコードをまとめる
	$sql = 'SELECT fiscal_year
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA .'
			WHERE open = '.config::STATUS_DATA_OPEN.'
			GROUP BY fiscal_year
			ORDER BY fiscal_year DESC
			';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$openYearList[] = array(
			'value' => $data['fiscal_year'],
			'name'  => $data['fiscal_year']
		);
	}

	// デバッグ用
	//var_dump($openYearList);
	
	// データベースクローズ
	$db->close();
	
	return $openYearList;
}

/**
 * ----------------------------------------------------------
 * getUpdateTime()
 * 年度、種目、提携企業IDを指定した場合の最新の更新時間を取得
 * @param $year 年度
 * @param $item 種目名
 * @param $pid 提携企業ID
 * @param $campaign キャンペーン種別
 * @return array $update_time：更新日
 * ----------------------------------------------------------
 */
function getUpdateTime($year, $item='%', $pid='NONE', $campaign='') {
	
	$update_time = '';
	
	// キャンペーン指定時のカラム名の生成
	if ($campaign !== '') {
		$campaign = $campaign.'_';
	}

	// 参照テーブルの設定
	$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA;

	// itemが補助種目の場合
	if (strpos($item, ':sub_') !== false) {
	
		$subItemArray = explode(':', $item);			// メイン種目の取り出し
		$subItemNo = explode('_', $subItemArray[1]);	// 補助種目が何番目のものかを取り出し(例：sub_1_2の場合、1がメイン種目との関連付け、2がテーブルNo)
		
		// 参照テーブルの変更
		$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SUBITEM.$subItemNo[2];
		
		// 種目の設定
		$item = $subItemArray[0];
	}

	// itemが特別種目の場合
	if (strpos($item, ':spitem') !== false) {
		$itemArray = explode(':', $item);	// 種目の取り出し
		$item = $itemArray[0];				// 種目の設定
		$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SPITEM;	// 参照テーブルの変更
	}

	// データベース接続
	$db = new DB(config::DB_NAME);
	
	// SQL文生成
	$sql = 'SELECT MAX(d.'.$campaign.'update_time) AS update_time
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u,'
			      . $dataTableName .' d
			WHERE (d.partnerID = u.id and u.item like "'.$item.'")
            AND    d.fiscal_year='.$year;
	
	// pidが指定されている場合
	if ($pid !== 'NONE' && $pid !== 'TOTAL') {
		$sql = $sql. ' AND d.partnerID ='.$pid;
	}

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// 更新日時取り出し
	while ($data = $db->getData()) {
		$update_time = $data['update_time'];
	}
	
	// データベースクローズ
	$db->close();

	return $update_time;
}

/**
 * ----------------------------------------------------------
 * getAreaTotalValue()
 * 指定年度の種目、地域毎の計画、実績の合計値を取得
 * @param $pid 提携企業ID
 * @param $item 種目名
 * @param $area 地区名
 * @param $year 年度
 * @param $enterable true 分母同友のみ false 全体
 * @return array $totalValueList：合計値
 * ----------------------------------------------------------
 */
function getAreaTotalValue($pid, $item, $area, $year, $enterable=false) {

	$totalValueList = array();
	$cooperationFlag = false;

	// 参照テーブルの設定
	$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA;

	// itemが補助種目の場合
	if (strpos($item, ':sub_') !== false) {
	
		$subItemArray = explode(':', $item);			// メイン種目の取り出し
		$subItemNo = explode('_', $subItemArray[1]);	// 補助種目が何番目のものかを取り出し(例：sub_1_2の場合、1がメイン種目との関連付け、2がテーブルNo)
		
		// 参照テーブルの変更
		$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SUBITEM.$subItemNo[2];
		
		// 種目の設定
		$item = $subItemArray[0];
	}

	// itemが特別種目の場合
	if (strpos($item, ':spitem') !== false) {
		$itemArray = explode(':', $item);	// 種目の取り出し
		$item = $itemArray[0];				// 種目の設定
		$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SPITEM;	// 参照テーブルの変更
	}

	// itemが協力企業(cooperation)の場合
	if (strpos($item, 'cooperation') !== false) {
		$cooperationFlag = true;
		$item = '-';	// 種目を - に設定（協力企業なので種目が存在しないため）
		$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_COOPERATION;	// 参照テーブルの変更
	}

	// データベース接続
	$db = new DB(config::DB_NAME);

	// キャンペーン参加同友のみの場合(1 キャンペーン参加、0 不参加)
	if (!$enterable && $cooperationFlag == false) {
	
		// SQL文生成(全同友)
		$sql = 'SELECT u1.item        AS item,
		               IFNULL(SUM(4_plan),0)    AS 4_plan,
					   IFNULL(SUM(4_result),0)  AS 4_result,
					   IFNULL(SUM(5_plan),0)    AS 5_plan,
					   IFNULL(SUM(5_result),0)  AS 5_result,
					   IFNULL(SUM(6_plan),0)    AS 6_plan,
					   IFNULL(SUM(6_result),0)  AS 6_result,
					   IFNULL(SUM(7_plan),0)    AS 7_plan,
					   IFNULL(SUM(7_result),0)  AS 7_result,
					   IFNULL(SUM(8_plan),0)    AS 8_plan,
					   IFNULL(SUM(8_result),0)  AS 8_result,
					   IFNULL(SUM(9_plan),0)    AS 9_plan,
					   IFNULL(SUM(9_result),0)  AS 9_result,
					   IFNULL(SUM(10_plan),0)   AS 10_plan,
					   IFNULL(SUM(10_result),0) AS 10_result,
					   IFNULL(SUM(11_plan),0)   AS 11_plan,
					   IFNULL(SUM(11_result),0) AS 11_result,
					   IFNULL(SUM(12_plan),0)   AS 12_plan,
					   IFNULL(SUM(12_result),0) AS 12_result,
					   IFNULL(SUM(1_plan),0)    AS 1_plan,
					   IFNULL(SUM(1_result),0)  AS 1_result,
					   IFNULL(SUM(2_plan),0)    AS 2_plan,
					   IFNULL(SUM(2_result),0)  AS 2_result,
					   IFNULL(SUM(3_plan),0)    AS 3_plan,
					   IFNULL(SUM(3_result),0)  AS 3_result
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u1,'
				      .local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u2,'
				      . $dataTableName .' d
				WHERE (d.partnerID = u1.id and u1.item like "'.$item.'")
				AND   (d.executiveID=u2.id and u2.area like "'.$area.'")
	            AND    d.fiscal_year='.$year;
	}
	// 協力企業の場合
	else if ($cooperationFlag) {
		// SQL文生成(全同友)
		$sql = 'SELECT u1.item        AS item,
		               IFNULL(SUM(4_plan),0)    AS 4_plan,
					   IFNULL(SUM(4_result),0)  AS 4_result,
					   IFNULL(SUM(4_shibu),0)   AS 4_shibu,
					   IFNULL(SUM(5_plan),0)    AS 5_plan,
					   IFNULL(SUM(5_result),0)  AS 5_result,
					   IFNULL(SUM(5_shibu),0)   AS 5_shibu,
					   IFNULL(SUM(6_plan),0)    AS 6_plan,
					   IFNULL(SUM(6_result),0)  AS 6_result,
					   IFNULL(SUM(6_shibu),0)   AS 6_shibu,
					   IFNULL(SUM(7_plan),0)    AS 7_plan,
					   IFNULL(SUM(7_result),0)  AS 7_result,
					   IFNULL(SUM(7_shibu),0)   AS 7_shibu,
					   IFNULL(SUM(8_plan),0)    AS 8_plan,
					   IFNULL(SUM(8_result),0)  AS 8_result,
					   IFNULL(SUM(8_shibu),0)   AS 8_shibu,
					   IFNULL(SUM(9_plan),0)    AS 9_plan,
					   IFNULL(SUM(9_result),0)  AS 9_result,
					   IFNULL(SUM(9_shibu),0)   AS 9_shibu,
					   IFNULL(SUM(10_plan),0)   AS 10_plan,
					   IFNULL(SUM(10_result),0) AS 10_result,
					   IFNULL(SUM(10_shibu),0)  AS 10_shibu,
					   IFNULL(SUM(11_plan),0)   AS 11_plan,
					   IFNULL(SUM(11_result),0) AS 11_result,
					   IFNULL(SUM(11_shibu),0)  AS 11_shibu,
					   IFNULL(SUM(12_plan),0)   AS 12_plan,
					   IFNULL(SUM(12_result),0) AS 12_result,
					   IFNULL(SUM(12_shibu),0)  AS 12_shibu,
					   IFNULL(SUM(1_plan),0)    AS 1_plan,
					   IFNULL(SUM(1_result),0)  AS 1_result,
					   IFNULL(SUM(1_shibu),0)   AS 1_shibu,
					   IFNULL(SUM(2_plan),0)    AS 2_plan,
					   IFNULL(SUM(2_result),0)  AS 2_result,
					   IFNULL(SUM(2_shibu),0)   AS 2_shibu,
					   IFNULL(SUM(3_plan),0)    AS 3_plan,
					   IFNULL(SUM(3_result),0)  AS 3_result,
					   IFNULL(SUM(3_shibu),0)   AS 3_shibu
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u1,'
				      .local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u2,'
				      . $dataTableName .' d
				WHERE (d.partnerID = u1.id and u1.item like "'.$item.'")
				AND   (d.executiveID=u2.id and u2.area like "'.$area.'")
	            AND    d.fiscal_year='.$year;
	}
	else {
		
		// キャンペーンデータ存在の有無をチェック
		$exitCampaignData = getCampaignInfo($year);
		if (count($exitCampaignData) > 0) {
		
		// キャンペーンデータが有るときは分母判定はキャンペーンのデータで行う
		// SQL文生成(計画値の集計は分母外の値を除外する)
		$sql = 'SELECT u1.item        AS item,
		               IFNULL(SUM(case when (c.summer_enterable!=0 or c.autumn_enterable!=0 or c.spring_enterable!=0) then d.4_plan else 0 end),0)  AS 4_plan,
					   IFNULL(SUM(d.4_result),0)  AS 4_result,                                                    
					   IFNULL(SUM(case when (c.summer_enterable!=0 or c.autumn_enterable!=0 or c.spring_enterable!=0) then d.5_plan else 0 end),0)  AS 5_plan,
					   IFNULL(SUM(d.5_result),0)  AS 5_result,                                                    
					   IFNULL(SUM(case when (c.summer_enterable!=0 or c.autumn_enterable!=0 or c.spring_enterable!=0) then d.6_plan else 0 end),0)  AS 6_plan,
					   IFNULL(SUM(d.6_result),0)  AS 6_result,                                                    
					   IFNULL(SUM(case when (c.summer_enterable!=0 or c.autumn_enterable!=0 or c.spring_enterable!=0) then d.7_plan else 0 end),0)  AS 7_plan,
					   IFNULL(SUM(d.7_result),0)  AS 7_result,                                                    
					   IFNULL(SUM(case when (c.summer_enterable!=0 or c.autumn_enterable!=0 or c.spring_enterable!=0) then d.8_plan else 0 end),0)  AS 8_plan,
					   IFNULL(SUM(d.8_result),0)  AS 8_result,                                                    
					   IFNULL(SUM(case when (c.summer_enterable!=0 or c.autumn_enterable!=0 or c.spring_enterable!=0) then d.9_plan else 0 end),0)  AS 9_plan,
					   IFNULL(SUM(d.9_result),0)  AS 9_result,                                                    
					   IFNULL(SUM(case when (c.summer_enterable!=0 or c.autumn_enterable!=0 or c.spring_enterable!=0) then d.10_plan else 0 end),0) AS 10_plan,
					   IFNULL(SUM(d.10_result),0) AS 10_result,                                                   
					   IFNULL(SUM(case when (c.summer_enterable!=0 or c.autumn_enterable!=0 or c.spring_enterable!=0) then d.11_plan else 0 end),0) AS 11_plan,
					   IFNULL(SUM(d.11_result),0) AS 11_result,                                                   
					   IFNULL(SUM(case when (c.summer_enterable!=0 or c.autumn_enterable!=0 or c.spring_enterable!=0) then d.12_plan else 0 end),0) AS 12_plan,
					   IFNULL(SUM(d.12_result),0) AS 12_result,                                                   
					   IFNULL(SUM(case when (c.summer_enterable!=0 or c.autumn_enterable!=0 or c.spring_enterable!=0) then d.1_plan else 0 end),0)  AS 1_plan,
					   IFNULL(SUM(d.1_result),0)  AS 1_result,                                                    
					   IFNULL(SUM(case when (c.summer_enterable!=0 or c.autumn_enterable!=0 or c.spring_enterable!=0) then d.2_plan else 0 end),0)  AS 2_plan,
					   IFNULL(SUM(d.2_result),0)  AS 2_result,                                                    
					   IFNULL(SUM(case when (c.summer_enterable!=0 or c.autumn_enterable!=0 or c.spring_enterable!=0) then d.3_plan else 0 end),0)  AS 3_plan,
					   IFNULL(SUM(d.3_result),0)  AS 3_result
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u1,'
				      .local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u2,'
					  .local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA .' c,'
				      . $dataTableName .' d
				WHERE (d.partnerID = u1.id and u1.item like "'.$item.'")
				AND   (d.executiveID = u2.id and u2.area like "'.$area.'")
				AND   (d.executiveID = c.executiveID and d.partnerID = c.partnerID)
				AND    d.fiscal_year='.$year.'
				AND    c.fiscal_year='.$year;
		}
		else {
		// キャンペーンデータがまだ生成されていない時はユーザ管理の情報で判定する
		// SQL文生成(計画値の集計は分母外の値を除外する)
		$sql = 'SELECT u1.item        AS item,
		               IFNULL(SUM(case when (u2.enterable!=0) then d.4_plan else 0 end),0)  AS 4_plan,
					   IFNULL(SUM(d.4_result),0)  AS 4_result,
					   IFNULL(SUM(case when (u2.enterable!=0) then d.5_plan else 0 end),0)  AS 5_plan,
					   IFNULL(SUM(d.5_result),0)  AS 5_result,                                                    
					   IFNULL(SUM(case when (u2.enterable!=0) then d.6_plan else 0 end),0)  AS 6_plan,
					   IFNULL(SUM(d.6_result),0)  AS 6_result,                                                    
					   IFNULL(SUM(case when (u2.enterable!=0) then d.7_plan else 0 end),0)  AS 7_plan,
					   IFNULL(SUM(d.7_result),0)  AS 7_result,                                                    
					   IFNULL(SUM(case when (u2.enterable!=0) then d.8_plan else 0 end),0)  AS 8_plan,
					   IFNULL(SUM(d.8_result),0)  AS 8_result,                                                    
					   IFNULL(SUM(case when (u2.enterable!=0) then d.9_plan else 0 end),0)  AS 9_plan,
					   IFNULL(SUM(d.9_result),0)  AS 9_result,                                                    
					   IFNULL(SUM(case when (u2.enterable!=0) then d.10_plan else 0 end),0) AS 10_plan,
					   IFNULL(SUM(d.10_result),0) AS 10_result,                                                   
					   IFNULL(SUM(case when (u2.enterable!=0) then d.11_plan else 0 end),0) AS 11_plan,
					   IFNULL(SUM(d.11_result),0) AS 11_result,                                                   
					   IFNULL(SUM(case when (u2.enterable!=0) then d.12_plan else 0 end),0) AS 12_plan,
					   IFNULL(SUM(d.12_result),0) AS 12_result,                                                   
					   IFNULL(SUM(case when (u2.enterable!=0) then d.1_plan else 0 end),0)  AS 1_plan,
					   IFNULL(SUM(d.1_result),0)  AS 1_result,                                                    
					   IFNULL(SUM(case when (u2.enterable!=0) then d.2_plan else 0 end),0)  AS 2_plan,
					   IFNULL(SUM(d.2_result),0)  AS 2_result,                                                    
					   IFNULL(SUM(case when (u2.enterable!=0) then d.3_plan else 0 end),0)  AS 3_plan,
					   IFNULL(SUM(d.3_result),0)  AS 3_result
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u1,'
				      .local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u2,'
				      . $dataTableName .' d
				WHERE (d.partnerID = u1.id and u1.item like "'.$item.'")
				AND   (d.executiveID=u2.id and u2.area like "'.$area.'")
	            AND    d.fiscal_year='.$year;
		}
	}
	
	// pidが指定されている場合
	if ($pid !== 'NONE' && $pid !== 'TOTAL') {
		$sql = $sql. ' AND (d.partnerID ='.$pid.')';
	}

	// エリアが全体になっている場合は本部のデータは加算しないようにする
	if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
		if ($area === '%') {
			$sql = $sql. ' AND ( u2.auth != '.config::USER_HEADOFFICE.')';
		}
	}

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	//-------------------------------
	// 通常
	//-------------------------------
	if ($cooperationFlag == false) {
		while ($data = $db->getData()) {
			$totalValueList[] = array(
				'item'      => $data['item'],
				'4_plan'    => $data['4_plan'],
				'4_result'  => $data['4_result'],
				'5_plan'    => $data['5_plan'],
				'5_result'  => $data['5_result'],
				'6_plan'    => $data['6_plan'],
				'6_result'  => $data['6_result'],
				'7_plan'    => $data['7_plan'],
				'7_result'  => $data['7_result'],
				'8_plan'    => $data['8_plan'],
				'8_result'  => $data['8_result'],
				'9_plan'    => $data['9_plan'],
				'9_result'  => $data['9_result'],
				'10_plan'   => $data['10_plan'],
				'10_result' => $data['10_result'],
				'11_plan'   => $data['11_plan'],
				'11_result' => $data['11_result'],
				'12_plan'   => $data['12_plan'],
				'12_result' => $data['12_result'],
				'1_plan'    => $data['1_plan'],
				'1_result'  => $data['1_result'],
				'2_plan'    => $data['2_plan'],
				'2_result'  => $data['2_result'],
				'3_plan'    => $data['3_plan'],
				'3_result'  => $data['3_result']
			);
		}
	}
	//-------------------------------
	// 協力企業の場合
	//-------------------------------
	else {
		while ($data = $db->getData()) {
			$totalValueList[] = array(
				'item'      => $data['item'],
				'4_plan'    => $data['4_plan'],
				'4_result'  => $data['4_result'],
				'4_shibu'   => $data['4_shibu'],
				'5_plan'    => $data['5_plan'],
				'5_result'  => $data['5_result'],
				'5_shibu'   => $data['5_shibu'],
				'6_plan'    => $data['6_plan'],
				'6_result'  => $data['6_result'],
				'6_shibu'   => $data['6_shibu'],
				'7_plan'    => $data['7_plan'],
				'7_result'  => $data['7_result'],
				'7_shibu'   => $data['7_shibu'],
				'8_plan'    => $data['8_plan'],
				'8_result'  => $data['8_result'],
				'8_shibu'   => $data['8_shibu'],
				'9_plan'    => $data['9_plan'],
				'9_result'  => $data['9_result'],
				'9_shibu'   => $data['9_shibu'],
				'10_plan'   => $data['10_plan'],
				'10_result' => $data['10_result'],
				'10_shibu'  => $data['10_shibu'],
				'11_plan'   => $data['11_plan'],
				'11_result' => $data['11_result'],
				'11_shibu'  => $data['11_shibu'],
				'12_plan'   => $data['12_plan'],
				'12_result' => $data['12_result'],
				'12_shibu'  => $data['12_shibu'],
				'1_plan'    => $data['1_plan'],
				'1_result'  => $data['1_result'],
				'1_shibu'   => $data['1_shibu'],
				'2_plan'    => $data['2_plan'],
				'2_result'  => $data['2_result'],
				'2_shibu'   => $data['2_shibu'],
				'3_plan'    => $data['3_plan'],
				'3_result'  => $data['3_result'],
				'3_shibu'   => $data['3_shibu']
			);
		}		
	}

	// デバッグ用
	//printArray($totalValueList);
	
	// データベースクローズ
	$db->close();
	
	return $totalValueList;
}

/**
 * ----------------------------------------------------------
 * getExecutiveResultTotalValue()
 * 各同友会社の指定年度の月毎の計画値、実績値の合計値を取得
 * @param $year 年度
 * @param $item 種目名
 * @param $area 地区名
 * @param $pid 提携企業ID
 * @param $eid 同友ID
 * @return array $executiveValueList：合計値
 * ----------------------------------------------------------
 */
function getExecutiveResultTotalValue($year, $item, $area, $pid, $eid=0) {

	$executiveValueList = array();

	// 参照テーブルの設定
	$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA;

	// itemが補助種目の場合
	if (strpos($item, 'sub_') !== false) {
		$subItemArray = explode(':', $item);			// メイン種目の取り出し
		$subItemNo = explode('_', $subItemArray[1]);	// 補助種目が何番目のものかを取り出し(例：sub_1_2の場合、1がメイン種目との関連付け、2がテーブルNo)
		
		// 参照テーブルの変更
		$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SUBITEM.$subItemNo[2];
		
		// 種目の設定
		$item = $subItemArray[0];
	}

	// itemが特別種目の場合
	if (strpos($item, ':spitem') !== false) {
		$itemArray = explode(':', $item);	// 種目の取り出し
		$item = $itemArray[0];				// 種目の設定
		$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SPITEM;	// 参照テーブルの変更
	}

	// データベース接続
	$db = new DB(config::DB_NAME);

	// SQL文生成
	// GROUP BYで同じレコードをまとめる
	$sql = 'SELECT u2.id          AS eid,
				   u2.user        AS user,
				   u2.name        AS name,
	               IFNULL(SUM(4_plan),0)    AS 4_plan,
				   IFNULL(SUM(4_result),0)  AS 4_result,
				   IFNULL(SUM(5_plan),0)    AS 5_plan,
				   IFNULL(SUM(5_result),0)  AS 5_result,
				   IFNULL(SUM(6_plan),0)    AS 6_plan,
				   IFNULL(SUM(6_result),0)  AS 6_result,
				   IFNULL(SUM(7_plan),0)    AS 7_plan,
				   IFNULL(SUM(7_result),0)  AS 7_result,
				   IFNULL(SUM(8_plan),0)    AS 8_plan,
				   IFNULL(SUM(8_result),0)  AS 8_result,
				   IFNULL(SUM(9_plan),0)    AS 9_plan,
				   IFNULL(SUM(9_result),0)  AS 9_result,
				   IFNULL(SUM(10_plan),0)   AS 10_plan,
				   IFNULL(SUM(10_result),0) AS 10_result,
				   IFNULL(SUM(11_plan),0)   AS 11_plan,
				   IFNULL(SUM(11_result),0) AS 11_result,
				   IFNULL(SUM(12_plan),0)   AS 12_plan,
				   IFNULL(SUM(12_result),0) AS 12_result,
				   IFNULL(SUM(1_plan),0)    AS 1_plan,
				   IFNULL(SUM(1_result),0)  AS 1_result,
				   IFNULL(SUM(2_plan),0)    AS 2_plan,
				   IFNULL(SUM(2_result),0)  AS 2_result,
				   IFNULL(SUM(3_plan),0)    AS 3_plan,
				   IFNULL(SUM(3_result),0)  AS 3_result
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u1,'
			      .local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u2,'
			      .$dataTableName .' d
			WHERE (d.partnerID = u1.id and u1.item like "'.$item.'")
			AND   (d.executiveID=u2.id and u2.area like "'.$area.'")
            AND    d.fiscal_year='.$year;

	// pidが指定されている場合
	if ($pid !== 'NONE' && $pid !== 'TOTAL') {
		$sql = $sql. ' AND d.partnerID ='.$pid;
	}

	// eidが指定されている場合
	if ($eid > 0) {
		$sql = $sql. ' AND d.executiveID ='.$eid;
	}
	
	// 同友会社IDでまとめる
	$sql = $sql.' GROUP BY d.executiveID ORDER BY u2.code ASC';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$executiveValueList[] = array(
			'eid'       => $data['eid'],
			'user'      => $data['user'],
			'name'      => htmlspecialchars_decode($data['name'], ENT_QUOTES),
			'4_plan'    => $data['4_plan'],
			'4_result'  => $data['4_result'],
			'5_plan'    => $data['5_plan'],
			'5_result'  => $data['5_result'],
			'6_plan'    => $data['6_plan'],
			'6_result'  => $data['6_result'],
			'7_plan'    => $data['7_plan'],
			'7_result'  => $data['7_result'],
			'8_plan'    => $data['8_plan'],
			'8_result'  => $data['8_result'],
			'9_plan'    => $data['9_plan'],
			'9_result'  => $data['9_result'],
			'10_plan'   => $data['10_plan'],
			'10_result' => $data['10_result'],
			'11_plan'   => $data['11_plan'],
			'11_result' => $data['11_result'],
			'12_plan'   => $data['12_plan'],
			'12_result' => $data['12_result'],
			'1_plan'    => $data['1_plan'],
			'1_result'  => $data['1_result'],
			'2_plan'    => $data['2_plan'],
			'2_result'  => $data['2_result'],
			'3_plan'    => $data['3_plan'],
			'3_result'  => $data['3_result'],
		);
	}

	// デバッグ用
	//var_dump($executiveValueList);
	
	// データベースクローズ
	$db->close();
	
	return $executiveValueList;
}


/**
 * ----------------------------------------------------------
 * getExecutiveResultValue()
 * 各同友会社の指定した年度、月、提携企業毎の計画値、実績を取得
 * @param $pid 提携企業ID
 * @param $area 地区名
 * @param $year 年度
 * @param $item 種目(補助種目の場合のみ利用)
 * @return array $executiveValueList：合計値
 * ----------------------------------------------------------
 */
function getExecutiveResultValue($pid, $area, $year, $item='') {

	$executiveValueList = array();

	// 参照テーブルの設定
	$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA;
	
	// itemが補助種目の場合
	if (strpos($item, ':sub_') !== false) {
	
		$subItemNo = explode('_', $item);	// 補助種目が何番目のものかを取り出し(例：sub_1_2の場合、1がメイン種目との関連付け、2がテーブルNo)
		
		// 参照テーブルの変更
		$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SUBITEM.$subItemNo[2];
	}

	// itemが特別種目の場合
	if (strpos($item, ':spitem') !== false) {
		$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SPITEM;	// 参照テーブルの変更
	}

	// itemが協力企業（cooperation）の場合
	if (strpos($item, 'cooperation') !== false) {
		$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_COOPERATION;	// 参照テーブルを協力企業用に変更
	}


	// データベース接続
	$db = new DB(config::DB_NAME);

	// SQL文生成
	// GROUP BYで同じレコードをまとめる
	//-------------------------------
	// 通常
	//-------------------------------
	if (strpos($item, 'cooperation') === false) {
		$sql = 'SELECT u2.id     AS eid,
				   u2.code   AS code,
				   u2.name   AS name,
				   u1.id     AS pid,
	               IFNULL(SUM(4_plan),0)    AS 4_plan,
				   IFNULL(SUM(4_result),0)  AS 4_result,
				   IFNULL(SUM(5_plan),0)    AS 5_plan,
				   IFNULL(SUM(5_result),0)  AS 5_result,
				   IFNULL(SUM(6_plan),0)    AS 6_plan,
				   IFNULL(SUM(6_result),0)  AS 6_result,
				   IFNULL(SUM(7_plan),0)    AS 7_plan,
				   IFNULL(SUM(7_result),0)  AS 7_result,
				   IFNULL(SUM(8_plan),0)    AS 8_plan,
				   IFNULL(SUM(8_result),0)  AS 8_result,
				   IFNULL(SUM(9_plan),0)    AS 9_plan,
				   IFNULL(SUM(9_result),0)  AS 9_result,
				   IFNULL(SUM(10_plan),0)   AS 10_plan,
				   IFNULL(SUM(10_result),0) AS 10_result,
				   IFNULL(SUM(11_plan),0)   AS 11_plan,
				   IFNULL(SUM(11_result),0) AS 11_result,
				   IFNULL(SUM(12_plan),0)   AS 12_plan,
				   IFNULL(SUM(12_result),0) AS 12_result,
				   IFNULL(SUM(1_plan),0)    AS 1_plan,
				   IFNULL(SUM(1_result),0)  AS 1_result,
				   IFNULL(SUM(2_plan),0)    AS 2_plan,
				   IFNULL(SUM(2_result),0)  AS 2_result,
				   IFNULL(SUM(3_plan),0)    AS 3_plan,
				   IFNULL(SUM(3_result),0)  AS 3_result
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u1,'
				      .local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u2,'
				      .$dataTableName .' d
				WHERE (d.partnerID = u1.id)
				AND   (d.executiveID=u2.id and u2.area like "'.$area.'")
	            AND    d.fiscal_year='.$year.'
				AND   (d.partnerID = '.$pid.')
				GROUP BY d.executiveID ORDER BY u2.code ASC
				';
			
		// SQL実行
		//echo 'sql='.$sql.'<br />';
		$db->exec($sql);

		// データ取得
		while ($data = $db->getData()) {
			$executiveValueList[] = array(
				'eid'       => $data['eid'],
				'code'      => $data['code'],
				'name'      => htmlspecialchars_decode($data['name'], ENT_QUOTES),
				'pid'       => $data['pid'],
				'4_plan'    => $data['4_plan'],
				'4_result'  => $data['4_result'],
				'5_plan'    => $data['5_plan'],
				'5_result'  => $data['5_result'],
				'6_plan'    => $data['6_plan'],
				'6_result'  => $data['6_result'],
				'7_plan'    => $data['7_plan'],
				'7_result'  => $data['7_result'],
				'8_plan'    => $data['8_plan'],
				'8_result'  => $data['8_result'],
				'9_plan'    => $data['9_plan'],
				'9_result'  => $data['9_result'],
				'10_plan'   => $data['10_plan'],
				'10_result' => $data['10_result'],
				'11_plan'   => $data['11_plan'],
				'11_result' => $data['11_result'],
				'12_plan'   => $data['12_plan'],
				'12_result' => $data['12_result'],
				'1_plan'    => $data['1_plan'],
				'1_result'  => $data['1_result'],
				'2_plan'    => $data['2_plan'],
				'2_result'  => $data['2_result'],
				'3_plan'    => $data['3_plan'],
				'3_result'  => $data['3_result'],
			);
		}
	}
	//-------------------------------
	// 協力企業の場合
	//-------------------------------
	else {
		$sql = 'SELECT u2.id     AS eid,
					u2.code   AS code,
					u2.name   AS name,
					u1.id     AS pid,
					IFNULL(SUM(4_plan),0)    AS 4_plan,
				   	IFNULL(SUM(4_result),0)  AS 4_result,
				   	IFNULL(SUM(4_shibu),0)   AS 4_shibu,
				   	IFNULL(SUM(5_plan),0)    AS 4_plan,
				   	IFNULL(SUM(5_result),0)  AS 4_result,
				   	IFNULL(SUM(5_shibu),0)   AS 4_shibu,
					IFNULL(SUM(6_plan),0)    AS 4_plan,
				   	IFNULL(SUM(6_result),0)  AS 4_result,
				   	IFNULL(SUM(6_shibu),0)   AS 4_shibu,
					IFNULL(SUM(7_plan),0)    AS 4_plan,
				   	IFNULL(SUM(7_result),0)  AS 4_result,
				   	IFNULL(SUM(7_shibu),0)   AS 4_shibu,
					IFNULL(SUM(8_plan),0)    AS 4_plan,
				   	IFNULL(SUM(8_result),0)  AS 4_result,
				   	IFNULL(SUM(8_shibu),0)   AS 4_shibu,
					IFNULL(SUM(9_plan),0)    AS 4_plan,
				   	IFNULL(SUM(9_result),0)  AS 4_result,
				   	IFNULL(SUM(9_shibu),0)   AS 4_shibu,
					IFNULL(SUM(10_plan),0)   AS 4_plan,
				   	IFNULL(SUM(10_result),0) AS 4_result,
				   	IFNULL(SUM(10_shibu),0)  AS 4_shibu,
					IFNULL(SUM(11_plan),0)   AS 4_plan,
				   	IFNULL(SUM(11_result),0) AS 4_result,
				   	IFNULL(SUM(11_shibu),0)  AS 4_shibu,
					IFNULL(SUM(12_plan),0)   AS 4_plan,
				   	IFNULL(SUM(12_result),0) AS 4_result,
				   	IFNULL(SUM(12_shibu),0)  AS 4_shibu,
					IFNULL(SUM(1_plan),0)    AS 4_plan,
				   	IFNULL(SUM(1_result),0)  AS 4_result,
				   	IFNULL(SUM(1_shibu),0)   AS 4_shibu,
					IFNULL(SUM(2_plan),0)    AS 4_plan,
				   	IFNULL(SUM(2_result),0)  AS 4_result,
				   	IFNULL(SUM(2_shibu),0)   AS 4_shibu,
					IFNULL(SUM(3_plan),0)    AS 4_plan,
				   	IFNULL(SUM(3_result),0)  AS 4_result,
				   	IFNULL(SUM(3_shibu),0)   AS 4_shibu
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u1,'
					.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u2,'
					.$dataTableName .' d
				WHERE (d.partnerID = u1.id)
				AND   (d.executiveID=u2.id and u2.area like "'.$area.'")
				AND    d.fiscal_year='.$year.'
				AND   (d.partnerID = '.$pid.')
				GROUP BY d.executiveID ORDER BY u2.code ASC
				';

		// SQL実行
		//echo 'sql='.$sql.'<br />';
		$db->exec($sql);

		// データ取得
		while ($data = $db->getData()) {
			$executiveValueList[] = array(
				'eid'       => $data['eid'],
				'code'      => $data['code'],
				'name'      => htmlspecialchars_decode($data['name'], ENT_QUOTES),
				'pid'       => $data['pid'],
				'4_plan'    => $data['4_plan'],
				'4_result'  => $data['4_result'],
				'4_shibu'   => $data['4_shibu'],
				'5_plan'    => $data['5_plan'],
				'5_result'  => $data['5_result'],
				'5_shibu'   => $data['5_shibu'],
				'6_plan'    => $data['6_plan'],
				'6_result'  => $data['6_result'],
				'6_shibu'   => $data['6_shibu'],
				'7_plan'    => $data['7_plan'],
				'7_result'  => $data['7_result'],
				'7_shibu'   => $data['7_shibu'],
				'8_plan'    => $data['8_plan'],
				'8_result'  => $data['8_result'],
				'8_shibu'   => $data['8_shibu'],
				'9_plan'    => $data['9_plan'],
				'9_result'  => $data['9_result'],
				'9_shibu'   => $data['9_shibu'],
				'10_plan'   => $data['10_plan'],
				'10_result' => $data['10_result'],
				'10_shibu'  => $data['10_shibu'],
				'11_plan'   => $data['11_plan'],
				'11_result' => $data['11_result'],
				'11_shibu'  => $data['11_shibu'],
				'12_plan'   => $data['12_plan'],
				'12_result' => $data['12_result'],
				'12_shibu'  => $data['12_shibu'],
				'1_plan'    => $data['1_plan'],
				'1_result'  => $data['1_result'],
				'1_shibu'   => $data['1_shibu'],
				'2_plan'    => $data['2_plan'],
				'2_result'  => $data['2_result'],
				'2_shibu'   => $data['2_shibu'],
				'3_plan'    => $data['3_plan'],
				'3_result'  => $data['3_result'],
				'3_shibu'   => $data['3_shibu']
			);
		}
	}

	// デバッグ用
	//printArray($executiveValueList);
	
	// データベースクローズ
	$db->close();
	
	return $executiveValueList;
}

/**
 * ----------------------------------------------------------
 * getExecutiveMinTargetInfo()
 * 同友最低販売基準値の取得
 * @param $year 年度
 * @return array $executiveMinTarget：同友最低販売基準値
 * ----------------------------------------------------------
 */
function getExecutiveMinTargetInfo($fiscal_year) {

	$executiveMinTarget = "";
	$executiveMinTargetArray = array();

	// データベース接続
	$db = new DB(config::DB_NAME);
	
	// SQL文生成
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "min_target_'.$fiscal_year.'"
			';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$executiveMinTarget = $data['value'];
	}

	// データベースのデータがない場合は、前年のデータを取得
	if ( strlen($executiveMinTarget) == 0) {

		// データベースのデータがない場合は、前年のデータを取得
		// SQL文生成
		$fiscal_year--;
		$sql = 'SELECT *
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
				WHERE item like "min_target_'.$fiscal_year.'"
				';

		// SQL実行
		//echo 'sql='.$sql.'<br />';
		$db->exec($sql);

		// データ取得
		while ($data = $db->getData()) {
			$executiveMinTarget = $data['value'];
		}
	}

	// 前年のデータもデータベースに入っていない場合はデフォルト値を入れる
	if ( strlen($executiveMinTarget) == 0) {
		//$executiveMinTarget = "LM:1-LS:2-LT:3-LH:4-LO:5-LE:6-LL:7";	 // default値
		$i = 1;
		$lmls = 0;
		$itemArray = getItemList('no-insert','',$fiscal_year,true,false,true);
		foreach ($itemArray as $item) {
			if ($item['value'] === 'LM' || $item['value'] === 'LS') {
				$lmls += $i;
			}
			$executiveMinTarget = $executiveMinTarget.$item['value'].':'.$i.'-';
			$i++;
		}
	}

	// Arrayに分解
	$temp = preg_split("/-/", $executiveMinTarget);
	//printArray($temp);
	for ($i= 0; $i < count($temp); $i++) {
		$sp = preg_split("/:/", $temp[$i]);
		$executiveMinTargetArray[$sp[0]] = $sp[1];
	}

	// データベースクローズ
	$db->close();
	
	// 同友最低販売基準値
	return $executiveMinTargetArray;
}

/**
 * ----------------------------------------------------------
 * getExecutivePromotionInfo()
 * 同友への販促費の取得
 * @param $year 年度
 * @return array $executivePromotionArray：同友への販促費
 * ----------------------------------------------------------
 */
function getExecutivePromotionInfo($fiscal_year) {
	
	$executivePromotion = "";
	$executivePromotionArray = array();

	// データベース接続
	$db = new DB(config::DB_NAME);
	
	// SQL文生成
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "excutive_promotion_'.$fiscal_year.'"
			';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$executivePromotion = $data['value'];
	}

	// データベースのデータがない場合は、前年のデータを取得
	if ( strlen($executivePromotion) == 0) {
		// SQL文生成
		$fiscal_year--;
		$sql = 'SELECT *
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
				WHERE item like "excutive_promotion_'.$fiscal_year.'"
				';

		// SQL実行
		//echo 'sql='.$sql.'<br />';
		$db->exec($sql);

		// データ取得
		while ($data = $db->getData()) {
			$executivePromotion = $data['value'];
		}
	}

	// 前年のデータもデータベースに入っていない場合はデフォルト値を入れる
	if ( strlen($executivePromotion) == 0) {
		$executivePromotion = 'LM:1-LS:2-LT:3-LT:4-LT:5-LT:6-LT:7-LT:8-';
		$executivePromotion = $executivePromotion.'LO:9-LO:10-LO:11-LO:12-LO:13-LO:14-';
		$executivePromotion = $executivePromotion.'LE:15-LE:16-LE:17-LE:18-LE:19-LE:20-';
		$executivePromotion = $executivePromotion.'LL:21-LL:22-LL:23-LL:24-LL:25-LL:26';
	}

	// Arrayに分解
	$temp = preg_split("/-/", $executivePromotion);
	//printArray($temp);
	$cnt = 1;
	for ($i = 0; $i < count($temp); $i++) {
		$sp = preg_split("/:/", $temp[$i]);
		
		// 種目が変わった時にカウンターをリセットする
		if ( $preItem !== $sp[0] ) {
			$cnt = 1;
		}

		// 2025.3.27
		// 種目を区別しなくても正しく順番にリードできているのでコメント化
		// if ($sp[0] === 'LM' || $sp[0] === 'LS') {
		// 	$executivePromotionArray[$sp[0].':ryoritsuE_'.$cnt] = $sp[1];
		// }
		// else {
			$executivePromotionArray[$sp[0].':ryoritsuE_'.$cnt] = $sp[1];
			$cnt++;
		//}

		$preItem = $sp[0];	// 前の種目を保存しておく
	}

	// データベースクローズ
	$db->close();
	
	// 同友への販促費
	return $executivePromotionArray;
}

/**
 * ----------------------------------------------------------
 * getBranchPromotionInfo()
 * 支部への販促費の取得
 * @param $year 年度
 * @param $setting_type：保存する設定値種別
 * @return array $branchPromotionArray：支部への販促費
 * ----------------------------------------------------------
 */
function getBranchPromotionInfo($fiscal_year, $setting_type) {
	
	$branchPromotion = "";
	$branchPromotionArray = array();

	$target_fiscal_year = $fiscal_year;

	// データベース接続
	$db = new DB(config::DB_NAME);
	
	// SQL文生成
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "'.$setting_type.'_'.$fiscal_year.'"
			';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$branchPromotion = $data['value'];
	}

	// データベースのデータがない場合は、前年のデータを取得
	if ( strlen($branchPromotion) == 0) {
		// SQL文生成
		$fiscal_year--;
		$sql = 'SELECT *
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
				WHERE item like "'.$setting_type.'_'.$fiscal_year.'"
				';

		// SQL実行
		//echo 'sql='.$sql.'<br />';
		$db->exec($sql);

		// データ取得
		while ($data = $db->getData()) {
			$branchPromotion = $data['value'];
		}
	}

	// 前年のデータがデータベースに入っていない場合はデフォルト値を入れる
	if ( strlen($branchPromotion) == 0) {
		// 2020年秋キャンペーンから追加
		if ($target_fiscal_year >= 2020) {
			// デフォルト値を入れる
			$branchPromotion = 'LM:1-LM:2-LM:3-LM:4-LS:1-LS:2-LS:3-LS:4-LT:1-LT:2-LT:3-LT:4-LT:5-LT:6-LT:7-LT:8-LT:9-LT:10-';
			$branchPromotion = $branchPromotion.'LO:1-LO:2-LO:3-LO:4-LO:5-LO:6-LO:7-LO:8-LO:9-LO:10-';
			$branchPromotion = $branchPromotion.'LE:1-LE:2-LE:3-LE:4-LE:5-LE:6-LE:7-LE:8-LE:9-LE:10-';
			$branchPromotion = $branchPromotion.'LL:1-LL:2-LL:3-LL:4-LL:5-LL:6-LL:7-LL:8-LL:9-LL:10';
		}
		else {
			$branchPromotion = 'LM:1-LS:1-LT:3-LT:4-LT:5-LT:6-LT:7-LT:8-';
			$branchPromotion = $branchPromotion.'LO:9-LO:10-LO:11-LO:12-LO:13-LO:14-';
			$branchPromotion = $branchPromotion.'LE:15-LE:16-LE:17-LE:18-LE:19-LE:20-';
			$branchPromotion = $branchPromotion.'LL:21-LL:22-LL:23-LL:24-LL:25-LL:26';
		}
	}

	// Arrayに分解
	$temp = preg_split("/-/", $branchPromotion);
	//printArray($temp);
	$cnt = 1;
	for ($i = 0; $i < count($temp); $i++) {
		$sp = preg_split("/:/", $temp[$i]);
		
		// 種目が変わった時にカウンターをリセットする
		if ( $preItem !== $sp[0] ) {
			$cnt = 1;
		}

		if ($sp[0] === 'LM' || $sp[0] === 'LS') {
			$branchPromotionArray[$sp[0].':'.$setting_type.'_'.$cnt] = $sp[1];
			$cnt++;
		}
		else {
			$branchPromotionArray[$sp[0].':'.$setting_type.'_'.$cnt] = $sp[1];
			$cnt++;
		}

		$preItem = $sp[0];	// 前の種目を保存しておく
	}

	// データベースクローズ
	$db->close();

	// デバッグ用
	//printArray($branchPromotionArray);
	
	// 支部への販促費
	return $branchPromotionArray;
}

/**
 * ----------------------------------------------------------
 * getOtherBranchPromotionInfo()
 * 販売施策のLOボーナス賞金、LH自動車特別賞、生産性＆ボリューム報奨金に関する設定を取得
 * @param $year 年度
 * @return array $lcHoldPromotionArray：支部への販促費
 * ----------------------------------------------------------
 */
function getOtherBranchPromotionInfo($fiscal_year) {
	
	$otherPromotion = "";
	$otherPromotionArray = array();

	// データベース接続
	$db = new DB(config::DB_NAME);
	
	// SQL文生成
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "other_prize_'.$fiscal_year.'"
			';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$otherPromotion = $data['value'];
	}

	// データベースのデータがない場合は、前年のデータを取得
	if ( strlen($otherPromotion) == 0) {
		// SQL文生成
		$fiscal_year--;
		$sql = 'SELECT *
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
				WHERE item like "other_prize_'.$fiscal_year.'"
				';

		// SQL実行
		//echo 'sql='.$sql.'<br />';
		$db->exec($sql);

		// データ取得
		while ($data = $db->getData()) {
			$otherPromotion = $data['value'];
		}
	}

	// データベースクローズ
	$db->close();

	// Arrayに分解
	$data = preg_split("/:/", $otherPromotion);

	$otherPromotionArray['lo_year_prize']        = $data[0];
	$otherPromotionArray['lh_summer_prize']      = $data[1];
	$otherPromotionArray['lh_autumn_prize']      = $data[2];
	$otherPromotionArray['lh_spring_prize']      = $data[3];
	$otherPromotionArray['lh_doyu_kaisyo_prize'] = $data[4];
	$otherPromotionArray['lms_seisan_prize_1']   = $data[5];
	$otherPromotionArray['lt_seisan_prize_1']    = $data[6];
	$otherPromotionArray['lh_seisan_prize_1']    = $data[7];
	$otherPromotionArray['lo_seisan_prize_1']    = $data[8];
	$otherPromotionArray['le_seisan_prize_1']    = $data[9];
	$otherPromotionArray['ll_seisan_prize_1']    = $data[10];
	$otherPromotionArray['lms_seisan_prize_2']   = $data[11];
	$otherPromotionArray['lt_seisan_prize_2']    = $data[12];
	$otherPromotionArray['lh_seisan_prize_2']    = $data[13];
	$otherPromotionArray['lo_seisan_prize_2']    = $data[14];
	$otherPromotionArray['le_seisan_prize_2']    = $data[15];
	$otherPromotionArray['ll_seisan_prize_2']    = $data[16];
	$otherPromotionArray['lms_volume_prize_1']   = $data[17];
	$otherPromotionArray['lt_volume_prize_1']    = $data[18];
	$otherPromotionArray['lh_volume_prize_1']    = $data[19];
	$otherPromotionArray['lo_volume_prize_1']    = $data[20];
	$otherPromotionArray['le_volume_prize_1']    = $data[21];
	$otherPromotionArray['ll_volume_prize_1']    = $data[22];
	$otherPromotionArray['lms_volume_prize_2']   = $data[23];
	$otherPromotionArray['lt_volume_prize_2']    = $data[24];
	$otherPromotionArray['lh_volume_prize_2']    = $data[25];
	$otherPromotionArray['lo_volume_prize_2']    = $data[26];
	$otherPromotionArray['le_volume_prize_2']    = $data[27];
	$otherPromotionArray['ll_volume_prize_2']    = $data[28];
	$otherPromotionArray['min_target_clear_prize'] = $data[29];
	$otherPromotionArray['lm_bonus_prize_1_half'] = $data[30];
	$otherPromotionArray['lm_bonus_prize_2_half'] = $data[31];
	$otherPromotionArray['ls_bonus_prize_1_half'] = $data[32];
	$otherPromotionArray['ls_bonus_prize_2_half'] = $data[33];
	// 社長賞 キャンペーン特別施策賞金
	$otherPromotionArray['LM+LS_special_prize_summer'] = $data[34];
	$otherPromotionArray['LT_special_prize_summer']    = $data[35];
	$otherPromotionArray['LO_special_prize_summer']    = $data[36];
	$otherPromotionArray['LE_special_prize_summer']    = $data[37];
	$otherPromotionArray['LL_special_prize_summer']    = $data[38];
	$otherPromotionArray['LM+LS_special_prize_autumn'] = $data[39];
	$otherPromotionArray['LT_special_prize_autumn']    = $data[40];
	$otherPromotionArray['LO_special_prize_autumn']    = $data[41];
	$otherPromotionArray['LE_special_prize_autumn']    = $data[42];
	$otherPromotionArray['LL_special_prize_autumn']    = $data[43];
	$otherPromotionArray['LM+LS_special_prize_spring'] = $data[44];
	$otherPromotionArray['LT_special_prize_spring']    = $data[45];
	$otherPromotionArray['LO_special_prize_spring']    = $data[46];
	$otherPromotionArray['LE_special_prize_spring']    = $data[47];
	$otherPromotionArray['LL_special_prize_spring']    = $data[48];

	// モービルプロショップ条件と特典
	$otherPromotionArray['LO_proshop_target']  = $data[49];
	$otherPromotionArray['LO_proshop_target2'] = $data[50];
	$otherPromotionArray['LO_proshop_msg']     = $data[51];

	// 社長賞支部部門の賞金額
	$otherPromotionArray['president_prize']    = $data[52];

	// 値が入っていないところには0を入れる
	foreach ($otherPromotionArray as $key => $value) {
		if ($value === "" || $value == null) {
			$otherPromotionArray[$key] = 0;
		}
	}

	return $otherPromotionArray;
}

/**
 * ----------------------------------------------------------
 * getLCHoldPromotionInfo()
 * LC獲得推進費と、LC保有支援金を取得
 * @param $year 年度
 * @return array $lcHoldPromotionArray：支部への販促費
 * ----------------------------------------------------------
 */
function getLCHoldPromotionInfo($fiscal_year) {
	
	$lcHoldPromotion = "";
	$lcHoldPromotionArray = array();

	// データベース接続
	$db = new DB(config::DB_NAME);
	
	// SQL文生成
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "lc_hold_number_'.$fiscal_year.'"
			';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$lcHoldPromotion = $data['value'];
	}

	// データベースのデータがない場合は、前年のデータを取得
	if ( strlen($lcHoldPromotion) == 0) {
		// SQL文生成
		$fiscal_year--;
		$sql = 'SELECT *
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
				WHERE item like "lc_hold_number_'.$fiscal_year.'"
				';

		// SQL実行
		//echo 'sql='.$sql.'<br />';
		$db->exec($sql);

		// データ取得
		while ($data = $db->getData()) {
			$lcHoldPromotion = $data['value'];
		}
	}

	// データベースクローズ
	$db->close();

	// Arrayに分解
	$data = preg_split("/:/", $lcHoldPromotion);

	$lcHoldPromotionArray['lc_year_target_count']   = $data[0];
	$lcHoldPromotionArray['lc_get_promotion_cost']  = $data[1];
	$lcHoldPromotionArray['lc_hold_support_cost']   = $data[2];
	$lcHoldPromotionArray['lc_get_promotion_count'] = $data[3];
	$lcHoldPromotionArray['lc_get_item_count_1']    = $data[4];
	$lcHoldPromotionArray['lc_get_item_prize_1']    = $data[5];
	$lcHoldPromotionArray['lc_get_item_count_2']    = $data[6];
	$lcHoldPromotionArray['lc_get_item_prize_2']    = $data[7];
	$lcHoldPromotionArray['lc_get_promotion_prize'] = $data[8];
	$lcHoldPromotionArray['lc_hold_support_count']  = $data[9];
	
	// LC獲得推進費と、LC保有支援金
	return $lcHoldPromotionArray;
}

/**
 * ----------------------------------------------------------
 * getLCHoldNumber()
 * LCの保有枚数を取得
 * @param $year 年度
 * @return array $lc_array 最新の保有枚数と保有月
 * ----------------------------------------------------------
 */
function getLCHoldNumber($fiscal_year) {

	$lc_array = array();
	$number = 0;
	$month = 0;

	$data = getAreaTotalValue('TOTAL', 'LC:spitem', '%', $fiscal_year, true);
	for ($i = 3; $i > 0; $i--) {
		if ($data[0][$i.'_result'] > 0) {
			$number = $data[0][$i.'_result'];
			$month = $i;
			break;
		}
	}
	if ($month == 0) {
		for ($i = 12; $i > 3; $i--) {
			if ($data[0][$i.'_result'] > 0) {
				$number = $data[0][$i.'_result'];
				$month = $i;
				break;
			}
		}
	}

	$lc_array['LC_hold_number'] = $number;
	$lc_array['LC_hold_month'] = $month;

	return $lc_array;
}

/**
 * ----------------------------------------------------------
 * getPartnerPromotionInfo()
 * 提携企業の販促費の取得
 * @param $year 年度
 * @param $partner：提携企業の種別（config.phpで設定している値）
 * @return array $branchPromotionArray：支部への販促費
 * ----------------------------------------------------------
 */
function getPartnerPromotionInfo($fiscal_year, $partner) {
	
	$partnerPromotion = "";
	$partnerPromotionArray = array();

	// データベース接続
	$db = new DB(config::DB_NAME);
	
	// SQL文生成
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "'.$partner.'_promotion_'.$fiscal_year.'"
			';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$partnerPromotion = $data['value'];
	}

	// データベースクローズ
	$db->close();

	// データベースにデータが入っていない場合はデフォルト値を入れる
	if (strlen($partnerPromotion) == 0) {
		$partnerPromotion = '0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0';
	}

	// Arrayに分解
	$partnerPromotionArray = preg_split("/:/", $partnerPromotion);

	// 支部への販促費
	return $partnerPromotionArray;
}

/**
 * ----------------------------------------------------------
 * getPointStatusInfo()
 * 支部表彰得点状況の設定値の取得
 * @param $year 年度
 * @return array $branchPromotionArray：支部への販促費
 * ----------------------------------------------------------
 */
function getPointStatusInfo($fiscal_year) {
	
	$pointStatus = "";
	$pointStatusArray = array();

	// データベース接続
	$db = new DB(config::DB_NAME);
	
	// SQL文生成
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "point_status_'.$fiscal_year.'"
			';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$pointStatus = $data['value'];
	}

	// データベースクローズ
	$db->close();

	// データベースにデータが入っていない場合はデフォルト値を入れる
	if (strlen($pointStatus) == 0) {
		$pointStatus = '0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0';
	}

	// Arrayに分解
	$pointStatusArray = preg_split("/:/", $pointStatus);

	// 支部への販促費
	return $pointStatusArray;
}

/**
 * ----------------------------------------------------------
 * getCampaignInfo()
 * 指定年度のデータの情報を取得
 * @param $fiscal_year 年度
 * @param $item 種目
 * @return array $dataArray キャンペーンデータの情報
 * ----------------------------------------------------------
 */
function getCampaignInfo($fiscal_year, $item='') {

	$dataArray = array();

	// データベース接続
	$db = new DB(config::DB_NAME);

	// SQL文生成
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN .'
			WHERE fiscal_year = '.$fiscal_year.'
			';
	
	// 種目が指定されている場合
	if (strlen($item) > 0) {
	
		// LM+LSの場合はitemをLMに変更(※LM+LSのキャンペーンデータフィールドはLMのため)
		if ($item === 'LM+LS') {
			$item - 'LM';
		}
	
		$sql = $sql . ' AND item like "'.$item.'"';
	}

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);
	
	// データ取得
	while ($data = $db->getData()) {
		$dataArray = array(
			'fiscal_year'            => $data['fiscal_year'],
			'item'                   => $data['item'],
			'summer_ave'             => $data['summer_ave'],
			'summer_plan'            => $data['summer_plan'],
			'summer_enterable_under' => $data['summer_enterable_under'],
			'summer_enterable_upper' => $data['summer_enterable_upper'],
			'summer_target'          => $data['summer_target'],
			'autumn_ave'             => $data['autumn_ave'],
			'autumn_plan'            => $data['autumn_plan'],
			'autumn_enterable_under' => $data['autumn_enterable_under'],
			'autumn_enterable_upper' => $data['autumn_enterable_upper'],
			'autumn_target'          => $data['autumn_target'],
			'spring_ave'             => $data['spring_ave'],
			'spring_plan'            => $data['spring_plan'],
			'spring_enterable_under' => $data['spring_enterable_under'],
			'spring_enterable_upper' => $data['spring_enterable_upper'],
			'spring_target'          => $data['spring_target'],
			'summer_opt'             => $data['summer_opt'],
			'autumn_opt'             => $data['autumn_opt'],
			'spring_opt'             => $data['spring_opt']
		);
	}
	
	// データベースクローズ
	$db->close();
	
	// ユーザ情報通知
	return $dataArray;
}

/**
 * ----------------------------------------------------------
 * getCampaignDataInfo()
 * 指定年度のデータの情報を取得
 * @param $fiscal_year 年度
 * @param $eid 同友会社ID
 * @param $pid 提携企業ID
 * @return array $dataArray：キャンペーンデータの情報
 * ----------------------------------------------------------
 */
function getCampaignDataInfo($fiscal_year, $eid=0, $pid=0) {

	$dataArray = array();

	// データベース接続
	$db = new DB(config::DB_NAME);

	// SQL文生成
	$sql = 'SELECT fiscal_year,
				   MIN(summer_open) AS summer_open,
				   MIN(autumn_open) AS autumn_open,
				   MIN(spring_open) AS spring_open,
				   MIN(summer_enterable) AS summer_enterable,
				   MIN(autumn_enterable) AS autumn_enterable,
				   MIN(spring_enterable) AS spring_enterable
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA .'
			WHERE fiscal_year = '.$fiscal_year.'
			';
	
	// 提携企業が指定されている場合
	if ($pid > 0) {
		$sql = $sql . ' AND partnerID = '.$pid;
	}

	// 同友IDが指定されている場合
	if ($eid > 0) {
		$sql = $sql . ' 
			   AND executiveID = '.$eid.'
			   GROUP BY executiveID
			   ';
	}

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);
	
	// データ取得
	while ($data = $db->getData()) {
		$dataArray = array(
			'fiscal_year'      => $data['fiscal_year'],
			'summer_open'      => $data['summer_open'],
			'summer_enterable' => $data['summer_enterable'],
			'autumn_open'      => $data['autumn_open'],
			'autumn_enterable' => $data['autumn_enterable'],
			'spring_open'      => $data['spring_open'],
			'spring_enterable' => $data['spring_enterable']
		);
	}
	
	// データベースクローズ
	$db->close();
	
	// ユーザ情報通知
	return $dataArray;
}


/**
 * ----------------------------------------------------------
 * getCampaignMonthTotalValue()
 * 指定年度、指定月、指定種目の計画、実績の合計値を取得
 * @param $fiscal_year 年度
 * @param $item 種目名
 * @param $pid 提携企業ID
 * @param $area 地域名
 * @param $eid 同友ID
 * @param $start_month 集計開始月
 * @param $end_month 集計終了月
 * @param $round_flag 合計計算時の四捨五入をするか判定フラグ
 * @return array $totalValueList：合計値
 * ----------------------------------------------------------
 */
function getCampaignMonthTotalValue($fiscal_year, $item, $pid, $area, $eid=0, $start_month, $end_month, $round_flag=true) {

	$campaign = '';
	$totalValueList = array();
	
	// 指定した月からキャンペーン種別を判定
	if (config::SUMMER_CAMPAIGN_START_MONTH == $start_month && config::SUMMER_CAMPAIGN_END_MONTH == $end_month) {
		$campaign = 'summer';
	}
	elseif (config::AUTUMN_CAMPAIGN_START_MONTH == $start_month && config::AUTUMN_CAMPAIGN_END_MONTH == $end_month) {
		$campaign = 'autumn';
	}
	elseif (config::SPRING_CAMPAIGN_START_MONTH == $start_month && config::SPRING_CAMPAIGN_END_MONTH == $end_month) {
		$campaign = 'spring';
	}
	else {
		return $totalValueList;
	}

	// 参照テーブルの設定
	$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA;
	
	// itemが補助種目の場合
	if (strpos($item, ':sub_') !== false) {
		$subItemArray = explode(':', $item);			// メイン種目の取り出し
		$subItemNo = explode('_', $subItemArray[1]);	// 補助種目が何番目のものかを取り出し(例：sub_1_2の場合、1がメイン種目との関連付け、2がテーブルNo)
		
		// 参照テーブルの変更
		$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SUBITEM.$subItemNo[2];
		
		// 種目の設定
		$item = $subItemArray[0];
	}

	// itemが特別種目の場合
	if (strpos($item, ':spitem') !== false) {
		$itemArray = explode(':', $item);	// 種目の取り出し
		$item = $itemArray[0];				// 種目の設定
		$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SPITEM;	// 参照テーブルの変更
	}

	// データベース接続
	$db = new DB(config::DB_NAME);

	// SQL文生成
	$sql = 'SELECT ';
	
	// 指定月のみsql文を作成
	for ($i = $start_month; $i <= $end_month; $i++) {
		$sql = $sql.'IFNULL(SUM(d.'.$i.'_result),0)  AS '.$i.'_result';
		//$sql = $sql.'COUNT((d.'.$i.'_result > 0 and cd.'.$campaign.'_enterable = 1) or NULL)  AS '.$i.'_cnt';
		if ($i < $end_month) {
			$sql = $sql.',';
		}
		else {
			$sql = $sql.' ';
		}
	}

	// 同友IDを指定しているかどうか
	if ($eid == 0) {
		$sql = $sql.
			   'FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' pu,'
				      .local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' eu,'
				      .$dataTableName .' d,'
					  .local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA .' cd
				WHERE (d.partnerID = pu.id AND pu.item like "'.$item.'")
				AND   (d.executiveID = eu.id AND eu.area like "'.$area.'")
				AND   (d.fiscal_year = cd.fiscal_year AND d.executiveID = cd.executiveID AND d.partnerID = cd.partnerID)
	            AND    d.fiscal_year='.$fiscal_year.'
				';
	}
	else {
		$sql = $sql.
			   'FROM '.$dataTableName .' d,'
					  .local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA .' cd
				WHERE (d.fiscal_year = cd.fiscal_year AND d.executiveID = cd.executiveID AND d.partnerID = cd.partnerID)
	            AND    d.fiscal_year='.$fiscal_year.'
				';
	}
	
	// 提携企業IDが設定されている場合
	if ($pid > 0) {
		$sql = $sql.' AND d.partnerID = '.$pid;
	}

	// 同友IDが設定されている場合
	if ($eid > 0) {
		$sql = $sql.' AND d.executiveID = '.$eid;
	}

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {

		$totalResultValue = 0;
		$cnt = 0;
		
		// 指定期間の実績合計値、参加同友数を算出
		for ($i = $start_month; $i <= $end_month; $i++) {
			$result = $i.'_result';
			if (local_config::FEATURE_RESULT_ROUND_BY_MONTH){
				if ($round_flag) {
					$totalResultValue += round($data[$result]);		// 提携毎、月毎に1,000円単位で四捨五入してから合計を計算
				}
				else {
					$totalResultValue += $data[$result];
				}
			}
			else {
				$totalResultValue += $data[$result];
			}
			
			// 実績ありの同友数の最大値を取得
			//$max = $i.'_cnt';
			//if ($cnt < $data[$max]) {
			//	$cnt = $data[$max];
			//}
		}
	
		// 戻り値用の配列作成
		$totalValueList = array(
			'fiscal_year' => $fiscal_year,
			'pid'	      => $pid,
			'item'	      => $item,
			'result'      => $totalResultValue,
			//'count'   => $cnt
		);

		// 月毎のデータも格納して、返す
		for ($i = $start_month; $i <= $end_month; $i++) {
			$result = $i.'_result';
			$totalValueList = array_merge($totalValueList, array($result =>$data[$result]));
		}
	}

	// デバッグ用
	//printArray($totalValueList);
	
	// データベースクローズ
	$db->close();
	
	return $totalValueList;

}

/**
 * ----------------------------------------------------------
 * getCampaignMonthTotalExecutiveResultCount()
 * 指定年度、指定月、指定種目の実績の合計値で実績のある会社数を取得
 * @param $fiscal_year 年度
 * @param $partnerList 提携企業一覧
 * @param $area 地域名
 * @param $start_month 集計開始月
 * @param $end_month 集計終了月
 * @param $join キャンペーンの参加の有無(false:参加してない同友もカウントの対象とする)
 * @return array $cnt：実績のある同友数
 * ----------------------------------------------------------
 */
function getCampaignMonthTotalExecutiveResultCount($fiscal_year, $partnerList, $area, $start_month, $end_month, $join=true) {

	$campaign = '';
	
	// 指定した月からキャンペーン種別を判定
	if (config::SUMMER_CAMPAIGN_START_MONTH == $start_month && config::SUMMER_CAMPAIGN_END_MONTH == $end_month) {
		$campaign = 'summer';
	}
	elseif (config::AUTUMN_CAMPAIGN_START_MONTH == $start_month && config::AUTUMN_CAMPAIGN_END_MONTH == $end_month) {
		$campaign = 'autumn';
	}
	elseif (config::SPRING_CAMPAIGN_START_MONTH == $start_month && config::SPRING_CAMPAIGN_END_MONTH == $end_month) {
		$campaign = 'spring';
	}
	else {
		return 0;
	}

	//echo '<br />partnerList count ='.count($partnerList).'<br />';

	// データベース接続
	$db = new DB(config::DB_NAME);

	// SQL文生成
	$sql = 'SELECT COUNT(*) AS cnt
		    FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA .' d
				INNER JOIN '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA .' cd 
					ON d.fiscal_year = cd.fiscal_year 
					AND d.executiveID = cd.executiveID 
					AND d.partnerID = cd.partnerID
				INNER JOIN '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' eu
					ON d.executiveID = eu.id
				WHERE
					d.fiscal_year = '.$fiscal_year.'
					AND eu.area LIKE "'.$area.'"';

	// 実績のカウントを行うのはキャンペーン参加同友のみ（参加資格なし、退会(休会)は含めない）
	if ($join) {
		$sql = $sql.' AND cd.'.$campaign.'_enterable = '.config::STATUS_ENTERABLE.' GROUP BY d.executiveID';
	}
	else {
		$sql = $sql.' AND cd.'.$campaign.'_enterable != '.config::STATUS_RECESS.' GROUP BY d.executiveID';	// 退会(休会)以外を取得 ※地区対抗戦の全同友表示で使用
	}

	// 提携企業が設定されている場合のみ実施
	if (count($partnerList) > 0) {
		
		$sql = $sql.' HAVING ';
		
		// 提携企業の条件
		$partnerCnt = 0;
		foreach ($partnerList as $partnerArray) {
			
			// すべての提携企業のキャンペーン期間中の実績の合計値がプラスの場合、参加同友数としてカウントする
			$sql = $sql. 'SUM(CASE WHEN d.partnerID = '.$partnerArray['value'].' THEN COALESCE(d.'.$start_month.'_result, 0) + COALESCE(d.'.$end_month.'_result, 0) ELSE 0 END)';

			$partnerCnt++;
			if ($partnerCnt == count($partnerList)) {
				$sql = $sql. ' > 0 ';
				break;
			}
			else {
				$sql = $sql. ' + ';
			}
		}
	}

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	$cnt = 0;
	while ($data = $db->getData()) {
		//if (count($partnerList) == $data['cnt']) {
			$cnt++;
		//}
	}
	
	// データベースクローズ
	$db->close();
	
	return $cnt;
}

/**
 * ----------------------------------------------------------
 * getCampaignPlanTotalValue()
 * 指定年度、指定月、指定種目の計画の合計値を取得
 * @param $fiscal_year 年度
 * @param $item 種目名
 * @param $pid 提携企業ID
 * @param $area 地域名
 * @param $eid 同友ID
 * @return array $totalValueList：合計値
 * ----------------------------------------------------------
 */
function getCampaignPlanTotalValue($fiscal_year, $item, $pid, $area, $eid=0) {

	// Arrayの初期化
	$totalValueList = array();

	// データベース接続
	$db = new DB(config::DB_NAME);

	if ($eid == 0) {
		// SQL文生成
		$sql = 'SELECT cd.fiscal_year   AS fiscal_year,
		               IFNULL(SUM(summer_plan),0) AS summer_plan,
					   IFNULL(SUM(autumn_plan),0) AS autumn_plan,
					   IFNULL(SUM(spring_plan),0) AS spring_plan
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' pu,'
					  .local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' eu,'
				      .local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA .' cd
				WHERE (cd.partnerID = pu.id AND pu.item like "'.$item.'")
				AND   (cd.executiveID = eu.id AND eu.area like "'.$area.'")
	            AND    cd.fiscal_year='.$fiscal_year.'
				AND    cd.partnerID = '.$pid.'
				';
	}
	else {
		// SQL文生成
		$sql = 'SELECT cd.fiscal_year   AS fiscal_year,
		               IFNULL(SUM(summer_plan),0) AS summer_plan,
					   IFNULL(SUM(autumn_plan),0) AS autumn_plan,
					   IFNULL(SUM(spring_plan),0) AS spring_plan
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA .' cd
				WHERE  cd.fiscal_year='.$fiscal_year.'
				AND    cd.executiveID = '.$eid.'
				AND    cd.partnerID = '.$pid.'
				';
	}

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$totalValueList = array(
			'fiscal_year' => $data['fiscal_year'],
			'summer_plan' => $data['summer_plan'],
			'autumn_plan' => $data['autumn_plan'],
			'spring_plan' => $data['spring_plan']
		);
	}

	// デバッグ用
	//printArray($totalValueList);
	
	// データベースクローズ
	$db->close();
	
	return $totalValueList;
}

/**
 * ----------------------------------------------------------
 * getCampaignExecutiveEnterableList()
 * 指定年度の指定キャンペーンのキャンペーン分母同友を取得
 * @param $fiscal_year 年度
 * @param $area 地域
 * @param $campaign キャンペーン種別
 * @param $type recess キャンペーン参加のみを取得（分母外、休会を除いた同友を取得）
 *              non_recess 休会以外をすべて取得
 * @return array キャンペーン分母同友数
 * ----------------------------------------------------------
 */
function getCampaignExecutiveEnterableList($fiscal_year, $area, $campaign, $type='') {

	$dataArray = array();
	
	// 退会(休会)を除いた同友を取得する場合
	if ($type === 'recess') {
		$enterable = ' AND '.$campaign.'_enterable = '.config::STATUS_ENTERABLE.'';
	}
	else if ($type === 'non_recess') {
		$enterable = ' AND '.$campaign.'_enterable != '.config::STATUS_RECESS.' AND '.$campaign.'_enterable != '.config::STATUS_NO_BASE_RECESS.''; // 退会(休会)以外を取得 ※地区対抗戦の全同友表示で使用
	}
	else {
		$enterable = ' AND '.$campaign.'_enterable != '.config::STATUS_UNENTERABLE.' AND '.$campaign.'_enterable != '.config::STATUS_NO_BASE_RECESS.'';
	}

	// データベース接続
	$db = new DB(config::DB_NAME);

	if ($area === '%') {
		// SQL文生成
		$sql = 'SELECT executiveID
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA .'
				WHERE fiscal_year = '.$fiscal_year.
				$enterable.'
				GROUP BY executiveID
				';
	}
	else {
		// SQL文生成
		$sql = 'SELECT executiveID
				FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .' u,'
				      .local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA .' d
				WHERE (d.executiveID = u.id and u.area like "'.$area.'")
	            AND    d.fiscal_year='.$fiscal_year.
				$enterable.'
				GROUP BY executiveID
				';
	}
	
	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);
	
	// データ取得
	while ($data = $db->getData()) {
		$dataArray[] = $data['executiveID'];
	}
	
	// データベースクローズ
	$db->close();
	
	// ユーザ情報通知
	return $dataArray;
}

/**
 * ----------------------------------------------------------
 * getCampaignGraphData()
 * 指定年度、指定キャンペーンの達成状況を取得
 * @param $fiscal_year 年度
 * @param $campaign キャンペーン種別
 * @return array 達成率データ
 * ----------------------------------------------------------
 */
function getCampaignGraphData($fiscal_year, $campaign) {

	$dataArray = array();

	// データベース接続
	$db = new DB(config::DB_NAME);

	// SQL文生成
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_GRAPH .'
			WHERE fiscal_year = '.$fiscal_year.'
			AND   campaign like "'.$campaign.'"
			ORDER BY date ASC
			';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);
	
	// データ取得
	while ($data = $db->getData()) {
		$dataArray[] = array(
			'id'          => $data['id'],
			'fiscal_year' => $data['fiscal_year'],
			'campaign'    => $data['campaign'],
			'date'        => $data['date'],
			'data'        => $data['data']
		);
	}
	
	// データベースクローズ
	$db->close();

	// デバッグ用
	//var_dump($dataArray);
	
	// ユーザ情報通知
	return $dataArray;
}

/**
 * ----------------------------------------------------------
 * getPartnerPrizeInfo()
 * 同友への販促費を取得
 * @param $fiscal_year 年度
 * @param $item        種目
 * @return array 取得データ
 * ----------------------------------------------------------
 */
function getPartnerPrizeInfo($fiscal_year, $item) {

	$partnerPrize = "";
	$partnerPrizeArray = array();

	// データベースのテーブルプレフィックスが「神奈川以外」の場合は「神奈川」に変更
	// 神奈川の本部施策を他の支部でも読み込む
	// ★★本部施策のみ対応
	if (local_config::FEATURE_PARTNER_PRIZE_ONE_SETTING) {
		$backup_prefix = local_config::$DB_TABLE_PREFIX;
		if (local_config::$DB_TABLE_PREFIX !== "kngw" && ($item === "LM" || $item === "LS" || $item === "LTB" || $item === "LTY" || $item === "LOP" || $item === "LEP" || $item === "LEG") ) {
			local_config::$DB_TABLE_PREFIX = "kngw";
			//echo 'change_prefix';
		}
	}

	// データベース接続
	$db = new DB(config::DB_NAME);
	
	// SQL文生成LM_partner_prize_2017
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "'.$item.'_partner_prize_'.$fiscal_year.'"
			';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$partnerPrize = $data['value'];
	}

	// データベースクローズ
	$db->close();

	// Arrayに分解
	$dataArray = preg_split("/;/", $partnerPrize);

	foreach ($dataArray as $data) {
		$tmp = preg_split("/\|/", $data);
		$partnerPrizeArray[$tmp[0]] = $tmp[1];
	}

	// プレフィックスを元に戻す
	if (local_config::FEATURE_PARTNER_PRIZE_ONE_SETTING) {
		local_config::$DB_TABLE_PREFIX = $backup_prefix;
	}

	//printArray($partnerPrizeArray);	// デバッグ
	return $partnerPrizeArray;
}

/**
 * ----------------------------------------------------------
 * getCooperationInfo()
 * 協力企業の設定を取得
 * @param $fiscal_year 年度
 * @param $partner     協力企業ID
 * @return array 取得データ
 * ----------------------------------------------------------
 */
function getCooperationInfo($fiscal_year, $partner) {

	$cooperatio = "";
	$cooperationArray = array();

	// データベース接続
	$db = new DB(config::DB_NAME);
	
	// SQL文生成LM_partner_prize_2017
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "cooperation_'.$partner.'_'.$fiscal_year.'"
			';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$cooperatio = $data['value'];
	}

	// データベースクローズ
	$db->close();

	// Arrayに分解
	$dataArray = preg_split("/;/", $cooperatio);

	foreach ($dataArray as $data) {
		$tmp = preg_split("/\|/", $data);
		$cooperatioArray[$tmp[0]] = $tmp[1];
	}

	//printArray($cooperatioArray);	// デバッグ
	return $cooperatioArray;
}

/**
 * ----------------------------------------------------------
 * getMsgforExecutive()
 * 同友向けメッセージの取得
 * @return array $msg：同友向けメッセージ
 * ----------------------------------------------------------
 */
function getMsgforExecutive() {
	
	$msg = "";

	// データベース接続
	$db = new DB(config::DB_NAME);
	
	// SQL文生成
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "msg"';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$msg = $data['value'];
	}

	// データベースクローズ
	$db->close();
	
	// 同友最低販売基準値
	return $msg;
}

/**
 * ----------------------------------------------------------
 * getEachExecutiveMsg()
 * 同友向けの個別メッセージを取得
 * @param eid 情報を取得するユーザID
 * @return array ログイン履歴
 * ----------------------------------------------------------
 */
function getEachExecutiveMsg($eid=0) {

	$msg = "";

	// データベース接続
	$db = new DB(config::DB_NAME);

	// SQL文生成
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .'
			WHERE id = "' . $eid.'"
			';
	
	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	while ($data = $db->getData()) {
		$msg = $data['msg'];
	}
	
	// データベースクローズ
	$db->close();

	return $msg;
}


/**
 * ----------------------------------------------------------
 * getLoginHistory()
 * ログイン履歴を取得
 * @param eid 情報を取得するユーザID
 * @return array ログイン履歴
 * ----------------------------------------------------------
 */
function getLoginHistory($eid=0) {

	$loginHistory = array();

	// データベース接続
	$db = new DB(config::DB_NAME);

	// SQL文生成
	$sql = 'SELECT *
			FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER .'
			WHERE id = "' . $eid.'"
			';
	
	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	while ($data = $db->getData()) {
		// ログイン情報はセミコロン(;)で区切っているのでそれを分解して入れる
		$loginHistory = preg_split("/\;/", $data['login_history']);
	}
	
	// データベースクローズ
	$db->close();

	// ログイン情報通知
	if (count($loginHistory) > 0) {
		return $loginHistory;
	}
	else {
		return '';
	}
}

?>