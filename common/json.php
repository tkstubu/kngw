<?php
/**
 * =================================================================
 * json.php
 * JSON形式でデータベースにアクセスするためのラッパー関数
 * =================================================================
 */

// エラーは表示しない
error_reporting(0);
ini_set('display_errors', 0); // エラー表示(0:OFF 1:ON)

//--------------------------------
// include
//--------------------------------
require_once("func.php");
require_once("readDB.php");
require_once("writeDB.php");

// セッション開始
session_start();
header('Expires: -1');
header('Cache-Control:');
header('Pragma:');

//--------------------------------
// パラメータ受信
//--------------------------------
$getArray  = convertSpecialChar($_GET);
$postArray = convertSpecialChar($_POST);

//--------------------------------
// パラメータのエラー処理
//--------------------------------
checkParameterIsSet($getArray['command']);

//--------------------------------
// 関数を選択
//--------------------------------
switch($getArray['command']) {
	case 'get_partner_list':
		JsonGetPartnerList($postArray);
		break;
	default:
		break;
}

/**
 * ----------------------------------------------------------
 * JsonGetPartnerList()
 * JSON形式で提携企業一覧を返す
 * @param fiscal_year：年度
 * @return
 * ----------------------------------------------------------
 */
function JsonGetPartnerList($postArray) {

	$pid = 0;
	$jsonArray = array();
	
	if ($postArray['plist'] === 'all') {
		$pid = 1;
	}
	if ($postArray['plist'] === 'limit') {
		// ログインユーザ情報を取得
		$userInfo = getUserInfo($_SESSION['USERID']);
		$pid = $userInfo['id'];
	}
	$partnerList = getPartnerList($postArray['fiscal_year'], '', $pid);
	
	// コマンドを格納
	$jsonArray = array(
		'command' => 'get_partner_list',
		'partner' => $partnerList
		);
	
	// JSON形式で表示
	echo json_xencode($jsonArray);
}
?>