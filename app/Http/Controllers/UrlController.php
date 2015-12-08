<?php

namespace App\Http\Controllers;

use DOMDocument;
use DOMNode;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Promise;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redis;
use Psr\Http\Message\ResponseInterface;

class UrlController extends Controller
{

    private static function makePromisesFromQueue(array $imgQueue, Client $client, array &$imgSizes, array &$errors)
    {
        /** @var Promise[] $promises */
        $promises = [];
        foreach($imgQueue as $imageSrc) {
            $promise = $client->headAsync($imageSrc)
                ->then(
                    function (ResponseInterface $res) use(&$imgSizes, &$errors, $imageSrc) {
                        if($headers = $res->getHeaders()
                            and array_key_exists('Content-Length', $headers)
                            and is_array($headers['Content-Length'])
                            and count($headers['Content-Length']) > 0) {
                            array_push($imgSizes, intval($headers['Content-Length'][0]));
                        } else {
                            array_push($errors, 'unable to get image size: ' . $imageSrc);
                        }
                    },
                    function (RequestException $e) use($imageSrc, &$errors) {
                        array_push($errors, 'unable to get image headers: ' . $imageSrc);
                    }
                );
            array_push($promises, $promise);
        }
        return $promises;
    }

    private static function getCssImages(\DOMNodeList $linkNodes, Client $client, array &$errors)
    {
        $result = [];
        foreach($linkNodes as $link) {
            /** @var DOMNode $link */
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
                    $result = array_merge($result, array_unique($matches[3]));
                }
            }
        }
        return $result;
    }

    private static function getCache($cacheKey, $startTime)
    {
        if ($cache = Redis::get($cacheKey)) {
            if ($response = json_decode($cache)) {
                $response->cached = true;
                $response->status = 'ok';
                list($sec, $usec) = explode('.', $startTime);
                $response->timestamp = date('H:i:s:', $sec) . substr($usec, 0, 2);
                $response->time = microtime(true) - $startTime;
                return $response;
            } else {
                Redis::setex($cacheKey, 1, '');
                return null;
            }
        }
    }

    private static function errResponse($reason)
    {
        return response()->json(
            [
                'status' => 'err',
                'msg' => $reason
            ]
        );
    }

    public function process()
    {
        $startTime = microtime(true);
        if (!$url = Input::get('url')) {
            return self::errResponse('no url in request');
        }
        $cacheKey = env('CACHE_PREFIX') . $url;
        if($cacheResult = self::getCache($cacheKey, $startTime)) {
            return response()->json($cacheResult);
        }
        $client = new \GuzzleHttp\Client(
            [
                'base_uri' => $url,
                'verify' => false,
            ]
        );
        try {
            $page = $client->get('')->getBody()->getContents();
        } catch (GuzzleException $e) {
            return self::errResponse('unable to get page');
        }
        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        if (!$document->loadHTML($page)) {
            return self::errResponse('invalid html from ' . $url);
        };
        $errors = [];
        $xpath = new DOMXPath($document);
        $imagesQueue = self::getCssImages(
            $xpath->query('//link[@rel=\'stylesheet\']'),
            $client,
            $errors
        );
        foreach ($xpath->query('//img') as $imgNode) {
            if ($src = $imgNode->getAttribute('src')) {
                array_push($imagesQueue, $src);
            }
        }
        $imgSizes = [];
        $promises = self::makePromisesFromQueue(
            array_unique($imagesQueue),
            $client,
            $imgSizes,
            $errors
        );
        foreach($promises as $promise) {
            $promise->wait();
        }
        $response = [
            'url' => $url,
            'imageCount' => count($imgSizes),
            'imageSize' => array_sum($imgSizes),
        ];
        Redis::setex($cacheKey, env('CACHE_TTL'), json_encode($response));
        if ($errors) {
            $response['errors'] = array_unique(array_values($errors));
        }
        $response['status'] = 'ok';
        list($sec, $usec) = explode('.', $startTime);
        $response['timestamp'] = date('H:i:s:', $sec) . substr($usec, 0, 2);
        $response['time'] = microtime(true) - $startTime;
        return response()->json($response);
    }
}
