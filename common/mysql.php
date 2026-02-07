<?php
/**
 * =================================================================
 * mysql.php
 * MySQLアクセス用クラス
 * =================================================================
 */

// 外部ファイルの読み込み
require_once("config.php");

class DB {
	// プロパティを定義
	private $mlink;
	private $mRecordData;
	private $mSQL;
	
	/**
     * -------------------------------------------------------------
     * コンストラクタ
     * データベースに接続
     * -------------------------------------------------------------
     */
	function __construct($DBName) {
		// データベースに接続
		// ローカルとネットワークで区別
		if (PHP_OS == "WIN32" || PHP_OS == "WINNT") {
			$this->mlink = mysqli_connect(config::LOCAL_DB_HOST_NAME, config::LOCAL_DB_ID, config::LOCAL_DB_PW, $DBName);
		}
		else {
	    	$this->mlink = mysqli_connect(config::DB_HOST_NAME, config::DB_ID, config::DB_PW, $DBName);
		}
	    
		// 接続状況をチェック
		if (mysqli_connect_errno()) {
		    printf("Connect failed: %s\n", mysqli_connect_error());
		    exit();
		}

		// 文字コードセット
		if (!mysqli_query($this->mlink, 'SET NAMES UTF8') === TRUE) {
			printf("Set char code failed: %s\n", mysqli_connect_error());
		    exit();
		}
	}
	
	/**
     * -------------------------------------------------------------
     * データベースを閉じる
     * -------------------------------------------------------------
     */
	function close() {
		return mysqli_close($this->mlink);
	}

	/**
     * -------------------------------------------------------------
     * 接続の現在の文字セットを考慮して、
     * SQL 文で使用する文字列の特殊文字をエスケープする
     * -------------------------------------------------------------
     */
	function escapeString($str) {
		return mysqli_real_escape_string($this->mlink, $str);
	}
	
	/**
     * -------------------------------------------------------------
     * SQLを実行
     * -------------------------------------------------------------
     */
	function exec($SQL) {
		//echo $SQL;
		$this->mSQL = $SQL;
		if ($this->mRecordData = mysqli_query($this->mlink, $this->mSQL)) {
			return $this->mRecordData;
		}
		else {
			printf("sql exec failed: %s\n", $SQL);
		    return 'failed';
		}
	}
	
	/**
     * -------------------------------------------------------------
     * データを取得
     * -------------------------------------------------------------
     */
	function getData() {
		return mysqli_fetch_assoc($this->mRecordData);
	}
	
	/**
     * -------------------------------------------------------------
     * データ個数を取得
     * -------------------------------------------------------------
     */
	function getNumRow() {
		if ($stmt = mysqli_prepare($this->mlink, $this->mSQL)) {
			mysqli_stmt_execute($stmt);
			mysqli_stmt_store_result($stmt);
			return mysqli_stmt_num_rows($stmt);
		}
		else {
			printf("get num row failed: %s\n", mysqli_connect_error());
		    exit();
		}
	}
	
	/**
     * -------------------------------------------------------------
     * 最後にINSERTしたレコードの自動生成IDを取得
     * -------------------------------------------------------------
     */
	function getLastID() {
		return mysqli_insert_id($this->mlink);
	}
}

/**
 * =================================================================
 *  Copyright(c)2013 iSKET All Rights Reserved.
 * =================================================================
 */
?>