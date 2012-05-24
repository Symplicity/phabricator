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

class PhabricatorShineBadgeController
  extends PhabricatorShineController {

  private $user;
  private $view;
  private $view_names = array(
    'my' => 'My Badges',
    'all' => 'All Badges',
    'top' => 'Top Scores',
  );
  private $total_score = 0;

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

    $nav = new AphrontSideNavView();
    foreach ($this->view_names as $key => $name) {
      $nav->addNavItem(
        phutil_render_tag(
          'a',
          array(
               'href' => '/shine/view/' . $key . '/',
               'class' => ($this->view == $key)
                       ? 'aphront-side-nav-selected'
                       : null,
          ),
          phutil_escape_html($name)));
    }

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
      $this->total_score = $stats[$row->getTitle()]['total'];
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

    $result_markup = id(new AphrontFormLayoutView());

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
        $user_markup = implode('', $user_markup);
      } else {
        $user_markup = 'This badge still evades conquest.';
      }
      $result_markup->appendChild(phutil_render_tag(
        'div',
        array(
             'class' => 'phabricator-shine-facepile',
        ),
        $user_markup));
    }
    $result_markup->appendChild(phutil_render_tag(
      'div',
      array(
           'class' => 'phabricator-shine-facepile',
      ),
      '&nbsp;'));

    return $result_markup;
  }

  private function renderTopScores()
  {
    static $avatars;

    $badge = new ShineBadge();
    $data = queryfx_all(
      $badge->establishConnection('r'),
      'SELECT UserPHID AS user, SUM(tally) AS score FROM %T GROUP BY user ORDER BY score DESC',
      $badge->getTableName());

    $this->total_score = array_reduce($data, function(&$total, $user)
    {
      $total += $user['score'];
      return $total;
    }, 0);

    $result_markup = id(new AphrontFormLayoutView());
    foreach ($data as $pos => $row) {
      $object = id(new PhabricatorUser())->loadOneWhere('phid = %s', $row['user']);
      if ($object) {
        $result_markup->appendChild(phutil_render_tag(
          'div',
          array(
            'class' => 'phabricator-shine-facepile',
          ),
          $this->renderPosition($pos) . $this->renderUserAvatar($object) . $this->renderScore($row['score'])));
      }
    }
    $result_markup->appendChild(phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-shine-facepile',
      ),
      '&nbsp;'));

    return $result_markup;
  }

  private function renderBadge($title)
  {
    return phutil_render_tag(
      'div',
      array(
           'class' => 'phabricator-shine-badge',
      ),
      $title);
  }

  private function renderBadgeDescription($title)
  {
    return phutil_render_tag(
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

    return phutil_render_tag(
      'a',
      array(
        'href' => '/p/' . $object->getUserName() . '/',
        'title' => $object->getRealName()
      ),
      phutil_render_tag(
        'img',
        array(
          'src' => $profile_image,
        )
      ));
  }

  private function renderPosition($pos)
  {
    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-shine-position',
      ),
      ($pos + 1) . '. ');
  }

  private function renderScore($score)
  {
    $score = number_format(100 * $score / $this->total_score, 2);
    $size = 100 + ceil(3 * $score);
    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-shine-score',
        'style' => 'font-size:' . $size . '%'
      ),
      $score . '%');
  }

}