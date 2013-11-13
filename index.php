<?PHP
ini_set("display_errors", "On");

require_once 'config.php';
require_once 'functions.php';

session_start();

$lastError = null;
$currentStatus = null;

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action == 'callback') {
        if (handleCallback()) {
            if (getTokenCredentials()) {
                //Get pics, set up stuff
            }
        }
    } elseif ($action == 'authorize') {
        if (getTemporaryCredentials()) {
            header('Location: ' . getAuthorizationUrl());
        }
    } elseif ($action == 'reset') {
        resetSession();
    }
}

?>

<html>
    <head>
        <title>Evernote Slides</title>
        <link href="css/least.min.css" rel="stylesheet" />
        <script src="http://code.jquery.com/jquery-latest.js"></script>
        <script src="js/least.min.js" defer="defer"></script>
        <script src="js/jquery.lazyload.min.js" defer="defer"></script>
        <?PHP
            if(isAuthenticated()) {
        ?>
        <script>
            $(document).ready(function(){
                $('#gallery').least();
            });
        </script>
        <?PHP
            }
        ?>
    </head>
    <body>
    <?PHP
        if (isAuthenticated()) {
            $images = getEvernoteImages();
    ?>
        <section>
        <ul id="gallery">
            <li id="fullPreview"></li>
            
            <?PHP
                if(count($images) > 0) {
                    foreach ($images as $image) {
            ?>
            <li>
                <a href="<?=$image['url']?>"></a>
                <img data-original="<?=$image['url']?>?resizeSmall&width=240" src="<?=$image['url']?>" width="240" height="150" alt="Ocean" />
                
                <div class="overLayer"></div>
                <div class="infoLayer">
                    <ul>
                        <li>
                            <h2>
                                <?=$image['title']?>
                            </h2>
                        </li>
                        <li>
                            <p>
                                View Picture
                            </p>
                        </li>
                    </ul>
                </div>
                
                <div class="projectInfo">
                    <strong>
                        Day, Month, Year:
                    </strong> 
                </div>
            </li>
            <?PHP
                }
            }
            ?>
        </ul>
        </section>
    <?PHP
        } else {
    ?>
        <p>
            <a href="index.php?action=authorize">Click here</a> to authorize this application to access your Evernote account. You will be directed to evernote.com to authorize access, then returned to this application after authorization is complete.
        </p>
    <?PHP
        }
    ?>
    </body>
</html>
