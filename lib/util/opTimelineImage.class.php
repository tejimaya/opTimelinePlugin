<?php

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

  public static function copyByResourcePathAndTargetPath($resourcePath, $targetPath)
  {
    self::_crateDirIfNotExists($targetPath);
    copy($resourcePath, $targetPath);

    return true;
  }

  private static function _crateDirIfNotExists($path)
  {
    $dirPath = pathinfo($path, PATHINFO_DIRNAME);

    if (!file_exists($dirPath))
    {
      mkdir($dirPath, 0777, true);
    }

    return true;
  }

  public static function createMinimumImageByWidthSizeAndPaths($minimumWidthSize, $paths)
  {
    self::_crateDirIfNotExists($paths['target']);

    $image = self::_getImageResourceByPath($paths['resource']);
    $fileSize = self::getImageSizeByPath($paths['resource']);

    $newWidth = $minimumWidthSize;
    $newHeight = abs($minimumWidthSize * $fileSize['height'] / $fileSize['width']);

    $newImage = ImageCreateTrueColor($newWidth, $newHeight);
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $fileSize['width'], $fileSize['height']);

    self::_saveImageByImageResourceAndSavePath($newImage, $paths['target']);

    return true;
  }

  public static function getImageSizeByPath($path)
  {
    $image = self::_getImageResourceByPath($path);

    return array(
        'width' => imagesx($image),
        'height' => imagesy($image)
    );
  }

  private static function _getImageResourceByPath($path)
  {
    $info = getimagesize($path);
    switch ($info['mime'])
    {
      case 'image/png':
        $image = imagecreatefrompng($path);
        break;

      case 'image/jpeg':
        $image = imagecreatefromjpeg($path);
        break;

      case 'image/gif':
        $image = imagecreatefromgif($path);
        break;
    }

    return $image;
  }

  private static function _saveImageByImageResourceAndSavePath($resource, $savePath)
  {
    $extension = pathinfo($savePath, PATHINFO_EXTENSION);

    switch ($extension)
    {
      case 'png':
        return imagepng($resource, $savePath);
        break;

      case 'jpeg':
        return imagejpeg($resource, $savePath);
        break;

      case 'gif':
        return imagegif($resource, $savePath);
        break;
    }
  }

}
