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

/**
 * @group console
 */
class DarkConsoleEventPluginAPI extends PhabricatorEventListener {

  private static $events = array();
  private static $discardMode = false;

  public static function enableDiscardMode() {
    self::$discardMode = true;
  }

  public static function getEvents() {
    return self::$events;
  }

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_ALL);
  }

  public function handleEvent(PhabricatorEvent $event) {
    if (self::$discardMode) {
      return;
    }
    self::$events[] = $event;
  }

}

