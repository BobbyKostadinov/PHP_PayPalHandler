<?php 
require_once 'PayPalHandler.php';
$handler = new PayPalHandler();
//$handler->test = true; - set this to use with sandbox testing tool of IPN requests
$options = array(
		'url' => 'http://track.paydot.com',
		'sid' => 'Your Site ID',
		'username' => 'Your Username',
		'password' => 'Your Password'
);

$handler->trackSale($options);