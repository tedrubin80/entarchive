<?php
/**
 * Add Media Item Page
 * File: public/user_add_item.php
 * Allows users to add new media items to their collection
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || !