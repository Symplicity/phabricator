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
  );

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
      'SELECT title, count(*) as cnt FROM %T GROUP BY title',
      $badge->getTableName());
    $stats = ipull($stats, 'cnt', 'title');

    $rows = array();
    foreach ($data as $row) {
      $rows[] = array(
        $this->renderBadge($row->getTitle()),
        BadgeConfig::getDescription($row->getTitle()),
        phabricator_datetime($row->getDateEarned(), $this->user),
        $stats[$row->getTitle()] - 1 ?: 'You are the only one!',
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
           'Badge',
           'Achievement',
           'Earned On',
           'Co-Badgers',
      ));
    $table->setColumnClasses(
      array(
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
            $object = id(new PhabricatorUser())->loadOneWhere('phid = %s', $phid);
            if (!$object) {
              continue;
            }

            $file = id(new PhabricatorFile())->loadOneWhere(
              'phid = %s',
              $object->getProfileImagePHID());
            $profile_image = $file->getBestURI();

            $avatars[$phid] = phutil_render_tag(
              'a',
              array(
                   'href' => '/p/' . $object->getUserName() . '/',
              ),
              phutil_render_tag(
                'img',
                array(
                     'src' => $profile_image,
                )));
          }
          $user_markup[] = $avatars[$phid];
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

}
