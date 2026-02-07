<?php
/**
 * =================================================================
 * writeDB.php
 * データベース書き込み用関数
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
 * createUserInfo()
 * ユーザデータを新規作成する
 * @param $postArray：POSTで送られてきたデータ
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function createUserInfo($postArray)
{

    $ret = 'success';
    
    $item = '-';
    $code = 0;
    $area = '-';
    $enterable = 0;
    
    // 提携企業か同友会社で格納する値を変更
    if ($postArray['auth'] == config::USER_PARTNER) {
        // 提携の場合は種目のみ設定
        $item = $postArray['item'];
    }
    elseif ($postArray['auth'] == config::USER_EXECUTIVE){
        $code = convertDoubleByteChar($postArray['code']);
        $area = $postArray['area'];
        $enterable = $postArray['enterable'];
    }
    
    // 現在の日付を取得
    $enterdate = getCurrentDate();

    // データベースに接続
    $db = new DB(config::DB_NAME);
    
    $sql = sprintf("INSERT INTO %s (name,
									kana,
									user,
									password,
									auth,
									item,
									code,
									area,
									enterable,
									enterdate,
									exitdate) 
									VALUES ('%s','%s','%s','%s','%d','%s','%d','%s','%d','%s','%s')",
                                    local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER,
                                    $db->escapeString($postArray['name']),
                                    $db->escapeString($postArray['kana']),
                                    $db->escapeString(convertDoubleByteChar($postArray['user'])),
                                    $db->escapeString(convertDoubleByteChar($postArray['password'])),
                                    $db->escapeString($postArray['auth']),
                                    $db->escapeString($item),
                                    $db->escapeString($code),
                                    $db->escapeString($area),
                                    $db->escapeString($enterable),
                                    $db->escapeString($enterdate),
                                    $db->escapeString(0)
                                    );

    // SQL実行
    //echo $sql.'<br />';
    $ret = $db->exec($sql);
    
    // データベースクローズ
    $db->close();

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * updateUserInfo()
 * ユーザデータを更新する
 * @param $postArray：POSTで送られてきたデータ
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function updateUserInfo($postArray)
{

    $ret = 'success';

    // 現在の日付を取得
    $exitdate = getCurrentDate();
    $exitdataArray = preg_split("/-/", $exitdate);  // 時間を分解
    $month = $exitdataArray[1];                 // 退会実行月を取得
    // 退会年度を取得
    $year = getCurrentFiscalYear();
    
    // データベースに接続
    $db = new DB(config::DB_NAME);

    //======================================
    // ユーザ情報の更新
    //======================================
    if ($postArray['auth'] == config::USER_PARTNER) {
        // 提携企業用 SQL生成
        $sql = sprintf("UPDATE %s	SET		name     = '%s',
											kana     = '%s',
											user     = '%s',
											password = '%s',
											item     = '%s'
									WHERE	id       = '%d'
									",
                                    local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER,
                                    $db->escapeString($postArray['name']),
                                    $db->escapeString($postArray['kana']),
                                    $db->escapeString(convertDoubleByteChar($postArray['user'])),
                                    $db->escapeString(convertDoubleByteChar($postArray['password'])),
                                    $db->escapeString($postArray['item']),
                                    $db->escapeString($postArray['id'])
                                    );
    } else {
        // 同友会社用 SQL生成
        $sql = sprintf("UPDATE %s	SET		name      = '%s',
											kana      = '%s',
											user      = '%s',
											password  = '%s',
											area      = '%s',
											code      = '%d',
											enterable = '%d'
									WHERE	id        = '%d'
									",
                                    local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER,
                                    $db->escapeString($postArray['name']),
                                    $db->escapeString($postArray['kana']),
                                    $db->escapeString(convertDoubleByteChar($postArray['user'])),
                                    $db->escapeString(convertDoubleByteChar($postArray['password'])),
                                    $db->escapeString($postArray['area']),
                                    $db->escapeString(convertDoubleByteChar($postArray['code'])),
                                    $db->escapeString($postArray['enterable']),
                                    $db->escapeString($postArray['id'])
                                    );
    }

    // SQL実行
    //echo $sql.'<br />';
    $ret = $db->exec($sql);

    //======================================
    // キャンペーンテーブルの更新
    // 同友の分母の状態をキャンペーンの計画作成に反映する
    //======================================
    if (isset($postArray['enterable'])) {
        $enterable = $postArray['enterable'];
        
        // 該当キャンペーンを選択
        // 指定した月からキャンペーン種別を判定キャンペーン参加している同友一覧です。
        $campaign = '';
        if ($month == 4 || $month == 5 || $month == 6 || $month == 7) {
            $campaign = 'summer_enterable = '.$enterable.', autumn_enterable = '.$enterable.', spring_enterable = '.$enterable;
        } elseif ($month == 8 || $month == 9 || $month == 10 || $month == 11) {
            $campaign = 'autumn_enterable = '.$enterable.', spring_enterable = '.$enterable;
        } elseif ($month == 12 || $month == 1 || $month == 2 || $month == 3) {
            $campaign = 'spring_enterable = '.$enterable;
        }
        
        // 退会実行キャンペーン計画数分
        for ($i = 0; $i < config::MAKE_PLAN_NUM_MAX; $i++) {
            // 細かく考えるのは今年度のみで、次年度移行は全て更新
            if ($i > 0) {
                $campaign = 'summer_enterable = '.$enterable.', autumn_enterable = '.$enterable.', spring_enterable = '.$enterable;
            }
        
            // 同友会社用 SQL生成
            $sql = sprintf("UPDATE %s	SET		%s
										WHERE	executiveID = '%d'
										AND		fiscal_year = '%d'
										",
                                        local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA,
                                        $db->escapeString($campaign),
                                        $db->escapeString($postArray['id']),
                                        $db->escapeString($year+$i)
                                        );
            // SQL実行
            //echo $sql.'<br />';
            $ret = $db->exec($sql);
        }
    }
    
    // データベースクローズ
    $db->close();

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * exitUserInfo()
 * ユーザを退会処理する
 * @param $postArray：POSTで送られてきたデータ
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function exitUserInfo($postArray)
{

    $ret = 'success';
    
    // 削除するユーザ情報に紐付ける時間を取得
    $exittime = getCurrentTime();
    $exittime = str_replace(' ', '', $exittime);
    $exittime = str_replace(':', '', $exittime);
    $exittime = str_replace('-', '', $exittime);
    
    // 現在の日付を取得
    $exitdate = getCurrentDate();
    $exitdataArray = preg_split("/-/", $exitdate);  // 時間を分解
    $month = $exitdataArray[1];                 // 退会実行月を取得
    // 退会年度を取得
    $year = getCurrentFiscalYear();

    // データベースに接続
    $db = new DB(config::DB_NAME);
    
    //======================================
    // ユーザテーブルの更新
    //======================================
    
    // SQL生成
    $sql = sprintf("UPDATE %s	SET		user     = '%s',
										exitdate = '%s'
								WHERE	id       = '%d'
								",
                                local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER,
                                $db->escapeString($postArray['user'].$exittime),
                                $db->escapeString($exitdate),
                                $db->escapeString($postArray['id'])
                                );

    // SQL実行
    //echo $sql.'<br />';
    $ret = $db->exec($sql);
    
    //======================================
    // キャンペーンテーブルの更新
    // 退会ボタン押下から未来のテーブルで退会した同友がある場合は退会にセットする
    //======================================
    
    // 該当キャンペーンを選択
    // 指定した月からキャンペーン種別を判定キャンペーン参加している同友一覧です。
    $campaign = '';
    if ($month == 4 || $month == 5 || $month == 6 || $month == 7) {
        $campaign = 'summer_enterable = 2, autumn_enterable = 2, spring_enterable = 2 ';
    } elseif ($month == 8 || $month == 9 || $month == 10 || $month == 11) {
        $campaign = 'autumn_enterable = 2, spring_enterable = 2 ';
    } elseif ($month == 12 || $month == 1 || $month == 2 || $month == 3) {
        $campaign = 'spring_enterable = 2 ';
    }
    
    // 退会実行キャンペーン計画数分
    for ($i = 0; $i < config::MAKE_PLAN_NUM_MAX; $i++) {
        // 細かく考えるのは今年度のみで、次年度移行は全て更新
        if ($i > 0) {
            $campaign = 'summer_enterable = 2, autumn_enterable = 2, spring_enterable = 2 ';
        }
    
        // 同友会社用 SQL生成
        $sql = sprintf("UPDATE %s	SET		%s
									WHERE	executiveID = '%d'
									AND		fiscal_year = '%d'
									",
                                    local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA,
                                    $db->escapeString($campaign),
                                    $db->escapeString($postArray['id']),
                                    $db->escapeString($year+$i)
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
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * writeExecutiveResult()
 * 同友会社の月毎の実績を保存する
 * @param $postArray：POSTで送られてきたパラメータ
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function writeExecutiveResult($postArray)
{
    $ret = 'success';
    
    // 指定した月からキャンペーン種別を判定
    $campaign = '';
    if (config::SUMMER_CAMPAIGN_START_MONTH <= $postArray['month'] && $postArray['month'] <= config::SUMMER_CAMPAIGN_END_MONTH) {
        $campaign = 'summer';
    } elseif (config::AUTUMN_CAMPAIGN_START_MONTH <= $postArray['month'] && $postArray['month'] <= config::AUTUMN_CAMPAIGN_END_MONTH) {
        $campaign = 'autumn';
    } elseif (config::SPRING_CAMPAIGN_START_MONTH <= $postArray['month'] && $postArray['month'] <= config::SPRING_CAMPAIGN_END_MONTH) {
        $campaign = 'spring';
    }

    // 参照テーブルの設定
    $dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA;
    
    // itemが補助種目の場合
    if (strpos($postArray['item'], ':sub_') !== false) {
        // 補助種目が何番目のものかを取り出し
        // 例：sub_1_2の場合、1がメイン種目との関連付け、2がテーブルNo
        $subItemNo = explode('_', $postArray['item']);
        
        // 参照テーブルの変更
        $dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SUBITEM.$subItemNo[2];
    }

	// itemが特別種目の場合
	if (strpos($postArray['item'], ':spitem') !== false) {
		$itemArray = explode(':', $item);	// 種目の取り出し
		$item = $itemArray[0];				// 種目の設定
		$dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SPITEM;	    // 参照テーブルの変更
    }

    // itemが協力企業の場合
    $cooperation = false;
	if (strpos($postArray['item'], 'cooperation') !== false) {
        $cooperation = true;
        $dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_COOPERATION;	// 参照テーブルを協力企業用に変更
    }
    
    // 現在時刻を取得
    $update_time = getCurrentTime();
    
    // データベースに接続
    $db = new DB(config::DB_NAME);

    // 実績値の場合のみデータベースに保存
    foreach ($postArray as $key => $value) {

        // 実績値
        if (strpos($key, '_result') === false) {
            continue;
        }
    
        $idArray = explode(':', $key);
        $eid = $idArray[0];     // 同友会社ID
        $pid = $idArray[1];     // 提携企業ID


        // SQL生成
        // 実績は計画がなければ入力できないので、UPDATEで対応可能
        if ($campaign === '' && $cooperation === false) {
            // キャンペーン期間外
            $sql = sprintf("UPDATE %s	SET		%s 			= '%f',
												update_time	= '%s'
										WHERE	fiscal_year	= '%d'
										AND		executiveID	= '%d'
										AND		partnerID	= '%d'
										",
                                        $dataTableName,
                                        $db->escapeString($postArray['month'].'_result'),               // カラム名
                                        $db->escapeString(convertDoubleByteChar($postArray[$key])),     // 実績値
                                        $db->escapeString($update_time),
                                        $db->escapeString($postArray['fiscal_year']),
                                        $db->escapeString($eid),
                                        $db->escapeString($pid));
        } 
        else if ($cooperation) {
            // 協力企業の実績入力の場合
            $sql = sprintf("UPDATE %s	SET		%s 			= '%f',
                                                %s 			= '%f',
                                                %s 			= '%f',
												update_time	= '%s'
										WHERE	fiscal_year	= '%d'
										AND		executiveID	= '%d'
										AND		partnerID	= '%d'
										",
                                        $dataTableName,
                                        $db->escapeString($postArray['month'].'_plan'),             // カラム名
                                        $db->escapeString(convertDoubleByteChar($postArray[$eid.':'.$pid.':'.$postArray['month'].'_plan'])),    // 同友取引実績
                                        $db->escapeString($postArray['month'].'_result'),           // カラム名
                                        $db->escapeString(convertDoubleByteChar($postArray[$eid.':'.$pid.':'.$postArray['month'].'_result'])),  // 同友手数料
                                        $db->escapeString($postArray['month'].'_shibu'),            // カラム名
                                        $db->escapeString(convertDoubleByteChar($postArray[$eid.':'.$pid.':'.$postArray['month'].'_shibu'])),   // 支部手数料
                                        $db->escapeString($update_time),
                                        $db->escapeString($postArray['fiscal_year']),
                                        $db->escapeString($eid),
                                        $db->escapeString($pid));
        }
        else {
            // キャンペーン期間中
            $sql = sprintf("UPDATE %s	SET		%s 			= '%f',
												update_time	= '%s',
												%s			= '%s'
										WHERE	fiscal_year	= '%d'
										AND		executiveID	= '%d'
										AND		partnerID	= '%d'
										",
                                        $dataTableName,
                                        $db->escapeString($postArray['month'].'_result'),           // カラム名
                                        $db->escapeString(convertDoubleByteChar($postArray[$key])),     // 実績値
                                        $db->escapeString($update_time),
                                        $db->escapeString($campaign.'_update_time'),
                                        $db->escapeString($update_time),
                                        $db->escapeString($postArray['fiscal_year']),
                                        $db->escapeString($eid),
                                        $db->escapeString($pid));
        }
    
        // SQL実行
        //echo $sql.'<br />';
        $ret = $db->exec($sql);
    }
    
    // データベースクローズ
    $db->close();

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * writeExecutivePlan()
 * 同友会社の月毎の計画を保存する
 * @param $postArray：POSTで送られてきたパラメータ
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function writeExecutivePlan($postArray)
{
    
    $ret = 'success';
    
    // データベースに接続
    $db = new DB(config::DB_NAME);

    // 実績値の場合のみデータベースに保存
    foreach ($postArray as $key => $value) {
        if (strpos($key, '_plan') === false) {
            continue;
        }
    
        $idArray = explode(':', $key);
        $eid = $idArray[0];     // 同友会社ID
        $pid = $idArray[1];     // 提携企業ID

        // SQL生成
        // 実績は計画がなければ入力できないので、UPDATEで対応可能
        $sql = sprintf("UPDATE %s	SET		%s 			= '%f'
									WHERE	fiscal_year	= '%d'
									AND		executiveID	= '%d'
									AND		partnerID	= '%d'
									",
                                    local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA,
                                    $db->escapeString($postArray['month'].'_plan'),         // カラム名
                                    $db->escapeString(convertDoubleByteChar($value)),   // 実績値
                                    $db->escapeString($postArray['fiscal_year']),
                                    $db->escapeString($eid),
                                    $db->escapeString($pid)
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
    } else {

        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * writeExecutiveCampaignPlan()
 * 同友会社のキャンペーン毎の計画値を保存する
 * @param $postArray：POSTで送られてきたパラメータ
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function writeExecutiveCampaignPlan($postArray)
{
    
    $ret = 'success';
    
    // データベースに接続
    $db = new DB(config::DB_NAME);

    // 実績値の場合のみデータベースに保存
    foreach ($postArray as $key => $value) {
        if (strpos($key, 'plan') === false) {
            continue;
        }
    
        $idArray = explode(':', $key);
        $eid = $idArray[0];     // 同友ID
        $pid = $idArray[1];     // 提携企業ID

        // SQL生成
        // 実績は計画がなければ入力できないので、UPDATEで対応可能
        $sql = sprintf("UPDATE %s	SET		%s 			= '%f'
									WHERE	fiscal_year	= '%d'
									AND		executiveID	= '%d'
									AND		partnerID	= '%d'
									",
                                    local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA,
                                    $db->escapeString($postArray['campaign'].'_plan'),  // カラム名
                                    $db->escapeString(convertDoubleByteChar($value)),   // 計画値
                                    $db->escapeString($postArray['fiscal_year']),
                                    $db->escapeString($eid),
                                    $db->escapeString($pid)
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
    } else {

        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');
        
        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * writeDataLockInfo()
 * 提携企業の実績締めを行う
 * @param $postArray：POSTで送られてきたパラメータ
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function writeDataLockInfo($postArray)
{
    $ret = 'success';
    $lock = 0;
    
    // ロック
    if ($postArray['lock'] === 'lock') {
        $lock = 1;
    }
    
    // データベースに接続
    $db = new DB(config::DB_NAME);

    // SQL生成
    // 実績締めは計画がなければ入力できないので、UPDATEで対応可能
    $sql = sprintf("UPDATE %s	SET		%s 			= '%d'
								WHERE	fiscal_year	= '%d'
								AND		partnerID	= '%d'
								",
                                local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA,
                                $db->escapeString($postArray['month'].'_lock'),     // カラム名
                                $db->escapeString($lock),                       // 0:アンロック 1:ロック
                                $db->escapeString($postArray['fiscal_year']),
                                $db->escapeString($postArray['partner'])
                                );

    // SQL実行
    //echo $sql.'<br />';
    $ret = $db->exec($sql);
    
    // データベースクローズ
    $db->close();

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * writeExecutiveMinTargetInfo()
 * 同友最低販売基準値の保存
 * @param $postArray：POSTで送られてきたパラメータ
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function writeExecutiveMinTargetInfo($postArray)
{
	$ret = 'success';

	//printArray($postArray);

    // データベースに接続
	$db = new DB(config::DB_NAME);

	// 同友最低販売基準値のデータ作成
	$str = "";
	foreach ($postArray as $key => $value) {
		if (strpos($key, 'min_target') === false) {
			continue;
		}

		$idArray = explode(':', $key);
		$item = $idArray[0];	// 種目
	
		// 書き込み用の文字列を生成 （種目,値;種目,値;種目,値・・・）
		$str = $str.$item.':'.$value.'-';
    }
    
    // もし書くものがなければ0を入れておく
    if ($str === "") {
        $str = 0;
    }

	// データベースのユニークキーの作成
	$key = 'min_target_'.$postArray['fiscal_year'];

	//DBGMSG('str:'.$str);
	//DBGMSG('key:'.$key);

	//------------------------------------------------
	// データベースにキーが存在するかどうか確認
	//------------------------------------------------
	$sql = 'SELECT * FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "'.$key.'"';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

    // データ取得
    $result = "";
	while ($data = $db->getData()) {
		$result = $data['value'];
	}

	//------------------------------------------------
	// データベースにデータが入っていない場合はINSERT、
	// 入っている場合はUPDATE
	//------------------------------------------------
	if ( strlen($result) == 0) {
		//DBGMSG('INSERT');
		$sql = sprintf("INSERT INTO %s (item,
										value) 
										VALUES ('%s','%s')",
										local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION,
										$db->escapeString($key),
										$db->escapeString($str)
										);
	}
	else {
		//DBGMSG('UPDATE');
		$sql = sprintf("UPDATE %s	SET		value 	= '%s'
									WHERE	item	= '%s'
									",
									local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION,
									$db->escapeString($str),
									$db->escapeString($key)
									);
	}
	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * writeExecutivePromotionInfo()
 * 同友への販促費の保存
 * @param $postArray：POSTで送られてきたパラメータ
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function writeExecutivePromotionInfo($postArray)
{
	$ret = 'success';

	//printArray($postArray); // DEBUG

	// 保存用データ作成
	$str = "";
	foreach ($postArray as $key => $value) {
		if (strpos($key, 'ryoritsuE') === false) {
			continue;
		}

		$idArray = explode(':', $key);
		$item = $idArray[0];	// 種目
	
		// 書き込み用の文字列を生成 （種目,値;種目,値;種目,値・・・）
		$str = $str.$item.':'.$value.'-';
    }

    // もし書くものがなければ0を入れておく
    if ($str === "") {
        $str = 0;
    }

	// データベースのユニークキーの作成
	$key = 'excutive_promotion_'.$postArray['fiscal_year'];

	//DBGMSG('str:'.$str);
    //DBGMSG('key:'.$key);

    // データベースに接続
    $db = new DB(config::DB_NAME);

	//------------------------------------------------
	// データベースにキーが存在するかどうか確認
	//------------------------------------------------
	$sql = 'SELECT * FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "'.$key.'"';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

    // データ取得
    $result = "";
	while ($data = $db->getData()) {
		$result = $data['value'];
	}

	//------------------------------------------------
	// データベースにデータが入っていない場合はINSERT、
	// 入っている場合はUPDATE
	//------------------------------------------------
	if ( strlen($result) == 0) {
		//DBGMSG('INSERT');
		$sql = sprintf("INSERT INTO %s (item,
										value) 
										VALUES ('%s','%s')",
										local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION,
										$db->escapeString($key),
										$db->escapeString($str)
										);
	}
	else {
		//DBGMSG('UPDATE');
		$sql = sprintf("UPDATE %s	SET		value 	= '%s'
									WHERE	item	= '%s'
									",
									local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION,
									$db->escapeString($str),
									$db->escapeString($key)
									);
	}
	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * writeBranchPromotionInfo()
 * 支部への販促費の保存(※料率関連)
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $setting_type：保存する設定値種別
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function writeBranchPromotionInfo($postArray, $setting_type)
{
	$ret = 'success';

	//printArray($postArray); // DEBUG

	// 保存用データ作成
	$str = "";
	foreach ($postArray as $key => $value) {
        // 設定値以外は飛ばす
		if (strpos($key, $setting_type) === false) {
			continue;
		}

		$idArray = explode(':', $key);
		$item = $idArray[0];	// 種目
	
		// 書き込み用の文字列を生成 （種目,値;種目,値;種目,値・・・）
		$str = $str.$item.':'.$value.'-';
    }

    // もし書くものがなければ0を入れておく
    if ($str === "") {
        $str = 0;
    }

    // データベースのユニークキーの作成
	$key = $setting_type.'_'.$postArray['fiscal_year'];

	//DBGMSG('str:'.$str);
    //DBGMSG('key:'.$key);

    // データベースに接続
    $db = new DB(config::DB_NAME);

	//------------------------------------------------
	// データベースにキーが存在するかどうか確認
	//------------------------------------------------
	$sql = 'SELECT * FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "'.$key.'"';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

    // データ取得
    $result = "";
	while ($data = $db->getData()) {
		$result = $data['value'];
	}

	//------------------------------------------------
	// データベースにデータが入っていない場合はINSERT、
	// 入っている場合はUPDATE
	//------------------------------------------------
	if ( strlen($result) == 0) {
		//DBGMSG('INSERT');
		$sql = sprintf("INSERT INTO %s (item,
										value) 
										VALUES ('%s','%s')",
										local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION,
										$db->escapeString($key),
										$db->escapeString($str)
										);
	}
	else {
		//DBGMSG('UPDATE');
		$sql = sprintf("UPDATE %s	SET		value 	= '%s'
									WHERE	item	= '%s'
									",
									local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION,
									$db->escapeString($str),
									$db->escapeString($key)
									);
	}
	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * writeOtherBranchPromotionInfo()
 * 販売施策のLOボーナス賞金、LH自動車特別賞、生産性＆ボリューム報奨金に関する設定を保存
 * @param $postArray：POSTで送られてきたパラメータ
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function writeOtherBranchPromotionInfo($postArray)
{
	$ret = 'success';

	//printArray($postArray); // DEBUG

	// 保存用データ作成
    $str = $postArray['lo_year_prize'];
    $str = $str.':'.$postArray['lh_summer_prize'].':'.$postArray['lh_autumn_prize'].':'.$postArray['lh_spring_prize'].':'.$postArray['lh_doyu_kaisyo_prize'];
    $str = $str.':'.$postArray['lms_seisan_prize_1'].':'.$postArray['lt_seisan_prize_1'].':'.$postArray['lh_seisan_prize_1'];
    $str = $str.':'.$postArray['lo_seisan_prize_1'].':'.$postArray['le_seisan_prize_1'].':'.$postArray['ll_seisan_prize_1'];
    $str = $str.':'.$postArray['lms_seisan_prize_2'].':'.$postArray['lt_seisan_prize_2'].':'.$postArray['lh_seisan_prize_2'];
    $str = $str.':'.$postArray['lo_seisan_prize_2'].':'.$postArray['le_seisan_prize_2'].':'.$postArray['ll_seisan_prize_2'];
    $str = $str.':'.$postArray['lms_volume_prize_1'].':'.$postArray['lt_volume_prize_1'].':'.$postArray['lh_volume_prize_1'];
    $str = $str.':'.$postArray['lo_volume_prize_1'].':'.$postArray['le_volume_prize_1'].':'.$postArray['ll_volume_prize_1'];
    $str = $str.':'.$postArray['lms_volume_prize_2'].':'.$postArray['lt_volume_prize_2'].':'.$postArray['lh_volume_prize_2'];
    $str = $str.':'.$postArray['lo_volume_prize_2'].':'.$postArray['le_volume_prize_2'].':'.$postArray['ll_volume_prize_2'];
    $str = $str.':'.$postArray['min_target_clear_prize'];
    $str = $str.':'.$postArray['lm_bonus_prize_1_half'].':'.$postArray['lm_bonus_prize_2_half'].':'.$postArray['ls_bonus_prize_1_half'].':'.$postArray['ls_bonus_prize_2_half'];
    $str = $str.':'.$postArray['lms_special_prize_summer'].':'.$postArray['lt_special_prize_summer'].':'.$postArray['lo_special_prize_summer'].':'.$postArray['le_special_prize_summer'].':'.$postArray['ll_special_prize_summer'];
    $str = $str.':'.$postArray['lms_special_prize_autumn'].':'.$postArray['lt_special_prize_autumn'].':'.$postArray['lo_special_prize_autumn'].':'.$postArray['le_special_prize_autumn'].':'.$postArray['ll_special_prize_autumn'];
    $str = $str.':'.$postArray['lms_special_prize_spring'].':'.$postArray['lt_special_prize_spring'].':'.$postArray['lo_special_prize_spring'].':'.$postArray['le_special_prize_spring'].':'.$postArray['ll_special_prize_spring'];
    $str = $str.':'.$postArray['LO_proshop_target'].':'.$postArray['LO_proshop_target2'].':'.$postArray['LO_proshop_msg'];
    $str = $str.':'.$postArray['president_prize'];

	// データベースのユニークキーの作成
	$key = 'other_prize_'.$postArray['fiscal_year'];

	//DBGMSG('str:'.$str);
    //DBGMSG('key:'.$key);

    // データベースに接続
    $db = new DB(config::DB_NAME);

	//------------------------------------------------
	// データベースにキーが存在するかどうか確認
	//------------------------------------------------
	$sql = 'SELECT * FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "'.$key.'"';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

    // データ取得
    $result = "";
	while ($data = $db->getData()) {
		$result = $data['value'];
	}

	//------------------------------------------------
	// データベースにデータが入っていない場合はINSERT、
	// 入っている場合はUPDATE
	//------------------------------------------------
	if ( strlen($result) == 0) {
		//DBGMSG('INSERT');
		$sql = sprintf("INSERT INTO %s (item,
										value) 
										VALUES ('%s','%s')",
										local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION,
										$db->escapeString($key),
										$db->escapeString($str)
										);
	}
	else {
		//DBGMSG('UPDATE');
		$sql = sprintf("UPDATE %s	SET		value 	= '%s'
									WHERE	item	= '%s'
									",
									local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION,
									$db->escapeString($str),
									$db->escapeString($key)
									);
	}
	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * writeLCHoldPromotionInfo()
 * LC獲得推進費と、LC保有支援金を保存
 * @param $postArray：POSTで送られてきたパラメータ
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function writeLCHoldPromotionInfo($postArray)
{
	$ret = 'success';

	//printArray($postArray); // DEBUG

	// 保存用データ作成
    $str = $postArray['lc_year_target_count'];
    $str = $str.':'.$postArray['lc_get_promotion_cost'].':'.$postArray['lc_hold_support_cost'];
    $str = $str.':'.$postArray['lc_get_promotion_count'];
    $str = $str.':'.$postArray['lc_get_item_count_1'].':'.$postArray['lc_get_item_prize_1'];
    $str = $str.':'.$postArray['lc_get_item_count_2'].':'.$postArray['lc_get_item_prize_2'];
    $str = $str.':'.$postArray['lc_get_promotion_prize'];
    $str = $str.':'.$postArray['lc_hold_support_count'];

	// データベースのユニークキーの作成
	$key = 'lc_hold_number_'.$postArray['fiscal_year'];

	//DBGMSG('str:'.$str);
    //DBGMSG('key:'.$key);

    // データベースに接続
    $db = new DB(config::DB_NAME);

	//------------------------------------------------
	// データベースにキーが存在するかどうか確認
	//------------------------------------------------
	$sql = 'SELECT * FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "'.$key.'"';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

    // データ取得
    $result = "";
	while ($data = $db->getData()) {
		$result = $data['value'];
	}

	//------------------------------------------------
	// データベースにデータが入っていない場合はINSERT、
	// 入っている場合はUPDATE
	//------------------------------------------------
	if ( strlen($result) == 0) {
		//DBGMSG('INSERT');
		$sql = sprintf("INSERT INTO %s (item,
										value) 
										VALUES ('%s','%s')",
										local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION,
										$db->escapeString($key),
										$db->escapeString($str)
										);
	}
	else {
		//DBGMSG('UPDATE');
		$sql = sprintf("UPDATE %s	SET		value 	= '%s'
									WHERE	item	= '%s'
									",
									local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION,
									$db->escapeString($str),
									$db->escapeString($key)
									);
	}
	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * writePointStatusInfo()
 * 支部表彰得点状況の得点を保存
 * @param $postArray：POSTで送られてきたパラメータ
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function writePointStatusInfo($postArray)
{
	$ret = 'success';

    //printArray($postArray); // DEBUG

    // 保存用データ作成
    $str = "";
    foreach ($postArray as $key => $value) {
        // 提携に該当する設定値以外は飛ばす
		if (strpos($key, 'point_status_') === false) {
			continue;
        }
        if ($str === "") {
            $str = $value;
        }
        else {
            $str = $str.':'.$value;
        }
    }
    
    // 空っぽの場合ダミーデータを挿入
    if ($str === "") {
        $str = ":";
    }

	// データベースのユニークキーの作成
	$key = 'point_status_'.$postArray['fiscal_year'];

	//DBGMSG('str:'.$str);
    //DBGMSG('key:'.$key);

    // データベースに接続
    $db = new DB(config::DB_NAME);

	//------------------------------------------------
	// データベースにキーが存在するかどうか確認
	//------------------------------------------------
	$sql = 'SELECT * FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "'.$key.'"';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

    // データ取得
    $result = "";
	while ($data = $db->getData()) {
		$result = $data['value'];
	}

	//------------------------------------------------
	// データベースにデータが入っていない場合はINSERT、
	// 入っている場合はUPDATE
	//------------------------------------------------
	if (strlen($result) == 0) {
		//DBGMSG('INSERT');
		$sql = sprintf("INSERT INTO %s (item,
										value) 
										VALUES ('%s','%s')",
										local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION,
										$db->escapeString($key),
										$db->escapeString($str)
										);
	}
	else {
		//DBGMSG('UPDATE');
		$sql = sprintf("UPDATE %s	SET		value 	= '%s'
									WHERE	item	= '%s'
									",
									local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION,
									$db->escapeString($str),
									$db->escapeString($key)
									);
	}
	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * writeAllMonthPlan()
 * 指定年度の同友毎の月別全計画をデータベースに登録する
 * @param $fiscal_year：年度
 * @param $filepath：計画ファイルのパス
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function writeAllMonthPlan($fiscal_year, $filepath)
{
    $ret = 'success';
    
    // データベースに接続
    $db = new DB(config::DB_NAME);

    // 新しいエクセルファイルを作成する
    $objPHPExcel = PHPExcel_IOFactory::load($filepath);
    
    // 0番目のシートをアクティブにする（シートは左から順に、0、1，2・・・）
    $objPHPExcel->setActiveSheetIndex(0);
    
    // アクティブにしたシートの情報を取得
    $objSheet = $objPHPExcel->getActiveSheet();
    
    $cnt = 2;
    while (true) {
        $val = $objSheet->getCell('A'.$cnt)->getValue();
        
        // 要素判定
        // PASSは飛ばす、ENDは終了、それ以外は「種目:提携企業ID：同友ID」
        if ($val === 'END') {
            break;
        } elseif ($val === 'PASS') {
            $cnt++;
            continue;
        } else {
            $valArray = explode(':', $val);
            if (count($valArray) == 3) {
                $item = $valArray[0];   // 種目
                $pid  = $valArray[1];   // 提携企業ID
                $eid  = $valArray[2];   // 同友ID
                
                // SQL生成
                // 計画入力ができるということはテーブルがあるはずなのでUPDATEで対応可能
                $sql = sprintf("UPDATE %s	SET		4_plan  = '%f',
													5_plan  = '%f',
													6_plan  = '%f',
													7_plan  = '%f',
													8_plan  = '%f',
													9_plan  = '%f',
													10_plan = '%f',
													11_plan = '%f',
													12_plan = '%f',
													1_plan  = '%f',
													2_plan  = '%f',
													3_plan  = '%f'
										WHERE	fiscal_year	= '%d'
										AND		partnerID	= '%d'
										AND		executiveID	= '%d'
										",
                                local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA,
                                $db->escapeString($objSheet->getCell('E'.$cnt)->getValue()),
                                $db->escapeString($objSheet->getCell('F'.$cnt)->getValue()),
                                $db->escapeString($objSheet->getCell('G'.$cnt)->getValue()),
                                $db->escapeString($objSheet->getCell('H'.$cnt)->getValue()),
                                $db->escapeString($objSheet->getCell('I'.$cnt)->getValue()),
                                $db->escapeString($objSheet->getCell('J'.$cnt)->getValue()),
                                $db->escapeString($objSheet->getCell('K'.$cnt)->getValue()),
                                $db->escapeString($objSheet->getCell('L'.$cnt)->getValue()),
                                $db->escapeString($objSheet->getCell('M'.$cnt)->getValue()),
                                $db->escapeString($objSheet->getCell('N'.$cnt)->getValue()),
                                $db->escapeString($objSheet->getCell('O'.$cnt)->getValue()),
                                $db->escapeString($objSheet->getCell('P'.$cnt)->getValue()),
                                $db->escapeString($fiscal_year),
                                $db->escapeString($pid),
                                $db->escapeString($eid)
                                );

                // SQL実行
                //echo $sql.'<br />';
                $ret = $db->exec($sql);
            } else {
                // データベースクローズ
                $db->close();
                
                return 'input_file_error';
            }
            $cnt++;
        }
    };

    // データベースクローズ
    $db->close();

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * createData()
 * 指定年度のデータを作成する
 * @param $fiscal_year：年度
 * @return boolean $ret success：成功
 *                その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function createData($fiscal_year)
{
    $ret = 'success';
    
    // ユーザ情報から提携企業一覧を取得
    $partnerList = getPartnerList();
    if (count($partnerList) == 0) {
        return 'no_partner';
    }

    // ユーザ情報から同友会社一覧を取得
    $executiveList = getExecutiveList(0, '', '%', 'all');
    if (count($executiveList) == 0) {
        return 'no_executive';
    }
    
    // データベースに接続
    $db = new DB(config::DB_NAME);
        
    //----------------------------
    // メイン種目のテーブル生成
    //----------------------------
    // 全提携企業、全同友会社の指定年度のデータテーブルを生成
    foreach ($partnerList as $partnerArray) {
        foreach ($executiveList as $executiveArray) {
            // 既にテーブルがあるかどうかを判定
            // 無い場合は追加
            $data = getResultDataInfo($fiscal_year, $executiveArray['value'], $partnerArray['value']);
            if (count($data) != 0) {
                // データがある場合は作成しない
                continue;
            }

            $sql = sprintf("INSERT INTO %s (fiscal_year,
											executiveID,
											partnerID) 
											VALUES ('%d','%d','%d')",
                                            local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA,
                                            $db->escapeString($fiscal_year),
                                            $db->escapeString($executiveArray['value']),
                                            $db->escapeString($partnerArray['value'])
                                            );
            
            // SQL実行
            //echo $sql.'<br />';
            $ret = $db->exec($sql);
            if ($ret === 'failed') {
                $db->close();
                return $ret;
            }
        }
    }
    
    //----------------------------
    // 補助種目のテーブル生成
    //----------------------------
    // 補助種目一覧を取得し、補助種目が存在する場合のみテーブルを生成
    $subitemList = getSubItemList('no-insert');
    foreach ($subitemList as $subitemArray) {
        // 補助種目に対応している提携企業を取得
        $partnerList = getPartnerList($fiscal_year, $subitemArray['mainitem']);
        
        // 補助種目を持つ提携企業数分のテーブルを生成
        foreach ($partnerList as $partnerArray) {
            foreach ($executiveList as $executiveArray) {
                // 既にテーブルがあるかどうかを判定
                // 無い場合は追加
                $data = getResultDataInfo($fiscal_year, $executiveArray['value'], $partnerArray['value'], $subitemArray['value']);
                if (count($data) != 0) {
                    // データがある場合は作成しない
                    continue;
                }
                
                $subItemNo = explode('_', $subitemArray['value']);  // 補助種目が何番目のものかを取り出し(例：sub_1_2の場合、1がメイン種目との関連付け、2がテーブルNo)
        
                // 参照テーブルの変更
                $dataTableName = local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SUBITEM.$subItemNo[2];
    
                $sql = sprintf("INSERT INTO %s (fiscal_year,
												executiveID,
												partnerID) 
												VALUES ('%d','%d','%d')",
                                                $dataTableName,
                                                $db->escapeString($fiscal_year),
                                                $db->escapeString($executiveArray['value']),
                                                $db->escapeString($partnerArray['value'])
                                                );
                
                // SQL実行
                //echo $sql.'<br />';
                $ret = $db->exec($sql);
                if ($ret === 'failed') {
                    $db->close();
                    return $ret;
                }
            }
        }
    }

    //----------------------------
    // 特殊種目のテーブル生成
    //----------------------------
    // 特別種目一覧を取得
    $spitemList = getSpecialItemList();
    foreach ($spitemList as $spitemArray) {

        $itemArray = explode(':', $spitemArray['value']);

        // 種目に対応している提携企業を取得
        $partnerList = getPartnerList($fiscal_year, $itemArray[0]);
        
        // 補助種目を持つ提携企業数分のテーブルを生成
        foreach ($partnerList as $partnerArray) {
            foreach ($executiveList as $executiveArray) {
                // 既にテーブルがあるかどうかを判定
                // 無い場合は追加
                $data = getResultDataInfo($fiscal_year, $executiveArray['value'], $partnerArray['value'], $spitemArray['value']);
                if (count($data) != 0) {
                    // データがある場合は作成しない
                    continue;
                }
    
                $sql = sprintf("INSERT INTO %s (fiscal_year,
												executiveID,
												partnerID) 
												VALUES ('%d','%d','%d')",
                                                local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SPITEM,
                                                $db->escapeString($fiscal_year),
                                                $db->escapeString($executiveArray['value']),
                                                $db->escapeString($partnerArray['value'])
                                                );
                
                // SQL実行
                //echo $sql.'<br />';
                $ret = $db->exec($sql);
                if ($ret === 'failed') {
                    $db->close();
                    return $ret;
                }
            }
        }
    }

    //----------------------------
    // 協力企業のテーブル生成
    //----------------------------
    // 協力企業一覧を取得
    $partnerList = getPartnerList(0, 'cooperation');

    // 全提携企業、全同友会社の指定年度のデータテーブルを生成
    foreach ($partnerList as $partnerArray) {
        foreach ($executiveList as $executiveArray) {
            // 既にテーブルがあるかどうかを判定
            // 無い場合は追加
            $data = getResultDataInfo($fiscal_year, $executiveArray['value'], $partnerArray['value'],  'cooperation');
            if (count($data) != 0) {
                // データがある場合は作成しない
                continue;
            }

            $sql = sprintf("INSERT INTO %s (fiscal_year,
											executiveID,
											partnerID) 
											VALUES ('%d','%d','%d')",
                                            local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_COOPERATION,
                                            $db->escapeString($fiscal_year),
                                            $db->escapeString($executiveArray['value']),
                                            $db->escapeString($partnerArray['value'])
                                            );
            
            // SQL実行
            //echo $sql.'<br />';
            $ret = $db->exec($sql);
            if ($ret === 'failed') {
                $db->close();
                return $ret;
            }
        }
    }

    // データベースクローズ
    $db->close();

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * deleteData()
 * 指定年度のデータを削除する
 * @param $fiscal_year：年度
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function deleteData($fiscal_year)
{

    $ret = 'success';

    // データベースに接続
    $db = new DB(config::DB_NAME);
    
    // メイン種目
    $sql = sprintf("DELETE FROM %s WHERE fiscal_year=%d",
                    local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA,
                    $db->escapeString($fiscal_year)
                    );
    // SQL実行
    //echo $sql.'<br />';
    $ret = $db->exec($sql);

    // 補助種目
    for ($i = 1; $i <= config::SUBITEM_NUM_MAX; $i++) {
        $sql = sprintf("DELETE FROM %s WHERE fiscal_year=%d",
                        local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SUBITEM.$i,
                        $db->escapeString($fiscal_year)
                        );
        // SQL実行
        //echo $sql.'<br />';
        $ret = $db->exec($sql);
    }

    // 特殊種目
    $sql = sprintf("DELETE FROM %s WHERE fiscal_year=%d",
                    local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA_SPITEM,
                    $db->escapeString($fiscal_year)
                    );
    // SQL実行
    //echo $sql.'<br />';
    $ret = $db->exec($sql);
    
    // データベースクローズ
    $db->close();

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * openData()
 * 指定年度のデータを公開する
 * @param $fiscal_year：年度
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function openData($fiscal_year)
{

    $ret = 'success';

    // データベースに接続
    $db = new DB(config::DB_NAME);

    // SQL生成
    // 公開は計画がなければ入力できないので、UPDATEで対応可能
    $sql = sprintf("UPDATE %s	SET		open 		= '%d'
								WHERE	fiscal_year	= '%d'
								",
                                local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA,
                                $db->escapeString(config::STATUS_DATA_OPEN),
                                $db->escapeString($fiscal_year)
                                );

    // SQL実行
    //echo $sql.'<br />';
    $ret = $db->exec($sql);
    
    // データベースクローズ
    $db->close();

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * closeData()
 * 指定年度のデータを非公開する
 * @param $fiscal_year：年度
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function closeData($fiscal_year)
{

    $ret = 'success';

    // データベースに接続
    $db = new DB(config::DB_NAME);

    // SQL生成
    // 非公開は計画がなければ入力できないので、UPDATEで対応可能
    $sql = sprintf("UPDATE %s	SET		open 		= '%d'
								WHERE	fiscal_year	= '%d'
								",
                                local_config::$DB_TABLE_PREFIX.config::DB_TABLE_DATA,
                                $db->escapeString(config::STATUS_DATA_CLOSE),
                                $db->escapeString($fiscal_year)
                                );

    // SQL実行
    //echo $sql.'<br />';
    $ret = $db->exec($sql);
    
    // データベースクローズ
    $db->close();

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * createCampaignData()
 * 指定年度のキャンペーンデータを作成する
 * @param $fiscal_year：年度
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function createCampaignData($fiscal_year)
{

    $ret = 'success';
    
    // 全種目を取得
    $itemList = getItemList('no-insert');
    if (count($itemList) == 0) {
        return 'no_item';
    }
    
    // ユーザ情報から退会していない提携企業一覧を取得
    $partnerList = getPartnerList();
    if (count($partnerList) == 0) {
        return 'no_partner';
    }

    // ユーザ情報から退会していない同友会社一覧を取得(生産性の計算では年度の1月以降に脱退した同友も含めてキャンペーンのデータは作成)
    $executiveList = getExecutiveList($fiscal_year, '', '%', 'entry');
    if (count($executiveList) == 0) {
        return 'no_executive';
    }
    //printArray($executiveList);
    
    // データベースに接続
    $db = new DB(config::DB_NAME);

    //----------------------------------
    // キャンペーン同友用データ作成
    //----------------------------------
    // 全提携企業、全同友会社の指定年度のデータテーブルを生成
    foreach ($partnerList as $partnerArray) {
        foreach ($executiveList as $executiveArray) {
            // 既にテーブルがあるかどうかを判定
            // 無い場合は追加
            $data = getCampaignDataInfo($fiscal_year, $executiveArray['value'], $partnerArray['value']);
            if (strlen($data['fiscal_year']) > 0) {
                // データがある場合は追加はしない
                continue;
            }

            $sql = sprintf("INSERT INTO %s (fiscal_year,
											executiveID,
											partnerID,
											summer_enterable,
											autumn_enterable,
											spring_enterable) 
											VALUES ('%d','%d','%d','%d','%d','%d')",
                                            local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA,
                                            $db->escapeString($fiscal_year),
                                            $db->escapeString($executiveArray['value']),
                                            $db->escapeString($partnerArray['value']),
                                            $db->escapeString($executiveArray['enterable']),
                                            $db->escapeString($executiveArray['enterable']),
                                            $db->escapeString($executiveArray['enterable'])
                                            );
            
            // SQL実行
            //echo $sql.'<br />';
            $ret = $db->exec($sql);
            if ($ret === 'failed') {
                $db->close();
                return $ret;
            }
        }
    }

    //----------------------------------
    // キャンペーン本部用データ作成
    //----------------------------------
    // 全種目分を生成
    foreach ($itemList as $itemArray) {
        // 既にテーブルがあるかどうかを判定
        $data = getCampaignInfo($fiscal_year, $itemArray['value']);
        if (strlen($data['fiscal_year']) > 0) {
            // データがある場合は作成しない
            continue;
        }
        
        $sql = sprintf("INSERT INTO %s (fiscal_year,
										item) 
										VALUES ('%d','%s')",
                                        local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN,
                                        $db->escapeString($fiscal_year),
                                        $db->escapeString($itemArray['value'])
                                        );
        
        // SQL実行
        //echo $sql.'<br />';
        $ret = $db->exec($sql);
        if ($ret === 'failed') {
            $db->close();
            return $ret;
        }
    }

    sleep(3);

    // データベースクローズ
    $db->close();

    // エクセルアップデート用のファイルのタイムスタンプを更新する
    updateTimestampFile('./tmp/update_timestamp.txt');

    // 戻り値
    return 'success';
}

/**
 * ----------------------------------------------------------
 * deleteCampaignData()
 * 指定年度のデータを削除する
 * @param $fiscal_year：年度
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function deleteCampaignData($fiscal_year)
{

    $ret = 'success';

    // データベースに接続
    $db = new DB(config::DB_NAME);
    
    //----------------------------------
    // キャンペーン同友用データ削除
    //----------------------------------
    $sql = sprintf("DELETE FROM %s WHERE fiscal_year=%d",
                    local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA,
                    $db->escapeString($fiscal_year)
                    );
    // SQL実行
    //echo $sql.'<br />';
    $ret = $db->exec($sql);

    //----------------------------------
    // キャンペーン本部用データ削除
    //----------------------------------
    $sql = sprintf("DELETE FROM %s WHERE fiscal_year=%d",
                    local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN,
                    $db->escapeString($fiscal_year)
                    );

    //----------------------------------
    // キャンペーン用グラフ削除
    //----------------------------------
    $sql = sprintf("DELETE FROM %s WHERE fiscal_year=%d",
                    local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_GRAPH,
                    $db->escapeString($fiscal_year)
                    );
                    
    // SQL実行
    //echo $sql.'<br />';
    $ret = $db->exec($sql);
    
    // データベースクローズ
    $db->close();

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * openCampaignData()
 * 指定年度のデータを公開する
 * @param $fiscal_year：年度
 * @param $campaign：キャンペーン種別
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function openCampaignData($fiscal_year, $campaign)
{

    $ret = 'success';
    $column = '';

    // キャンペーン種別判定
    switch ($campaign) {
        case 'summer':
            $column = 'summer_open';
            break;
        case 'autumn':
            $column = 'autumn_open';
            break;
        case 'spring':
            $column = 'spring_open';
            break;
        default:
            return 'failed';
    }

    // データベースに接続
    $db = new DB(config::DB_NAME);

    // SQL生成
    // 公開は計画がなければ入力できないので、UPDATEで対応可能
    $sql = sprintf("UPDATE %s	SET		%s 			= '%d'
								WHERE	fiscal_year	= '%d'
								",
                                local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA,
                                $column,
                                $db->escapeString(config::STATUS_DATA_OPEN),
                                $db->escapeString($fiscal_year)
                                );

    // SQL実行
    //echo $sql.'<br />';
    $ret = $db->exec($sql);
    
    // データベースクローズ
    $db->close();

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * closeCampaignData()
 * 指定年度のデータを非公開する
 * @param $fiscal_year：年度
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function closeCampaignData($fiscal_year, $campaign)
{

    $ret = 'success';
    $column = '';

    // キャンペーン種別判定
    switch ($campaign) {
        case 'summer':
            $column = 'summer_open';
            break;
        case 'autumn':
            $column = 'autumn_open';
            break;
        case 'spring':
            $column = 'spring_open';
            break;
        default:
            return 'failed';
    }

    // データベースに接続
    $db = new DB(config::DB_NAME);

    // SQL生成
    // 公開は計画がなければ入力できないので、UPDATEで対応可能
    $sql = sprintf("UPDATE %s	SET		%s 			= '%d'
								WHERE	fiscal_year	= '%d'
								",
                                local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA,
                                $column,
                                $db->escapeString(config::STATUS_DATA_CLOSE),
                                $db->escapeString($fiscal_year)
                                );

    // SQL実行
    //echo $sql.'<br />';
    $ret = $db->exec($sql);
    
    // データベースクローズ
    $db->close();

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * updateCampaignInfo()
 * キャンペーンの内容を更新する
 * @param $postArray：POSTで送られてきたデータ
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function updateCampaignInfo($postArray)
{
    
    $ret = 'success';
    $columnNameAve  = '';
    $columnNamePlan = '';

    // キャンペーン種別判定
    switch ($postArray['campaign']) {
        case 'summer':
            $columnNameAve  = 'summer_ave';
            $columnNamePlan = 'summer_plan';
            $columnEnterableUnder = 'summer_enterable_under';
            $columnEnterableUpper = 'summer_enterable_upper';
            $columnTarget = 'summer_target';
            $columnOpt = 'summer_opt';
            break;
        case 'autumn':
            $columnNameAve  = 'autumn_ave';
            $columnNamePlan = 'autumn_plan';
            $columnEnterableUnder = 'autumn_enterable_under';
            $columnEnterableUpper = 'autumn_enterable_upper';
            $columnTarget = 'autumn_target';
            $columnOpt = 'autumn_opt';
            break;
        case 'spring':
            $columnNameAve  = 'spring_ave';
            $columnNamePlan = 'spring_plan';
            $columnEnterableUnder = 'spring_enterable_under';
            $columnEnterableUpper = 'spring_enterable_upper';
            $columnTarget = 'spring_target';
            $columnOpt = 'spring_opt';
            break;
        default:
            return 'failed';
    }

    // 「地区対抗戦 達成率賞」対応
    if (local_config::FEATURE_AREA_REACH_AWARD) {
        $opt = 'areaItemAward,'.$postArray['areaItemAward1'].','.$postArray['areaItemAward2'].','.$postArray['areaItemAward3'].'-';
        $opt = $opt.'areaTotalAward,'.$postArray['areaTotalAward1'].','.$postArray['areaTotalAward2'].','.$postArray['areaTotalAward3'].'-';
        if (isset($postArray['areaAwardCheck'])) {
            $opt = $opt.'areaAwardCheck,1';
        } else {
            $opt = $opt.'areaAwardCheck,0';
        }
        $opt = $opt.'-areaMustLCCnt,'.$postArray['areaMustLCCnt'].'-areaMustLCCntforAward,'.$postArray['areaMustLCCntforAward'];
    } else {
        $opt = '';
    }
    
    // 全種目を取得（LM+LS対策：年度を指定）
    $itemList = getItemList('no-insert', '', $postArray['fiscal_year'], true, true, true);
    if (count($itemList) == 0) {
        return 'no_item';
    }

    // データベースに接続
    $db = new DB(config::DB_NAME);

    //-------------------------------------
    // キャンペーンデータの更新
    //-------------------------------------
    // 全種目分を生成
    foreach ($itemList as $itemArray) {
        // 種目を設定
        $item = $itemArray['value'];

        // LM+LS対策：LM+LSはLMとして扱う
        if ($itemArray['value'] === 'LM+LS') {
            $item = 'LM';
        }

        // SQL生成
        // 公開は計画がなければ入力できないので、UPDATEで対応可能
        $sql = sprintf("UPDATE %s	SET		%s 			= '%f',
											%s 			= '%f',
											%s 			= '%d',
											%s 			= '%d',
											%s 			= '%d',
											%s			= '%s'
									WHERE	fiscal_year	= '%d'
									AND		item like 	  '%s'
									",
                                    local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN,
                                    $columnNameAve,
                                    $db->escapeString(convertDoubleByteChar($postArray[$item.'_ave'])),
                                    $columnNamePlan,
                                    $db->escapeString(convertDoubleByteChar($postArray[$item.'_plan'])),
                                    $columnEnterableUnder,
                                    $db->escapeString(convertDoubleByteChar($postArray[$itemArray['value'].'_enterable_under'])),
                                    $columnEnterableUpper,
                                    $db->escapeString(convertDoubleByteChar($postArray[$itemArray['value'].'_enterable_upper'])),
                                    $columnTarget,
                                    $db->escapeString(convertDoubleByteChar($postArray[$itemArray['value'].'_target'])),
                                    $columnOpt,
                                    $db->escapeString($opt),
                                    $db->escapeString($postArray['fiscal_year']),
                                    $db->escapeString($item)
                                    );
    
        // SQL実行
        //echo $sql.'<br />';
        $ret = $db->exec($sql);
        if ($ret === 'failed') {
            return $ret;
        }
    }
    
    //-------------------------------------
    // 同友のキャンペーンデータを更新
    //-------------------------------------
    // 分母となる同友のチェックを反映
    foreach ($postArray as $key => $value) {
        // 分母同友チェックの場合
        if (strpos($key, 'enterable:') !== false) {
            $array = explode(':', $key);
            $eid = $array[1];
            $columnNameEnterable = $postArray['campaign'].'_enterable';
            
            // SQL生成
            // 公開は計画がなければ入力できないので、UPDATEで対応可能
            $sql = sprintf("UPDATE %s	SET		%s 			= '%d'
										WHERE	fiscal_year	= '%d'
										AND		executiveID = '%d'
										",
                                        local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_DATA,
                                        $columnNameEnterable,
                                        $db->escapeString($value),
                                        $db->escapeString($postArray['fiscal_year']),
                                        $db->escapeString($eid)
                                        );
            // SQL実行
            //echo $sql.'<br />';
            $ret = $db->exec($sql);
            if ($ret === 'failed') {
                return $ret;
            }
        }
    }
    
    // データベースクローズ
    $db->close();

    // エクセルアップデート用のファイルのタイムスタンプを更新する
    updateTimestampFile('./tmp/update_timestamp.txt');
    
    // 戻り値
    return 'success';
}

/**
 * ----------------------------------------------------------
 * writeCampaignGraphData()
 * キャンペーンの達成率グラフのデータを保存する
 * @param $postArray：POSTで送られてきたデータ
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function writeCampaignGraphData($postArray)
{

    $ret = 'success';
    
    // グラフ期間数を取得
    $campaignGraphData = getCampaignGraphData($postArray['fiscal_year'], $postArray['campaign']);
    if (count($campaignGraphData) >= config::CAMPAIGN_GRAPH_RANGE_MAX) {
        return 'error_graph_data_max';
    }
    
    // 日付取得
    $date = getCurrentDate();
    
    // data作成
    $data = '';
    foreach ($postArray as $key => $value) {
        if (strpos($key, 'graph:') !== false) {
            $itemArray = explode(':', $key);
            
            // 最初だけ-(ハイフン)を付けない
            if (strlen($data) > 0) {
                $data = $data.'-';
            }
            
            // 種目毎のデータを生成
            $data = $data.$itemArray[1].':'.$value;
        }
    }
    
    // データベースに接続
    $db = new DB(config::DB_NAME);

    // SQL生成
    $sql = sprintf("INSERT INTO %s (fiscal_year,
									campaign,
									date,
									data) 
									VALUES ('%d','%s','%s','%s')",
                                    local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_GRAPH,
                                    $db->escapeString($postArray['fiscal_year']),
                                    $db->escapeString($postArray['campaign']),
                                    $db->escapeString($date),
                                    $db->escapeString($data)
                                    );

    // SQL実行
    //echo $sql.'<br />';
    $ret = $db->exec($sql);
    
    // データベースクローズ
    $db->close();

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * deleteCampaignGraphData()
 * 指定年度のデータを削除する
 * @param $id：削除するキャンペーンのグラフデータID
 * @return boolean $ret success：成功
 *               その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function deleteCampaignGraphData($id)
{

    $ret = 'success';

    // データベースに接続
    $db = new DB(config::DB_NAME);
    
    $sql = sprintf("DELETE	FROM %s
					WHERE	id = %d
					",
                    local_config::$DB_TABLE_PREFIX.config::DB_TABLE_CAMPAIGN_GRAPH,
                    $db->escapeString($id)
                    );
    // SQL実行
    //echo $sql.'<br />';
    $ret = $db->exec($sql);
    
    // データベースクローズ
    $db->close();

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * writePartnerPrizeInfo()
 * 提携企業の販促費の保存
 * @param $postArray：POSTで送られてきたパラメータ
 * @param $item     ：種目
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function writePartnerPrizeInfo($postArray, $item)
{
	$ret = 'success';

	//printArray($postArray); // DEBUG

	// 保存用データ作成
    $str = "";
    $order = array(';', '|');
	foreach ($postArray as $key => $value) {

        // 保存しない項目を飛ばす
        if ($key === 'save' || $key === 'fiscal_year') {
            continue;
        }

        // 関係する種目だけを保存(keyにitemを含むものだけ保存する)
        if(strpos($key, $item) === 0) {
            $key = str_replace($order, "", $key);       // 区切り文字を削除
            $value = str_replace($order, "", $value);   // 区切り文字を削除
            $str = $str.$key.'|'.$value.';';
        }
        else {
            continue;
        }
    }
    
    // もし書くものがなければ0を入れておく
    if ($str === "") {
        $str = 0;
    }

	// データベースのユニークキーの作成
	$key = $item.'_partner_prize_'.$postArray['fiscal_year'];

	//DBGMSG('str:'.$str);
    //DBGMSG('key:'.$key);

    // データベースに接続
    $db = new DB(config::DB_NAME);

	//------------------------------------------------
	// データベースにキーが存在するかどうか確認
	//------------------------------------------------
	$sql = 'SELECT * FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "'.$key.'"';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

    // データ取得
    $result = "";
	while ($data = $db->getData()) {
		$result = $data['value'];
	}

	//------------------------------------------------
	// データベースにデータが入っていない場合はINSERT、
	// 入っている場合はUPDATE
	//------------------------------------------------
	if ( strlen($result) == 0) {
		//DBGMSG('INSERT');
		$sql = sprintf("INSERT INTO %s (item,
										value) 
										VALUES ('%s','%s')",
										local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION,
										$db->escapeString($key),
										$db->escapeString($str)
										);
	}
	else {
		//DBGMSG('UPDATE');
		$sql = sprintf("UPDATE %s	SET		value 	= '%s'
									WHERE	item	= '%s'
									",
									local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION,
									$db->escapeString($str),
									$db->escapeString($key)
									);
	}
	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * writeCooperationInfo()
 * 協力企業の設定を保存
 * @param $postArray：POSTで送られてきたパラメータ
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function writeCooperationInfo($postArray)
{
	$ret = 'success';

    //printArray($postArray); // DEBUG

	// 保存用データ作成
    $str = "";
    $order = array(';', '|');
	foreach ($postArray as $key => $value) {

        // 保存しない項目を飛ばす
        if ($key === 'save' || $key === 'command' || $key === 'fiscal_year') {
            continue;
        }

        // 関係する種目だけを保存(keyにitemを含むものだけ保存する)
        $key = str_replace($order, "", $key);       // 区切り文字を削除
        $value = str_replace($order, "", $value);   // 区切り文字を削除
        $str = $str.$key.'|'.$value.';';
    }
    
    // もし書くものがなければ0を入れておく
    if ($str === "") {
        $str = 0;
    }

	// データベースのユニークキーの作成
	$key = 'cooperation_'.$postArray['partner'].'_'.$postArray['fiscal_year'];

	//DBGMSG('str:'.$str);
    //DBGMSG('key:'.$key);

    // データベースに接続
    $db = new DB(config::DB_NAME);

	//------------------------------------------------
	// データベースにキーが存在するかどうか確認
	//------------------------------------------------
	$sql = 'SELECT * FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "'.$key.'"';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

    // データ取得
    $result = "";
	while ($data = $db->getData()) {
		$result = $data['value'];
	}

	//------------------------------------------------
	// データベースにデータが入っていない場合はINSERT、
	// 入っている場合はUPDATE
	//------------------------------------------------
	if ( strlen($result) == 0) {
		//DBGMSG('INSERT');
		$sql = sprintf("INSERT INTO %s (item,
										value) 
										VALUES ('%s','%s')",
										local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION,
										$db->escapeString($key),
										$db->escapeString($str)
										);
	}
	else {
		//DBGMSG('UPDATE');
		$sql = sprintf("UPDATE %s	SET		value 	= '%s'
									WHERE	item	= '%s'
									",
									local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION,
									$db->escapeString($str),
									$db->escapeString($key)
									);
	}
	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        // エクセルアップデート用のファイルのタイムスタンプを更新する
        updateTimestampFile('./tmp/update_timestamp.txt');

        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * writeMsgforExecutive()
 * 同友向けメッセージの保存
 * @param $postArray：POSTで送られてきたパラメータ
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function writeMsgforExecutive($postArray)
{
	$ret = 'success';

	//printArray($postArray);

    // データベースに接続
	$db = new DB(config::DB_NAME);

	// データベースのユニークキーの作成
    $key = 'msg';
    
    // 値を作成
    $cday = getCurrentDate(); // 現在日時を取得
    $value = $cday.'##'.$postArray['msg'];
    $value = $value.'##'.$postArray['icn'];

    //DBGMSG('key:'.$key.' value:'.$value);
    
    // 個別の同友向けメッセージは個別に処理
    foreach ($postArray as $e_key => $e_value) {
        if (strpos($e_key, '_msg') !== false) {
            // keyを分解してeidを取り出す
            $keyArray = explode('_', $e_key);
            // データベースに保存
            writeEachExecutiveMsg($keyArray[0], $e_value);
        }
    }

	//------------------------------------------------
	// データベースにキーが存在するかどうか確認
	//------------------------------------------------
	$sql = 'SELECT * FROM '.local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION .'
			WHERE item like "'.$key.'"';

	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

	// データ取得
	while ($data = $db->getData()) {
		$result = $data['value'];
	}

	//------------------------------------------------
	// データベースにデータが入っていない場合はINSERT、
	// 入っている場合はUPDATE
	//------------------------------------------------
	if ( strlen($result) == 0) {
		//DBGMSG('INSERT');
		$sql = sprintf("INSERT INTO %s (item,
										value) 
										VALUES ('%s','%s')",
										local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION,
										$db->escapeString($key),
										$value
										);
	}
	else {
		//DBGMSG('UPDATE');
		$sql = sprintf("UPDATE %s	SET		value 	= '%s'
									WHERE	item	= '%s'
									",
									local_config::$DB_TABLE_PREFIX.config::DB_TABLE_OPTION,
									$value,
									$db->escapeString($key)
									);
	}
	// SQL実行
	//echo 'sql='.$sql.'<br />';
	$db->exec($sql);

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * writeEachExecutiveMsg()
 * 個別の同友向けのメッセージを保存する
 * @param  $eid 同友ID
 * @param  $msg 同友向けメッセージ
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function writeEachExecutiveMsg($eid, $msg)
{
    $ret = 'success';
    
    // データベースに接続
    $db = new DB(config::DB_NAME);

    $sql = sprintf("UPDATE %s	SET		msg = '%s'
								WHERE	id = '%d'
								",
                                local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER,
                                $db->escapeString($msg),
                                $db->escapeString($eid)
                                );
                                
    // SQL実行
    //echo $sql.'<br />';
    $ret = $db->exec($sql);
    
    // データベースクローズ
    $db->close();

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        return 'success';
    }
}

/**
 * ----------------------------------------------------------
 * writeLoginHistory()
 * ログイン日時を記録する
 * @param  $eid 同友ID
 * @return boolean $ret success：成功
 *                 その他：エラーメッセージ
 * ----------------------------------------------------------
 */
function writeLoginHistory($eid)
{
    
    $loginHistory = "";
    $loginHistoryArray = array();
    $ret = 'success';
    
    // データベースに接続
    $db = new DB(config::DB_NAME);

    // 現在のログイン情報を取得
    $loginHistoryArray = getLoginHistory($eid);
    //printArray($loginHistoryArray);

    // 現在の値を取得
    $loginHistory = getCurrentTime();

    // 既にあるログイン履歴と連結
    for ($i = 0; $i < count($loginHistoryArray); $i++) {
        $loginHistory = $loginHistory .';'. $loginHistoryArray[$i];
        if ($i == config::LOGIN_HISTORY_NUM_MAX) {
            break;  // 最新＋ログを19つ連結を保存
        }
    }

    $sql = sprintf("UPDATE %s	SET		login_history = '%s'
								WHERE	id = '%d'
								",
                                local_config::$DB_TABLE_PREFIX.config::DB_TABLE_USER,
                                $loginHistory,
                                $db->escapeString($eid)
                                );

    // SQL実行
    //echo $sql.'<br />';
    $ret = $db->exec($sql);
    
    // データベースクローズ
    $db->close();

    // 戻り値
    if ($ret === 'failed') {
        return $ret;
    } else {
        return 'success';
    }
}