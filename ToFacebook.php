<?php
/**
 * Special Page
 * @addtogroup Extensions
 * @author Aoyama
 */
if(!defined('MEDIAWIKI')) {
	die('This file is an Extension to the MediaWiki software and cannot be use standalone.');
}
require_once('config/facebook.php');
require_once('class/facebook.php');
class ToFacebook {
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
		//投稿メッセージ作成
		$message = self::createPageEditMessage('tpe_edit_page',$user,$article->getTitle());
		//編集成功を戻す...
		return true;
	}
	public static function onBeforePageDisplay(OutputPage $out,Skin $skin) {
		$fb = new Facebook(array(
			'appId'  => FB_APPID,
			'secret' => FB_SECRET,
			'fileUpload' => FB_USE_FILEUPLOAD,
		));
		$user = $fb->getUser();
		if($user) {
			try{
				$user_profile = $fb->api('/me');
			}
			catch(FacebookApiException $e) {
				$u = null;
			}
		}
		if($user) {
			$out->addHTML('<span id="fbLogout"><a class="fb_button fb_button_medium"><span class="fb_button_text">'.wfMsg('tpe_facebook_already_logon').'</span></a></span>');
		}
		else {
			$out->addHTML('<fb:login-button></fb:login-button>');
		}
$scr = "<script>
window.fbAsyncInit = function() {
	FB.init({
		appId: '".$fb->getAppID()."',
		cookie: true,
		xfbml: true,
		oauth: true
	});
	FB.Event.subscribe('auth.login', function(response) {
		window.location.reload();
	});
	FB.Event.subscribe('auth.logout', function(response) {
		window.location.reload();
	});
};
(function() {
	var	e = document.createElement('script');
	e.async = true;
	e.src = document.location.protocol +
			'//connect.facebook.net/en_US/all.js';
	document.getElementById('fb-root').appendChild(e);
}());
</script>";
		$out->addHTML('<div id="fb-root"></div>');
		$out->addHTML($scr);
		
		return true;
	}
}
