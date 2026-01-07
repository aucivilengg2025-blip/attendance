<?php
/**
 * Plugin Name: CDAT18122025 
 * Description: Modified Attendance for Civil Engg Dept 
 * Version: 5.0 
 * Author: Yazh..
 */

if (!defined('ABSPATH')) exit;

global $cdat_db_version;
$cdat_db_version = '1.1';

/* ---------------- Activation / Deactivation ---------------- */
register_activation_hook(__FILE__, 'cdat_activate');
function cdat_activate() {
    global $wpdb, $cdat_db_version;
    $charset_collate = $wpdb->get_charset_collate();

    $students_table = $wpdb->prefix . 'cdat_students';
    $staff_table    = $wpdb->prefix . 'cdat_staff';
    $subjects_table = $wpdb->prefix . 'cdat_subjects';
    $timetable_table= $wpdb->prefix . 'cdat_timetable';
    $attendance_table = $wpdb->prefix . 'cdat_attendance';

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $sql = "CREATE TABLE $students_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        roll varchar(50) NOT NULL,
        name varchar(200) NOT NULL,
        semester tinyint(2) NOT NULL,
// 		mobile varchar(200) DEFAULT '9999999999',
        PRIMARY KEY  (id),
        UNIQUE KEY roll (roll)
    ) $charset_collate;";
    dbDelta($sql);

    $sql = "CREATE TABLE $staff_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(200) NOT NULL,
        email varchar(150) DEFAULT '',
// 		mobile varchar (10) DEFAULT '9999999999',
        user_id bigint(20) unsigned DEFAULT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);

    $sql = "CREATE TABLE $subjects_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        semester tinyint(2) NOT NULL,
        code varchar(50) NOT NULL,
        title varchar(255) NOT NULL,
//         email varchar(150) DEFAULT '',
		staff_id bigint(20) unsigned DEFAULT NULL,		
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);

    $sql = "CREATE TABLE $timetable_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        semester tinyint(2) NOT NULL,
        day varchar(20) NOT NULL,
        slot varchar(50) NOT NULL,
        subject_id bigint(20) unsigned NOT NULL,
// 		period varchar(1) NOT NULL,
// 		room varchar(5) NOT NULL,
		PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);

	// 	$sql = "ALTER TABLE $timetable_table ADD UNIQUE KEY uniq_slot (semester, day, period);"
	// 	dbDelta($sql);
	 	
	 	
    $sql = "CREATE TABLE $attendance_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        student_id bigint(20) unsigned NOT NULL,
        subject_id bigint(20) unsigned NOT NULL,
        date date NOT NULL,
        status enum('present','absent') NOT NULL DEFAULT 'present',
        recorded_by bigint(20) unsigned DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY student_id (student_id),
        KEY subject_id (subject_id),
        KEY date (date)
    ) $charset_collate;";
    dbDelta($sql);
	// seed default staff (12) and subjects (8 sem * 6 subjects)
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $staff_table");
    if ($count == 0) {
        $default_staff = array(
            'Dr. A. Kumar','Dr. B. Singh','Prof. C. Ramesh','Prof. D. Meena',
            'Dr. E. Suresh','Prof. F. Varma','Dr. G. Natarajan','Prof. H. Priya',
            'Dr. I. Raj','Prof. J. Kannan','Dr. K. Leela','Prof. L. Mohan'
        );
        foreach ($default_staff as $name) {
            $wpdb->insert($staff_table, array('name'=>$name));
        }
    }

    $count = $wpdb->get_var("SELECT COUNT(*) FROM $subjects_table");
    if ($count == 0) {
        for ($sem=1;$sem<=8;$sem++){
            for ($s=1;$s<=6;$s++){
                $code = sprintf('CE%02d%02d',$sem,$s);
                $title = "Semester $sem - Subject $s";
                $staff_id = (($s + $sem) % 12) + 1;
                $wpdb->insert($subjects_table, array('semester'=>$sem,'code'=>$code,'title'=>$title,'staff_id'=>$staff_id));
            }
        }
    }

    // Create teacher role and capability
    add_role('cdat_teacher', 'CDAT Teacher', array('read' => true));
    $role = get_role('administrator');
    if ($role) $role->add_cap('manage_cdat');
    $trole = get_role('cdat_teacher');
    if ($trole) $trole->add_cap('manage_cdat');

    add_option('cdat_db_version', $cdat_db_version);
}

register_deactivation_hook(__FILE__, 'cdat_deactivate');
function cdat_deactivate(){
    $role = get_role('administrator');
    if ($role) $role->remove_cap('manage_cdat');
    remove_role('cdat_teacher');
}

function login_url(){
return "https://tnedunet.in"; // Your URL Here
}
add_filter('login_headerurl', 'login_url');


/* ---------------- Admin Menu ---------------- */
add_action('admin_menu','cdat_admin_menu');
function cdat_admin_menu(){
    $cap = 'manage_cdat';
    add_menu_page('Civil Attendance','Civil Attendance',$cap,'cdat_dashboard','cdat_dashboard_page','dashicons-groups',30);
    $user = wp_get_current_user();
    //echo $user->user_email;
	if ($user->user_login == 'admin')
	{ 
		add_submenu_page('cdat_dashboard','Students','Students',$cap,'cdat_students','cdat_students_page');
		add_submenu_page('cdat_dashboard','Staff','Staff',$cap,'cdat_staff','cdat_staff_page');
		add_submenu_page('cdat_dashboard','Subjects','Subjects',$cap,'cdat_subjects','cdat_subjects_page');
		add_submenu_page('cdat_dashboard','TimetableChange','TimetableChange',$cap,'cdat_TTC','cdat_timetable_matrix_page');
		add_submenu_page('cdat_dashboard','VisualDashboard','VisualDashBoard',$cap,'cdat_attendance_dashboard_page','cdat_attendance_dashboard_page');
		
	}	
	add_submenu_page('cdat_dashboard','Attendance','+Take Attendance',$cap,'cdat_attendance','cdat_attendance_page');
    add_submenu_page('cdat_dashboard','Reports','Reports',$cap,'cdat_reports','cdat_reports_page');
	add_submenu_page('cdat_dashboard','SubjectAttendance','SubjectAttendance',$cap,'cdat_attendance_matrix_page','cdat_attendance_matrix_page');
	add_submenu_page('cdat_dashboard','Timetable','Timetable',$cap,'cdat_timetable','cdat_timetable_page');
    add_submenu_page('cdat_dashboard','ClassAttendance','ClassAttendance',$cap,'cdat_class_attendance','cdat_class_attendance_page');
	add_submenu_page('cdat_dashboard','StudentDeficiency','StudentDeficiency',$cap, 'cdat_student_deficiency_report', 'cdat_student_deficiency_report'); 
    add_submenu_page('cdat_dashboard','SubjectTopics','+SubjectTopics',$cap, 'cdat_bulk_update_attendance_topic_page', 'cdat_bulk_update_attendance_topic_page'); 
  	add_submenu_page('cdat_dashboard','DeficiencyWhatsapp','DeficiencyWhatsApp',$cap, 'cdat_student_deficiency_report1', 'cdat_student_deficiency_report1'); 
	
	if ($user->user_login == 'nmanikumariau@gmail.com') {
		add_submenu_page('cdat_dashboard','VisualDashboard','VisualDashBoard',$cap,'cdat_attendance_dashboard_page','cdat_attendance_dashboard_page');
		add_submenu_page('cdat_dashboard','DeficiencyWhatsapp','DeficiencyWhatsApp',$cap, 'cdat_student_deficiency_report1', 'cdat_student_deficiency_report1'); 
     	add_submenu_page('cdat_dashboard','edittable','editTable',$cap,'cdat_edit_table','edit_update_table');
 		add_submenu_page('cdat_dashboard','TimetableChange','TimetableChange',$cap,'cdat_TTC','cdat_timetable_matrix_page');
	}
	if ($user->user_login == 'sivacdm67@gmail.com') {
		add_submenu_page('cdat_dashboard','VisualDashboard','VisualDashBoard',$cap,'cdat_attendance_dashboard_page','cdat_attendance_dashboard_page');
//      add_submenu_page('cdat_dashboard','DeficiencyWhatsapp','DeficiencyWhatsApp',$cap, 'cdat_student_deficiency_report1', 'cdat_student_deficiency_report1'); 
//      add_submenu_page('cdat_dashboard','TimetableChange','TimetableChange',$cap,'cdat_TTC','cdat_timetable_matrix_page');
	}  
	if ($user->user_login == 'rmkps6101969@gmail.com') {	
//		add_submenu_page('cdat_dashboard','TimetableChange','TimetableChange',$cap,'cdat_TTC','cdat_timetable_matrix_page');
	}
		
}
/* ---------------- Small Admin Styles ---------------- */
function cdat_admin_header(){
    echo '<style>
    .cdat-wrap{font-family:Inter,Arial,Helvetica,sans-serif;padding:20px;background:#f4f7fb}
    .cdat-box{background:#fff;border:1px solid #e6eef8;padding:18px;margin-bottom:15px;border-radius:8px;box-shadow:0 1px 3px rgba(10,20,40,0.03)}
    .cdat-table{width:100%;border-collapse:collapse;margin-top:10px}
    .cdat-table th,.cdat-table td{padding:10px;border-bottom:1px solid #f1f6fb;text-align:left}
    .cdat-actions{margin-top:12px}
    input[type=text],select,input[type=date],input[type=email]{padding:8px;width:100%;box-sizing:border-box;border:1px solid #dfeaf6;border-radius:6px}
    .btn{display:inline-block;padding:8px 12px;border-radius:8px;border:1px solid #0073aa;background:#0073aa;color:#fff;text-decoration:none}
    .btn-secondary{background:#6c757d;border-color:#606f7b}
    .btn-danger{background:#c9302c;border-color:#b02a2a}
    .flex{display:flex;gap:10px}
    .preset-btn{background:#eef6ff;border:1px solid #d0e6ff;color:#0366d6;padding:6px 8px;border-radius:6px;cursor:pointer}
    </style>';
}

/* ---------------- Dashboard ---------------- */
function cdat_dashboard_page(){
    global $wpdb;
    cdat_admin_header();
    $students_table = $wpdb->prefix . 'cdat_students';
    $staff_table    = $wpdb->prefix . 'cdat_staff';
    $subjects_table = $wpdb->prefix . 'cdat_subjects';
    $attendance_table = $wpdb->prefix . 'cdat_attendance';
    $total_students = $wpdb->get_var("SELECT COUNT(*) FROM $students_table");
    $total_staff = $wpdb->get_var("SELECT COUNT(*) FROM $staff_table");
    $total_subjects = $wpdb->get_var("SELECT COUNT(*) FROM $subjects_table");
    $today = date('Y-m-d');
    $today_att = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $attendance_table WHERE date=%s", $today));
    ?>
    <div class="cdat-wrap">
        <h1>Civil-Dashboard</h1>
        <div class="cdat-box">
            <div class="flex">
                <div style="flex:1"><strong>Students:</strong> <?php echo intval($total_students); ?></div>
                <div style="flex:1"><strong>Staff:</strong> <?php echo intval($total_staff); ?></div>
                <div style="flex:1"><strong>Subjects:</strong> <?php echo intval($total_subjects); ?></div>
                <div style="flex:1"><strong>Today records:</strong> <?php echo intval($today_att); ?></div>
            </div>
        </div>
		    <style>
        .cdat-box {
                background: #b9e225; 
/* 			    background: #2271b1; */
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            }
            /* Buttons container behavior */
        .cdat-box {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            /* Button styling */
        .cdat-box .btn {
                display: block;
                text-align: center;
                padding: 12px 16px;
                background: #2271b1; /* WP admin blue */
                color: #fff;
                text-decoration: none;
                border-radius: 6px;
                font-size: 15px;
                transition: all 0.2s ease;
            }

        .cdat-box .btn:hover {
                background: #135e96;
            }

            /* 🔹 Tablet & above: show buttons in grid */
        @media (min-width: 600px) {
                .cdat-box {
                    flex-direction: row;
                    flex-wrap: wrap;
                }

        .cdat-box h2 {
                    flex: 0 0 100%;
                }

        .cdat-box .btn {
                    flex: 1 1 calc(50% - 12px);
                }
            }

            /* 🔹 Desktop: 4 columns */
        @media (min-width: 1024px) {
                .cdat-box .btn {
                    flex: 1 1 calc(33.00% - 12px);
                }
            }
    </style>
        <div class="cdat-box">
            <h2>Quick actions</h2>
            <a class="btn" href="admin.php?page=cdat_students">Manage Students</a>
            <a class="btn" href="admin.php?page=cdat_subjects">Manage Subjects</a>
            <a class="btn" href="admin.php?page=cdat_staff">Manage Staff</a>
            <a class="btn" href="admin.php?page=cdat_attendance">Take Attendance</a>
            <a class="btn" href="admin.php?page=cdat_reports">Reports</a>
        </div>
    </div>
    <?php
}
/* ---------------- Students (Add / CSV Import / List) ---------------- */
function cdat_students_page(){
    global $wpdb;
    cdat_admin_header();
    $table = $wpdb->prefix . 'cdat_students';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cdat_action'])){
        check_admin_referer('cdat_students_action');
        $action = sanitize_text_field($_POST['cdat_action']);
        if ($action === 'add'){
            $roll = sanitize_text_field($_POST['roll']);
            $name = sanitize_text_field($_POST['name']);
            $sem = intval($_POST['semester']);
            if ($roll && $name && $sem){
                $wpdb->insert($table,array('roll'=>$roll,'name'=>$name,'semester'=>$sem));
            }
        } elseif ($action === 'delete' && !empty($_POST['id'])){
             $wpdb->delete($table,array('id'=>intval($_POST['id'])));
        } elseif ($action === 'import' && !empty($_FILES['csv_file']['tmp_name'])){
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file,'r');
            if ($handle){
                $row = 0; $inserted = 0; $skipped = 0;
                while(($data = fgetcsv($handle, 2000, ',')) !== FALSE){
                    $row++;
                    if ($row==1){
                        $lower = array_map('strtolower',$data);
                        $hasHeader = (in_array('roll',$lower) || in_array('name',$lower));
                        if ($hasHeader) continue;
                    }
                    $roll = isset($data[0])? sanitize_text_field($data[0]) : '';
                    $name = isset($data[1])? sanitize_text_field($data[1]) : '';
                    $sem = isset($data[2])? intval($data[2]) : 1;
                    if ($roll && $name){
                        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE roll=%s LIMIT 1", $roll));
                        if (!$exists){ $wpdb->insert($table,array('roll'=>$roll,'name'=>$name,'semester'=>$sem)); $inserted++; } else { $skipped++; }
                    }
                }
                fclose($handle);
                echo '<div class="cdat-box"><strong>Import completed:</strong> Inserted '.intval($inserted).', Skipped '.intval($skipped).' (duplicates)</div>';
            }
        }
    }
    $students = $wpdb->get_results("SELECT * FROM $table ORDER BY semester,roll");
    ?>
    <div class="cdat-wrap">
        <h1>Students</h1>
        <div class="cdat-box">
            <h3>Add Student</h3>
            <form method="post">
                <?php wp_nonce_field('cdat_students_action'); ?>
                <input type="hidden" name="cdat_action" value="add">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div><label>Roll</label><input type="text" name="roll" required></div>
                    <div><label>Name</label><input type="text" name="name" required></div>
                    <div><label>Semester (1-8)</label><input type="text" name="semester" value="1" required></div>
                    <div style="align-self:end"><button class="btn" type="submit">Add</button></div>
                </div>
            </form>
        </div>
        <div class="cdat-box">
            <h3>Bulk Import (CSV)</h3>
            <p>CSV format: <code>roll,name,semester</code> — header row optional.</p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('cdat_students_action'); ?>
                <input type="hidden" name="cdat_action" value="import">
                <div style="display:grid;grid-template-columns:2fr 1fr;gap:10px;align-items:center">
                    <input type="file" name="csv_file" accept=".csv" required>
                    <div><button class="btn" type="submit">Upload CSV</button></div>
                </div>
            </form>
        </div>

        <div class="cdat-box">
            <h3>Existing Students</h3>
            <table class="cdat-table">
                <thead><tr><th>ID</th><th>Roll</th><th>Name</th><th>Sem</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach($students as $s){ ?>
                    <tr>
                        <td><?php echo intval($s->id); ?></td>
                        <td><?php echo esc_html($s->roll); ?></td>
                        <td><?php echo esc_html($s->name); ?></td>
                        <td><?php echo intval($s->semester); ?></td>
                        <td>
                            <form method="post" style="display:inline">
                                <?php wp_nonce_field('cdat_students_action'); ?>
                                <input type="hidden" name="cdat_action" value="delete">
                                <input type="hidden" name="id" value="<?php echo intval($s->id); ?>">
                                 <!-- <button class="btn btn-danger" type="submit">Delete</button> -->
                            </form>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
/* ---------------- Staff (add, map to WP user) ---------------- */
function cdat_staff_page(){
    global $wpdb;
    cdat_admin_header();
    $table = $wpdb->prefix . 'cdat_staff';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cdat_action'])){
        check_admin_referer('cdat_staff_action');
        $action = sanitize_text_field($_POST['cdat_action']);
        if ($action === 'add'){
            $name = sanitize_text_field($_POST['name']);
            $email = sanitize_email($_POST['email']);
            $user_id = intval($_POST['user_id']);
            if ($name) $wpdb->insert($table,array('name'=>$name,'email'=>$email,'user_id'=>$user_id));
        } elseif ($action === 'delete' && !empty($_POST['id'])){
            $wpdb->delete($table,array('id'=>intval($_POST['id'])));
        }
    }
    $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY name");
    $users = get_users(array('role__in'=>array('administrator','editor','author','cdat_teacher')));
    ?>
    <div class="cdat-wrap">
        <h1>Staff</h1>
        <div class="cdat-box">
            <h3>Add Staff (map to WP user optionally)</h3>
            <form method="post">
                <?php wp_nonce_field('cdat_staff_action'); ?>
                <input type="hidden" name="cdat_action" value="add">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div><label>Name</label><input type="text" name="name" required></div>
                    <div><label>Email</label><input type="text" name="email"></div>
                    <div style="grid-column:1/3"><label>Map to WP user</label>
                        <select name="user_id">
                            <option value="">-- None --</option>
                            <?php foreach($users as $u) echo '<option value="'.intval($u->ID).'">'.esc_html($u->display_name).' ('.esc_html($u->user_email).')</option>'; ?>
                        </select>
                    </div>
                    <div style="align-self:end"><button class="btn" type="submit">Add</button></div>
                </div>
            </form>
        </div>

        <div class="cdat-box">
            <h3>Existing Staff</h3>
            <table class="cdat-table">
                <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>WP User</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach($rows as $r){ $wpuser = $r->user_id ? get_userdata($r->user_id) : null;?>
                    <tr>
                        <td><?php echo intval($r->id); ?></td>
                        <td><?php echo esc_html($r->name); ?></td>
                        <td><?php echo esc_html($r->email); ?></td>
                        <td><?php echo $wpuser ? esc_html($wpuser->display_name).' ('.esc_html($wpuser->user_email).')' : '-'; ?></td>
                        <td>
                            <form method="post" style="display:inline">
                                <?php wp_nonce_field('cdat_staff_action'); ?>
                                <input type="hidden" name="cdat_action" value="delete">
                                <input type="hidden" name="id" value="<?php echo intval($r->id); ?>">
<!--                                <button class="btn btn-danger" type="submit">Delete</button> -->
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
/* ---------------- Subjects ---------------- */
function cdat_subjects_page(){
    global $wpdb;
    cdat_admin_header();
    $table = $wpdb->prefix . 'cdat_subjects';
    $staff_table = $wpdb->prefix . 'cdat_staff';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cdat_action'])){
        check_admin_referer('cdat_subjects_action');
        $action = sanitize_text_field($_POST['cdat_action']);
        if ($action === 'add'){
            $sem = intval($_POST['semester']);
            $code = sanitize_text_field($_POST['code']);
            $title = sanitize_text_field($_POST['title']);
            $staff_id = intval($_POST['staff_id']);
            if ($code && $title) $wpdb->insert($table,array('semester'=>$sem,'code'=>$code,'title'=>$title,'staff_id'=>$staff_id));
        } elseif ($action==='delete' && !empty($_POST['id'])){
            $wpdb->delete($table,array('id'=>intval($_POST['id'])));
        }
    }
    $subjects = $wpdb->get_results("SELECT s.*,st.name as staff_name FROM $table s LEFT JOIN $staff_table st ON s.staff_id=st.id ORDER BY s.semester,s.code");
    $staffs = $wpdb->get_results("SELECT * FROM $staff_table ORDER BY name");
    ?>
    <div class="cdat-wrap">
        <h1>Subjects</h1>
        <div class="cdat-box">
            <h3>Add Subject</h3>
            <form method="post">
                <?php wp_nonce_field('cdat_subjects_action'); ?>
                <input type="hidden" name="cdat_action" value="add">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div><label>Semester</label><input type="text" name="semester" value="1" required></div>
                    <div><label>Code</label><input type="text" name="code" required></div>
                    <div style="grid-column:1/3"><label>Title</label><input type="text" name="title" required></div>
                    <div style="grid-column:1/3"><label>Staff</label>
                        <select name="staff_id">
                            <option value="">-- Select --</option>
                            <?php foreach($staffs as $sf){ echo '<option value="'.intval($sf->id).'">'.esc_html($sf->name).'</option>'; } ?>
                        </select>
                    </div>
                    <div style="align-self:end"><button class="btn" type="submit">Add Subject</button></div>
                </div>
            </form>
        </div>

        <div class="cdat-box">
            <h3>Existing Subjects</h3>
            <table class="cdat-table">
                <thead><tr><th>ID</th><th>Sem</th><th>Code</th><th>Title</th><th>Staff</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach($subjects as $s){ ?>
                    <tr>
                        <td><?php echo intval($s->id); ?></td>
                        <td><?php echo intval($s->semester); ?></td>
                        <td><?php echo esc_html($s->code); ?></td>
                        <td><?php echo esc_html($s->title); ?></td>
                        <td><?php echo esc_html($s->staff_name); ?></td>
                        <td>
                            <form method="post" style="display:inline">
                                <?php wp_nonce_field('cdat_subjects_action'); ?>
                                <input type="hidden" name="cdat_action" value="delete">
                                <input type="hidden" name="id" value="<?php echo intval($s->id); ?>">
                                <button class="btn btn-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

/* ---------------- Timetable -----------new----- */
function cdat_timetable_page1()
{
    global $wpdb;
    cdat_admin_header();
    $table = $wpdb->prefix . 'cdat_timetable';
    $subjects_table = $wpdb->prefix . 'cdat_subjects';
	
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cdat_action'])) {
        check_admin_referer('cdat_timetable_action');
        $action = sanitize_text_field($_POST['cdat_action']);
        if ($action === 'add') {
            $sem = intval($_POST['semester']);
            $day = sanitize_text_field($_POST['day']);
            $slot = sanitize_text_field($_POST['slot']);
            $subject_id = intval($_POST['subject_id']);
            if ($subject_id)
                $wpdb->insert($table, array('semester' => $sem, 'day' => $day, 'slot' => $slot, 'subject_id' => $subject_id));
        } elseif ($action === 'delete' && !empty($_POST['id'])) {
            $wpdb->delete($table, array('id' => intval($_POST['id'])));
        }
    }
    $timetable = $wpdb->get_results("SELECT t.*,s.code,s.title FROM $table t LEFT JOIN $subjects_table s ON t.subject_id=s.id ORDER BY t.semester,t.day,t.slot");
    $subjects = $wpdb->get_results("SELECT * FROM $subjects_table ORDER BY semester,code");
    $days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
    ?>
    <div class="cdat-wrap">
        <h1>Timetable</h1>
        <div class="cdat-box">
            <h3>Add Timetable Entry</h3>
            <form method="post" width="100%">
                <?php wp_nonce_field('cdat_timetable_action'); ?>
                <input type="hidden" name="cdat_action" value="add">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div><label>Semester</label><input type="text" name="semester" value="1" required></div>
                    <div><label>Day</label>
                        <select name="day">
                            <?php foreach ($days as $d)
                                echo '<option>' . esc_html($d) . '</option>'; ?>
                        </select>
                    </div>
                    <div><label>Slot (e.g. 9:00-10:00)</label><input type="text" name="slot" required></div>
                    <div><label>Subject</label>
                        <select name="subject_id">
                            <option value="">-- Select --</option>
                            <?php foreach ($subjects as $sub)
                                echo '<option value="' . intval($sub->id) . '">Sem ' . intval($sub->semester) . ' - ' . esc_html($sub->code) . ' ' . esc_html($sub->title) . '</option>'; ?>
                        </select>
                    </div>
					     <div style="align-self:end"><button class="btn" type="submit">Add to Timetable</button></div> 
                </div>
            </form>
        </div>
        <div class="cdat-box">
            <details class="cdat-details">
                <summary class="cdat-summary">📘 Timetable Entries</summary>
                <div class="cdat-box">
                    <table class="cdat-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Sem</th>
                                <th>Day</th>
                                <th>Slot</th>
                                <th>Subject</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timetable as $t) { ?>
                                <tr>
                                    <td><?php echo intval($t->id); ?></td>
                                    <td><?php echo intval($t->semester); ?></td>
                                    <td><?php echo esc_html($t->day); ?></td>
                                    <td><?php echo esc_html($t->slot); ?></td>
                                    <td><?php echo esc_html($t->code . ' - ' . $t->title); ?></td>
                                    <td>
                                        <form method="post" style="display:inline">
                                            <?php wp_nonce_field('cdat_timetable_action'); ?>
                                            <input type="hidden" name="cdat_action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo intval($t->id); ?>">
<!--                                              <button class="btn btn-danger" type="submit">Delete</button>  -->
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
        <div>
            <style>
                /* Column 2 (P1) */
                table.cdat-timetable td:nth-child(2),
                table.cdat-timetable th:nth-child(2) {
                    background: #e6f7ff;
                    /* light blue */
                }

                /* Column 4 (P3) */
                table.cdat-timetable td:nth-child(4),
                table.cdat-timetable th:nth-child(4) {
                    background: #fff9cc;
                    /* light yellow */
                }

                /* Column 6 (P5) */
                table.cdat-timetable td:nth-child(6),
                table.cdat-timetable th:nth-child(6) {
                    background: #eaffea;
                    /* light green */
                }

                /* Optional: Improve table look */
                table.cdat-timetable td,
                table.cdat-timetable th {
                    padding: 10px;
                    font-size: 14px;
                    border: 1px solid #ccc;
                }

                table.cdat-timetable tbody tr:nth-child(even) {
                    background: #fafafa;
                }
            </style>
            <details style="margin:15px 0; border:1px solid #ddd; padding:10px; border-radius:6px;">
        		<summary style="font-size:18px; font-weight:bold; cursor:pointer; padding:6px;">
            		Staff Time Table
        		</summary>
						<?php
						// ============================================
						// STAFF TIMETABLE (Based on logged-in email)
						// ============================================

						// Get current staff email
						$current_user = wp_get_current_user();
						$staff_email = $current_user->user_email;
						$staff_email1 = $current_user->user_login;
						// Fetch staff subjects (joined via email)
	                    $staff_subjects = $wpdb->get_results("SELECT id, code, title FROM $subjects_table WHERE email = '$staff_email'");
					    //print_r($staff_subjects);
						if ($staff_subjects) {
							// Convert subject list → subject_id array
							$staff_subject_ids = wp_list_pluck($staff_subjects, 'id');

							// Fetch timetable entries that belong to these subjects
							$placeholders = implode(',', array_fill(0, count($staff_subject_ids), '%d'));
							//echo $placeholders;
							$staff_timetable = $wpdb->get_results(
								$wpdb->prepare(
									"SELECT t.*, s.code, s.title 
												FROM $table t
												LEFT JOIN $subjects_table s ON t.subject_id = s.id
												WHERE t.subject_id IN ($placeholders)
												ORDER BY FIELD(day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),
														period",
									$staff_subject_ids
								)
							);

							// Build matrix day → period → subject
							$matrix = [];
							foreach ($staff_timetable as $r) {
								$matrix[$r->day][$r->period] = $r;
							}

							// Days and periods
							$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
							$periods = [1, 2, 3, 4, 5, 6, 7]; // modify if needed
							?>
							<div class="cdat-semester-block">
								<h2 class="cdat-print-title">
									Staff Timetable — <?php echo esc_html($current_user->display_name); ?>
								</h2>

								<table class="cdat-print-table" border="1" cellpadding="8" cellspacing="1">
									<thead>
										<tr>
											<th>Day</th> 
											<th>P1(8.30-9.30)</th>
											<th>P2(9.31-10.30)</th>
											<th>P3(10.41-11.40)</th>
											<th>P4(11.41-12.40)</th>
											<th>P5(1.31-2.30)</th>
											<th>P6(2.31-3.30)</th>
											<th>P7(3.31-4.30)</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($days as $d): ?>
											<tr>
												<td style="font-weight:bold;"><?php echo $d; ?></td>

												<?php foreach ($periods as $p): ?>
													<?php if (isset($matrix[$d][$p])): ?>
														<td>
															<?php
															echo esc_html($matrix[$d][$p]->code) . "<br>";
															echo "<small>" . esc_html($matrix[$d][$p]->title) .  "</small>"."<br>";
                                                            echo "Hall: ";
															?>
														</td>
													<?php else: ?>
														<td>—</td>
													<?php endif; ?>
												<?php endforeach; ?>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>

							</div>

							<?php
						} else {
							echo '<p><strong>No timetable assigned for your subjects Work in Progress.....</strong></p>';
						}
						?>
          </details>
        </div>
		<?php
                global $wpdb;
                $timetable = $wpdb->prefix . "cdat_timetable";
                $subjects  = $wpdb->prefix . "cdat_subjects";
                $staffs    = $wpdb->prefix . "cdat_staff";

                $semesters = [2, 4, 6, 8];
                $daysOrder = "'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'";

                $periods = [
                    1 => '8.30-9.30',
                    2 => '9.31-10.30',
                    3 => '10.41-11.40',
                    4 => '11.41-12.40',
                    5 => '1.31-2.30',
                    6 => '2.31-3.30',
                    7 => '3.31-4.30'
                ];

                $hall = [4 => '2210', 6 => 'HL1', 8 => 'HL2'];

                /* ---------- STAFF CONFLICT DETECTION ---------- */
                $conflicts = $wpdb->get_results("
                    SELECT t.day, t.period, s.staff_id, COUNT(*) cnt
                    FROM $timetable t
                    JOIN $subjects s ON t.subject_id = s.id
                    GROUP BY t.day, t.period, s.staff_id
                    HAVING cnt > 1
                ");

                $conflict_map = [];
                foreach ($conflicts as $c) {
                    $conflict_map[$c->day][$c->period][$c->staff_id] = true;
                }

                /* ---------- HELPER: CELL RENDER ---------- */
                function cdat_render_cell($html, $day, $period, $conflict_map) {
                    if (!$html) return '';

                    preg_match('/data-staff="(\d+)"/', $html, $m);
                    $staff_id = $m[1] ?? null;

                    $conflict = $staff_id && isset($conflict_map[$day][$period][$staff_id]);

                    $style = $conflict
                        ? 'style="border:2px solid red; background:#ffecec;" title="⚠ Staff conflict detected"'
                        : '';

                    return "<div $style>$html</div>";
                }

                echo '<div class="wrap"><h1>📅 Semester-wise Timetable</h1>';

                /* ---------- TIMETABLE PER SEMESTER ---------- */
                foreach ($semesters as $sem):
                    ?>
                    <details style="margin:16px 0; border:1px solid #ccc; padding:10px; border-radius:6px;">
                        <summary style="font-size:18px; font-weight:bold; cursor:pointer;">
                            Semester <?= esc_html($sem) ?> Time Table
                            <span style="color:#1e73be; margin-left:10px;">
                                🏫 Hall No. <?= esc_html($hall[$sem] ?? '') ?>
                            </span>
                        </summary>

                        <table class="widefat striped" style="margin-top:12px; text-align:center;">
                            <thead style="background:#1e73be; color:#ffffff; font-weight:1000;">
                            <tr>
                                    <th style="text-align:center;color:#ffffff;font-weight:1000">Day</th>
                                    <?php foreach ($periods as $p => $time): ?>
                                        <th style="text-align:center;">
                                            <small style="color:#e6e6e6; font-weight:600;">
                                                P<?= esc_html($p) ?><br>(<?= esc_html($time) ?>)
                                            </small>                                         
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
							  <style>
                                    .staff { font-size:12px; font-weight:600; color:#1e73be; }
/*                                     details summary::-webkit-details-marker { display:none; } */
                              </style>
                            <tbody>
                            <?php
                            $sql = "
                                SELECT 
                                    t.day,
                                    MAX(CASE WHEN t.period=1 THEN CONCAT(s.title,'<br><span class=\"staff\" data-staff=\"',s.staff_id,'\">',IFNULL(st.name,'Not Assigned'),'</span>') END) p1,
                                    MAX(CASE WHEN t.period=2 THEN CONCAT(s.title,'<br><span class=\"staff\" data-staff=\"',s.staff_id,'\">',IFNULL(st.name,'Not Assigned'),'</span>') END) p2,
                                    MAX(CASE WHEN t.period=3 THEN CONCAT(s.title,'<br><span class=\"staff\" data-staff=\"',s.staff_id,'\">',IFNULL(st.name,'Not Assigned'),'</span>') END) p3,
                                    MAX(CASE WHEN t.period=4 THEN CONCAT(s.title,'<br><span class=\"staff\" data-staff=\"',s.staff_id,'\">',IFNULL(st.name,'Not Assigned'),'</span>') END) p4,
                                    MAX(CASE WHEN t.period=5 THEN CONCAT(s.title,'<br><span class=\"staff\" data-staff=\"',s.staff_id,'\">',IFNULL(st.name,'Not Assigned'),'</span>') END) p5,
                                    MAX(CASE WHEN t.period=6 THEN CONCAT(s.title,'<br><span class=\"staff\" data-staff=\"',s.staff_id,'\">',IFNULL(st.name,'Not Assigned'),'</span>') END) p6,
                                    MAX(CASE WHEN t.period=7 THEN CONCAT(s.title,'<br><span class=\"staff\" data-staff=\"',s.staff_id,'\">',IFNULL(st.name,'Not Assigned'),'</span>') END) p7
                                FROM $timetable t
                                LEFT JOIN $subjects s ON t.subject_id = s.id
                                LEFT JOIN $staffs st ON s.staff_id = st.id
                                WHERE t.semester = %d
                                GROUP BY t.day
                                ORDER BY FIELD(t.day,$daysOrder)
                            ";

                            $rows = $wpdb->get_results($wpdb->prepare($sql, $sem));

                            if ($rows):
                                foreach ($rows as $r):
                            ?>
                                <tr>
                                    <td><strong><?= esc_html($r->day) ?></strong></td>
                                    <td><?= wp_kses_post(cdat_render_cell($r->p1,$r->day,1,$conflict_map)) ?></td>
                                    <td><?= wp_kses_post(cdat_render_cell($r->p2,$r->day,2,$conflict_map)) ?></td>
                                    <td><?= wp_kses_post(cdat_render_cell($r->p3,$r->day,3,$conflict_map)) ?></td>
                                    <td><?= wp_kses_post(cdat_render_cell($r->p4,$r->day,4,$conflict_map)) ?></td>
                                    <td><?= wp_kses_post(cdat_render_cell($r->p5,$r->day,5,$conflict_map)) ?></td>
                                    <td><?= wp_kses_post(cdat_render_cell($r->p6,$r->day,6,$conflict_map)) ?></td>
                                    <td><?= wp_kses_post(cdat_render_cell($r->p7,$r->day,7,$conflict_map)) ?></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="8">😴 No timetable found</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </details>
                   <?php endforeach; ?>
<!-- 		            <php cdat_staff_workload(); ?> -->
	            					      
<?php
}

function cdat_staff_workload(){
	             global $wpdb;
                $timetable = $wpdb->prefix . "cdat_timetable";
                $subjects  = $wpdb->prefix . "cdat_subjects";
                $staffs    = $wpdb->prefix . "cdat_staff";      
	
                /* ---------- STAFF WORKLOAD SUMMARY ---------- */
                echo '<h2 style="margin-top:30px;">📊 Staff Workload Summary</h2>';

                $workload = $wpdb->get_results("
                    -- SELECT st.name, s.semester, COUNT(*) periods
                    -- FROM $timetable t
                    -- JOIN $subjects s ON t.subject_id = s.id
                    -- JOIN $staffs st ON s.staff_id = st.id
                    -- GROUP BY st.id, s.semester
                    -- ORDER BY st.name, s.semester
SELECT 
    st.id   AS staff_id,
    st.name AS staff_name,
    COUNT(*) AS total_periods, s.semester
FROM $timetable t
JOIN $subjects s ON t.subject_id = s.id
JOIN $staffs st   ON s.staff_id = st.id
GROUP BY st.id, st.name
ORDER BY s.semester, total_periods DESC;
                ");

                echo '<table class="widefat striped">';
                echo '<thead><tr><th>Staff</th><th>Semester</th><th>Periods / Week</th></tr></thead><tbody>';

                foreach ($workload as $w) {
                    // $color = $w->periods > 20 ? 'red' : '#1e73be';
                    $color = $w->total_periods > 6 ? 'red' : '#1e73be';
                    echo "<tr>
                        <td><strong>{$w->staff_name}</strong></td>
                        <td>{$w->semester}</td>
                        <td style='font-weight:bold;color:$color'>{$w->total_periods}</td>
                    </tr>";
                }
                echo '</tbody></table>';

                echo '</div>';

                /* ---------- CSS ---------- */
                ?>
                <style>
                    .staff { font-size:12px; font-weight:600; color:#1e73be; }
                    details summary::-webkit-details-marker { display:none; }
                </style>
<?php
}

function cdat_timetable_page()
{
    global $wpdb;
    cdat_admin_header();
    $table = $wpdb->prefix . 'cdat_timetable';
    $subjects_table = $wpdb->prefix . 'cdat_subjects';
	
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cdat_action'])) {
        check_admin_referer('cdat_timetable_action');
        $action = sanitize_text_field($_POST['cdat_action']);
        if ($action === 'add') {
            $sem = intval($_POST['semester']);
            $day = sanitize_text_field($_POST['day']);
            $slot = sanitize_text_field($_POST['slot']);
            $subject_id = intval($_POST['subject_id']);
            if ($subject_id)
                $wpdb->insert($table, array('semester' => $sem, 'day' => $day, 'slot' => $slot, 'subject_id' => $subject_id));
        } elseif ($action === 'delete' && !empty($_POST['id'])) {
            $wpdb->delete($table, array('id' => intval($_POST['id'])));
        }
    }
    $timetable = $wpdb->get_results("SELECT t.*,s.code,s.title FROM $table t LEFT JOIN $subjects_table s ON t.subject_id=s.id ORDER BY t.semester,t.day,t.slot");
    $subjects = $wpdb->get_results("SELECT * FROM $subjects_table ORDER BY semester,code");
    $days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
    ?>
    <div class="cdat-wrap">
        <h1>Timetable</h1>
        <div class="cdat-box">
            <h3>Add Timetable Entry</h3>
            <form method="post">
                <?php wp_nonce_field('cdat_timetable_action'); ?>
                <input type="hidden" name="cdat_action" value="add">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div><label>Semester</label><input type="text" name="semester" value="1" required></div>
                    <div><label>Day</label>
                        <select name="day">
                            <?php foreach ($days as $d)
                                echo '<option>' . esc_html($d) . '</option>'; ?>
                        </select>
                    </div>
                    <div><label>Slot (e.g. 9:00-10:00)</label><input type="text" name="slot" required></div>
                    <div><label>Subject</label>
                        <select name="subject_id">
                            <option value="">-- Select --</option>
                            <?php foreach ($subjects as $sub)
                                echo '<option value="' . intval($sub->id) . '">Sem ' . intval($sub->semester) . ' - ' . esc_html($sub->code) . ' ' . esc_html($sub->title) . '</option>'; ?>
                        </select>
                    </div>
                    <div style="align-self:end"><button class="btn" type="submit">Add to Timetable</button></div>
                </div>
            </form>
        </div>
        <div class="cdat-box">
            <details class="cdat-details">
                <summary class="cdat-summary">📘 Timetable Entries</summary>

                <div class="cdat-box">
                    <table class="cdat-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Sem</th>
                                <th>Day</th>
                                <th>Slot</th>
                                <th>Subject</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timetable as $t) { ?>
                                <tr>
                                    <td><?php echo intval($t->id); ?></td>
                                    <td><?php echo intval($t->semester); ?></td>
                                    <td><?php echo esc_html($t->day); ?></td>
                                    <td><?php echo esc_html($t->slot); ?></td>
                                    <td><?php echo esc_html($t->code . ' - ' . $t->title); ?></td>
                                    <td>
                                        <form method="post" style="display:inline">
                                            <?php wp_nonce_field('cdat_timetable_action'); ?>
                                            <input type="hidden" name="cdat_action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo intval($t->id); ?>">
<!--                                              <button class="btn btn-danger" type="submit">Delete</button>  -->
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
        <div>
            <style>
                /* Column 2 (P1) */
                table.cdat-timetable td:nth-child(2),
                table.cdat-timetable th:nth-child(2) {
                    background: #e6f7ff;
                    /* light blue */
                }

                /* Column 4 (P3) */
                table.cdat-timetable td:nth-child(4),
                table.cdat-timetable th:nth-child(4) {
                    background: #fff9cc;
                    /* light yellow */
                }

                /* Column 6 (P5) */
                table.cdat-timetable td:nth-child(6),
                table.cdat-timetable th:nth-child(6) {
                    background: #eaffea;
                    /* light green */
                }

                /* Optional: Improve table look */
                table.cdat-timetable td,
                table.cdat-timetable th {
                    padding: 10px;
                    font-size: 14px;
                    border: 1px solid #ccc;
                }

                table.cdat-timetable tbody tr:nth-child(even) {
                    background: #fafafa;
                }
            </style>
            <details style="margin:15px 0; border:1px solid #ddd; padding:10px; border-radius:6px;">
        		<summary style="font-size:18px; font-weight:bold; cursor:pointer; padding:6px;">
            		Staff Time Table
        		</summary>
						<?php
						// ============================================
						// STAFF TIMETABLE (Based on logged-in email)
						// ============================================

						// Get current staff email
						$current_user = wp_get_current_user();
						$staff_email = $current_user->user_email;
						$staff_email1 = $current_user->user_login;
						// Fetch staff subjects (joined via email)
	                    $staff_subjects = $wpdb->get_results("SELECT id, code, title FROM $subjects_table WHERE email = '$staff_email'");
					    //print_r($staff_subjects);
						if ($staff_subjects) {
							// Convert subject list → subject_id array
							$staff_subject_ids = wp_list_pluck($staff_subjects, 'id');

							// Fetch timetable entries that belong to these subjects
							$placeholders = implode(',', array_fill(0, count($staff_subject_ids), '%d'));
							//echo $placeholders;
							$staff_timetable = $wpdb->get_results(
								$wpdb->prepare(
									"SELECT t.*, s.code, s.title 
												FROM $table t
												LEFT JOIN $subjects_table s ON t.subject_id = s.id
												WHERE t.subject_id IN ($placeholders)
												ORDER BY FIELD(day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),
														period",
									$staff_subject_ids
								)
							);

							// Build matrix day → period → subject
							$matrix = [];
							foreach ($staff_timetable as $r) {
								$matrix[$r->day][$r->period] = $r;
							}

							// Days and periods
							$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
							$periods = [1, 2, 3, 4, 5, 6, 7]; // modify if needed
							?>
							<div class="cdat-semester-block">
								<h2 class="cdat-print-title">
									Staff Timetable — <?php echo esc_html($current_user->display_name); ?>
								</h2>

								<table class="cdat-print-table" border="1" cellpadding="8" cellspacing="1">
									<thead>
										<tr>
											<th>Day</th> 
											<th>P1(8.30-9.30)</th>
											<th>P2(9.31-10.30)</th>
											<th>P3(10.41-11.40)</th>
											<th>P4(11.41-12.40)</th>
											<th>P5(1.31-2.30)</th>
											<th>P6(2.31-3.30)</th>
											<th>P7(3.31-4.30)</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($days as $d): ?>
											<tr>
												<td style="font-weight:bold;"><?php echo $d; ?></td>

												<?php foreach ($periods as $p): ?>
													<?php if (isset($matrix[$d][$p])): ?>
														<td>
															<?php
															echo esc_html($matrix[$d][$p]->code) . "<br>";
															echo "<small>" . esc_html($matrix[$d][$p]->title) . "</small>";
															?>
														</td>
													<?php else: ?>
														<td>—</td>
													<?php endif; ?>
												<?php endforeach; ?>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>

							</div>

							<?php
						} else {
							echo '<p><strong>No timetable assigned for your subjects Work in Progress.....</strong></p>';
						}
						?>
          </details>
        </div>
	    <?php
        global $wpdb;

        $timetable = $wpdb->prefix . "cdat_timetable";
        $subjects = $wpdb->prefix . "cdat_subjects";

        $semesters = [2, 4, 6, 8];
        $daysOrder = "'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'";
        $periods = [
            1 => '8.30-9.30',
            2 => '9.31-10.30',
            3 => '10.41-11.40',
            4 => '11.41-12.40',
            5 => '1.31-2.30',
            6 => '2.31-3.30',
            7 => '3.31-4.30'
        ];

        foreach ($semesters as $sem):
            ?>
            <details style="margin:15px 0; border:1px solid #ddd; padding:10px; border-radius:6px;">
                <summary style="font-size:18px; font-weight:bold; cursor:pointer; padding:6px;">
                    <?= esc_html($sem) ?> Semester Time Table
                    <?php
                    $hall = [4 => '2210', 6 => 'HL1', 8 => 'HL2'];
                    ?>
                    <span style="color:#1e73be;">
                        Hall No. <?= esc_html($hall[$sem] ?? '') ?>
                    </span>
                </summary>

                <table class="cdat-timetable" border="1" cellpadding="8" cellspacing="1"
                    style="border-collapse:collapse; width:100%; text-align:center; margin-top:12px;">

                    <thead style="background:#f2f2f2; font-weight:bold;">
                        <tr>
                            <th>Day</th>
                            <?php foreach ($periods as $p => $time): ?>
                                <th>P<?= $p ?><br><small>(<?= $time ?>)</small></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
// 								$sql = "
// 							SELECT 
// 								t.day,
// 								MAX(CASE WHEN t.period = 1 THEN CONCAT(s.title, '||', s.staff_name, '||', s.id) END) AS p1,
// 								MAX(CASE WHEN t.period = 2 THEN CONCAT(s.title, '||', s.staff_name, '||', s.id) END) AS p2,
// 								MAX(CASE WHEN t.period = 3 THEN CONCAT(s.title, '||', s.staff_name, '||', s.id) END) AS p3,
// 								MAX(CASE WHEN t.period = 4 THEN CONCAT(s.title, '||', s.staff_name, '||', s.id) END) AS p4,
// 								MAX(CASE WHEN t.period = 5 THEN CONCAT(s.title, '||', s.staff_name, '||', s.id) END) AS p5,
// 								MAX(CASE WHEN t.period = 6 THEN CONCAT(s.title, '||', s.staff_name, '||', s.id) END) AS p6,
// 								MAX(CASE WHEN t.period = 7 THEN CONCAT(s.title, '||', s.staff_name, '||', s.id) END) AS p7
// 							FROM $timetable t
// 							LEFT JOIN $subjects s ON t.subject_id = s.id
// 							WHERE t.semester = %d
// 							GROUP BY t.day
// 							ORDER BY FIELD(t.day, $daysOrder)
// 							";

						$sql = "
							 SELECT 
								 t.day,
								 MAX(CASE WHEN t.period = 1 THEN s.title END) AS p1,
								 MAX(CASE WHEN t.period = 2 THEN s.title END) AS p2,
								 MAX(CASE WHEN t.period = 3 THEN s.title END) AS p3,
								 MAX(CASE WHEN t.period = 4 THEN s.title END) AS p4,
								 MAX(CASE WHEN t.period = 5 THEN s.title END) AS p5,
								 MAX(CASE WHEN t.period = 6 THEN s.title END) AS p6,
								 MAX(CASE WHEN t.period = 7 THEN s.title END) AS p7
							 FROM $timetable t
							 LEFT JOIN $subjects s ON t.subject_id = s.id
							 WHERE t.semester = %d
							 GROUP BY t.day
							 ORDER BY FIELD(t.day, $daysOrder)
						 ";

                        $rows = $wpdb->get_results($wpdb->prepare($sql, $sem));

                        if ($rows):
                            foreach ($rows as $r):
                                ?>
                               <tr>
									<td><strong><?= esc_html($r->day) ?></strong></td>
									<td><?= esc_html($r->p1 ?? '') ?></td>
									<td><?= esc_html($r->p2 ?? '') ?></td>
									<td><?= esc_html($r->p3 ?? '') ?></td>
									<td><?= esc_html($r->p4 ?? '') ?></td>
									<td><?= esc_html($r->p5 ?? '') ?></td>
									<td><?= esc_html($r->p6 ?? '') ?></td>
									<td><?= esc_html($r->p7 ?? '') ?></td>
						 	 </tr>
							<?php endforeach; else:?>
                            <tr>
                                <td colspan="8" style="color:#999;">
                                    😴 No timetable found for Semester <?= esc_html($sem) ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </details>
        <?php endforeach; ?>
 		<?php
			$user = wp_get_current_user();
			if ($user->user_login === 'admin') {
    			cdat_staff_workload();
			}  
		?>
	<?php
}

function cdat_render_cell($cell) {
    if (!$cell) return '';

    list($title, $staff, $sid) = array_pad(explode('||', $cell), 3, '');

    return "
        <div style='font-weight:600;'>".esc_html($title)."</div>
        <div style='font-size:12px; color:#1e73be;'>👨‍🏫 ".esc_html($staff)."</div>
        <div style='font-size:11px; color:#777;'>
            <a href='admin.php?page=cdat_subjects&subject_id=".intval($sid)."'
               style='color:#777; text-decoration:none;'>
               🆔 Subject ID: ".intval($sid)."
            </a>
        </div>
    ";
}


/* ---------------- Attendance (Admin & Teacher UI) ---------------- */
function cdat_attendance_page()
{
    if (!current_user_can('manage_cdat'))
        wp_die('You do not have permission to access this page.');
    global $wpdb;
    cdat_admin_header();
    $students_table = $wpdb->prefix . 'cdat_students';
    $subjects_table = $wpdb->prefix . 'cdat_subjects';
    $attendance_table = $wpdb->prefix . 'cdat_attendance';

    // handle attendance submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cdat_action'])) {
        check_admin_referer('cdat_attendance_action');
        $action = sanitize_text_field($_POST['cdat_action']);
        if ($action === 'save') {
            $subject_id = intval($_POST['subject_id']);
            $date = sanitize_text_field($_POST['date']);
            $present = isset($_POST['present']) ? array_map('intval', $_POST['present']) : array();
            // remove existing records for subject+date then insert
           $wpdb->query($wpdb->prepare("DELETE FROM $attendance_table WHERE subject_id=%d AND date=%s", $subject_id, $date));
           $students = $wpdb->get_results($wpdb->prepare("SELECT * FROM $students_table WHERE semester=%d ORDER BY roll", intval($_POST['semester'])));
            foreach ($students as $st) {
                $status = in_array(intval($st->id), $present) ? 'present' : 'absent';
                $wpdb->insert($attendance_table, array(
                    'student_id' => $st->id,
                    'subject_id' => $subject_id,
                    'date' => $date,
                    'status' => $status,
                    'recorded_by' => get_current_user_id()
                ));
            }
            echo '<div class="cdat-box"><strong>Attendance saved for ' . esc_html($date) . '</strong></div>';
        }
    }
    // selection form
    $semesters = range(4, 8, 2);
    $user = wp_get_current_user();
    //   echo $user->user_email;
    // 	 echo $user->user_login;  
    $subjects = $wpdb->get_results("SELECT * FROM $subjects_table ORDER BY semester,code");
    //    	   
    if ($user->user_email == $user->user_login)
        $subjects = $wpdb->get_results("SELECT * FROM $subjects_table where email = '$user->user_email'");
    //
    ?>
    <div class="cdat-wrap">
        <h1>Take Attendance</h1>
        <div class="cdat-box">
            <form method="post">
                <?php wp_nonce_field('cdat_attendance_action'); ?>
                <input type="hidden" name="cdat_action" value="select">
				<div>
<!-- 			         <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;align-items:end"> --> 
                  <div>
					  <label>Subject</label>
                       <select name="subject_id" id="cdat_subject">
                            <option value="">-- Select subject --</option>
                            <?php foreach ($subjects as $sub) {
                                echo '<option value="' . intval($sub->id) . '" data-sem="' . intval($sub->semester) . '">Sem ' . intval($sub->semester) . '- ' . esc_html($sub->code) . ' ' . esc_html($sub->title) . '</option>';
                            } ?>
                       </select>
                    </div>
                    <br><div><label>Semester</label>
                        <select name="semester" id="cdat_semester">
                            <?php foreach ($semesters as $s)
                                echo '<option value="' . $s . '">' . $s . '</option>'; ?>
                        </select>
					<p class="description cdat-warning" style="color: blue;">For Second Semester, you can't select here. Please go to Exclusive <b>Semester-2 Attendance</b> Side Panel.</p>
					<br>
                    </div>
                    <script>
                        document.addEventListener("DOMContentLoaded", function () {
                            const subjectDropdown = document.getElementById("cdat_subject");
                            const semesterDropdown = document.getElementById("cdat_semester");
                            subjectDropdown.addEventListener("change", function () {
                                const selected = subjectDropdown.options[subjectDropdown.selectedIndex];
                                const sem = selected.getAttribute("data-sem");

                                if (sem) {
                                    semesterDropdown.value = sem;
                                }
                            });
                        });
                    </script>
                    <div><label>Date</label><input type="date" name="date" value="<?php echo date('Y-m-d'); ?>"></div>
                </div>
                <div style="margin-top:10px;display:flex;gap:8px;align-items:center">
                    <button class="btn" type="button" onclick="cdatLoadStudents()">Load Students</button>
                    <!--<button class="preset-btn" type="button" onclick="setPreset('month')">This Month</button>
                    <button class="preset-btn" type="button" onclick="setPreset('last_month')">Last Month</button>
                    <button class="preset-btn" type="button" onclick="setPreset('semester')">Semester Range</button> -->
                </div>
            </form>
        </div>
        <div id="cdat_students_area"></div>
    </div>
    <script>
        function cdatLoadStudents() {
            var sem = document.getElementById('cdat_semester').value;
            var subj = document.getElementById('cdat_subject').value;
            var date = document.querySelector('input[name="date"]').value;
            if (!subj) { alert('Select subject'); return; }
            var data = new URLSearchParams();
            data.append('action', 'cdat_load_students');
            data.append('semester', sem);
            data.append('subject_id', subj);
            data.append('date', date);
            data.append('_wpnonce', '<?php echo wp_create_nonce('cdat_ajax_nonce'); ?>');
            fetch(ajaxurl, { method: 'POST', body: data }).then(r => r.text()).then(html => { document.getElementById('cdat_students_area').innerHTML = html; });
        }

        function cdatSubmitAttendance() {
            var form = document.getElementById('cdat_att_form');
            var data = new FormData(form);
            data.append('cdat_action', 'save');
            data.append('_wpnonce', '<?php echo wp_create_nonce('cdat_attendance_action'); ?>');
            fetch(location.href, { method: 'POST', body: data }).then(r => r.text()).then(txt => { location.reload(); });
        }

        function setPreset(p) {
            var today = new Date();
            var from, to;
            if (p === 'month') {
                from = new Date(today.getFullYear(), today.getMonth(), 1);
                to = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            } else if (p === 'last_month') {
                from = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                to = new Date(today.getFullYear(), today.getMonth(), 0);
            } else {
                var y = today.getFullYear();
                if (today.getMonth() < 6) { from = new Date(y, 0, 1); to = new Date(y, 5, 30); } else { from = new Date(y, 6, 1); to = new Date(y, 11, 31); }
            }
            var fromInput = document.querySelector('input[name="from"]');
            var toInput = document.querySelector('input[name="to"]');
            if (fromInput && toInput) {
                fromInput.value = from.toISOString().slice(0, 10);
                toInput.value = to.toISOString().slice(0, 10);
            } else {
                document.querySelector('input[name="date"]').value = to.toISOString().slice(0, 10);
            }
        }
    </script>
    <?php
}
/* ---------------- AJAX: load students for attendance ---------------- */
add_action('wp_ajax_cdat_load_students','cdat_ajax_load_students');
function cdat_ajax_load_students()
{
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'cdat_ajax_nonce'))
        wp_die('Nonce fail');
    global $wpdb;
    $sem = intval($_POST['semester']);
    $subject_id = intval($_POST['subject_id']);
    $date = sanitize_text_field($_POST['date']);
    $students_table = $wpdb->prefix . 'cdat_students';
    $attendance_table = $wpdb->prefix . 'cdat_attendance';
//     $students = $wpdb->get_results($wpdb->prepare("SELECT * FROM $students_table WHERE semester=%d ORDER BY name", $sem));
// Changes made for 2 sem 	
//	$students = $wpdb->get_results($wpdb->prepare("SELECT * FROM $students_table WHERE semester=%d order by roll", $sem));   
//  $existing = $wpdb->get_results($wpdb->prepare("SELECT student_id,status FROM $attendance_table WHERE subject_id=%d AND date=%s", $subject_id, $date), ARRAY_A);
// Addition for 2 sem Batch

// // ---- STUDENTS QUERY (compact) ----

	$user  = wp_get_current_user();
	$email = strtolower(trim($user->user_email));
	$batch = '';
	switch ($email) {
	case 'kmarvelb@gmail.com':
			$batch = 'A';
			break;
	case 'rmkps6101969@gmail.com':
			$batch = 'B';
			break;
	case 'damucivil75@gmail.com':
			$batch = 'C';
			break;
	case 'velayutham.au@gmail.com':
			$batch = 'D';
			break;
	case 'ezhisai_kb@yahoo.co.in':
			$batch = 'E';
			break;
	case 'jee.ezhiljodhi@gmail.com':
			$batch  = 'F';
				if (!empty($subject_id)) {
			    $subject = $wpdb->get_row(
		        $wpdb->prepare("SELECT title FROM {$wpdb->prefix}cdat_subjects WHERE id = %d",
            		$subject_id
        		)
    		);

       if ($subject) {

        // 🔁 Override batch if subject implies H batch
	        if (stripos($subject->title, 'H Batch') !== false) {
            $batch = 'H';
        }
    }
}
			break;
	case 'ashrasgo@rediffmail.com':
			$batch = 'G';
			break;
 	case 'srikrish.kkarthikeyan@gmail.com':
			$batch = 'I';
			break;
	case 'nnrajan.au@gmail.com':
			$batch = 'J';
			break;
	case 'raje69@yahoo.co.in':
			$batch = 'J';
			break;
	default:
			$batch = '';              // no batch assigned
	}
	
	
	
$sql  = "SELECT * FROM $students_table WHERE semester = %d";
$args = [$sem];
	
if ($sem == 2 && !empty($batch)) {
    $sql  .= " AND batch = %s";
    $args[] = $batch;
}

$sql .= " ORDER BY roll";
// $sql .= " ORDER BY name";
	
$students = $wpdb->get_results(
    $wpdb->prepare($sql, $args)
);

	// ---- EXISTING ATTENDANCE QUERY (compact) ----
$sql  = "SELECT a.student_id, a.status
         FROM $attendance_table a";
$args = [$subject_id, $date];


	
if ($semester == 2 && !empty($batch)) {

    $sql .= " INNER JOIN {$wpdb->prefix}cdat_students s
              ON s.id = a.student_id
              WHERE a.subject_id = %d
                AND a.date = %s
                AND s.batch = %s
                AND s.semester = %d";

    $args[] = $batch;
    $args[] = 2;

} else {

    $sql .= " WHERE a.subject_id = %d
              AND a.date = %s";
}

$existing = $wpdb->get_results(
    $wpdb->prepare($sql, $args),
    ARRAY_A
);

	
	$map = array();
    foreach ($existing as $e)
        $map[intval($e['student_id'])] = $e['status'];
    ob_start();
    ?>

    <div class="cdat-box">
        <h3>Students - Sem 
			<?php echo intval($sem); 
	          if ($sem == 2 && !empty($batch)) {echo '<span style="color:red;">  Batch: ' . esc_html($batch) . '</span>';}
	          if (!empty($date)) { echo " ".esc_html(date('d-m-Y', strtotime($date)));} 
			?>
		</h3>
        <style>
            .cdat-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                font-size: 14px;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            }

            .cdat-table thead {
                background: #4A90E2;
                color: #fff;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .cdat-table th,
            .cdat-table td {
                padding: 8px 10px;
                text-align: left;
                border-bottom: 1px solid #e1e1e1;
            }

            .cdat-table tbody tr:nth-child(odd) {
                background-color: #f7f9fc;
            }

            .cdat-table tbody tr:nth-child(even) {
                background-color: #eaf1fb;
            }

            .cdat-table tbody tr:hover {
                background-color: #d0e4ff;
                transition: 0.3s;
            }

            .cdat-table td input[type="checkbox"] {
                transform: scale(1.2);
                cursor: pointer;
            }

            .cdat-table th:first-child {
                border-top-left-radius: 10px;
            }

            .cdat-table th:last-child {
                border-top-right-radius: 10px;
            }

            .cdat-table tbody tr:last-child td:first-child {
                border-bottom-left-radius: 10px;
            }

            .cdat-table tbody tr:last-child td:last-child {
                border-bottom-right-radius: 10px;
            }
        </style>
        <form id="cdat_att_form">
            <input type="hidden" name="subject_id" value="<?php echo intval($subject_id); ?>">
            <input type="hidden" name="date" value="<?php echo esc_attr($date); ?>">
            <input type="hidden" name="semester" value="<?php echo intval($sem); ?>">
            <table class="cdat-table">
                <thead>
                    <tr>
                        <th>Roll</th>
                        <th>Name</th>
                        <th>Present</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $st) {
                        $checked = (isset($map[$st->id]) && $map[$st->id] === 'present') ? 'checked' : '';
                        $name = $st->name;
                        $color = preg_match('/^[A-Z ]+$/', $name) ? 'black' : 'green';
                        ?>
                        <tr>
                            <td><?php echo esc_html($st->roll); ?></td>
                            <td style="color: <?php echo $color; ?>; font-weight: bold;"><?php echo esc_html($name); ?></td>
                            <td><input type="checkbox" name="present[]" value="<?php echo intval($st->id); ?>" <?php echo $checked; ?>></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <div class="cdat-actions"><a href="javascript:void(0)" class="btn" onclick="cdatSubmitAttendance()">Save
                    Attendance</a></div>
        </form>
    </div>
    <?php
    $html = ob_get_clean();
    echo $html;
    wp_die();
}

function cdat_ajax_load_students1(){
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'],'cdat_ajax_nonce')) wp_die('Nonce fail');
    global $wpdb;
    $sem = intval($_POST['semester']);
    $subject_id = intval($_POST['subject_id']);
    $date = sanitize_text_field($_POST['date']);
    $students_table = $wpdb->prefix . 'cdat_students';
    $attendance_table = $wpdb->prefix . 'cdat_attendance';
    $students = $wpdb->get_results($wpdb->prepare("SELECT * FROM $students_table WHERE semester=%d ORDER BY roll", $sem));
    $existing = $wpdb->get_results($wpdb->prepare("SELECT student_id,status FROM $attendance_table WHERE subject_id=%d AND date=%s", $subject_id, $date), ARRAY_A);
    $map = array(); foreach($existing as $e) $map[intval($e['student_id'])] = $e['status'];

    ob_start();
    ?>
    <div class="cdat-box">
        <h3>Students - Sem <?php echo intval($sem); ?> (<?php echo esc_html($date); ?>)</h3>
        <form id="cdat_att_form">
            <input type="hidden" name="subject_id" value="<?php echo intval($subject_id); ?>">
            <input type="hidden" name="date" value="<?php echo esc_attr($date); ?>">
            <input type="hidden" name="semester" value="<?php echo intval($sem); ?>">
            <table class="cdat-table">
                <thead><tr><th>Roll</th><th>Name</th><th>Present</th></tr></thead>
                <tbody>
                <?php foreach($students as $st){ $checked = (isset($map[$st->id]) && $map[$st->id]==='present') ? 'checked' : ''; ?>
                    <tr>
                        <td><?php echo esc_html($st->roll); ?></td>
                        <td><?php echo esc_html($st->name); ?></td>
                        <td><input type="checkbox" name="present[]" value="<?php echo intval($st->id); ?>" <?php echo $checked; ?>></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
            <div class="cdat-actions"><a href="javascript:void(0)" class="btn" onclick="cdatSubmitAttendance()">Save Attendance</a></div>
        </form>
    </div>
    <?php
    $html = ob_get_clean();
    echo $html;
    wp_die();
}

/* ---------------- Reports (CSV | PDF) ---------------- */
function cdat_reports_page()
{
    if (isset($_POST['export_detailed_csv'])) {
        ce_export_detailed_report_csv();
    }
    if (!current_user_can('manage_cdat'))
        wp_die('You do not have permission to access this page.');
    global $wpdb;
    cdat_admin_header();
    $students_table = $wpdb->prefix . 'cdat_students';
    $subjects_table = $wpdb->prefix . 'cdat_subjects';
    $attendance_table = $wpdb->prefix . 'cdat_attendance';

    $results = array();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_action'])) {
        check_admin_referer('cdat_reports_action');
        $subject_id = intval($_POST['subject_id']);
        $from = sanitize_text_field($_POST['from']);
        $to = sanitize_text_field($_POST['to']);
        $students = $wpdb->get_results($wpdb->prepare("SELECT * FROM $students_table WHERE semester=%d ORDER BY roll", intval($_POST['semester'])));
        foreach ($students as $st) {
            $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $attendance_table WHERE student_id=%d AND subject_id=%d AND date BETWEEN %s AND %s", $st->id, $subject_id, $from, $to));
            $present = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $attendance_table WHERE student_id=%d AND subject_id=%d AND date BETWEEN %s AND %s AND status='present'", $st->id, $subject_id, $from, $to));
            $percent = ($total > 0) ? round(($present / $total) * 100, 2) : 0;
            $results[] = array('student' => $st, 'total' => $total, 'present' => $present, 'percent' => $percent);
        }

        // export CSV
        if (isset($_POST['export']) && $_POST['export'] === 'csv') {
            $filename = 'attendance_report_' . date('YmdHis') . '.csv';
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $out = fopen('php://output', 'w');
            fputcsv($out, array('Roll', 'Name', 'Total Sessions', 'Present Count', 'Percent'));
            foreach ($results as $r) {
                fputcsv($out, array($r['student']->roll, $r['student']->name, $r['total'], $r['present'], $r['percent']));
            }
            fclose($out);
            exit;
        }

        // export PDF (if mPDF library is available)
        if (isset($_POST['export']) && $_POST['export'] === 'pdf') {
            if (class_exists('\\Mpdf\\Mpdf') || class_exists('Mpdf\\Mpdf')) {
                $html = '<h2>Attendance Report</h2><table border="1" cellpadding="6" cellspacing="0" width="100%"><thead><tr><th>Roll</th><th>Name</th><th>Total</th><th>Present</th><th>Percent</th></tr></thead><tbody>';
                foreach ($results as $r) {
                    $html .= '<tr><td>' . esc_html($r['student']->roll) . '</td><td>' . esc_html($r['student']->name) . '</td><td>' . intval($r['total']) . '</td><td>' . intval($r['present']) . '</td><td>' . esc_html($r['percent']) . '%</td></tr>';
                }
                $html .= '</tbody></table>';
                $mpdf = new \Mpdf\Mpdf();
                $mpdf->WriteHTML($html);
                $mpdf->Output('attendance_report_' . date('YmdHis') . '.pdf', 'D');
                exit;
            } else {
                // fallback to printable HTML
                $pdfHtml = '<html><head><meta charset="utf-8"><title>Attendance Report</title></head><body>';
                $pdfHtml .= '<h2>Attendance Report</h2><table border="1" cellpadding="6" cellspacing="0" width="100%"><thead><tr><th>Roll</th><th>Name</th><th>Total</th><th>Present</th><th>Percent</th></tr></thead><tbody>';
                foreach ($results as $r) {
                    $pdfHtml .= '<tr><td>' . esc_html($r['student']->roll) . '</td><td>' . esc_html($r['student']->name) . '</td><td>' . intval($r['total']) . '</td><td>' . intval($r['present']) . '</td><td>' . esc_html($r['percent']) . '%</td></tr>';
                }
                $pdfHtml .= '</tbody></table><p>Use your browser Print → Save as PDF.</p></body></html>';
                echo $pdfHtml;
                exit;
            }
        }
    }
    $semesters = range(2, 8, 2);
    $user = wp_get_current_user();
    $subjects = $wpdb->get_results("SELECT * FROM $subjects_table ORDER BY semester,code");
    if ($user->user_email == $user->user_login)
        $subjects = $wpdb->get_results("SELECT * FROM $subjects_table where email = '$user->user_email'");
    ?>

    <div class="cdat-wrap">
        <h1>Reports</h1>
        <div class="cdat-box">
            <form method="post">
                <?php wp_nonce_field('cdat_reports_action'); ?>
                <input type="hidden" name="report_action" value="generate">
               <!-- <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">  -->
                <div>
                    <div><label>Subject</label>
                        <select name="subject_id" id="cdat_subject">
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo intval($s->id); ?>"
                                    data-semester="<?php echo intval($s->semester); ?>">
                                    Semester <?php echo intval($s->semester); ?> -
                                    <?php echo esc_html($s->code); ?>
                                    <?php echo esc_html($s->title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label>Semester</label><input type="text" id="cdat_semester" name="semester" value="1"></div>
                    <!-- <script>
                        document.getElementById('cdat_subject').addEventListener('change', function () {
                            var selected = this.options[this.selectedIndex];
                            var sem = selected.getAttribute('data-semester');
                            document.getElementById('cdat_semester').value = sem;
                        });
                    </script> -->
                    <script>
                        (function () {
                        // helper to safely find element and its selected option
                        function setSemesterFromSubject() {
                            var subj = document.getElementById('cdat_subject');
                            var semInput = document.getElementById('cdat_semester');
                            if (!subj || !semInput) return;

                            // get the selected option (if none, try option[0])
                            var opt = subj.options && subj.options.length
                            ? subj.options[subj.selectedIndex >= 0 ? subj.selectedIndex : 0]
                            : null;

                            if (!opt) return;

                            var sem = opt.getAttribute('data-semester') || opt.dataset && opt.dataset.semester || '';
                            sem = (sem || '').toString().trim();
                            if (sem !== '') semInput.value = sem;
                        }

                        // set on DOM ready
                        document.addEventListener('DOMContentLoaded', setSemesterFromSubject);

                        // set immediately in case script placed after the elements (fast path)
                        setSemesterFromSubject();

                        // update when user changes selection
                        var subjEl = document.getElementById('cdat_subject');
                        if (subjEl) subjEl.addEventListener('change', setSemesterFromSubject);
                        })();
                    </script>

                    <div><label>From</label><input type="date" name="from" value="<?php echo date('Y-m-01'); ?>"></div>
                    <div><label>To</label><input type="date" name="to" value="<?php echo date('Y-m-d'); ?>"></div>
                    <div style="grid-column:1/4;text-align:right">
                        <button class="btn" type="submit">Generate Report</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!empty($results)) { ?>
            <div class="cdat-box">
                <h3>Report Results</h3>
                <form method="post" style="margin-bottom:12px">
                    <?php wp_nonce_field('cdat_reports_action'); ?>
                    <input type="hidden" name="report_action" value="generate">
                    <input type="hidden" name="semester" value="<?php echo esc_attr($_POST['semester']); ?>">
                    <input type="hidden" name="subject_id" value="<?php echo esc_attr($_POST['subject_id']); ?>">
                    <input type="hidden" name="from" value="<?php echo esc_attr($_POST['from']); ?>">
                    <input type="hidden" name="to" value="<?php echo esc_attr($_POST['to']); ?>">
<!--                     <button class="btn" name="export" value="csv">Export CSV</button> -->
                    <button class="btn btn-secondary" name="export" value="pdf">Export PDF</button>
<!--                     <button name="export_detailed_csv" class="button button-secondary">Export Detailed CSV</button> -->
                </form>
<!-- 				<button onclick="window.print()" class="button"> 🖨 Print / Save as PDF </button>  -->
                    <table class="cdat-table">
                    <thead>
                        <tr>
                            <th>Roll</th>
                            <th>Name</th>
<!--                             <th>Total</th> -->
                            <th>Present</th>
                            <th>Percent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $r) { ?>
                            <tr>
                                <td><?php echo esc_html($r['student']->roll); ?></td>
                                    <?php 
                                        $name = $r['student']->name; 
                                         $color = preg_match('/^[A-Z ]+$/', $name) ? 'black' : 'green';
                                        ?>
                                        <td style="color: <?php echo $color; ?>">
                                            <?php echo esc_html($name); 
                                    ?>
                                </td>
<!--                                 <td><?php echo intval($r['total']); ?></td> -->
                                <td><?php echo intval($r['present']); ?></td>
                                <?php
                                    $percent = $r['percent'];
                                    $emoji = ($percent < 50) ? ' ⚠️' : '';?>
                                <td>
                                        <?php echo esc_html($percent) . ' %' . $emoji; ?>
                                </td>
<!--                                 <td><?php echo esc_html($r['percent']) . ' %'; ?></td> -->
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
				<p>
				<?php echo ' ⚠️' . ': Attendance lagging Alerts வருகை பற்றிய விழிப்பூட்டல்கள்';?>
				</p>
            </div>
        <?php } ?>
    </div>
    <?php
}

function cdat_reports_page1(){
	if (isset($_POST['export_detailed_csv'])) {
    	ce_export_detailed_report_csv();
	}
    if (!current_user_can('manage_cdat')) wp_die('You do not have permission to access this page.');
    global $wpdb; cdat_admin_header();
    $students_table = $wpdb->prefix . 'cdat_students';
    $subjects_table = $wpdb->prefix . 'cdat_subjects';
    $attendance_table = $wpdb->prefix . 'cdat_attendance';

    $results = array();
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['report_action'])){
        check_admin_referer('cdat_reports_action');
        $subject_id = intval($_POST['subject_id']);
        $from = sanitize_text_field($_POST['from']);
        $to = sanitize_text_field($_POST['to']);
        $students = $wpdb->get_results($wpdb->prepare("SELECT * FROM $students_table WHERE semester=%d ORDER BY roll", intval($_POST['semester'])));
        foreach($students as $st){
            $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $attendance_table WHERE student_id=%d AND subject_id=%d AND date BETWEEN %s AND %s", $st->id,$subject_id,$from,$to));
            $present = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $attendance_table WHERE student_id=%d AND subject_id=%d AND date BETWEEN %s AND %s AND status='present'", $st->id,$subject_id,$from,$to));
            $percent = ($total>0) ? round(($present/$total)*100,2) : 0;
            $results[] = array('student'=>$st,'total'=>$total,'present'=>$present,'percent'=>$percent);
        }

        // export CSV
        if (isset($_POST['export']) && $_POST['export']==='csv'){
            $filename = 'attendance_report_'.date('YmdHis').'.csv';
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="'.$filename.'"');
            $out = fopen('php://output','w');
            fputcsv($out, array('Roll','Name','Total Sessions','Present Count','Percent'));
            foreach($results as $r){ fputcsv($out, array($r['student']->roll,$r['student']->name,$r['total'],$r['present'],$r['percent'])); }
            fclose($out);
            exit;
        }

        // export PDF (if mPDF library is available)
        if (isset($_POST['export']) && $_POST['export']==='pdf'){
            if (class_exists('\\Mpdf\\Mpdf') || class_exists('Mpdf\\Mpdf') ){
                $html = '<h2>Attendance Report</h2><table border="1" cellpadding="6" cellspacing="0" width="100%"><thead><tr><th>Roll</th><th>Name</th><th>Total</th><th>Present</th><th>Percent</th></tr></thead><tbody>';
                foreach($results as $r){ $html .= '<tr><td>'.esc_html($r['student']->roll).'</td><td>'.esc_html($r['student']->name).'</td><td>'.intval($r['total']).'</td><td>'.intval($r['present']).'</td><td>'.esc_html($r['percent']).'%</td></tr>'; }
                $html .= '</tbody></table>';
                $mpdf = new \Mpdf\Mpdf();
                $mpdf->WriteHTML($html);
                $mpdf->Output('attendance_report_'.date('YmdHis').'.pdf','D');
                exit;
            } else {
                // fallback to printable HTML
                $pdfHtml = '<html><head><meta charset="utf-8"><title>Attendance Report</title></head><body>';
                $pdfHtml .= '<h2>Attendance Report</h2><table border="1" cellpadding="6" cellspacing="0" width="100%"><thead><tr><th>Roll</th><th>Name</th><th>Total</th><th>Present</th><th>Percent</th></tr></thead><tbody>';
                foreach($results as $r){ $pdfHtml .= '<tr><td>'.esc_html($r['student']->roll).'</td><td>'.esc_html($r['student']->name).'</td><td>'.intval($r['total']).'</td><td>'.intval($r['present']).'</td><td>'.esc_html($r['percent']).'%</td></tr>'; }
                $pdfHtml .= '</tbody></table><p>Use your browser Print → Save as PDF.</p></body></html>';
                echo $pdfHtml; exit;
            }
        }
    }
    $subjects = $wpdb->get_results("SELECT * FROM $subjects_table ORDER BY semester,code");
    ?>
    <div class="cdat-wrap">
        <h1>Reports</h1>
        <div class="cdat-box">
            <form method="post">
                <?php wp_nonce_field('cdat_reports_action'); ?>
                <input type="hidden" name="report_action" value="generate">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
                    <div><label>Semester</label><input type="text" name="semester" value="1"></div>
                    <div><label>Subject</label>
                        <select name="subject_id">
                            <?php foreach($subjects as $s) echo '<option value="'.intval($s->id).'">Sem '.intval($s->semester).' - '.esc_html($s->code).' '.esc_html($s->title).'</option>'; ?>
                        </select>
                    </div>
                    <div><label>From</label><input type="date" name="from" value="<?php echo date('Y-m-01'); ?>"></div>
                    <div><label>To</label><input type="date" name="to" value="<?php echo date('Y-m-d'); ?>"></div>
                    <div style="grid-column:1/4;text-align:right">
                        <button class="btn" type="submit">Generate Report</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if(!empty($results)){ ?>
            <div class="cdat-box">
                <h3>Report Results</h3>
                <form method="post" style="margin-bottom:12px">
                    <?php wp_nonce_field('cdat_reports_action'); ?>
                    <input type="hidden" name="report_action" value="generate">
                    <input type="hidden" name="semester" value="<?php echo esc_attr($_POST['semester']); ?>">
                    <input type="hidden" name="subject_id" value="<?php echo esc_attr($_POST['subject_id']); ?>">
                    <input type="hidden" name="from" value="<?php echo esc_attr($_POST['from']); ?>">
                    <input type="hidden" name="to" value="<?php echo esc_attr($_POST['to']); ?>">
                   <button class="btn" name="export" value="csv">Export CSV</button>
                    <button class="btn btn-secondary" name="export" value="pdf">Export PDF</button>
					<!-- <button name="export_detailed_csv" class="button button-secondary">Export Detailed CSV</button> -->
                </form>
                <table class="cdat-table">
                    <thead><tr><th>Roll</th><th>Name</th><th>Total</th><th>Present</th><th>Percent</th></tr></thead>
                    <tbody>
                    <?php foreach($results as $r){ ?>
                        <tr>
                            <td><?php echo esc_html($r['student']->roll); ?></td>
                            <td><?php echo esc_html($r['student']->name); ?></td>
                            <td><?php echo intval($r['total']); ?></td>
                            <td><?php echo intval($r['present']); ?></td>
                            <td><?php echo esc_html($r['percent']).' %'; ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
    <?php
}


function ce_export_detailed_report_csv() {
    global $wpdb;

    // Security check (only admins or teachers)
    if ( ! current_user_can('manage_options') && ! current_user_can('take_attendance') ) {
        wp_die(__('You do not have permission to export this report.'));
    }

    // Get filters (optional)
    $from = isset($_POST['from']) ? sanitize_text_field($_POST['from']) : '2000-01-01';
    $to   = isset($_POST['to']) ? sanitize_text_field($_POST['to']) : date('Y-m-d');

    // Teacher restriction — only their subjects
    if (current_user_can('take_attendance') && !current_user_can('manage_options')) {
        $current_user_id = get_current_user_id();
        $query = $wpdb->prepare("
            SELECT a.date, a.slot, s.roll_no, s.name AS student_name, sub.name AS subject_name, sub.semester,
                   st.name AS staff_name, a.status
            FROM {$wpdb->prefix}ce_attendance a
            JOIN {$wpdb->prefix}ce_students s ON a.student_id = s.id
            JOIN {$wpdb->prefix}ce_subjects sub ON a.subject_id = sub.id
            JOIN {$wpdb->prefix}ce_staff st ON sub.staff_id = st.id
            WHERE sub.user_id = %d AND a.date BETWEEN %s AND %s
            ORDER BY sub.semester, sub.name, s.roll_no, a.date, a.slot
        ", $current_user_id, $from, $to);
    } else {
        // Admin view — all subjects
        $query = $wpdb->prepare("
            SELECT a.date, a.slot, s.roll_no, s.name AS student_name, sub.name AS subject_name, sub.semester,
                   st.name AS staff_name, a.status
            FROM {$wpdb->prefix}ce_attendance a
            JOIN {$wpdb->prefix}ce_students s ON a.student_id = s.id
            JOIN {$wpdb->prefix}ce_subjects sub ON a.subject_id = sub.id
            JOIN {$wpdb->prefix}ce_staff st ON sub.staff_id = st.id
            WHERE a.date BETWEEN %s AND %s
            ORDER BY sub.semester, sub.name, s.roll_no, a.date, a.slot
        ", $from, $to);
    }

    $records = $wpdb->get_results($query);

    if (empty($records)) {
        echo '<div class="notice notice-warning"><p>No attendance records found for the given period.</p></div>';
        return;
    }

    // Output CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_detailed_report.csv"');
    $out = fopen('php://output', 'w');

    // CSV header row
    fputcsv($out, [
        'Semester', 'Subject', 'Date', 'Slot', 
        'Roll No', 'Student Name', 'Staff Name', 'Status'
    ]);

    // Write data rows
    foreach ($records as $r) {
        fputcsv($out, [
            $r->semester,
            $r->subject_name,
            $r->date,
            $r->slot ?: '-', // slot might be null
            $r->roll_no,
            $r->student_name,
            $r->staff_name,
            $r->status
        ]);
    }

    fclose($out);
    exit;
}
/* ---------------- Admin enqueue (placeholder) ---------------- */
add_action('admin_enqueue_scripts','cdat_admin_enqueue');
function cdat_admin_enqueue($hook){
    // no external scripts required for now
}

function cdat_attendance_matrix_page() {
    global $wpdb;

    $students_table   = $wpdb->prefix . 'cdat_students';
    $subjects_table   = $wpdb->prefix . 'cdat_subjects';
    $attendance_table = $wpdb->prefix . 'cdat_attendance';

    $results = [];
    $dates = [];

    // Fetch subjects for dropdown
    $subjects = $wpdb->get_results("SELECT * FROM $subjects_table ORDER BY semester, title");

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_matrix'])) {
        check_admin_referer('cdat_matrix_action');

        $subject_id = intval($_POST['subject_id']);
        $semester   = intval($_POST['semester']);
        $from       = sanitize_text_field($_POST['from']);
        $to         = sanitize_text_field($_POST['to']);
//       $to = $from;   

        // Get all students in the semester
        
        // $students = $wpdb->get_results($wpdb->prepare(
        //     "SELECT * FROM $students_table WHERE semester=%d ORDER BY roll",
        //     $semester
        // ));

 $user  = wp_get_current_user();
    $email = strtolower(trim($user->user_email));
    $batch = '';

    switch ($email) {
        case 'kmarvelb@gmail.com':            $batch = 'A'; break;
        case 'rmkps6101969@gmail.com':        $batch = 'B'; break;
        case 'damucivil75@gmail.com':         $batch = 'C'; break;
        case 'velayutham.au@gmail.com':       $batch = 'D'; break;
        case 'ezhisai_kb@yahoo.co.in':        $batch = 'E'; break;
        case 'jee.ezhiljodhi@gmail.com':
  			  $batch = 'F'; 
				case 'jee.ezhiljodhi@gmail.com':
			  $batch  = 'F';
					if (!empty($subject_id)) {
							  $subject = $wpdb->get_row(
							  $wpdb->prepare("SELECT title FROM {$wpdb->prefix}cdat_subjects WHERE id = %d",
								$subject_id
							)
						);
				   if ($subject) {

					// 🔁 Override batch if subject implies H batch
						if (stripos($subject->title, 'H Batch') !== false) {
						$batch = 'H';
					}
				}
			 }
			break;
        case 'ashrasgo@rediffmail.com':       $batch = 'G'; break;
		case 'jee.ezhiljodhi@gmail.com':      $batch = 'H'; break;	
        case 'srikrish.kkarthikeyan@gmail.com': $batch = 'I'; break;
        case 'nnrajan.au@gmail.com':
        case 'raje69@yahoo.co.in':            $batch = 'J'; break;
        default: $batch = '';
    }
	
	echo $email;
	echo $batch;
			
		
	echo $batch;
    $sql  = "SELECT * FROM $students_table WHERE semester = %d";
    $args = [$semester];

if ($semester == 2 && !empty($batch)) {
    $sql  .= " AND batch = %s";
    $args[] = $batch;
}

$sql .= " ORDER BY name";

$students = $wpdb->get_results(
    $wpdb->prepare($sql, $args)
);

        // Get all dates in the attendance table for the selected subject and range
        $dates = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT date FROM $attendance_table WHERE subject_id=%d AND date BETWEEN %s AND %s ORDER BY date ASC",
            $subject_id, $from, $to
        ));
        
        // Fetch attendance records for all students
        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT student_id, date, status FROM $attendance_table WHERE subject_id=%d AND date BETWEEN %s AND %s",
            $subject_id, $from, $to
        ));
        // Organize attendance by student_id and date
        $attendance_matrix = [];
        foreach ($records as $r) {
            $attendance_matrix[$r->student_id][$r->date] = $r->status;
        }
        // Prepare results
        foreach ($students as $st) {
            $row = [
                'roll' => $st->roll,
                'name' => $st->name,
                'dates' => [],
                'total' => count($dates),
                'present' => 0,
            ];
            foreach ($dates as $d) {
                $status = isset($attendance_matrix[$st->id][$d]) ? $attendance_matrix[$st->id][$d] : '-';
                $row['dates'][$d] = $status;
                if ($status === 'present') $row['present']++;
            }
            $row['percent'] = ($row['total'] > 0) ? round(($row['present'] / $row['total']) * 100, 2) : 0;
            $results[] = $row;
        }
    }
    // --- HTML Form & Table ---
    ?>
    <div class="wrap">
        <h1>Attendance Matrix</h1>
        <!-- Filters -->
        <div style="margin-bottom:20px;padding:15px;background:#fff;border-radius:8px;box-shadow:0 0 10px rgba(0,0,0,0.1);">
            <form method="POST">
                <?php wp_nonce_field('cdat_matrix_action'); $subject_id = isset($_POST['subject_id']) ? intval($_POST['subject_id']): 0; ?>
                <div style="display:flex;gap:15px;flex-wrap:wrap">
                    <div>
                        <label>Subject</label><br>
                        <select name="subject_id" id="cdat-subject">
                               <?php 
								$semesters = range(4, 10, 2);
								$user = wp_get_current_user();
								echo $user->user_email;
								echo $user->user_login;  
								$subjects = $wpdb->get_results("SELECT * FROM $subjects_table ORDER BY semester,code");
								//    	   
								if ($user->user_email == $user->user_login)
									$subjects = $wpdb->get_results("SELECT * FROM $subjects_table where email = '$user->user_email'");
								?>
								   <?php foreach ($subjects as $sub): ?>
									   <option value="<?php echo intval($sub->id); ?>" 
											data-sem="<?php echo intval($sub->semester); ?>">
											Semester <?php echo intval($sub->semester); ?> - <?php echo esc_html($sub->title); ?>
									   </option>
								   <?php endforeach; ?>
                        </select>
                    </div>
					<div>
						<hr>
					</div>
                    <div>
                        <label>Semester</label><br>
                        <select name="semester" id="cdat-semester">
                            <?php foreach ([4,6,8] as $sem): ?>
                                <option value="<?php echo $sem; ?>"><?php echo $sem; ?></option><br>
                            <?php endforeach; ?>
                        </select>
                    </div>
					<script>
                        document.addEventListener("DOMContentLoaded", function () {
                            const subjectDropdown = document.getElementById("cdat-subject");
                            const semesterDropdown = document.getElementById("cdat-semester");
                            subjectDropdown.addEventListener("change", function () {
                                const selected = subjectDropdown.options[subjectDropdown.selectedIndex];
                                const sem = selected.getAttribute("data-sem");

                                if (sem) {
                                    semesterDropdown.value = sem;
                                }
                            });
                        });
                    </script>
				
					<div>
                        <label>From Date</label><br>
                        <input type="date" name="from"  value="2025-12-08" required><br>
                    </div>
				    <div>
                        <label>To Date</label><br>
                        <input type="date" name="to" value="<?php echo date('Y-m-d'); ?>" required><br>
                    </div>
				    <div style="align-self:end">
                        <button type="submit" name="generate_matrix" class="button button-primary">Generate Matrix</button>
                    </div>
                </div>
            </form>
        </div>
        <?php if (!empty($results) && !empty($dates)) { ?>
            <div style="padding:15px;background:#fff;border-radius:8px;box-shadow:0 0 10px rgba(0,0,0,0.1);overflow-x:auto;">
                <h2>Attendance Matrix</h2>
                <table style="border-collapse:collapse;width:100%;min-width:800px;">
                    <thead>
                        <tr style="background:#0073aa;color:#fff;">
                            <th style="padding:8x;border:1px solid #ddd">Roll / Name</th>
                            <?php foreach ($dates as $d): ?>
                                <th style="padding:8px;border:1px solid #ddd">
                                    <?php echo date("d/m", strtotime($d)); ?>
                                </th>
                                <!-- <th style="padding:8px;border:1px solid #ddd"><?php echo esc_html($d); ?></th> -->
                            <?php endforeach; ?>
<!--                             <th style="padding:8px;border:1px solid #ddd">Total</th> -->
                            <th style="padding:8px;border:1px solid #ddd">Present</th>
                            <th style="padding:8px;border:1px solid #ddd">Percent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $r): 
                            $color = ($r['percent'] < 50) ? 'red' : 'green';
                        ?>
                        <tr style="text-align:center;">
                            <td style="padding:8px;border:1px solid #ddd;text-align:left;"><?php echo esc_html($r['roll'] . ' - ' . $r['name']); ?></td>
                            <?php foreach ($dates as $d): 
                                $status = $r['dates'][$d];
                                $s_color = ($status === 'present') ? 'green' : (($status==='absent') ? 'red' : 'gray');
                            ?>
                            <td style="padding:8px;border:1px solid #ddd;color:<?php echo $s_color; ?>;">
                                <?php echo (strtolower($status) === 'present') ? '/' : 'A'; ?>
                            </td>
                            <?php endforeach; ?>
<!--                             <td style="padding:8px;border:1px solid #ddd;"><?php echo intval($r['total']); ?></td> -->
                            <td style="padding:8px;border:1px solid #ddd;"><?php echo intval($r['present']); ?></td>
                            <td style="padding:8px;border:1px solid #ddd;color:<?php echo $color; ?>;"><?php echo esc_html($r['percent']); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div>
				<style>
				table.widefat td {
				    vertical-align: middle;
				}
				table.widefat tbody tr:hover {
    				background: #eef5ff;
				}
				</style>
                <?php
// ----------------------------------------
// DISPLAY DATE + TOPIC AFTER MATRIX
// ----------------------------------------
               
				if (!$subject_id) {   echo '<p style="color:#999;">⚠ Please select a subject to view topics.</p>'; return; }									   
				$attendance_table = $wpdb->prefix . 'cdat_attendance';

				$topics = $wpdb->get_results(
    			$wpdb->prepare(
						"SELECT DISTINCT date, topic
						 FROM $attendance_table
						 WHERE subject_id = %d
						   AND topic IS NOT NULL
						   AND topic <> ''
						 ORDER BY date ASC",
						$subject_id
					)
				);
			
                if ($topics):
                ?>
                    <div style="margin-top:25px;">
                        <h3 style="color:#1e73be;">📚 Topics Covered/Record of Class work</h3>
                        <table class="widefat striped" style="max-width:600px;">
                            <thead>
                                <tr>
                                    <th style="width:50px;background:#0073aa;color:#fff;">Date</th>
                                    <th style="width:200px;background:#0073aa;color:#fff;">Topic / Title</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topics as $t): ?>
                                    <tr>
                                        <td>
                                            <strong><?= esc_html(date('d-m-Y', strtotime($t->date))) ?></strong>
                                        </td>
                                        <td>
                                            <?= esc_html($t->topic) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php
                else:
                ?>
                    <p style="margin-top:20px; color:#999;">
                        😴 No topics recorded yet for this subject.
                    </p>
                <?php
                endif;
                ?>
            </div>
        <?php } ?>
    </div>
    <?php
}
/* ---------------- Consolidated summary begis here for class  ---------------- */
function cdat_class_attendance_page()
{
     ?>
    <div class="wrap">
		<style>
				.blink {
				color: red; /* optional */
				animation: blinkText 1.2s infinite;
			}

			@keyframes blinkText {
				0%   { opacity: 1; }
				50%  { opacity: 0; }
				100% { opacity: 1; }
			}
		</style>
       <h1 class="blink">Class Attendance Summary (work in progress) </h1>
       <form method="get" style="margin-bottom:20px">
            <input type="hidden" name="page"
                value="<?php echo esc_attr($_GET['page'] ?? ''); ?>">
            <label><strong>Semester:</strong></label>
            <select name="sem" required>
                <option value="">Select Semester</option>
                <?php for ($i = 4; $i < 10; $i += 2): ?>
        			<option value="<?= $i ?>">
            			Semester <?= $i ?>
        			</option>
    			<?php endfor; ?>           
            </select>
            <button class="button button-primary">
                View Matrix
            </button>
        </form>
        <?php
			if (!empty($_GET['sem'])) {
				cdat_semester_attendance_matrix(intval($_GET['sem']));
			}
		   if (!empty($_GET['sem'])) {
				cdat_display_subjects_by_semester(intval($_GET['sem']));
			}	   
        ?>
    </div>
    <?php
}               

function cdat_semester_attendance_matrix($semester) {
    global $wpdb;

    $student_table    = $wpdb->prefix . 'cdat_students';
    $subject_table    = $wpdb->prefix . 'cdat_subjects';
    $attendance_table = $wpdb->prefix . 'cdat_attendance';

    /* ----------------------------------------
     * STUDENTS
     * -------------------------------------- */

$user  = wp_get_current_user();
    $email = strtolower(trim($user->user_email));
    $batch = '';

    switch ($email) {
        case 'kmarvelb@gmail.com':           $batch = 'A'; break;
        case 'rmkps6101969@gmail.com':        $batch = 'B'; break;
        case 'damucivil75@gmail.com':         $batch = 'C'; break;
        case 'velayutham.au@gmail.com':       $batch = 'D'; break;
        case 'ezhisai_kb@yahoo.co.in':        $batch = 'E'; break;
        case 'jee.ezhiljodhi@gmail.com':      $batch = 'F'; break;
        case 'ashrasgo@rediffmail.com':       $batch = 'G'; break;
        case 'srikrish.kkarthikeyan@gmail.com': $batch = 'I'; break;
        case 'nnrajan.au@gmail.com':
        case 'raje69@yahoo.co.in':            $batch = 'J'; break;
        default: $batch = '';
    }

$sql  = "SELECT id, roll, name FROM $student_table WHERE semester = %d";
$args = [$semester];

/* Semester 2 → filter by batch (normal users) */
if ($semester == 2 && !empty($batch)) {
    $sql   .= " AND batch = %s";
    $args[] = $batch;
}

/* Special email → allow F & H batch */
if ($email === 'jee.ezhiljodhi@gmail.com') {
    $sql   .= " AND batch IN (%s, %s)";
    $args[] = 'F';
    $args[] = 'H';
}

$sql .= " ORDER BY roll";

/* FINAL QUERY */
$students = $wpdb->get_results(
    $wpdb->prepare($sql, $args)
);
 
    // $students = $wpdb->get_results(
    //     $wpdb->prepare(
    //         "SELECT id, roll, name
    //          FROM $student_table
    //          WHERE semester = %d
    //          ORDER BY roll",
    //         $semester
    //     )
    // );

    if (!$students) {
        echo "<p>🚨 No students found for Semester $semester.</p>";
        return;
    }

    /* ----------------------------------------
     * SUBJECTS → GROUP BY CODE
     * -------------------------------------- */
    $raw_subjects = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, code
             FROM $subject_table
             WHERE semester = %d
             ORDER BY RIGHT(code,3)",
            $semester
        )
    );

    if (!$raw_subjects) {
        echo "<p>🚨 No subjects found for Semester $semester.</p>";
        return;
    }

    // Group subject IDs by subject code
    $subjects = [];
    foreach ($raw_subjects as $s) {
        $subjects[$s->code][] = (int) $s->id;
    }

    /* ----------------------------------------
     * TOTAL CLASSES PER SUBJECT CODE
     * -------------------------------------- */
    $total_classes = [];

    foreach ($subjects as $code => $ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $sql = "
            SELECT COUNT(DISTINCT date)
            FROM $attendance_table
            WHERE subject_id IN ($placeholders)
        ";

        // SAFE prepare (NO spread operator)
        $query = call_user_func_array(
            [$wpdb, 'prepare'],
            array_merge([$sql], $ids)
        );

        $total_classes[$code] = (int) $wpdb->get_var($query);
    }

    /* ----------------------------------------
     * ATTENDANCE (MERGED BY SUBJECT CODE)
     * -------------------------------------- */
    $attendance = [];

    $rows = $wpdb->get_results(
        "SELECT student_id, subject_id, COUNT(*) AS present_count
         FROM $attendance_table
         WHERE status = 'present'
         GROUP BY student_id, subject_id"
    );

    foreach ($rows as $r) {
        foreach ($subjects as $code => $ids) {
            if (in_array((int) $r->subject_id, $ids, true)) {
                $attendance[$r->student_id][$code] =
                    ($attendance[$r->student_id][$code] ?? 0) + (int) $r->present_count;
            }
        }
    }

    /* ----------------------------------------
     * OUTPUT
     * -------------------------------------- */
    ?>
    <h2>Semester <?= intval($semester) ?> – Attendance Matrix</h2>

    <div style="overflow:auto">
        <button onclick="window.print()" class="button">🖨 Print / Save as PDF</button>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Roll</th>
                    <th>Name</th>
                    <?php foreach ($subjects as $code => $ids): ?>
                        <th style="color:#1e73be"><?= esc_html($code) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($students as $st): ?>
                    <tr>
                        <td><?= esc_html($st->roll) ?></td>

                        <?php
                        $name = esc_html($st->name);
                        $is_upper = strtoupper($name) === $name;
                        $color = $is_upper ? '#1e73be' : '#ff69b4';
                        ?>
                        <td style="color:<?= $color ?>"><?= $name ?></td>

                        <?php foreach ($subjects as $code => $ids):
                            $present = $attendance[$st->id][$code] ?? 0;
                            $total   = $total_classes[$code] ?? 0;
                            $percent = $total ? round(($present / $total) * 100) : 0;

                            if ($percent >= 75)      $style = 'color:#198754;';
                            elseif ($percent >= 50)  $style = 'color:#ffc107;';
                            else                     $style = 'color:#dc3545;';
                        ?>
                            <td style="<?= $style ?>">
                                <?= "$present / $total = $percent%" ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>

                <tr>
                    <td colspan="<?= count($subjects) + 2 ?>">
                        Attendance Indicator 🚦
                        <span style="color:#198754">● ≥75%</span>
                        <span style="color:#ffc107">● 50–74%</span>
                        <span style="color:#dc3545">● &lt;50%</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}

function cdat_display_subjects_by_semester($semester) {
    global $wpdb;

    $subject_table = $wpdb->prefix . 'cdat_subjects';
    $staff_table   = $wpdb->prefix . 'cdat_staff';

    $subjects = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT 
                MIN(s.id) AS subject_id,
                s.code,
                s.title,
                st.name AS staff_name
             FROM $subject_table s
             LEFT JOIN $staff_table st 
                ON s.staff_id = st.id
             WHERE s.semester = %d
             GROUP BY s.code, s.title, st.name
             ORDER BY RIGHT(s.code, 3)",
            $semester
        )
    );

    if (!$subjects) {
        echo '<p>😴 No subjects found for this semester.</p>';
        return;
    }
    ?>

    <h3>Subjects for Semester <?= intval($semester) ?></h3>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Subject ID</th>
                <th>Subject Code</th>
                <th>Subject Title</th>
                <th>Staff Name</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($subjects as $sub): ?>
            <tr>
                <td><?= intval($sub->subject_id) ?></td>
                <td style="font-weight:bold;color:#1e73be;">
                    <?= esc_html($sub->code) ?>
                </td>
                <td><?= esc_html($sub->title) ?></td>
                <td><?= $sub->staff_name ? '<strong style="color:#1e73be;">' . esc_html($sub->staff_name) . '</strong>': '<span style="color:#999">Not Assigned</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
/* ---------------- Edit & View functionalty attended ---------------- */


add_action('wp_ajax_edit_update_table_ajax', 'edit_update_table_ajax_handler');

function edit_update_table_ajax_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'edit_update_table_inline_update')) {
        wp_send_json_error('Nonce verification failed');
    }

    global $wpdb;
    $table = sanitize_text_field($_POST['table_name'] ?? '');
    $crud_action = sanitize_text_field($_POST['crud_action'] ?? '');
    $id = intval($_POST['id'] ?? 0);

    if (!$table || !in_array($crud_action, ['add', 'update', 'delete'])) {
        wp_send_json_error('Invalid request');
    }

    // Ensure table exists
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if (!$table_exists) wp_send_json_error('Table does not exist');

    // Get columns
    $columns = $wpdb->get_col("DESCRIBE $table", 0);
    $data = [];
    foreach ($columns as $col) {
        if ($col === 'id') continue;
        if (isset($_POST[$col])) $data[$col] = sanitize_text_field($_POST[$col]);
    }

    try {
        if ($crud_action === 'add') {
            $wpdb->insert($table, $data);
            $id = $wpdb->insert_id;
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id), ARRAY_A);
            wp_send_json_success(['message' => 'Added successfully', 'row' => $row]);
        }

        if ($crud_action === 'update') {
            if (!$id) wp_send_json_error('Missing ID for update');
            $wpdb->update($table, $data, ['id' => $id]);
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id), ARRAY_A);
            wp_send_json_success(['message' => 'Updated successfully', 'row' => $row]);
        }

        if ($crud_action === 'delete') {
            if (!$id) wp_send_json_error('Missing ID for delete');
            $wpdb->delete($table, ['id' => $id]);
            wp_send_json_success(['message' => 'Deleted successfully', 'deleted' => true]);
        }

    } catch (Exception $e) {
        wp_send_json_error('Database error: ' . $e->getMessage());
    }

    wp_send_json_error('Unknown error');
}

// 2️⃣ Admin page function

function edit_update_table() {
    global $wpdb;
    $all_tables = $wpdb->get_col("SHOW TABLES;");

    // Table selection form
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_tables'])) {
        check_admin_referer('edit_update_table_select');
        $selected_tables = array_map('sanitize_text_field', $_POST['selected_tables']);
        if (empty($selected_tables)) { echo '<p>Select at least one table.</p>'; return; }

        // Styles
        echo '<style>
        .edit-table { width:100%; border-collapse: collapse; margin-bottom:20px; }
        .edit-table th, .edit-table td { border:1px solid #ccc; padding:8px; text-align:left; }
        .edit-table tr:nth-child(even) { background:#f8f8f8; }
        .toast { position: fixed; top: 10px; right: 10px; background:#323232; color:#fff; padding:10px 20px; border-radius:5px; display:none; z-index:9999; }
        .add-new-btn { margin-bottom:10px; }
        </style>';
        echo '<div class="toast" id="crud-toast"></div>';

        // Loop selected tables
        foreach ($selected_tables as $table) {
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if (!$table_exists) continue;

            $rows = $wpdb->get_results("SELECT * FROM $table");
            $columns = $wpdb->get_col("DESCRIBE $table", 0);

            echo '<details style="margin-bottom:20px"><summary><strong>' . esc_html($table) . '</strong></summary>';
            echo '<div style="margin-top:10px;">';
            echo '<button class="button button-primary add-new-btn" data-table="' . esc_attr($table) . '">Add New</button>';

            echo '<table class="edit-table"><thead><tr>';
            foreach ($columns as $col) echo '<th>' . esc_html($col) . '</th>';
            echo '<th>Actions</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                echo '<tr data-id="' . intval($row->id) . '">';
                foreach ($columns as $col) echo '<td class="view-' . $col . '">' . esc_html($row->$col) . '</td>';
                echo '<td class="actions"><i class="bi bi-pencil-square edit-icon" style="cursor:pointer;margin-right:5px"></i> <i class="bi bi-trash3 delete-icon" style="cursor:pointer;color:red"></i></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div></details>';
        }

    } else {
        echo '<h2>Select Tables to Manage</h2>';
        echo '<form method="post">';
        wp_nonce_field('edit_update_table_select');
        echo '<select name="selected_tables[]" multiple size="10" style="width:300px;">';
        foreach ($all_tables as $table) echo '<option value="' . esc_attr($table) . '">' . esc_html($table) . '</option>';
        echo '</select><br><br>';
        echo '<button class="button button-primary" type="submit">Manage Selected Tables</button>';
        echo '</form>';
    }

    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">';

    // JS
    ?>
    <script>
    jQuery(document).ready(function($){
        const ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
        const ajaxNonce = '<?php echo wp_create_nonce("edit_update_table_inline_update"); ?>';
        const toast = $('#crud-toast');

        function showToast(msg){ toast.text(msg).fadeIn(300).delay(2000).fadeOut(300); }

        // Add New modal
        $('.add-new-btn').click(function(){
            const table = $(this).data('table');
            let cols = [];
            $(this).next('table').find('thead th').each(function(){ if($(this).text()!=='Actions') cols.push($(this).text()); });

            let formHtml='<div class="modal-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;">';
            formHtml+='<div style="background:#fff;padding:20px;border-radius:8px;width:400px;"><h3>Add New '+table+'</h3><form id="add-new-form">';
            cols.forEach(c=>{ if(c!=='id') formHtml+='<label>'+c+'</label><input type="text" name="'+c+'" style="width:100%;margin-bottom:5px;"><br>'; });
            formHtml+='<button class="button button-primary" type="submit">Add</button> <button type="button" class="button cancel-btn">Cancel</button></form></div></div>';
            $('body').append(formHtml);
        });

        $('body').on('click','.cancel-btn',function(e){ e.preventDefault(); $(this).closest('.modal-overlay').remove(); });

        $('body').on('submit','#add-new-form',function(e){
            e.preventDefault();
            const table = $(this).closest('.modal-overlay').prevAll('details').find('summary').first().text().trim();
            const data = {action:'edit_update_table_ajax',nonce:ajaxNonce,crud_action:'add',table_name:table};
            $(this).serializeArray().forEach(f=>data[f.name]=f.value);
            $.post(ajaxUrl,data,function(res){ if(res.success){ showToast('Added successfully'); location.reload(); } else showToast(res.data||'Add failed'); },'json');
        });

        // Inline edit
        $('table').on('click','.edit-icon',function(){
            const row=$(this).closest('tr'); if(row.data('editing')) return; row.data('editing',true);
            const cols=row.find('td').not('.actions'); row.data('oldHtml',row.html());
            let html=''; cols.each(function(){ html+='<td><input type="text" value="'+$(this).text().trim()+'" style="width:100%"></td>'; });
            html+='<td><button class="button button-primary save-btn">Save</button> <button class="button cancel-btn-inline">Cancel</button></td>'; row.html(html);
        });

        $('table').on('click','.cancel-btn-inline',function(e){ e.preventDefault(); $(this).closest('tr').html($(this).closest('tr').data('oldHtml')); });

        // Save
        $('table').on('click','.save-btn',function(e){
            e.preventDefault(); const row=$(this).closest('tr'); const id=row.data('id');
            const table=row.closest('table').prevAll('summary').first().text().trim();
            let data={action:'edit_update_table_ajax',nonce:ajaxNonce,crud_action:'update',table_name:table,id:id};
            row.find('input').each(function(i){ const col=row.closest('table').find('thead th').eq(i).text().trim(); data[col]=$(this).val(); });
            $.post(ajaxUrl,data,function(res){ if(res.success){ showToast('Updated successfully'); location.reload(); } else showToast(res.data||'Update failed'); },'json');
        });

        // Delete
        $('table').on('click','.delete-icon',function(){
            const row=$(this).closest('tr'); const id=row.data('id'); const table=row.closest('table').prevAll('summary').first().text().trim();
            if(confirm('Delete this row?')){
                $.post(ajaxUrl,{action:'edit_update_table_ajax',nonce:ajaxNonce,crud_action:'delete',table_name:table,id:id},function(res){
                    if(res.success){ showToast('Deleted successfully'); row.remove(); } else showToast(res.data||'Delete failed'); },'json');
            }
        });
    });
    </script>
    <?php
}


// Added Functions on 16122025
// 
// 

function cdat_timetable_matrix_page() {
    global $wpdb;

    $tt_table      = $wpdb->prefix . 'cdat_timetable';
    $subject_table = $wpdb->prefix . 'cdat_subjects';
    $staff_table   = $wpdb->prefix . 'cdat_staff';

    $days    = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    $periods = range(1,7);

    /* ---------------- SAVE TIMETABLE ---------------- */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_timetable'])) {
        check_admin_referer('cdat_timetable_matrix');

        $semester = intval($_POST['semester']);
        $matrix   = $_POST['matrix'] ?? [];

        foreach ($matrix as $day => $slots) {
            foreach ($slots as $period => $subject_id) {

                $subject_id = intval($subject_id);
                $slot = 'P' . intval($period);

                // Delete existing entry
                $wpdb->delete($tt_table, [
                    'semester' => $semester,
                    'day'      => $day,
                    'period'   => $period
                ]);

                // Insert if selected
                if ($subject_id > 0) {
                    $wpdb->insert($tt_table, [
                        'semester'   => $semester,
                        'day'        => $day,
                        'slot'       => $slot,
                        'period'     => $period,
                        'subject_id' => $subject_id
                    ]);
                }
            }
        }

        echo '<div class="notice notice-success"><p>✅ Timetable saved successfully.</p></div>';
    }

    /* ---------------- SEMESTER SELECT ---------------- */
    $semester = isset($_GET['semester']) ? intval($_GET['semester']) : 0;
    ?>
    <div class="wrap">
        <h1>📅 Timetable Matrix</h1>

        <form method="get" style="margin-bottom:15px">
            <input type="hidden" name="page" value="<?= esc_attr($_GET['page']) ?>">
            <select name="semester" required>
                <option value="">Select Semester</option>
                <?php for ($i=4;$i<=8;$i +=2): ?>
                    <option value="<?= $i ?>" <?= selected($semester,$i) ?>>
                        Semester <?= $i ?>
                    </option>
                <?php endfor; ?>
            </select>
            <button class="button button-primary">Load</button>
        </form>

    <?php
    if (!$semester) {
        echo '<p>👉 Select a semester to begin.</p>';
        return;
    }

    /* ---------------- SUBJECTS + STAFF ---------------- */
    $subjects = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT s.id, s.code, s.title, st.name AS staff_name
             FROM $subject_table s
             LEFT JOIN $staff_table st ON s.staff_id = st.id
             WHERE s.semester = %d
             ORDER BY RIGHT(s.code,3)",
            $semester
        )
    );

    if (!$subjects) {
        echo '<p>😴 No subjects found for this semester.</p>';
        return;
    }

    /* ---------------- EXISTING TIMETABLE ---------------- */
    $existing = [];
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT day, period, subject_id
             FROM $tt_table
             WHERE semester = %d",
            $semester
        )
    );

    foreach ($rows as $r) {
        $existing[$r->day][$r->period] = $r->subject_id;
    }
    ?>

    <form method="post">
        <?php wp_nonce_field('cdat_timetable_matrix'); ?>
        <input type="hidden" name="semester" value="<?= $semester ?>">

        <table class="widefat striped" style="text-align:center">
            <thead style="background:#1e73be;color:#fff">
                <tr>
					  <th style="color:#fff; background:#1e73be; font-weight:bold; text-align:left;"> Day/Period</th>  
                    <?php foreach ($periods as $p): ?>
<!--                         <th>P<?= $p ?></th> -->
					  <th style="color:#fff; background:#1e73be; font-weight:bold; text-align:center; ">Period <?= $p ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($days as $day): ?>
                <tr>
                    <th style="background:#f0f6ff"><?= esc_html($day) ?></th>
                    <?php foreach ($periods as $p):
                        $selected = $existing[$day][$p] ?? '';
                    ?>
                        <td>
                            <select name="matrix[<?= esc_attr($day) ?>][<?= $p ?>]">
                                <option value="">—</option>
                                <?php foreach ($subjects as $sub): ?>
                                    <option value="<?= $sub->id ?>"
                                        <?= selected($selected,$sub->id) ?>>
                                        <?= esc_html($sub->code) ?>
                                        <?= $sub->staff_name ? ' – '.$sub->staff_name : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top:15px">
            <button name="save_timetable" class="button button-primary button-large">
                💾 Save Timetable
            </button>
            <button type="button" onclick="window.print()" class="button">
                🖨 Print
            </button>
        </p>
    </form>
    </div>
    <?php
}


function cdat_student_deficiency_report() {
    global $wpdb;

    $students   = $wpdb->prefix . 'cdat_students';
    $attendance = $wpdb->prefix . 'cdat_attendance';
//     echo $students;
    
    // Selected semester from filter
    $selected_sem = isset($_GET['semester']) ? intval($_GET['semester']) : 0;

    // Fetch semesters
    $semesters = $wpdb->get_col("SELECT DISTINCT semester FROM $students ORDER BY semester");
    echo '<div class="wrap">';
    echo '<h1>📉 Student Attendance Deficiency  Report</h1>';
	echo "As on today" . date("d-m-Y");
    /* ---------- FILTER FORM ---------- */
    ?>
    <form method="get" style="margin:15px 0;">
        <input type="hidden" name="page" value="<?= esc_attr($_GET['page']) ?>">
        <label><strong>Filter by Semester:</strong></label>
		<?php $semesters = range(4, 8, 2);  ?> // 4, 6, 8
        <select name="semester">
            <option value="">All Semesters</option>
            <?php foreach ($semesters as $sem): ?>
                <option value="<?= $sem ?>" <?= selected($selected_sem, $sem, false) ?>>
                    Semester <?= $sem ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="button button-primary">Apply</button>
    </form>
    <?php

    /* ---------- LOOP SEMESTERS ---------- */
    foreach ($semesters as $sem) {

        if ($selected_sem && $selected_sem != $sem) continue;

        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT 
                s.roll,
                s.name,
                s.semester,
                COUNT(a.id) AS total_classes,
                SUM(a.status = 'present') AS present_count,
                ROUND((SUM(a.status = 'present') / COUNT(a.id)) * 100, 1) AS percent
            FROM $students s
            JOIN $attendance a ON a.student_id = s.id
            WHERE s.semester = %d
            GROUP BY s.id
            HAVING percent < 65
            ORDER BY s.roll
            ", $sem));
// ------  ORDER BY percent ASC
        echo "<h2 style='margin-top:25px;'>Semester $sem</h2>";

        if (!$rows) {
            echo "<p style='color:#16a34a;'>🎉 No defaulters or warning students.</p>";
            continue;
        }
        ?>

        <table class="widefat striped">
            <thead>
                <tr style="background:#f1f5f9;">
                    <th>Roll No</th>
                    <th>Name</th>
                    <th>Attendance %</th>
                    <th>Category</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):

                if ($r->percent < 50) {
                    $label = '🚨 Defaulter';
                    $color = '#dc2626';
                } else {
                    $label = '⚠ Warning';
                    $color = '#d97706';
                }
                ?>
                <tr>
                    <td><?= esc_html($r->roll) ?></td>
                    <td><?= esc_html($r->name) ?></td>
                    <td style="font-weight:bold;color:<?= $color ?>">
                        <?= esc_html($r->percent) ?>%
                    </td>
                    <td style="font-weight:600;color:<?= $color ?>">
                        <?= $label ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php
    }

    echo '</div>';
}
// Restricting Profile.php
function restrict_profile_by_roles() {
    $user = wp_get_current_user();
    $allowed_roles = array('administrator', 'editor'); // Add allowed roles
    
    if (!array_intersect($allowed_roles, $user->roles)) {
        remove_menu_page('profile.php');
        
        // Redirect if trying to access directly
        if (isset($_GET['page']) && $_GET['page'] == 'profile.php') {
            wp_redirect(admin_url());
            exit;
        }
    }
}
add_action('admin_init', 'restrict_profile_by_roles');

function cdat_bulk_update_attendance_topic_page() {
    // if (!current_user_can('manage_options')) {
    //     wp_die('Access denied');
    // }

    global $wpdb;
    $subjects = $wpdb->get_results("SELECT id, code, title FROM {$wpdb->prefix}cdat_subjects ORDER BY semester, code");

    ?>
    <div class="wrap">
        <h1>📝 Bulk Update Attendance Topic [ only for 4/6/8 Sems]</h1>

        <form method="post">
            <?php 
            wp_nonce_field('cdat_bulk_topic_action');
            $subjects_table = $wpdb->prefix . "cdat_subjects";
            $user = wp_get_current_user();
                //   echo $user->user_email;
                // 	 echo $user->user_login;  
                $subjects = $wpdb->get_results("SELECT * FROM $subjects_table ORDER BY semester,code");
                //    	   
            if ($user->user_email == $user->user_login)
                    $subjects = $wpdb->get_results("SELECT * FROM $subjects_table where email = '$user->user_email'");

            ?>
            <table class="form-table">
            <tr>
                        <th>Subject</th>
                        <td>
                            <label>Subject</label>
                            <select name="subject_id" id="cdat_subject">
                                <option value="">-- Select subject --</option>
                                <?php foreach ($subjects as $sub) {
                                    echo '<option value="' . intval($sub->id) . '" data-sem="' . intval($sub->semester) . '">Sem ' . intval($sub->semester) . ' - ' . esc_html($sub->code) . ' ' . esc_html($sub->title) . '</option>';
                                } ?>
                            </select>
                        </td>
                    </tr>
                <tr>
                    <th>Date</th>
                    <td><input type="date" name="from_date" required></td>
                </tr>
                 <tr>
                    <th>Date To</th>
                    <td><input type="date" name="to_date" required></td>
                </tr> 

                <tr>
                    <th>Topic / Title</th>
                    <td>
                        <input type="text" name="topic" style="width:400px" required placeholder="Eg: Unit 3 – Sorting Algorithms">
                    </td>
                </tr>
            </table>

            <p>
                <button class="button button-primary">Update Attendance Records</button>
            </p>
        </form>
    </div>
    <?php

//     /* ---------- HANDLE FORM SUBMIT ---------- */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_wpnonce'])) {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'cdat_bulk_topic_action')) {
            wp_die('Nonce verification failed');
        }

        $subject_id = intval($_POST['subject_id']);
        $from       = sanitize_text_field($_POST['from_date']);
        $to         = sanitize_text_field($_POST['to_date']);
        $topic      = sanitize_text_field($_POST['topic']);

        $attendance = $wpdb->prefix . 'cdat_attendance';
    
		$updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE $attendance 
                 SET topic = %s
                 WHERE subject_id = %d
                 AND date BETWEEN %s AND %s",
                $topic, $subject_id, $from, $to
            )
        );

        echo '<div class="notice notice-success is-dismissible">
                <p>✅ Topic updated for <strong>' . intval($updated) . '</strong> attendance records.</p>
              </div>';
}?>
<?php    
}

function cdat_attendance_dashboard_page()
{
    if (!is_user_logged_in()) {
        wp_die('Please login to view attendance dashboard');
    }

    global $wpdb;

    $attendance = $wpdb->prefix . 'cdat_attendance';
    $students   = $wpdb->prefix . 'cdat_students';
    $subjects   = $wpdb->prefix . 'cdat_subjects';
    $staff      = $wpdb->prefix . 'cdat_staff';

    $semester = intval($_GET['semester'] ?? 0);
    $staff_id = intval($_GET['staff_id'] ?? 0);

    $staffs = $wpdb->get_results("SELECT id, name FROM $staff ORDER BY name");

    $where = "1=1";
    if ($semester) $where .= $wpdb->prepare(" AND st.semester=%d", $semester);
    if ($staff_id) $where .= $wpdb->prepare(" AND sb.staff_id=%d", $staff_id);

    $rows = $wpdb->get_results("
        SELECT 
            sb.id AS subject_id,
            sb.code,
            sb.title,
            st.semester,
            sf.name AS staff_name,
            COUNT(a.id) AS total,
            SUM(a.status='present') AS present
        FROM $attendance a
        JOIN $students st ON a.student_id = st.id
        JOIN $subjects sb ON a.subject_id = sb.id
        LEFT JOIN $staff sf ON sb.staff_id = sf.id
        WHERE $where
        GROUP BY a.subject_id
        ORDER BY st.semester, sb.code
    ");

    // Prepare chart data
    $chart_labels = [];
    $chart_values = [];

    foreach ($rows as $r) {
        $percent = $r->total ? round(($r->present / $r->total) * 100, 1) : 0;
        $chart_labels[] = $r->code;
        $chart_values[] = $percent;
    }
    ?>

    <div class="wrap">
        <h1>📊 Attendance Dashboard</h1>

        <!-- FILTERS -->
        <form method="get" style="display:flex;gap:12px;margin-bottom:20px">
            <input type="hidden" name="page" value="<?= esc_attr($_GET['page']) ?>">

            <select name="semester">
                <option value="">All Semesters</option>
                <?php foreach ([2,4,6,8] as $s): ?>
                    <option value="<?= $s ?>" <?= selected($semester, $s) ?>>Semester <?= $s ?></option>
                <?php endforeach; ?>
            </select>

            <select name="staff_id">
                <option value="">All Staff</option>
                <?php foreach ($staffs as $sf): ?>
                    <option value="<?= $sf->id ?>" <?= selected($staff_id, $sf->id) ?>>
                        <?= esc_html($sf->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="button button-primary">Apply</button>
        </form>

        <!-- CHART -->
        <canvas id="attendanceChart" height="90"></canvas>

        <!-- TABLE -->
        <table class="widefat striped" style="margin-top:25px">
             <thead style="background:#fff;color:#1e73be"> 
                <tr>
                    <th>Semester</th>
                    <th>Subject</th>
                    <th>Staff</th>
                    <th>Attendance</th>
                    <th>AI Advice</th>
                    <!-- <th>WhatsApp</th> -->
                </tr>
            </thead>
            <tbody>
            <?php if ($rows): foreach ($rows as $r):
                $percent = $r->total ? round(($r->present / $r->total) * 100, 1) : 0;
                // Color
                $color = $percent < 50 ? 'red' : ($percent < 75 ? 'orange' : 'green');
                // AI Advice (rule based)
                if ($percent < 50) {
                    $advice = "🚨 Critical: Conduct remedial & parent contact";
                } elseif ($percent < 75) {
                    $advice = "⚠ Improve: Extra classes & mentoring";
                } else {
                    $advice = "✅ Good: Maintain engagement";
                }
               
            ?>
                <tr>
                    <td><?= esc_html($r->semester) ?></td>
                    <td>
                        <strong><?= esc_html($r->code) ?></strong><br>
                        <?= esc_html($r->title) ?>
                    </td>
                    <td><?= esc_html($r->staff_name ?? '—') ?></td>
                    <td style="font-weight:bold;color:<?= $color ?>">
                        <?= $percent ?>%
                    </td>
                    <td><?= esc_html($advice) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6">No data</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    const ctx = document.getElementById('attendanceChart');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Attendance %',
                data: <?= json_encode($chart_values) ?>,
                backgroundColor: '#1e73be'
            }]
        },
        options: {
            scales: {
                y: { beginAtZero: true, max: 100 }
            }
        }
    });
    </script>
    <?php
}

function cdat_student_deficiency_report1() {
    global $wpdb;

    $students   = $wpdb->prefix . 'cdat_students';
    $attendance = $wpdb->prefix . 'cdat_attendance';

    $selected_sem = isset($_GET['semester']) ? intval($_GET['semester']) : 0;

    // Fetch semesters
    $semesters = $wpdb->get_col("SELECT DISTINCT semester FROM $students ORDER BY semester");

    echo '<div class="wrap">';
    echo '<h1>📉 Student Attendance Deficiency Report</h1>';
    echo '<p><strong>As on:</strong> ' . date("d-m-Y") . '</p>';

    /* ---------- FILTER FORM ---------- */
    ?>
    <form method="get" style="margin:15px 0;">
        <input type="hidden" name="page" value="<?= esc_attr($_GET['page']) ?>">
        <label><strong>Filter by Semester:</strong></label>
        <select name="semester">
            <option value="">All Semesters</option>
            <?php $semesters = range(2, 8, 2); ?>
			<?php foreach ($semesters as $sem): ?>
                <option value="<?= esc_attr($sem) ?>" <?= selected($selected_sem, $sem, false) ?>>
                    Semester <?= esc_html($sem) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="button button-primary">Apply</button>
    </form>
    <?php

    /* ---------- LOOP SEMESTERS ---------- */
    foreach ($semesters as $sem) {

        if ($selected_sem && $selected_sem != $sem) continue;

		$rows = $wpdb->get_results($wpdb->prepare("
            SELECT 
                s.roll,
                s.name,
                s.semester,
                COUNT(a.id) AS total_classes,
                SUM(a.status = 'present') AS present_count,
                ROUND((SUM(a.status = 'present') / COUNT(a.id)) * 100, 1) AS percent
            FROM $students s
            JOIN $attendance a ON a.student_id = s.id
            WHERE s.semester = %d
            GROUP BY s.id
            HAVING percent < 65
            ORDER BY s.roll
            ", $sem));
// ------  ORDER BY percent ASC
        		
        echo "<h2 style='margin-top:25px;'>Semester $sem</h2>";

        if (!$rows) {
            echo "<p style='color:#16a34a;'>🎉 No defaulters or warning students.</p>";
            continue;
        }

        // ---------- BUILD BULK WHATSAPP MESSAGE ----------
        $bulk_whatsapp = "🚨 Attendance Defaulters – Semester $sem\n";
        $bulk_whatsapp .= "As on " . date("d-m-Y") . "\n\n";

        $has_defaulters = false;

        foreach ($rows as $r) {
            if ($r->percent < 50) {
                $has_defaulters = true;
                $bulk_whatsapp .= "{$r->roll} - {$r->name} ({$r->percent}%)\n";
            }
        }

        $bulk_whatsapp .= "\nPlease instruct students to meet the class advisor immediately.\n— Academic Section";
        ?>

        <?php if ($has_defaulters): ?>
            <p style="margin:10px 0;">
                <a class="button button-secondary"
                   target="_blank"
                   href="https://wa.me/?text=<?= rawurlencode($bulk_whatsapp) ?>">
                   📲 Bulk WhatsApp Alert (Defaulters)
                </a>
            </p>
        <?php endif; ?>

        <table class="widefat striped">
            <thead>
                <tr style="background:#f1f5f9;">
                    <th>Roll No</th>
                    <th>Name</th>
                    <th>Attendance %</th>
                    <th>Category</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>

            <?php foreach ($rows as $r):

                if ($r->percent < 50) {
                    $label = '🚨 Defaulter';
                    $color = '#dc2626';
                } else {
                    $label = '⚠ Warning';
                    $color = '#d97706';
                }

                // Email content (FIXED)
                $email_subject = rawurlencode('Attendance Deficiency Alert');
                $email_body = rawurlencode(
                    "Dear {$r->name},\n\n" .
                    "Your current attendance is {$r->percent}%, which is below the minimum required.\n\n" .
                    "Please meet the class advisor immediately.\n\n" .
                    "— Academic Section"
                );

                // WhatsApp (individual)
                $whatsapp_msg = rawurlencode(
                    "🚨 Attendance Deficiency Alert\n\n" .
                    "Roll No: {$r->roll}\n" .
                    "Name: {$r->name}\n" .
                    "Attendance: {$r->percent}%\n\n" .
                    "Please meet the class advisor immediately.\n— Academic Section"
                );
                ?>

                <tr>
                    <td><?= esc_html($r->roll) ?></td>
                    <td><?= esc_html($r->name) ?></td>

                    <td style="font-weight:bold;color:<?= esc_attr($color) ?>">
                        <?= esc_html($r->percent) ?>%
                    </td>

                    <td style="font-weight:600;color:<?= esc_attr($color) ?>">
                        <?= $label ?>
                    </td>

                    <td>
                        <a class="button button-secondary"
                           target="_blank"
                           href="https://wa.me/?text=<?= $whatsapp_msg ?>">
                           📲 WhatsApp
                        </a>

                        <a class="button button-primary"
                           href="mailto:<?= esc_attr($r->email) ?>?subject=<?= $email_subject ?>&body=<?= $email_body ?>">
                           📧 Email
                        </a>
                    </td>
                </tr>

            <?php endforeach; ?>

            </tbody>
        </table>

        <?php
    }

    echo '</div>';
}