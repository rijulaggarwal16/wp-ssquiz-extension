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
     $a = shortcode_atts(array(
         'id' => 0
     ),$atts);
     if($a['id'] == 0)
        return;
    else {
        $row = $wpdb->get_row("SELECT post_title,guid FROM {$wpdb->posts} WHERE post_status='publish' AND post_type='page' AND post_title IN (SELECT name FROM {$wpdb->base_prefix}ssquiz_quizzes WHERE id=".$a['id'].")");
        echo '<p class="quizName"><a href="'.$row->guid.'">'.$row->post_title.'</a></p>';
    }
 }
 add_shortcode('ssquizExtension','displayName');
 ?>