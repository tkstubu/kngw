<?php
/**
 * =================================================================
 * local_config.php
 * 支部毎のオリジナルプレフィックスの設定
 * =================================================================
 */

class local_config {

	//-------------------------------------------------------------
	// 支部別の設定
	//-------------------------------------------------------------

	// DBテーブル名の接頭語
	public static $DB_TABLE_PREFIX	= 'kngw';

	// 調整用の支部の名称
	const SHIBU_AREA_NAME = "ロータス神奈川";

	// 年間計画で統合する種目
	const PLAN_ITEM_COMBINE = ['LM','LS','LM+LS','LT','LH','LO','LE','LL','LC'];

	// キャンペーンで分離する種目
	const CAMPAIGN_ITEM_COMBINE = ['LEB'];

	// 計画値を表示する種目
	const PLAN_OPEN_ITEM = [];

	// 速報シートのエクセルで地域間の行数
	const EXCEL_ROW_NUM = 30;

	//-------------------------------------------------------------
	// 機能別フラグ設定
	//-------------------------------------------------------------

	// 1.同友IDでログインした際にトップページに同友の進捗（個別、年間）を表示する(true：ON false：OFF)
	const FEATURE_EXECUTIVE_RESULT_ON_TOP = true;

	// 2.同友IDでログインした際にトップページに同友の進捗（四半期）を表示する(true：ON false：OFF)
	// FEATURE_EXECUTIVE_RESULT_ON_TOPが有効になっていることが条件
	const FEATURE_EXECUTIVE_QUARTER_RESULT_ON_TOP = false;

	// 3.キャンペーン速報をダウンロードする機能(true：ON false：OFF)
	const FEATURE_DOWNLOAD_CAMPAIGN_SHEET = true;

	// 4.年間計画から分母外同友の計画値を除外する機能(true：ON false：OFF)
	const FEATURE_REMOVE_UNENTERABLE_EXECUTIVE_FROM_PLAN = false;

	// 5.計画入力の同友の並び順 (true：同友コード順 false：地域別同友コード順)
	const FEATURE_EXECLUTIVELIST_CODE_ORDER = false;

	// 6.ログイン時間を記録するかどうか(true：ON false：OFF)
	const FEATURE_SAVE_LOGIN_HISTORY = true;

	// 7.ローディング画面を表示するかどうか (true：有効 false：無効)
	//const FLAG_LOADING_ICON = false;
	
	// 8.地区対抗戦 達成率賞 LCの条件設定（※神奈川オリジナル） (true：有効 false：無効)
	const FEATURE_AREA_REACH_AWARD = true;

	// 9.同友IDでログインした際にキャンペーン毎のシートにLCを表示する (true：有効 false：無効)
	const FEATURE_EXECUTIVE_RESULT_WITH_LC = true;

	// 10.最低販売基準を表示する (true：有効 false：無効)
	const FEATURE_EXECUTIVE_ITEM_MIN_TARGET = true;

	// 11.メッセージ機能を利用する (true：有効 false：無効)
	const FEATURE_MSG_FUNCTION = true;

	// 12.同友役員を追加する (true：有効 false：無効)
	const FEATURE_EXECUTIVE_OFFICER = true;

	// 13.年間計画でLL選択時には全体合計を表示するかどうか（※神奈川オリジナル） (true：有効 false：無効)
	const FEATURE_SELECT_LL_PRINT_TOTAL = true;

	// 14.速報シートKAIINにLMS、キャンペーン用LC追加（8.のFEATUREと合わせてtrueにしないとダメ） (true：有効 false：無効)
	const FEATURE_ADD_LMS_LC_EXCELSHEET = true;

	// 15.同友ログイン時に達成度に応じて色を変える (true：有効 false：無効)
	const FEATURE_CHANGE_COLOR_ACCODING_TO_REACH = true;

	// 16.同友への販促費の同友ログイン時の表示＆設定画面（10.のFEATUREと合わせてtrueにしないとダメ） (true：有効 false：無効)
	const FEATURE_SALES_PROMOTION_FOR_EXECUTIVE = true;

	// 17.支部向け販促費の表示＆設定画面（10.のFEATUREと合わせてtrueにしないとダメ） (true：有効 false：無効)
	const FEATURE_SALES_PROMOTION_FOR_BRANCH = true;

	// 18.LC保有枚数に関する表示と設定画面
	const FEATURE_LC_HOLD_NUMBER = true;

	// 19.支部表彰得点状況に関する表示と設定画面
	const FEATURE_BRANCH_POINT_STATUS = true;

	// 20.社長賞の状況に関する表示と設定画面
	const FEATURE_PRESIDENT_PRIZE_STATUS = true;

	// 21.実績を月ごと提携毎に四捨五入をする処理
	const FEATURE_RESULT_ROUND_BY_MONTH = true;

	// 22.年間計画で提携企業の計画入力を分離するかどうか (true：分離 false：統合)
	// ※基本的に true を指定
	public static $FLAG_SEPARATE_PARTNER_PLAN = true;

	// 23.本部計画を入力するためのユーザーを追加する (true：有効 false：無効)
	public static $FLAG_HEAD_OFFICE_PLAN = true;
	
	// 24.速報シートに出力する月毎の計画と実績を1年分にする (true：有効 false：無効)
	const FEATURE_OUTPUT_EXCELSHEET_FOR_ONEYEAR = false;

	// 25.速報シートの同友出力方法 (true：地区順同友コード順 false：同友コード順)
	const FEATURE_OUTPUT_EXCELSHEET_ORDER_TYPE = true;

	// 26.速報の支援施策の本部設定を一括設定にする (true：一括設定 false：個別)
	// ★機能不完全
	const FEATURE_PARTNER_PRIZE_ONE_SETTING = true;

	// 27.協力会社の実績と設定画面 (true：有効 false：無効)
	const FEATURE_COOPERATION = true;

	// 28.料率固定、ボーナス得点固定（コロナ対応特別措置） (true：有効 false：無効)
	const FEATURE_FIXED_POINT_AND_RATE = true;

	// 29.地域履歴 (true：有効 false：無効)
	const FEATURE_AREA_HISTORY = false;
	
	// 30.表彰機能（★兵庫専用★） (true：有効 false：無効)
	const FEATURE_COMMENDATION_FOR_HYOGO = false;
	
	// 31.あいおいがログインすると他の実績が閲覧不可能になる機能（★千葉専用★） (true：有効 false：無効)
	const FEATURE_AIOI_FOR_CHIBA = false;
}

/**
 * =================================================================
 *  Copyright(c)2013 iSKET All Rights Reserved.
 * =================================================================
 */
?>