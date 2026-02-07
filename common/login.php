<?php
/**
 * =================================================================
 * login.php
 * ログイン処理用PHPスクリプト
 * =================================================================
 */
// 直接アクセス禁止
//if (array_shift(get_included_files()) === __FILE__) die('Error. Invalid URL.');


// セッション開始
session_start();
header('Expires: -1');
header('Cache-Control:');
header('Pragma:');

//--------------------------------
// include
//--------------------------------
require_once("local_config.php");
require_once("config.php");
require_once("mysql.php");
require_once("common/writeDB.php");

$getArray  = convertSpecialChar($_GET);


$userInfo = array();

// もしGETでIDとPWが送られてきた場合は認証処理をする
if (isset($getArray['id']) && isset($getArray['pw'])) {
	$_POST["login"] = 'login';
	$_POST["userid"] = $getArray['id'];
	$_POST["password"] = $getArray['pw'];
}

// ログインボタンが押された場合      
if (isset($_POST["login"])) {

	// エラー処理
	if (strlen($_POST["userid"]) == 0) {
		clearSession();
	}
	else {
		// 認証成功
		$userInfo = getUserInfo($_POST["userid"]);

		// ユーザ情報が取れた場合は10の要素があるのでそれ以下の場合は間違ったデータ
		if (count($userInfo) < 10) {
			clearSession();
		}
		else {
			// 取得したユーザデータのパスワードが一致するかどうか
			if ($_POST["password"] === $userInfo['password']) {
			
				// セッションIDを新規に発行する
				// ※セッションハイジャック対策
				session_regenerate_id(TRUE);
				
				// セッションにユーザIDを格納
				$_SESSION["USERID"] = $_POST["userid"];
				$_SESSION["USERNAME"] = $userInfo['name'];
				$_SESSION["eid"] = $userInfo['id'];
				
				// ドメイン毎の区別を付けるためにURLも保存
				$_SESSION["URL"] = dirname($_SERVER['SCRIPT_NAME']);
			}
			else {
				// パスワードが一致しないのでクリア
				clearSession();
			}
		}
	}
}
// ログアウトボタンが押された場合
elseif (isset($_POST["logout"])) {
	clearSession();
}

// デバッグ用
//echo $userInfo.'<br />';
//echo 'count($userInfo)='.count($userInfo).'<br />';
//echo 'SESSION='.$_SESSION["URL"].'<br />';
//echo '$_SERVER["REQUEST_URI"]='.$_SERVER['REQUEST_URI'].'<br />';
//echo '$_SERVER["DOCUMENT_ROOT"]='.$_SERVER['DOCUMENT_ROOT'].'<br />';
//echo '$_SERVER["SCRIPT_FILENAME"]='.$_SERVER['SCRIPT_FILENAME'].'<br />';
//echo '$_SERVER["SCRIPT_NAME"]='.$_SERVER['SCRIPT_NAME'].'<br />';
//echo 'dirname($_SERVER["SCRIPT_NAME"])='.dirname($_SERVER['SCRIPT_NAME']).'<br />';

/**
 * ----------------------------------------------------------
 * checkLogin()
 * ログイン完了済みかどかを判定
 * 未完了の場合、ログアウト画面を表示
 * ----------------------------------------------------------
 */
function checkLogin() {
	
	// デバッグ用：初期化処理
	//$_SESSION = array();

	// ログインが完了していない場合
	if (!isset($_SESSION["USERID"])) {
		clearSession();
		printLoginForm('unfinished');
	}
	// URLチェック
	elseif ($_SESSION["URL"] !== dirname($_SERVER['SCRIPT_NAME'])) {
		clearSession();
		printLoginForm('unfinished');
	}
	else {
		printLoginForm('finished');
	}
}

/**
 * ----------------------------------------------------------
 * printLoginForm()
 * ログイン用フォームを表示する
 * @param $type ログイン状態
 * ----------------------------------------------------------
 */
function printLoginForm($type) {

	// ログイン状況に応じて表示フォームを変更
	switch($type) {
		
		// ログインしていない場合
		case 'unfinished':
			$html = '
				<form action="index.php" method="POST">
				ユーザID  <input type="text" name="userid" value="">
				パスワード  <input type="password" name="password" value="">
				&nbsp;<input type="submit" name="login" value="ログイン">
				</form>
				';
			break;
		
		// ログイン中の場合
		case 'finished':
			$html = '
				<form action="index.php" method="POST">'
				.$_SESSION["USERNAME"].'様ようこそ
				&nbsp;<input type="submit" name="logout" value="ログアウト">
				</form>
				';
			
			// ログイン日時を記録
			if (local_config::FEATURE_SAVE_LOGIN_HISTORY && isset($_POST["login"]) && $_SESSION["NOLOG"] == false) {
				writeLoginHistory($_SESSION["eid"]);
			}

			break;
			
		default:
			$html = '';
			break;		
	}
	
	echo $html."\n";
}

/**
 * ----------------------------------------------------------
 * clearSession()
 * セッションをクリア
 * ----------------------------------------------------------
 */
function clearSession() {

	// セッション変数のクリア
	$_SESSION = array();
	
	// クッキーの破棄
	if (ini_get("session.use_cookies")) {
	    $params = session_get_cookie_params();
	    // setcookie(session_name(), '', time() - 42000,
	    //     $params["path"], $params["domain"],
	    //     $params["secure"], $params["httponly"]
	    // );
	}
	
	// セッションクリア
	@session_destroy();
}

/**
 * =================================================================
 *  Copyright(c)2013 iSKET All Rights Reserved.
 * =================================================================
 */
?>