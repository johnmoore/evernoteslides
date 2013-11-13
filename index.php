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
        if (!isAuthenticated() && handleCallback()) {
            getTokenCredentials();
        }
    } elseif ($action == 'authorize') {
        if (getTemporaryCredentials()) {
            header('Location: ' . getAuthorizationUrl());
        }
    } elseif ($action == 'reset') {
        resetSession();
    }
}

if (isAuthenticated()) {
    $images = getEvernoteImages();
    if (count($images) == 0) {
        $lastError = "You don't have any images in your notebooks";
    }
}
?>

<html>
    <head>
        <title>Evernote Slides</title>
        <link href="css/least.min.css" rel="stylesheet" />
        <link href="css/main.css" rel="stylesheet" />
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
        <header>
            <h1>
                Evernote Slides
            </h1>
            <?PHP
            if (isAuthenticated() && !isset($lastError)) {
            ?>
            <h2>
                welcome, <?=$_SESSION['name']?>
            </h2>
            <?PHP
            }
            ?>
        </header>
        <section>
        <ul id="gallery">
        <?PHP
        if (isset($lastError)) {
        ?>
            <h3 class="error">Uh-oh!</h3>
            <p class="error"><?=$lastError?></p>
        <?PHP
        } else if (isAuthenticated()) {
        ?>
            <li id="fullPreview"></li>
            
            <?PHP
                if(count($images) > 0) {
                    foreach ($images as $image) {
            ?>
            <li>
                <a href="<?=$image['url']?>"></a>
                
                <img data-original="<?=$image['url']?>" src="<?=$image['thumb']?>" height="150" width="240" alt="" />
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
                                view picture
                            </p>
                        </li>
                    </ul>
                </div>
                
                <div class="projectInfo">
                    <strong>

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
        <div id="connect">
            <a href="index.php?action=authorize"><img src="connect.png" /></a>
        </div>
    <?PHP
        }
    ?>
    </body>
</html>
