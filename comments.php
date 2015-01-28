<?php

// displays some comments for a certain url
$url = 'http://developers.facebook.com/docs/reference/fql/comment/';

// fql multiquery to fetch all the data we need to display in one go
$queries = array('q1' => 'select post_fbid, fromid, object_id, text, time from comment where object_id in (select comments_fbid from link_stat where url ="'.$url.'")',
                 'q2' => 'select post_fbid, fromid, object_id, text, time from comment where object_id in (select post_fbid from #q1)',
                 'q3' => 'select name, id, url, pic_square from profile where id in (select fromid from #q1) or id in (select fromid from #q2)',
                 );

// note format json-strings is necessary because 32-bit php sucks at decoding 64-bit ints :(
$result = json_decode(file_get_contents('http://api.facebook.com/restserver.php?format=json-strings&method=fql.multiquery&queries='.urlencode(json_encode($queries))));

$comments = $result[0]->fql_result_set;
$replies = $result[1]->fql_result_set;
$profiles = $result[2]->fql_result_set;
$profiles_by_id = array();
foreach ($profiles as $profile) {
  $profiles_by_id[$profile->id] = $profile;
}
$replies_by_target = array();
foreach ($replies as $reply) {
  $replies_by_target[$reply->object_id][] = $reply;
}

/**
 * print a comment and author, given a comment passed in an an array of all profiles.
 * @param object $comment as returned by q1 or q2 of the above fql queries
 * @param array $profiles_by_id, a list of profiles returned by q3, keyed by profile id
 * @returns string markup
 */
function pr_comment($comment, $profiles_by_id) {
  $profile = $profiles_by_id[$comment->fromid];
  $author_markup = '';
  if ($profile) {
    $author_markup =
      '<span class="profile">'.
        '<img src="'.$profile->pic_square.'" align=left />'.
        '<a href="'.$profile->url.'" target="_blank">'.$profile->name.'</a>'.
      '</span>';
  }

  return
    $author_markup.
    ' ('.date('r', $comment->time).')'.
    ': '.
    htmlspecialchars($comment->text);
}

print '<html><body>';

// print each comment
foreach ($comments as $comment) {
  print
    '<div style="overflow:hidden; margin: 5px;">'.
      pr_comment($comment, $profiles_by_id).
    '</div>';
  // print each reply
  if (!empty($replies_by_target[$comment->post_fbid])) {
    foreach ($replies_by_target[$comment->post_fbid] as $reply) {
      print
        '<div style="overflow:hidden; margin: 5px 5px 5px 50px">'.
          pr_comment($reply, $profiles_by_id).
        '</div>';
    }
  }
}


