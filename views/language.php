<?php
session_start();

if(!isset($_SESSION['language']))
{
    $_SESSION['language'] = 'FR';
}
else if($_SESSION['language'] == 'FR')
{
    $_SESSION['language'] = 'EN';
}
else
{
    $_SESSION['language'] = 'FR';
}

$returnPage = isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] != ""
            ? $_SERVER['HTTP_REFERER']
            : "https://www.lacombedominique.com";
header("location: ".$returnPage);
?>