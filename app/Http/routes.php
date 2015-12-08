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

Route::get('/', function() {
   return redirect('/index.html');
});

Route::post('/count_images', function () {
    $startTime = microtime(true);
    if(!$url = Input::get('url')) {
        return response()->json(
            [
                'status' => 'err',
                'msg' => 'no url in request'
            ]
        );
    }
    if(Cache::has($url)) {
        $response = json_decode(Cache::get($url));
        $response->cached = true;
        $response->status = 'ok';
        $response->time = microtime(true) - $startTime;
        return response()->json($response);
    }
    $client = new GuzzleHttp\Client(
        [
            'base_uri' => $url,
            'verify' => false,
            'timeout' => 5
        ]
    );
    try {
        $page = $client->get($url)->getBody()->getContents();
    } catch(GuzzleException $e) {
        return response()->json(
            [
                'status' => 'err',
                'msg' => 'bad response'
            ]
        );
    }
    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    if(!$document->loadHTML($page)) {
        return response()->json(
            [
                'status' => 'err',
                'msg' => 'invalid html from ' . $url
            ]
        );
    };
    $imgCount = 0;
    $imgTotalSize = 0;
    $errors = [];
    foreach($document->getElementsByTagName('img') as $imgNode) {
        if($src = $imgNode->getAttribute('src')) {
            try {
                $headers = $client
                    ->head($src)
                    ->getHeaders();
            } catch(GuzzleException $e) {
                array_push($errors, 'bad response');
                continue;
            }
            if(array_key_exists('Content-Length', $headers)) {
                if(is_array($headers) and array_key_exists('Content-Length', $headers)) {
                    $imgTotalSize += intval($headers['Content-Length'][0]);
                } else {
                    array_push($errors, 'image_size');
                }
                $imgCount++;
            }
        } else {
            array_push($errors, 'image_src');
        }
    }
    $response = [
        'url' => $url,
        'imageCount' => $imgCount,
        'imageSize' => $imgTotalSize,
    ];
    Cache::put($url, json_encode($response), 10);
    if($errors) {
        $response['errors'] = array_unique(array_values($errors));
    }
    $response['status'] = 'ok';
    $response['time'] = microtime(true) - $startTime;
    return response()->json($response);
});