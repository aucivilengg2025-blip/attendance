<?php
/*
Plugin Name: 04012026
Description: Sem 2 Att System Demo
Version: 2.0
Author: CDAT
*/

if (!defined('ABSPATH'))
    exit;

/* =========================================================
   1. ACTIVATION – CREATE TABLES
========================================================= */
register_activation_hook(__FILE__, 'cdat_v2_install');
function cdat_v2_install()
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    /* STAFF */
    dbDelta("CREATE TABLE {$wpdb->prefix}cdat_staff2 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        wp_user_id BIGINT,
        staff_code VARCHAR(20),
        staff_name VARCHAR(100),
        role VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset;");

    /* SUBJECTS */
    dbDelta("CREATE TABLE {$wpdb->prefix}cdat_subjects2 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20),
        title VARCHAR(150),
        semester INT,
        class CHAR(1),
        staff_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset;");

    /* TIMETABLE */
    dbDelta("CREATE TABLE {$wpdb->prefix}cdat_timetable2 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class CHAR(1),
        semester INT,
        subject_id INT,
        day VARCHAR(10),
        hour INT
    ) $charset;");

    /* TOPICS */
    dbDelta("CREATE TABLE {$wpdb->prefix}cdat_topics2 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject_id INT,
        topic_title VARCHAR(200),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset;");

    /* STUDENTS + ATTENDANCE TABLES A–J */
    foreach (range('a', 'j') as $c) {
        dbDelta("CREATE TABLE {$wpdb->prefix}cdat_students2_$c (
            id INT AUTO_INCREMENT PRIMARY KEY,
            roll VARCHAR(20),
            name VARCHAR(100),
            semester INT
        ) $charset;");

        dbDelta("CREATE TABLE {$wpdb->prefix}cdat_attendance2_$c (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            student_id INT,
            subject_id INT,
            topic_id INT NULL,
            date DATE,
            hour INT,
            status VARCHAR(10),
            recorded_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_lookup (student_id, subject_id, date)
        ) $charset;");
    }
}

function cdat_add_attendance_unique_keys()
{
    global $wpdb;

    $classes = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'i', 'j'];

    foreach ($classes as $cls) {

        $table = $wpdb->prefix . 'cdat_attendance_' . $cls;

        // Check table exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ));

        if ($exists !== $table) {
            continue;
        }

        // Check if UNIQUE key already exists
        $index_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW INDEX FROM $table WHERE Key_name = %s",
            'uniq_att'
        ));

        if ($index_exists) {
            continue; // already added
        }

        // Add UNIQUE key
        $wpdb->query(
            "ALTER TABLE $table 
             ADD UNIQUE KEY uniq_att (student_id, subject_id, date, hour)"
        );
    }
}

/* =========================================================
   2. HELPERS
========================================================= */
function cdat_v2_tables($class)
{
    global $wpdb;
    $c = strtolower($class);
    // echo $wpdb->prefix . "cdat_students2_$c".' ----  '. $wpdb->prefix . "cdat_attendance2_$c";

    return [
        'students' => $wpdb->prefix . "cdat_students2_$c",
        'attendance' => $wpdb->prefix . "cdat_attendance2_$c"
    ];
}

/* =========================================================
   3. SAVE ATTENDANCE (SAFE UPSERT)
========================================================= */
function cdat_v2_save_attendance($class, $semester, $subject_id, $topic, $date, $hour, $present_ids)
{
    global $wpdb;
    $t = cdat_v2_tables($class);
    $students = $t['students'];
    $attendance = $t['attendance'];

    $present_ids = array_map('intval', (array) $present_ids);
    if (empty($present_ids))
        $present_ids = [0];
    $uid = get_current_user_id();

    // UPDATE existing
    $wpdb->query("UPDATE $attendance a
        JOIN $students s ON s.id = a.student_id
        SET a.status = IF(a.student_id IN (" . implode(',', $present_ids) . "),'present','absent'),
            a.topic = '$topic',
            a.recorded_by = $uid
        WHERE a.subject_id = $subject_id AND a.date = '$date' AND a.hour = $hour");

    // INSERT missing
    $wpdb->query("INSERT INTO $attendance (student_id,subject_id,topic_id,date,hour,status,recorded_by)
        SELECT s.id,$subject_id,'$topic','$date',$hour,
               IF(s.id IN (" . implode(',', $present_ids) . "),'present','absent'),$uid
        FROM $students s
        LEFT JOIN (
            SELECT DISTINCT student_id FROM $attendance
            WHERE subject_id=$subject_id AND date='$date' AND hour=$hour
        ) a ON a.student_id=s.id
        WHERE s.semester=$semester AND a.student_id IS NULL");
}

/* =========================================================
   4. ADMIN MENU
========================================================= */
add_action('admin_menu', function () {

    // 1️⃣ Semester-2 Attendance (Main Attendance UI)
    add_menu_page(
        'Semester-2 Attendance',
        'Sem-2 Attendance',
        'read',
        'cdat-v2',
        'cdat_v2_ui',
        'dashicons-welcome-learn-more',
        60
    );

    // 2️⃣ Subject Attendance
    add_menu_page(
        'Subject Attendance',
        'Subject Attendance',
        'read',
        'cdat-subject-attendance',
        'cdat_subject_attendance_ui',
        'dashicons-clipboard',
        61
    );

    // 3️⃣ Attendance Deficiency
    add_menu_page(
        'Attendance Deficiency',
        'Deficiency List',
        'read',
        'cdat-deficiency',
        'cdat_subject_attendance_deficiency_ui',
        'dashicons-warning',
        62
    );
});

add_action('admin_menu', function () {
    if (!current_user_can('manage_options')) {
        remove_menu_page('profile.php');   // Your Profile
        remove_menu_page('users.php');     // Users menu
    }
});


/* =========================================================
   5. BASIC UI (DEMO)
========================================================= */
function cdat_v2_ui()
{
    if (!current_user_can('read'))
        return;
    global $wpdb;

    $user = wp_get_current_user();
    $email = strtolower($user->user_email);

    /* =========================
       CLASS + SUBJECT MAPPING
    ========================== */
    $map = [
        'kmarvelb@gmail.com' => ['A', 2, 1],
        'rmkps6101969@gmail.com' => ['B', 2, 2],
        'damucivil75@gmail.com' => ['C', 2, 3],
        'velayutham.au@gmail.com' => ['D', 2, 4],
        'ezhisai_kb@yahoo.co.in' => ['E', 2, 5],
        'jee.ezhiljodhi@gmail.com' => ['F', 2, 6],
        'ashrasgo@rediffmail.com' => ['G', 2, 7],
        'srikrish.kkarthikeyan@gmail.com' => ['I', 2, 9],
        'nnrajan.au@gmail.com' => ['J', 2, 10],
        'raje69paru@gmail.com' => ['J', 2, 11],
    ];

    if (!isset($map[$email])) {
        echo '<div class="notice notice-error"><p>No class assigned.</p></div>';
        return;
    }

    [$class, $semester, $subject_id] = $map[$email];

    $class = strtolower($class);

    /* =========================
       HANDLE SAVE
    ========================== */
    if (isset($_POST['save_att'])) {
        cdat_v2_save_attendance(
            $class,
            intval($_POST['semester']),
            intval($_POST['subject_id']),
            sanitize_text_field($_POST['topic']), // ⭐ topic text
            sanitize_text_field($_POST['date']),
            intval($_POST['hour']),
            $_POST['present'] ?? []
        );

        echo '<div class="updated"><p>Attendance Saved</p></div>';
    }

    echo '<div class="wrap"><h1>Attendance v2 Batch: ' . strtoupper($class) . '</h1>';
    echo '<form method="post">';

    echo '<p><strong>Assigned Batch:</strong> ' . strtoupper($class) . '</p>';

    echo 'Semester <input name="semester" value="' . esc_attr($semester) . '" required> ';
    echo 'Subject ID <input name="subject_id" value="' . esc_attr($subject_id) . '" required> ';

    // ⭐ TOPIC TEXT INPUT
    echo 'Topic <input type="text" name="topic" 
            value="' . esc_attr($_POST['topic'] ?? '') . '" 
            placeholder="Enter topic covered" 
            style="width:300px;"> ';

    echo 'Date <input type="date" name="date" value="' . esc_attr($_POST['date'] ?? '') . '" required> ';
    echo 'Hour <input name="hour" value="' . esc_attr($_POST['hour'] ?? '') . '" required> ';

    echo '<br><br>';
    echo '<input type="submit" name="load_students" value="Load Students" class="button"> ';
    // echo '<input type="submit" name="save_att" value="Save Attendance" class="button button-primary">';

    /* =========================
       LOAD STUDENTS
    ========================== */
    if (isset($_POST['load_students']) || isset($_POST['save_att'])) {

        $tables = cdat_v2_tables($class);

        $students = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$tables['students']} 
                 WHERE semester=%d",
                intval($semester)
            )
        );

        // preload attendance
        $existing = [];
        if (!empty($_POST['subject_id']) && !empty($_POST['date']) && !empty($_POST['hour'])) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT student_id, status 
                     FROM {$tables['attendance']} 
                     WHERE subject_id=%d AND date=%s AND hour=%d",
                    intval($_POST['subject_id']),
                    $_POST['date'],
                    intval($_POST['hour'])
                )
            );
            foreach ($rows as $r) {
                $existing[$r->student_id] = $r->status;
            }
        }

        if ($students) {
            echo '<table class="widefat striped">';
            echo '<thead><tr>
                    <th>Present</th>
                    <th>Roll</th>
                    <th>Name</th>
                  </tr></thead><tbody>';

            foreach ($students as $st) {
                $checked = (isset($existing[$st->id]) && $existing[$st->id] === 'present') ? 'checked' : '';
                echo '<tr>';
                echo '<td>' . esc_html($st->roll) . '</td>';
                echo '<td>' . esc_html($st->name) . '</td>';
                echo '<td><input type="checkbox" name="present[]" value="' . $st->id . '" ' . $checked . '></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }
        echo '<input type="submit" name="save_att" value="Save Attendance" class="button button-primary">';
    }

    echo '</form></div>';
}

// Subject Attendance Register Matrix

function cdat_subject_attendance_ui()
{

    if (!current_user_can('read')) {
        return;
    }

    global $wpdb;

    $user = wp_get_current_user();
    $email = strtolower($user->user_email);

    /* ---------------- CLASS RESOLUTION ---------------- */

    // Default class
    $class = '';

    switch ($email) {
        case 'kmarvelb@gmail.com':
            $class = 'A';
            break;
        case 'rmkps6101969@gmail.com':
            $class = 'B';
            break;
        case 'damucivil75@gmail.com':
            $class = 'C';
            break;
        case 'velayutham.au@gmail.com':
            $class = 'D';
            break;
        case 'ezhisai_kb@yahoo.co.in':
            $class = 'E';
            break;

        // 🔥 MULTI-CLASS USER
        case 'jee.ezhiljodhi@gmail.com':
            $class = $_POST['class'] ?? 'F';
            if (!in_array($class, ['F', 'H'], true)) {
                $class = 'F';
            }
            break;

        case 'ashrasgo@rediffmail.com':
            $class = 'G';
            break;
        case 'srikrish.kkarthikeyan@gmail.com':
            $class = 'I';
            break;
        case 'nnrajan.au@gmail.com':
            $class = 'J';
            break;
        case 'raje69paru@gmail.com':
            $class = 'J';
            break;

        default:
            echo '<div class="notice notice-error"><p>Class not mapped</p></div>';
            return;
    }

    /* ---------------- UI HEADER ---------------- */

    echo '<div class="wrap">';
    echo '<h2>Subject Attendance Matrix – Batch: ' . esc_html($class) . '</h2>';

    /* ---------------- CLASS SELECTOR ---------------- */

    echo '<form method="post">';

    if ($email === 'jee.ezhiljodhi@gmail.com') {
        echo '<p><strong>Select Class:</strong> ';
        echo '<select name="class">';
        echo '<option value="F"' . selected($class, 'F', false) . '>Class F</option>';
        echo '<option value="H"' . selected($class, 'H', false) . '>Class H</option>';
        echo '</select></p>';
    } else {
        echo '<p><strong>Assigned Class:</strong> ' . esc_html($class) . '</p>';
    }

    /* ---------------- FILTER INPUTS ---------------- */

    $semester = intval($_POST['semester'] ?? 0);
    $subject = intval($_POST['subject_id'] ?? 0);
    echo '
        Semester  <input type="number" name="semester" required value="2" placeholder ="2"><br>
        SubjectID <input type="number" name="subject_id" min="1" required value="' . esc_attr($subject) . '">
        <p class="description">
            Basic: Class A=1, B=2, … J=10 | J Env. Studies: 11
        </p><br>
        <button class="button button-primary">Load Matrix</button>
    </form><br>';

    if (!$semester || !$subject) {
        echo '</div>';
        return;
    }

    /* ---------------- TABLES ---------------- */

    $students_table = $wpdb->prefix . 'cdat_students2_' . strtolower($class);
    $attendance_table = $wpdb->prefix . 'cdat_attendance2_' . strtolower($class);

    /* ---------------- STUDENTS ---------------- */

    $students = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, roll, name
             FROM $students_table
             WHERE semester=%d",
            $semester
        )
    );

    /* ---------------- DATE / HOUR SLOTS ---------------- */

    $slots = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DISTINCT date, hour, subject_id
             FROM $attendance_table
             WHERE subject_id=%d
             ORDER BY date, hour",
            $subject
        )
    );

    if (!$students || !$slots) {
        echo '<div class="notice notice-warning"><p>No attendance data found.</p></div>';
        echo '</div>';
        return;
    }

    /* ---------------- LOAD ATTENDANCE ---------------- */

    $raw = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT student_id, date, hour, status
             FROM $attendance_table
             WHERE subject_id=%d",
            $subject
        )
    );

    $att = [];
    foreach ($raw as $r) {
        $att[$r->student_id][$r->date][$r->hour] = $r->status;
    }

    /* ---------------- MATRIX ---------------- */

    echo '<table class="widefat striped">';
    echo '<thead><tr><th>Roll</th><th>Name</th>';

    foreach ($slots as $s) {
        echo '<th>' . esc_html(date('d-m-Y', strtotime($s->date))) . '</th>';
    }
    echo '<th>Present</th>';
    echo '<th>%</th>';
    echo '</tr></thead><tbody>';

    foreach ($students as $st) {

        $present_count = 0;
        $total_classes = count($slots);

        echo '<tr>';
        echo '<td>' . esc_html($st->roll) . '</td>';
        echo '<td>' . esc_html($st->name) . '</td>';

        foreach ($slots as $s) {
            $status = $att[$st->id][$s->date][$s->hour] ?? '-';

            if ($status === 'present') {
                $present_count++;
            }

            $color = $status === 'present' ? 'green' : ($status === 'absent' ? 'red' : '#999');

            $mark = '-';
            if ($status === 'present') {
                $mark = '/';
            } elseif ($status === 'absent') {
                $mark = 'A';
            }
            echo '<td style="text-align:center;color:' . esc_attr($color) . ';">' .
                esc_html($mark) .
                '</td>';
        }

        $percentage = $total_classes
            ? round(($present_count / $total_classes) * 100, 2)
            : 0;

        echo '<td style="text-align:center;font-weight:bold;">' . $present_count . '/' . $total_classes . '</td>';
        echo '<td style="text-align:center;font-weight:bold;">' . $percentage . '%</td>';

        echo '</tr>';
    }
    echo '</tbody></table></div>';

    /// Display DAte + Topic 

    global $wpdb;

    //     $attendance_table = $wpdb->prefix . 'cdat_attendance2_a';

    echo '<div class="wrap">';
    //     echo '<h1>Subject Attendance</h1>';
    echo $subject, $attendance_table;

    $records = $wpdb->get_results("
        SELECT DISTINCT date, topic, subject_id
        FROM $attendance_table
          WHere  topic IS NOT NULL
          AND topic != ''
        ORDER BY date DESC");

    if ($records) {
        echo '<hr>';
        echo '<h2>Record of class work/Sessions</h2>';

        echo '<table class="widefat striped">';
        echo '<thead>
                <tr>
                    <th style="width:150px">Date</th>
                    <th>Topic</th>
                </tr>
              </thead><tbody>';

        foreach ($records as $r) {
            if ($r->subject_id == $subject) {
                echo '<tr>';
                echo '<td>' . esc_html(date('d-m-Y', strtotime($r->date))) . '</td>';
                echo '<td>' . esc_html($r->topic) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    } else {
        echo '<p><em>No Recorded Classwork for sessions yet.</em></p>';
    }
    echo '</div>';
}

function cdat_subject_attendance_deficiency_ui()
{
    global $wpdb;

    if (!current_user_can('read'))
        return;

    $user = wp_get_current_user();
    $email = strtolower($user->user_email);

    /* =========================
       CLASS MAPPING (MULTI OK)
    ========================== */
    $map = [
        'kmarvelb@gmail.com' => ['A'],
        'rmkps6101969@gmail.com' => ['B'],
        'damucivil75@gmail.com' => ['C'],
        'velayutham.au@gmail.com' => ['D'],
        'ezhisai_kb@yahoo.co.in' => ['E'],
        'jee.ezhiljodhi@gmail.com' => ['F', 'H'], // 🔥 multi class
        'ashrasgo@rediffmail.com' => ['G'],
        'srikrish.kkarthikeyan@gmail.com' => ['I'],
        'nnrajan.au@gmail.com' => ['J'],
        'raje69paru@gmail.com' => ['J'],
    ];

    if (!isset($map[$email])) {
        echo '<div class="notice notice-error"><p>Class not mapped.</p></div>';
        return;
    }

    $allowed_classes = $map[$email];
    $class = strtoupper($_POST['class'] ?? $allowed_classes[0]);

    if (!in_array($class, $allowed_classes)) {
        echo '<div class="notice notice-error"><p>Invalid class selection.</p></div>';
        return;
    }

    $semester = intval($_POST['semester'] ?? 0);
    $subject = intval($_POST['subject_id'] ?? 0);

    $students_table = $wpdb->prefix . 'cdat_students2_' . strtolower($class);
    $attendance_table = $wpdb->prefix . 'cdat_attendance2_' . strtolower($class);

    echo '<div class="wrap">';
    echo '<h2 style="color:blue;">Attendance Deficiency Class ' . esc_html($class) . '</h2>';
    echo '<br><br>';

    /* =========================
       FILTER FORM
    ========================== */
    echo '<form method="post">';

    if (count($allowed_classes) > 1) {
        echo 'Class <select name="class">';
        foreach ($allowed_classes as $c) {
            echo '<option value="' . $c . '" ' . selected($class, $c, false) . '>' . $c . '</option>';
        }
        echo '</select> ';
    } else {
        echo '<input type="hidden" name="class" value="' . $class . '">';
    }
    // Semester <input type="number" name="semester" required value="'.esc_attr($semester).'">
    echo '
        Semester <input type="number" name="semester" required value="2">
        Subject ID <input type="number" name="subject_id" required value="' . esc_attr($subject) . '"
            placeholder="A-1,B-2,...J-10 | Env:11">
        <button class="button button-primary">Load Deficiency</button>
    </form><br>';

    if (!$semester || !$subject) {
        echo '</div>';
        return;
    }

    /* =========================
       LOAD STUDENTS
    ========================== */
    $students = $wpdb->get_results($wpdb->prepare(
        "SELECT id, roll, name, email, mobile
         FROM $students_table
         WHERE semester=%d
         ",
        $semester
    ));

    if (!$students) {
        echo '<div class="notice notice-warning"><p>No students found.</p></div></div>';
        return;
    }

    echo '<table class="widefat striped">';
    echo '<thead>
        <tr>
            <th>Roll</th>
            <th>Name</th>
            <th>Attendance %</th>
            <th>Status</th>
            <th>Email</th>
            <th>WhatsApp</th>
        </tr>
    </thead><tbody>';

    foreach ($students as $st) {

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $attendance_table
             WHERE student_id=%d AND subject_id=%d",
            $st->id,
            $subject
        ));

        if ($total === 0)
            continue;

        $present = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $attendance_table
             WHERE student_id=%d AND subject_id=%d AND status='present'",
            $st->id,
            $subject
        ));

        $percent = round(($present / $total) * 100, 2);

        // ❌ Only deficient students (<80%)
        $is_deficient = ($percent < 80);

        // show only deficient students
        if (!$is_deficient) {
            continue;
        }

        echo '<tr>';
        echo '<td>' . esc_html($st->roll) . '</td>';
        echo '<td>' . esc_html($st->name) . '</td>';
        echo '<td>' . esc_html($percent) . '%</td>';
        echo '<td style="color:red;font-weight:bold;">DEFICIENT</td>';

        /* ========= EMAIL BUTTON ========= */
        echo '<td>';
        if ($is_deficient && !empty($st->email)) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline;">
                <input type="hidden" name="action" value="cdat_send_def_email">
                <input type="hidden" name="email" value="' . esc_attr($st->email) . '">
                <input type="hidden" name="name" value="' . esc_attr($st->name) . '">
                ' . wp_nonce_field('cdat_def_mail', '_wpnonce', true, false) . '
                <button class="button">📧 Email</button>
            </form>';
        } else {
            echo '-';
        }
        echo '</td>';

        /* ========= WHATSAPP BUTTON ========= */
        echo '<td>';
        if ($is_deficient) // && !empty($st->mobile))
        {
            $msg = rawurlencode(
                "Dear {$st->name}, your attendance for Subject {$subject} is {$percent}%. "
                    . "Minimum required is 80%. Please improve your attendance."
            );

            echo '<a class="button button-primary" target="_blank"
                href="https://wa.me/' . $st->mobile . '?text=' . $msg . '">📲 WhatsApp</a>';
        } else {
            echo '-';
        }
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}
add_action('admin_post_cdat_send_def_email', function () {

    if (!wp_verify_nonce($_POST['_wpnonce'], 'cdat_def_mail')) {
        wp_die('Security check failed');
    }

    $to = sanitize_email($_POST['email']);
    $name = sanitize_text_field($_POST['name']);

    wp_mail(
        $to,
        'Attendance Deficiency Alert',
        "Dear $name,\n\nYour attendance is below 80%. Please attend classes regularly.\n\n– CDAT"
    );

    wp_safe_redirect(wp_get_referer());
    exit;
});
