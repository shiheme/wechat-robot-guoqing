<?php
/*
Plugin Name: Wechat Robot公众号国庆头像合成
Plugin URI:  https://github.com/shiheme/wechat-robot-guoqing
Description: 基于Wechat Robot源码二次开发的公众号国庆头像合成对话机器人。原插件地址：https://github.com/wangvsa/wechat-robot
Version: 1.0
Author: 小鱼哥
Author URI: https://beebee.work
*/

define('WEIXIN_ROBOT_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . dirname(plugin_basename(__FILE__)));
require(WEIXIN_ROBOT_PLUGIN_DIR . '/wechat.php');

add_action('parse_request', 'wechat_robot_redirect', 4);
function wechat_robot_redirect($wp)
{
  if (isset($_GET['wechat'])) {
    // 重要!!!
    // 这里的 三个参数分为为 token、appid和 secret
    // 1.首先进入微信公众账号后台，找到 设置与开发 -> 基本设置 -> 公众号开发信息，
    // 将后两个参数改成你的开发者ID和开发者密码
    // 2. IP白名单填写的服务器 IP
    // 3. 服务器配置
    // 服务器地址： 填写你的域名 + '?wechat',例如：https://gallery.demo.beebee.work/?wechat 
    // 令牌 ：填写 'wechat' 一定要和这里的 token 一致
    // 消息加解密密钥： 随机
    // 消息加解密方式： 明文模式
    // 搜索公众号【比比小鱼哥】可以体验生成国庆头像的交互
    // 还不懂搜索公众号【app比比】进群问群友，这里汇集了比比源码的爱好者
    $robot = new WechatRobot("wechat", "wxd92aa3b9312c96e5", "48b894699a2f9b09cfd0e23b75e1f7a5", true);
    // 删除了源代码的创建菜单，需要的自行前往源码http://github.com/wangvsa/wechat-robot
    $robot->run();
  }
}


class WechatRobot extends Wechat
{

  protected function queryAndResponse($arg)
  {
    $the_query = new WP_Query($arg);
    if ($the_query->have_posts()) {

      $counter = 0;
      $items = array();

      while ($the_query->have_posts()) {
        $the_query->the_post();
        global $post;

        $title = get_the_title();
        // 这里我修改成了比比轻壁纸的 H5端，不需要的注释掉换回原先的
        // $link = get_permalink();
        $link = 'https://gallery.demo.beebee.work/web/#/';

        $excerpt = wechat_get_excerpt(get_the_excerpt());

        if ($counter == 0) {
          $thumb = wechat_get_thumb($post, array(640, 320));
        } else {
          $thumb = wechat_get_thumb($post, array(80, 80));
        }

        $new_item = new NewsResponseItem($title, $excerpt, $thumb, $link);
        array_push($items, $new_item);

        // 最多显示3篇
        if (++$counter == 3)
          break;
      }

      $this->responseNews($items);
    } else {
      $this->responseText("不好意思～没有找到您想要的东东～请换个关键字再试试？");
    }

    wp_reset_postdata();
  }

  // 这里我增加了国庆头像的相关回复
  protected function queryAndImageResponse($arg)
  {
    // 定义卡片标题
    $title = '国庆头像';
    // 定义卡片描述
    $excerpt = '你的国庆头像已经生成，请点击查看。不满意可以回复【9】切换国旗';
    $items = array();
    // 定义卡片卡片封面图
    $sucai = $arg['sucai'];  //国旗样式参数
    $pic = $arg['pic'];  //用户发来的照片
    $user = $arg['user']; //用户的openid
    $thumb = wechat_get_hecheng_thumb($pic, $sucai, $user, array(500, 500)); //合成头像函数
    //定义卡片跳转链接，这里是跳转到合成的头像图片
    $link = $thumb;

    $new_item = new NewsResponseItem($title, $excerpt, $thumb, $link);
    array_push($items, $new_item);
    $this->responseNews($items);
    wp_reset_postdata();
  }

  protected function searchPosts($key)
  {
    $arg = array('s' => $key);
    $this->queryAndResponse($arg);
  }

  protected function recentPosts()
  {
    //这里的相关内容我加了如果启用了比比插件则返回博客
    if (is_plugin_active('smartbeebee/smartbeebee.php')) {
      $arg = array('post_type' => 'beebee_blog', 'showposts' => 3);
    } else {
      $arg = array('post_type' => 'post', 'showposts' => 3);
    }

    $this->queryAndResponse($arg);
  }

  protected function randomPosts()
  {
    //这里的随机内容我加了如果启用了比比插件则返回博客
    if (is_plugin_active('smartbeebee/smartbeebee.php')) {
      $arg = array('post_type' => 'beebee_blog', 'orderby' => 'rand');
    } else {
      $arg = array('post_type' => 'post', 'orderby' => 'rand');
    }
    $this->queryAndResponse($arg);
  }

  protected function hotestPosts()
  {  
    //这里的热门内容我加了如果启用了比比插件则返回博客
    if (is_plugin_active('smartbeebee/smartbeebee.php')) {
      $arg = array(
        'post_type' => 'beebee_blog',
        'orderby' => 'views'
      );
    } else {
      $arg = array(
        'post_type' => 'post',
        'orderby' => 'views'
      );
    }
    $this->queryAndResponse($arg);
  }


  protected function onText()
  {
    $msg = $this->getRequest('content');

    //主要是加了用户互动消息的规则。即用户回复【国庆】进入图片合成状态，回复【Q】或者一天后自动退出合成头像状态
    $fromusername = $this->getRequest('fromusername');
    $time = 60 * 24 * 60; // 默认一天
    $transient_name = 'guoqing_timestamp_' . $fromusername;
    $adtransient = get_transient($transient_name);
    if ($adtransient) { //这里判断的是处理图片合成状态，回复消息才会回馈
      if ($msg == '9') {
        $items = array();
        $plugin_path = plugin_dir_url(__FILE__);
        $title = '国庆样式';
        $excerpt = '看中某一款输入红旗下方编号即可切换：';
        $thumb = $plugin_path . 'red/screen.min.jpg';
        $link = $plugin_path . 'red/index.html';

        $new_item = new NewsResponseItem($title, $excerpt, $thumb, $link);
        array_push($items, $new_item);
        $this->responseNews($items);
      } else if ($msg == 'a1' || $msg == 'a2' || $msg == 'a3' || $msg == 'a4' || $msg == 'a5' || $msg == 'a6') {
        // 切换国旗样式
        $transient_name = 'guoqing_style_' . $fromusername;
        set_transient($transient_name, $msg, $time);
        $this->responseText("成功切换红旗样式，继续下方⊕选择上传头像图片");
      } else if ($msg == 'b1' || $msg == 'b2' || $msg == 'b3' || $msg == 'b4' || $msg == 'b5' || $msg == 'b6') {
        // 切换国旗样式
        $transient_name = 'guoqing_style_' . $fromusername;
        set_transient($transient_name, $msg, $time);
        $this->responseText("成功切换红旗样式，继续下方⊕选择上传头像图片");
      }
    }
    
    if ($msg == '国庆') {
      $transient_name = 'guoqing_timestamp_' . $fromusername;
      set_transient($transient_name, time(), $time);
      $this->responseText("请点击左下角的键盘按钮，接着点击右侧⊕，然后选择上传头像图片，退出请输入 Q");
    } else if ($msg == 'Q') {
      $transient_name = 'guoqing_timestamp_' . $fromusername;
      delete_transient($transient_name);
      $this->responseText("退出成功！");
    } else if ($msg == '1') {
      $this->recentPosts();
    } else if ($msg == '2') {
      $this->randomPosts();
    } else if ($msg == '3') {
      $this->hotestPosts();
    } else if (strncmp($msg, '4', 1) == 0) {    // starts with '4'
      if (strlen($msg) == 1) {
        $this->responseText("您没有输入关键字，要输入[4关键字]进行搜索哦，比如 4马里奥 ");
      } else {
        $this->searchPosts(substr($msg, 1, strlen($msg) - 1));
      }
    } else {
      //这里是默认的自动回复，根据自己需要修改
      $this->responseText("欢迎关注比比小鱼哥个人公众号\n 回复[国庆]制作国庆头像\n 回复[1]查看最新文章" .
        "\n 回复[2]查看随机文章\n 回复[3]查看热门文章\n 回复[4关键字]搜索文章\n");
    }
  }

  protected function onImage()
  {
    //用户发照片的反馈，如何处于图片合成状态则进入下一步合成进程中，否则返回提示
    $fromusername = $this->getRequest('fromusername');
    $pic = $this->getRequest('picurl');
    $transient_name = 'guoqing_timestamp_' . $fromusername;
    $adtransient = get_transient($transient_name);

    $style_name = 'guoqing_style_' . $fromusername;
    $style_adtransient = get_transient($style_name);
    if (!$style_adtransient) {
      $style_adtransient = 'a1';
    }
    if ($adtransient) {
      $arg = array('pic' => $pic, 'sucai' => $style_adtransient, 'user' => $fromusername);
      $this->queryAndImageResponse($arg);
    } else {
      $this->responseText("如果你需要制作国庆图片\n 请先回复[国庆]开始创作");
    }
  }

  protected function onSubscribe()
  {
    // 用户关注公众号的自动回复，根据需要自行修改
    $this->responseText("欢迎关注比比小鱼哥个人公众号\n 回复[国庆]制作国庆头像\n 回复[1]查看最新文章" .
      "\n 回复[2]查看随机文章\n 回复[3]查看热门文章\n 回复[4关键字]搜索文章\n");
  }

  protected function onClick()
  {
    $key = $this->getRequest('EventKey');
    if ($key == "MENU_RECENT_POSTS") {
      $this->recentPosts();
    } else if ($key == "MENU_RANDOM_POSTS") {
      $this->randomPosts();
    } else if ($key == "MENU_HOTEST_POSTS") {
      $this->hotestPosts();
    }
  }
}
