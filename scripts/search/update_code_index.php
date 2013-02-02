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
require_once $root . '/scripts/__init_script__.php';

if (empty($argv[1])) {
  echo "usage: checkout_repos.php <checkout base dir>\n";
  die(1);
}

$repos = id(new PhabricatorRepository())->loadAllWhere(
  'versionControlSystem = %s',
  'svn');

$repo_base = $argv[1];
if (!is_dir($repo_base)) {
  echo "$repo_base does not exist\n";
  die(1);
}
if (substr($repo_base, -1) != DIRECTORY_SEPARATOR) {
  $repo_base .= DIRECTORY_SEPARATOR;
}

$users = id(new PhabricatorUser())->loadAll();
$user_phids = mpull($users, 'getPHID', 'getUserName');

$commit_object = new PhabricatorRepositoryCommit();
$commit_conn = $commit_object->establishConnection('r');

$search_type = PhabricatorPHIDConstants::PHID_TYPE_SOURCE;
$search_object = newv('PhabricatorSearchDocument', array());

$engine = PhabricatorSearchEngineSelector::newSelector()->newEngine();

foreach ($repos as $repo) {
  $details = $repo->getDetails();
  $subpath = $details['svn-subpath'];
  $svn_uri = $details['remote-uri'] . $subpath;
  $repo_code = $repo->getCallsign();
  $name = $repo->getName();
  $repo_path = $repo_base . $repo_code . DIRECTORY_SEPARATOR;
  $svn_out = $files = array();
  $force_update = false;
  if (file_exists($repo_path)) {
    chdir($repo_path);
    echo "Updating $name\n";
    exec("svn update --accept theirs-full", $svn_out);
    foreach ($svn_out as $line) {
      //U    SympleObjects.class
      list($action, $path) = preg_split('/\s+/', $line);
      if ($action === 'D') {
        $phid = "PHID-$search_type-" . md5($repo_code . ':' . $path);
        $indexed_file = $search_object->loadOneWhere('phid = %s', $phid);
        if ($indexed_file) {
          $indexed_file->delete();
        }
      } elseif (file_exists($repo_path . $path)) {
        $files[] = new SplFileInfo($path);
      }
    }
  } else {
    chdir($repo_base);
    echo "Checking out $name as $repo_code\n";
    exec("svn checkout -q $svn_uri $repo_code");
    $dir_iterator = new RecursiveDirectoryIterator($repo_path);
    $files = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);
    $force_update = true;
  }

  foreach ($files as $file) {
    if ($file->isFile()) {
      $mtime = $file->getMTime();
      $full_path = $file->getPathname();
      $ext = substr($full_path, strrpos($full_path, '.') + 1);
      $extensions = PhabricatorEnv::getEnvConfig('search.source_extensions');
      if ($extensions && !in_array($ext, $extensions)) {
        continue;
      }

      $path = str_replace($repo_path, '', $full_path);
      $skip_regexp = PhabricatorEnv::getEnvConfig('search.source_skip_regexp');
      if ($skip_regexp && preg_match($skip_regexp, DIRECTORY_SEPARATOR . $path)) {
        continue;
      }

      $phid = "PHID-$search_type-" . md5($repo_code . ':' . $path);
      $indexed_file = $force_update ? false : $search_object->loadOneWhere('phid = %s', $phid);

      if (!$indexed_file || ($mtime != $indexed_file->getDocumentModified())) {
        $ctime = $file->getCTime();
        $content = preg_replace('/(\s)\s+/', '$1', file_get_contents($full_path));
        echo "$path\n";

        $doc = new PhabricatorSearchAbstractDocument();
        $doc->setPHID($phid);
        $doc->setDocumentType($search_type);
        $doc->setDocumentCreated($ctime);
        $doc->setDocumentModified($mtime);
        $doc->setDocumentTitle($path);

        $doc->addField(
          PhabricatorSearchField::FIELD_BODY,
          "$path\n$content");

        $doc->addRelationship(
          PhabricatorSearchRelationship::RELATIONSHIP_REPOSITORY,
          $repo->getPHID(),
          PhabricatorPHIDConstants::PHID_TYPE_REPO,
          $mtime);

        $path_id = queryfx_one(
          $commit_conn,
          'SELECT id FROM repository_path INNER JOIN repository_pathchange'
            .' ON id=pathID and repositoryID=%d AND path=%s LIMIT 1',
          $repo->getID(),
          "/$subpath$path");
        if ($path_id) {
          $author = queryfx_one(
            $commit_conn,
            'SELECT authorName FROM repository_commitdata rcd'
              .' INNER JOIN repository_pathchange rpc ON rcd.commitID=rpc.commitID and pathID=%d'
              .' ORDER BY rcd.id DESC LIMIT 1',
            $path_id['id']);
          if ($author) {
            $author = $author['authorName'];
            if (isset($user_phids[$author])) {
              $doc->addRelationship(
                PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
                $user_phids[$author],
                PhabricatorPHIDConstants::PHID_TYPE_USER,
                $mtime);
            }
          }
        }
        $engine->reindexAbstractDocument($doc);
      }
    }
  }
}

