<?php
$url = 'http://localhost/finalweb/api/student_api.php';
$data = json_decode(file_get_contents(__DIR__.'/payload.json'), true);
$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
        'ignore_errors' => true,
    ],
];
$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
echo $result;
if (isset($http_response_header)) {
    echo "\nHTTP Headers:\n" . implode("\n", $http_response_header);
}
