<?php
define('POST_BAG_API_KEY', 'v+LkUV&9L)$!RM8+u4}AGMT:q&Jv:?bftrF{K2TZxBgM282E');

function postSSE($customer, $userId, $target, $content)
{
    $options = array(
        'http' => array(
            'header'  => "Content-type: text/plain;charset=UTF-8\r\nUser-Agent: Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.102011-10-16 20:23:10\r\n",
            'method'  => 'POST',
            'content' => $content,
        )
    );
    $context  = stream_context_create($options);
    file_get_contents(URL_EVENT_SYSTEM . '?topic=' . $customer . '&u=' . $userId . '&target=' . $target, false, $context);
}

function getBag($bid)
{
    $result = file_get_contents(URL_BAG_SYSTEM . '?bid=' . $bid);
    return $result;
}

function postBag($content)
{
    $options = array(
        'http' => array(
            'header'  => ["Content-type: text/plain;charset=UTF-8", "Api-Key: " . POST_BAG_API_KEY],
            'method'  => 'POST',
            'content' => $content,
        )
    );
    $context  = stream_context_create($options);
    $result = file_get_contents(URL_BAG_SYSTEM, false, $context);
    $o = json_decode($result, true);
    return $o['bid'];
}