<?php

// error_reporting(E_ALL);
// ini_set('display_errors',1);

// authorized API access for a dummy project that can only be requested from [anon.]csug.rochester.edu (128.151.69.98)
$uct_callink_key = 'AIzaSyB6xPrZcyxXHdWvXrx3GUWeEGczw42YdLQ';
// calendar to read
$uct_cal_id = '04lnqg1jsbtupnkq09esf5ccpo@group.calendar.google.com';

// API request constructions
// base URL
$api_base = 'https://www.googleapis.com/calendar/v3/';
// request for events in a week
$api_events_list = "calendars/$uct_cal_id/events?";
// request for events in a week: GET params
$api_events_list_query =
    array(
        'key'=>$uct_callink_key,
        'maxResults'=>50,
        'singleEvents'=>'true',
        'orderBy'=>'startTime',
        //set 'timeMin' and 'timeMax' below when doing actual query
    );

// csug-tutoring defaults
$def_location = 'Hylan 301';
$def_class_list = 'CSC 171, 172';

// not PHP 5.5.0 yet!
if (!function_exists('json_last_error_msg')) {
    function json_last_error_msg() {
        static $errors = array(
            JSON_ERROR_NONE             => null,
            JSON_ERROR_DEPTH            => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH   => 'Underflow or the modes mismatch',
            JSON_ERROR_CTRL_CHAR        => 'Unexpected control character found',
            JSON_ERROR_SYNTAX           => 'Syntax error, malformed JSON',
            JSON_ERROR_UTF8             => 'Malformed UTF-8 characters, possibly incorrectly encoded'
        );
        $error = json_last_error();
        return array_key_exists($error, $errors) ? $errors[$error] : "Unknown error ({$error})";
    }
}

// HTTP request function
function curl_get_json($url, $query_object = array()) {
    $url .= http_build_query($query_object);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $json = curl_exec($ch);
    if ($json === false) {
        if (curl_errno($ch)) {
            return array('error'=>'Google API request error: '.curl_error($ch));
        } else {
            return array('error'=>'Google API request error: cURL failed');
        }
    }
    $arr = json_decode($json, true);
    if ($arr === null) {
        if (json_last_error()) {
            return array('error'=>'Google API request error: '.json_last_error_msg());
        } else {
            return array('error'=>'Google API request error: JSON parsing failed');
        }
    }
    return $arr;
}

// print relevent parts of a date, where there are 3 choices:
// parts = 1: \t\h\e jS
// parts = 2: M jS
// parts = 3: M jS \o\f Y
// given $value = date to print, $now = current time (to determine which parts 
// are relevant), and $minimum_parts = minimum parts value that can be used
function date_adapt($value, $now, $minimum_parts) {
    $parts = $minimum_parts;
    if ($parts < 1) $parts = 1;
    if (date('n', $value) != date('n', $now)) {
        if ($parts < 2) $parts = 2;
    }
    if (date('Y', $value) != date('Y', $now)) {
        if ($parts < 3) $parts = 3;
    }

    switch ($parts) {
    case 1:
        $fmt = '\t\h\e jS';
        break;
    default:
    case 2:
        $fmt = 'M jS';
        break;
    case 3:
        $fmt = 'M jS \o\f Y';
        break;
    }
    return date($fmt, $value);
}

function display_week($week_relative_name, $week_start, $week_end, $range_start, $range_end) {
    global $api_base, $api_events_list, $api_events_list_query;
    global $now;

    $tutoring_list = '';

    $api_events_list_query['timeMin'] = date('c', $range_start);
    $api_events_list_query['timeMax'] = date('c', $range_end);
    $events_this_week = curl_get_json($api_base.$api_events_list, $api_events_list_query);

    // check whether we had a result and print first line
    if ($events_this_week === false || !isset($events_this_week['items'])) {
        // $events_this_week did not have an 'items' element
        $tutoring_list .= "<tr><td colspan=\"2\"><pre>Error getting tutor list for $week_relative_name week. Value of $events_this_week:\n".var_dump($events_this_week).'</pre></td></tr>';
    } elseif (count($events_this_week['items']) == 0) {
        // no sessions
        $tutoring_list .= ($week_relative_name == 'this') ? "<tr><td colspan=\"2\">No tutoring sessions are scheduled for $week_relative_name week. This could be because it's a school vacation.</td></tr>" : '';
    } else  {
        $week_start_v = date_adapt(strtotime('+1 day', $week_start), $now, 2);
        $week_end_v = date_adapt(strtotime('-2 days', $week_end), $week_start, 1);
        $tutoring_list .= "<tr><td colspan=\"2\">Tutoring sessions $week_relative_name week (the week of $week_start_v through $week_end_v):</td></tr>";

        // iterate over events
        foreach ($events_this_week['items'] as $event) {
            $tutor = isset($event['summary']) ? htmlentities($event['summary']) : 'Tutor Name';
            $start = isset($event['start']['dateTime']) ? strtotime($event['start']['dateTime']) : -1;
            $end = isset($event['end']['dateTime']) ? strtotime($event['end']['dateTime']) : -1;
            $location = isset($event['location']) ? htmlentities($event['location']) : $def_location;
            $class_list = isset($event['description']) ? str_replace("\n", '<br/>', htmlentities($event['description'])) : $def_class_list;
            if (!strlen($location)) {
                $location = $def_location;
            }
            if (!strlen($class_list)) {
                $class_list = $def_class_list;
            }

            if ($start <= 0 || $end <= 0) {
                $tutoring_list .= '<tr><td colspan="2"><pre>Error parsing one of the returned events. Value of $event:'."\n".var_dump($event).'</pre></td></tr>';
                continue;
            }

            if ($now > $end) {
                $row_style = 'past';
            } else if ($now > $start) {
                $row_style = 'now';
            } else if ($now > strtotime('today', $start)) {
                $row_style = 'today';
            } else {
                $row_style = 'future';
            }
            $tutoring_list .= '<tr class="'.$row_style.'">'.
                '<td class="tutor">'.$tutor.'</td><td>'.
                date('l', $start).' '.date_adapt($start, $now, 1).' at '.
                date('g:ia', $start).' - '.date('g:ia', $end)." ($row_style) in $location<br/>".
                '<span class="class_list">'.$class_list.'</span></td></tr>';
            //$tutoring_list .= "<tr class="
        }
    }
    return $tutoring_list;
}


// calculations for list display
$now = time();
if (file_exists('tutors-cache.txt') && $now - filemtime('tutors-cache.txt') < 60) {
    $tutoring_list = file_get_contents('tutors-cache.txt');
} else {
    // do actual query
    $week1_start = strtotime('+1 day', strtotime('last Saturday', $now));
    $week1_end = strtotime('next Sunday', $week1_start);
    $first_day = strtotime('today', $now);
    $last_day = strtotime('+7 days', $first_day);
    $week2_start = strtotime('+7 days', $week1_start);
    $week2_end = strtotime('+7 days', $week1_end);
    $tutoring_list  = '<table>';
    $tutoring_list .= display_week('this', $week1_start, $week1_end, $first_day, $week1_end);
    $tutoring_list .= display_week('next', $week2_start, $week2_end, $week2_start, $last_day);
    $tutoring_list .= '</table>';

    file_put_contents('tutors-cache.txt', $tutoring_list);
    $tutoring_list .= '<br>* = Will help you set up your development environment<br><br>';
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Rochester CSUG Tutoring</title>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />

        <!-- styles -->
        <link href="assets/css/bootstrap.css" rel="stylesheet">
        <link href="assets/css/bootstrap-responsive.css" rel="stylesheet">
        <style type="text/css">
            /*body {
                padding-top: 60px;
                padding-bottom: 40px;
            }*/
            table { padding: 0; border-spacing: 5px; border-collapse: separate; }
            tr.today { background-color: #ffd; }
            tr.now { background-color: #ff8; font-weight: bold; }
            tr.past { color: #bbb; font-style: italic; }
            span.class_list { color: #777; }
            tr.past span.class_list { color: #ccc; }
            td.tutor { font-weight: bold; vertical-align: top; }
        </style>

        <!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
        <!--[if lt IE 9]>
        <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
        <![endif]-->

        <link rel="shortcut icon" href="assets/ico/favicon.ico">

        <script type="text/javascript">
            var _gaq = _gaq || [];
            _gaq.push(['_setAccount', 'UA-34732780-1']);
            _gaq.push(['_trackPageview']);
        </script>
    </head>

    <body>
        <!--<div class="navbar navbar-inverse navbar-fixed-top">
            <div class="navbar-inner">
                <div class="container">
                    <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </a>
                    <a class="brand" href="index.html#">University of Rochester CSUG Tutoring</a>
                    <div class="nav-collapse collapse">
                        <ul class="nav">
                            <li class="active"><a href="index.html#">Home</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>-->
        <div class="container">
            <a style="margin-bottom:10px;display:block" href="http://cs.rochester.edu/"><img id="logo" src="urcs-banner-old.png" alt="Department of Computer Science, part of the Hajim School of Engineering at the University of Rochester" /></a>

            <div class="hero-unit">
                <h1>Need CS help?</h1>
                <p>You're in the right place. <img src="csug-b-256-t.png" width="32" height="32" alt="CSUG (logo)" title="Computer Science Undergraduate Council" style="cursor:help" /> offers <b>free tutoring</b> for all <b>Computer Science</b> courses.</p>
                <?php echo $tutoring_list; ?>
                <p><a class="btn btn-primary btn-large" href="https://www.google.com/calendar/embed?src=04lnqg1jsbtupnkq09esf5ccpo%40group.calendar.google.com&amp;ctz=America/New_York" onClick="_gaq.push(['_trackEvent', 'Followup', 'Schedule']);">See full schedule &raquo;</a></p>
            </div>

            <div class="row">
                <div class="span6">
                    <h2>Who we are</h2>
                    <p>We're a bunch of volunteer computer science majors looking to provide help to those who need it.  Feel free to drop by and say hello.</p>
                </div>
                <div class="span6">
                    <h2>What we do</h2>
                    <p>We provide tutoring <b>Monday through Fridays</b> during the school year.  If you're having difficulty with homework, you're stuck on a project, or you need a second pair of eyes, please stop by. Some of us are also able to help you set up your development environment!</p>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="span6">
                    <h2>Find us</h2>
                    <p>You'll usually be able to find one of us in <b>Hylan 301</b> (the non-major's computer science lab) on weekdays between 9AM and 9PM.  Please see our schedule for more details or changes in our location.</p>
                </div>
                <div class="span6">
                    <h2>Contact us</h2>
                <p>Can't find the room?  Do you have a comment or suggestion?  Unable to attend any of the times?  Want to become a tutor?</p>
                    <p><a class="btn" href="mailto:csug-tutoring@googlegroups.com">Send us an email &raquo;</a></p>
                </div>
            </div>


        </div> <!-- /container -->

        <!-- javascript
        ================================================== -->
        <!-- Placed at the end of the document so the pages load faster -->
        <!--script src="assets/js/jquery.js"></script>
        <script src="assets/js/bootstrap-transition.js"></script>
        <script src="assets/js/bootstrap-alert.js"></script>
        <script src="assets/js/bootstrap-modal.js"></script>
        <script src="assets/js/bootstrap-dropdown.js"></script>
        <script src="assets/js/bootstrap-scrollspy.js"></script>
        <script src="assets/js/bootstrap-tab.js"></script>
        <script src="assets/js/bootstrap-tooltip.js"></script>
        <script src="assets/js/bootstrap-popover.js"></script>
        <script src="assets/js/bootstrap-button.js"></script>
        <script src="assets/js/bootstrap-collapse.js"></script>
        <script src="assets/js/bootstrap-carousel.js"></script>
        <script src="assets/js/bootstrap-typeahead.js"></script-->

        <script type="text/javascript">
            (function() {
             var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
             ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
             var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
             })();
        </script>
     </body>
 </html>

