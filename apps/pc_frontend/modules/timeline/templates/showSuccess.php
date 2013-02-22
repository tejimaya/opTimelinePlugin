<?php use_helper('opUtil', 'Javascript') ?>
<script type="text/javascript">
//<![CDATA[
var gorgon = {
      'activity_id': <?php echo $activity->getId() ?>,
      'count': 1,
    };
var viewPhoto = '<?php echo $viewPhoto ?>';
var MAXLENGTH = 140;
var fileMaxSize = '<?php echo opTimelinePluginUtil::getFileSizeMax() ?>';
//]]>
</script>
<?php use_javascript('/opTimelinePlugin/js/jquery.timeline.js', 'last') ?>
<?php use_javascript('/opTimelinePlugin/js/jquery.timeago.js', 'last') ?>
<?php use_javascript('/opTimelinePlugin/js/timeline-loader.api.js', 'last') ?>
<?php use_javascript('/opTimelinePlugin/js/lightbox.js', 'last') ?>
<?php use_stylesheet('/opTimelinePlugin/css/lightbox.css', 'last') ?>
<?php use_stylesheet('/opTimelinePlugin/css/bootstrap.css', 'last') ?>
<?php use_stylesheet('/opTimelinePlugin/css/timeline.css', 'last') ?>

<?php include_partial('timeline/timelineTemplate') ?>

<div class="partsHeading"><h3><?php echo $activity->getMember()->getName(); ?>さんのタイムライン</h3></div>

    <div class="timeline-large">
      <div id="timeline-loading" style="text-align: center;"><?php echo op_image_tag('ajax-loader.gif', array()) ?></div>
      <div id="timeline-list" data-last-id=""data-loadmore-id="">

      </div>
    </div>


<script id="LikelistTemplate" type="text/x-jquery-tmpl">
<table>
<tr style="padding: 2px;">
<td style="width: 48px; padding: 2px;"><a href="${profile_url}"><img src="${profile_image}" width="48"></a></td>
<td style="padding: 2px;"><a href="${profile_url}">${name}</a></td>
</tr>
</table>
</script>

<div id="likeModal" class="modal hide">
  <div class="modal-header">
    <h1>「いいね！」と言っている人</h1>
  </div>
  <div class="like-modal-body">
  </div>
  <div class="modal-footer">
    <a href="#" class="btn close" data-dismiss="modal" aria-hidden="true">閉じる</a>
  </div>
</div>
