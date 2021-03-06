<?php

namespace Miaoxing\Wechat;

use miaoxing\plugin\BaseController;
use Miaoxing\Plugin\Service\User;
use Wei\WeChatApp;

class Plugin extends \miaoxing\plugin\BasePlugin
{
    protected $name = '微信公众平台';

    protected $description = '包括公众号,回复,自定义菜单等';

    protected $adminNavId = 'wechat';

    public function onAdminNavGetNavs(&$navs, &$categories, &$subCategories)
    {
        $categories['wechat'] = [
            'name' => '微信',
            'sort' => 700,
        ];

        $subCategories['wechat-account'] = [
            'parentId' => 'wechat',
            'name' => '公众号',
            'icon' => 'fa fa-wechat',
            'sort' => 1000,
        ];

        $subCategories['wechat-stat'] = [
            'parentId' => 'wechat',
            'name' => '统计',
            'icon' => 'fa fa-bar-chart',
            'sort' => 500,
        ];

        $navs[] = [
            'parentId' => 'wechat-account',
            'url' => 'admin/wechat-account',
            'name' => '公众号管理',
            'sort' => 1000,
        ];

        $navs[] = [
            'parentId' => 'wechat-account',
            'url' => 'admin/wechat-reply/index',
            'name' => '回复管理',
            'sort' => 900,
        ];

        $navs[] = [
            'parentId' => 'wechat-account',
            'url' => 'admin/wechat-menu-categories',
            'name' => '菜单管理',
            'sort' => 800,
        ];

        $navs[] = [
            'parentId' => 'wechat-account',
            'url' => 'admin/wechat-qrcode/index',
            'name' => '二维码管理',
            'sort' => 700,
        ];

        $subCategories['wechat-setting'] = [
            'parentId' => 'wechat',
            'name' => '设置',
            'icon' => 'fa fa-gear',
            'sort' => 0,
        ];

        $navs[] = [
            'parentId' => 'wechat-setting',
            'url' => 'admin/wechat-settings',
            'name' => '功能设置',
            'sort' => 0,
        ];
    }

    public function onLinkToGetLinks(&$links, &$types, &$decorators)
    {
        $types['keyword'] = [
            'name' => '关键字',
            'input' => 'text',
            'sort' => 1100,
            'placeholder' => '请输入微信回复的关键字',
        ];

        $decorators['oauth2Base'] = [
            'name' => '微信OpenID授权',
        ];

        // 暂不支持
        /*
            $decorators['oauth2UserInfo'] = [
            'name' => '微信用户信息授权',
        ];*/
    }

    public function onPreControllerInit(BaseController $controller)
    {
        $controller->middleware(\Miaoxing\Wechat\Middleware\Auth::className());
    }

    public function onUserGetPlatform($platforms)
    {
        $platforms[] = [
            'name' => '微信',
            'value' => 'wechat',
        ];
    }

    public function onPreUserSearch(User $users, $req)
    {
        if ($req['platform'] == 'wechat') {
            $users->andWhere("wechatOpenId != ''");
        }
    }

    public function onPreContent()
    {
        if ($this->app->getControllerAction() != 'index/index') {
            return;
        }
        $this->displayShareImage();
    }

    public function displayShareImage()
    {
        if ($shareImage = wei()->setting('wechat.shareImage')) {
            $this->event->trigger('postImageLoad', [&$shareImage]);
            $this->view->display('wechat:wechat/preContent.php', get_defined_vars());
        }
    }

    /**
     * 扫描二维码关注后的操作
     *
     * @param WeChatApp $app
     * @param User $user
     */
    public function onWechatScan(WeChatApp $app, User $user)
    {
        $sceneId = $app->getScanSceneId();
        if (!$sceneId) {
            return;
        }

        $qrcode = wei()->weChatQrcode()->find(['sceneId' => $sceneId]);

        $app->subscribe(function (WeChatApp $app) use ($user, $qrcode) {
            $reply = wei()->weChatReply();

            // 将重新关注的用户置为有效
            if (!$user['isValid']) {
                $user['isValid'] = true;
            }

            if ($sceneId = $app->getScanSceneId()) {
                $user->save([
                    'isValid' => true,
                    'source' => $sceneId,
                ]);
            }

            if ($user->isChanged()) {
                $user->save();
            }

            // 扫码的关注回复
            if ($qrcode['articleIds']) {
                return $app->sendArticle($qrcode->toArticleArray());
            }

            // 关注回复
            if ($reply->findByIdFromCache('subscribe')) {
                return $reply->send($app, '{关注顺序}', $user['id']);
            }
        });
    }
}
