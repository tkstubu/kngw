<?php
/**
 * =================================================================
 * error.php
 * エラー表示用PHPスクリプト
 * =================================================================
 */

//=================================================================
// ロジック部
//=================================================================

//--------------------------------
// include
//--------------------------------

/**
 * ----------------------------------------------------------
 * printErrorMessage()
 * パターンに基づいてエラーメッセージを表示する
 * @param $type：エラー種別
 * @return
 * ----------------------------------------------------------
 */
function printErrorMessage($type) {

	switch($type) {
		case 'no_data':
			$message = 'データがまだ登録されていません。';
			break;
		case 'no_item':
			$message = '種目が設定されていません。システム設定を行ってください。';
			break;
		case 'no_area':
			$message = '地域が設定されていません。システム設定を行ってください。';
			break;
		case 'no_partner':
			$message = '提携企業が設定されていません。ユーザ管理から提携企業を登録してください。';
			break;
		case 'no_executive':
			$message = '同友会社が設定されていません。ユーザ管理から同友会社を登録してください。';
			break;
		case 'no_exeofficer':
			$message = '同友役員が設定されていません。ユーザ管理から同友役員を登録してください。';
			break;
		case 'no_cooperation':
			$message = '協力企業が設定されていません。ユーザ管理から協力企業を登録してください。';
			break;
		case 'no_adjustment':
			$message = '調整用項目が設定されていません。ユーザ管理から項目を登録してください。';
			break;
		case 'no_campaign_executive':
			$message = 'キャンペーンに参加している同友会社が設定されていません。ユーザ管理から設定してください。';
			break;
		case 'no_input':
			$message = '入力されていない項目があります。確認してください。';
			break;
		case 'no_input_file':
			$message = 'ファイルが選択されていません。';
			break;
		case 'no_graph_data':
			$message = '編集するグラフのデータがありません。';
			break;
		case 'error_unselect_item':
			$message = '種目を選択してください。';
			break;
		case 'input_file_error':
			$message = '入力ファイルの解析に失敗しました。ファイルの内容を確認してください。';
			break;
		case 'output_file_error':
			$message = 'ダウンロードするファイルの生成に失敗しました。';
			break;
		case 'template_file_error':
			$message = 'テンプレートファイルがありません。';
			break;
		case 'error_filesize':
			$message = '正しいファイルが選択されていません。';
			break;
		case 'unmatch_password':
			$message = 'パスワードが一致しません。確認してください。';
			break;
		case 'exist_user':
			$message = '同一のユーザIDが登録されています。ユーザIDを別のものに変更してください。';
			break;
		case 'db_write_fail':
			$message = 'データベースへの書き込みに失敗しました。';
			break;
		case 'campaign_create_failed':
			$message = 'キャンペーンデータが作成できません。年間計画を先に作成してください。';
			break;
		case 'file_backup_error':
				$message = 'ファイルをバックアップできませんでした。';
				break;
		case 'file_delete_error':
				$message = 'ファイルが削除できませんでした。';
				break;
		default:
			$message = 'エラーが発生しました。';
			break;
	}
	
	echo '<div id="contents">'.$message.'</div>';
}

/**
 * =================================================================
 *  Copyright(c)2013 iSKET All Rights Reserved.
 * =================================================================
 */
?>