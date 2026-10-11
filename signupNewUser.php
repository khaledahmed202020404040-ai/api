<?php
// Enable error logging
error_log("signupNewUser.php called");
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . file_get_contents('php://input'));
error_log("GET data: " . json_encode($_GET));
error_log("POST array: " . json_encode($_POST));

require __DIR__ . '/account/UserAuth/Register.php';
