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

global $ssquizExtensionVersion;
$ssquizExtensionVersion = '1.0.0';

 function displayName($atts){
     global $wpdb;
     $user_id = get_current_user_id();
     $a = shortcode_atts(array(
         'id' => 0
     ),$atts);
    $quiz_id = $a['id'];
    if($a['id'] == 0)
        return;
    else {
        $row = $wpdb->get_row("SELECT post_title,guid FROM {$wpdb->posts} WHERE post_status='publish' AND post_type='page' AND post_title IN (SELECT name FROM {$wpdb->base_prefix}ssquiz_quizzes WHERE id=".$quiz_id.")");
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
            $result = '<span class="quizName passed"><a href="'.$row->guid.'">'.$row->post_title.'</a></span>';
        } elseif(null != $quiz_status && $quiz_status->question_offset >= $quiz_status->total && ($quiz_status->questions_right/$quiz_status->total)*100 < $pass_percent){
            /*failed */
            $result = '<span class="quizName failed"><a href="'.$row->guid.'">'.$row->post_title.'</a></span>';
        }elseif((null != $quiz_status && $quiz_status->question_offset < $quiz_status->total) || (null == $quiz_status && null != $prereq_status && $prereq_status->question_offset >= $prereq_status->total && ($prereq_status->questions_right/$prereq_status->total)*100 >= $pass_percent) || (null == $quiz_status && $quiz_meta->prerequisites <= 0)){
            $result = '<span class="quizName active"><a href="'.$row->guid.'">'.$row->post_title.'</a></span>';
            /*passed prereq && not finished*/
        }else{
            // not concerned (gray and un-clickable) = do prereq first
            $result = '<span class="quizName inactive">'.$row->post_title.'</span>';
        }
    }
    return $result;
 }
 add_shortcode('ssquizExtension','displayName');

 function diaplayCurriculums($atts){
    global $wpdb;
    $result = '';
	$user_id = get_current_user_id();
    $curricula = $wpdb->get_results("SELECT name from {$wpdb->base_prefix}groups_group natural join {$wpdb->base_prefix}groups_user_group where group_id != 1 and user_id=".$user_id);
	if(null == $curricula || count($curricula) <= 0){
		$result = "<h2>Sorry! You are not enrolled in any curriculum yet.</h2>";
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
            $curriculaNames[] = $curriculum->name;
        }
        $diff1 = array_diff($storedArray, $curriculaNames);
        $diff2 = array_diff($curriculaNames, $storedArray);
        if(!empty($diff1) || !empty($diff2)){
            if(!empty($diff1)){
                // delete
                foreach($diff1 as $cn)
                    $wpdb->delete('{$wpdb->base_prefix}self_ssquiz_extension_curriculum',array('user_id' => $user_id, 'curriculum_name' => $cn),array('%d','%s'));
            }
            if(!empty($diff2)){
                // insert
                foreach($diff2 as $cn)
                    $wpdb->insert('{$wpdb->base_prefix}self_ssquiz_extension_curriculum',array('user_id' => $user_id, 'curriculum_name' => $cn, 'status' => 'n'),array('%d','%s','%s'));
            }
        }
		foreach($curricula as $curriculum){
			$link = $wpdb->get_var("SELECT guid FROM {$wpdb->base_prefix}posts WHERE post_title='{$curriculum->name}' AND post_status='publish' AND post_type='page'");
			if(!empty($activeCurriculum) && strcasecmp($activeCurriculum, $curriculum->name)==0)
                $result = '<span class="selfCurriculum active"><a data-link="'.$link.'">'.$curriculum->name.'</a></span>';
            elseif (!empty($activeCurriculum)) {
                $result = '<span class="selfCurriculum inactive">'.$curriculum->name.'</span>';
            } else{
                if(in_array($curriculum->name, $finishedArray))
                    $result = '<span class="selfCurriculum passed"><a href="#">'.$curriculum->name.'</a></span>';
                else
                    $result = '<span class="selfCurriculum active"><a data-link="'.$link.'">'.$curriculum->name.'</a></span>';
            }
		}
	}
    addCurriculumBody($result);
    return $result;
 }
add_shortcode('ssquizExtensionCurriculums','diaplayCurriculums');

function addCurriculumBody(&$body){
    $body .= '<script>
$(".active a").click(function(){
    if(confirm("Are you sure you want to opt for this curriculum? You cannot change the curriculum before it is complete.")){
        $(this).attr("href", $(this).data("link"));
    }
    else{
        return false;
    }
});
</script>';
}

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
    delete_option('ssquizExtensionVersion');
    $wpdb->query("
		DROP TABLE IF EXISTS {$wpdb->base_prefix}self_ssquiz_extension_curriculum;
	");
}

register_activation_hook( __FILE__, 'extensionInstall' );
register_uninstall_hook( __FILE__, 'extensionUninstall' );

 ?>