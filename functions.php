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
use EDAM\NoteStore\NoteFilter,
    EDAM\NoteStore\NotesMetadataResultSpec;

function getTemporaryCredentials()
{
    global $lastError;
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
            $_SESSION['notes'] = array();
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

function isValidResource($resource) {
    if (substr($resource->mime, 0, 5) == "image" && $resource->width >= MIN_WIDTH && $resource->height >= MIN_HEIGHT) {
        return true;
    } else {
        return false;
    }
}

function getAllNotebookImages($notebooks, $cache=true) {
    if (!$notebooks)
        return false;        
    $images = array();
    if (!empty($notebooks)) {
        foreach ($notebooks as $notebook) {
            $result = getNotebookImages($notebook, $cache);
            if ($result) {
                $images = array_merge($images, $result);
            }
        }
    }
    return $images;
}

function getNotebookNotes($notebook, $cache=true) {
    if ($cache && isset($_SESSION['notebooks'][$notebook->guid]->notes))
        return $_SESSION['notebooks'][$notebook->guid]->notes;
    try {
        $client = getClient();
        $spec = new NotesMetadataResultSpec(array('includeLargestResourceMime' => true, 'includeTitle' => true));
        $filter = new NoteFilter(array('guid' => $notebook->guid));
        $result = $client->getNoteStore()->findNotesMetadata($filter, 0, MAX_NOTES, $spec);
        $_SESSION['notebooks'][$notebook->guid]->notes = $result->notes;
        return $result->notes;
    } catch (Exception $e) {
        $lastError = 'Error listing notebooks: ' . $e->getMessage();
    }
    return false;
}

function getNote($guid, $cache=true) {
    if ($cache && isset($_SESSION['notes'][$guid]))
        return $_SESSION['notes'][$guid];
    $client = getClient();
    $note = $client->getNoteStore()->getNote($guid, false, false, false, false);
    $_SESSION['notes'][$guid] = $note;
    return $note;
}

function getNotebookImages($notebook, $cache=true) {
    if (!$notebook)
        return false;
    $notes = getNotebookNotes($notebook, $cache);
    if (!$notes)
        return false;
    $images = array();

    try {
        foreach ($notes as $note) {
            $notedata = getNote($note->guid, $cache);
            $resources = $notedata->resources;
            if (!$resources) continue;
            $resources = array_filter($resources, "isValidResource");
            foreach ($resources as $resource) {
                $images[] = array("thumb" => $_SESSION['webApiUrlPrefix']."thm/res/".$resource->guid."?size=240&auth=".$_SESSION['accessToken'], "url" => $_SESSION['webApiUrlPrefix']."res/".$resource->guid."?auth=".$_SESSION['accessToken'], "title" => trimTitle($note->title));
            }
        }
        return $images;
    } catch (Exception $e) {
        $lastError = 'Error listing notebooks: ' . $e->getMessage();
    }
    return false;
}

function getClient() {
    global $client;
    if (!isset($client)) {
        $accessToken = $_SESSION['accessToken'];
        $client = new Client(array(
                    'token' => $accessToken,
                    'sandbox' => SANDBOX
        ));
    }
    return $client;
}

function getNotebooks($cache=true)
{
    if ($cache && isset($_SESSION['notebooks']))
        return $_SESSION['notebooks'];

    global $lastError;

    try {
        $client = getClient();
        $result = $client->getNoteStore()->listNotebooks();
        foreach ($result as $notebook) {
            $notebooks[$notebook->guid] = $notebook;
        }
        $_SESSION['notebooks'] = $notebooks;

        return $notebooks;

    } catch (Exception $e) {
        $lastError = 'Error listing notebooks: ' . $e->getMessage();
    }

    return FALSE;
}

function isAuthenticated() {
    return (isset($_SESSION['authenticated']) ? $_SESSION['authenticated'] : false);
}

?>