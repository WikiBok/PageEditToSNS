<?php
require_once('HTTP/Request.php');
require_once('config/setting.php');
require_once('config/twitter.php');
require_once('class/twitteroauthplus.php');

if(!defined('API_URL')) define('API_URL','http://localhost/WikiBok/api.php');
if(!defined('API_BASIC_AUTH')) define('API_BASIC_AUTH','');
if(!defined('BASIC_AUTH_USER')) define('BASIC_AUTH_USER','');
if(!defined('BASIC_AUTH_PASS')) define('BASIC_AUTH_PASS','');
if(!defined('WIKI_BOT_USER')) define('WIKI_BOT_USER','WikiSysop');
if(!defined('WIKI_BOT_PASS')) define('WIKI_BOT_PASS','mediawiki');


class twitter_bot {
	const post_update = 'http://api.twitter.com/1/statuses/update.json';
	const post_mention = 'http://api.twitter.com/1/statuses/mentions.json';

	private $tw;
	private $api;

	/**
	 * コンストラクタ
	 */
	public function __construct($ck="",$cs="",$at="",$as="") {
		//省略時はデフォルト設定を利用
		$ck = ($ck == "") ? TWITTER_CONSUMER_KEY   : $ck;
		$cs = ($cs == "") ? TWITTER_CONSUMER_SECRET: $cs;
		$at = ($at == "") ? TWITTER_ACCESS_TOKEN   : $at;
		$as = ($as == "") ? TWITTER_ACCESS_SECRET  : $as;
		//リクエスト用インスタンス作成
		$this->tw = new TwitterOAuthPlus($ck,$cs,$at,$as);
		$this->tw->setProxy(TWITTER_PROXY);
		//Twitterサーバへの接続
		$this->api = new HTTP_Request();
		if(API_BASIC_AUTH !== '') {
			$this->api->setBasicAuth(BASIC_AUTH_USER,BASIC_AUTH_PASS);
		}
		$this->api->setMethod(HTTP_REQUEST_METHOD_POST);
		$this->api->setUrl(API_URL);
		$this->botLogin();
		return;
	}
	/**
	 * @Tweetを取得
	 */
	/*private*/ function fromMentions($page=false,$param="") {
		if($param == "") {
			$param = array(
			);
		}
		if($page) {
			$param['page'] = intval($page);
		}
		//結果を配列として返却する
		$mention = json_decode($this->tw->OAuthRequest(twitter_bot::post_mention,'GET',$param),true);
		if(!is_array($mention)) {
			return false;
		}
		else if(count($mention) < 1) {
			return false;
		}
		else {
			return $mention;
		}
	}
	/**
	 * 記事作成
	 */
	/*private*/ function create_description($mention) {
		/* user => name : 投稿者名称
		 * user => screen_name : 投稿者TwitterアカウントID
		 * in_reply_to_screen_name : BOTユーザ(@[in_reply_to_screen_name]を削除)
		 * text : 投稿内容(上記BOTユーザ名が入っている可能性あり)
		 */
		$user = $mention['user'];
		$me = $mention['in_reply_to_screen_name'];
		//mention用ワードの除去
		$pattern = '/\s*@'.$me.'\s*/';
		$message = $mention['text'];
		$message = preg_replace($pattern,'',$message);
		//スペースにて分割
		preg_match_all('/[^　 ]+/u',$message,$matchs);
		$match = $matchs[0];
		//先頭を記事名称/それ以降を記事内容として登録する
		$desc_title = array_shift($match);
		$desc_text = (is_array($match)) ? implode(' ',$match) : $match;
		$res = array(
			'tw_account' => $user['screen_name'],
			'tw_user' => $user['name'],
			'title' => $desc_title,
			'comment' => $desc_text
		);
		return $res;
	}
	/**
	 * WikiAPIへのPOSTリクエストを送信する
	 */
	private function postApiRequest($post="",$cook="") {
		$this->api->clearPostData();
		//パラメータ設定
		if(is_array($post)) {
			foreach($post as $k => $v) {
				$this->api->addPostData($k,$v);
			}
		}
		//クッキー設定
		if(is_array($cook)) {
			foreach($cook as $v) {
				$this->api->addCookie($v['name'],$v['value']);
			}
		}
		//リクエスト送信
		$res = $this->api->sendRequest();
		if(PEAR::isError($res)) {
			$result = false;
			$error = $this->api->getMessage();
		}
		else {
			$result = true;
			$data = $this->api->getResponseBody();
			$cookies = $this->api->getResponseCookies();
		}
		//通信失敗時はFALSE固定
		return ($result) ? array($data,$cookies) : false;
	}
	/**
	 * WikiへBOTユーザでログインしておく...
	 *  - Twitter経由での投稿はBOTユーザが作成した扱いとする
	 *    Wiki側の書き込み権限によらず投稿できるようにしておきたい...
	 */
	private function botLogin() {
		//仮ログイン
		$data = array(
			'format' => 'json',
			'action' => 'login',
			'lgname' => WIKI_BOT_USER,
			'lgpassword' => WIKI_BOT_PASS
		);
		$res = $this->postApiRequest($data);
		if($res !== false) {
			list($response,$cookie) = $res;
			$d = json_decode($response,true);
			$result = $d['login']['result'];
			$token = $d['login']['token'];
			//ログイントークンを設定
			if($result == 'NeedToken') {
				$data['lgtoken'] = $token;
				$res = $this->postApiRequest($data,$cookie);
			}
		}
		return;
	}
	/**
	 * Wiki編集用Tokenの取得リクエスト
	 * @param $page Tweetの内容
	 */
	public function getEditToken($page) {
		$data = array(
			'format'=>'json',
			'action'=>'query',
			'prop'=>'info',
			'intoken'=>'edit'
		);
		//名前空間の補完...
		$page_title = $page['title'];
		if(preg_match('/^Document:/',$page_title) === 0) {
			$page_title = 'Document:'.$page_title;
		}
		$data['titles'] = $page_title;

		$res = $this->postApiRequest($data); 
		if($res !== false) {
			list($body,$cookie) = $res;
			$mes = json_decode($body,true);
			$data = $mes['query']['pages'];
			$data = array_shift($data);
		}
		return ($res) ? $data : false;
	}
	/**
	 * Wiki記事内容更新処理
	 * @param page	Tweetの内容
	 * @param token	編集用Token
	 */
	public function setPageSummary($page,$token) {
		$data = array(
			'format'=>'json',
			'action'=>'edit',
			'summary'=>'edit'
		);
		//名前空間の補完...
		$page_title = $page['title'];
		if(preg_match('/^Document:/',$page_title) === 0) {
			$page_title = 'Document:'.$page_title;
		}
		$data['title'] = $page_title;
		//記事内容の作成(投稿者～を編集)
		$data['text'] = $page['comment'];
		if(isset($token['starttimestamp'])) {
			$data['basetimestamp'] =  $token['starttimestamp'];
		}
		if(isset($token['edittoken'])) {
			$data['token'] = $token['edittoken'];
		}
		//リクエスト送信
		$res = $this->postApiRequest($data);
		if($res !== false) {
			list($d,$cookie) = $res;
			$ed = json_decode($d,true);
			//競合発生
			if(isset($ed['error']) && isset($ed['error']['code']) && $ed['error']['code'] == 'editconflict') {
				return false;
			}
			else {
				return array($page,$data,$ed);
			}
		}
	}
	/** 
	 * デバッグ用出力ファイル作成
	 */
	public function debugOutput() {
		$args = func_get_args();
		//第1引数をファイル名
		$file = array_shift($args);
		//第2引数をメソッド名
		$func = array_shift($args);
		//残りの引数はそのままメソッドへ渡す
		$m = call_user_func_array(array(__CLASS__,$func),$args);
		//出力を整形
		$out = print_r($m,TRUE);
		file_put_contents($file,$out,FILE_APPEND);
	}
}
$file = './text.txt';
$tb = new twitter_bot();
$mentions = $tb->fromMentions();
if($mentions) {
	foreach($mentions as $mention) {
file_put_contents($file,print_r($mention,true),FILE_APPEND);
		$page = $tb->create_description($mention);
		$tb->debugOutput($file,'create_description',$mention);
	//	$token = $tb->getEditToken($page);
		//$tb->setPageSummary($page,$token);
	//	$tb->debugOutput($file,'setPageSummary',$page,$token);
	}
}
return true;
//$tb->debugOutput($file,'get_pages');
//$tb->debugOutput($file,'fromMentions');
