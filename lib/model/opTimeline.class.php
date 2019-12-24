<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * opTimeline
 *
 * @package    OpenPNE
 * @subpackage opTimelinePlugin
 */

class opTimeline
{

  /**
   * @var opTimelineUser
   */
  private $user;

  private $imageContentSize;
  private $baseUrl;


  public function __construct(opTimelineUser $user, array $params)
  {
    $this->user = $user;

    $this->imageContentSize = $params['image_size'];
    $this->baseUrl = $params['base_url'];
  }

  const COMMENT_DISPLAY_MAX = 10;

  public function addPublicFlagByActivityDataForSearchAPIByActivityData(array $responseDataList, $activityDataList)
  {
    $publicFlags = array();
    foreach ($activityDataList as $activity)
    {
      $publicFlags[$activity->getId()] = $activity->getPublicFlag();
    }

    $publicStatusTextList = array(
        ActivityDataTable::PUBLIC_FLAG_OPEN => 'open',
        ActivityDataTable::PUBLIC_FLAG_SNS => 'sns',
        ActivityDataTable::PUBLIC_FLAG_FRIEND => 'friend',
        ActivityDataTable::PUBLIC_FLAG_PRIVATE => 'private'
    );

    foreach ($responseDataList as &$data)
    {
      $publicFlag = $publicFlags[$data['id']];
      $data['public_status'] = $publicStatusTextList[$publicFlag];
    }
    unset($data);

    return $responseDataList;
  }

  /**
   * メソッドを実行する前にopJsonApiをロードしておく必要がある
   */
  public function createActivityDataByActivityDataAndViewerMemberIdForSearchAPI($activityDataList, $viewerMemberId, $isSmartPhone = false)
  {
    $activityIds = array();
    foreach ($activityDataList as $activity)
    {
      $activityIds[] = $activity->getId();
    }

    if (empty($activityIds))
    {
      return array();
    }

    $replyActivityDataList = $this->findReplyActivityDataByActivityIdsGroupByActivityId($activityIds);

    $memberIds = $this->extractionMemberIdByActivityDataAndReplyActivityDataRows(
                    $activityDataList, $replyActivityDataList);
    $memberDataList = $this->user->createMemberDataByViewerMemberIdAndMemberIdsForAPIResponse($viewerMemberId, $memberIds);

    $responseDataList = $this->createActivityDataByActivityDataAndMemberDataForSearchAPI($activityDataList, $memberDataList, $isSmartPhone);

    foreach ($responseDataList as &$response)
    {
      $id = $response['id'];

      if (isset($replyActivityDataList[$id]))
      {
        $replies = $replyActivityDataList[$id];

        $response['replies'] = $this->createActivityDataByActivityDataRowsAndMemberDataForSearchAPI($replies['data'], $memberDataList, $isSmartPhone);
        $response['replies_count'] = $replies['count'];
      }
      else
      {
        $response['replies'] = null;
        $response['replies_count'] = 0;
      }
    }
    unset($response);

    return $responseDataList;
  }

  private function extractionMemberIdByActivityDataAndReplyActivityDataRows($activities, $replyActivitiyRows)
  {
    $memberIds = array();
    foreach ($activities as $activity)
    {
      $memberIds[] = $activity->getMemberId();
    }

    foreach ($replyActivitiyRows as $activityDataList)
    {
      foreach ($activityDataList['data'] as $activityData)
      {
        $memberIds[] = $activityData['member_id'];
      }
    }

    $memberIds = array_unique($memberIds);

    return $memberIds;
  }

  private function createActivityDataByActivityDataAndMemberDataForSearchAPI($activityDataList, $memberData, $isSmartPhone = false)
  {
    $activityIds = array();
    foreach ($activityDataList as $activity)
    {
      $activityIds[] = $activity->getId();
    }

    $responseDataList = array();
    foreach ($activityDataList as $activity)
    {
      $image = $this->getActivityImage($activity->getId());

      $responseData = array();
      $responseData['id'] = $activity->getId();
      $responseData['member'] = $memberData[$activity->getMemberId()];
      $responseData['body'] = htmlspecialchars($activity->getBody(), ENT_QUOTES, 'UTF-8');
      $responseData['body_html'] = $this->convCmd(nl2br($responseData['body']), is_null($activity->in_reply_to_activity_id) ? false: true, $isSmartPhone);
      $responseData['uri'] = $activity->getUri();
      $responseData['source'] = $activity->getSource();
      $responseData['source_uri'] = $activity->getSourceUri();

      $responseData['image_url'] = $image ? sf_image_path($image->getFile()->getName(), array('size' => '120x120')) : null;
      $responseData['image_large_url'] = $image ? sf_image_path($image->getFile()->getName()): null;
      $responseData['created_at'] = date('r', strtotime($activity->getCreatedAt()));

      $responseDataList[] = $responseData;
    }

    return $responseDataList;
  }

  private function createActivityDataByActivityDataRowsAndMemberDataForSearchAPI($activityDataRows, $memberDataList, $isSmartPhone = false)
  {
    $responseDataList = array();
    foreach ($activityDataRows as $row)
    {
      $responseData['id'] = $row['id'];
      $responseData['member'] = $memberDataList[$row['member_id']];
      $responseData['body'] = htmlspecialchars($row['body'], ENT_QUOTES, 'UTF-8');
      $responseData['body_html'] = $this->convCmd(nl2br($responseData['body']), is_null($row['in_reply_to_activity_id']) ? false: true, $isSmartPhone);
      $responseData['uri'] = $row['uri'];
      $responseData['source'] = $row['source'];
      $responseData['source_uri'] = $row['source_uri'];

      //コメントでは画像を投稿できない
      $responseData['image_url'] = null;
      $responseData['image_large_url'] = null;
      $responseData['created_at'] = date('r', strtotime($row['created_at']));

      $responseDataList[] = $responseData;
    }

    return $responseDataList;
  }

  public function convCmd($_body, $_isMini, $_isSmartPhone)
  {
    $body = $this->_unfoldGooGl($_body);
    $urlList = $this->_getUrlList($body);

    foreach ($urlList as $urlStr) {
      if (preg_match('/\.(jpg|jpeg|png|gif)/', $urlStr))
      {
        $body = str_replace($urlStr, '<div><a href="'.$urlStr.'"><img src="'.$urlStr.'"></a></div>', $body);
      }
      else if (preg_match('/(http:|https:)\/\/jp\.youtube\.com\/watch\?v=([a-zA-Z0-9_\-]+)/', $urlStr, $matches))
      {
        $body = str_replace($urlStr, $this->_getYoutubeCmd($matches[2], $_isMini, $_isSmartPhone), $body);
      }
      else if (preg_match('/(http:|https:)\/\/(?:www\.|)youtube\.com\/watch\?(?:.+&amp;)?v=([a-zA-Z0-9_\-]+)/', $urlStr, $matches))
      {
        $body = str_replace($urlStr, $this->_getYoutubeCmd($matches[2], $_isMini, $_isSmartPhone), $body);
      }
      else if (preg_match('/(http:|https:)\/\/youtu\.be\/([a-zA-Z0-9_\-]+)/', $urlStr, $matches))
      {
        $body = str_replace($urlStr, $this->_getYoutubeCmd($matches[2], $_isMini, $_isSmartPhone), $body);
      }
      else if (preg_match('/(http:|https:)\/\/www\.nicovideo\.jp\/watch\/([a-z0-9]+)/', $urlStr, $matches))
      {
        $body = str_replace($urlStr, $this->_getNicoVideoCmd($matches[2], $_isMini, $_isSmartPhone), $body);
      }
      else if (preg_match('/(http:|https:)\/\/maps\.google\.co\.jp\/maps[?\/](.+)/', $urlStr, $matches))
      {
        $body = str_replace($urlStr, $this->_getGoogleMapCmd($matches[2], $_isMini, $_isSmartPhone), $body);
      }
      else if (preg_match('/(http:|https:)\/\/maps\.google\.com\/maps[?\/](.+)/', $urlStr, $matches))
      {
        $body = str_replace($urlStr, $this->_getGoogleMapCmd($matches[2], $_isMini, $_isSmartPhone), $body);
      }
      else if (preg_match('/(http:|https:)\/\/www\.google\.co\.jp\/maps[?\/](.+)/', $urlStr, $matches))
      {
        $body = str_replace($urlStr, $this->_getGoogleMapCmd($matches[2], $_isMini, $_isSmartPhone), $body);
      }
      else if (preg_match('/(http:|https:)\/\/www\.google\.com\/maps[?\/](.+)/', $urlStr, $matches))
      {
        $body = str_replace($urlStr, $this->_getGoogleMapCmd($matches[2], $_isMini, $_isSmartPhone), $body);
      }
      else if (preg_match('/((http:|https:)\/\/[\x21-\x26\x28-\x7e]+)/', $urlStr, $matches))
      {
        $body = str_replace($urlStr, $this->_getLink($urlStr), $body);
      }
    }

    return $body;
  }

  private function _getUrlList($_body)
  {
    $pattern = '(https?://[-_.!~*\'()a-zA-Z0-9;/?:@&=+$,%#]+)';
    $list = [];
    if(preg_match_all($pattern, $_body, $result) !== false){
      foreach ($result[0] as $value){
        $list[] = $value;
      }
    }

    return $list;
  }

  private function _unfoldGooGl($_body)
  {
    $pattern = '(https?://goo\.gl/maps/[-_.!~*\'()a-zA-Z0-9;/?:@&=+$,%#]+)';
    if(preg_match_all($pattern, $_body, $result) !== false){
      foreach ($result[0] as $value){
        // プロトコルは https にする
        // http にした場合、いったん https にリダイレクトされてしまうため、'Location' の内容が変わってしまう
        $gglUrl = preg_replace('/^https?/', 'https', $value);
        $header = get_headers($gglUrl, 1);

        $gglLocation = $header["Location"];
        // 'Location' の内容が array の場合、1つ目を取得
        if (is_array($gglLocation))
        {
          $gglLocation = $gglLocation[0];
        }
        $_body = preg_replace($pattern, $gglLocation, $_body);
      }
    }

    return $_body;
  }

  private function _getYoutubeCmd($_id, $_isMini, $_isSmartPhone)
  {
    $w = 370;
    $h = 277;
    if ($_isMini)
    {
      $w = 300;
      $h = 230;
    }
    if ($_isSmartPhone)
    {
      $w = 250;
      $h = 230;
    }
    $html = '<object width="'.$w.'" height="'.$h.'">';
    $html .= '<param name="movie" value="https://www.youtube.com/v/'.$_id.'"></param>';
    $html .= '<embed src="https://www.youtube.com/v/'.$_id.'" type="application/x-shockwave-flash" width="'.$w.'" height="'.$h.'"></embed>';
    $html .= '</object>';

    return $html;
  }

  private function _getNicoVideoCmd($_id, $_isMini, $_isSmartPhone)
  {
    $w = 350;
    $h = 230;
    if ($_isMini)
    {
      $w = 300;
      $h = 230;
    }
    if ($_isSmartPhone)
    {
      $w = 250;
      $h = 270;
    }

    $url = 'https://www.nicovideo.jp/watch/'.$_id;

    $html = '<iframe id="iframe1" src="https://ext.nicovideo.jp/thumb/'.$_id.'" width="'.$w.'"';
    $html .= ' height="'.$h.'" scrolling="no" style="border: solid 1px #ccc;" frameborder="0">';
    $html .= '<a href="'.$url.'">'.$url.'</a>';
    $html .= '</iframe>';

    return $html;
  }

  private function _getGoogleMapCmd($_id, $_isMini, $_isSmartPhone)
  {
    $w = 350;
    $h = 350;
    if ($_isMini)
    {
      $w = 300;
      $h = 300;
    }
    if ($_isSmartPhone)
    {
      $w = 250;
      $h = 270;
    }

    $param = [ 'lon' => 0, 'lat' => 0, 'z' => 15, 't' => '', 'q' => '' ];
    preg_match(
      '/(?:^|\/)@(-?[0-9.]+),(-?[0-9.]+),([0-9.]+z)(\/data=!3m1!1e3)?/',
      $_id,
      $result
    );

    if ($result)
    {
      $param['lon'] = $result[1];
      $param['lat'] = $result[2];
      $param['z'] = $result[3];
      if ($result[4])
      {
        $param['t'] = 'k';
      }
    }
    else
    {
      $query = explode('&amp;', $_id);
      foreach($query as $q)
      {
        $pair = explode('=', $q);
        if (2 !== count($pair))
        {
          continue;
        }
        $key = $pair[0];
        $value = $pair[1];
        if (!$param[$key])
        {
          $param[$key] = $value;
        }
        else if ('ll' === $key)
        {
          $v = explode(',', $value);
          $param['lon'] = $v[0];
          $param['lat'] = $v[1];
        }
      }
    }

    $html = '<iframe marginwidth="0" marginheight="0" hspace="0" vspace="0" frameborder="0" scrolling="no" bordercolor="#000000"';
    $html .= 'src="/googlemaps';
    $html .= '?x='.$param['lon'];
    $html .= '&y='.$param['lat'];
    $html .= '&z='.$param['z'];
    $html .= '&t='.$param['t'];
    $html .= '&q='.$param['q'];
    $html .= '" name="gmap" width="'.$w.'" height="'.$h.'">この部分はインラインフレームを使用しています。</iframe>';

    return $html;
  }

  private function _getLink($_url)
  {
    return '<a href="'.$_url.'"><div class="urlBlock"><img src="https://mozshot.nemui.org/shot?'.$_url.'"><br>'.$_url.'</div></a>';
  }

  public function findReplyActivityDataByActivityIdsGroupByActivityId(array $activityIds)
  {
    static $queryCacheHash;

    if (!$queryCacheHash)
    {
      $q = Doctrine_Query::create();
      $q->from('ActivityData');
      $q->whereIn('in_reply_to_activity_id', $activityIds);
      $q->orderBy('in_reply_to_activity_id, created_at DESC');
      $searchResult = $q->fetchArray();

      $queryCacheHash = $q->calculateQueryCacheHash();
    }
    else
    {
      $q->setCachedQueryCacheHash($queryCacheHash);
      $searchResult = $q->fetchArray();
    }

    $replies = array();
    foreach ($searchResult as $row)
    {
      $targetId = $row['in_reply_to_activity_id'];

      if (!isset($replies[$targetId]['data']) || count($replies[$targetId]['data']) < self::COMMENT_DISPLAY_MAX)
      {
        $replies[$targetId]['data'][] = $row;
      }

      if (isset($replies[$targetId]['count']))
      {
        $replies[$targetId]['count']++;
      }
      else
      {
        $replies[$targetId]['count'] = 1;
      }
    }

    return $replies;
  }

  public function searchActivityDataByAPIRequestDataAndMemberId($requestDataList, $memberId)
  {
    $builder = opActivityQueryBuilder::create()
                    ->setViewerId($memberId);

    if (isset($requestDataList['target']))
    {
      if ('friend' === $requestDataList['target'])
      {
        $builder->includeSelf()
                ->includeFriends($requestDataList['target_id'] ? $requestDataList['target_id'] : null);
      }

      if ('community' === $requestDataList['target'])
      {
        $builder
                ->includeSelf()
                ->includeFriends()
                ->includeSns()
                ->setCommunityId($requestDataList['target_id']);
      }
    }
    else
    {
      if (isset($requestDataList['member_id']))
      {
        $builder->includeMember($requestDataList['member_id']);
      }
      else
      {
        $builder
                ->includeSns()
                ->includeFriends()
                ->includeSelf();
      }
    }

    $query = $builder->buildQuery();

    if (isset($requestDataList['keyword']))
    {
      $query->andWhereLike('body', $requestDataList['keyword']);
    }

    $globalAPILimit = sfConfig::get('op_json_api_limit', 20);
    if (isset($requestDataList['count']) && (int) $requestDataList['count'] < $globalAPILimit)
    {
      $query->limit($requestDataList['count']);
    }
    else
    {
      $query->limit($globalAPILimit);
    }

    if (isset($requestDataList['max_id']))
    {
      $query->addWhere('id <= ?', $requestDataList['max_id']);
    }

    if (isset($requestDataList['since_id']))
    {
      $query->addWhere('id > ?', $requestDataList['since_id']);
    }

    if (isset($requestDataList['activity_id']))
    {
      $query->addWhere('id = ?', $requestDataList['activity_id']);
    }

    $query->andWhere('in_reply_to_activity_id IS NULL');

    return $query->execute();
  }

  public function embedImageUrlToContentForSearchAPI(array $responseDataList)
  {
    $imageUrls = array();
    foreach ($responseDataList as $row)
    {
      if (!is_null($row['image_url']))
      {
        if ('large' === $this->imageContentSize)
        {
          $imageUrls[$row['id']] = $row['image_large_url'];
        }
        else
        {
          $imageUrls[$row['id']] = $row['image_url'];
        }
      }
    }

    foreach ($responseDataList as &$data)
    {
      $id = $data['id'];

      if (isset($imageUrls[$id]))
      {
        $data['body'] = $data['body'].' '.$imageUrls[$id];
        $data['body_html'] = $data['body_html'].'<a href="'.$data['image_large_url'].'" rel="lightbox"><div><img src="'.$imageUrls[$id].'"></div></a>';
      }
    }

    return $responseDataList;
  }

  public function createPostActivityFromAPIByApiDataAndMemberId($apiData, $memberId)
  {
    $body = (string) $apiData['body'];

    $options = array();

    if (isset($apiData['public_flag']))
    {
      $options['public_flag'] = $apiData['public_flag'];
    }

    if (isset($apiData['in_reply_to_activity_id']))
    {
      $options['in_reply_to_activity_id'] = $apiData['in_reply_to_activity_id'];
    }

    if (isset($apiData['uri']))
    {
      $options['uri'] = $apiData['uri'];
    }
    elseif (isset($apiData['url']))
    {
      $options['uri'] = $apiData['url'];
    }

    if (isset($apiData['target']) && 'community' === $apiData['target'])
    {
      $options['foreign_table'] = 'community';
      $options['foreign_id'] = $apiData['target_id'];
    }

    $options['source'] = 'API';

    return Doctrine::getTable('ActivityData')->updateActivity($memberId, $body, $options);
  }

  public function createActivityImageByFileInfoAndActivity(sfValidatedFile $fileInfo, ActivityData $activity)
  {
    $file = new File();
    $file->setFromValidatedFile($fileInfo);
    $file->name = 'ac_'.$activity->member_id.'_'.$file->name;
    $file->save();

    $activityImage = new ActivityImage();
    $activityImage->setActivityData($activity);
    $activityImage->setFileId($file->getId());
    $activityImage->setMimeType($file->type);
    $activityImage->save();

    return $activityImage;
  }

  private function getActivityImage($timelineId)
  {
    return Doctrine::getTable('ActivityImage')->findOneByActivityDataId($timelineId);
  }

  public static function getViewPhoto()
  {
    $viewPhoto = Doctrine::getTable('SnsConfig')->get('op_timeline_plugin_view_photo', false);
    if (false !== $viewPhoto)
    {
      return $viewPhoto;
    }
    return 1;
  }
}
