<?php
/**
 * Special Page
 * @addtogroup Extensions
 * @author Aoyama
 */
if(!defined('MEDIAWIKI')) {
	die('This file is an Extension to the MediaWiki software and cannot be use standalone.');
}
$dir = dirname(__FILE__);
require_once('config/setting.php');
$wgExtensionCredits['PageEditToSNS'][] = array(
	'path' => __FILE__,
	'name' => 'PageEditToSNS',
	'author' => array( 'Aoyama Univ', '...' ),
	'url' => '',
	'version' => '0.9',
);
$wgAutoloadClasses['ToTwitter'] = "$dir/ToTwitter.php";
$wgAutoloadClasses['ToFacebook'] = "$dir/ToFacebook.php";
$wgExtensionMessagesFiles['PageEditToSNS'] = "$dir/PageEditToSNS.i18n.php";
$wgExtensionFunctions[] = 'efPageEditToSNS';
/**
 * 拡張機能使用時に実行する関数
 */
function efPageEditToSNS() {
	global $wgHooks;
	//メッセージデータの読み込み
	wfLoadExtensionMessages('PageEditToSNS');
	//追加HTMLの読み込み
	if((defined('SEND_TWITTER') && SEND_TWITTER) || (defined('SEND_FACEBOOK') && SEND_FACEBOOK)) {
		$wgHooks['BeforePageDisplay'][] = 'efPageEditToSNSInsertHtml';
	}
	//Twitter
	if(defined('SEND_TWITTER') && SEND_TWITTER) {
		//記事変更イベント
		$wgHooks['ArticleSaveComplete'][] = 'ToTwitter::onArticleSaveComplete';
		//記事登録イベント
		$wgHooks['ArticleInsertComplete'][] = 'ToTwitter::onArticleInsertComplete';
	}
	//Facebook
	if(defined('SEND_FACEBOOK') && SEND_FACEBOOK) {
		//記事変更イベント
		$wgHooks['ArticleSaveComplete'][] = 'ToFacebook::onArticleSaveComplete';
		//記事登録イベント
		$wgHooks['ArticleInsertComplete'][] = 'ToFacebook::onArticleInsertComplete';
	}
}
/**
 * 固定リンクの追加
 *  - TwitterBOTユーザのフォローボタン
 *  - FacebookAppの認証・ログイン/ログアウト
 */
function efPageEditToSNSInsertHtml(OutputPage $out,Skin $skin) {
	global $wgLanguageCode;
	//SNS用のタグをひとまとめにする
	$out->addHTML('<div id="wikibok-sns">');
	if(defined('SEND_TWITTER') && SEND_TWITTER) {
		ToTwitter::onBeforePageDisplay($out,$skin);
	}
	if(defined('SEND_FACEBOOK') && SEND_FACEBOOK) {
		ToFacebook::onBeforePageDisplay($out,$skin);
	}
	$out->addHTML('</div>');
	return true;
}
