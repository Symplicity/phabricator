<?php

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
      'weight' => 3,
    ),
    'Accepted' => array(
      'class' => 'DifferentialRevision',
      'desc' => 'Received code review approval',
      'href' => '/differential/',
      'weight' => 1,
    ),
    'Prover' => array(
      'class' => 'DifferentialComment',
      'where' => "action = 'accept'",
      'desc' => 'Approved a code review',
      'href' => '/differential/',
      'weight' => 2,
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
      'weight' => 10,
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
      'weight' => 2,
    ),
    'Closer' => array(
      'class' => 'ManiphestTask',
      'phid_field' => 'ownerPHID',
      'where' => "status = 1",
      'desc' => 'Completed a task',
      'href' => '/maniphest/',
      'weight' => 5,
    ),
    'Commithor' => array(
      'class' => 'PhabricatorRepositoryCommit',
      'date_field' => 'epoch',
      'desc' => 'Every commit counts',
      'href' => '/diffusion/',
    ),
    'Instigator' => array(
      'class' => 'PhabricatorAuditComment',
      'phid_field' => 'ActorPHID',
      'where' => "action = 'concern'",
      'desc' => 'Raised a concern with a commit',
      'href' => '/audit/',
    ),
    'Izerizer' => array(
      'class' => 'PhabricatorRepositoryCommit',
      'date_field' => 'epoch',
      'desc' => 'Mentioned manager issue number in a commit',
      'href' => '/diffusion/',
    ),
    'Testhor' => array(
      'class' => 'PhabricatorRepositoryCommit',
      'date_field' => 'epoch',
      'desc' => 'Contributed to unit tests',
      'href' => '/diffusion/',
      'weight' => 2,
    ),
  );

  public static function getDescription($title)
  {
    return phutil_tag(
      'a',
      array(
        'href' => self::$data[$title]['href'],
      ),
      self::$data[$title]['desc']
    );
   }

  public static function getWhere($title) {
    $where = '';
    if (isset(self::$data[$title]['where'])) {
      $where = 'WHERE ' . self::$data[$title]['where'];
    } elseif ($title == 'Accepted') {
      $where = sprintf('WHERE status IN (%d, %d)',
        ArcanistDifferentialRevisionStatus::ACCEPTED,
        ArcanistDifferentialRevisionStatus::CLOSED);
    } elseif ($title == 'Profiller') {
      $where = "WHERE title != '' AND blurb != '' AND profileImagePHID IS NOT NULL";
    } elseif ($title == 'Photogenic') {
      $where = "WHERE profileImagePHID IS NOT NULL";
    } elseif ($title == 'Izerizer') {
      $where = "rc INNER JOIN repository_commitdata rcd ON rc.id=rcd.commitID"
        . " WHERE commitMessage rlike '[1-9][0-9]{5,}'";
    } elseif ($title == 'Testhor') {
      $where = "rc INNER JOIN repository_filesystem rfs"
        . " ON commitIdentifier=svnCommit AND rc.repositoryID=rfs.repositoryID"
        . " INNER JOIN repository_path rp ON rfs.pathID=rp.id AND fileType=7"
        . " WHERE rp.path like '%%/tests/%%.php'";
    }
    return $where;
  }
}
