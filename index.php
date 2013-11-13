<?PHP
ini_set("display_errors", "On");

require_once 'config.php';
require_once 'functions.php';

session_start();

$lastError = null;

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action == 'callback') {
        if (!isAuthenticated() && handleCallback()) {
            getTokenCredentials();
            $notebooks = getNotebooks(false);
            $images = getAllNotebookImages($notebooks, false);
            header("Location: index.php");
        }
    } else if ($action == 'authorize') {
        if (getTemporaryCredentials()) {
            header('Location: ' . getAuthorizationUrl());
        }
    } else if ($action == 'reset') {
        resetSession();
    } else if($action == 'refresh') {
            $notebooks = getNotebooks(false);
            $images = getAllNotebookImages($notebooks, false);
            header("Location: index.php");
    }
}

if (isAuthenticated()) {
    $notebooks = getNotebooks(true);
    $images = getAllNotebookImages($notebooks, true);
    if (count($images) == 0 && !$lastError) {
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
        <script src="js/least.js" defer="defer"></script>
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
            <h1>Evernote Slides</h1>
            <?PHP
            if (isAuthenticated() && !isset($lastError)) {
            ?>
            <h2>welcome, <?=$_SESSION['name']?></h2>
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
                foreach ($images as $image) {
            ?>
            <li>
                <a href="<?=$image['url']?>"></a>
                
                <img data-original="<?=$image['thumb']?>" src="<?=$image['thumb']?>" width="240" alt="" />
                <div class="overLayer"></div>
                <div class="infoLayer">
                    <ul>
                        <li>
                            <h2><?=$image['title']?></h2>
                        </li>
                        <li>
                            <p>view picture</p>
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
            ?>
        </ul>
        </section>
        <div id="footer">
        <a href="index.php?action=refresh">reload data</a> | <a href="index.php?action=reset">end session</a>
        </div>
    <?PHP
        } else {
    ?>
        <div id="connect">
            <a href="index.php?action=authorize"><img src="img/connect.png" /></a>
        </div>
    <?PHP
        }
    ?>
    </body>
</html>
