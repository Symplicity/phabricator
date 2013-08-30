#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';
require_once $root.'/scripts/__init_env__.php';

$users = id(new PhabricatorUser())->loadAll();
echo "Exporting " . count($users) . " user photos\n";

$photo_path = isset($argv[1]) ? $argv[1] : '';
if (!$photo_path || !is_writable($photo_path)) {
  die("Please provide a writable target folder for user photos.\n");
}

foreach ($users as $user) {
  $username = $user->getUserName();
  echo "$username: ";
  $phid = $user->getPHID();
  $file_name = 'no photo';
  $profile = id(new PhabricatorUserProfile())->loadOneWhere('userPHID = %s', $phid);
  if ($profile) {
    $src_phid = $profile->getProfileImagePHID();
    if ($src_phid) {
      $picture = id(new PhabricatorFile())->loadOneWhere('phid = %s', $src_phid);
      if ($picture) {
        $file_name = "$photo_path/$username" . substr($picture->getName(), strrpos($picture->getName(), '.'));
        if (!file_exists($file_name) || (filemtime($file_name) < $picture->getDateModified())) {
          $blob_id = (int) $picture->getStorageHandle();
          $blob = id(new PhabricatorFileStorageBlob())->loadOneWhere('id = %d', $blob_id);
          file_put_contents($file_name, $blob->getData());
          echo " $file_name";
        } else {
          $file_name = 'not modified';
        }
      }
    }
  }

  echo "$file_name\n";
}

echo "Done.\n";
