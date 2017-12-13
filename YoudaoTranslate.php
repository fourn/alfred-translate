<?php

require 'vendor/autoload.php';

use Alfred\Workflows\Workflow;

// $workflow->result()
//          ->uid('bob-belcher')   唯一编号 : STRING (可选)，用于排序
//          ->title('Bob')         标题： STRING， 显示结果
//          ->subtitle('Head Burger Chef')  副标题： STRING ,显示额外的信息
//          ->quicklookurl('http://www.bobsburgers.com')  快速预览地址 : STRING (optional)
//          ->type('default')   类型，可选择文件类型: "default" | "file"
//          ->arg('bob')    输出参数 : STRING (recommended)，传递值到下一个模块
//          ->valid(true)       回车是否可用 : true | false (optional, default = true)
//          ->icon('bob.png')   图标
//          ->mod('cmd', 'Search for Bob', 'search')   修饰键 : OBJECT (可选)
//          ->text('copy', 'Bob is the best!')   按cmd+c 复制出来的文本: OBJECT (optional)
//          ->autocomplete('Bob Belcher');    自动补全 : STRING (recommended)

class YoudaoTranslate {

    private $workflow;
    private $keys;
    private $result;
    private $query; //用户输入值
    private $pronounce;
    private $historyFile;

    const show_phonetic = false; //是否显示音标

    public function __construct($keys)
    {
        $this->workflow = new Workflow;
        $this->keys = $keys;
        $this->historyFile = 'YoudaoTranslate-' . @date('Ym') . '.log';
    }

    public function get($url)
    {
        if (ini_get("allow_url_fopen") == "1") {
            $response = file_get_contents($url);
        } else {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            $response = curl_exec($ch);
            curl_close($ch);
        }
        return $response;
    }



    /**
     * @param $query
     * @return string
     */
    public function translate($query)
    {
        $this->query = $query;
        // 如果输入的是 yd * ，列出查询记录最近10条
        if ($this->query === '*') {
            return $this->getHistory();
        }

        $url = $this->getOpenQueryUrl($query);
        $response = $this->get($url);
        $this->result = json_decode($response); //接口返回的翻译结果（http://ai.youdao.com/docs/doc-trans-api.s#p04）

        if (empty($this->result) || (int)$this->result->errorCode !== 0) {
            //翻译出错
            $this->addItem('翻译出错', $response);
        } else {
            // 获取要发音的单词
            if ($this->isChinese($this->query)) {
                //是中文，读翻译出来的英文
                $this->pronounce = $this->result->translation[0];
            } else {
                //是英文，读查询的单词
                $this->pronounce = $this->query;
            }

            if (isset($this->result->translation)) {
                //查询正确时一定存在
                $this->addItem($this->result->translation[0], $this->result->translation[0]);
            }

            if (isset($this->result->basic)) {
                //基本词典,查词时才有
                $this->parseBasic($this->result->basic);
            }

            if (isset($this->result->web)) {
                //网络释义，该结果不一定存在
                $this->parseWeb($this->result->web);
            }
        }

        return $this->workflow->output();
    }

    /**
     * 解析 Basic 字段， 基础释义
     * @param $basic
     */
    private function parseBasic($basic)
    {
        foreach ($basic->explains as $explain) {
            $this->addItem($explain, $explain, $explain);
        }

        if(self::show_phonetic){
            if (isset($basic->phonetic)) {
                // 获取音标，同时确定要发音的单词
                $phonetic = $this->getPhonetic($basic);
                $this->addItem($phonetic, '回车可听发音', '~' . $this->pronounce);
            }
        }

    }

    /**
     * 解析 Web 字段， 网络释义
     * @param $web
     */
    private function parseWeb($web)
    {
        foreach ($web as $key => $item) {
            $_title = join(',', $item->value);
            if ($key === 0) {
                //第一条网络释义，保存到本地词典
                $result = $this->addItem($_title, $item->key, $_title, true);
                $this->saveHistory($result);
            } else {
                $this->addItem($_title, $item->key, $_title);
            }
        }
    }

    /**
     * 检测字符串是否由纯英文，纯中文，中英文混合组成1:纯英文;2:纯中文;3:中英文混合
     * @param $str
     * @return bool
     */
    private function isChinese($str)
    {
        $m = mb_strlen($str, 'utf-8');
        $s = strlen($str);
        if ($s == $m) {
            return false;
        }
        if ($s % $m == 0 && $s % 3 == 0) {
            return true;
        }
        return true;
    }

    /**
     * 从 basic 字段中获取音标
     * @param $basic
     * @return string
     */
    public function getPhonetic($basic)
    {
        $phonetic = '';
        // 中文才会用到这个音标y
        if ($this->isChinese($this->query) && isset($basic->{'phonetic'}))
            $phonetic .= "[" . $basic->{'phonetic'} . "]";
        if (isset($basic->{'us-phonetic'}))
            $phonetic .= " [美: " . $basic->{'us-phonetic'} . "]";
        if (isset($basic->{'uk-phonetic'}))
            $phonetic .= " [英: " . $basic->{'uk-phonetic'} . "]";

        return $phonetic;
    }

    /**
     * 获取查询记录的最近 9 条
     */
    private function getHistory()
    {
        $history = [];
        $lastTenLines = $this->getLastLines($this->historyFile, 9);
        if (!empty($lastTenLines)) {
            foreach ($lastTenLines as $line) {
                $result = json_decode($line);
                if (strlen($result->subtitle) > 1) {
                    $history[] = $result;
                }
            }

            $output = [
                'items' => $history,
            ];

            return json_encode($output);
        } else {
            $this->addItem('没有历史纪录', 'No History');
            return $this->workflow->output();
        }
    }

    /**
     * 保存翻译结果
     * @param $translation
     */
    private function saveHistory($translation)
    {
        @file_put_contents($this->historyFile, json_encode($translation) . "\n", FILE_APPEND);
    }

    /**
     * 取文件最后$n行
     * @param $filename [文件路径]
     * @param $n [最后几行]
     * @return array|bool [成功则返回字符串]
     */
    private function getLastLines($filename, $n)
    {
        if (!$handler = @fopen($filename, 'r')) {
            return false;
        }

        $eof = "";
        $lines = [];
        //忽略最后的 \n
        $position = -2;

        while ($n > 0) {
            while ($eof != "\n") {
                if (!fseek($handler, $position, SEEK_END)) {
                    $eof = fgetc($handler);
                    $position--;
                } else {
                    break;
                }
            }

            if ($line = fgets($handler)) {
                $lines[] = $line;
                $eof = "";
                $n--;
            } else {
                //当游标超限 fseek 报错以后，无法 fgets($fp), 需要将游标向后移动一位
                fseek($handler, $position + 1, SEEK_END);
                if ($line = fgets($handler)) {
                    $lines[] = $line;
                }
                break;
            }

        }
        return $lines;
    }

    /**
     * 随机从配置中获取一组 keyfrom 和 key
     * @param $title [标题]
     * @param $subtitle [副标题]
     * @param null $arg [传递值]
     * @param bool $toArray
     * @return mixed
     */
    private function addItem($title, $subtitle, $arg = null, $toArray = false)
    {
        $arg = $arg ? $arg : $this->pronounce;
        $_subtitle = $subtitle ? $subtitle : $this->query;
        $_quicklookurl = 'http://youdao.com/w/' . urlencode($this->query);
        $_icon = $this->startsWith($arg, '~') ? 'translate-say.png' : 'translate.png';

        $result = $this->workflow->result();
        $result->title($title)
            ->subtitle($_subtitle)
            ->quicklookurl($_quicklookurl)
            ->arg($arg)
            ->icon($_icon)
            ->text('copy', $title);

        if ($toArray) {
            return $result->toArray();
        }
    }

    /**
     * 检测字符串开头
     * @param $haystack [等待检测的字符串]
     * @param $needle [开头的定义]
     * @return bool
     */
    private function startsWith($haystack, $needle)
    {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }

    /**
     * 组装网易智云请求地址
     * @param $query
     * @return string
     */
    private function getOpenQueryUrl($query)
    {
        $api = 'https://openapi.youdao.com/api?from=auto&to=auto&';

        $key = $this->keys[array_rand($this->keys)];//随机选一个 api
        $key['q'] = $query; //要翻译的文本
        $key['salt'] = strval(rand(1, 100000)); //随机数
        $key['sign'] = md5($key['appKey'] . $key['q'] . $key['salt'] . $key['secret']);

        return $api . http_build_query($key);
    }
}
