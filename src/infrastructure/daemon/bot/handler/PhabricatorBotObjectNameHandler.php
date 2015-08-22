<?php

/**
 * Looks for Dxxxx, Txxxx and links to them.
 */
final class PhabricatorBotObjectNameHandler extends PhabricatorBotHandler {

  /**
   * Map of PHIDs to the last mention of them (as an epoch timestamp); prevents
   * us from spamming chat when a single object is discussed.
   */
  private $recentlyMentioned = array();

  public function receiveMessage(PhabricatorBotMessage $original_message) {

    static $jokes = array(
      'When Chuck Norris throws exceptions, it\'s across the room.',
      'Chuck Norris burst the dot com bubble.',
      'All browsers support the hex definitions #chuck and #norris for the colors black and blue.',
      'Chuck Norris can solve the Towers of Hanoi in one move.',
      'Project managers never ask Chuck Norris for estimations...ever.',
      '"It works on my machine" always holds true for Chuck Norris.',
      'Chuck Norris can divide by zero.',
      'Chuck Norris can access private methods.',
      'Chuck Norris can instantiate an abstract class.',
      'Chuck Norris has faster upstream than downstream because even data runs from him.',
      'No statement can catch the ChuckNorrisException.',
      'Chuck Norris can binary search unsorted data.',
      'Chuck Norris’s keyboard has the Any key.',
      'Chuck Norris can retrieve anything from /dev/null.',
      'Every programming language accepts "Chuck Norris" as the line terminator.',
      'Chuck Norris doesn\'t need an account. He just logs in.',
      'All user interfaces are friendly to Chuck Norris',
      'Chuck Norris finished World of Warcraft.',
      'Chuck Norris can delete the Recycling Bin.',
      'It was Chuck Norris that bit the apple in the Apple logo.',
      'Chuck Norris browser doesn\'t store cookies. It only stores raw meat.',
      'Chuck Norris can use goto in Java.',
    );

    switch ($original_message->getCommand()) {
    case 'MESSAGE':
      $message = $original_message->getBody();
      $matches = null;

      $paste_ids = array();
      $commit_names = array();
      $vote_ids = array();
      $file_ids = array();
      $object_names = array();
      $output = array();
      $quiet_mins = 5;

      $pattern =
        '@'.
        '(?<!/)(?:^|\b)'.
        '(R2D2|C3PO|Chewbacca|Chuck Norris)'.
        '(?:\b|$)'.
        '@i';

      $target_name = $original_message->getTarget()->getName();
      if (empty($this->recentlyMentioned[$target_name])) {
        $this->recentlyMentioned[$target_name] = array();
      }
      if (preg_match_all($pattern, $message, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
          switch ($match[1]) {
            case 'R2D2':
              $output[$match[1]] = pht('beep boop bop');
              break;
            case 'Chewbacca':
              $output[$match[1]] = pht('Uuuuuur Ahhhhhrrr Uhrrr Ahhhrrr Aaargh');
              break;
            case 'C3PO':
              $output[$match[1]] = pht('Excuse me sir, but might I inquire as to what\'s going on?');
              break;
            case 'Chuck Norris':
              $quiet_mins = 60;
              $joke = $jokes[array_rand($jokes)];
              $joke_id = md5($joke);
              if (isset($this->recentlyMentioned[$target_name][$joke_id])) {
                $joke = "I am tired of Chuck Norris jokes. How about a game if Tic-Tac-Toe?";
                $joke_id = "TTT";
              }
              $output[$joke_id] = pht($joke);
              break;
          }
        }

        // Use a negative lookbehind to prevent matching "/D123", "#D123",
        // ":D123", etc.
        $pattern =
          '@'.
          '(?<![/:#-])(?:^|\b)'.
          '([A-Z])(\d+)'.
          '(?:\b|$)'.
          '@';

        if (preg_match_all($pattern, $message, $matches, PREG_SET_ORDER)) {
          foreach ($matches as $match) {
            switch ($match[1]) {
              case 'P':
                $paste_ids[] = $match[2];
                break;
              case 'V':
                $vote_ids[] = $match[2];
                break;
              case 'F':
                $file_ids[] = $match[2];
                break;
              default:
                $name = $match[1] . $match[2];
                switch ($name) {
                  case 'T1000':
                    $output[$name] = pht(
                      'T1000: A mimetic poly-alloy assassin controlled by ' .
                      'Skynet');
                    break;
                  default:
                    $object_names[] = $name;
                    break;
                }
                break;
            }
          }
        }

        $pattern =
          '@'.
          '(?<!/)(?:^|\b)'.
          '(r[A-Z]+)([0-9a-z]{0,40})'.
          '(?:\b|$)'.
          '@';
        if (preg_match_all($pattern, $message, $matches, PREG_SET_ORDER)) {
          foreach ($matches as $match) {
            if ($match[2]) {
              $commit_names[] = $match[1] . $match[2];
            } else {
              $object_names[] = $match[1];
            }
          }
        }

        if ($object_names) {
          $objects = $this->getConduit()->callMethodSynchronous(
            'phid.lookup',
            array(
              'names' => $object_names,
            ));
          foreach ($objects as $object) {
            $output[$object['phid']] = $object['fullName'] . ' - ' . $object['uri'];
          }
        }

        if ($vote_ids) {
          foreach ($vote_ids as $vote_id) {
            $vote = $this->getConduit()->callMethodSynchronous(
              'slowvote.info',
              array(
                'poll_id' => $vote_id,
              ));
            $output[$vote['phid']] = 'V'.$vote['id'].': '.$vote['question'].
              ' '.pht('Come Vote').' '.$vote['uri'];
          }
        }

        if ($file_ids) {
          foreach ($file_ids as $file_id) {
            $file = $this->getConduit()->callMethodSynchronous(
              'file.info',
              array(
                'id' => $file_id,
              ));
            $output[$file['phid']] = $file['objectName'].': '.
              $file['uri'].' - '.$file['name'];
          }
        }

        if ($paste_ids) {
          foreach ($paste_ids as $paste_id) {
            $paste = $this->getConduit()->callMethodSynchronous(
              'paste.query',
              array(
                'ids' => array($paste_id),
              ));
            $paste = head($paste);

            $output[$paste['phid']] = 'P'.$paste['id'].': '.$paste['uri'].' - '.
              $paste['title'];

            if ($paste['language']) {
              $output[$paste['phid']] .= ' (' . $paste['language'] . ')';
            }

            $user = $this->getConduit()->callMethodSynchronous(
              'user.query',
              array(
                'phids' => array($paste['authorPHID']),
              ));
            $user = head($user);
            if ($user) {
              $output[$paste['phid']] .= ' by '.$user['userName'];
            }
          }
        }

        if ($commit_names) {
          $commits = $this->getConduit()->callMethodSynchronous(
            'diffusion.querycommits',
            array(
              'names' => $commit_names,
            ));
          foreach ($commits['data'] as $commit) {
            $output[$commit['phid']] = $commit['uri'];
          }
        }

        foreach ($output as $phid => $description) {

          // Don't mention the same object more than once every 10 minutes
          // in public channels, so we avoid spamming the chat over and over
          // again for discsussions of a specific revision, for example.


          $quiet_until = idx(
              $this->recentlyMentioned[$target_name],
              $phid,
              0) + (60 * $quiet_mins);

          if (time() < $quiet_until) {
            // Remain quiet on this channel.
            continue;
          }

          $this->recentlyMentioned[$target_name][$phid] = time();
          $this->replyTo($original_message, $description);
        }
        break;
      }
    }
  }

}
