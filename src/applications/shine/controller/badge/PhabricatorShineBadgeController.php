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

    $badge = new ShineBadge();
    switch ($this->view) {
      case 'my':
        $data = $badge->loadAllWhere('userPHID = %s', $this->user->getPHID());
        $table = $this->renderMyTable($data);
        break;
      case 'all':
        $data = queryfx_all(
          $badge->establishConnection('r'),
          'SELECT title, GROUP_CONCAT(UserPHID) AS users FROM %T GROUP BY title ORDER BY COUNT(*)',
          $badge->getTableName());
        $table = $this->renderAllTable($data);
        break;
      default:
        $table = null;
    }

    $panel = new AphrontPanelView();
    $panel->setHeader($this->view_names[$this->view]);
    $panel->appendChild($table);
    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Shine',
      ));
  }

  private function renderMyTable(array $data)
  {
    $rows = array();
    foreach ($data as $row) {
      $rows[] = array(
        $this->renderBadge($row->getTitle()),
        BadgeConfig::getDescription($row->getTitle()),
        phabricator_datetime($row->getDateEarned(), $this->user),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
           'Badge',
           'Achievement',
           'Earned',
      ));
    $table->setColumnClasses(
      array(
           null,
           null,
           'wide',
      ));

    return $table;
  }

  private function renderBadge($title) {
    return phutil_render_tag(
      'div',
      array(
           'class' => 'phabricator-shine-badge',
      ),
      $title);
  }

  private function renderAllTable(array $data)
  {
    static $avatars;

    $rows = array();
    foreach ($data as $row) {
      if ($row['users']) {
        $user_markup = array();
        $users = explode(',', $row['users']);
        foreach ($users as $phid) {
          if (!isset($avatars[$phid])) {
            $object = id(new PhabricatorUser())->loadOneWhere('phid = %s', $phid);
            if (!$object) {
              continue;
            }

            $profile_image = PhabricatorFileURI::getViewURIForPHID(
              $object->getProfileImagePHID());

            $avatars[$phid] = phutil_render_tag(
              'a',
              array(
                   'href' => '/p/' . $object->getUserName() . '/',
                   'class' => 'phabricator-shine-facepile',
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
        $user_markup = 'This option has failed to appeal to anyone.';
      }
      $rows[] = array(
        $this->renderBadge($row['title']),
        $user_markup,
      );

    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
           'Badge',
           'Badgers',
      ));
    $table->setColumnClasses(
      array(
           null,
           'wide wrap',
      ));

    return $table;
  }

}
