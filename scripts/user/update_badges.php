#!/usr/bin/env php
<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$users = id(new PhabricatorUser())->loadAllWhere('isDisabled = 0 and isSystemAgent = 0');
echo "Badging " . count($users) . " users\n";

$new_badges = array();
foreach (BadgeConfig::$data as $title => $meta) {
  $class = $meta['class'];
  $object = new $class();
  $where = BadgeConfig::getWhere($title);
  $phid_field = isset($meta['phid_field']) ? $meta['phid_field'] : 'authorPHID';
  $date_field = isset($meta['date_field']) ? $meta['date_field'] : 'dateCreated';
  $weight = isset($meta['weight']) ? $meta['weight'] : 1;
  $data = queryfx_all(
    $object->establishConnection('r'),
    'SELECT ' . $phid_field . ', min(' . $date_field . ') as earned, (%d * count(*)) as tally FROM %T ' . $where . ' GROUP BY ' . $phid_field,
    $weight,
    $object->getTableName());
  $new_badges[$title] = ipull($data, null, $phid_field);
}

$bodies = array();
foreach ($users as $user) {
  $phid = $user->getPHID();
  $old_badges = id(new ShineBadge())->loadAllWhere('userPHID = %s', $phid);
  $old_badges = mpull($old_badges, null, 'getTitle');
  $badge_titles = '';
  foreach($new_badges as $title => $users) {
    if (isset($users[$phid])) {
      if (isset($old_badges[$title])) {
        if ($users[$phid]['tally'] != $old_badges[$title]->getTally()) {
          $old_badges[$title]->setTally($users[$phid]['tally'])->save();
        }
      } else {
        id(new ShineBadge())
                ->setTitle($title)
                ->setUserPHID($phid)
                ->setDateEarned($users[$phid]['earned'])
                ->setTally($users[$phid]['tally'])
                ->save();
        $description = strip_tags(BadgeConfig::getDescription($title));
        $suffix = strlen($title) < 8 ? "\t" : '';
        $badge_titles .= "\t$title$suffix\t($description)\n";
      }
    }
  }
  if ($badge_titles) {
    try {
      $email = $user->loadPrimaryEmailAddress();
    } catch (Exception $e) {
      continue;
    }
    $bodies[$email] = "Hi " . $user->getUserName() . "!\n\nYou just unlocked this:\n\n$badge_titles\n";
  }
}

if (count($bodies)) {
  $root = phutil_get_library_root('phabricator');
  $root = dirname($root);
  require_once $root . '/externals/phpmailer/class.phpmailer-lite.php';
  foreach ($bodies as $email => $body) {
    $body .= "\nSee your badges at " . PhabricatorEnv::getEnvConfig('phabricator.base-uri') . "shine/view/my/\n";
    $body .= "\nSee all badges at " . PhabricatorEnv::getEnvConfig('phabricator.base-uri') . "shine/view/all/\n";
    $mailer = newv('PHPMailerLite', array());
    $mailer->CharSet = 'utf-8';
    $mailer->Subject = "You Has Badges!";
    $mailer->SetFrom(PhabricatorEnv::getEnvConfig('shine.notifications_from'), 'Phabricator');
    $mailer->AddAddress($email);
    $mailer->Body = $body;
    $mailer->send();
  }
}
echo "Done.\n";
