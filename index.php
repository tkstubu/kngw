<?php
/**
 * =================================================================
 * index.php
 * 管理画面用PHPスクリプト
 * =================================================================
 */

//=================================================================
// ロジック部
//=================================================================
// エラーは表示しない
error_reporting(0);
ini_set('display_errors', 0); // エラー表示(0:OFF 1:ON)

//--------------------------------
// 設定
//--------------------------------
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 1);
ini_set('session.gc_maxlifetime', 24 * 60 * 60);

//--------------------------------
// include
//--------------------------------
require_once("common/config.php");
require_once("common/setting.php");
require_once("common/func.php");
require_once("common/calc.php");
require_once("common/readDB.php");
require_once("common/writeDB.php");
require_once("common/outputSheet.php");
require_once("error.php");

// PHPExcelライブラリのパスを通す
set_include_path(get_include_path().PATH_SEPARATOR.$_SERVER["DOCUMENT_ROOT"].dirname($_SERVER["SCRIPT_NAME"]).'/../Library/Classes/');
//echo get_include_path().PATH_SEPARATOR.$_SERVER["DOCUMENT_ROOT"].dirname($_SERVER["SCRIPT_NAME"]).'/../Library/Classes/';
require_once( 'PHPExcel.php' );


if (config::FLAG_SECURITY) {
	// セキュリティ(クリックジャッキング対策)
	header('X-FRAME-OPTIONS: DENY');
}

//--------------------------------
// 初期化処理
//--------------------------------
// グローバル変数で選択中のメニューを管理
$param = config::MENU_NAME_1_TAG;

// 本部の値を参照するのは2019年以降を設定
$postArray = convertSpecialChar($_POST);
if ($postArray['fiscal_year'] >= 2019) {
	local_config::$FLAG_HEAD_OFFICE_PLAN = true;
}
else {
	local_config::$FLAG_HEAD_OFFICE_PLAN = false;
}

// GETで送られてきたパラメータを格納
if (isset($_GET['reg'])) {
	$param = $_GET['reg'];
}

//=================================================================
// デザイン部
//=================================================================

// デバッグ用
//echo 'PHP_OS='.PHP_OS;

//--------------------------------
// ヘッダー読み込み
//--------------------------------
include("common/header.php");

//--------------------------------
// コンテンツ読み込み
//--------------------------------
if (isset($_SESSION["USERID"])) {

	// メニュー
	include("menu.php");
	
	// 現在メニューで選択されているコンテンツを表示
	include(getTargetContent($param));
}
else {

	$html = '
	<hr>
	<div id="contents">
	<div id="system_name">
		<p>'.config::SOFTWARE_NAME.'</p>
	</div>
	</div>
	';
	echo $html;
}

//--------------------------------
// フッター読み込み
//--------------------------------
include("common/footer.php");

/**
 * =================================================================
 *  Copyright(c)2013 iSKET All Rights Reserved.
 * =================================================================
 */
?>