<?php

declare(strict_types=1);

namespace BaiduTranslate;

use ZM\Annotation\Framework\Init;
use ZM\Annotation\OneBot\BotCommand;
use ZM\Annotation\OneBot\CommandArgument;
use ZM\Context\BotContext;
use ZM\Exception\OneBot12Exception;
use ZM\Utils\ZMRequest;

class BaiduTranslate
{
    private const LANG_CH = [
        "简体中文" => "zh",
        "汉语" => "zh",
        "中文" => "zh",
        "普通话" => "zh",
        "英语" => "en",
        "英文" => "en",
        "法语" => "fra",
        "日语" => "jp",
        "日本语" => "jp",
        "日文" => "jp",
        "韩语" => "kor",
        "西班牙语" => "spa",
        "泰语" => "th",
        "阿拉伯语" => "ara",
        "俄语" => "ru",
        "葡萄牙语" => "pt",
        "德语" => "de",
        "意大利语" => "it",
        "希腊语" => "el",
        "荷兰语" => "nl",
        "波兰语" => "pl",
        "保加利亚语" => "bul",
        "丹麦语" => "dan",
        "爱沙尼亚语" => "est",
        "芬兰语" => "fin",
        "捷克语" => "cs",
        "罗马尼亚语" => "rom",
        "斯洛文尼亚语" => "slo",
        "瑞典语" => "swe",
        "匈牙利语" => "hu",
        "繁体中文" => "cht",
        "越南语" => "vie"

        /*
        "世界语" => "eo",
        "夏威夷语" => "haw",
        "土耳其语" => "tr",
        "乌克兰语" => "uk",
        "马来语" => "ms",
        "拉丁语" => "la",
        "爪哇语" => "jv",
        */
    ];

    #[Init()]
    public function init(): void
    {
        // 初始化配置文件
        if (config('baidu-translate') === null) {
            logger()->notice('百度翻译插件还没有配置文件，正在为你生成，请到 config/baidu-translate.json 填入你的配置项');
            file_put_contents(WORKING_DIR . '/config/baidu-translate.json', json_encode(['appid' => '','seckey' => '','req_timeout' => 10], JSON_PRETTY_PRINT));
        }
    }

    /**
     * 匹配 翻译 命令、翻译 xxx 命令
     * @throws OneBot12Exception
     */
    #[BotCommand(match: '翻译', level: 23)]
    #[BotCommand(start_with: '翻译', level: 21)]
    #[CommandArgument(name: 'content', type: 'string', required: true, prompt: '请输入你要翻译的内容')]
    public function translateMatch(BotContext $ctx): void
    {
        $this->emitTranslate($ctx, $ctx->getParamString('content'));
    }

    /**
     * 用{英语}怎么翻译{你好}
     * @throws OneBot12Exception
     */
    #[BotCommand(pattern: '*用*咋翻译*', level: 25)]
    #[BotCommand(pattern: '*用*怎么翻译*', level: 24)]
    #[CommandArgument(name: 'b1', type: 'string', required: false, default: '')]
    #[CommandArgument(name: 'b2', type: 'string', required: false, default: '')]
    #[CommandArgument(name: 'b3', type: 'string', required: false, default: '')]
    public function translatePattern1(BotContext $ctx): void
    {
        $b2 = $ctx->getParamString('b2');
        // 语言如果是空的，那玩犊子
        if ($b2 === '') {
            return;
        }
        // 右边的语气词去掉
        $b3 = $this->rtrimLang($ctx->getParamString('b3'), '吧呗啊啦呀！。？');
        $content = $b1 = $ctx->getParamString('b1');
        if ($b1 === '') {
            $content = $b3;
        }
        // 空白是无效的
        if ($content === '') {
            return;
        }
        // 获取目标语言，不在支持的列表里就不算命令
        if (($target_lang = $this->getTargetLanguage($b2)) === null) {
            return;
        }
        // 调用翻译逻辑
        $this->emitTranslate($ctx, $content, target_lang: $target_lang);
    }

    /**
     * 把{你好}翻译成{日语}
     * @throws OneBot12Exception
     */
    #[BotCommand(regex: '[把将](.*)翻译[为成](.*)', level: 22)]
    #[CommandArgument(name: 'b1', type: 'string', required: false, default: '')]
    #[CommandArgument(name: 'b2', type: 'string', required: false, default: '')]
    public function translatePattern2(BotContext $ctx): void
    {
        // 右边的语气词去掉
        $b1 = $this->rtrimLang($ctx->getParamString('b1'), '吧呗。.！');
        if ($b1 === '') {
            return;
        }
        // 获取目标语言，不在支持的列表里就不算命令
        if (($target_lang = $this->getTargetLanguage($ctx->getParamString('b2'))) === null) {
            return;
        }
        // 调用翻译逻辑
        $this->emitTranslate($ctx, $b1, target_lang: $target_lang);
    }

    /**
     * @throws OneBot12Exception
     */
    private function emitTranslate(BotContext $ctx, string $content, ?string $origin_lang = null, ?string $target_lang = null): void
    {
        if (config('baidu-translate.appid', '') === '') {
            $ctx->reply('你还没有配置百度翻译的 appid 和 seckey，请先到开发者平台生成并配置该配置项（config/baidu-translate.json）');
            return;
        }
        try {
            // 没有目标语言，需要先查询源语言是啥，或自动设定为中文。中文要翻译为英文
            if ($target_lang === null) {
                $language_type = $this->requestLanguageType($content);
                $target_lang = $language_type === 'zh' ? 'en' : 'zh';
            }
            // 设置参数
            $args = [
                'q' => $content,
                'appid' => config('baidu-translate.appid'),
                'salt' => random_int(100000, 999999),
                'from' => $origin_lang ?? 'auto',
                'to' => $target_lang,
                'tts' => "0",
                'dict' => "0",
            ];
            $args['sign'] = $this->buildSign($content, $args['appid'], $args['salt'], config('baidu-translate.seckey'));
            // 发起请求
            $ret = ZMRequest::post(
                url: 'http://api.fanyi.baidu.com/api/trans/vip/translate',
                header: [],
                data: $args,
                config: ['timeout' => config('baidu-translate.req_timeout', 5)]
            );
            if ($ret === false) {
                throw new TranslateException('无法请求翻译 API');
            }
            $ret = json_decode($ret, true);
            if (($ret['error_code'] ?? 52000) !== 52000) {
                throw new TranslateException('通用翻译API返回错误：' . $ret['error_code'], -1303);
            }
            $result = [
                "retcode" => 0,
                "origin" => $ret["from"],
                "target" => $ret["to"],
                "result" => $ret["trans_result"][0]["dst"],
                "src" => $ret["trans_result"][0]["src"]
            ];
            // if (isset($ret["src_tts"])) $result["src_tts"] = $ret["src_tts"];
            // if (isset($ret["dst_tts"])) $result["dst_tts"] = $ret["dst_tts"];
            // if (isset($ret["dict"])) $result["dict"] = $ret["dict"];
            // 发送
            $msg = '翻译结果';
            $msg .= "\n[{$this->getLanguageName($result['origin'])} -> {$this->getLanguageName($result['target'])}]";
            $msg .= "\n{$result['src']}";
            $msg .= "\n\n{$result['result']}";
            $ctx->reply($msg);
        } catch (TranslateException $e) {
            $ctx->reply("翻译出错，错误代码：[{$e->getCode()}]，内容：{$e->getMessage()}");
        }
    }

    /**
     * @throws TranslateException
     */
    private function requestLanguageType(string $content): string
    {
        $args = [
            'q' => $content,
            'appid' => config('baidu-translate.appid'),
            'salt' => random_int(100000, 999999),
        ];
        $args['sign'] = $this->buildSign($content, $args['appid'], $args['salt'], config('baidu-translate.seckey'));
        $ret = ZMRequest::post(
            url: "http://api.fanyi.baidu.com/api/trans/vip/language",
            header: [],
            data: $args,
            config: ['timeout' => config('baidu-translate.req_timeout', 5)]
        );
        if ($ret === false) {
            throw new TranslateException('请求语种查询出错', -1301);
        }
        $ret = json_decode($ret, true);
        if ($ret['error_code'] !== 0) {
            throw new TranslateException("语种查询出错，返回：{$ret['error_code']} -> {$ret['error_msg']}", -1302);
        }
        return $ret['data']['src'];
    }

    private function buildSign($msg, $app_id, $salt, $sec_key): string
    {
        $str = $app_id . $msg . $salt . $sec_key;
        return md5($str);
    }

    private function getTargetLanguage(string $target_lang): ?string
    {
        return self::LANG_CH[trim($target_lang)] ?? null;
    }

    private function getLanguageName($lang): ?string
    {
        foreach (self::LANG_CH as $k => $v) {
            if ($v == $lang) return $k;
        }
        return null;
    }

    private function rtrimLang($string, $trim, $encoding = "utf-8"): string
    {
        $mask = [];
        $trimLength = mb_strlen($trim, $encoding);
        for ($i = 0; $i < $trimLength; $i++) {
            $item = mb_substr($trim, $i, 1, $encoding);
            $mask[] = $item;
        }

        $len = mb_strlen($string, $encoding);
        if ($len > 0) {
            $i = $len - 1;
            do {
                $item = mb_substr($string, $i, 1, $encoding);
                if (in_array($item, $mask)) {
                    $len--;
                } else {
                    break;
                }
            } while ($i-- != 0);
        }

        return mb_substr($string, 0, $len, $encoding);
    }
}
