<?php
// Bulk load libraries which are installed by composer
require_once __DIR__ . '/vendor/autoload.php';

use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use LINE\LINEBot\MessageBuilder\ImageMessageBuilder;
use LINE\LINEBot\MessageBuilder\LocationMessageBuilder;
use LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;
use LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;

$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));
$bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);
$signature = $_SERVER['HTTP_' . \LINE\LINEBot\Constant\HTTPHeader::LINE_SIGNATURE];

$inputString = file_get_contents('php://input');
$events = $bot->parseEventRequest($inputString, $signature);
error_log($inputString);

foreach($events as $event) {
  if ($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage) {
    $location = $event->getText();
  } else if ($event instanceof \LINE\LINEBot\Event\MessageEvent\LocationMessage) {
    $jsonString = file_get_contents ('https://maps.googleapis.com/maps/api/geocode/json?language=ja&latlng=' . $event->getLatitude() . ',' . $event->getLongitude());
    $json = json_decode($jsonString, true);
	$addressComponentArray = $json['results'][0]['address_components'];
	error_log('addressComponentArray' . $addressComponentArray);
	foreach($addressComponentArray as $addressComponent) {
	  if (in_array('administrative_area_level_1', $addressComponent['types'])) {
	    $prefName = $addressComponent['long_name'];
		break;
	  }
	}
	if ($prefName == '東京都') {
	  $loation = '東京';
	} else if ($prefName == '大阪府') {
	  $location = '大阪';
	} else {
	  foreach ($addressComponentArray as $addressComponent) {
	    if (in_array('locality', $addressComponent['types']) && !in_array('ward', $addressComponent['types'])) {
		  $location = $addressComponent['long_name'];
		  break;
		}
	  }
	}
  }

  $locationId;
  $client = new Goutte\Client();
  $crawler = $client->request('GET', 'http://weather.livedoor.com/forecast/rss/primary_area.xml');
  foreach($crawler->filter('channel ldWeather|source pref city') as $city) {
    if ($city->getAttribute('title') == $location || $city->getAttribute('title') . "市" == $location) {
      $locationId = $city->getAttribute('id');
      break;
    }
  }

  if(empty($locationId)) {
	if ($event instanceof \LINE\LINEBot\Event\MessageEvent\LocationMessage)  {
	   $loationId = $prefName;
	}
    $suggestArray = array();
    foreach($crawler->filter('channel ldWeather|source pref') as $pref) {
      if (strpos($pref->getAttribute('title'), $location) !== false) {
        foreach($pref->childNodes as $child) {
          if($child instanceof DOMElement && $child->nodeName == 'city') {
            array_push($suggestArray, $child->getAttribute('title'));
          }
        }
        break;
      }
    }

    if(count($suggestArray) > 0) {
      $actionArray = array();
      foreach($suggestArray as $city) {
        array_push($actionArray, new MessageTemplateActionBuilder($city, $city));
      }
      $builder = new TemplateMessageBuilder('見つかりませんでした',
          new ButtonTemplateBuilder('見つかりませんでした。', 'もしかして？', null, $actionArray));

      $bot->replyMessage($event->getReplyToken(), $builder);
    } else {
      replyTextMessage($bot, $event->getReplyToken(), '入力された地名が見つかりませんでした。市を入力してください。');
    }

    continiue;
  }

  $jsonString = file_get_contents('http://weather.livedoor.com/forecast/webservice/json/v1?city=' . $locationId);
  $json = json_decode($jsonString, true);
//  $date = date_parse_from_format('Y-m-d\TH:i:sP', $json['description']['publicTime']);
//  replyTextMessage($bot, $event->getReplyToken(), $json['description']['text'] . PHP_EOL . PHP_EOL . '最終更新:' . sprintf('%s月%s日%s時%s分', $date['month'], $date['day'], $date['hour'], $date['minite']));
//  replyTextMessage($bot, $event->getReplyToken(), $json['location']['city'] . 'の天気');
  foreach($json['forecasts'] as $fc) {
     $image_url = $fc['image']['url'];
	 $min = $fc['temperature']['min'];
	 $max = $fc['temperature']['max'];
	 $minCelsius = $fc['temperature']['min']['celsius'];
	 $maxCelsius = $fc['temperature']['max']['celsius'];
	 if (!isset($min)) { $minCelsius = "--"; }
	 if (!isset($max)) { $maxCelsius = "--"; }
	 replyMultiMessage($bot, $event->getReplyToken(), 
           new TextMessageBuilder($json['location']['city'] . 'の天気' . PHP_EOL . $fc['dataLabel'] . PHP_EOL . $fc['telop'] . PHP_EOL . $minCelsius . '/' . $maxCelsius));
//		   new ImageMessageBuilder($image_url, $image_url));
//         new TextMessageBuilder($json['location']['city'] . 'の天気' . PHP_EOL . $fc['dataLabel'] . PHP_EOL . $fc['telop'] . PHP_EOL . $json[forecasts][temperature][min] . '/' . $json[forecasts][temperature][max]),
//		 new ImageMessageBuilder($image_url, $image_url));
	 error_log($image_url);
  }
}

function replyLocationMessage($bot, $replyToken, $title, $address, $lat, $lon) {
  $response = $bot->replyMessage($replyToken, new LocationMessageBuilder($title, $address, $lat, $lon));
  if (!$response->isSucceeded()) {
    errorLog($response);
  }
}

function replyMultiMessage($bot, $replyToken, ...$msgs) {
  $builder = new MultiMessageBuilder();
  foreach($msgs as $msg) {
	$builder->add($msg);
  }
  $response = $bot->replyMessage($replyToken, $builder);
  if (!$response->isSucceeded()) {
    errorLog($response);
  }
}

function replyButtonsTemplate($bot, $replyToken, $alternativeText, $imageUrl, $title, $text, ...$actions) {
  $actionArray = array();
  foreach($actions as $value) {
    array_push($actionArray, $value);
  }

  $builder = new TemplateMessageBuilder($alternativeText,
                   new ButtonTemplateBuilder(
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

function replyTextMessage($bot, $replyToken, $text) {
  $response = $bot->replyMessage($replyToken, new TextMessageBuilder($text));
  if (!$response->isSucceeded()) {
    errorLog($response);
  }
}
?>
