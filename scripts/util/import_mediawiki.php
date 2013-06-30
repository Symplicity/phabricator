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

$args = array_slice($argv, 1);
if (!$args) {
  help();
}

$action = 'replace';
$all_actions = array('insert', 'replace', 'delete');
$title = '';
$category = '';
$category_map = array();
$wiki_url = '';
$wiki_file = '';
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
      $category_map = array_change_key_case(idx($config, 'category.map'));
      break;
    case '--title':
      $title = $args[++$ii];
      break;
    case '--cat':
      $category = $args[++$ii];
      break;
    case '--limit':
      $limit = intval($args[++$ii]);
      break;
    case '--file':
      $wiki_file = $args[++$ii];
      break;
    case '--action':
      $action = $args[++$ii];
      if (!in_array($action, $all_actions)) {
        usage("Supported actions are: " . join(', ', $all_actions));
      }
      break;
    case '--help':
      help();
      break;
    default:
      usage("Unrecognized argument '{$args[$ii]}'.");
  }
}

if (!isset($config)) {
  usage("Please specify --config for the wiki of --file with wiki markup.");
}

if (!$category && !$title) {
  usage("Please use --cat or --title options to specify what to import.");
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

if ($category) {
  if ($category == 'all') {
    $loop_categories = array_keys(idx($config, 'category.map'));
  } else {
    $loop_categories = array($category);
  }
} else {
  $loop_categories = array(''); // single loop in title case
}
$data = null;
foreach ($loop_categories as $category) {
  $category_page = '';
  if ($category) {
    echo "\nImporting $category articles\n";
    $data = getMWCategoryData($wiki_url, $category, $limit);
  }
  if ($title) {
    if ($wiki_file) {
      $data = array(array(
        'title' => $title,
        'content' => file_get_contents($wiki_file)
      ));
    } else {
      $data = getMWTitleData($wiki_url, $title);
    }
  }
  if ($data) {
    foreach ($data as $page) {
      echo $page['title'] . ': ';
      if (isset($page['pageid'])) {
        $page_data = getMWPageData($wiki_url, $page['pageid']);
      } elseif (!empty($page['content'])) {
        $page_data = $page;
      } else {
        continue;
      }
      if ($page_data) {
        $safe_title = str_replace(' ', '_', $page['title']);
        $text = convertMWToPhriction($wiki_url, $page_data['content']);
        if (!$wiki_file) {
          $text .= "\n\nImported from [[$wiki_url/index.php/$safe_title|{$page['title']}]]";
        }
        $cat_prefix = getPhrictionPrefix($text, $category_map);
        if ($cat_prefix) {
          removeUncategorizedArticle($conduit, $safe_title, $text);
          $safe_title = $cat_prefix . $safe_title;
        }
        try {
          $existing = $conduit->callMethodSynchronous('phriction.info', array(
            "slug" => strtolower($safe_title)));
        } catch (Exception $e) {
          $existing = null;
        }
        if ($action == 'delete') {
          $text = '';
        }
        if ($existing && $existing['content'] == $text) {
          echo 'no changes';
          $category_page .= "* [[{$existing['slug']}|{$existing['title']}]]\n";
        } else {
          $response = $conduit->callMethodSynchronous('phriction.edit', array(
            "slug" => $safe_title,
            "title" => $page['title'],
            "content" => $text,
            "description" => "Imported from " . ($wiki_file ?: "$wiki_url ($category))")));

          if ($response['status'] == 'exists') {
            echo ($existing ? 'updated' : 'imported') . " as {$response['slug']}";
            $category_page .= "* [[{$response['slug']}|{$response['title']}]]\n";
          } else {
            echo $response['status'];
          }
        }
      }
      echo "\n";
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
}

echo "Done.\n";

function getMWCategoryData($wiki_url, $category, $limit) {
  $data = file_get_contents("$wiki_url/api.php?action=query&format=php&cmlimit=$limit&list=categorymembers&cmtitle=Category:" . str_replace(' ', '_', $category));
  if ($data) {
    $data = unserialize($data);
    if (count($data['query']['categorymembers'])) {
      return $data['query']['categorymembers'];
    }
  }
  return null;
}

function getMWTitleData($wiki_url, $title) {
  $data = file_get_contents("$wiki_url/api.php?action=query&format=php&prop=info&titles=" . str_replace(' ', '_', $title));
  if ($data) {
    $data = unserialize($data);
    if (count($data['query']['pages'])) {
      return $data['query']['pages'];
    }
  }
  return null;
}

function getMWPageData($wiki_url, $page_id) {
  $page_data = array();
  $data = file_get_contents("$wiki_url/api.php?action=query&format=php&prop=revisions&rvprop=content&pageids=" . $page_id);
  if ($data) {
    $data = unserialize($data);
    if (isset($data['query']['pages'])) {
      $data = array_shift($data['query']['pages']);
      if (isset($data['revisions'][0]['*'])) {
        $page_data = array(
          'title' => $data['title'],
          'content' => $data['revisions'][0]['*']
        );
      }
    }
  }
  return $page_data;
}

function convertMWToPhriction($wiki_url, $text) {
  $wiki_url = str_replace('https', 'https?', $wiki_url);
  $regexps = array(
    // replace bolded or italicized headers with regular headers
    // and make sure there are empty lines around headers
    '/\n+(=+)\'*([^\'=]+)\'*(=+)\n+/' => "\n\n\$1\$2\$3\n\n",

    // ensure an empty line before bulleted lists, and no spaces between bullet chars
    '/\n+(?:([*#])\s*)(?:([*#])\s*)?(?:([*#])\s*)?/' => "\n\n\$1\$2\$3 ",

    // use two spaces for pre-formatted block, and make sure there is a blank line before
    '/\n+ +/' => "\n\n  ",

    // replace <pre> with ``` and ensure a blank line
    '#\n*<pre>([\w\W]+?)</pre>\n*#' => "\n\n```\$1```\n\n",

    // replace direct references to wiki url (which should not be there...)
    "#\[+$wiki_url/index\.php/([^ ]+) ([^\]]+)\]+#" => '[[$1|$2]]',

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

  $text = preg_replace_callback(
    '#^\{\|(.*?)(?:^\|\+(.*?))?(^(?:((?R))|.)*?)^\|}#msi',
    'processTables',
    $text
  );

  return $text;
}

/**
 * Ideas for table processing stolen from
 * https://github.com/pear/Text_Wiki2/blob/master/Text/Wiki2/Parse/Mediawiki/Table.php
 */
function processTables(&$matches)
{
  return "\n" . preg_replace_callback(
    '#(?:^([|!])-|\G)(.*?)^(.+?)(?=^[|!]-|\z)#msi',
    'processRows',
    $matches[3]
  );
}

function processRows(&$matches)
{
  if (trim($matches[3]) == '|-') {
    return '';
  }
  $sub = preg_replace_callback(
    '#((?:^\||^!|\|\||!!|\G))(?:([^|\n]*?)\|(?!\|))?(?:\n*)(.+?)(?:\n*)(?=^\||^!|\|\||!!|\z)#msi',
    'processCells',
    $matches[3]
  );
  if ($matches[3][0] == '!') {
    $sub .= "\n" . preg_replace('/[^|]/', '-', $sub);
  }
  return "$sub\n";
}

function processCells(&$matches)
{
  return '| ' . trim($matches[3]) . ' ';
}

function getPhrictionPrefix($text, $category_map) {
  if (preg_match_all('/\[\[category:([^\]]+)\]\]/i', $text, $categories)) {
    foreach ($categories[1] as $cat) {
      $cat = strtolower(str_replace('_', ' ', $cat));
      if (isset($category_map[$cat])) {
        return $category_map[$cat] . '/';
      }
    }
  }
  return '';
}

function removeUncategorizedArticle($conduit, $safe_title, $text) {
  try {
    $existing = $conduit->callMethodSynchronous('phriction.info', array(
      "slug" => strtolower($safe_title)));
    if ($existing && $existing['content'] == $text) {
      $response = $conduit->callMethodSynchronous('phriction.edit', array(
        "slug" => $safe_title,
        "content" => ""));
      echo " ({$response['status']} as $safe_title) ";
    } else {
      echo " (may exist as $safe_title) ";
    }
  } catch (Exception $e) {
    // it does not exist, keep going
  }
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
    **import_mediwiki.php** --config foo.json --title Foo

    Import MediaWiki articles from a given category or title.
    Convert MW syntax to Remarkup.
    Create "category" pages with links to all imported articles.

    __--config__
        JSON config file with wiki.url, conduit.user, and conduit.cert

    __--title__
        MW title to import

    __--cat__
        MW category to import from (use "all" for all configured categories)

    __--file__
        Path to file with wiki code to import

    __--limit__
        Number of articles to import (defaults to 500)

    __--action__
        Action to perform (insert, replace, delete - defaults to replace)

    __--help__: show this help


EOHELP;
  echo phutil_console_format($help);
  exit(1);
}
