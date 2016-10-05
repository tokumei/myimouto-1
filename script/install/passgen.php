<?php
require_once('libraries/PasswordHash.php');
$HASHER = new PasswordHash(8, FALSE);
$USERS = file_get_contents('config/.htusers.php');
$EXTPASSWORDHASH = $HASHER->HashPassword($argv[2]);
$USERS = str_replace("__username__", $argv[1], $USERS);
$USERS = str_replace("__passwordhash__", $EXTPASSWORDHASH, $USERS);
file_put_contents('config/.htusers.php', $USERS);
?>