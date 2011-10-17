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
require_once $root . '/scripts/__init_env__.php';

if (empty($argv[1]) || empty($argv[2])) {
  echo "usage: index_repository.php <repository code> <checkout path> [--reindex]\n";
  die(1);
}

$reindex = isset($argv[3]) && $argv[3] == '--reindex';

// repo codes are randomly chosen when adding them to phabricator
// see here: https://review.symplicity.com/repository/
$repo_code = $argv[1];
$repo = id(new PhabricatorRepository())->loadOneWhere(
  'callsign = %s',
  $repo_code);
if (!$repo) {
  throw new Exception("Unknown repository $repo_code!");
}

$repo_path = $argv[2];
if (substr($repo_path, -1) != DIRECTORY_SEPARATOR) {
  $repo_path .= DIRECTORY_SEPARATOR;
}

$details = $repo->getDetails();
if ($details['svn-subpath']) {
  $subpath = $details['svn-subpath'];
}

$users = id(new PhabricatorUser())->loadAll();
$user_phids = mpull($users, 'getPHID', 'getUserName');

$commit_object = new PhabricatorRepositoryCommit();
$commit_conn = $commit_object->establishConnection('r');

$search_type = PhabricatorPHIDConstants::PHID_TYPE_SOURCE;
PhutilSymbolLoader::loadClass('PhabricatorSearchDocument');
$search_object = newv('PhabricatorSearchDocument', array());

if ($reindex) {
  $last_index = 0;
} else {
  $last_index = queryfx_one(
    $search_object->establishConnection('r'),
          'SELECT max(relatedTime) FROM search_documentrelationship'
                  . ' WHERE relation=%s AND relatedPHID=%s',
    PhabricatorSearchRelationship::RELATIONSHIP_REPOSITORY,
    $repo->getPHID());
  if ($last_index) {
    $last_index = array_pop($last_index);
  }
}

$engine = PhabricatorSearchEngineSelector::newSelector()->newEngine();

echo ($reindex ? 'Reindexing ' : 'Indexing ').$repo->getName()." files:\n";

$dir_iterator = new RecursiveDirectoryIterator($repo_path);
$files = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);
foreach ($files as $file) {
  if ($file->isFile()) {
    $mtime = $file->getMTime();
    if ($last_index >= $file->getMTime()) {
      continue;
    }
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
    $indexed_file = $search_object->loadOneWhere('phid = %s', $phid);

    if ($reindex || !$indexed_file || ($mtime != $indexed_file->getDocumentModified())) {
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