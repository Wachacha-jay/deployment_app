<?php
/**
 * Common utility functions
 */

if (!function_exists('sanitize')) {
    function sanitize($string) {
        return htmlspecialchars(strip_tags(trim($string)), ENT_QUOTES, 'UTF-8');
    }
}

?>
