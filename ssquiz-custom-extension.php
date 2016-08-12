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
                $prereq_status = $wpdb->get_row("SELECT questions_right,total,question_offset FROM {$wpdb->base_prefix}self_ssquiz_response_history AS s JOIN {$wpdb->base_prefix}ssquiz_history AS h ON s.quiz_id=h.quiz_id WHERE s.user_id=".$user_id." AND s.quiz_id=".$quiz_id." order by timestamp desc limit 1");
            }
        };
        $pass_percent = 85; 
        if (null != $quiz_status && $quiz_status->question_offset >= $quiz_status->total && $quiz_status->questions_right/$quiz_status->total >= $pass_percent) {
            /*passed*/
            $result = '<p class="quizName passed"><a href="'.$row->guid.'">'.$row->post_title.'</a></p>';
        } elseif(null != $quiz_status && $quiz_status->question_offset >= $quiz_status->total && $quiz_status->questions_right/$quiz_status->total < $pass_percent){
            /*failed */
            $result = '<p class="quizName failed"><a href="'.$row->guid.'">'.$row->post_title.'</a></p>';
        }elseif((null != $quiz_status && $quiz_status->question_offset < $quiz_status->total) || (null == $quiz_status && null != $prereq_status && $prereq_status->question_offset >= $prereq_status->total && $prereq_status->questions_right/$prereq_status->total >= $pass_percent) || (null == $quiz_status && $quiz_meta->prerequisites <= 0)){
            $result = '<p class="quizName active"><a href="'.$row->guid.'">'.$row->post_title.'</a></p>';
            /*passed prereq && not finished*/
        }else{
            // not concerned (gray and un-clickable) = do prereq first
            $result = '<p class="quizName inactive">'.$row->post_title.'</p>';
        }
    }
    return $result;
 }
 add_shortcode('ssquizExtension','displayName');
 ?>