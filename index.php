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
  $date = date_parse_from_format('Y-m-d\TH:i:sP', $json['description']['publicTime']);
  $detail = new TextMessageBuilder($json['location']['city'] . 'の天気' . PHP_EOL . $json['description']['text'] . PHP_EOL . PHP_EOL . '最終更新:' . sprintf('%s月%s日%s時%s分', $date['month'], $date['day'], $date['hour'], $date['minite']));

  $builder = new MultiMessageBuilder();
  $builder->add($detail);

  foreach($json['forecasts'] as $fc) {
     $image_url = $fc['image']['url'];
	 $min = $fc['temperature']['min'];
	 $max = $fc['temperature']['max'];
	 $minCelsius = $fc['temperature']['min']['celsius'];
	 $maxCelsius = $fc['temperature']['max']['celsius'];
	 if (!isset($min)) { $minCelsius = "--"; }
	 if (!isset($max)) { $maxCelsius = "--"; }
	 $msg = new TextMessageBuilder($json['location']['city'] . 'の天気' . PHP_EOL . $fc['dateLabel'] . ' ' . $fc['telop'] . PHP_EOL . $minCelsius . '/' . $maxCelsius . ' ℃');
	 $builder->add($msg);
	 $image;
	 if ($image_url == 'http://weather.livedoor.com/img/icon/1.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/1.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/2.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/2.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/3.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/3.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/4.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/4.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/5.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/5.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/6.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/6.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/7.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/7.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/8.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/8.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/9.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/9.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/10.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/10.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/11.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/11.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/12.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/12.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/13.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/13.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/14.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/14.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/15.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/15.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/16.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/16.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/17.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/17.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/18.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/18.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/19.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/19.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/20.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/20.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/21.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/21.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/22.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/22.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/23.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/23.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/24.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/24.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/25.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/25.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/26.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/26.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/27.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/27.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/28.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/28.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/29.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/29.jpg';}
	 else if ($image_url == 'http://weather.livedoor.com/img/icon/30.gif') { $image = 'https://' . $_SERVER['HTTP_HOST'] . '/imgs/30.jpg';}
//	 $imb = new ImageMessageBuilder($image, $image);
//	 $builder->add($imb);
//	 break;
     if (fc['dateLabel'] == '明日') {
	   break;
	 }
  }
  
  replyMultiMessages($bot, $event->getReplyToken(), $builder);
}

function replyLocationMessage($bot, $replyToken, $title, $address, $lat, $lon) {
  $response = $bot->replyMessage($replyToken, new LocationMessageBuilder($title, $address, $lat, $lon));
  if (!$response->isSucceeded()) {
    errorLog($response);
  }
}

function replyMultiMessages($bot, $replyToken, ...$msgs) {
  $builder = new MultiMessageBuilder();
  foreach($msgs as $msg) {
	$builder->add($msg);
  }
  $response = $bot->replyMessage($replyToken, $builder);
  if (!$response->isSucceeded()) {
    errorLog($response);
  }
}

function replyMultiMessage($bot, $replyToken, $builder) {
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
