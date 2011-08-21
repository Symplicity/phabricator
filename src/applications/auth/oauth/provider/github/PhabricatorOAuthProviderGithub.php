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

class PhabricatorOAuthProviderGithub extends PhabricatorOAuthProvider {

  private $userData;

  public function getProviderKey() {
    return self::PROVIDER_GITHUB;
  }

  public function getProviderName() {
    return PhabricatorEnv::getEnvConfig('github.provider-name');
  }

  public function isProviderEnabled() {
    return PhabricatorEnv::getEnvConfig('github.auth-enabled');
  }

  public function isProviderLinkPermanent() {
    return PhabricatorEnv::getEnvConfig('github.auth-permanent');
  }

  public function isProviderRegistrationEnabled() {
    return PhabricatorEnv::getEnvConfig('github.registration-enabled');
  }

  public function getRedirectURI() {
    return PhabricatorEnv::getURI('/oauth/github/login/');
  }

  public function getClientID() {
    return PhabricatorEnv::getEnvConfig('github.application-id');
  }

  public function getClientSecret() {
    return PhabricatorEnv::getEnvConfig('github.application-secret');
  }

  public function getAuthURI() {
    return PhabricatorEnv::getEnvConfig('github.auth-uri');
  }

  public function getTokenURI() {
    return PhabricatorEnv::getEnvConfig('github.token-uri');
  }

  public function getUserInfoURI() {
    return PhabricatorEnv::getEnvConfig('github.user-info-uri');
  }

  public function getMinimumScope() {
    return PhabricatorEnv::getEnvConfig('github.min-scope');
  }

  public function setUserData($data) {
    $this->userData = $data['user'];
    return $this;
  }

  public function retrieveUserID() {
    return $this->userData['id'];
  }

  public function retrieveUserEmail() {
    return idx($this->userData, 'email');
  }

  public function retrieveUserAccountName() {
    return $this->userData['login'];
  }

  public function retrieveUserProfileImage() {
    $id = $this->userData['gravatar_id'];
    if ($id) {
      $uri = 'http://www.gravatar.com/avatar/'.$id.'?s=50';
      return @file_get_contents($uri);
    }
    return null;
  }

  public function retrieveUserAccountURI() {
    $username = $this->retrieveUserAccountName();
    if ($username) {
      return PhabricatorEnv::getEnvConfig('github.user-account-uri').$username;
    }
    return null;
  }

  public function retrieveUserRealName() {
    return idx($this->userData, 'name');
  }

}
