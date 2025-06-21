<?php
require_once '../config.php';
$type = $_GET['type'] ?? 'movie';
$identifier = $_GET['id'] ?? '';

switch ($type) {
  case 'book':
    include 'books.php'; break;
  case 'comic':
    include 'comics.php'; break;
  case 'music':
    include 'music.php'; break;
  default:
    include 'movies.php'; break;
}
