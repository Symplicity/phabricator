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

final class BadgeConfig {

  static $data = array(
    'Writer' => array(
      'class' => 'PhrictionContent',
      'desc' => 'Edited a wiki article',
      'href' => '/w/',
    ),
    'Questor' => array(
      'class' => 'DifferentialRevision',
      'desc' => 'Requested a code review',
      'href' => '/differential/',
      'weight' => 5,
    ),
    'Accepted' => array(
      'class' => 'DifferentialRevision',
      'desc' => 'Received code review approval',
      'href' => '/differential/',
      'weight' => 5,
    ),
    'Prover' => array(
      'class' => 'DifferentialComment',
      'where' => "action = 'accept'",
      'desc' => 'Approved a code review',
      'href' => '/differential/',
      'weight' => 5,
    ),
    'Pollster' => array(
      'class' => 'PhabricatorSlowvotePoll',
      'desc' => 'Created a poll',
      'href' => '/vote/create/',
    ),
    'Voter' => array(
      'class' => 'PhabricatorSlowvoteChoice',
      'desc' => 'Voted in a poll',
      'href' => '/vote/',
    ),
    'Profiller' => array(
      'class' => 'PhabricatorUserProfile',
      'phid_field' => 'userPHID',
      'date_field' => 'dateModified',
      'desc' => 'Completed the user profile',
      'href' => '/settings/page/profile/',
    ),
    'Photogenic' => array(
      'class' => 'PhabricatorUserProfile',
      'phid_field' => 'userPHID',
      'date_field' => 'dateModified',
      'desc' => 'Uploaded a profile photo',
      'href' => '/settings/page/profile/',
      'weight' => 100,
    ),
    'Pastafarian' => array(
      'class' => 'PhabricatorPaste',
      'desc' => 'Shared code with Paste',
      'href' => '/paste/',
    ),
    'Taskerizer' => array(
      'class' => 'ManiphestTask',
      'desc' => 'Created a task',
      'href' => '/maniphest/',
      'weight' => 5,
    ),
    'Closer' => array(
      'class' => 'ManiphestTask',
      'phid_field' => 'ownerPHID',
      'where' => "status = 1",
      'desc' => 'Completed a task',
      'href' => '/maniphest/',
      'weight' => 10,
    ),
    'Commithor' => array(
      'class' => 'PhabricatorRepositoryCommit',
      'date_field' => 'epoch',
      'desc' => 'Every commit counts',
      'href' => '/diffusion/',
    ),
  );

  public static function getDescription($title)
  {
    return phutil_render_tag(
      'a',
      array(
        'href' => self::$data[$title]['href'],
      ),
      phutil_escape_html(self::$data[$title]['desc'])
    );
   }

  public static function getWhere($title) {
    if (isset(self::$data[$title]['where'])) {
      $where = self::$data[$title]['where'];
    } elseif ($title == 'Accepted') {
      $where = sprintf('status IN (%d, %d)',
        ArcanistDifferentialRevisionStatus::ACCEPTED,
        ArcanistDifferentialRevisionStatus::CLOSED);
    } elseif ($title == 'Profiller') {
      $where = sprintf("title != '' AND blurb != '' AND profileImagePHID != '%s'",
        PhabricatorEnv::getEnvConfig('user.default-profile-image-phid'));
    } elseif ($title == 'Photogenic') {
      $where = sprintf("profileImagePHID != '%s'",
        PhabricatorEnv::getEnvConfig('user.default-profile-image-phid'));
    }
    if (empty($where)) {
      return '';
    } else {
      return 'WHERE ' . $where;
    }
  }
}
