<?php
/**
 * File Name:   test.php
 * Description: Test script to validate the implementation.
 */

// Load WordPress environment
if (file_exists(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php')) {
    require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');
} else {
    // die("WordPress environment not found.");
}

// Include the activation file
// require_once 'includes/activation.php';

echo "Test script loaded.";
?>
