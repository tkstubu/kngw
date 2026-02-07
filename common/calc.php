<?php
/**
 * =================================================================
 * 共通関数 キャンペーンなどのポイントを計算するためのファンクション
 * =================================================================
 */

 /**
 * ----------------------------------------------------------
 * getResultAndPlanByItem()
 * 種目毎の実績と計画値を年間、四半期、月別、キャンペーン別で取得する
 * @param $fiscal_year：指定年度
 * @param $item：種目
 * @param $pid：提携企業を指定する場合
 * @param $area：地域を指定する場合
 * @param $enterable true 分母同友のみ false 全体
 * @return $returndata：指定種目の年間、四半期、月別の値
 * ----------------------------------------------------------
 */
function getResultAndPlanByItem($fiscal_year, $item, $pid, $area='%', $enterable) {

    // 初期化
    $data = array();
    $resultTotal = array();
    $planTotal = array();
    $returndata = array();

    // pidが指定されていない場合は、種目ごとの提携企業を取得する
    if ($pid === 'TOTAL' || $pid === 'NONE') {
        $partnerList = getPartnerList($fiscal_year, $item);
        //echo 'item='.$item.'<br/>';
        //printArray($partnerList);
    }
    else {
        $partnerList = getPartnerList($fiscal_year, $item, $pid);
    }

    // 種目毎の実績と計画を取得
    if ($item === 'LM+LS') {
        $lm_data = getAreaTotalValue($pid, 'LM', $area, $fiscal_year, $enterable);
        $ls_data = getAreaTotalValue($pid, 'LS', $area, $fiscal_year, $enterable);

        // 地域が「全地域=%」の場合、計画は本部計画を取得
        if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
            if ($area === '%') {
                $headoffice_lm_data = getAreaTotalValue($pid, 'LM', config::HEADOFFICE_NAME, $fiscal_year);
                $headoffice_ls_data = getAreaTotalValue($pid, 'LS', config::HEADOFFICE_NAME, $fiscal_year);
            }
        }

        for ($i = 1; $i <= 12; $i++) {
            $data[0][$i.'_result'] = $lm_data[0][$i.'_result'] + $ls_data[0][$i.'_result'];
            // 地域が「全地域=%」の場合、計画は本部計画を取得
            if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
                if ($area === '%') {
                    $data[0][$i.'_plan']   = $headoffice_lm_data[0][$i.'_plan'] + $headoffice_ls_data[0][$i.'_plan'];
                }
                else {
                    $data[0][$i.'_plan']   = $lm_data[0][$i.'_plan'] + $ls_data[0][$i.'_plan'];
                }
            }
            else {
                $data[0][$i.'_plan']   = $lm_data[0][$i.'_plan'] + $ls_data[0][$i.'_plan'];
            }
        }
    }
    elseif ($item === 'LL' && $pid === 'TOTAL' && local_config::FEATURE_SELECT_LL_PRINT_TOTAL) {
        // ジャックスはジャックスとジャックス1/2、オリコはオリコとオリコ1/2で合算してから、月毎に四捨五入する
        $tmp_key1 = array();
        $tmp_key2 = array();
        foreach ($partnerList as $partnerArray) {
            //echo 'name='.$partnerArray['name'].'<br>';
            // ジャックスか、オリコなのかを判定し、グルーピングする。
            if(strpos($partnerArray['name'],'ジャックス') !== false || strpos($partnerArray['name'],'ｼﾞｬｯｸｽ') !== false || strpos($partnerArray['name'],'ロートピア/J') !== false){
                $tmp = getAreaTotalValue($partnerArray['value'], $item, $area, $fiscal_year, $enterable);
                // 地域が「全地域=%」の場合、計画は本部計画を取得
                if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
                    if ($area === '%') {
                        $headoffice_tmp = getAreaTotalValue($partnerArray['value'], $item, config::HEADOFFICE_NAME, $fiscal_year);
                    }
                }

                for ($i = 1; $i <= 12; $i++) {
                    $tmp_key1[0][$i.'_result'] += $tmp[0][$i.'_result'];
                    // 地域が「全地域=%」の場合、計画は本部計画を取得
                    if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
                        if ($area === '%') {
                            $tmp_key1[0][$i.'_plan'] += $headoffice_tmp[0][$i.'_plan'];
                        }
                        else {
                            $tmp_key1[0][$i.'_plan'] += $tmp[0][$i.'_plan'];
                        }
                    }
                    else {
                        $tmp_key1[0][$i.'_plan'] += $tmp[0][$i.'_plan'];
                    }
                }
            }
            else {
                $tmp = getAreaTotalValue($partnerArray['value'], $item, $area, $fiscal_year, $enterable);
                // 地域が「全地域=%」の場合、計画は本部計画を取得
                if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
                    if ($area === '%') {
                        $headoffice_tmp = getAreaTotalValue($partnerArray['value'], $item, config::HEADOFFICE_NAME, $fiscal_year);
                    }
                }

                for ($i = 1; $i <= 12; $i++) {
                    $tmp_key2[0][$i.'_result'] += $tmp[0][$i.'_result'];
                    // 地域が「全地域=%」の場合、計画は本部計画を取得
                    if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
                        if ($area === '%') {
                            $tmp_key2[0][$i.'_plan'] += $headoffice_tmp[0][$i.'_plan'];
                        }
                        else {
                            $tmp_key2[0][$i.'_plan'] += $tmp[0][$i.'_plan'];
                        }
                    }
                    else {
                        $tmp_key2[0][$i.'_plan'] += $tmp[0][$i.'_plan'];
                    }
                }
            }
        }
        // ジャックスとオリコをそれぞれ月毎に四捨五入してから合算する
        for ($i = 1; $i <= 12; $i++) {
            $data[0][$i.'_result'] += round($tmp_key1[0][$i.'_result']) + round($tmp_key2[0][$i.'_result']);
            $data[0][$i.'_plan']   += $tmp_key1[0][$i.'_plan'] + $tmp_key2[0][$i.'_plan'];
        }
        //printArray($tmp_key1);
        //printArray($tmp_key2);
        //printArray($data);
    }
    elseif ($item === 'LL' && $pid !== 'TOTAL' && local_config::FEATURE_SELECT_LL_PRINT_TOTAL) {
        // ジャックスやオリコのPIDが設定された時に、1/2との合算を算出する処理
        foreach ($partnerList as $partnerArray) {
            // 現在のローン会社を判定し、データを取得する
            if(strpos($partnerArray['name'],'ジャックス') !== false || strpos($partnerArray['name'],'ｼﾞｬｯｸｽ') || strpos($partnerArray['name'],'ロートピア/J') !== false){
                $key1 = "ジャックス";
                $key2 = "ｼﾞｬｯｸｽ";
                $key3 = "ロートピア/J";
            }
            else {
                $key1 = "オリ";
                $key2 = "ｵﾘ";
                $key3 = "ロートピア/O";
            }
        }
        // keyに設定された提携の実績データを取得
        $partnerList = getPartnerList($fiscal_year, $item);
        foreach ($partnerList as $partnerArray) {
            if(strpos($partnerArray['name'],$key1) !== false || strpos($partnerArray['name'],$key2) !== false || strpos($partnerArray['name'],$key3) !== false ){
                $tmp = getAreaTotalValue($partnerArray['value'], $item, $area, $fiscal_year, $enterable);
                // 地域が「全地域=%」の場合、計画は本部計画を取得
                if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
                    if ($area === '%') {
                        $headoffice_tmp = getAreaTotalValue($partnerArray['value'], $item, config::HEADOFFICE_NAME, $fiscal_year);
                    }
                }

                for ($i = 1; $i <= 12; $i++) {
                    $data[0][$i.'_result'] += round($tmp[0][$i.'_result']);
                    // 地域が「全地域=%」の場合、計画は本部計画を取得
                    if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
                        if ($area === '%') {
                            $data[0][$i.'_plan'] += $headoffice_tmp[0][$i.'_plan'];
                        }
                        else {
                            $data[0][$i.'_plan'] += $tmp[0][$i.'_plan'];
                        }
                    }
                    else {
                        $data[0][$i.'_plan'] += $tmp[0][$i.'_plan'];
                    }
                }
            }
        }
        //printArray($data);
    }
    else {
        // LM+LS以外の時は、提携企業毎に実績、計画データを取得し、月毎に四捨五入する
        foreach ($partnerList as $partnerArray) {

            $tmp = getAreaTotalValue($partnerArray['value'], $item, $area, $fiscal_year, $enterable);

            // 地域が「全地域=%」の場合、計画は本部計画を取得
            if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
                if ($area === '%') {
                    $headoffice_tmp = getAreaTotalValue($partnerArray['value'], $item, config::HEADOFFICE_NAME, $fiscal_year);
                }
            }

            for ($i = 1; $i <= 12; $i++) {
                $data[0][$i.'_result'] += round($tmp[0][$i.'_result']);

                // 地域が「全地域=%」の場合、計画は本部計画を取得
                if (local_config::$FLAG_HEAD_OFFICE_PLAN) {
                    if ($area === '%') {
                        $data[0][$i.'_plan']   += $headoffice_tmp[0][$i.'_plan'];
                    }
                    else {
                        $data[0][$i.'_plan']   += $tmp[0][$i.'_plan'];
                    }
                }
                else {
                    $data[0][$i.'_plan']   += $tmp[0][$i.'_plan'];
                }
            }
        }
    }
    //printArray($data);

    // キャンペーンの計画を取得
    $campaign_info = getCampaignInfo($fiscal_year, $item);

    // 年間
    for ($i = 1; $i <= 12; $i++) {
        $resultTotal['year'] += $data[0][$i.'_result'];
        $planTotal['year']   += $data[0][$i.'_plan'];
    }
    // 第一四半期
    for ($i = 4; $i <= 6; $i++) {
        $resultTotal['1_quarter'] += $data[0][$i.'_result'];
        $planTotal['1_quarter']   += $data[0][$i.'_plan'];
    }
    // 第二四半期
    for ($i = 7; $i <= 9; $i++) {
        $resultTotal['2_quarter'] += $data[0][$i.'_result'];
        $planTotal['2_quarter']   += $data[0][$i.'_plan'];
    }
    // 第三四半期
    for ($i = 10; $i <= 12; $i++) {
        $resultTotal['3_quarter'] += $data[0][$i.'_result'];
        $planTotal['3_quarter']   += $data[0][$i.'_plan'];
    }
    // 第四四半期
    for ($i = 1; $i <= 3; $i++) {
        $resultTotal['4_quarter'] += $data[0][$i.'_result'];
        $planTotal['4_quarter']   += $data[0][$i.'_plan'];
    }
    // 第一三半期
    for ($i = 4; $i <= 7; $i++) {
        $resultTotal['1_trimester'] += $data[0][$i.'_result'];
        $planTotal['1_trimester']   += $data[0][$i.'_plan'];
    }
    // 第二三半期
    for ($i = 8; $i <= 11; $i++) {
        $resultTotal['2_trimester'] += $data[0][$i.'_result'];
        $planTotal['2_trimester']   += $data[0][$i.'_plan'];
    }
    // 第三三半期
    for ($i = 1; $i <= 3; $i++) {
        $resultTotal['3_trimester'] += $data[0][$i.'_result'];
        $planTotal['3_trimester']   += $data[0][$i.'_plan'];
    }
    $resultTotal['3_trimester'] += $data[0]['12_result'];
    $planTotal['3_trimester']   += $data[0]['12_plan'];

    // 上半期
    $resultTotal['1_half'] = $resultTotal['1_quarter']+$resultTotal['2_quarter'];
    $planTotal['1_half']   = $planTotal['1_quarter']+$planTotal['2_quarter'];

    // 下半期
    $resultTotal['2_half'] = $resultTotal['3_quarter']+$resultTotal['4_quarter'];
    $planTotal['2_half']   = $planTotal['3_quarter']+$planTotal['4_quarter'];

    // 月別
    for ($i = 1; $i <= 12; $i++) {
        $resultTotal[$i] = $data[0][$i.'_result'];
        $planTotal[$i]   = $data[0][$i.'_plan'];
    }

    // キャンペーン月まで
    // 7月まで
    $resultTotal['until_7'] = $resultTotal['1_trimester'];
    $planTotal['until_7']   = $planTotal['1_trimester'];
    // 11月まで
    $resultTotal['until_11'] = $resultTotal['1_trimester']+$resultTotal['2_trimester'];
    $planTotal['until_11']   = $planTotal['1_trimester']+$planTotal['2_trimester'];

    // キャンペーン
    $resultTotal['summer'] = $data[0][config::SUMMER_CAMPAIGN_START_MONTH.'_result'] + $data[0][config::SUMMER_CAMPAIGN_END_MONTH.'_result'];
    $planTotal['summer']   = $data[0][config::SUMMER_CAMPAIGN_START_MONTH.'_plan']   + $data[0][config::SUMMER_CAMPAIGN_END_MONTH.'_plan'];
    $resultTotal['autumn'] = $data[0][config::AUTUMN_CAMPAIGN_START_MONTH.'_result'] + $data[0][config::AUTUMN_CAMPAIGN_END_MONTH.'_result'];
    $planTotal['autumn']   = $data[0][config::AUTUMN_CAMPAIGN_START_MONTH.'_plan']   + $data[0][config::AUTUMN_CAMPAIGN_END_MONTH.'_plan'];
    $resultTotal['spring'] = $data[0][config::SPRING_CAMPAIGN_START_MONTH.'_result'] + $data[0][config::SPRING_CAMPAIGN_END_MONTH.'_result'];
    $planTotal['spring']   = $data[0][config::SPRING_CAMPAIGN_START_MONTH.'_plan']   + $data[0][config::SPRING_CAMPAIGN_END_MONTH.'_plan'];
    
    // 返信用配列に格納
    $returndata['year']   = $fiscal_year;
    $returndata['item']   = $item;
    $returndata['pid']    = $pid;
    $returndata['result'] = $resultTotal;
    $returndata['plan']   = $planTotal;
    $returndata['campaign_info'] = $campaign_info;

    //printArray($returndata);
    return $returndata;
}

 /**
 * ----------------------------------------------------------
 * getResultAndPlanByExecutive()
 * 同友の種目毎の実績と計画値を年間、四半期、月別、キャンペーン別で取得する
 * @param $fiscal_year：指定年度
 * @param $item：種目
 * @param $pid：提携企業を指定する場合
 * @param $eid：同友を指定する場合
 * @param $round_flag：四捨五入するかどうか
 * @return $returndata：指定種目の年間、四半期、月別の値
 * ----------------------------------------------------------
 */
function getResultAndPlanByExecutive($fiscal_year, $item, $pid, $eid, $round_flag=true) {

    // 初期化
    $data = array();
    $resultTotal = array();
    $planTotal = array();
    $returndata = array();

    // 種目毎の実績と計画を取得
    if ($item === 'LM+LS') {
        $lm_data = getExecutiveResultTotalValue($fiscal_year, 'LM', '%', $pid, $eid);
        $ls_data = getExecutiveResultTotalValue($fiscal_year, 'LS', '%', $pid, $eid);

        for ($i = 1; $i <= 12; $i++) {
            $data[0][$i.'_result'] = $lm_data[0][$i.'_result'] + $ls_data[0][$i.'_result'];
            $data[0][$i.'_plan']   = $lm_data[0][$i.'_plan'] + $ls_data[0][$i.'_plan'];
        }
    }
    else {
        // LM+LS以外の時は、提携企業毎に実績、計画データを取得し、月毎に四捨五入する
        $tmp = getExecutiveResultTotalValue($fiscal_year, $item, '%', $pid, $eid);
        for ($i = 1; $i <= 12; $i++) {
            if ($round_flag) {
                $data[0][$i.'_result'] += round($tmp[0][$i.'_result']); // 実績を四捨五入する（★通常はこちら）
            }
            else {
                $data[0][$i.'_result'] += $tmp[0][$i.'_result'];        // 四捨五入しない場合
            }
            $data[0][$i.'_plan']   += $tmp[0][$i.'_plan'];
        }
    }
    //printArray($data);

    // 年間
    for ($i = 1; $i <= 12; $i++) {
        $resultTotal['year'] += $data[0][$i.'_result'];
        $planTotal['year']   += $data[0][$i.'_plan'];
    }
    // 第一四半期
    for ($i = 4; $i <= 6; $i++) {
        $resultTotal['1_quarter'] += $data[0][$i.'_result'];
        $planTotal['1_quarter']   += $data[0][$i.'_plan'];
    }
    // 第二四半期
    for ($i = 7; $i <= 9; $i++) {
        $resultTotal['2_quarter'] += $data[0][$i.'_result'];
        $planTotal['2_quarter']   += $data[0][$i.'_plan'];
    }
    // 第三四半期
    for ($i = 10; $i <= 12; $i++) {
        $resultTotal['3_quarter'] += $data[0][$i.'_result'];
        $planTotal['3_quarter']   += $data[0][$i.'_plan'];
    }
    // 第四四半期
    for ($i = 1; $i <= 3; $i++) {
        $resultTotal['4_quarter'] += $data[0][$i.'_result'];
        $planTotal['4_quarter']   += $data[0][$i.'_plan'];
    }
    // 第一三半期
    for ($i = 4; $i <= 7; $i++) {
        $resultTotal['1_trimester'] += $data[0][$i.'_result'];
        $planTotal['1_trimester']   += $data[0][$i.'_plan'];
    }
    // 第二三半期
    for ($i = 8; $i <= 11; $i++) {
        $resultTotal['2_trimester'] += $data[0][$i.'_result'];
        $planTotal['2_trimester']   += $data[0][$i.'_plan'];
    }
    // 第三三半期
    for ($i = 1; $i <= 3; $i++) {
        $resultTotal['3_trimester'] += $data[0][$i.'_result'];
        $planTotal['3_trimester']   += $data[0][$i.'_plan'];
    }
    $resultTotal['3_trimester'] += $data[0]['12_result'];
    $planTotal['3_trimester']   += $data[0]['12_plan'];

    // 上半期
    $resultTotal['1_half'] = $resultTotal['1_quarter']+$resultTotal['2_quarter'];
    $planTotal['1_half']   = $planTotal['1_quarter']+$planTotal['2_quarter'];

    // 下半期
    $resultTotal['2_half'] = $resultTotal['3_quarter']+$resultTotal['4_quarter'];
    $planTotal['2_half']   = $planTotal['3_quarter']+$planTotal['4_quarter'];

    // 月別
    for ($i = 1; $i <= 12; $i++) {
        $resultTotal[$i] = $data[0][$i.'_result'];
        $planTotal[$i]     = $data[0][$i.'_plan'];
    }

    // キャンペーン
    $resultTotal['summer'] = $data[0][config::SUMMER_CAMPAIGN_START_MONTH.'_result'] + $data[0][config::SUMMER_CAMPAIGN_END_MONTH.'_result'];
    $planTotal['summer']   = $data[0][config::SUMMER_CAMPAIGN_START_MONTH.'_plan']   + $data[0][config::SUMMER_CAMPAIGN_END_MONTH.'_plan'];
    $resultTotal['autumn'] = $data[0][config::AUTUMN_CAMPAIGN_START_MONTH.'_result'] + $data[0][config::AUTUMN_CAMPAIGN_END_MONTH.'_result'];
    $planTotal['autumn']   = $data[0][config::AUTUMN_CAMPAIGN_START_MONTH.'_plan']   + $data[0][config::AUTUMN_CAMPAIGN_END_MONTH.'_plan'];
    $resultTotal['spring'] = $data[0][config::SPRING_CAMPAIGN_START_MONTH.'_result'] + $data[0][config::SPRING_CAMPAIGN_END_MONTH.'_result'];
    $planTotal['spring']   = $data[0][config::SPRING_CAMPAIGN_START_MONTH.'_plan']   + $data[0][config::SPRING_CAMPAIGN_END_MONTH.'_plan'];
    
    // 返信用配列に格納
    $returndata['year']   = $fiscal_year;
    $returndata['item']   = $item;
    $returndata['pid']    = $pid;
    $returndata['eid']    = $eid;
    $returndata['result'] = $resultTotal;
    $returndata['plan']   = $planTotal;

    return $returndata;
}

 /**
 * ----------------------------------------------------------
 * getCalcCampeignPoint()
 * 種目毎のキャンペーンの得点を計算する
 * @param $fiscal_year：指定年度
 * @param $campaign：キャンペーン種別
 * @param $item：種目
 * @return $point
 * ----------------------------------------------------------
 */
function getCalcCampeignPoint($fiscal_year, $campaign, $item) {

    $resultTotal = 0;
    $point       = array();
    $resultData  = array();

    // キャンペーン種別判定
	switch($campaign) {
		case 'summer':
			$start_month = config::SUMMER_CAMPAIGN_START_MONTH;
			$end_month = config::SUMMER_CAMPAIGN_END_MONTH;
			break;
		case 'autumn':
			$start_month = config::AUTUMN_CAMPAIGN_START_MONTH;
			$end_month = config::AUTUMN_CAMPAIGN_END_MONTH;
			break;
		case 'spring':
			$start_month = config::SPRING_CAMPAIGN_START_MONTH;
			$end_month = config::SPRING_CAMPAIGN_END_MONTH;
			break;
	}

    // 分母同友数を取得
	$baseEnterableNum = count(getCampaignExecutiveEnterableList($fiscal_year, '%', $campaign));
	// 参加率の計算の場合に限り、退会(休会)中の同友は母数のカウントには含めない
    $ignoreRecessEnterableNum = count(getCampaignExecutiveEnterableList($fiscal_year, '%', $campaign, 'recess'));

    // 参加同友数(販売実績のある同友数)の取得
    if ($item === 'LM+LS') {
        $lmList = getPartnerList($fiscal_year, 'LM');
        $lsList = getPartnerList($fiscal_year, 'LS');
        $partnerList = array_merge($lmList, $lsList);
        $enterableNum = getCampaignMonthTotalExecutiveResultCount($fiscal_year,  $partnerList, '%', $start_month, $end_month);
    }
    else {
        $partnerList = getPartnerList($fiscal_year, $item);
        $enterableNum = getCampaignMonthTotalExecutiveResultCount($fiscal_year,  $partnerList, '%', $start_month, $end_month);
    }
    if ($enterableNum > $baseEnterableNum) {
        $enterableNum = $baseEnterableNum;
    }
    
    // 実績データを取得
    if ($item === 'LM+LS') {
        // LM、LSの実績取得
        $LM_resultData = getCampaignMonthTotalValue($fiscal_year, 'LM', 0, '%', 0, $start_month, $end_month);
        $LS_resultData = getCampaignMonthTotalValue($fiscal_year, 'LS', 0, '%', 0, $start_month, $end_month);
        $resultData['result'] = $LM_resultData['result'] + $LS_resultData['result'];
    }
    elseif ($item === 'LL' && local_config::FEATURE_SELECT_LL_PRINT_TOTAL) {
        $r_jaccs = array();
        $r_orico = array();
        
        // ジャックスとジャックス1/2、オリコとオリコ1/2は合算してから四捨五入するために
		// ジャックスとオリコでそれぞれ月別の合計値を保存しておく
        $partnerList = getPartnerList($fiscal_year, $item);
        foreach ($partnerList as $partnerArray) {
            $resultData = getCampaignMonthTotalValue($fiscal_year, $item, $partnerArray['value'], '%', 0, $start_month, $end_month);

            if(strpos($partnerArray['name'],'ジャックス') !== false || strpos($partnerArray['name'],'ｼﾞｬｯｸｽ') !== false || strpos($partnerArray['name'],'ロートピア/J') !== false){
				$r_jaccs[$start_month] += $resultData[$start_month.'_result'];
				$r_jaccs[$end_month]   += $resultData[$end_month.'_result'];
			}
			else {
				$r_orico[$start_month] += $resultData[$start_month.'_result'];
				$r_orico[$end_month]   += $resultData[$end_month.'_result'];
			}
        }
        // ジャックスとジャックス1/2、オリコとオリコ1/2は合算してから四捨五入する
        $resultTotal = round($r_jaccs[$start_month]) + round($r_jaccs[$end_month])
                     + round($r_orico[$start_month]) + round($r_orico[$end_month]);
        
        $resultData['result'] = $resultTotal;
        //DBGMSG('item='.$item.' resultData='.$resultData['result']);
    }
    else {
        $resultData['result'] = 0;
        $partnerList = getPartnerList($fiscal_year, $item);
        foreach ($partnerList as $partnerArray) {
            $resultData = getCampaignMonthTotalValue($fiscal_year, $item, $partnerArray['value'], '%', 0, $start_month, $end_month);
            $resultTotal += round($resultData[$start_month.'_result']);
            $resultTotal += round($resultData[$end_month.'_result']);
        }
        $resultData['result'] = $resultTotal;
    }

    // デバッグ用
    //DBGMSG($item.' ========================================');
    //DBGMSG('baseEnterableNum='.$baseEnterableNum.' ignoreRecessEnterableNum='.$ignoreRecessEnterableNum.' enterableNum='.$enterableNum);
    //printArray($partnerList);
    //printArray($resultData);


    // キャンペーン情報を取得
	// LM+LS対策：LM+LSの計画値はLM、LSの合算値
	if ($item === 'LM+LS') {
		$lmCampaignInfo = getCampaignInfo($fiscal_year, 'LM');
		$lsCampaignInfo = getCampaignInfo($fiscal_year, 'LS');
		
		$campaignInfo = array(
			'fiscal_year'            => $lmCampaignInfo['fiscal_year'],
			'item'                   => 'LM+LS',
			'summer_ave'             => $lmCampaignInfo['summer_ave'] + $lsCampaignInfo['summer_ave'],
			'summer_plan'            => $lmCampaignInfo['summer_plan'] + $lsCampaignInfo['summer_plan'],
			'summer_enterable_under' => $lmCampaignInfo['summer_enterable_under'],
			'summer_enterable_upper' => $lmCampaignInfo['summer_enterable_upper'],
			'summer_target'          => $lmCampaignInfo['summer_target'],
			'autumn_ave'             => $lmCampaignInfo['autumn_ave'] + $lsCampaignInfo['autumn_ave'],
			'autumn_plan'            => $lmCampaignInfo['autumn_plan'] + $lsCampaignInfo['autumn_plan'],
			'autumn_enterable_under' => $lmCampaignInfo['autumn_enterable_under'],
			'autumn_enterable_upper' => $lmCampaignInfo['autumn_enterable_upper'],
			'autumn_target'          => $lmCampaignInfo['autumn_target'],
			'spring_ave'             => $lmCampaignInfo['spring_ave'] + $lsCampaignInfo['spring_ave'],
			'spring_plan'            => $lmCampaignInfo['spring_plan'] + $lsCampaignInfo['spring_plan'],
			'spring_enterable_under' => $lmCampaignInfo['spring_enterable_under'],
			'spring_enterable_upper' => $lmCampaignInfo['spring_enterable_upper'],
			'spring_target'          => $lmCampaignInfo['spring_target'],
		);
		//printArray($campaignInfo);
	}
	else {
		// 各種目の本部計画値、キャンペーン参加同友数、目標同友会得点を取得
		$campaignInfo = getCampaignInfo($fiscal_year, $item);
	}

	$ave    = $campaign.'_ave';
	$plan   = $campaign.'_plan';
	
	$under  = $campaignInfo[$campaign.'_enterable_under'];
	$upper  = $campaignInfo[$campaign.'_enterable_upper'];
	$target = $campaignInfo[$campaign.'_target'];
	
	// デバッグ
	//echo '生産性=('.$resultData['result'].'/'.$baseEnterableNum.')/'.$campaignInfo[$ave].'<br />';
	//echo '参加率='.$enterableNum.'/'.$ignore_recess_enterable.'<br />';
	//echo '達成率='.$resultData['result'].'/'.$campaignInfo[$plan].'<br />';
	
	// 生産性計算
	// 計算式：四捨五入(実績 / 分母同友数) / 全国1同友あたり計画
	//$productPoint = ($resultData['result'] / ($baseEnterableNum * $campaignInfo[$ave]))*100;
	$productPoint = round($resultData['result'] / $baseEnterableNum, 1) / $campaignInfo[$ave] * 100;
	$productPoint = floor($productPoint);	// 切り捨て
	//echo '分母'.$baseEnterableNum;

	// 参加率計算
	// 計算式：参加同友数 / 分母同友数
	// 参加率の計算の場合に限り、退会(休会)中の同友は母数のカウントには含めない
	$enterablePoint = ($enterableNum*100 / $ignoreRecessEnterableNum) ;
	$enterablePoint = floor($enterablePoint);
	if ($enterablePoint > config::ENTERABLE_POINT_MAX) {
		$enterablePoint = config::ENTERABLE_POINT_MAX;
	}
	
	// 達成率計算
	// 計算式：実績 / 計画
	$reachPoint = ($resultData['result'] / $campaignInfo[$plan])*100;
	$reachPoint = floor($reachPoint);
	if ($reachPoint > config::REACH_POINT_MAX) {
		$reachPoint = config::REACH_POINT_MAX;
	}
    
    // 計算に使用した数値
    $point['enterableNum']             = $enterableNum;             // 参加同友数
    $point['baseEnterableNum']         = $baseEnterableNum;         // 分母同友数
    $point['ignoreRecessEnterableNum'] = $ignoreRecessEnterableNum; // 参加率の計算用分母同友数

    $point['campaign_plan_ave']        = $campaignInfo[$ave];       // 全国1同友あたり計画
    $point['campaign_result']          = $resultData['result'];     // キャンペーン期間中の実績
    $point['campaign_plan']            = $campaignInfo[$plan];      // 支部計画

	// 合計点
    $point['total']     = $productPoint+$enterablePoint+$reachPoint;
    $point['product']   = $productPoint;    // 生産性得点
    $point['enterable'] = $enterablePoint;  // 参加率得点
    $point['reach']     = $reachPoint;      // 達成率得点
    
    return $point;
}

 /**
 * ----------------------------------------------------------
 * getCalcCampeignBonusPoint()
 * 種目毎のキャンペーンのボーナス得点を計算する
 * @param $campaignPoint：生産性、参加率、達成率が入った配列
 * @param $fiscal_year：年度
 * @param $campaign   ：キャンペーン種別
 * @return $point
 * ----------------------------------------------------------
 */
function getCalcCampeignBonusPoint($campaignPoint, $fiscal_year=0, $campaign="") {

    $point = 0;

    // 特別キャンペーン(得点に関わらず固定の点数を与える)
    // ※2020年のCOLVIC-19のための対策を2020年サマーキャンペーンにおいて実施
    if (local_config::FEATURE_FIXED_POINT_AND_RATE) {
        $cnt = 0;
        foreach (config::CAMPAIGN_FIXED_POINT_TYPE as $value) {
            if ($value === $fiscal_year.'_'.$campaign) {
                // もし年度とキャンペーン種別が一致する組み合わせがあれば固定ポイントを戻す
                $point = config::CAMPAIGN_FIXED_POINT[$cnt];
                return $point;
            }
            $cnt++;
        }
    } // FEATURE_FIXED_POINT_AND_RATE

    if ($campaignPoint['total'] >= config::EXCELLENT_POINT_15_MAX && $campaignPoint['reach'] >= config::EXCELLENT_REACH_POINT_MAX) {
        $point = config::$EXECUTIVE_BONUS_POINT[2]['value'];
    }
    elseif ($campaignPoint['total'] >= config::EXCELLENT_POINT_10_MAX && $campaignPoint['reach'] >= config::EXCELLENT_REACH_POINT_MAX) {
        $point = config::$EXECUTIVE_BONUS_POINT[1]['value'];
    }
    elseif ($campaignPoint['total'] >= config::EXCELLENT_POINT_10_MAX) {
        $point = config::$EXECUTIVE_BONUS_POINT[0]['value'];
    }
    else {
        $point = 0;
    }

    return $point;
}

 /**
 * =================================================================
 *  Copyright(c) Incloop All Rights Reserved.
 * =================================================================
 */
?>