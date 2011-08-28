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
require_once $root.'/scripts/__init_env__.php';

$users = id(new PhabricatorUser())->loadAll();
echo "Badging " . count($users) . " users\n";

$badge = new ShineBadge();
$data = queryfx_all(
  $badge->establishConnection('r'),
  'SELECT userPHID, GROUP_CONCAT(title) AS badges FROM %T GROUP BY userPHID',
  $badge->getTableName());
$all_old_badges = ipull($data, 'badges', 'userPHID');

$new_badges = array();

foreach (BadgeConfig::$data as $title => $meta) {
  $class = $meta['class'];
  $object = new $class();
  $where = BadgeConfig::getWhere($title);
  $phid_field = isset($meta['phid_field']) ? $meta['phid_field'] : 'authorPHID';
  $date_field = isset($meta['date_field']) ? $meta['date_field'] : 'dateCreated';
  $data = queryfx_all(
    $object->establishConnection('r'),
    'SELECT ' . $phid_field . ', min(' . $date_field . ') as earned FROM %T ' . $where . ' GROUP BY ' . $phid_field,
    $object->getTableName());
  $new_badges[$title] = ipull($data, 'earned', $phid_field);
}

foreach ($users as $user) {
  echo $user->getUserName();
  $phid = $user->getPHID();

  $old_badges = array();
  if (isset($all_old_badges[$phid])) {
    $old_badges = array_flip(explode(',', $all_old_badges[$phid]));
  }

  foreach($new_badges as $title => $users) {
    if (isset($users[$phid]) && !isset($old_badges[$title])) {
      id(new ShineBadge())
              ->setTitle($title)
              ->setUserPHID($phid)
              ->setDateEarned($users[$phid])
              ->save();
      echo " $title!";
    }
  }
  echo "\n";
}

echo "Done.\n";
