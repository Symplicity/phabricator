#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = array_slice($argv, 1);
if (!$args) {
  help();
}

$action = 'replace';
$all_actions = array('insert', 'replace', 'delete');
$project = '';
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
      $manager_url = idx($config, 'manager.url');
      break;
    case '--project':
      $project = $args[++$ii];
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
  usage("Please specify --config for manager instance.");
}

if (!$project) {
  usage("Please use --project option to specify phabricator project name.");
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

try {
  $project_data = $conduit->callMethodSynchronous('project.query', array(
    "names" => array($project)));
} catch (Exception $e) {
  $project_data = null;
}
print_r($project_data);

echo "Done.\n";

function usage($message) {
  echo "Usage Error: {$message}";
  echo "\n\n";
  echo "Run 'import_manager_issue.php --help' for detailed help.\n";
  exit(1);
}

function help() {
  $help = <<<EOHELP
**SUMMARY**

    **import_manager_issue.php** --config foo.json --project foo

    __--config__
        JSON config file with wiki.url, conduit.user, and conduit.cert

    __--project__
        Phabricator project name

    __--action__
        Action to perform (insert, replace, delete - defaults to replace)

    __--help__: show this help


EOHELP;
  echo phutil_console_format($help);
  exit(1);
}

/*
 select * from picklist_bug_status;
+------+--------------+---------+
| id   | value        | orderby |
+------+--------------+---------+
| 0080 | New          |       0 |
| 0060 | Processing   |       2 |
| 0040 | Feedback     |       3 |
| 0020 | Resolved     |       5 |
| 0100 | Reopened     |       6 |
| 0120 | Acknowledged |       1 |
| 0140 | Not Resolved |       7 |
| 0160 | Review       |       4 |
| 161  | Suspended    |       8 |
| 200  | Auto Closed  |      15 |

select * from picklist_severity;
+----+-------------------+---------+-----------+
| id | value             | orderby | test_pick |
+----+-------------------+---------+-----------+
| 8  | Idea/Suggestion   |       3 | 0         |
| 13 | New System        |       7 | 0         |
| 16 | General Question  |       1 | 0         |
| 17 | Bug               |       2 | 0         |
| 18 | Contracts         |       5 | 0         |
| 20 | IT                |       6 | 0         |
| 19 | Invoicing         |       4 | 0         |
| 25 | Testing Issue     |      26 | 1         |
| 26 | Code Review Issue |      27 | 1         |
| 30 | Task              |      30 | 0         |

select * from picklist_issue_type;
+----+-----------------+---------+
| id | value           | orderby |
+----+-----------------+---------+
| 1  | Idea/Suggestion |       1 |
| 2  | Bug             |       2 |
| 3  | General/Other   |       3 |

select * from picklist_bug_priority;
+--------+---------------+---------+
| id     | value         | orderby |
+--------+---------------+---------+
| normal | Normal        |       1 |
| outage | System Outage |       2 |

select * from picklist_priority;
+------+-----------+---------+------+
| id   | value     | orderby | sla  |
+------+-----------+---------+------+
| 0060 | High      |       3 | NULL |
| 0040 | Low       |       1 | NULL |
| 0020 | Normal    |       2 | NULL |
| 0080 | Urgent    |       4 | NULL |
| 0100 | Immediate |       5 | NULL |
| 0120 | None      |       0 | NULL |

select ug.contact_id, fullname from _user_groups ug inner join contact on ug.contact_id = contact.contact_id where group_id='c9783cbb407be28dc2f5ccc2f6c71d4b';
+----------------------------------+-----------------+
| contact_id                       | fullname        |
+----------------------------------+-----------------+
| db53954d0603c4cc52380e27d1d0b251 | Svemir Brkic    |
| e3d3203ddac1455f17a97127a8ffa59b | Michael Slusher |
| d0b54d5994536ee2623844ef4208a285 | James Keel      |
| ca0223984262cc24698801ac7bd741f7 | Brendan Bennett |

cc83836025fa3bfce4729bcbcdb6370e Mike DeVine

select issue_id, assigned_to, status, priority, visible_id, issue_title, assigned_to_group, issue_type, issue_internal, todo_orderby, project, date_resolved from issue where assigned_to_group='c9783cbb407be28dc2f5ccc2f6c71d4b' or assigned_to in ('cc83836025fa3bfce4729bcbcdb6370e')

select issue_id, visible_id, issue_title, summary, assigned_to, status, priority, issue_type, issue_internal, todo_orderby, project, date_resolved from issue where (assigned_to_group='c9783cbb407be28dc2f5ccc2f6c71d4b' or assigned_to in ('cc83836025fa3bfce4729bcbcdb6370e')) and modified > '2014-09-01'

 */

include('../include/setup.inc');
if (!$sess['auth']['username']) {
  echo "Please login to manager first";
  exit;
}

$users = $_db->getAssoc("select ug.contact_id, username from _user_groups ug inner join contact on ug.contact_id = contact.contact_id where group_id='c9783cbb407be28dc2f5ccc2f6c71d4b'");
$users['cc83836025fa3bfce4729bcbcdb6370e'] = 'mdevine';

$issues = $_db->getAssoc("select i.visible_id, issue_id, issue_title, i.summary, i.assigned_to, i.status, issue_type, issue_internal, todo_orderby from issue i inner join project on project = project_id and top = 'b074e238a7686e2b63ddf660dd538588' where (i.assigned_to_group='c9783cbb407be28dc2f5ccc2f6c71d4b' or i.assigned_to in (" . joinQuoted(', ', array_keys($users)) . ")) and i.modified > " . quote(date('Y-m-d', strtotime('7 days ago'))));

$client = new PhabricatorConduitClient(array(
  'host' => 'https://review.symplicity.com/',
  'user' => 'ircbot',
  'cert' => '6XYKUYBKbwZk7mVm2duU5Yi+NlJysNkL4qDZl0+kdWcHyFAMorJk6gi2w37Gm9eBy1F5HDHUQnMBX2/AH8erDqLB4aChVm97rewbnpV5cBFsPU8C1+7jRI3JSXhbK5OLG5x+BRjBPI0qW+6b8XPsLhICn7ATqzLBebMsJM8Tvs04j2T21OhpoUdX18kFVS8TFlxSe1fLFyb3TeD5Y30l2iKfhSNuRP75VpeGzXh5N2ZYj6a52BrRRpM/af9t5hQ'
));

$user_phids = $client->getUserPHIDs($users);
$tasks = $client->fetchRecentTasks(array('projectPHIDs' => array('PHID-PROJ-jpsqxuu7ppmyi2bkg5ca')));
/**
 * [PHID-TASK-75mlb5ce635sjtcgyzdi]
 * [id]  293, [phid]  PHID-TASK-75mlb5ce635sjtcgyzdi, [authorPHID]  PHID-USER-d9456b2f4fb1d9eca148, [ownerPHID]  PHID-USER-f2qmnityh7ds4tox2zdf
 * [status]  open, [isClosed]  , [priority]  Normal
 * [title]  Deprecate default_printer flag on printer object
 * [description]  Due to recent feature (D2167) requiring selection of printer on correspondence in order to print, the 'default_printer' flag on printer object is now obsolete and needs to be removed.
 * [projectPHIDs]
 * [0]  PHID-PROJ-jpsqxuu7ppmyi2bkg5ca
 * [1]  PHID-PROJ-c335c5cfeb982433c574
 * [auxiliary]  [std:maniphest:symplicity:category]  devs
 * [dateCreated]  1393607359
 * [dateModified]  1412087761
 */

// Unset manager issues that already have matching phabricator tasks, based in issue number in task title
$exists = array();
foreach ($tasks as $phid => $t) {
  $matches = array();
  if (preg_match('/(\d\d\d\d\d+)/', $t['title'], $matches)) {
    $issue_number = $matches[1];
    if (isset($issues[$issue_number])) {
      // TODO: close phab task if issue closed?
      $issue = $issues[$issue_number];
      $exists[$phid] = $t['title'] . ' - ' . $t['status'] . ' -> ' . $issue['status'];
      unset($issues[$issue_number]);
    }
  }
}
pp($exists);
exit;

$open_statuses = array('0060', '0080', '0100', '0120', '0140');
foreach ($issues as $issue_number => $issue) {
  if (in_array($issue['status'], $open_statuses)) {
    $issue['title'] = $issue['issue_title'] . " (# $issue_number)";
    $issue['description'] = trim(preg_replace(array(
      '/\[Description:[^\]]*\]/i',
      '/\-+\s*Original Message\s*\-+\s*(Subject:[^\r\n]+)?\s*(Date:[^\r\n]+)?\s*(From:[^\r\n]+)?\s*(To:[^\r\n]+)?/i',
      '/Thank you[\w\W]+/i',
    ), '', strip_tags($issue['summary'])));
    $issue['description'] .= "\n\nhttps://manager.symplicity.com/issues/" . $issue['issue_id'];
    $issue['projectPHIDs'] = array('PHID-PROJ-jpsqxuu7ppmyi2bkg5ca');
    if ($issue['assigned_to']) {
      $username = $users[$issue['assigned_to']];
      $issue['ownerPHID'] = $user_phids[$username];
    }
    $issue['ccPHIDs'] = array('PHID-USER-ef241c883e52306c9e67');
    $result = $client->createTask($issue);
    pp($result);
  }
}

class PhabricatorConduitClient {
  private $host;
  private $user;
  private $cert;
  private $conduit;

  function __construct($args) {
    $this->host = $args['host'];
    $this->user = $args['user'];
    $this->cert = $args['cert'];
    $this->connect();
  }

  private function connect() {
    $token = time();
    $signature = sha1($token . $this->cert);
    $this->conduit = $this->callConduit('conduit.connect', array(
      'client' => __CLASS__,
      'clientVersion' => 0,
      'clientDescription' => 'Symplicity conduit client',
      'user' => $this->user,
      'host' => $this->host,
      'authToken' => $token,
      'authSignature' => $signature
    ));
  }

  private function callConduit($method, $params) {
    if (!isset($params['__conduit__']) && $this->conduit) {
      $params['__conduit__'] = $this->conduit;
    }
    $context = stream_context_create(array(
      'http' => array(
        'method' => 'POST',
        'content' => http_build_query(array('params' => json_encode($params)))
      )
    ));
    $response = file_get_contents($this->host . 'api/' . $method, false, $context);
    if ($response) {
      $response = json_decode($response, true);
      return $response['result'];
    }
    return false;
  }

  function getUserPHIDs(array $usernames) {
    $users = $this->callConduit('user.query', array(
      'usernames' => $usernames
    ));
    $phids = array();
    foreach ($users as $u) {
      $phids[$u['userName']] = $u['phid'];
    }
    return $phids;
  }

  function fetchRecentTasks($params) {
    if (isset($params['owners'])) {
      $params['ownerPHIDs'] = $this->getUserPHIDs($params['owners']);
      unset($params['owners']);
    }
    if (!isset($params['limit'])) {
      $params['limit'] = 100;
    }
    $params['order'] = 'order-modified';
    $tasks = $this->callConduit('maniphest.query', $params);
    return $tasks;
  }

  function createTask(array $params) {
    $priorities = array(0, 80, 50, 25);
    if (isset($params['todo_orderby'])) {
      $params['priority'] = $priorities[$params['todo_orderby']];
    }
    $params = array_intersect_key($params, array(
      'title' => 'issue_title',
      'description' => 'summary',
      'ownerPHID' => 'assigned_to',
      'ccPHIDs' => 'Subscribers',
      'priority' => '0 => Wishlist, 80 => High, 50 => Normal, 25 => Low',
      'projectPHIDs' => 'Projects',
    ));
    $result = $this->callConduit('maniphest.createtask', $params);
    return $result;
  }

}
