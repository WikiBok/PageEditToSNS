<?php
# 設置サーバ⇒TWITTERへのアクセスでPROXYを通す必要がある場合設定
define('TWITTER_PROXY',SNS_PROXY);
# APIリクエスト先(2012/08/02現在)
define('TWITTER_UPDATE_API','http://api.twitter.com/1/statuses/update.json');
# 情報配信用ハッシュタグ
define('TWITTER_HASH' , '');
# TWEETに設定されるリンク先
#  - BOKEditor/DescriptionEditor表示は#[記事名]で読み込み時に対象記事にフォーカスするため最後に#を設定
define('TWITTER_LINK' , '');

# ツイッターアプリとして申請した情報
define('TWITTER_CONSUMER_KEY','');
define('TWITTER_CONSUMER_SECRET','');
define('TWITTER_ACCESS_TOKEN','');
define('TWITTER_ACCESS_SECRET','');
# FOLLOW-APIリクエスト先(2012/08/02現在)
define('TWITTER_FOLLOW_API' , 'https://twitter.com/');
define('TWITTER_USER' , '');
