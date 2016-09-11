<?php
/**
 * Plugin Name: SSQuiz Custom Extension
 * Plugin URI: http://rijulaggarwal.wordpress.com
 * Description: This plugin is an extension to the ssquiz plugin to add some features
 * Version: 1.0.0
 * Author: Rijul Aggarwal
 * Author URI: http://rijulaggarwal.wordpress.com
 * License: GPL2
 */

define( 'SSQUIZ_EXTENSION_URL', plugin_dir_url( __FILE__ ) . '/' );

global $ssquizExtensionVersion;
$ssquizExtensionVersion = '1.0.0';

 function displayName($atts){
     global $wpdb;
     $user_id = get_current_user_id();
     $result = "";
     if(false === checkValidAccess($user_id, get_the_title()))
        return;
     $a = shortcode_atts(array(
         'id' => 0
     ),$atts);
    $quiz_id = $a['id'];
    if($a['id'] == 0)
        return;
    else {
        $defultNameSearch = "SELECT name FROM {$wpdb->base_prefix}ssquiz_quizzes WHERE id=".$quiz_id;
        $result .= printQuizName($user_id, $quiz_id, $defultNameSearch, true);
    }
    return $result;
 }
 add_shortcode('ssquizExtension','displayName');

 function printQuizName($user_id, $quiz_id, $name, $subQuery = false){
    global $wpdb;
    if($subQuery)
        $row = $wpdb->get_row("SELECT post_title,guid FROM {$wpdb->posts} WHERE post_status='publish' AND post_type='page' AND post_title IN (".$name.")");
    else
        $row = $wpdb->get_row("SELECT post_title,guid FROM {$wpdb->posts} WHERE post_status='publish' AND post_type='page' AND post_title='".$name."'");
        if(null == $row)
            return;
        $quiz_status = $wpdb->get_row("SELECT questions_right,total,question_offset FROM {$wpdb->base_prefix}self_ssquiz_response_history AS s JOIN {$wpdb->base_prefix}ssquiz_history AS h ON s.quiz_id=h.quiz_id WHERE s.user_id=".$user_id." AND s.quiz_id=".$quiz_id." order by timestamp desc limit 1");
        if(null == $quiz_status){
            $quiz_meta = $wpdb->get_var("SELECT meta FROM {$wpdb->base_prefix}ssquiz_quizzes WHERE id=".$quiz_id);
            $quiz_meta = unserialize($quiz_meta);
            if($quiz_meta->prerequisites > 0){
                $prereq_status = $wpdb->get_row("SELECT questions_right,total,question_offset FROM {$wpdb->base_prefix}self_ssquiz_response_history AS s JOIN {$wpdb->base_prefix}ssquiz_history AS h ON s.quiz_id=h.quiz_id WHERE s.user_id=".$user_id." AND s.quiz_id=".$quiz_meta->prerequisites." order by timestamp desc limit 1");
            }
        };
        $pass_percent = 85; 
        if (null != $quiz_status && $quiz_status->question_offset >= $quiz_status->total && ($quiz_status->questions_right/$quiz_status->total)*100 >= $pass_percent) {
            /*passed*/
            $result = '<p class="quizName passed"><a href="'.$row->guid.'">'.$row->post_title.'</a></p>';
        } elseif(null != $quiz_status && $quiz_status->question_offset >= $quiz_status->total && ($quiz_status->questions_right/$quiz_status->total)*100 < $pass_percent){
            /*failed */
            $result = '<p class="quizName failed"><a href="'.$row->guid.'">'.$row->post_title.'</a></p>';
        }elseif((null != $quiz_status && $quiz_status->question_offset < $quiz_status->total) || (null == $quiz_status && null != $prereq_status && $prereq_status->question_offset >= $prereq_status->total && ($prereq_status->questions_right/$prereq_status->total)*100 >= $pass_percent) || (null == $quiz_status && $quiz_meta->prerequisites <= 0)){
            $result = '<p class="quizName active"><a href="'.$row->guid.'">'.$row->post_title.'</a></p>';
            /*passed prereq && not finished*/
        }else{
            // not concerned (gray and un-clickable) = do prereq first
            $result = '<p class="quizName inactive">'.$row->post_title.'</p>';
        }
    return $result;
 }

 function checkValidAccess($user_id, $curriculum_name){
     global $wpdb;
     $activeName = $wpdb->get_var("SELECT curriculum_name FROM {$wpdb->base_prefix}self_ssquiz_extension_curriculum WHERE user_id=".$user_id." AND status='y'");
     if(!empty($activeName) && strcasecmp($activeName, $curriculum_name) == 0){
         return true;
     }
     return false;
 }

 function displayQuizAccessError($atts, $content = null){
     if(false === checkValidAccess(get_current_user_id(), get_the_title())){
        return '<span class="selfAccessError">'.do_shortcode($content).'</span>';
     }
     return;
 }
 add_shortcode('ssquizExtensionAccessError','displayQuizAccessError');

function displayQuizAccessGranted($atts, $content = null){
     if(true === checkValidAccess(get_current_user_id(), get_the_title())){
        return '<span class="selfAccessGranted">'.do_shortcode($content).'</span>';
     }
     return;
 }
 add_shortcode('ssquizExtensionAccessOk','displayQuizAccessGranted');

 function diaplayCurriculums($atts){
    global $wpdb;
    $curriculaResult = '<h2>Curriculums</h2>';
    $quizResult = '<h2>Individual Courses</h2>';
    $result = '';
	$user_id = get_current_user_id();
    $curricula = $wpdb->get_results("SELECT name from {$wpdb->base_prefix}groups_group natural join {$wpdb->base_prefix}groups_user_group where group_id != 1 and user_id=".$user_id);
	if(null == $curricula || count($curricula) <= 0){
		$result = "<h2>Sorry! You are not enrolled in any curriculum/course yet.</h2>";
	}else{
        $curriculaStatus = $wpdb->get_results("SELECT * FROM {$wpdb->base_prefix}self_ssquiz_extension_curriculum WHERE user_id=".$user_id);
        $storedArray = array();
        $finishedArray = array();
        foreach($curriculaStatus as $status){
            if($status->status == 'y'){
                $activeCurriculum = $status->curriculum_name;
            } elseif ($status->status == 'f') {
                $finishedArray[] = $status->curriculum_name;
            }
            $storedArray[] = $status->curriculum_name;
        }
        $curriculaNames = array();
        foreach($curricula as $curriculum){
            if(isCurriculum($curriculum->name))
                $curriculaNames[] = $curriculum->name;
        }
        $diff1 = array_diff($storedArray, $curriculaNames);
        $diff2 = array_diff($curriculaNames, $storedArray);
        if(!empty($diff1) || !empty($diff2)){
            if(!empty($diff1)){
                // delete
                foreach($diff1 as $cn){
                    $wpdb->delete($wpdb->base_prefix.'self_ssquiz_extension_curriculum',array('user_id' => $user_id, 'curriculum_name' => $cn),array('%d','%s'));
                    if(strcasecmp($activeCurriculum, $cn)==0)
                        $activeCurriculum = '';
                }
            }
            if(!empty($diff2)){
                // insert
                foreach($diff2 as $cn)
                    $wpdb->insert($wpdb->base_prefix.'self_ssquiz_extension_curriculum',array('user_id' => $user_id, 'curriculum_name' => $cn, 'status' => 'n'),array('%d','%s','%s'));
            }
        }
		foreach($curricula as $curriculum){
            if(isCurriculum($curriculum->name)){
                $link = $wpdb->get_var("SELECT guid FROM {$wpdb->base_prefix}posts WHERE post_title='{$curriculum->name}' AND post_status='publish' AND post_type='page'");
                if(!empty($activeCurriculum) && strcasecmp($activeCurriculum, $curriculum->name)==0)
                    $curriculaResult .= '<p class="selfCurriculum active"><a data-active="y" data-link="'.$link.'">'.$curriculum->name.'</a></p>';
                elseif (!empty($activeCurriculum) && in_array($curriculum->name, $finishedArray)) {
                    $curriculaResult .= '<p class="selfCurriculum passed"><a href="javascript:void(0);">'.$curriculum->name.'</a></p>';
                }
                elseif (!empty($activeCurriculum)) {
                    $curriculaResult .= '<p class="selfCurriculum inactive">'.$curriculum->name.'</p>';
                } else{
                    if(in_array($curriculum->name, $finishedArray))
                        $curriculaResult .= '<p class="selfCurriculum passed"><a href="javascript:void(0);">'.$curriculum->name.'</a></p>';
                    else
                        $curriculaResult .= '<p class="selfCurriculum active"><a data-active="n" data-link="'.$link.'">'.$curriculum->name.'</a></p>';
                }
            } else{
                $quiz_id = $wpdb->get_var("SELECT id FROM {$wpdb->base_prefix}ssquiz_quizzes WHERE name='{$curriculum->name}'");
                $quizResult .= printQuizName($user_id, $quiz_id, $curriculum->name);
            }
		}
	}
    $result .= $quizResult.$curriculaResult;
    addCurriculumBody($result);
    return $result;
 }
add_shortcode('ssquizExtensionCurriculums','diaplayCurriculums');

function isCurriculum($name){
    preg_match('#\((.*?)\)#', $name, $match);
    return (count($match) > 1 && !empty($match[1]));
}

function displayGradeTable($atts){
    global $wpdb;
    $user_id = get_current_user_id();
    $pass_percent = 85;
    $gradea_cutoff = 90;
    $result = '<table id="gradeTable">';
    $result .= '<tr><th>Course Name</th><th>Marks Obtained</th><th>Letter Grade</th></tr>';
    $quizzes = $wpdb->get_results("select name,total,correct from {$wpdb->base_prefix}ssquiz_quizzes as q join (SELECT h1.quiz_id,total,correct,h2.ts FROM {$wpdb->base_prefix}ssquiz_history as h1 inner JOIN (select quiz_id,user_id,max(timestamp) as ts from {$wpdb->base_prefix}ssquiz_history where user_id=".$user_id." group by quiz_id)as h2 ON h2.quiz_id = h1.quiz_id and h1.user_id = h2.user_id and h1.timestamp=h2.ts) as h on q.id=h.quiz_id order by h.ts desc");
    $i = 0;
    foreach($quizzes as $quiz){
        if($i%2 == 0)
            $result .= "<tr class='even'>";
        else
            $result .= "<tr class='odd'>";

        $grade = 'F';
        $marks = $quiz->correct/$quiz->total*100;
        $marks = round($marks,1);
        if($marks >= $gradea_cutoff)
            $grade = 'A';
        elseif($marks >= $pass_percent)
            $grade = 'B';
        $result .= "<td>".$quiz->name."</td><td>".$marks."</td><td>".$grade."</td>";
        $result .= "</tr>";
        $i++;
    }
    $result .= '</table>';
    return $result;
}
add_shortcode('ssquizExtensionGradeTable','displayGradeTable');

function ajaxOptCurriculum(){
    global $wpdb;
    $user_id = get_current_user_id();
    $cName = $_REQUEST['name'];
    $activeCount = $wpdb->get_var("SELECT count(*) FROM {$wpdb->base_prefix}self_ssquiz_extension_curriculum WHERE user_id=".$user_id." AND status='y'");
    if($activeCount <= 0){
        $wpdb->update($wpdb->base_prefix."self_ssquiz_extension_curriculum", array('status' => 'y'), array('user_id' => $user_id, 'curriculum_name' => $cName), array('%s'), array("%d","%s"));
    }
}
add_action('wp_ajax_ajaxOptCurriculum', 'ajaxOptCurriculum');

function addCurriculumBody(&$body){
    $body .= '<script>
    jQuery(".selfCurriculum.active a").click(function($){
        var c = jQuery(this);
        if(jQuery(this).data("active") == "y"){
             location.href = c.data("link");
        }
        else if(confirm("Are you sure you want to opt for this curriculum? You cannot change the curriculum before it is complete.")){
            jQuery.post(ssquizExtension.ajaxurl, {
                action: "ajaxOptCurriculum",
                name: c.html()
            }).done(function(data){
                location.href = c.data("link");
            }).fail(function(){
                alert("Could not opt for this curriculum. Please contact the administrator.");
            });
        }
        else{
            return false;
        }
    });
    </script>';
}

function enqueueExtensionScripts(){
    wp_enqueue_script('jquery');
    wp_localize_script( 'jquery', 'ssquizExtension', array(
		'ajaxurl' => admin_url( 'admin-ajax.php' )
	));

    wp_enqueue_style( 'default_style', SSQUIZ_EXTENSION_URL.'css/default-style.css' );
}
add_action( 'wp_enqueue_scripts', 'enqueueExtensionScripts' );

function extensionInstall(){
    global $ssquizExtensionVersion;
    global $wpdb;

    $installedVersion = get_option('ssquizExtensionVersion');
    if($installedVersion != $ssquizExtensionVersion){
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->base_prefix}self_ssquiz_extension_curriculum (
                    curriculum_id int(11) NOT NULL AUTO_INCREMENT,
                    user_id int(11) NOT NULL,
                    curriculum_name varchar(50) NOT NULL,
                    status enum('y','n','f') NOT NULL,
                    PRIMARY KEY (curriculum_id,user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";

        $wpdb->query($sql);
        update_option('ssquizExtensionVersion',$ssquizExtensionVersion);
    }
}

function extensionUninstall(){
    global $wpdb;
    delete_option('ssquizExtensionVersion');
    $wpdb->query("
		DROP TABLE IF EXISTS {$wpdb->base_prefix}self_ssquiz_extension_curriculum;
	");
}

register_activation_hook( __FILE__, 'extensionInstall' );
register_uninstall_hook( __FILE__, 'extensionUninstall' );

 ?>