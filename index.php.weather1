<?php
// Bulk load libraries which are installed by composer
require_once __DIR__ . '/vendor/autoload.php';

$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));
$bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);
$signature = $_SERVER['HTTP_' . \LINE\LINEBot\Constant\HTTPHeader::LINE_SIGNATURE];

$inputString = file_get_contents('php://input');
$events = $bot->parseEventRequest($inputString, $signature);
error_log($inputString);

foreach($events as $event) {
  replyButtonsTemplate($bot, $event->getReplyToken(), 'お天気お知らせ - 今日の天気予報は晴れです',
                  'https://' . $_SERVER['HTTP_HOST'] . '/imgs/template.jpg',
                  'お天気お知らせ', '今日の天気予報は晴れです。',
                  new \LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('明日の天気', 'tomorrow'),
                  new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('週末の天気', 'weekend'),
                  new \LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder('Webで見る', 'http://google.jp'));

}

function replyLocationMessage($bot, $replyToken, $title, $address, $lat, $lon) {
  $response = $bot->replyMessage($replyToken, 
        new \LINE\LINEBot\MessageBuilder\LocationMessageBuilder($title, $address, $lat, $lon));
  if (!$response->isSucceeded()) {
    errorLog($response);
  }
}

function replyButtonsTemplate($bot, $replyToken, $alternativeText, $imageUrl, $title, $text, ...$actions) {
  $actionArray = array();
  foreach($actions as $value) {
    array_push($actionArray, $value);
  }

  $builder = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder($alternativeText,
                   new \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder(
                      $title, $text, $imageUrl, $actionArray));
  $response = $bot->replyMessage($replyToken, $builder);
  if (!$response->isSucceeded()) {
    errorLog($response);
  }
}

function errorLog($response) {
  if (!$response->isSucceeded()) {
    error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

?>
