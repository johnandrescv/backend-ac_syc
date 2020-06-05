<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
    $notification = array(
        "to" => "dgC8hmfIWG8:APA91bFvuHay31rhAmHFk8QIQ_BywjW8r3NnI5L302BjG9kfsNK83AS2VU5qpS6pRQCrEGEByeCOvR0WQEpDX_Lo1RZLr4cbOJYxpYO1ACBmeFU-ylk3rpKpqGrIkgk3dWYuo30ja8oP",
        "notification" => [
            "body" => "Alerta",
            "title" => "Maxlink",
            'sound' => 'default',
        ],
        "data" => [
            "body" => "Alerta",
            "title" => "Maxlink",
            'sound' => 'default',
        ]
    );
	$data = json_encode($notification);
    //FCM API end-point
    $url = 'https://fcm.googleapis.com/fcm/send';
    //api_key in Firebase Console -> Project Settings -> CLOUD MESSAGING -> Server key
    $server_key = 'AAAA1UVfifQ:APA91bEMvXyaXhDDPOyxxBqpIYvlvNk1ItQQTyIJkQf6yW0JCOLgl6C-sZrUFpQWk-rt3RIT7rOVHglqOkNsC13fIeM_C8UMIoYx64NkWsYaJxzuW4gh-J_OVfI3ikWv5zjef7PXhvnu';
    //header with content_type api key
    $headers = array(
        'Content-Type:application/json',
        'Authorization:key='.$server_key
    );
    //CURL request to route notification to FCM connection server (provided by Google)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $result = curl_exec($ch);
    var_dump($result);
    if ($result === FALSE) {
        die('Oops! FCM Send Error: ' . curl_error($ch));
    }
    curl_close($ch);
?>