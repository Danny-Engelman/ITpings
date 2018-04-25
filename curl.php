<?php
// Here is the data we will be sending to the service
$some_data = array(
    'value1' => 'v1',
    'value2' => 'v2',
    'value3' => 'v3',
    'name' => 'Danny'
);

$curl = curl_init();
// You can also set the URL you want to communicate with by doing this:
// $curl = curl_init('http://localhost/echoservice');

// We POST the data
curl_setopt($curl, CURLOPT_POST, 1);
// Set the url path we want to call
curl_setopt($curl, CURLOPT_URL, 'https://maker.ifttt.com/trigger/ttn_button_clicked/with/key/cwmYqAicGxSfDWLOO6MZSa');
// Make it so the data coming back is put into a string
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
// Insert the data
curl_setopt($curl, CURLOPT_POSTFIELDS, $some_data);

// You can also bunch the above commands into an array if you choose using: curl_setopt_array

// Send the request
$result = curl_exec($curl);

// Get some cURL session information back
$info = curl_getinfo($curl);
echo 'content type: ' . $info['content_type'] . '<br />';
echo 'http code: ' . $info['http_code'] . '<br />';

// Free up the resources $curl is using
curl_close($curl);

echo $result;