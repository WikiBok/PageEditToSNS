<?php
/**
 * TwitterOAuthの拡張クラス
 *   - Twitter.comへアクセスするためにProxyを使用する必要がある場合
 *     インスタンス作成後にsetProxyメソッドを利用する
 */
//必要なソースを読み込み(階層は設定によって変更)
require_once('OAuth.php');
require_once('twitteroauth.php');

class TwitterOAuthPlus extends TwitterOAuth {
  //初期設定はProxyを使用しない
  private $proxy = '';
  /**
   * プロキシサーバを設定する
   * @param $p プロキシサーバ設定文字列([サーバアドレス]:[ポートアドレス])
   */
  public function setProxy($p) {
    $this->proxy = $p;
  }
  /**
   * HTTPリクエストの送信メソッド
   *  - PROXYサーバ設定追加のため、オーバライド
   * @override
   * @return API results
   */
  function http($url, $method, $postfields = NULL) {
    //Proxy設定なしの場合、継承元のメソッドを実施
    if(empty($this->proxy)) {
      return parent::http($url, $method, $postfields);
    }
    $this->http_info = array();
    $ci = curl_init();
    /* Curl settings */
    curl_setopt($ci, CURLOPT_PROXY,$this->proxy); //ここを追加
    curl_setopt($ci, CURLOPT_USERAGENT, $this->useragent);
    curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, $this->connecttimeout);
    curl_setopt($ci, CURLOPT_TIMEOUT, $this->timeout);
    curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ci, CURLOPT_HTTPHEADER, array('Expect:'));
    curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, $this->ssl_verifypeer);
    curl_setopt($ci, CURLOPT_HEADERFUNCTION, array($this, 'getHeader'));
    curl_setopt($ci, CURLOPT_HEADER, FALSE);

    switch ($method) {
      case 'POST':
        curl_setopt($ci, CURLOPT_POST, TRUE);
        if (!empty($postfields)) {
          curl_setopt($ci, CURLOPT_POSTFIELDS, $postfields);
        }
        break;
      case 'DELETE':
        curl_setopt($ci, CURLOPT_CUSTOMREQUEST, 'DELETE');
        if (!empty($postfields)) {
          $url = "{$url}?{$postfields}";
        }
    }

    curl_setopt($ci, CURLOPT_URL, $url);
    $response = curl_exec($ci);
    $this->http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
    $this->http_info = array_merge($this->http_info, curl_getinfo($ci));
    $this->url = $url;
    curl_close ($ci);
    return $response;
  }
}
