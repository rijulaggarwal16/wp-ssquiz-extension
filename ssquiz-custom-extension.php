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
        $name = $wpdb->get_var("SELECT name FROM {$wpdb->base_prefix}ssquiz_quizzes WHERE id=".$a['id']);
        echo '<p class="quizName">'.$name.'</p>';
    }
 }
 add_shortcode('ssquizExtension','displayName');
 ?>