<?php
/**
 * @title deepl翻译
 * @author Ocean
 * @time 2024-7-1
 */
$from = isset($_GET['from']) ? strtoupper($_GET['from']) : 'EN';
$to   = isset($_GET['to'])   ? strtoupper($_GET['to'])   : 'ZH';
$text = isset($_GET['q'])    ? trim($_GET['q']) : '';

$post_str = translate($text, $from, $to);
$proxy_ip = proxyIp();
$response = httpCurl($post_str, $proxy_ip);

print_r(json_encode(json_decode($response, true), JSON_UNESCAPED_UNICODE));

function translate($text, $from, $to)
{
    $id = getRandomNumber();
//    $post_data = initData($from, $to);
    $post_data = initDeepLXData($from, $to);
//    print_r($post_data);exit;
    $text = html_entity_decode($text, ENT_COMPAT, 'UTF-8'); // 特殊字符进行处理
    $texts['text'] = $text;
    $texts['requestAlternatives'] = 3;

    $post_data["id"] = $id;
    $post_data["params"]["texts"] = [$texts];
    $post_data["params"]["timestamp"] = getTimeStamp([$text]);
    $post_str = json_encode($post_data);
//        print_r($post_data);exit;
    if (($id + 5) % 29 == 0 || ($id + 3) % 13 == 0) {
        $post_str = str_replace('"method":"', '"method" : "', $post_str);
    } else {
        $post_str = str_replace('"method":"', '"method": "', $post_str);
    }

    return $post_str;
}

/**
 * 随机字符串
 * @return int
 */
function getRandomNumber(): int
{
    $rand = random_int(100000, 999999);
    return $rand * 1000;
}

/**
 * 不区分美式英语(en-US)， 英式英语(en-GB)
 * @param string $source_lang
 * @param string $target_lang
 * @return array
 */
function initData(string $source_lang, string $target_lang): array
{
    return [
        "jsonrpc" => "2.0",
        "method" => "LMT_handle_texts",
        "params" => [
            "splitting" => "newlines",
            "lang" => [
                "source_lang_user_selected" => $source_lang,
                "target_lang" => $target_lang,
            ],
        ]
    ];
}

/**
 * @param $source_lang
 * @param $target_lang
 * @return array
 */
function initDeepLXData($source_lang, $target_lang): array
{
//    区分 美式英语(en-US)， 英式英语(en-GB)
    $targetLangParts = explode('-', $target_lang);
    $targetLangCode = $targetLangParts[0];

    $commonJobParams = [
        'wasSpoken' => false,
        'transcribeAS' => "",
    ];

    if (isset($targetLangParts[1])) {
        $commonJobParams['regionalVariant'] = strtolower($targetLangParts[0]) . '-' . $targetLangParts[1];
    }

    $postData = [
        'jsonrpc' => "2.0",
        'method' => "LMT_handle_texts",
        'params' => [
            'splitting' => 'newlines',
            'lang' => [
                'source_lang_user_selected' => $source_lang,
                'target_lang' => $targetLangCode,
            ],
            'commonJobParams' => $commonJobParams,
        ]
    ];

    return $postData;
}

/**
 * 时间戳
 * @param array $translates
 * @return int
 */
function getTimeStamp(array $translates): int
{
    $ts = (int)(microtime(true)*1000);
    $i_count = array_reduce($translates, function ($carry, $text) {
        return $carry + substr_count($text, 'i');
    }, 1);

    return $ts - ($ts % $i_count) + $i_count;
}

/**
 * 代理ip
 * @return string
 */
function proxyIp(): string
{
    $proxy_url = 'http://qingxun.user.xiecaiyun.com/api/proxies?action=getText&key=NPB3305ADB&count=1&word=&rand=true&norepeat=false&detail=false&ltime=0';
    $proxy_ip = file_get_contents($proxy_url);
    return trim($proxy_ip);
}

/**
 * 单文本翻译
 * @param $post_str
 * @param $proxy_ip
 * @return bool|string
 */
function httpCurl($post_str, $proxy_ip = '')
{
    $url = 'https://www2.deepl.com/jsonrpc';

    $headers = [
        'Content-Type: application/json',
        'Origin: https://www.deepl.com',
        'Referer: https://www.deepl.com/',
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36',
    ];

    $curl = curl_init();

    // Set common cURL options
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post_str,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
    ]);

    // Set proxy options if provided
    if ($proxy_ip) {
        curl_setopt_array($curl, [
            CURLOPT_PROXYTYPE => CURLPROXY_HTTP,
            CURLOPT_PROXY => $proxy_ip,
            CURLOPT_PROXYUSERPWD => 'qingxun:123456',
        ]);
    }

    $response = curl_exec($curl);
//    if (curl_errno($curl)) {
//        echo 'Curl error: ' . curl_error($curl);
//    }
    curl_close($curl);
    return $response;
}