<?php
/**
 * =================================================================
 * config.php 
 * 共通オブジェクト定数の定義
 * =================================================================
 */

class config {

	//-------------------------------------------------------------
	// ソフトウェア管理
	//-------------------------------------------------------------
	const SOFTWARE_NAME    = 'ロータス・リアルタイム実績管理システム';
	const SOFTWARE_VERSION = 'Ver1.11';
	
	//-------------------------------------------------------------
	// データベース設定
	//-------------------------------------------------------------
	
	// データベースログイン情報
	// ローカル用
	const LOCAL_DB_HOST_NAME = '127.0.0.1';
	const LOCAL_DB_ID        = 'root';
	const LOCAL_DB_PW        = '';
	
	// lotas-systemサーバ用
	const DB_HOST_NAME = 'localhost';
	const DB_ID        = 'lotas';
	const DB_PW        = 'PjFjIo4#';
	
	// データベース名
	const DB_NAME = 'lotas';
	
	// DBテーブル名一覧
	const DB_TABLE_SETTING	 	    = '_setting';			// 設定情報
	const DB_TABLE_USER    		    = '_user';				// ユーザ情報
	const DB_TABLE_CAMPAIGN		    = '_campaign';			// キャンペーン本部計画
	const DB_TABLE_CAMPAIGN_DATA    = '_campaign_data';		// キャンペーン計画
	const DB_TABLE_CAMPAIGN_GRAPH   = '_campaign_graph';	// キャンペーン用グラフ
	const DB_TABLE_DATA			    = '_data';				// メイン種目
	const DB_TABLE_DATA_SPITEM      = '_data_spitem';		// 特別種目
	const DB_TABLE_DATA_SUBITEM     = '_data_subitem';		// 補助種目
	const DB_TABLE_DATA_COOPERATION = '_data_cooperation';	// 協力会社
	const DB_TABLE_OPTION	 	    = '_option';			// 自由に設定できるオプション用
	
	//-------------------------------------------------------------
	// フラグ設定
	//-------------------------------------------------------------
	
	// セキュリティ対策 (true：有効 false：無効)
	const FLAG_SECURITY = false;
	
	//-------------------------------------------------------------
	// メニュー設定
	// TAGはスクリプト名としても利用
	//-------------------------------------------------------------
	
	const MENU_NAME_1  = 'HOME';
	const MENU_NAME_2  = '年間計画';
	const MENU_NAME_3  = 'キャンペーン';
	const MENU_NAME_4  = 'ユーザ管理';
	const MENU_NAME_5  = 'システム設定';
	const MENU_NAME_6  = 'メッセージ';
	const MENU_NAME_7  = '支部表彰得点';
	const MENU_NAME_8  = '社長賞';
	const MENU_NAME_9  = '支援施策';
	const MENU_NAME_10 = '本部年間施策';
	const MENU_NAME_11 = '協力企業';
	const MENU_NAME_12 = '表彰';
	
	const MENU_NAME_1_TAG  = 'top';
	const MENU_NAME_2_TAG  = 'plan';
	const MENU_NAME_3_TAG  = 'campaign';
	const MENU_NAME_4_TAG  = 'user';
	const MENU_NAME_5_TAG  = 'option';
	const MENU_NAME_6_TAG  = 'msg';
	const MENU_NAME_7_TAG  = 'point_status';
	const MENU_NAME_8_TAG  = 'president_prize';
	const MENU_NAME_9_TAG  = 'partner_prize';
	const MENU_NAME_10_TAG = 'branch_prize';
	const MENU_NAME_11_TAG = 'cooperation';
	const MENU_NAME_12_TAG = 'commendation';

	//-------------------------------------------------------------
	// キャンペーン
	//-------------------------------------------------------------
	const SUMMER_CAMPAIGN_NAME = 'サマーキャンペーン';
	const AUTUMN_CAMPAIGN_NAME = '秋のキャンペーン';
	const SPRING_CAMPAIGN_NAME = '春のキャンペーン';
	
	const SUMMER_CAMPAIGN_START_MONTH = 6;	// サマーキャンペーン開始月
	const SUMMER_CAMPAIGN_END_MONTH   = 7;	// サマーキャンペーン終了月
	const AUTUMN_CAMPAIGN_START_MONTH = 10;	// 秋のキャンペーン開始月
	const AUTUMN_CAMPAIGN_END_MONTH   = 11;	// 秋のキャンペーン終了月
	const SPRING_CAMPAIGN_START_MONTH = 2;	// 春のキャンペーン開始月
	const SPRING_CAMPAIGN_END_MONTH   = 3;	// 春のキャンペーン終了月
	
	const ENTERABLE_UNDER_POINT       = 70;		// 参加率計算時の最低値
	const ENTERABLE_POINT_MAX         = 100;	// 参加率得点の最大値
	const REACH_POINT_MAX             = 150;	// 達成率得点の最大値
	const EXCELLENT_REACH_POINT_MAX   = 100;	// 優績を取るための達成率の基準値
	const EXCELLENT_POINT_10_MAX      = 300;	// 優績ポイント(10点の場合)
	const EXCELLENT_POINT_15_MAX      = 350;	// 優績ポイント(15点の場合)
	
	// 優績ポイント
	public static $EXCELLENT_POINT = array('5'=>'300','10'=>'300','15'=>'350');

	// 同友会得点
	public static $EXECUTIVE_BONUS_POINT = array(array('value' => '5', 'name'=>'5'),
												 array('value' => '10', 'name'=>'10'),
												 array('value' => '15', 'name'=>'15'));
	
	// グラフ用カラーパレット
	public static $CAMPAIGN_GRAPH_COLOR = array('#9B59B6',		// AMETHYST
												'#3498DB',		// PETER RIVER
												'#2ECC71',		// EMERALD
												'#16A085',		// GREEN SEA
												'#F1C40F',		// SUN FLOWER
												'#E67E22',		// CARROT
												'#E74C3C');		// ALIZARIN
	
	// グラフの最大期間数
	const CAMPAIGN_GRAPH_RANGE_MAX = 7;		// キャンペーン用の達成グラフの最大期間数

	// 特別キャンペーン(得点に関わらず固定の点数を与える場合に使用)
	const CAMPAIGN_FIXED_POINT_TYPE = array('2020_summer');
	const CAMPAIGN_FIXED_POINT = array(10);
	
	const ALERT_MSG1 = 'alert(\'こちらの設定を変更した場合、\n本キャンペーンのみ有効な設定変更となります。\n\n継続的な設定変更を行う場合は\n【ユーザー管理】→【同友一覧】→【編集】\nから設定を行って下さい。\')';

	//-------------------------------------------------------------
	// 定数
	//-------------------------------------------------------------
	const CAMPAIGN_LMLS_ADD_YEAR = 2015;	// キャンペーンでLM+LSを追加した年度は2015年
	
	const ITEM_NUM_MAX = 10;		// 設定可能な種目の最大数
	const SUBITEM_NUM_MAX = 10;		// 設定可能な補助種目の最大数
	const AREA_NUM_MAX = 10;		// 設定可能な地域名の最大数
	const MAKE_PLAN_NUM_MAX = 6;	// 計画作成の最大数
	const LOGIN_HISTORY_NUM_MAX = 18;	// ログイン履歴の記録件数（設定値+2が実際の保存数）
	
	const TEMP_DIRECTORY_NAME  = 'tmp';			// 添付フォルダ
	const INPUT_EXCEL_FILENAME = 'plan_input_';	// 一括計画入力用Excelファイル名
	
	// テンプレート用ファイル
	const CAMPAIGN_EXCEL_FILENAME = 'template_campaign.xlsx';
	const EXECUTIVE_EXCEL_FILENAME = 'template_executive.xlsx';
	// テンプレート用ファイル 2015年以降
	const CAMPAIGN_V1_EXCEL_FILENAME = 'template_campaign_2019_summer.xlsx';	// FEATURE_ADD_LMS_LC_EXCELSHEETを有効にするならv3以降にすること
	const EXECUTIVE_V1_EXCEL_FILENAME = 'template_executive_v3.xlsx';

	//-------------------------------------------------------------
	// 協力企業用
	//-------------------------------------------------------------
	const COOPERATION_SETTING_TYPE_RAW = 1;
	const COOPERATION_SETTING_TYPE_UNIT = 2;

	//-------------------------------------------------------------
	// 賞金関連
	//-------------------------------------------------------------
	// パーフェクト賞、準パーフェクト賞の種目数
	const PERFECT_AWARD_ITEM_NUM = 6;			// パーフェクト賞
	const SECOND_PERFECT_AWARD_ITEM_NUM = 5;	// 準パーフェクト賞

	// 達成状況判定用
	const REACH_PATTERN_1 = 0.70;	// 達成率 75%
	const REACH_PATTERN_2 = 0.80;	// 達成率 85%
	const REACH_PATTERN_3 = 0.90;	// 達成率 90%
	const REACH_PATTERN_4 = 0.95;	// 達成率 95%

	// 支部への販促費
	// 達成率に関わらず料率を一律に設定する期間
	const CAMPAIGN_FIXED_RATE_TYPE = array('2020_summer');	// キャンペーン
	const QUARTER_FIXED_RATE_TYPE = array('2020_1', '2020_2');	// 四半期

	// 達成率に関わらず料率を一律に設定(キャンペーン用)
	const CAMPAIGN_FIXED_RATE = array(array('LT'=>'0.27', 'LO'=>'0.6', 'LE'=>'0.3', 'LL'=>'0.06')
	);

	// 達成率に関わらず料率を一律に設定（四半期用）
	const QUARTER_FIXED_RATE = array(array('LT'=>'0.7', 'LO'=>'2','LE'=>'1','LL'=>'0.14'),
									 array('LT'=>'0.7', 'LO'=>'2','LE'=>'1','LL'=>'0.14')
	);	

	// 生産性・ボリューム報奨金に関する順位からの賞金変換テーブル
	const SEISAN_PRIZE = array(0, 5000,4000,3000,1500,1500,1500,1500,1500,1500,1500);
	const VOLUME_PRIZE = array(0, 150000,100000,75000,40000,40000,40000,40000,40000,40000,40000);

	// 表彰（★兵庫支部専用★）
	const COMMENDATION_NO_RESULT 	= 0;	// 実績なしの判定値
	const COMMENDATION_RESULT_MIN	= 2;	// 実績ありの判定値
	const COMMENDATION_TOP_NUM 		= 10;	// 表彰で表示する同友数
	const COMMENDATION_ITEM_NUM_MAX = 7;	// 判定する種目数
	const COMMENDATION_ACHIEVEMENT_RATIO_1 = 110;	// 達成率 その1
	const COMMENDATION_ACHIEVEMENT_RATIO_2 = 120;	// 達成率 その2
	const COMMENDATION_ACHIEVEMENT_RATIO_3 = 150;	// 達成率 その3
	const COMMENDATION_ACHIEVEMENT_RATIO_4 = 200;	// 達成率 その4
	const COMMENDATION_ACHIEVEMENT_RATIO_MAX = 300;	// 達成率の最大値

	// 提携企業
	// 報奨金の設定をする時に使うID
	const LM   = 'lm';
	const LMK  = 'lm-k';
	const LMS  = 'lm-s'; 
	const LS   = 'ls';
	const LTB  = 'lt-b';	// ブリヂストン
	const LTY  = 'lt-y';	// ヨコハマタイヤ
	const LOM  = 'lo-m';	// モービル
	const LOP  = 'lo-p';	// パルスター
	const LEP  = 'le-p';	// パナソニック
	const LEG  = 'le-g';	// GSユアサ
	const LLO  = 'll-o';	// オリコ
	const LLJ  = 'll-j';	// ジャックス

	//-------------------------------------------------------------
	// 判定用
	//-------------------------------------------------------------
	// ユーザ関連
	const USER_ADMIN       = 0;	// 管理者
	const USER_PARTNER     = 1;	// 提携企業
	const USER_EXECUTIVE   = 2;	// 同友
	const USER_ADJUSTMENT  = 3;	// その他（平均にカウントしない調整用）
	const USER_EXEOFFICER  = 4;	// 同友役員
	const USER_HEADOFFICE  = 5;	// 本部計画用
	const USER_COOPERATION = 6;	// 協力企業

	// 本部判定用
	const HEADOFFICE_NAME = '本部';	// この名称で地域に設定すること
	
	// データ関連
	const STATUS_UNENTERABLE    = 0;	// キャンペーン参加資格なし
	const STATUS_ENTERABLE      = 1;	// キャンペーン参加資格あり
	const STATUS_RECESS         = 2;	// キャンペーン退会（休会）中
	const STATUS_NO_BASE_RECESS = 3;	// 基準外退会（基準同友になる前に退会）
	
	const STATUS_DATA_UNLOCK = 0;	// 実績、キャンペーンのデータがロックが解除されている状態
	const STATUS_DATA_LOCK   = 1;	// 実績、キャンペーンのデータのロックされている状態
	const STATUS_DATA_CLOSE  = 0;	// 実績、キャンペーンのデータが非公開の状態
	const STATUS_DATA_OPEN   = 1;	// 実績、キャンペーンのデータが公開の状態

	// 年間、キャンペーンにおける種目の統合、分離
	// 基本的に実績は分離、計画の入力において、分離させるか統合させるかを以下のフラグで判断する
	const ITEM_LIST_HIDDEN   = 0;	// 非表示
	const ITEM_LIST_SEPARATE = 1;	// 分離
	const ITEM_LIST_COMBINE  = 2;	// 統合
}

/**
 * =================================================================
 *  Copyright(c)2013 iSKET All Rights Reserved.
 * =================================================================
 */
?>