<?php
// =====================================================================
//  TEAM PROJECT WEBSITE  -  everything runs from this one page (index.php)
//  First comes the PHP that runs before the page is shown, then the HTML.
//  The styling is in team.css and the JavaScript is in app.js.
//
//  HOW THE PAGE IS CONNECTED
//   Team photos + Finder rows  -> open that teammate's Profile
//   Profile                    -> shows the posts written for that teammate
//   Post board                 -> write posts (Fetch POST) and read them (Fetch GET)
//   Post viewer                -> opens ONE post from the board by its number (XMLHttpRequest)
//   Data tools                 -> converts the teammate you picked into JSON
//   Students                   -> the XML list, with an add form
// =====================================================================

date_default_timezone_set('Asia/Manila');

// ---------- the team (written once, used by every section) ----------
$team = [
    ['first' => 'Carlz D.',        'last' => 'Andrino',   'age' => 20, 'location' => 'Molino 3, Bacoor, Cavite',            'hobbies' => 'Online Games, Reading, Cooking',                       'img' => 'images/andrino.jpg'],
    ['first' => 'Loid Russel A.',  'last' => 'Bernal',    'age' => 20, 'location' => 'Poblacion, Muntinlupa City',          'hobbies' => 'Gaming, Watching Movies, Doom Scrolling',              'img' => 'images/bernal.jpg'],
    ['first' => 'Kyla C.',         'last' => 'Garcia',    'age' => 20, 'location' => 'Bayanan, Muntinlupa City',            'hobbies' => 'Singing, Watching Movies, Listening to Music',         'img' => 'images/garcia.jpg'],
    ['first' => 'Althea Kyle N.',  'last' => 'Grejarte',  'age' => 20, 'location' => 'Putatan, Muntinlupa City',            'hobbies' => 'Reading, Gaming, Sketching',                           'img' => 'images/grejarte.jpg'],
    ['first' => 'John Marcel V.',  'last' => 'Hernandez', 'age' => 20, 'location' => 'GMA, Cavite, Brgy. F. Reyes',         'hobbies' => 'Playing Guitar, Reading, Jogging & Walking, Singing',  'img' => 'images/hernandez.jpg'],
    ['first' => 'Ivan Roy L.',     'last' => 'Manzano',   'age' => 19, 'location' => 'Prinza, Poblacion, Muntinlupa City',  'hobbies' => 'Watching Movies, Jogging',                             'img' => 'images/manzano.jpg'],
    ['first' => 'Pauline Angel T.','last' => 'Perez',     'age' => 20, 'location' => 'NBP Poblacion, Muntinlupa City',      'hobbies' => 'Cafe Hopping',                                         'img' => 'images/perez.jpg'],
];
$course = 'BSIT';
$year   = 3;

// ---------- small helpers ----------
function e($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }   // makes text safe to print
function full_name($m) { return $m['first'] . ' ' . $m['last']; }
function clip($text, $max) { return function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max); }
function as_text($value) { return is_scalar($value) ? trim((string)$value) : ''; }

// who a message is for: 0 = the whole team, 1..7 = one teammate
function recipient_name($to) {
    global $team;
    return ($to >= 1 && $to <= count($team)) ? full_name($team[$to - 1]) : 'The whole team';
}

// ---------- files that the site writes to ----------
define('MSG_FILE', __DIR__ . '/data/messages.json');
define('XML_FILE', __DIR__ . '/data/student_data.xml');

// ---------- messages are kept as JSON text in a file ----------
function load_messages() {
    if (!file_exists(MSG_FILE)) return [];
    $list = json_decode(file_get_contents(MSG_FILE), true);      // JSON text -> PHP array
    if (!is_array($list)) return [];
    foreach ($list as $k => $m) {                                // every post has a number (its ID)
        $list[$k]['id']    = isset($m['id']) ? (int)$m['id'] : $k + 1;
        $list[$k]['title'] = isset($m['title']) && $m['title'] !== '' ? $m['title'] : 'Untitled post';
    }
    return $list;
}

function add_message($name, $title, $text, $to) {
    global $team;
    $name  = clip(trim($name), 40);
    $title = clip(trim($title), 80);
    $text  = clip(trim($text), 300);
    $to    = (int)$to;
    if ($to < 0 || $to > count($team)) $to = 0;
    if ($name === '' || $text === '') return false;
    if ($title === '') $title = 'Untitled post';

    $list = load_messages();
    $next = 1;                                                   // next post number = highest number + 1
    foreach ($list as $m) { $next = max($next, (int)$m['id'] + 1); }
    $time = date('M d, Y h:i A');

    $list[] = ['id' => $next, 'name' => $name, 'title' => $title, 'message' => $text, 'to' => $to, 'time' => $time];
    $list   = array_slice($list, -200);                          // keep the latest 200
    $json   = json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);   // PHP array -> JSON text
    if ($json === false) return false;
    if (file_put_contents(MSG_FILE, $json, LOCK_EX) === false) return false;
    return post_view(['id' => $next, 'name' => $name, 'title' => $title, 'message' => $text, 'to' => $to, 'time' => $time]);
}

// one post, in the shape the JavaScript receives
function post_view($m) {
    $to = isset($m['to']) ? (int)$m['to'] : 0;
    return ['id' => (int)$m['id'], 'author' => as_text($m['name']), 'title' => as_text($m['title']), 'body' => as_text($m['message']),
            'to' => $to, 'to_name' => recipient_name($to), 'time' => as_text($m['time'])];
}

// ---------- students are kept in an XML file ----------
function xml_child($dom, $parent, $tag, $text) {
    $el = $dom->createElement($tag);
    $el->appendChild($dom->createTextNode($text));
    $parent->appendChild($el);
}

function load_students() {                                       // XML file -> PHP array (or null if unreadable)
    if (!file_exists(XML_FILE)) return null;
    $xml = @simplexml_load_file(XML_FILE);
    if ($xml === false) return null;
    $list = [];
    foreach ($xml->student as $s) {
        $list[] = ['id' => (string)$s->id, 'name' => (string)$s->name, 'course' => (string)$s->course, 'year' => (string)$s->year];
    }
    return $list;
}

function add_student($name, $courseName, $yearLevel) {          // returns the new student, or false
    $name       = clip(trim($name), 60);
    $courseName = clip(trim($courseName), 20);
    $yearLevel  = (int)$yearLevel;
    if ($name === '' || $courseName === '' || $yearLevel < 1 || $yearLevel > 5) return false;

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    if (!file_exists(XML_FILE) || !@$dom->load(XML_FILE)) {      // no file yet? start a new one
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->appendChild($dom->createElement('students'));
    }

    $highest = 0;                                                // next ID = highest ID + 1
    foreach ($dom->getElementsByTagName('id') as $idNode) {
        $highest = max($highest, (int)$idNode->nodeValue);
    }
    $newId = str_pad((string)($highest + 1), 3, '0', STR_PAD_LEFT);

    $student = $dom->createElement('student');
    xml_child($dom, $student, 'id',     $newId);
    xml_child($dom, $student, 'name',   $name);
    xml_child($dom, $student, 'course', $courseName);
    xml_child($dom, $student, 'year',   (string)$yearLevel);
    $dom->documentElement->appendChild($student);
    if ($dom->save(XML_FILE) === false) return false;
    return ['id' => $newId, 'name' => $name, 'course' => $courseName, 'year' => (string)$yearLevel];
}

function delete_student($id) {                                   // remove one student from the XML file
    $id = (int)$id;
    if ($id < 1 || !file_exists(XML_FILE)) return false;

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    if (!@$dom->load(XML_FILE)) return false;

    foreach ($dom->getElementsByTagName('student') as $student) {
        $idNode = $student->getElementsByTagName('id')->item(0);
        if ($idNode && (int)$idNode->nodeValue === $id) {
            $student->parentNode->removeChild($student);
            return $dom->save(XML_FILE) !== false;
        }
    }
    return false;                                                // no student has that ID
}

// =====================================================================
//  DATA API: a small JSON API. JavaScript on this page calls it
//  with addresses like index.php?api=posts
// =====================================================================
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $api    = $_GET['api'];
    $method = $_SERVER['REQUEST_METHOD'];

    if ($api === 'members') {
        // the team as JSON
        $list = [];
        foreach ($team as $i => $m) {
            $list[] = ['id' => $i + 1, 'name' => full_name($m), 'email' => strtolower($m['last']) . '@example.com', 'status' => 'active'];
        }
        echo json_encode(['count' => count($list), 'members' => $list]);

    } elseif ($api === 'encode') {
        // PHP array -> JSON text
        $i = isset($_GET['member']) ? (int)$_GET['member'] - 1 : 0;
        $m = isset($team[$i]) ? $team[$i] : $team[0];
        $info = ['name' => full_name($m), 'age' => $m['age'], 'course' => $course];
        echo json_encode($info);

    } elseif ($api === 'decode' && $method === 'POST') {
        // JSON text -> PHP object and PHP array
        $raw    = file_get_contents('php://input');
        $object = json_decode($raw);          // as an object  ->  $object->name
        $array  = json_decode($raw, true);    // as an array   ->  $array['course']

        if (is_object($object) && is_array($array)) {
            echo json_encode([
                'status'       => 'success',
                'object_name'  => isset($object->name) ? as_text($object->name) : '',
                'array_course' => isset($array['course']) ? as_text($array['course']) : '',
                'age'          => isset($array['age']) ? as_text($array['age']) : ''
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'That text could not be read. Press "Convert details" first, or fix the text.']);
        }

    } elseif ($api === 'posts' && $method === 'GET') {
        // Fetch GET: the post list, newest first. ?to=3 keeps only the posts for teammate 3
        $out = [];
        foreach (array_reverse(load_messages()) as $m) {
            $v = post_view($m);
            if (isset($_GET['to']) && $_GET['to'] !== '' && $v['to'] !== (int)$_GET['to']) continue;
            $out[] = $v;
        }
        echo json_encode(['count' => count($out), 'posts' => array_slice($out, 0, 8)]);

    } elseif ($api === 'post' && $method === 'GET') {
        // XMLHttpRequest GET: ONE post by its number, e.g. index.php?api=post&id=2
        $want  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $found = null;
        foreach (load_messages() as $m) {
            if ((int)$m['id'] === $want) { $found = post_view($m); break; }
        }
        if ($found === null) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'There is no post number ' . $want . '.']);
        } else {
            echo json_encode(['status' => 'success', 'post' => $found]);
        }

    } elseif ($api === 'posts' && $method === 'POST') {
        // Fetch POST: read the JSON that was sent, save the post, answer with JSON
        $data  = json_decode(file_get_contents('php://input'), true);
        $saved = is_array($data) ? add_message(
            isset($data['author']) ? as_text($data['author']) : '',
            isset($data['title'])  ? as_text($data['title'])  : '',
            isset($data['body'])   ? as_text($data['body'])   : '',
            isset($data['to'])     ? (int)$data['to']         : 0
        ) : false;

        if ($saved === false) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Please fill in your name and the content of the post.']);
        } else {
            http_response_code(201);
            echo json_encode(['status' => 'success', 'message' => 'New Post Created!', 'post' => $saved]);
        }

    } elseif ($api === 'students' && $method === 'GET') {
        $list = load_students();
        echo json_encode(['count' => $list === null ? 0 : count($list), 'students' => $list === null ? [] : $list]);

    } elseif ($api === 'students' && $method === 'POST') {
        // Fetch POST: read the JSON that was sent, save to the XML file, answer with JSON
        $data  = json_decode(file_get_contents('php://input'), true);
        $saved = is_array($data) ? add_student(
            isset($data['name'])   ? as_text($data['name'])   : '',
            isset($data['course']) ? as_text($data['course']) : '',
            isset($data['year'])   ? as_text($data['year'])   : 0
        ) : false;

        if ($saved === false) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Check the name, the course, and a year from 1 to 5.']);
        } else {
            http_response_code(201);
            echo json_encode(['status' => 'success', 'message' => 'Student added!', 'student' => $saved]);
        }

    } elseif ($api === 'students' && $method === 'DELETE') {
        // Fetch DELETE: index.php?api=students&id=3 removes student 3 from the XML file
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (delete_student($id)) {
            echo json_encode(['status' => 'success', 'message' => 'Student deleted.', 'id' => $id]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Could not delete student ' . $id . '. It may already be gone.']);
        }

    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Unknown API request.']);
    }
    exit;   // for the API we do not want the web page, so stop here
}

// =====================================================================
//  PROFILES: the chosen member travels in the address (?member=3)
//  MESSAGES: ?to=3 pre-selects who a new message is for
// =====================================================================
$picked   = null;
$pickedNo = 0;
if (isset($_GET['member'])) {
    $n = (int)$_GET['member'];
    if ($n >= 1 && $n <= count($team)) { $picked = $team[$n - 1]; $pickedNo = $n; }
}
$toNo = isset($_GET['to']) ? (int)$_GET['to'] : 0;
if ($toNo < 0 || $toNo > count($team)) $toNo = 0;

// the messages written for the opened profile (so the profile is connected to the board)
$forPicked = [];
if ($picked) {
    foreach (array_reverse(load_messages()) as $msg) {
        if (isset($msg['to']) && (int)$msg['to'] === $pickedNo) $forPicked[] = $msg;
    }
    $forPicked = array_slice($forPicked, 0, 3);
}

$students = load_students();

// most common hobbies, used for the quick filter chips in the Finder
$hobbyCount = [];
foreach ($team as $m) {
    foreach (explode(',', str_replace('&', ',', $m['hobbies'])) as $h) {
        $h = trim($h);
        if ($h !== '') $hobbyCount[$h] = (isset($hobbyCount[$h]) ? $hobbyCount[$h] : 0) + 1;
    }
}
arsort($hobbyCount);
$topHobbies = array_slice(array_keys($hobbyCount), 0, 6);

$nav = [
    ['team',     'Team'],
    ['finder',   'Finder'],
    ['profiles', 'Profiles'],
    ['post-board',  'Post board'],
    ['post-viewer', 'Post viewer'],
    ['data',     'Data tools'],
    ['students', 'Students'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Team Project</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400&family=Young+Serif&display=swap">
<link rel="stylesheet" href="team.css?v=<?php echo (int)@filemtime(__DIR__ . '/team.css'); ?>">
</head>
<body>

<header class="topbar">
    <a class="brand" href="#top">Team Project</a>
    <nav aria-label="Sections">
        <?php foreach ($nav as $item): ?>
            <a href="#<?php echo e($item[0]); ?>" data-target="<?php echo e($item[0]); ?>"><?php echo e($item[1]); ?></a>
        <?php endforeach; ?>
    </nav>
</header>

<main id="top">

        <!-- ============ TEAM ============ -->
        <section id="team" class="hero">
            <h1>Meet the team</h1>

            <div class="composite">
                <?php foreach ($team as $i => $m): ?>
                    <article class="member">
                        <a class="member-photo" href="index.php?member=<?php echo $i + 1; ?>#profiles" aria-label="Open the profile of <?php echo e(full_name($m)); ?>">
                            <img src="<?php echo e($m['img']); ?>" alt="Photo of <?php echo e(full_name($m)); ?>">
                        </a>
                        <h3><?php echo e(full_name($m)); ?></h3>
                        <dl>
                            <dt>Age</dt>      <dd><?php echo (int)$m['age']; ?></dd>
                            <dt>From</dt>     <dd><?php echo e($m['location']); ?></dd>
                            <dt>Hobbies</dt>  <dd><?php echo e($m['hobbies']); ?></dd>
                        </dl>
                        <a class="more" href="index.php?member=<?php echo $i + 1; ?>#profiles">Open profile</a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ============ FINDER ============ -->
        <section id="finder">
            <h2>Find a teammate</h2>
            <p class="lead">Type a name, a place, or a hobby and the list filters as you type. Click a name to open that profile.</p>

            <div class="panel">
                <label for="finderInput">Search by name, place, or hobby</label>
                <input type="search" id="finderInput" placeholder="Try &quot;Muntinlupa&quot; or &quot;Reading&quot;" autocomplete="off">

                <div class="chips" aria-label="Quick filters">
                    <?php foreach ($topHobbies as $h): ?>
                        <button type="button" class="chip" data-q="<?php echo e($h); ?>"><?php echo e($h); ?></button>
                    <?php endforeach; ?>
                    <button type="button" class="chip chip-clear" data-q="">Show everyone</button>
                </div>

                <p class="count">Showing <b id="finderCount"><?php echo count($team); ?></b> of <?php echo count($team); ?> members</p>

                <div class="finder-list">
                    <?php foreach ($team as $i => $m): ?>
                        <div class="finder-row" data-search="<?php echo e(strtolower(full_name($m) . ' ' . $m['location'] . ' ' . $m['hobbies'])); ?>">
                            <img src="<?php echo e($m['img']); ?>" alt="">
                            <div>
                                <b><a href="index.php?member=<?php echo $i + 1; ?>#profiles"><?php echo e(full_name($m)); ?></a></b>
                                <span><?php echo e($m['location']); ?></span>
                                <span class="muted"><?php echo e($m['hobbies']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="empty" id="finderNone" hidden>No one matches that search. Clear it to see the whole team.</p>
            </div>
        </section>

        <!-- ============ PROFILES ============ -->
        <section id="profiles">
            <h2>Open a profile</h2>
            <p class="lead">Pick a teammate to see their details and the posts written for them.</p>

            <div class="panel">
                <form method="GET" action="index.php#profiles" class="inline-form">
                    <div>
                        <label for="memberSelect">Teammate</label>
                        <select id="memberSelect" name="member">
                            <?php foreach ($team as $i => $m): ?>
                                <option value="<?php echo $i + 1; ?>"<?php if ($pickedNo === $i + 1) echo ' selected'; ?>><?php echo e(full_name($m)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit">Open profile</button>
                </form>

                <?php if ($picked): ?>
                    <div class="profile">
                        <img src="<?php echo e($picked['img']); ?>" alt="Photo of <?php echo e(full_name($picked)); ?>">
                        <div>
                            <h3><?php echo e(full_name($picked)); ?></h3>
                            <p><b>Age:</b> <?php echo (int)$picked['age']; ?></p>
                            <p><b>From:</b> <?php echo e($picked['location']); ?></p>
                            <p><b>Hobbies:</b> <?php echo e($picked['hobbies']); ?></p>
                            <p><a href="index.php?member=<?php echo $pickedNo; ?>&amp;to=<?php echo $pickedNo; ?>#post-board">Write a post for <?php echo e($picked['first']); ?></a></p>
                        </div>
                    </div>

                    <h3 class="sub">Posts for <?php echo e($picked['first']); ?></h3>
                    <?php if (count($forPicked) === 0): ?>
                        <p class="empty">No posts for <?php echo e($picked['first']); ?> yet.</p>
                    <?php else: ?>
                        <?php foreach ($forPicked as $msg): ?>
                            <div class="msg">
                                <b>Post #<?php echo (int)$msg['id']; ?>: <?php echo e($msg['title']); ?></b> <small>by <?php echo e($msg['name']); ?></small>
                                <p><?php echo e($msg['message']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <p class="note">Copy this page's link to show the same profile to someone else.</p>
                <?php else: ?>
                    <p class="note">No profile open yet. Choose a teammate above, or click a photo in the Team section.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- ============ POST BOARD (Fetch API: GET to read, POST to write) ============ -->
        <section id="post-board">
            <h2>Post board</h2>
            <p class="lead">Write a post for the whole team or for one teammate. It is saved on the server and appears here without reloading the page. Every post has a number.</p>

            <div class="split">
                <div class="panel">
                    <h3>Write a post</h3>
                    <form id="postForm">
                        <label for="postAuthor">Your name</label>
                        <input type="text" id="postAuthor" maxlength="40" required autocomplete="off">

                        <label for="postTo">Post for</label>
                        <select id="postTo">
                            <option value="0">The whole team</option>
                            <?php foreach ($team as $i => $m): ?>
                                <option value="<?php echo $i + 1; ?>"<?php if ($toNo === $i + 1) echo ' selected'; ?>><?php echo e(full_name($m)); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="postTitle">Title</label>
                        <input type="text" id="postTitle" maxlength="80" required autocomplete="off">

                        <label for="postBody">Content</label>
                        <textarea id="postBody" rows="3" maxlength="300" required></textarea>
                        <button type="submit">Create New Post</button>
                    </form>
                    <div class="result" id="postOut" hidden></div>
                </div>

                <div class="panel">
                    <h3>Latest posts</h3>
                    <label for="postFilter">Show posts for</label>
                    <select id="postFilter">
                        <option value="">Everyone (all posts)</option>
                        <option value="0">The whole team</option>
                        <?php foreach ($team as $i => $m): ?>
                            <option value="<?php echo $i + 1; ?>"<?php if ($toNo === $i + 1) echo ' selected'; ?>><?php echo e(full_name($m)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="postList" class="msg-list"><p class="empty">Loading posts...</p></div>
                </div>
            </div>
        </section>

        <!-- ============ POST VIEWER (XMLHttpRequest: GET one post by its number) ============ -->
        <section id="post-viewer">
            <h2>Post viewer</h2>
            <p class="lead">Open one post from the board by its number. Press <b>View</b> under any post on the board and it loads here.</p>

            <div class="panel">
                <div class="inline-form">
                    <div>
                        <label for="viewId">Post number</label>
                        <input type="number" id="viewId" min="1" value="1">
                    </div>
                    <button type="button" id="viewBtn">Load Post Data</button>
                </div>
                <div class="result" id="viewOut" hidden></div>
            </div>
        </section>

        <!-- ============ DATA TOOLS (PHP array <-> JSON) ============ -->
        <section id="data">
            <h2>Team data tools</h2>
            <p class="lead">See how a teammate's details look as JSON text, edit the text, and have the server read it back.</p>

            <div class="panel">
                <h3>Teammate details</h3>
                <label for="jsonMember">Teammate</label>
                <select id="jsonMember">
                    <?php foreach ($team as $i => $m): ?>
                        <option value="<?php echo $i + 1; ?>"<?php if ($pickedNo === $i + 1) echo ' selected'; ?>><?php echo e(full_name($m)); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="encodeBtn">Convert details</button>

                <label for="jsonText">Details (you can edit them)</label>
                <textarea id="jsonText" rows="5" spellcheck="false" placeholder='Press "Convert details", or type your own'></textarea>
                <button type="button" id="decodeBtn" class="secondary">Read details</button>
                <div class="result" id="decodeOut" hidden></div>

                <p class="note" id="rosterNote">Checking the team roster...</p>
                <p class="note"><a href="index.php?api=members" target="_blank" rel="noopener">View the raw team list</a> as the server sends it.</p>
            </div>
        </section>

        <!-- ============ STUDENTS (XML file + Fetch POST) ============ -->
        <section id="students">
            <h2>Student list</h2>
            <p class="lead">The class list is saved in an XML file on the server. Look a student up by ID, or add a new one and watch the table update.</p>

            <div class="split">
                <div class="panel">
                    <h3>Students (<span id="studentCount"><?php echo $students === null ? 0 : count($students); ?></span>)</h3>
                    <?php if ($students === null): ?>
                        <p class="result err">The student list could not be read. Check that <code>data/student_data.xml</code> exists.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>ID</th><th>Name</th><th>Course</th><th>Year</th><th>Action</th></tr></thead>
                                <tbody id="studentRows">
                                <?php foreach ($students as $s): ?>
                                    <tr>
                                        <td><?php echo e($s['id']); ?></td>
                                        <td><?php echo e($s['name']); ?></td>
                                        <td><?php echo e($s['course']); ?></td>
                                        <td><?php echo e($s['year']); ?></td>
                                        <td><button type="button" class="secondary small danger" data-id="<?php echo (int)$s['id']; ?>" data-name="<?php echo e($s['name']); ?>">Delete</button></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="stack">
                    <div class="panel">
                        <h3>Add a student</h3>
                        <form id="studentForm">
                            <label for="sName">Full name</label>
                            <input type="text" id="sName" maxlength="60" required autocomplete="off">
                            <label for="sCourse">Course</label>
                            <input type="text" id="sCourse" value="<?php echo e($course); ?>" maxlength="20" required autocomplete="off">
                            <label for="sYear">Year level (1 to 5)</label>
                            <input type="number" id="sYear" min="1" max="5" value="<?php echo (int)$year; ?>" required>
                            <button type="submit">Add student</button>
                        </form>
                        <div class="result" id="studentOut" hidden></div>
                    </div>
                </div>
            </div>
        </section>


</main>

<script src="app.js?v=<?php echo (int)@filemtime(__DIR__ . '/app.js'); ?>"></script>
</body>
</html>
