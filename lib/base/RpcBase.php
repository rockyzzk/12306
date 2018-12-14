<?php
/**
 * Desc: rpc 基础库
 * User: zhangzekang
 * Date: 2018/11/22
 * Time: 下午4:46
 */

namespace Base;

use \GuzzleHttp\Client;
use \GuzzleHttp\Pool;
use \GuzzleHttp\Psr7\Request;
use \GuzzleHttp\Psr7\Response;
use \Core\Log;

class RpcBase {

    private $httpClient = null;

    private function _getHttp($forceUpdate = false) {
        if (is_null($this->httpClient) || $forceUpdate) {
            $this->httpClient = new Client(['cookies' => true]);
        }
        return $this->httpClient;
    }

    protected $host;

    protected $logExceptRequestKeywords = [];

    protected $logExceptResponseKeywords = [];

    protected function _get($uri, $params, $headers = null, $multiCount = 0) {

        // 请求头
        if (!is_null($headers)) {
            $options['headers'] = $headers;
        }

        // 参数
        if (!empty($params)) {
            $params = '?' . http_build_query($params);
        }

        if ($multiCount > 0) {
            // 发送多个请求
            $res = $this->_requestMulti('GET', $this->host . $uri . $params, $options ?? [], $multiCount);
        } else {
            $res = $this->_request('GET', $this->host . $uri . $params, $options ?? []);
        }

        return $res;
    }

    protected function _post($uri, $params, $headers = null, $multiCount = 0) {

        // 文件
        if (isset($params['multipart'])) {
            foreach ($params['multipart'] as $file) {
                $options['multipart'][] = [
                    'name' => $file['form_name'],
                    'contents' => $file['content']
                ];
            }
            unset($params['multipart']);
        }

        // 请求头
        if (!is_null($headers)) {
            $options['headers'] = $headers;
        }

        // 请求体
        if (!empty($params)) {
            $options['form_params'] = $params;
        }

        if ($multiCount > 0) {
            // 发送多个请求
            $res = $this->_requestMulti('POST', $this->host . $uri, $options ?? [], $multiCount);
        } else {
            $res = $this->_request('POST', $this->host . $uri, $options ?? []);
        }

        return $res;
    }

    protected function _request($method, $url, $options = []) {
        $client = $this->_getHttp();
        try {
            $logInput = (isset($options['form_params']) ? json_encode($options['form_params']) : null);

            // 不存日志的请求
            if (!empty($this->logExceptRequestKeywords)) {
                foreach ($this->logExceptRequestKeywords as $requestKeyword) {
                    if (strpos($url, $requestKeyword) !== false) {
                        $logInput = 'except request';
                    }
                }
            }

            Log::info("[RPC-REQUEST] [method]$method [url]$url [input]" . $logInput, 'rpc');
            $res = $client->request($method, $url, $options);
            $logOutput = $res->getBody();

            // 不存日志的响应
            if (!empty($this->logExceptResponseKeywords)) {
                foreach ($this->logExceptResponseKeywords as $responseKeyword) {
                    if (strpos($url, $responseKeyword) !== false) {
                        $logOutput = 'except response';
                    }
                }
            }

            Log::info("[RPC-REQUEST] [method]$method [url]$url [status]" . $res->getStatusCode() . ' [output]' . $logOutput, 'rpc');
        } catch (\Throwable $e) {
            Log::warning("[RPC-REQUEST] [method]$method [url]$url [failed]" . $e->getMessage(), 'rpc');
            return null;
        }
        return $res->getBody();
    }

    protected function _requestMulti($method, $url, $options = [], $multiCount) {
        $client = $this->_getHttp();
        $resArr = [];

        Log::info("[MULTI-RPC-REQUEST] [method]$method [url]$url [input]" . (isset($options['form_params']) ? json_encode($options['form_params']) : null), 'rpc');
        $requests = function ($total) use ($method, $url, $options) {
            for ($i = 0; $i < $total; $i++) {
                yield new Request($method, $url, isset($options['headers']) ? $options['headers'] : [], isset($options['form_params']) ? $options['form_params'] : null);
            }
        };

        $pool = new Pool($client, $requests($multiCount), [
            'concurrency' => 5,
            'fulfilled' => function ($response, $index) use ($method, $url, &$resArr) {
                $resArr[$index] = $response->getBody();
                Log::info("[MULTI-RPC-REQUEST] [INDEX]$index [method]$method [url]$url [status]" . $response->getStatusCode() . ' [output]' . $response->getBody(), 'rpc');
            },
            'rejected' => function ($reason, $index) use ($method, $url) {
                Log::warning("[MULTI-RPC-REQUEST] [INDEX]$index [method]$method [url]$url [failed]$reason", 'rpc');
            },
        ]);

        $promise = $pool->promise();

        $promise->wait();

        return $resArr;
    }

    public function cleanCookies()
    {
        $this->_getHttp(true);
    }

}