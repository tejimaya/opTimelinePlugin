<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * opTimelinePluginConfigurationForm
 *
 * @package    OpenPNE
 * @subpackage opTimelinePlugin
 * @author     tatsuya ichikawa <ichikawa@tejimaya.com>
 */
class opTimelinePluginConfigurationForm extends BaseForm
{
  public function configure()
  {
    $choices = array('1' => '使用する', '0' => '使用しない');

    $this->setWidget('view_photo', new sfWidgetFormSelectRadio(array('choices' => $choices)));
    $this->setValidator('view_photo', new sfValidatorChoice(array('choices' => array_keys($choices))));
    $this->setDefault('view_photo', Doctrine::getTable('SnsConfig')->get('op_timeline_plugin_view_photo', '1'));
    $this->widgetSchema->setLabel('view_photo', '画像表示');
    $this->widgetSchema->setHelp('view_photo', '画像URLに自動でimgタグを付けない場合はOFFに設定して下さい。デフォルトはON');
    
    $this->setWidget('timeline_comment_reply', new sfWidgetFormSelectRadio(array('choices' => $choices)));
    $this->setValidator('timeline_comment_reply', new sfValidatorChoice(array('choices' => array_keys($choices))));
    $this->setDefault('timeline_comment_reply', Doctrine::getTable('SnsConfig')->get('op_timeline_plugin_timeline_comment_reply', '0'));
    $this->widgetSchema->setLabel('timeline_comment_reply', '%activity% comment reply');
    $this->widgetSchema->setHelp('timeline_comment_reply', 'If this is used, you can reply to the %activity% comment.');

    if (version_compare(OPENPNE_VERSION, '3.6beta1-dev', '<'))
    {
      unset($this['view_photo']);
    }

    $this->widgetSchema->setNameFormat('op_timeline_plugin[%s]');
  }

  public function save()
  {
    $names = array('view_photo', 'timeline_comment_reply');

    foreach ($names as $name)
    {
      if (!is_null($this->getValue($name)))
      {
        Doctrine::getTable('SnsConfig')->set('op_timeline_plugin_'.$name, $this->getValue($name));
      }
    }
  }
}
