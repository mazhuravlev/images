<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\PromiseInterface;

Route::get('/cache', function () {
    $keys = Redis::keys(env('CACHE_PREFIX') . '*');
    $values = $keys ? Redis::command('MGET', $keys) : [];
    $result = array_filter(
        array_map(
            function ($json) {
                return json_decode($json);
            },
            $values
        )
    );
    return response()->json($result);
});

Route::get('/', function () {
    return redirect('/index.html');
});

Route::post('/count_images', function () {
    $startTime = microtime(true);
    if (!$url = Input::get('url')) {
        return response()->json(
            [
                'status' => 'err',
                'msg' => 'no url in request'
            ]
        );
    }
    $cacheKey = env('CACHE_PREFIX') . $url;
    if ($cache = Redis::get($cacheKey)) {
        $response = json_decode($cache);
        if ($response) {
            $response->cached = true;
            $response->status = 'ok';
            $response->time = microtime(true) - $startTime;
            return response()->json($response);
        } else {
            Redis::del($cacheKey);
        }
    }
    $client = new GuzzleHttp\Client(
        [
            'base_uri' => $url,
            'verify' => false,
            'timeout' => 10
        ]
    );
    try {
        $page = $client->get('')->getBody()->getContents();
    } catch (GuzzleException $e) {
        return response()->json(
            [
                'status' => 'err',
                'msg' => 'unable to get page'
            ]
        );
    }
    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$document->loadHTML($page)) {
        return response()->json(
            [
                'status' => 'err',
                'msg' => 'invalid html from ' . $url
            ]
        );
    };
    $errors = [];
    $imagesProcessQueue = [];
    $xpath = new DOMXPath($document);
    foreach($xpath->query('//link[@rel=\'stylesheet\']') as $link) {
        if($cssHref = $link->getAttribute('href')) {
            try {
                $css = $client
                    ->get($cssHref)
                    ->getBody()
                    ->getContents();
            } catch (GuzzleException $e) {
                array_push($errors, 'unable to get css: ' . $cssHref);
                continue;
            }
            if(preg_match_all('/background(-image)?\s*:\s*url\s*\((\'|")?(.+?)(\'|")?\)/', $css, $matches)) {
                foreach(array_unique($matches[3]) as $imageSrc) {
                    array_push($imagesProcessQueue, $imageSrc);
                }
            }
        }
    }
    foreach ($xpath->query('//img') as $imgNode) {
        if ($src = $imgNode->getAttribute('src')) {
            array_push($imagesProcessQueue, $src);
        }
    }
    $imgCount = 0;
    $imgTotalSize = 0;
    $promises = [];
    foreach(array_unique($imagesProcessQueue) as $imageQueueSrc) {
        $promise = $client->headAsync($imageQueueSrc);
        array_push($promises, $promise);
        $promise->then(
            function (ResponseInterface $res) use(&$imgTotalSize, &$errors, $imageQueueSrc) {
                if($size = $res->getHeader('Content-Length')) {
                    $imgTotalSize += intval($size);
                } else {
                    array_push($errors, 'unable to get image size: ' . $imageQueueSrc);
                }
            },
            function (RequestException $e) use($imageQueueSrc, &$errors) {
                array_push($errors, 'unable to get image headers: ' . $imageQueueSrc);
            }
        );
    }
    set_time_limit(120);
    while(count($promises) > 0) {
        $promises = array_filter(
            $promises,
            function(PromiseInterface $promise) {
                return PromiseInterface::PENDING === $promise->getState();
            }
        );
    }
    $response = [
        'url' => $url,
        'imageCount' => $imgCount,
        'imageSize' => $imgTotalSize,
    ];
    //Redis::setex($cacheKey, 600, json_encode($response));
    if ($errors) {
        $response['errors'] = array_unique(array_values($errors));
    }
    $response['status'] = 'ok';
    $response['time'] = microtime(true) - $startTime;
    return response()->json($response);
});

//if(strstr($imageQueueSrc, 'data:image')) {
//    $imgCount++;
//    $imgTotalSize += strlen($imageQueueSrc);
//    continue;
//}
//try {
//    $headers = $client
//        ->head($imageQueueSrc)
//        ->getHeaders();
//} catch (GuzzleException $e) {
//    array_push($errors, 'unable to get image: ' . $imageQueueSrc);
//    continue;
//}
//if (array_key_exists('Content-Length', $headers)) {
//    if (is_array($headers) and array_key_exists('Content-Length', $headers)) {
//        $imgTotalSize += intval($headers['Content-Length'][0]);
//    } else {
//        array_push($errors, 'image_size');
//    }
//    $imgCount++;
//}