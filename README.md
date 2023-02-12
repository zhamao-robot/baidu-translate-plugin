# baidu-translate-plugin

炸毛框架插件 - 百度翻译 API 插件。

安装框架后，可直接安装插件到框架。

## 安装方法

```bash
./zhamao plugin:install https://github.com/zhamao-robot/baidu-translate-plugin.git
```

## 配置方法

安装插件后第一次启动将会生成一个 `config/baidu-translate.json` 配置文件，需要填入百度翻译开发平台的 appid 和 sec_key。

appid 和 sec_key 可在 <https://fanyi-api.baidu.com/> 获取。

## 使用方法

安装插件后，可匹配如下对话：

- `翻译 xxx`
- `把xx翻译成yy`
- `xx用yy怎么翻译`
- `用yy咋翻译xx`
