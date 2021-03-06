<?php

namespace Miaoxing\Wechat\Controller\Wechat;

use Wei\Request;

class Payment extends Base
{
    /**
     * 无需用户登录的页面
     *
     * @var array
     */
    protected $guestPages = [
        'wechat/payment/native',
    ];

    public function __construct($options)
    {
        parent::__construct($options);

        // 记录微信的请求信息
        $this->logger->info('Wechat request URL: ' . $this->request->getUrl() . ' ' . print_r($_POST, true));
        $this->logger->info('Wechat post data : ' . $this->request->getContent());

        // 记录服务器返回信息
        $this->response->setOption('beforeSend', function ($response, $content) {
            $this->logger->info('Server response data: ' . $content);
        });
    }

    /**
     * 处理原生支付
     */
    public function nativeAction(Request $req)
    {
        $wechatPay = wei()->payment()->createCurrentWechatPayService();

        return $wechatPay->executeNativePay($req->getContent());
    }
}
