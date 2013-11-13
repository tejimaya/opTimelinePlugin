<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * opTimelineImage
 *
 * @package    OpenPNE
 * @subpackage opTimelinePlugin
 */

class opTimelineImage
{

  /**
   * サイズ未指定 縮小をしていない
   */
  const SIZE_UNDESIGNATED = 0;

  public static function getNotImageUrl()
  {
    return op_image_path('no_image.gif', true);
  }

  public static function findUploadDirPath($fileName, $size = self::SIZE_UNDESIGNATED)
  {
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);

    $uploadBasePath = '/cache/img/'.$extension;
    $uploadSubPath = self::findUploadSubPath($size);
    $uploadDirPath = sfConfig::get('sf_web_dir').$uploadBasePath.'/'.$uploadSubPath;

    return $uploadDirPath;
  }

  public static function addExtensionToBasenameForFileTable($basename)
  {
    $match = array();
    preg_match('/.*_(png|jpeg|jpg|gif)$/', $basename, $match);

    if (!isset($match[1]))
    {
      return false;
    }

    return $basename.'.'.$match[1];
  }

  public static function findUploadSubPath($size = self::SIZE_UNDESIGNATED)
  {
    if ($size === self::SIZE_UNDESIGNATED)
    {
      $uploadSubPath = 'w_h';
    }
    else
    {
      $uploadSubPath = 'w'.$size.'_h'.$size;
    }

    return $uploadSubPath;
  }
}
