<?php

require_once 'vendor/autoload.php';
require_once 'models/SecretModel.php';
require_once 'utils.php';


(Dotenv\Dotenv::createImmutable(__DIR__))->safeLoad();

$HOST = $_ENV['HOST'];
$USERNAME = $_ENV['USERNAME'];
$PASSWORD = $_ENV['PASSWORD'];
$DATABASE = $_ENV['DATABASE'];


date_default_timezone_set('Europe/Budapest');

const APPLICATION_JSON = 'application/json';

$connection = mysqli_connect($HOST, $USERNAME, $PASSWORD, $DATABASE);

$secrets = new SecretModel($connection);

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
    $acceptHeader = (!isset(getallheaders()['Accept'])) ? APPLICATION_JSON : getallheaders()['Accept'];

    if (!$connection)
    {
        $response = ['Error' => 'Failed to connect to the database.'];
        http_response_code(405);
        if ($acceptHeader == APPLICATION_JSON) { echo json_encode($response); } else { echo xml_encode('Error', $response); }
        exit();
    }

    if (!isset($_POST['secret']))
    {
        $response = ['error' => 'Your request did not contain a secret!'];
        http_response_code(405);
        if ($acceptHeader == APPLICATION_JSON) { echo json_encode($response); } else { echo xml_encode('Error', $response); }
        exit();
    }

    if (!isset($_POST['expireAfter']))
    {
        $response = ['error' => 'Your request must have an expireAfter field!'];
        http_response_code(405);
        if ($acceptHeader == APPLICATION_JSON) { echo json_encode($response); } else { echo xml_encode('Error', $response); }
        exit();
    }

    if (!isset($_POST['expireAfterViews']) || intval($_POST['expireAfterViews']) <= 0)
    {
        $response = ['error' => 'Your request must have an expireAfterViews field which is bigger than 0!'];
        http_response_code(405);
        if ($acceptHeader == APPLICATION_JSON) { echo json_encode($response); } else { echo xml_encode('Error', $response); }
        exit();
    }

    $secret = $_POST['secret'];
    $createdAt  = new DateTime('now');
    $expiresAt = new DateTime(date('Y-m-d H:i:s', PHP_INT_MAX));
    $remainingViews = null;

    if (intval($_POST['expireAfter']) != 0)
    {
        try {
            $expiresAt = (new DateTime('now'))->modify('+' . $_POST['expireAfter'] . ' minute');
        } catch (Exception $_) {
            $response = ['error' => 'Failed to parse the provided field data field: expireAfter!'];
            http_response_code(405);
            if ($acceptHeader == APPLICATION_JSON) {
                echo json_encode($response);
            } else {
                echo xml_encode('Error', $response);
            }
            exit();
        }
    }

    try
    {
        $remainingViews = intval($_POST['expireAfterViews']);
    }
    catch (Exception $_)
    {
        $response = ['error' => 'Failed to parse the provided field data field: expireAfterViews!'];
        http_response_code(405);
        if ($acceptHeader == APPLICATION_JSON) { echo json_encode($response); } else { echo xml_encode('Error', $response); }
        exit();
    }

    $key = base64url_encode(base64_encode(openssl_random_pseudo_bytes(30)));

    $encryptedSecret = base64_encode(encrypt($secret, $key));

    $hash = md5($secret.$createdAt->format('Y-m-d H:i:s').$expiresAt->format('Y-m-d H:i:s').$remainingViews);

    if (!$secrets->create($hash, $encryptedSecret, $remainingViews, $createdAt, $expiresAt))
    {
        $response = ['error' => 'Failed to save the file into the database.'];
        http_response_code(405);
        if ($acceptHeader == APPLICATION_JSON) { echo json_encode($response); } else { echo xml_encode('Error', $response); }
        exit();
    }

    $response = ['hash' => $hash, 'secretText' => $secret, 'createdAt' => $createdAt->format('Y-m-d H:i:s'),
        'expiresAt' => $expiresAt->format('Y-m-d H:i:s'), 'remainingViews' => $remainingViews, 'key' => $key];
    http_response_code(200);
    if ($acceptHeader == APPLICATION_JSON) { echo json_encode($response); } else { echo xml_encode('Secret', $response); }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'GET')
{
    $acceptHeader = (!isset(getallheaders()['Accept'])) ? APPLICATION_JSON : getallheaders()['Accept'];
    $authorizationHeader = (!isset(getallheaders()['Authorization'])) ? null : getallheaders()['Authorization'];

    if (!$authorizationHeader)
    {
        $response = ['error' => 'Your request did not contain an Authorization header.'];
        http_response_code(403);
        if ($acceptHeader == APPLICATION_JSON) { echo json_encode($response); } else { echo xml_encode('Error', $response); }
        exit();
    }

    if (!$connection) {
        $response = ['Error' => 'Failed to connect to the database.'];
        http_response_code(500);
        if ($acceptHeader == APPLICATION_JSON) { echo json_encode($response); } else { echo xml_encode('Error', $response); }
        exit();
    }

    if (!isset($_GET['hash']))
    {
        $response = ['error' => 'You request did not contain a hash!'];
        http_response_code(405);
        if ($acceptHeader == APPLICATION_JSON) { echo json_encode($response); } else { echo xml_encode('Error', $response); }
        exit();
    }

    $key = $authorizationHeader; $hash = $_GET['hash'];

    $record = $secrets->get($hash);

    if (!$record)
    {
        $response = ['error' => 'There is no secret with the provided hash value.'];
        http_response_code(405);
        if ($acceptHeader == APPLICATION_JSON) { echo json_encode($response); } else { echo xml_encode('Error', $response); }
        exit();
    }

    $removed = false;

    if ($record['remainingViews'] != null) $secrets->update($record['hash'], 'remainingViews', $record['remainingViews'] - 1);

    if ((intval($record['remainingViews']) - 1) == 0)
    {
        if ($secrets->remove($record['hash'])) $removed = true;
    }

    $now = $expiresAt = new DateTime('now');

    try
    {
        $expiresAt= new DateTime($record['expiresAt']);
    }
    catch (Exception $_)
    {
        $response = ['' => 'Failed to parse the stored availability datetime.'];
        http_response_code(500);
        if ($acceptHeader == APPLICATION_JSON) { echo json_encode($response); } else { echo xml_encode('Error', $response); }
        exit();
    }

    if ($expiresAt <= $now)
    {
        if ($secrets->remove($record['hash'])) $removed = true;
    }

    $encryptedSecret = base64_decode($record['secret']);

    $decryptedSecret = decrypt($encryptedSecret, $key);

    if ($decryptedSecret == '')
    {
        $response = ['error' => 'The provided key was invalid!'];
        http_response_code(405);
        if ($acceptHeader == APPLICATION_JSON) { echo json_encode($response); } else { echo xml_encode('Error', $response); }
        exit();
    }

    $response = ['hash' => $record['hash'], 'secretText' => $decryptedSecret, 'createdAt' => $record['createdAt'],
        'expiresAt' => $record['expiresAt'], 'remainingViews' => intval($record['remainingViews']) - 1, 'key' => $key];
    http_response_code(200);
    if ($acceptHeader == APPLICATION_JSON) { echo json_encode($response); } else { echo xml_encode('Secret', $response); }
    exit();
}
