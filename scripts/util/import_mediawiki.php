#!/usr/bin/env php
<?php

/*
 * Copyright 2012 Facebook, Inc.
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
require_once $root.'/scripts/__init_script__.php';

phutil_require_module('phutil', 'console');

$args = array_slice($argv, 1);
if (!$args) {
  return help();
}

$category = '';
$category_page = '';
$limit = 500;
$len = count($args);
for ($ii = 0; $ii < $len; $ii++) {
  switch ($args[$ii]) {
    case '--config':
      $config_file = $args[++$ii];
      $json_raw = Filesystem::readFile($config_file);
      $config = json_decode($json_raw, true);
      if (!is_array($config)) {
        throw new Exception("File '$config_file' is not valid JSON!");
      }
      $wiki_url = idx($config, 'wiki.url');
      break;
    case '--cat':
      $category = $args[++$ii];
      break;
    case '--limit':
      $limit = intval($args[++$ii]);
      break;
    case '--help':
      return help();
    default:
      return usage("Unrecognized argument '{$args[$ii]}'.");
  }
}

if (!isset($config)) {
  return usage("Please specify --config file.");
}

if (!$category) {
  return usage("Please use --cat option to specify article category.");
}

$conduit = new ConduitClient(PhabricatorEnv::getURI('/api/'));
$response = $conduit->callMethodSynchronous(
  'conduit.connect',
  array(
    'client' => 'MediaWikiImportBot',
    'clientVersion' => '1.0',
    'clientDescription' => php_uname('n') . ':mwbot',
    'user' => idx($config, 'conduit.user'),
    'certificate' => idx($config, 'conduit.cert'),
  ));
$data = file_get_contents("$wiki_url/api.php?action=query&format=php&cmlimit=$limit&list=categorymembers&cmtitle=Category:" . str_replace(' ', '_', $category));
if ($data) {
  $data = unserialize($data);
  foreach ($data['query']['categorymembers'] as $page) {
    echo $page['title'];
    $export = file_get_contents("$wiki_url/api.php?action=query&format=php&prop=revisions&rvprop=content&pageids=" . $page['pageid']);
    if ($export) {
      $export = unserialize($export);
      foreach ($export['query']['pages'] as $page_id => $page_data) {
        $safe_title = str_replace(' ', '_', $page['title']);
        $text = convertMWToPhriction($page_data['revisions'][0]['*'])
          . "\n\nImported from [[$wiki_url/index.php/$safe_title|{$page['title']}]]";

        $response = $conduit->callMethodSynchronous('phriction.info', array(
          "slug" => strtolower($page['title'])));
        if ($response['content'] == $text) {
          echo ': no changes';
        } else {
          $response = $conduit->callMethodSynchronous('phriction.edit', array(
            "slug" => strtolower($page['title']),
            "title" => $page['title'],
            "content" => $text,
            "description" => "Imported from $wiki_url ($category)"));

          if ($response['status'] == 'exists') {
            echo ': imported';
            $category_page .= "* [[{$response['slug']}|{$response['title']}]]\n";
          }
        }
      }
      echo "\n";
    }
  }
}

if ($category_page) {
  $response = $conduit->callMethodSynchronous('phriction.edit', array(
    "slug" => strtolower("Category $category"),
    "title" => "Category $category",
    "content" => $category_page,
    "description" => "Imported from $wiki_url"
  ));
}
echo "Done.\n";

function convertMWToPhriction($text) {
  // numbered lists suck, use bullets
  //$text = str_replace("\n# ", "\n* ", $text);
  $regexps = array(
    // replaces bolded or italicized headers with regular headers
    '/(=+)\'+([^\']+)\'+(=+)/' => "\$1\$2\$3",
    // make sure there is an empty line after headers
    '/(=+)\n+/' => "\$1\n\n",
    // ensure an empty line before bulleted lists, and no spaces between bullet chars
    '/\n+(?:([*#])\s*)(?:([*#])\s*)?(?:([*#])\s*)?/' => "\n\n\$1\$2\$3 ",
    // use two spaces for pre-formatted block, and make sure there is a blank line before
    '/\n+ +/' => "\n\n  ",
    // replace <pre> with ``` and ensure a blank line
    '#\n*<pre>([\w\W]+?)</pre>\n*#' => "\n\n```\$1```\n\n",
    // use [[...]] instead of [...] for external links
    '/([^[])\[(http[^ ]+) ([^\]]+)\]/' => '$1[[$2|$3]]',
    // bold
    "/'''+/" => '**',
    // new lines need not be forced
    '#<br\s*/?>#' => ''
  );
  $text = preg_replace(array_keys($regexps), $regexps, $text);
  // italics
  $text = str_replace("''", '//', $text);
  return $text;
}

function usage($message) {
  echo "Usage Error: {$message}";
  echo "\n\n";
  echo "Run 'import_mediwiki.php --help' for detailed help.\n";
  exit(1);
}

function help() {
  $help = <<<EOHELP
**SUMMARY**

    **import_mediwiki.php** --config foo.json --cat Bar [--limit 10]

    Import MediaWiki articles from a given category into Phriction.
    Convert MW syntax to Remarkup.
    Create "category" pages with links to all imported articles.

    __--config__
        JSON config file with wiki.url, conduit.user, and conduit.cert

    __--cat__
        MW category to import from

    __--limit__
        Number of articles to import (defaults to 500)

    __--help__: show this help


EOHELP;
  echo phutil_console_format($help);
  exit(1);
}
