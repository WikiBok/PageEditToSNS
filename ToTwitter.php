<?php
/**
 * Special Page
 * @addtogroup Extensions
 * @author Aoyama
 */
if(!defined('MEDIAWIKI')) {
	die('This file is an Extension to the MediaWiki software and cannot be use standalone.');
}
require_once('config/twitter.php');
require_once('class/twitteroauthplus.php');
class ToTwitter {
	/**
	 * Twitterに更新情報をTweetする(OAuth認証形式)
	 */
	private function sendTweet($message) {
		$tw = new TwitterOAuthPlus(TWITTER_CONSUMER_KEY,TWITTER_CONSUMER_SECRET,TWITTER_ACCESS_TOKEN,TWITTER_ACCESS_SECRET);
		$tw->setProxy(TWITTER_PROXY);
		//長すぎる場合、分割ツイートする
		if(is_array($message) && (count($message) > 0)) {
			foreach($message as $key => $value) {
				//配列キーを連番として利用する
				$mes = wfMsg('tpe_continue',$key+1,$value);
				$tw->OAuthRequest(TWITTER_UPDATE_API,'POST',array('status'=>$mes));
			}
		}
		else {
			//同一内容の場合、リクエストエラーとなるが、同一人物の同一記事編集なので再ポストしない
			//※Twitter側の仕様上、他の編集が10件挟まるとポストできるため、更新通知として不要と判断
			$tw->OAuthRequest(TWITTER_UPDATE_API,'POST',array('status'=>$message));
		}
		return;
	}
	/**
	 * 言語ファイル+設定ファイルでTweet用文言を作成する
	 * @param $base		言語ファイルの対象キー
	 * @param $user		Userインスタンス
	 * @param $title	Titleインスタンス(Article->getTitle()で取得)
	 * @return	言語ファイルの文言に対して下記の置換処理をしたものを返す
	 *		$1 => [編集者]
	 *		$2 => [投稿記事(名前空間含む)]
	 *		$3 => [設定ファイルハッシュタグ]+[設定ファイルリンクURL]+[#投稿記事]
	 */
	private function createPageEditMessage($base,$user,$title) {
		$mb_chr = "UTF-8";
		//$3へ設定する文字列を作成(設定ファイルから変更可能)
		$add_param = array();
		if(defined('TWITTER_HASH') && TWITTER_HASH != '') {
			array_push($add_param,TWITTER_HASH);
		}
		if(defined('TWITTER_LINK') && TWITTER_LINK != '') {
			array_push($add_param,TWITTER_LINK.$title->getPartialURL());
		}
		//ユーザ名取得(本名を優先利用する)
		$userName = $user->getRealName();
		if(empty($userName)) {
			//本名の登録がない場合、IDを利用
			$userName = $user->getName();
		}
		//Wikiメッセージの置換処理を流用
		$message = wfMsg($base,array($userName,$title,implode(' ',$add_param)));
		//分割後メッセージの最大文字数を算出(ハッシュタグは必ず付ける)
		$tw_len = 139 - mb_strlen(TWITTER_HASH,$mb_chr);
		//文字数上限を超える場合、分割
		if(mb_strlen($message,$mb_chr) > 140) {
			$messages = array();
			//とりあえず、本文[投稿者、記事]を作成
			$mess = wfMsg($base,array($userName,$title,''));
			if(mb_strlen($mess,$mb_chr) > $tw_len) {
				//ハッシュタグが付けられない場合、分割用文言分を考慮する
				$tw_len -= mb_strlen(wfMsg('tpe_continue',0),$mb_chr);
				//空白以外の連続文字列を文字数で分割(<=空白があればそこで分割される)
				$pattern = '/[^\s]{1,'.$tw_len.'}/u';
				preg_match_all($pattern,$mess,$matches);
				foreach($matches[0] as $v) {
					//各Tweetにハッシュタグを付与
					$messages[] = $v." ".TWITTER_HASH;
				}
			}
			else {
				//ハッシュタグが付けられるので、URLだけ次Tweetにする
				$messages[] = $mess." ".TWITTER_HASH;
			}
			//$3への設定文字列がある場合のみ設定...
			if(count($add_param) > 0) {
				$messages[] = implode(' ',$add_param);
			}
			return $messages;
		}
		else {
			return $message;
		}
	}
	/**
	 * 記事新規作成時に呼び出す処理
	 * @param $article
	 * @param $user
	 * @param $text
	 * @param $summary
	 * @param $minoredit
	 * @param $watchthis
	 * @param $sectionanchor
	 * @param $flags
	 * @param $revision
	 */
	public static function onArticleInsertComplete(	&$article,
								&$user,$text,$summary,$minoredit,$watchthis,
								$sectionanchor,&$flags,$revision ) {
		//投稿メッセージ作成
		$message = self::createPageEditMessage('tpe_create_page',$user,$article->getTitle());
		self::sendTweet($message);
		//作成成功を戻す
		return true;
	}
	/**
	 * 記事編集終了時に呼び出す処理
	 * @param $article
	 * @param $user
	 * @param $text
	 * @param $summary
	 * @param $minoredit
	 * @param $watchthis
	 * @param $sectionanchor
	 * @param $flags
	 * @param $revision
	 * @param $status
	 * @param $baseRevId
	 */
	public static function onArticleSaveComplete(&$article,&$user,$text,$summary,
								$minoredit,$watchthis,$sectionanchor,&$flags,
								$revision,&$status,$baseRevId ){
		$message = self::createPageEditMessage('tpe_edit_page',$user,$article->getTitle());
		self::sendTweet($message);
		//編集成功を戻す...
		return true;
	}
	/**
	 * フォローボタンの追加
	 *   - BeforePageDisplayにフックするイベント
	 * @param $out
	 * @param $skin
	 */
	public static function onBeforePageDisplay(OutputPage $out,Skin $skin) {
		global $wgLanguageCode;
		//言語対応はTwitterに丸投げ
		$out->addHTML('<div id="wikibok-twitter"><a href="'.TWITTER_FOLLOW_API.TWITTER_USER.'" class="twitter-follow-button" data-show-count="true" data-lang="'.$wgLanguageCode.'"></a></div>');
		$out->addInlineScript('!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","'.TWITTER_USER.'");');
		return true;
	}
}
