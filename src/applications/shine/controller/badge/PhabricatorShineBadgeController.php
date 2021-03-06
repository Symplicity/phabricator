<?php

class PhabricatorShineBadgeController
  extends PhabricatorShineController {

  private $user;
  private $view;
  private $view_names = array(
    'my' => 'My Badges',
    'all' => 'All Badges',
    'top' => 'Top Scores',
  );
  private $max_score = 0;
  private $user_exceptions = array();

  public function willProcessRequest(array $data)
  {
    $this->view = idx($data, 'view');
  }

  public function processRequest()
  {
    $request = $this->getRequest();
    $this->user = $request->getUser();

    if (empty($this->view_names[$this->view])) {
      reset($this->view_names);
      $this->view = key($this->view_names);
    }

    $nav = id(new AphrontSideNavFilterView())
            ->setBaseURI(new PhutilURI('/shine/'))
            ->addLabel('Shine');

    foreach ($this->view_names as $key => $name) {
      $nav->addFilter("view/{$key}", $name);
    }
    $nav->selectFilter('view/' . $this->view);

    require_celerity_resource('phabricator-shine-css');

    switch ($this->view) {
      case 'my':
        $content = $this->renderMyTable();
        break;
      case 'all':
        $content = $this->renderAllTable();
        break;
      case 'top':
        $content = $this->renderTopScores();
        break;
      default:
        $content = null;
    }

    $panel = new AphrontPanelView();
    $panel->setHeader($this->view_names[$this->view]);
    $panel->appendChild($content);
    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Shine',
      ));
  }

  private function renderMyTable()
  {
    $badge = new ShineBadge();
    $data = $badge->loadAllWhere('userPHID = %s', $this->user->getPHID());
    $stats = queryfx_all(
      $badge->establishConnection('r'),
      'SELECT title, count(*) as cnt, sum(tally) as total FROM %T GROUP BY title',
      $badge->getTableName());
    $stats = ipull($stats, null, 'title');

    $rows = array();
    foreach ($data as $row) {
      $rows[] = array(
        $this->renderBadge($row->getTitle()),
        BadgeConfig::getDescription($row->getTitle()),
        phabricator_datetime($row->getDateEarned(), $this->user),
        $row->getTally(),
        number_format(100 * $row->getTally() / $stats[$row->getTitle()]['total'], 2),
        $stats[$row->getTitle()]['cnt'] - 1 ?: 'You are the only one!',
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
           'Badge',
           'Achievement',
           'Earned On',
           'Tally',
           '%',
           'Co-Badgers',
      ));
    $table->setColumnClasses(
      array(
           null,
           null,
           null,
           null,
           null,
           'wide',
      ));

    return $table;
  }

  private function renderAllTable()
  {
    static $avatars;

    $badge = new ShineBadge();
    $data = queryfx_all(
      $badge->establishConnection('r'),
      'SELECT title, GROUP_CONCAT(UserPHID) AS users FROM %T GROUP BY title ORDER BY COUNT(*)',
      $badge->getTableName());

    $result_markup = id(new PHUIFormLayoutView());

    foreach ($data as $row) {
      $result_markup->appendChild($this->renderBadge($row['title']));
      $result_markup->appendChild($this->renderBadgeDescription($row['title']));
      if ($row['users']) {
        $user_markup = array();
        $users = explode(',', $row['users']);
        foreach ($users as $phid) {
          if (!isset($avatars[$phid])) {
            $avatars[$phid] = '';
            $object = id(new PhabricatorUser())->loadOneWhere('phid = %s', $phid);
            if ($object) {
              $avatars[$phid] = $this->renderUserAvatar($object);
            }
          }
          if ($avatars[$phid]) {
            $user_markup[] = $avatars[$phid];
          }
        }
      } else {
        $user_markup = 'This badge still evades conquest.';
      }
      $result_markup->appendChild(phutil_tag(
        'div',
        array(
             'class' => 'phabricator-shine-facepile',
        ),
        $user_markup));
    }
    $result_markup->appendChild(phutil_tag(
      'div',
      array(
           'class' => 'phabricator-shine-facepile',
      ),
      ' '));

    return $result_markup;
  }

  private function renderTopScores()
  {
    static $avatars;

    $result_markup = id(new PHUIFormLayoutView());

    if (PhabricatorEnv::envConfigExists('shine.user_exceptions')) {
      $this->user_exceptions = PhabricatorEnv::getEnvConfig('shine.user_exceptions');
    }

    $this->renderTopScoreList(
      $result_markup,
      $this->getBadgeData(array(strtotime('7 days ago'), time())),
      "7 Days");

    $this->renderTopScoreList(
      $result_markup,
      $this->getBadgeData(array(strtotime('30 days ago'), time())),
      "30 Days");

    $this->renderTopScoreList(
      $result_markup,
      $this->getBadgeData(array(strtotime('365 days ago'), time())),
      "365 Days");

    return $result_markup;
  }

  private function renderTopScoreList($result_markup, $data, $title) {
    $content = array(phutil_tag(
      'div',
      array(
        'class' => 'phabricator-shine-badge',
      ),
      $title)
    );
    $this->max_score = 0;
    foreach ($data as $pos => $row) {
      if (!$this->max_score) {
        $this->max_score = $row['score'];
      }
      $object = id(new PhabricatorUser())->loadOneWhere('phid = %s', $row['user']);
      if ($object) {
        $content[] = phutil_tag(
          'div',
          array(
            'class' => 'phabricator-shine-facepile',
          ),
          array(
            $this->renderPosition($pos),
            $this->renderUserAvatar($object),
            $this->renderScore($row['score'], $row['details']))
        );
      }
    }
    $content[] = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-shine-facepile'
      ),
      ' '
    );
    $result_markup->appendChild(phutil_tag(
        'div',
        array(
          'style' => 'width:33%; float:left; margin:20px 0',
        ),
        $content
    ));
  }

  private function getBadgeData($date_range) {
    $badge_data = array();
    foreach (BadgeConfig::$data as $title => $meta) {
      $class = $meta['class'];
      $object = new $class();
      $phid_field = isset($meta['phid_field']) ? $meta['phid_field'] : 'authorPHID';
      $date_field = isset($meta['date_field']) ? $meta['date_field'] : 'dateCreated';
      $where = BadgeConfig::getWhere($title);
      if ($date_range) {
        if ($where) {
          $where .= ' AND ';
        } else {
          $where = 'WHERE ';
        }
        $where .= $date_field . ' BETWEEN ' . join(' AND ', $date_range);
      }
      $weight = isset($meta['weight']) ? $meta['weight'] : 1;
      $data = queryfx_all(
        $object->establishConnection('r'),
        'SELECT ' . $phid_field . ' as user, (%d * count(*)) as score FROM %T ' . $where . ' GROUP BY user',
        $weight,
        $object->getTableName());
      foreach ($data as $row) {
        if (!isset($this->user_exceptions[$title]) || !in_array($row['user'], $this->user_exceptions[$title])) {
          if (isset($badge_data[$row['user']])) {
            $badge_data[$row['user']]['score'] += $row['score'];
            $badge_data[$row['user']]['details'] .= ',' . $title . ': ' . $row['score'];
          } else {
            $row['details'] = $title . ': ' . $row['score'];
            $badge_data[$row['user']] = $row;
          }
        }
      }
    }
    usort($badge_data, function($a, $b) {
      if ($a['score'] == $b['score']) {
        return 0;
      }
      return ($a['score'] < $b['score']) ? 1 : -1;
    });
    return array_slice($badge_data, 0, 20);
  }

  private function renderBadge($title)
  {
    return phutil_tag(
      'div',
      array(
           'class' => 'phabricator-shine-badge',
      ),
      $title);
  }

  private function renderBadgeDescription($title)
  {
    return phutil_tag(
      'div',
      array(
           'class' => 'phabricator-shine-desc',
      ),
      BadgeConfig::getDescription($title));
  }

  private function renderUserAvatar($object)
  {
    $file = id(new PhabricatorFile())->loadOneWhere(
      'phid = %s',
      $object->getProfileImagePHID());
    if ($file) {
      $profile_image = $file->getBestURI();
    } else {
      $profile_image = '/res/1c5f2550/rsrc/image/avatar.png';
    }

    return phutil_tag(
      'a',
      array(
        'href' => '/p/' . $object->getUserName() . '/',
        'title' => $object->getRealName()
      ),
		phutil_tag(
        'img',
        array(
          'src' => $profile_image,
        )
      ));
  }

  private function renderPosition($pos)
  {
    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-shine-position',
      ),
      ($pos + 1) . '. ');
  }

  private function renderScore($score, $details)
  {
    $size = 100 + ceil(3 * ceil(100 * $score / $this->max_score));
    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-shine-score',
        'style' => 'font-size:' . $size . '%',
        'title' => str_replace(',', ' + ', $details)
      ),
      number_format($score, 0));
  }

}