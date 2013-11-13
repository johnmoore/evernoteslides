<?PHP
define("EVERNOTE_LIBS", dirname(__FILE__) . DIRECTORY_SEPARATOR . "lib");
ini_set("include_path", ini_get("include_path") . PATH_SEPARATOR . EVERNOTE_LIBS);

require_once 'Evernote/Client.php';
require_once 'packages/Types/Types_types.php';

use EDAM\Error\EDAMSystemException,
    EDAM\Error\EDAMUserException,
    EDAM\Error\EDAMErrorCode,
    EDAM\Error\EDAMNotFoundException;
use Evernote\Client;
use EDAM\NoteStore\NoteFilter;
use EDAM\NoteStore\NotesMetadataResultSpec;

function getTemporaryCredentials()
{
    global $lastError, $currentStatus;
    try {
        $client = new Client(array(
            'consumerKey' => OAUTH_CONSUMER_KEY,
            'consumerSecret' => OAUTH_CONSUMER_SECRET,
            'sandbox' => SANDBOX
        ));
        $requestTokenInfo = $client->getRequestToken(getCallbackUrl());
        if ($requestTokenInfo) {
            $_SESSION['requestToken'] = $requestTokenInfo['oauth_token'];
            $_SESSION['requestTokenSecret'] = $requestTokenInfo['oauth_token_secret'];
            $currentStatus = 'Obtained temporary credentials';

            return TRUE;
        } else {
            $lastError = 'Failed to obtain temporary credentials.';
        }
    } catch (OAuthException $e) {
        $lastError = 'Error obtaining temporary credentials: ' . $e->getMessage();
    }

    return FALSE;
}

function handleCallback()
{
    global $lastError, $currentStatus;
    if (isset($_GET['oauth_verifier'])) {
        $_SESSION['oauthVerifier'] = $_GET['oauth_verifier'];
        $currentStatus = 'Content owner authorized the temporary credentials';
        return TRUE;
    } else {
        $lastError = 'Content owner did not authorize the temporary credentials';
        return FALSE;
    }
}

function getTokenCredentials()
{
    global $lastError, $currentStatus;

    if (isset($_SESSION['accessToken'])) {
        $lastError = 'Temporary credentials may only be exchanged for token credentials once';
        return FALSE;
    }

    try {
        $client = new Client(array(
            'consumerKey' => OAUTH_CONSUMER_KEY,
            'consumerSecret' => OAUTH_CONSUMER_SECRET,
            'sandbox' => SANDBOX
        ));
        $accessTokenInfo = $client->getAccessToken($_SESSION['requestToken'], $_SESSION['requestTokenSecret'], $_SESSION['oauthVerifier']);
        if ($accessTokenInfo) {
            $user = $client->getUserStore()->getUser();
            $_SESSION['webApiUrlPrefix'] = $accessTokenInfo['edam_webApiUrlPrefix'];
            $_SESSION['name'] = (strlen($user->name) > 0 ? $user->name : $user->username);
            $_SESSION['accessToken'] = $accessTokenInfo['oauth_token'];
            $_SESSION['authenticated'] = true;
            $currentStatus = 'Exchanged the authorized temporary credentials for token credentials';
            return TRUE;
        } else {
            $lastError = 'Failed to obtain token credentials.';
        }
    } catch (OAuthException $e) {
        $lastError = 'Error obtaining token credentials: ' . $e->getMessage();
    }

    return FALSE;
}

function resetSession()
{
    foreach ($_SESSION as $k => $v) {
        unset($_SESSION[$k]);
    }
}

function getCallbackUrl()
{
    $thisUrl = (empty($_SERVER['HTTPS'])) ? "http://" : "https://";
    $thisUrl .= $_SERVER['SERVER_NAME'];
    $thisUrl .= ($_SERVER['SERVER_PORT'] == 80 || $_SERVER['SERVER_PORT'] == 443) ? "" : (":".$_SERVER['SERVER_PORT']);
    $thisUrl .= $_SERVER['SCRIPT_NAME'];
    $thisUrl .= '?action=callback';

    return $thisUrl;
}

function getAuthorizationUrl()
{
    $client = new Client(array(
        'consumerKey' => OAUTH_CONSUMER_KEY,
        'consumerSecret' => OAUTH_CONSUMER_SECRET,
        'sandbox' => SANDBOX
    ));

    return $client->getAuthorizeUrl($_SESSION['requestToken']);
}

function trimTitle($title) {
    if (strlen($title) > 18) {
        $title = substr($title, 0, 15)."...";
    }
    return $title;
}

function getEvernoteImages()
{
    global $lastError, $currentStatus;

    try {
        $accessToken = $_SESSION['accessToken'];
        $client = new Client(array(
                    'token' => $accessToken,
                    'sandbox' => SANDBOX
        ));
        $ns = $client->getNoteStore();
        $notebooks = $ns->listNotebooks();
        $result = array();
        $images = array();
        if (!empty($notebooks)) {
            foreach ($notebooks as $notebook) {
                $spec = new NotesMetadataResultSpec(array('includeLargestResourceMime' => true, 'includeTitle' => true));
                $filter = new NoteFilter(array('guid' => $notebook->guid));
                $result = $client->getNoteStore()->findNotesMetadata($filter, 0, 100, $spec);
                foreach ($result->notes as $note) {
                    $notedata = $ns->getNote($note->guid, false, false, true, false);
                    if (!$notedata->resources) continue;
                    foreach ($notedata->resources as $resource) {
                        if (substr($resource->mime, 0, 5) == "image" && $resource->width > 240 && $resource->height > 150) {
                            $images[] = array("thumb" => $_SESSION['webApiUrlPrefix']."res/thm/".$resource->guid, "url" => $_SESSION['webApiUrlPrefix']."res/".$resource->guid, "title" => trimTitle($note->title));
                        }
                    }
                }
            }
        }
        $currentStatus = 'Successfully listed content owner\'s notebooks';

        return $images;
    } catch (EDAMSystemException $e) {
        if (isset(EDAMErrorCode::$__names[$e->errorCode])) {
            $lastError = 'Error listing notebooks: ' . EDAMErrorCode::$__names[$e->errorCode] . ": " . $e->parameter;
        } else {
            $lastError = 'Error listing notebooks: ' . $e->getCode() . ": " . $e->getMessage();
        }
    } catch (EDAMUserException $e) {
        if (isset(EDAMErrorCode::$__names[$e->errorCode])) {
            $lastError = 'Error listing notebooks: ' . EDAMErrorCode::$__names[$e->errorCode] . ": " . $e->parameter;
        } else {
            $lastError = 'Error listing notebooks: ' . $e->getCode() . ": " . $e->getMessage();
        }
    } catch (EDAMNotFoundException $e) {
        if (isset(EDAMErrorCode::$__names[$e->errorCode])) {
            $lastError = 'Error listing notebooks: ' . EDAMErrorCode::$__names[$e->errorCode] . ": " . $e->parameter;
        } else {
            $lastError = 'Error listing notebooks: ' . $e->getCode() . ": " . $e->getMessage();
        }
    } catch (Exception $e) {
        $lastError = 'Error listing notebooks: ' . $e->getMessage();
    }

    return FALSE;
}

function isAuthenticated() {
    return (isset($_SESSION['authenticated']) ? $_SESSION['authenticated'] : false);
}
?>