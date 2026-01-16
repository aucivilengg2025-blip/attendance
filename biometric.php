<?php
/*
Plugin Name: Smart Biometric Attendance System
Description: Attendance with GPS, Selfie, Reverse Geocoding + Excel Export
Version: 2.1
Author: RMC LearnEarn
*/

if(!defined('ABSPATH')) exit;

/* =====================================================
DATABASE TABLES
===================================================== */
register_activation_hook(__FILE__, function(){
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH.'wp-admin/includes/upgrade.php';

    // Users table
    dbDelta("CREATE TABLE {$wpdb->prefix}sba_users (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) UNIQUE,
        name VARCHAR(100),
        dept VARCHAR(50),
        semester VARCHAR(20),
        role VARCHAR(20),
        email VARCHAR(100),
        mobile VARCHAR(20)
    ) $charset;");

    // Subjects table
    dbDelta("CREATE TABLE {$wpdb->prefix}sba_subjects (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        subject_code VARCHAR(50),
        subject_name VARCHAR(100),
        semester VARCHAR(20),
        classtype VARCHAR(50)
    ) $charset;");

    // Attendance table
    dbDelta("CREATE TABLE {$wpdb->prefix}sba_attendance (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50),
        subject_code VARCHAR(50),
        att_date DATE,
        att_time TIME,
        latitude VARCHAR(30),
        longitude VARCHAR(30),
        location_name VARCHAR(150),
        selfie LONGTEXT
    ) $charset;");

    // Export folder
    if(!file_exists(wp_upload_dir()['basedir'].'/sba-exports')){
        mkdir(wp_upload_dir()['basedir'].'/sba-exports',0777,true);
    }
});

/* =====================================================
ADMIN MENUS
===================================================== */
add_action('admin_menu', function(){
    add_menu_page('Attendance','Attendance','read','sba-home','sba_home','dashicons-camera');
    add_submenu_page('sba-home','Register','Register','manage_options','sba-register','sba_register_page');
    add_submenu_page('sba-home','Subjects','Subjects','manage_options','sba-subjects','sba_subjects_page');
    add_submenu_page('sba-home','Mark Attendance','Mark Attendance','read','sba-mark','sba_mark_page');
    add_submenu_page('sba-home','Reports','Reports','manage_options','sba-reports','sba_reports_page');
});

/* =====================================================
HOME PAGE
===================================================== */
function sba_home(){
    echo "<h2>Smart Biometric Attendance System</h2>
    <p>Attendance with GPS + Selfie + Reverse Geocoding + Excel Export</p>";
}

/* =====================================================
REGISTER USER PAGE
===================================================== */
function sba_register_page(){
    global $wpdb;
    if(isset($_POST['sba_register'])){
        $code = trim(sanitize_text_field($_POST['code']));
        $name = trim(sanitize_text_field($_POST['name']));
        $dept = sanitize_text_field($_POST['dept']);
        $sem  = sanitize_text_field($_POST['semester']);
        $role = sanitize_text_field($_POST['role']);
        $email = sanitize_email($_POST['email']);
        $mobile = sanitize_text_field($_POST['mobile']);

        if($code && $name){
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sba_users WHERE code=%s",$code));
            if($exists) echo "<div style='color:red;'>User already exists</div>";
            else{
                $wpdb->insert($wpdb->prefix.'sba_users',[
                    'code'=>$code,
                    'name'=>$name,
                    'dept'=>$dept,
                    'semester'=>$sem,
                    'role'=>$role,
                    'email'=>$email,
                    'mobile'=>$mobile
                ]);
                echo "<div style='color:green;'>Registered Successfully</div>";
            }
        } else echo "<div style='color:red;'>Code & Name required</div>";
    }

    echo "
    <h3>Register User</h3>
    <form method='POST'>
        <input name='code' placeholder='User ID'><br><br>
        <input name='name' placeholder='Name'><br><br>
        <input name='dept' placeholder='Department'><br><br>
        <input name='semester' placeholder='Semester'><br><br>
        <select name='role'>
            <option value='student'>Student</option>
            <option value='staff'>Staff</option>
        </select><br><br>
        <input name='email' placeholder='Email'><br><br>
        <input name='mobile' placeholder='Mobile'><br><br>
        <button name='sba_register'>Register</button>
    </form>";
}

/* =====================================================
SUBJECTS PAGE
===================================================== */
function sba_subjects_page(){
    global $wpdb;
    if(isset($_POST['sba_add_subject'])){
        $code = trim(sanitize_text_field($_POST['subject_code']));
        $name = trim(sanitize_text_field($_POST['subject_name']));
        $sem  = sanitize_text_field($_POST['semester']);
        $type = sanitize_text_field($_POST['classtype']);

        if($code && $name){
            $wpdb->insert($wpdb->prefix.'sba_subjects',[
                'subject_code'=>$code,
                'subject_name'=>$name,
                'semester'=>$sem,
                'classtype'=>$type
            ]);
            echo "<div style='color:green;'>Subject Added</div>";
        } else echo "<div style='color:red;'>Code & Name required</div>";
    }

    echo "
    <h3>Add Subject</h3>
    <form method='POST'>
        <input name='subject_code' placeholder='Subject Code'><br><br>
        <input name='subject_name' placeholder='Subject Name'><br><br>
        <input name='semester' placeholder='Semester'><br><br>
        <select name='classtype'>
            <option value='lecture'>Lecture (1 hour)</option>
            <option value='practicals'>Practicals (3 hours)</option>
            <option value='seminar/project'>Seminar / Project (2 hours)</option>
        </select><br><br>
        <button name='sba_add_subject'>Add Subject</button>
    </form>";
}

/* =====================================================
MARK ATTENDANCE PAGE
===================================================== */
function sba_mark_page(){
    global $wpdb;
    if(isset($_POST['sba_mark'])){
        $code = trim(sanitize_text_field($_POST['user_code']));
        $subject = trim(sanitize_text_field($_POST['subject_code']));
        $lat = sanitize_text_field($_POST['latitude']);
        $lng = sanitize_text_field($_POST['longitude']);
        $selfie = $_POST['selfie']??'';

        if(!$code || !$subject){ echo "<div style='color:red;'>User & Subject required</div>"; return; }

        $exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}sba_attendance 
            WHERE code=%s AND subject_code=%s AND att_date=CURDATE()",
            $code, $subject
        ));

        if($exists > 0){ echo "<div style='color:red;'>Attendance already marked for this subject today</div>"; return; }

        $location_name = '';
        if($lat && $lng){
            $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=18&addressdetails=1";
            $resp = @file_get_contents($url);
            if($resp){
                $json = json_decode($resp,true);
                $location_name = $json['display_name'] ?? '';
            }
        }

        $wpdb->insert($wpdb->prefix.'sba_attendance',[
            'code'=>$code,
            'subject_code'=>$subject,
            'att_date'=>date('Y-m-d'),
            'att_time'=>date('H:i:s'),
            'latitude'=>$lat,
            'longitude'=>$lng,
            'location_name'=>$location_name,
            'selfie'=>$selfie
        ]);

        echo "<div style='color:green;'>Attendance Marked Successfully</div>";
    }

    echo "
    <h3>Mark Attendance</h3>
    <form method='POST'>
        <input name='user_code' placeholder='User ID'><br><br>
        <select name='subject_code'><br><br>";
            $subjects = $wpdb->get_results("SELECT subject_code, subject_name FROM {$wpdb->prefix}sba_subjects ORDER BY subject_name");
            foreach($subjects as $sub){
                echo "<option value='{$sub->subject_code}'>{$sub->subject_name}</option>";
            }
    echo "</select><br><br>
        <input id='latitude' name='latitude' placeholder='Latitude'><br><br>
        <input id='longitude' name='longitude' placeholder='Longitude'><br><br>
        <input id='location' name='location' placeholder='Location'><br><br>
        <input type='hidden' name='selfie' id='selfie'>
        <video id='cam' autoplay width='280'></video><br><br>
        <button name='sba_mark'>Mark Attendance</button>
    </form>

    <script>
    navigator.mediaDevices.getUserMedia({video:true}).then(stream => cam.srcObject = stream);
    navigator.geolocation.getCurrentPosition(async pos => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;
        try {
            const resp = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
            const data = await resp.json();
            document.getElementById('location').value = data.display_name || '';
        } catch(e){
            console.error('Reverse geocoding failed', e);
            document.getElementById('location').value = '';
        }
    });

    let snap = document.createElement('canvas');
    document.querySelector('form').addEventListener('submit', function() {
        let ctx = snap.getContext('2d');
        snap.width = cam.videoWidth;
        snap.height = cam.videoHeight;
        ctx.drawImage(cam, 0, 0);
        document.getElementById('selfie').value = snap.toDataURL('image/jpeg');
    });
    </script>";
}

/* =====================================================
REPORTS PAGE + EXCEL EXPORT
===================================================== */
function sba_reports_page(){
    global $wpdb;

    // Export handler
    if(isset($_POST['sba_export_excel'])){
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=attendance_" . date('Ymd_His') . ".csv");
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name','Subject','Date','Time','Lat','Lng','Location','Selfie']);

        $rows = $wpdb->get_results("
            SELECT u.name, a.subject_code, a.att_date, a.att_time,
                   a.latitude, a.longitude, a.location_name, a.selfie
            FROM {$wpdb->prefix}sba_attendance a
            JOIN {$wpdb->prefix}sba_users u ON u.code = a.code
            ORDER BY a.att_date ASC
        ");

        foreach($rows as $r){
            fputcsv($output, [
                $r->name,
                $r->subject_code,
                $r->att_date,
                $r->att_time,
                $r->latitude,
                $r->longitude,
                $r->location_name,
                $r->selfie ? 'Image Attached' : ''
            ]);
        }
        fclose($output);
        exit;
    }

    // Lightbox CSS + JS for selfies
    echo "
    <style>
    #sbaLightbox { display:none; position:fixed; z-index:9999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); text-align:center; }
    #sbaLightbox img { max-width:90%; max-height:90%; margin-top:5%; border-radius:8px; }
    #sbaLightbox:target { display:block; }
    </style>
    <div id='sbaLightbox'><img id='sbaLightboxImg' src=''></div>
    <script>
    function openLightbox(src){ document.getElementById('sbaLightboxImg').src = src; window.location.hash='sbaLightbox'; }
    </script>
    ";

    echo "<h3>Attendance Matrix</h3>";
    echo "<form method='POST'><button name='sba_export_excel'>Export to Excel</button></form><br>";

    // Table display
    echo "<table border='1' cellpadding='6'>
    <tr>
        <th>Name</th>
        <th>Subject</th>
        <th>Date</th>
        <th>Time</th>
        <th>Lat</th>
        <th>Lng</th>
        <th>Location</th>
        <th>Selfie</th>
    </tr>";

    $rows = $wpdb->get_results("
        SELECT u.name, a.subject_code, a.att_date, a.att_time,
               a.latitude, a.longitude, a.location_name, a.selfie
        FROM {$wpdb->prefix}sba_attendance a
        JOIN {$wpdb->prefix}sba_users u ON u.code = a.code
        ORDER BY a.att_date ASC
    ");

    foreach($rows as $r){
        echo "<tr>
            <td>{$r->name}</td>
            <td>{$r->subject_code}</td>
            <td>{$r->att_date}</td>
            <td>{$r->att_time}</td>
            <td>{$r->latitude}</td>
            <td>{$r->longitude}</td>
            <td>".($r->location_name ? $r->location_name : 'N/A')."</td>
            <td>";
        if(!empty($r->selfie)){
            echo "<img src='{$r->selfie}' style='width:60px;height:60px;object-fit:cover;border-radius:5px;cursor:pointer;' onclick='openLightbox(this.src)'>";
        } else echo "N/A";
        echo "</td></tr>";
    }
    echo "</table>";
}
