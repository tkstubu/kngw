/**
 * =================================================================
 * form.js
 * フォーム関連の処理を行うJavaScript
 * =================================================================
 */

/**
 * ----------------------------------------------------------
 * checkMessage()
 * ダイアログを開き、確認メッセージを表示する
 * @return true/false
 * ----------------------------------------------------------
 */
function checkMessage(str) {

	// 確認ダイアログを表示
	if (window.confirm(str)){
		return true;
	}
	else{
		return false;
	}
}

/**
 * ----------------------------------------------------------
 * changeSelectItemBoxDisplay()
 * 選択キャンペーンに応じて表示するセレクトボックスを変更する
 * @return
 * ----------------------------------------------------------
 */
function changeSelectItemBoxDisplay() {
	
	var year     = document.getElementById('fiscal_year');
	var campaign = document.getElementById('campaign');
	
	if (campaign.value === 'ALL') {
		document.getElementById('all_item').style.display = 'none';
		document.getElementById('2014_item').style.display = 'none';
		document.getElementById('none_item').style.display = '';
	}
	else if (campaign.value !== 'ALL' && year.value >= 2015) {
		document.getElementById('all_item').style.display = '';
		document.getElementById('2014_item').style.display = 'none';
		document.getElementById('none_item').style.display = 'none';
	}
	else {
		document.getElementById('all_item').style.display = 'none';
		document.getElementById('2014_item').style.display = '';
		document.getElementById('none_item').style.display = 'none';
	}
}

 /**
 * ----------------------------------------------------------
 * clickAuthSelectRadioButton()
 * 提携企業/同友会社を選択するラジオボタンをクリックした時に
 * 対応する箇所を表示する
 * @param auth:提携企業/同友会社
 * @return true/false
 * ----------------------------------------------------------
 */
function clickAuthSelectRadioButton(auth) {

	if (auth == 1) {
		// 提携企業
		document.getElementById('partner_item').style.display = '';
		document.getElementById('user_area').style.display = 'none';
		document.getElementById('user_info').style.display = 'none';
	}
	else if(auth == 2) {
		// 同友会社
		document.getElementById('partner_item').style.display = 'none';
		document.getElementById('user_area').style.display = '';
		document.getElementById('user_info').style.display = '';
	}
	else if(auth == 3) {
		// 調整項目
		document.getElementById('partner_item').style.display = 'none';
		document.getElementById('user_area').style.display = '';
		document.getElementById('user_info').style.display = 'none';
	}
	else if(auth == 4) {
		// 同友役員
		document.getElementById('partner_item').style.display = 'none';
		document.getElementById('user_area').style.display = 'none';
		document.getElementById('user_info').style.display = 'none';
	}
	else {
		document.getElementById('partner_item').style.display = 'none';
		document.getElementById('user_area').style.display = 'none';
		document.getElementById('user_info').style.display = 'none';
	}
}

/**
 * ----------------------------------------------------------
 * changeSelectBoxContents()
 * JSON形式でデータベースから種目に該当する提携企業を読み込み
 * @param 
 * ----------------------------------------------------------
 */
function changeSelectBoxContents(planItemCombine) {

	var param = "";
	var fiscal_year = document.getElementsByName('fiscal_year');
	var item = document.getElementsByName('item');
	var partner = document.getElementById('partner');
	var period_btn = document.getElementsByName('period_btn');

	// IE11対策
	if(typeof planItemCombine === 'undefined') { planItemCombine = null; }

	// 計画の分離の有無
	var separate_partner = document.getElementById('separate_partner');
	if (separate_partner !== null && planItemCombine !== null) {
		if (planItemCombine.indexOf(item[0].value) >= 0) {
			separate_partner.style.display = "none";
		}
		else {
			separate_partner.style.display = "inline";
		}
	}

	// LCの時は「月別表示」だけにする
	var period_btn = document.getElementsByName('period_btn');
	if (period_btn.length > 0) {
		if (item[0].value === "LC:spitem") {
			period_btn[0].style.display = "none";
		}
		else {
			period_btn[0].style.display = "inline";
		}
	}
	
	// selectの中身を空にする
	for (var i = 0; i < partner.options.length;) {
		partner.options[i] = null;
	}
	
	if (item[0].value === "ALL" || item[0].value === "NONE") {
		partner.add(new Option('----'), 0);
		partner[0].value = 'NONE';
	}
	else {
		// パラメータ生成
		param += "fiscal_year=" + fiscal_year[0].value;
    	
    	if (document.getElementById('result_view') != null) {
			param += "&plist=" + 'all';
		}
    	else if (document.getElementById('result_input') != null) {
			param += "&plist=" + 'limit';
		}
    	
    	sendRequest("POST", "./common/json.php?command=get_partner_list", param, false, callback);
	}
}

/**
 * ----------------------------------------------------------
 * changePartnerSelectList()
 * セレクトボックスの内容を選択している種目の内容に切り替え
 * @param 
 * ----------------------------------------------------------
 */
function changePartnerSelectList(partnerList) {
	
	var itemName = '';
	var item = document.getElementById('item');
	var partner = document.getElementById('partner');
	
	// itemが補助種目の場合はメイン種目を抜き出し
	if (item.value.indexOf(':sub_') != -1) {
		itemArray = item.value.split(':');
		itemName = itemArray[0];
	}
	// 特別種目の場合
	else if (item.value.indexOf(':spitem') != -1) {
		itemArray = item.value.split(':');
		itemName = itemArray[0];
	}
	else {
		itemName = item.value;
	}

	// もしitemがLM+LSの場合は「合計」を入れて終了
	// if (itemName === 'LM+LS') {
	// 	partner.add(new Option('合計'), partner.options[0]);
	// 	partner[0].value = 'TOTAL';
	// 	return;
	// }
	
	// itemを持つ提携企業のみ表示
	var cnt = 0;
	for (var i = 0; i < partnerList.length; i++) {
		if (partnerList[i].item === itemName) {
		//if (itemName.indexOf(partnerList[i].item) === 0) {
			partner.options[cnt] = new Option(partnerList[i].name);
			partner[cnt].value = partnerList[i].value;
			cnt++;
		}
		// LMSの場合はLMとLSの提携企業を格納する
		if (itemName === 'LM+LS') {
			if (partnerList[i].item === "LM" || partnerList[i].item === "LS") {
				partner.options[cnt] = new Option(partnerList[i].name);
				partner[cnt].value = partnerList[i].value;
				cnt++;
			}
		}
}

	// 提携企業が0の場合は、ハイフンで表示
	if (cnt == 0) {
		partner.add(new Option('----'), 0);
		partner[0].value = 'NONE';
	}
	else if (cnt > 0) {
		if (document.getElementById('result_view') != null) {
			partner.add(new Option('合計'), partner.options[0]);
			partner[0].value = 'TOTAL';
			partner.selectedIndex = 0;
		}
		partner.selectedIndex = 0;
	}
}

/**
 * ----------------------------------------------------------
 * openBundleInputWindow()
 * 実績を一括入力するための子ウインドウを開く
 * @param 
 * ----------------------------------------------------------
 */
function openBundleInputWindow () {
	
	window.open('./bundleinput.php', '同友実績一括入力', 'menubar=no, resizable=yes、scrollbars=yes, width=700, height=750');
	window.moveTo(100,100);
}

/**
 * ----------------------------------------------------------
 * closeBundleInputWindow()
 * 実績を一括入力するための子ウインドウを閉じる
 * また、テキストエリアの実績値を親ウインドウに反映する
 * @param 
 * ----------------------------------------------------------
 */
function closeBundleInputWindow () {
	
	// テキストエリアの情報を取得
	var data = document.getElementsByName('bandleinputarea');
	var textdata = data[0].value;
	//window.alert(resultdata);	// デバッグ用メッセージウインドウ
	
	// 改行前のスペースを削除
	textdata = textdata.replace(/\s\r\n|\s\r|\s\n/g, '\n');
	textdata = textdata.replace(/\n+$/g, '');

	// テキストエリアの内容を1行ずつ配列に格納
	resultData = textdata.split(/\r\n|\r|\n/);

	// もし同友コードが含まれている場合は同友コードを参照して実績を入力する
	var code_exist_flag = false;
	if (resultData.length > 0) {
		if (resultData[0].indexOf('\t') !== -1 || resultData[0].indexOf(' ') !== -1 || resultData[0].indexOf('　') !== -1) {
			code_exist_flag = true;
		}
	}
	
	// テキストエリアに入力がある場合だけ格納する
	if (resultData.length > 0) {

		// 同友コードが記載されていない場合の一括入力
		if (!code_exist_flag) {
	
			// 親ウインドウのテキストウィンドウ情報を取得
			var resultTextBoxArray = window.opener.document.getElementsByTagName("input");	// inputタグをすべて取得
			
			// 実績入力用のテキストボックスに値を入力
			var cnt = 0;
			for (var i = 0; i < resultTextBoxArray.length; i++) {
				if (resultTextBoxArray[i].name.indexOf('_result') !== -1 || resultTextBoxArray[i].name.indexOf('plan') !== -1) {
				
					// 入力がある行まで
					if (resultData.length > cnt) {
						// 入力値ある場合のみ格納
						if (resultData[cnt].length > 0) {
						
							// カンマを取り除いた数値をテキストボックスに格納
							resultTextBoxArray[i].value = resultData[cnt].split(",").join("");
						}
						cnt++;
					}
					else {
						break;
					}
				}
			}
		}
		// 同友コードが記載されている場合の一括入力
		else {
			var resultTextBoxArray = window.opener.document.getElementsByTagName("tr");	// trタグをすべて取得
			var url = window.opener.location.href ;

			// URLから一括入力の画面が協力企業の画面かどうかを判定
			flag_cooperation = false;
			if (url.indexOf('cooperation') !== -1) {
				flag_cooperation = true;
			}

			for (var i = 0; i < resultData.length; i++) {
				// ウインドウに貼り付けたデータから同友コードと、数値（あるだけ）を取得し、配列に格納する。
				//e_data = resultData[i].split(/\t|\s/);
				//e_data = resultData[i].match(/(\d+)[^\d-]+([\d\.,-]+)[^\d]?/);
				e_data = resultData[i].match(/([\d\.,-]+)/g);

				// 区切りを検出できた行のみ処理する
				if (e_data.length > 1) {

					// 指定した同友コードを持つ行を探して入力する
					for (var j = 3; j < resultTextBoxArray.length; j++) {
						if (resultTextBoxArray[j].childNodes[0].id == e_data[0]) {

							// tdの数をカウントして、3なら年間計画、5または7(LLの場合)ならキャンペーンの計画入力の統合、それ以外はキャンペーンの分離
							if (resultTextBoxArray[j].childElementCount == 3) {
								// 年間計画
								resultTextBoxArray[j].childNodes[2].childNodes[0].value = e_data[1].replace(',', '');	// カンマ除去	
							}
							else if (resultTextBoxArray[j].childElementCount == 4) {
								if (flag_cooperation) {
									// 協力企業の実績入力画面の場合
									resultTextBoxArray[j].childNodes[1].childNodes[0].value = e_data[1].replace(',', '');	// カンマ除去
									resultTextBoxArray[j].childNodes[2].childNodes[0].value = e_data[2].replace(',', '');	// カンマ除去
									resultTextBoxArray[j].childNodes[3].childNodes[0].value = e_data[3].replace(',', '');	// カンマ除去
								}
								else {
									// キャンペーンの計画入力の提携企業統合版（提携が1社しかないケース）
									resultTextBoxArray[j].childNodes[3].childNodes[0].value = e_data[1].replace(',', '');	// カンマ除去
								}
							}
							else if (resultTextBoxArray[j].childElementCount == 5) {
								// キャンペーンの計画入力の提携企業統合版（LL以外で提携が複数あるケース）
								resultTextBoxArray[j].childNodes[4].childNodes[0].value = e_data[1].replace(',', '');	// カンマ除去
							}
							else if (resultTextBoxArray[j].childElementCount == 7) {
								// キャンペーンの計画入力の提携企業統合版（LLの場合）
								resultTextBoxArray[j].childNodes[6].childNodes[0].value = e_data[1].replace(',', '');	// カンマ除去
							}
							else {
								// キャンペーンの計画入力の提携企業分離板
								for (var datacnt = 1; datacnt < e_data.length; datacnt++) {
									//resultTextBoxArray[j].childNodes[5].childNodes[0].value = e_data[1].replace(',', '');	// カンマ除去
									//resultTextBoxArray[j].childNodes[9].childNodes[0].value = e_data[2].replace(',', '');	// カンマ除去

									dataindex = 5 + 4 * (datacnt - 1);
									resultTextBoxArray[j].childNodes[dataindex].childNodes[0].value = e_data[datacnt].replace(',', '');	// カンマ除去
								}
							}
							break;
						}
					}
				}
			}
		}
	}
	
	// ウインドウを閉じる
	window.close();
}

/**
 * ----------------------------------------------------------
 * checkAndCondition()
 * AND条件の場合、1行目が達成で2行目が未達成の時の場合に限り、1行目の賞金額を達成時の賞金に移動させる
 * @param id 
 * ----------------------------------------------------------
 */
function checkAndCondition(id) {
	// 現在の行数を取得
	var line = document.getElementById(id);

	// 一つ上の行の情報を取得
	var upper_line = line.previousElementSibling;

	// 一つ上の行が達成済みで、現在の行が達成ではない場合
	if (upper_line.childNodes[9].innerText.indexOf('達成') != -1 && line.childNodes[9].innerText.indexOf('達成') == -1) {
		// 現在の賞金額を0にして、達成時の賞金に入れ直す
		upper_line.childNodes[10].innerText = upper_line.childNodes[11].innerText;
		upper_line.childNodes[11].innerText = 0;
	}

	// ANDの行は達成時の賞金と現在の賞金額はハイフンにしておく
	line.childNodes[10].innerText = '----';
	line.childNodes[10].style.textAlign = 'center';
	line.childNodes[11].innerText = '----';
	line.childNodes[11].style.textAlign = 'center';
}

/**
 * ----------------------------------------------------------
 * checkOrCondition()
 * OR条件の場合、1行目と2行目が達成している場合、1行目を有効にする
 * @param id 
 * ----------------------------------------------------------
 */
function checkOrCondition(id) {
	// 現在の行数を取得
	var line = document.getElementById(id);

	// 一つ上の行の情報を取得
	var upper_line = line.previousElementSibling;

	// 両方が達成の時
	if (upper_line.childNodes[9].innerText.indexOf('達成') != -1 && line.childNodes[9].innerText.indexOf('達成') != -1) {
		// ORの行は達成時の賞金と現在の賞金額はハイフンにしておく
		line.childNodes[10].innerText = '----';
		line.childNodes[10].style.textAlign = 'center';
		line.childNodes[11].innerText = '----';
		line.childNodes[11].style.textAlign = 'center';
	}
	// 上の行が達成で、下が達成ではない場合、下の行をハイフンにする
	else if (upper_line.childNodes[9].innerText.indexOf('達成') != -1 && line.childNodes[9].innerText.indexOf('達成') == -1) {
		line.childNodes[9].innerText = '----';
		line.childNodes[9].style.textAlign = 'center';
		line.childNodes[10].innerText = '----';
		line.childNodes[10].style.textAlign = 'center';
		line.childNodes[11].innerText = '----';
		line.childNodes[11].style.textAlign = 'center';
	}
	// 下の行が達成で、上が達成ではない場合、上の行をハイフンにする
	else if (upper_line.childNodes[9].innerText.indexOf('達成') == -1 && line.childNodes[9].innerText.indexOf('達成') != -1) {
		upper_line.childNodes[9].innerText = '----';
		upper_line.childNodes[9].style.textAlign = 'center';
		upper_line.childNodes[9].style.backgroundColor = '#fff';
		upper_line.childNodes[10].innerText = '----';
		upper_line.childNodes[10].style.textAlign = 'center';
		upper_line.childNodes[11].innerText = '----';
		upper_line.childNodes[11].style.textAlign = 'center';
	}
}

/**
 * ----------------------------------------------------------
 * openLoginHistory()
 * ログイン履歴の一覧を子ウインドウで表示する
 * @param param ログイン情報を連結したもの
 * ----------------------------------------------------------
 */
function openLoginHistory(name, param) {

	url = './loginhistory.php?name=' +name+ '&' + param;

    window.open(url, 'ログイン履歴表示', 'menubar=no, width=400, height=630');
}

/**
 * ----------------------------------------------------------
 * sendRequest()
 * HTTPリクエストを送信
 * @param method：GET/POST
 * @param url：URL
 * @param param：パラメータ
 * @param async：同期/非同期
 * @param callback：コールバック関数
 * ----------------------------------------------------------
 */
function sendRequest (method, url, param, async, callback) {
	
    // XMLHttpRequestオブジェクト生成
    var xmlhttp = createHttpRequest();
    
    // 受信時に起動するイベント
    xmlhttp.onreadystatechange = function() { 
        // readyState値は4で受信完了
        if (xmlhttp.readyState == 4) { 
            //コールバック
            callback(xmlhttp);
        }
    }
    
    // open メソッド
    xmlhttp.open(method, url, async);
    
    // HTTPリクエストヘッダを設定
    xmlhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    
    // send メソッド
    xmlhttp.send(param);
}

/**
 * ----------------------------------------------------------
 * callback()
 * コールバック関数
 * ----------------------------------------------------------
 */
function callback(xmlhttp) {
	
	// 応答受信
    var response = xmlhttp.responseText;
    
    // JSONデータ読み込み
    var jsonData = JSON.parse(response);
    
    // commandに応じて処理を振り分け
    switch (jsonData.command) {
		case 'get_partner_list':
			changePartnerSelectList(jsonData.partner, jsonData.index);
			break;
		default:
			break;
	}
} 

/**
 * ----------------------------------------------------------
 * createHttpRequest()
 * XMLHttpRequestオブジェクト生成
 * ----------------------------------------------------------
 */
function createHttpRequest() {
	
	var xmlhttp = null;
	
	if (window.ActiveXObject) {
		try {
			// MSXML2以降用
			xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
		}
		catch (e) {
			try {
		    	// 旧MSXML用
		    	xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
		  	}
		  	catch (e2) {
		  	}
		}
	}
	else if(window.XMLHttpRequest) {
		// Win Mac Linux m1,f1,o8 Mac s1 Linux k3用
		xmlhttp = new XMLHttpRequest();
	}
	else {
	}

	if (xmlhttp == null) {
		alert("Can not create an XMLHTTPRequest instance");
	}
	
	return xmlhttp;
}