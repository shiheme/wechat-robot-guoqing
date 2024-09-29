<?php

function custom_excerpt_length($length)
{
  return 100;
}
add_filter('excerpt_length', 'custom_excerpt_length', 999);

/* no use for get_the_excerpt
function new_excerpt_more( $more ) {
	return '[.....]';
}
add_filter('excerpt_more', 'new_excerpt_more');
 */


/**
 *
 * 返回摘要，50个汉字加省略号
 *
 * @raw_excerpt 由调用函数传来的未经处理的excerpt
 *
 */
function wechat_get_excerpt($raw_excerpt)
{
  $excerpt = wp_strip_all_tags($raw_excerpt);
  $excerpt = trim(preg_replace("/[\n\r\t ]+/", ' ', $excerpt), ' ');
  $excerpt = mb_substr($excerpt, 0, 50, 'utf8');
  $excerpt = $excerpt . '...';
  return $excerpt;
}

/**
 *
 * 返回post缩略图
 *
 */
function wechat_get_thumb($post, $size)
{
  $thumbnail_id = get_post_thumbnail_id($post->ID);
  if ($thumbnail_id) {
    $thumb = wp_get_attachment_image_src($thumbnail_id, $size);
    $thumb = $thumb[0];
  }

  if (empty($thumb)) {
    $thumb = '';
  }

  return $thumb;
}

/**
 *
 * 头像合成相关函数开始
 *
 */

function bee_resize($image, $width, $height, $info)
{
  $new_image = imagecreatetruecolor($width, $height);
  if ($info[2] === 3) {
    imagealphablending($new_image, false);
    imagesavealpha($new_image, true);
    imagefilledrectangle($new_image, 0, 0, $width, $height, imagecolorallocatealpha($new_image, 255, 255, 255, 127));
  }

  imagecopyresampled($new_image, $image, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);

  return $new_image;
}

function bee_imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct)
{
  $cut = imagecreatetruecolor($src_w, $src_h);
  imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
  imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
  imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct);
}

function bee_add_watermark_image($image, $sucai, $size, $upload_dir)
{
  $plugin_path = plugin_dir_url(__FILE__);
  // 通过$sucai的编码转化红旗框的样式
  if($sucai==='a1') {
    $apath = 'img/1.png';
  } else if($sucai==='a2') {
    $apath = 'img/2.png';
  } else if($sucai==='a3') {
    $apath = 'img/3.png';
  } else if($sucai==='a4') {
    $apath = 'img/4.png';
  } else if($sucai==='a5') {
    $apath = 'img/5.png';
  } else if($sucai==='a6') {
    $apath = 'img/6.png';
  } else if($sucai==='b1') {
    $apath = 'img/1-1.png';
  }  else if($sucai==='b2') {
    $apath = 'img/1-2.png';
  }  else if($sucai==='b3') {
    $apath = 'img/1-3.png';
  }  else if($sucai==='b4') {
    $apath = 'img/1-4.png';
  }  else if($sucai==='b5') {
    $apath = 'img/1-5.png';
  }  else if($sucai==='b6') {
    $apath = 'img/1-6.png';
  } else {
    $apath = 'img/1.png';
  } 
  $watermark_file = $plugin_path . $apath;
  $url = $watermark_file;
  $watermark_file_info = getimagesize($url);

  switch ($watermark_file_info['mime']) {
    case 'image/jpeg':
    case 'image/pjpeg':
      $watermark = imagecreatefromjpeg($url);
      break;

    case 'image/gif':
      $watermark = imagecreatefromgif($url);
      break;

    case 'image/png':
      $watermark = imagecreatefrompng($url);
      break;

    default:
      return false;
  }

  $image_width = $size[0];
  $image_height = $size[1];
  $dest_x = 0;
  $dest_y = 0;
  
  bee_imagecopymerge_alpha($image, bee_resize($watermark, $image_width, $image_height, $watermark_file_info), $dest_x, $dest_y, 0, 0, $image_width, $image_height, 100);
  
  return $image;
}


function bee_get_image_resource($filepath, $size, $mime_type)
{
  
  switch ($mime_type) {
    case 'image/jpeg':
    case 'image/pjpeg':
      $src_image = imagecreatefromjpeg($filepath);
      break;

    case 'image/png':
      $src_image = imagecreatefrompng($filepath);
      break;

    default:
    $src_image = false;
  }

  if($src_image) {
  
    $target_width = $size[0];
    $target_height = $size[1];
    
    // 获取源图像的宽度和高度
    $src_width = floor(imagesx($src_image));
    $src_height = floor(imagesy($src_image));

    // 计算缩放比例
    $ratio_w = $target_width / $src_width;
    $ratio_h = $target_height / $src_height;
    $ratio = max($ratio_w, $ratio_h);

    // 计算新的目标尺寸
    $new_width = floor($src_width * $ratio);
    $new_height = floor($src_height * $ratio);

    // 创建一个新的临时图像资源
    $temp_image = imagecreatetruecolor($new_width, $new_height);
    
    // 保持透明度
    imagealphablending($temp_image, false);
    imagesavealpha($temp_image, true);
    imagefilledrectangle($temp_image, 0, 0, $new_width, $new_height, imagecolorallocatealpha($temp_image, 255, 255, 255, 127));

    // 使用 imagecopyresampled 进行等比例缩放
    imagecopyresampled($temp_image, $src_image, 0, 0, 0, 0, $new_width, $new_height, $src_width, $src_height);

    // 创建最终的目标图像资源
    $dst_image = imagecreatetruecolor($target_width, $target_height);

    // 保持透明度
    imagealphablending($dst_image, false);
    imagesavealpha($dst_image, true);
    imagefilledrectangle($dst_image, 0, 0, $target_width, $target_height, imagecolorallocatealpha($dst_image, 255, 255, 255, 127));

    // 计算裁剪区域的起始点
    $src_x = ($new_width - $new_height > 2) ? floor(($new_width - $target_width) / 2) : 0;
    $src_y = ($new_height - $new_width > 2) ? floor(($new_height - $target_height) / 2) : 0;

    // 裁剪并复制到目标图像
    imagecopyresampled($dst_image, $temp_image, 0, 0, $src_x, $src_y, $target_width, $target_height, $target_width, $target_height);

    // 释放临时图像资源
    imagedestroy($temp_image);

    return $dst_image;
  }
  return '';
}

function bee_save_image_file($image, $mime_type, $filepath, $quality)
{

  switch ($mime_type) {
    case 'image/jpeg':
    case 'image/pjpeg':
      imagejpeg($image, $filepath, $quality);

      break;

    case 'image/png':
      imagepng($image, $filepath, (int)round(9 - (9 * $quality / 100), 0));
      header('Content-Type: image/png');

      break;
  }
}

function wechat_get_hecheng_thumb($pic, $sucai, $user, $size)
{
  $image_info = getimagesize($pic);
  $mime = $image_info['mime'];
  $upload_dir = wp_upload_dir();
  $timestamp = date('YmdHis');
  $save_dir = $upload_dir['basedir'] . '/jieri'; //生成的头像存储在 wordpress中的 wp-content/uploads/jieri/里的
  $newpic = $save_dir . '/merged-image-' . $user . '-' . $timestamp . '.jpg';
  
  $image = bee_get_image_resource($pic, $size, $mime);
  
  if ($image !== false) {
    $image = bee_add_watermark_image($image, $sucai, $size, $upload_dir);
    if ($image !== false) {
      bee_save_image_file($image, $mime, $newpic, 100);
      imagedestroy($image);
      $image = null;
    }
  }
  return $upload_dir['baseurl'] . '/jieri' . '/merged-image-' . $user . '-' . $timestamp . '.jpg';
}


/**
 * GET 请求
 * @param string $url
 * @return string $result
 */

function http_get($url)
{
  $oCurl = curl_init();
  if (stripos($url, "https://") !== FALSE) {
    curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
  }

  curl_setopt($oCurl, CURLOPT_URL, $url);
  curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);

  $result = curl_exec($oCurl);
  $status = curl_getinfo($oCurl);
  curl_close($oCurl);

  if (intval($status["http_code"]) == 200)
    return $result;
  return $status["http_code"];
}


/**
 * POST 请求
 * @param string $url
 * @param array $param
 * @return string $result
 */
function http_post($url, $param)
{

  $oCurl = curl_init();

  if (stripos($url, "https://") !== FALSE) {
    curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
  }

  if (is_string($param)) {
    $strPOST = $param;
  } else {
    $aPOST = array();
    foreach ($param as $key => $val) {
      $aPOST[] = $key . "=" . urlencode($val);
    }
    $strPOST =  join("&", $aPOST);
  }

  curl_setopt($oCurl, CURLOPT_URL, $url);
  curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($oCurl, CURLOPT_POST, true);
  curl_setopt($oCurl, CURLOPT_POSTFIELDS, $strPOST);

  $result = curl_exec($oCurl);
  $status = curl_getinfo($oCurl);
  curl_close($oCurl);
  if (intval($status["http_code"]) == 200)
    return $result;
  return $status["http_code"];
}
