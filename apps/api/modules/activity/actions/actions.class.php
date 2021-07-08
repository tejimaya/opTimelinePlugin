<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */


/**
 * activity actions.
 *
 * @package    OpenPNE
 * @subpackage opTimelinePlugin
 * @author     tatsuya ichikawa <ichikawa@tejimaya.com>
 */
class activityActions extends opJsonApiActions
{

  const TWEET_MAX_LENGTH = 140;
  const COMMENT_DEFAULT_LIMIT = 15;

  /**
   * active data model maked by POSTAPI
   */
  private $createdActivity;

  /**
   * @var opTimeline
   */
  private $timeline;

  const DEFAULT_IMAGE_SIZE = 'large';

  public function preExecute()
  {
    parent::preExecute();

    $user = new opTimelineUser();

    $params = array();
    $params['image_size'] = $this->getRequestParameter('image_size', self::DEFAULT_IMAGE_SIZE);

    $request = sfContext::getInstance()->getRequest();
    $params['base_url'] = $request->getUriPrefix().$request->getRelativeUrlRoot();

    $this->timeline = new opTimeline($user, $params);

    $this->loadHelperForUseOpJsonAPI();
    $this->memberId = $this->getUser()->getMemberId();
  }

  public function executeCommentSearch(sfWebRequest $request)
  {
    if (!isset($request['timeline_id']))
    {
      $this->forward400('timeline id is not specified');
    }

    if ('' === (string) $request['timeline_id'])
    {
      $this->forward400('timeline id is not specified');
    }

    $limit = isset($request['count']) ? $request['count'] : sfConfig::get('op_json_api_limit', self::COMMENT_DEFAULT_LIMIT);

    $timelineId = $request['timeline_id'];
    $activity = Doctrine::getTable('ActivityData')->find($timelineId);

    if (0 < count($activity))
    {
      $this->replies = $activity->getReplies(ActivityDataTable::PUBLIC_FLAG_SNS, $limit);
    }
  }

  public function executePost(sfWebRequest $request)
  {
    $errorResponse = $this->getErrorResponseIfBadRequestOfTweetPost($request);
    if (!is_null($errorResponse))
    {
      return $this->renderJSONDirect($errorResponse);
    }

    $validator = new opValidatorImageFile(array('required' => false));
    $validator->setOption('max_size', opTimelinePluginUtil::getFileSizeMax());
    try
    {
      $file = $request->getFiles('timeline-submit-upload');
      if (0 !== count($file))
      {
        $validatedFile = $validator->clean($file);
      }
      else
      {
        $validatedFile = null;
      }
    }
    catch (sfValidatorError $e)
    {
      if ('max_size' === $e->getCode())
      {
        $errorResponse = array('status' => 'error', 'message' => 'file size over', 'type' => 'file_size');
      }
      elseif ('mime_types' === $e->getCode())
      {
        $errorResponse = array('status' => 'error', 'message' => 'not image', 'type' => 'not_image');
      }
      else
      {
        $errorResponse = array('status' => 'error', 'message' => 'file upload error', 'type' => 'upload');
      }

      return $this->renderJSONDirect($errorResponse);
    }

    $this->createActivityDataByRequest($request);

    if (!is_null($validatedFile))
    {
      $this->timeline->createActivityImageByFileInfoAndActivity($validatedFile, $this->createdActivity);
    }

    $responseData = $this->createResponActivityDataOfPost();
    $responseData['body'] = htmlspecialchars($responseData['body'], ENT_QUOTES, 'UTF-8');
    if (is_null($request->getParameter('in_reply_to_activity_id')))
    {
      $responseData['body_html'] = $this->timeline->convCmd(nl2br(op_api_force_escape($responseData['body'])), false, $request->isSmartphone());
    }
    else
    {
      $responseData['body_html'] = $this->timeline->convCmd(nl2br($responseData['body']), true, $request->isSmartphone());
    }

    if (!is_null($validatedFile))
    {
      return $this->renderJSONDirect(array('status' => 'success', 'message' => 'file up success', 'data' => $responseData));
    }

    return $this->renderJSONDirect(array('status' => 'success', 'message' => 'tweet success', 'data' => $responseData));
  }

  private function createResponActivityDataOfPost()
  {
    $this->loadHelperForUseOpJsonAPI();
    $activity = op_api_activity($this->createdActivity);
    $replies = $this->createdActivity->getReplies();
    if (0 < count($replies))
    {
      $activity['replies'] = array();

      foreach ($replies as $reply)
      {
        $activity['replies'][] = op_api_activity($reply);
      }
    }

    return $activity;
  }

  /**
   * なぜかPOSTAPIだとJSONレンダーがうまくうごかなかった
   */
  private function renderJSONDirect(array $data)
  {
    echo json_encode($data);
    exit;
  }

  private function createActivityDataByRequest(sfWebRequest $request)
  {
    $saveData = $request->getParameterHolder()->getAll();
    $memberId = $this->getUser()->getMemberId();

    $this->createdActivity = $this->timeline->createPostActivityFromAPIByApiDataAndMemberId($saveData, $memberId);
  }

  private function getErrorResponseIfBadRequestOfTweetPost(sfWebRequest $request)
  {
    $body = (string) $request['body'];

    $errorInfo = array('status' => 'error', 'type' => 'tweet');

    if (is_null($body) || '' == mb_ereg_replace('^(\s|　)+|(\s|　)+$', '', $body))
    {
      $errorInfo['message'] = 'body parameter not specified.';

      return $errorInfo;
    }

    if (mb_strlen($body) > self::TWEET_MAX_LENGTH)
    {
      $errorInfo['message'] = 'The body text is too long.';

      return $errorInfo;
    }

    if (isset($request['target']) && 'community' === $request['target'])
    {
      if (!isset($request['target_id']))
      {
        $errorInfo['message'] = 'target_id parameter not specified.';

        return $errorInfo;
      }

      $memberId = $this->getUser()->getMemberId();
      $isCommunityMember = Doctrine_Core::getTable('CommunityMember')->isMember($memberId, $request['target_id']);
      if (!$isCommunityMember)
      {
        $errorInfo['message'] = 'You don\'t participate in this community.';

        return $errorInfo;
      }
    }

    return null;
  }

  public function executeSearch(sfWebRequest $request)
  {
    $parameters = $request->getGetParameters();

    if (isset($parameters['target']))
    {
      $this->forward400IfInvalidTargetForSearchAPI($parameters);
    }

    $activityData = $this->timeline->searchActivityDataByAPIRequestDataAndMemberId(
                    $request->getGetParameters(), $this->getUser()->getMemberId());

    $activitySearchData = $activityData->getData();
    //一回も投稿していない
    if (empty($activitySearchData))
    {
      return $this->renderJSON(array('status' => 'success', 'data' => array()));
    }

    $responseData = $this->timeline->createActivityDataByActivityDataAndViewerMemberIdForSearchAPI(
                    $activityData, $this->getUser()->getMemberId(), $request->isSmartphone());

    $responseData = $this->timeline->addPublicFlagByActivityDataForSearchAPIByActivityData($responseData, $activityData);
    $responseData = $this->timeline->embedImageUrlToContentForSearchAPI($responseData);

    return $this->renderJSON(array('status' => 'success', 'data' => $responseData));
  }

  private function loadHelperForUseOpJsonAPI()
  {
    //op_api_activityを使用するために必要なヘルパーを読み込む
    $configuration = $this->getContext()->getConfiguration();
    $configuration->loadHelpers('opJsonApi');
    $configuration->loadHelpers('opUtil');
    $configuration->loadHelpers('Asset');
    $configuration->loadHelpers('Helper');
    $configuration->loadHelpers('Tag');
    $configuration->loadHelpers('sfImage');
  }

  private function forward400IfInvalidTargetForSearchAPI(array $params)
  {
    $validTargets = array('friend', 'community');

    if (!in_array($params['target'], $validTargets))
    {
      return $this->forward400('target parameter is invalid.');
    }

    if ('community' === $params['target'])
    {
      $this->forward400Unless(
              Doctrine::getTable('CommunityMember')->isMember($this->getUser()->getMemberId(), $params['target_id']),
              'You are not community member'
              );

      $this->forward400Unless($params['target_id'], 'target_id parameter not specified.');
    }
  }

  public function executeMember(sfWebRequest $request)
  {
    if ($request['id'])
    {
      $request['member_id'] = $request['id'];
    }

    if (isset($request['target']))
    {
      unset($request['target']);
    }

    $this->forward('activity', 'search');
  }

  public function executeFriends(sfWebRequest $request)
  {
    $request['target'] = 'friend';

    if (isset($request['member_id']))
    {
      $request['target_id'] = $request['member_id'];
      unset($request['member_id']);
    }
    elseif (isset($request['id']))
    {
      $request['target_id'] = $request['id'];
      unset($request['id']);
    }

    $this->forward('activity', 'search');
  }

  public function executeCommunity(sfWebRequest $request)
  {
    $request['target'] = 'community';

    if (isset($request['community_id']))
    {
      $request['target_id'] = $request['community_id'];
      unset($request['community_id']);
    }
    elseif (isset($request['id']))
    {
      $request['target_id'] = $request['id'];
      unset($request['id']);
    }
    else
    {
      $this->forward400('community_id parameter not specified.');
    }

    $this->forward('activity', 'search');
  }

  public function executeDelete(sfWebRequest $request)
  {
    if (isset($request['activity_id']))
    {
      $activityId = $request['activity_id'];
    }
    elseif (isset($request['id']))
    {
      $activityId = $request['id'];
    }
    else
    {
      $this->forward400('activity_id parameter not specified.');
    }

    $activity = Doctrine::getTable('ActivityData')->find($activityId);

    $this->forward404Unless($activity, 'Invalid activity id.');

    $this->forward403Unless($activity->getMemberId() === $this->getUser()->getMemberId());

    $activity->delete();

    return $this->renderJSON(array('status' => 'success'));
  }

  public function executeMentions(sfWebRequest $request)
  {
    $builder = opActivityQueryBuilder::create()
                    ->setViewerId($this->getUser()->getMemberId())
                    ->includeMentions();

    $query = $builder->buildQuery()
                    ->andWhere('in_reply_to_activity_id IS NULL')
                    ->andWhere('foreign_table IS NULL')
                    ->andWhere('foreign_id IS NULL')
                    ->limit(20);

    $this->activityData = $query->execute();

    $this->setTemplate('array');
  }
}
