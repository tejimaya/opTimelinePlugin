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
  public function createActivityDataByActivityDataAndViewerMemberIdForSearchAPI($activityDataList, $viewerMemberId)
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

    $responseDataList = $this->createActivityDataByActivityDataAndMemberDataForSearchAPI($activityDataList, $memberDataList);

    foreach ($responseDataList as &$response)
    {
      $id = $response['id'];

      if (isset($replyActivityDataList[$id]))
      {
        $replies = $replyActivityDataList[$id];

        $response['replies'] = $this->createActivityDataByActivityDataRowsAndMemberDataForSearchAPI($replies['data'], $memberDataList);
        $response['replies_count'] = $replies['count'];
      }
      else
      {
        $response['replies'] = null;
        $response['replies_count'] = 0;
      }
      $response['body'] = htmlspecialchars($response['body'], ENT_QUOTES, 'UTF-8', false);
      $response['body_html'] = htmlspecialchars($response['body_html'], ENT_QUOTES, 'UTF-8', false);
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

  private function createActivityDataByActivityDataAndMemberDataForSearchAPI($activityDataList, $memberData)
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

      $responseData['body'] = preg_replace('/<br\s\/>/', '&lt;br&nbsp;/&gt;', $activity->getBody());
      $responseData['body_html'] = op_activity_linkification(nl2br(op_api_force_escape($responseData['body'])));
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

  private function createActivityDataByActivityDataRowsAndMemberDataForSearchAPI($activityDataRows, $memberDataList)
  {

    $responseDataList = array();
    foreach ($activityDataRows as $row)
    {
      $responseData['id'] = $row['id'];
      $responseData['member'] = $memberDataList[$row['member_id']];

      $responseData['body'] = htmlspecialchars($row['body'], ENT_QUOTES, 'UTF-8', false);
      $responseData['body_html'] = op_activity_linkification(nl2br(htmlspecialchars($row['body'], ENT_QUOTES, 'UTF-8', false)));
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
