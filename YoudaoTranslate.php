<?php

require 'vendor/autoload.php';
require 'Api.php';

use Alfred\Workflows\Workflow;
use Api\api;

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

    const ICON = 'translate.png';
    const ICON_SAY = 'translate-say.png';

    private $workflow;
    private $api;

    public function __construct()
    {
        $this->workflow = new Workflow;
        $this->api = new Api();
    }

    /**
     * @param $query
     * @return string
     */
    public function main($query)
    {
        if ($query === '*') {
//            return $this->getHistory();
        } else {
            $result = $this->api->translate($query);
            if ($result['errorCode'] == 0) {
                $this->handleResult($result);
            } else {
                $this->handleApiError($result);
            }
        }
        return $this->workflow->output();
    }

    public function handleApiError($result)
    {
        $this->addItem('接口错误', '错误代码：' . $result['errorCode']);
    }

    public function handleResult($result)
    {
        $translation = $result['translation'][0];
        $query = $result['query'];
        $basic = $result['basic'];
        $web = $result['web'];
        if ($translation == $query) {
            $this->addItem('无结果', '');
        } else {
            if ($this->isChinese($query)) {
                $this->addItem($translation, '', $translation, $query);
                if (isset($basic)) {
                    foreach ($basic['explains'] as $v) {
                        $this->addItem($v, '', $v);
                    }
                }
                if (isset($web)) {
                    foreach ($web as $v) {
                        $val = implode(',', $v['value']);
                        $this->addItem($val, $v['key'], $val);
                    }
                }
            } else {
                $this->addItem($translation, '', $translation, $query);
                if (isset($basic)) {
                    if(isset($basic['phonetic'])){
                        $this->addItem($basic['phonetic'], 'cmd+enter发音', $query, null, self::ICON_SAY);
                    }
                    foreach ($basic['explains'] as $v) {
                        $this->addItem($v, '', $v);
                    }
                }
                if (isset($web)) {
                    foreach ($web as $v) {
                        $val = implode(',', $v['value']);
                        $this->addItem($val, $v['key'], $v['key']);
                    }
                }
            }
        }
    }

    /**
     * @param $title
     * @param $subtitle
     * @param null $arg
     * @param null $search
     * @param string $icon
     */
    private function addItem($title, $subtitle, $arg = null, $search = null, $icon = self::ICON)
    {
        $quicklookurl = $search ? 'http://youdao.com/w/' . urlencode($search) : null;
        $this->workflow->result()
            ->title($title)
            ->subtitle($subtitle)
            ->arg($arg)
            ->quicklookurl($quicklookurl)
            ->icon($icon);
    }

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
}
