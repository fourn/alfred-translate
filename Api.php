<?php
/**
 * user.workflow.AD6DB1B7-B03B-46A9-A5A4-6C838FC6392D Api.php
 * Created by fourn.
 * Date: 21/12/2017 11:36
 */
namespace Api;

class Api{

    const CURL_TIMEOUT = 20;
    const URL = 'https://openapi.youdao.com/api';
    const APP_KEY = '079eb4664f437099';
    const SEC_KEY = '6Zd53TBwEiYfetYWfki8oaoEHKrC1cgI';

    /**
     * 翻译入口
     * @param $query
     * @param string $from
     * @param string $to
     * @return bool|mixed
     */
    function translate($query, $from = 'auto', $to = 'auto')
    {
        $args = array(
            'q' => $query,
            'appKey' => self::APP_KEY,
            'salt' => rand(10000,99999),
            'from' => $from,
            'to' => $to,
        );
        $args['sign'] = $this->buildSign(self::APP_KEY, $query, $args['salt'], self::SEC_KEY);
        $ret = $this->call(self::URL, $args);
        $ret = json_decode($ret, true);
        return $ret;
    }

    /**
     * 加密
     * @param $appKey
     * @param $query
     * @param $salt
     * @param $secKey
     * @return string
     */
    function buildSign($appKey, $query, $salt, $secKey)
    {
        $str = $appKey . $query . $salt . $secKey;
        $ret = md5($str);
        return $ret;
    }

    /**
     * 发起网络请求
     * @param $url
     * @param null $args
     * @param string $method
     * @param int $timeout
     * @param array $headers
     * @return bool|mixed
     */
    function call($url, $args=null, $method="post", $timeout = self::CURL_TIMEOUT, $headers=array())
    {
        $ret = false;
        $i = 0;
        while($ret === false)
        {
            if($i > 1)
                break;
            if($i > 0)
            {
                sleep(1);
            }
            $ret = $this->callOnce($url, $args, $method, false, $timeout, $headers);
            $i++;
        }
        return $ret;
    }

    /**
     * @param $url
     * @param null $args
     * @param string $method
     * @param bool $withCookie
     * @param int $timeout
     * @param array $headers
     * @return mixed
     */
    function callOnce($url, $args=null, $method="post", $withCookie = false, $timeout = self::CURL_TIMEOUT, $headers=array())
    {
        $ch = curl_init();
        if($method == "post")
        {
            $data = $this->convert($args);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_POST, 1);
        }
        else
        {
            $data = $this->convert($args);
            if($data)
            {
                if(stripos($url, "?") > 0)
                {
                    $url .= "&$data";
                }
                else
                {
                    $url .= "?$data";
                }
            }
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if(!empty($headers))
        {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if($withCookie)
        {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $_COOKIE);
        }
        $r = curl_exec($ch);
        curl_close($ch);
        return $r;
    }

    /**
     * @param $args
     * @return string
     */
    function convert(&$args)
    {
        $data = '';
        if (is_array($args))
        {
            foreach ($args as $key=>$val)
            {
                if (is_array($val))
                {
                    foreach ($val as $k=>$v)
                    {
                        $data .= $key.'['.$k.']='.rawurlencode($v).'&';
                    }
                }
                else
                {
                    $data .="$key=".rawurlencode($val)."&";
                }
            }
            return trim($data, "&");
        }
        return $args;
    }
}
