<?php session_start(); const MAX_REQUESTS_PER_MINUTE = 5; $sessionTokenIsSet = FALSE; $sessionToken = (isset($_SESSION['token'])) ? $_SESSION['token'] : ''; $db = new PDO('mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8', $_ENV['DB_USER'], $_ENV['DB_PASS']); ?>
<?php
function isRequestAllowed($token)
{
    global $sessionToken;

    $memcached = new Memcached();
    $memcached->addServer($_ENV['MEMCACHED_HOST'], 11211);

    $cacheKey = 'mtgoxbalance-antiflood-' . sprintf('%u', ip2long($_SERVER['REMOTE_ADDR']));
    $attempts = $memcached->get($cacheKey);
    if ($attempts >= MAX_REQUESTS_PER_MINUTE)
    {
        return FALSE;
    }
    elseif ($attempts === FALSE)
    {
        $memcached->set($cacheKey, 1, 60);
    }
    else
    {
        $memcached->increment($cacheKey, 1);
    }

    if (!isset($sessionToken) || $sessionToken != $token)
    {
        $memcached->set($cacheKey, MAX_REQUESTS_PER_MINUTE, 60);
        return FALSE;
    }
    return TRUE;
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="description" content="MtGox Balance - A way to prove your real balance at MtGox" />
        <meta name="keywords" content="Bitcoin, Exchange, MtGox, Cryptocoin, Cryptocurrency, Balance"/>
        <meta name="author" content="Multisig" />
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

        <title>MtGox Balance</title>

        <link rel="stylesheet" href="https://netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css">
        <style type="text/css">
            body {
              padding-top: 50px;
            }
            .starter-template {
              text-align: center;
            }
            .popover-content {
                font-size: 1em;
            }
        </style>

        <script src="https://code.jquery.com/jquery-1.11.0.min.js"></script>
        <script src="https://netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
        <script src="https://crypto-js.googlecode.com/svn/tags/3.1.2/build/rollups/sha256.js"></script>
    </head>
    <body>
        <a href="https://github.com/mtgoxbalance/mtgoxbalance" target="_blank"><img style="position: absolute; top: 0; left: 0; border: 0;" src="https://github-camo.global.ssl.fastly.net/567c3a48d796e2fc06ea80409cc9dd82bf714434/68747470733a2f2f73332e616d617a6f6e6177732e636f6d2f6769746875622f726962626f6e732f666f726b6d655f6c6566745f6461726b626c75655f3132313632312e706e67" alt="Fork me on GitHub" data-canonical-src="https://s3.amazonaws.com/github/ribbons/forkme_left_darkblue_121621.png"></a>
        <div class="container">
          <div class="starter-template">
            <h1>MtGox Balance</h1>
            <p class="lead">
             Use this tool to add your MtGox balance to our list.<br>
             All you need to do is login to MtGox, <a class="my-popover" title="Get your session id" data-toggle="popover" data-container="body" data-html="true" data-content="<a href='http://www.wikihow.com/View-Cookies' target='_blank'>How to view cookies</a><br><br>Search for www.mtgox.com and copy the ‘value’ string in PHPSESSID.  Example: 5ie80p13q6qk8bt89suk2crrk5">get your session id</a> and paste it here together with your email.<br>
             This email does not have to be your MtGox email, in fact for privacy reasons, you <b>should not</b> use any email address known to anyone that you don't want to show your balance to.<br>
             Also note, that as of now, it is possible to guess your email address provided here if you were registered on MtGox before the hack of 2011.<br>
             Once again for privacy reasons, you should log off from the MtGox after your balance data is fetched.
           </p>
          </div>
        </div>
        <div class="container">
            <div class="starter-template">
                <h2>Add balance</h2>
                <?php
                $success = FALSE;

                if (isset($_POST['email']) && isset($_POST['session']) && isset($_POST['token']) && isRequestAllowed($_POST['token']))
                {
                    if (empty($_POST['email']) || !preg_match("/^[a-zA-Z0-9]{64}$/", $_POST['email']))
                    {
                        echo '<p class="lead">Invalid email</p>';
                    }
                    elseif (!preg_match("/^[a-z0-9]{26}$/", $_POST['session']))
                    {
                        echo '<p class="lead">Invalid session</p>';
                    }
                    else
                    {
                        $curl = curl_init();
                        $curlSettings = array(
                            CURLOPT_URL            => 'https://www.mtgox.com/',
                            CURLOPT_TIMEOUT        => 1000,
                            CURLOPT_RETURNTRANSFER => TRUE,
                            CURLOPT_HEADER         => TRUE,
                            CURLOPT_COOKIE         => 'PHPSESSID=' . $_POST['session'],
                        );
                        curl_setopt_array($curl, $curlSettings);

                        $result = curl_exec($curl);
                        if (strpos($result, 'input type="password"') === FALSE)
                        {
                            $statement = $db->prepare('INSERT INTO rawdata SET email = :email, data = :data');
                            $statement->execute(array(
                                ':email' => $_POST['email'],
                                ':data'  => $result,
                            ));
                            $success = TRUE;
                            echo '<p class="lead">Success! You can log out from MtGox now. Make sure to <a class="my-popover" title="Clear your cookies" data-toggle="popover" data-container="body" data-content="Look for a delete/clear button where you found the cookie, or right-click and delete it.">clear your cookies</a> as well.</p>';
                        }
                        else
                        {
                            echo '<p class="lead">Session does not seem to work anymore, please get a new session id.</p>';
                        }
                    }
                }

                if (!$success)
                {
                    if (!$sessionTokenIsSet)
                    {
                        $_SESSION['token'] = sha1(rand());
                        $sessionTokenIsSet = TRUE;
                    }
                    ?>
                    <form method="post" id="form1" class="navbar-form" style="margin-left: auto; margin-right: auto; float: none;">
                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>" />
                        <div class="form-group">
                            <input type="text" name="email" id="email1" placeholder="Email" class="form-control" />
                            <input type="text" name="session" placeholder="Session ID" <?php if (isset($_POST['session'])):?>value="<?php echo $_POST['session']; ?>" <?php endif;?>class="form-control" />
                        </div>
                        <button type="submit" class="btn btn-default">Submit</button>
                    </form>
                    <script>
                    $(function() {
                            $( "#form1" ).submit(function(e) {
                                var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);
                                var email = $("#email1").val();
                                if (pattern.test(email))
                                {
                                    $("#email1").val(CryptoJS.SHA256(email));
                                }
                                else
                                {
                                    e.preventDefault();
                                }
                            });
                    });
                    </script>
                    <?php
                }
                ?>
            </div>
        </div>
        <div class="container">
            <div class="starter-template">
                <h2>Verify balance</h2>
                <?php
                $success = FALSE;
                if (isset($_POST['checkemail']) && isset($_POST['username']) && isset($_POST['token']) && isRequestAllowed($_POST['token']))
                {
                    if (empty($_POST['checkemail']) || !preg_match("/^[a-zA-Z0-9]{64}$/", $_POST['checkemail']))
                    {
                        echo '<p class="lead">Invalid email</p>';
                    }
                    else
                    {
                        $statement = $db->prepare('SELECT * FROM rawdata WHERE email = :email ORDER BY id DESC LIMIT 1');
                        $statement->execute(array(':email' => $_POST['checkemail']));
                        $row = $statement->fetch();
                        if (!empty($row))
                        {
                            preg_match("/<h3 style=\".*?\">Connected as (.*?).<\/h3>/sm", $row['data'], $matches);
                            if (empty($matches))
                            {
                                echo '<p class="lead">We do not seem to have that email on file or could not parse the MtGox result.</p>';
                            }
                            else
                            {
                                if ($_POST['username'] != $matches[1])
                                {
                                    echo '<p class="lead">We do not seem to have that email on file or could not parse the MtGox result.</p>';
                                }
                                else
                                {
                                    preg_match("/<h3>My wallets:<\/h3>\s*<ul>(.*?)<\/ul>/sm", $row['data'], $matches);
                                    if (empty($matches))
                                    {
                                        echo '<p class="lead">We do not seem to have that email on file or could not parse the MtGox result.</p>';
                                    }
                                    else
                                    {
                                        echo '<p class="lead">Your balance: <ul class="list-inline">' . strip_tags($matches[1], '<li>') . '</ul></p>';
                                    }
                                }
                            }
                        }
                        else
                        {
                            echo '<p class="lead">We do not seem to have that email on file or could not parse the MtGox result.</p>';
                        }
                    }
                }

                if (!$success)
                {
                    if (!$sessionTokenIsSet)
                    {
                        $_SESSION['token'] = sha1(rand());
                        $sessionTokenIsSet = TRUE;
                    }
                    ?>
                    <form method="post" id="form2" class="navbar-form" style="margin-left: auto; margin-right: auto; float: none;">
                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>" />
                        <div class="form-group">
                            <input type="text" name="checkemail" id="email2" placeholder="Email" class="form-control" />
                            <input type="text" name="username" placeholder="MtGox Username" class="form-control" />
                        </div>
                        <button type="submit" class="btn btn-default">Submit</button>
                    </form>
                    <script>
                    $(function() {
                            $( "#form2" ).submit(function(e) {
                                var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);
                                var email = $("#email2").val();
                                if (pattern.test(email))
                                {
                                    $("#email2").val(CryptoJS.SHA256(email));
                                }
                                else
                                {
                                    e.preventDefault();
                                }
                            });
                    });
                    </script>
                    <?php
                }
                ?>
            </div>
        </div>
        <div class="container">
            <div class="starter-template">
                <h2>Current list of confirmed balances</h2>
                <table class="table" style="text-align: left;">
                    <thead><tr><th>Hash</th><th>Balance</th></tr></thead>
                    <tbody>
                        <?php
                        $processedUsernames = array();
                        $statement = $db->prepare('SELECT * FROM rawdata ORDER BY id DESC');
                        $statement->execute();
                        while ($row = $statement->fetch())
                        {
                            preg_match("/<h3 style=\".*?\">Connected as (.*?).<\/h3>/sm", $row['data'], $matches);
                            if (!empty($matches))
                            {
                                $username = $matches[1];
                                if (!isset($processedUsernames[ $username ]))
                                {
                                    preg_match("/<h3>My wallets:<\/h3>\s*<ul>(.*?)<\/ul>/sm", $row['data'], $matches);
                                    if (!empty($matches))
                                    {
                                        $processedUsernames[ $username ] = TRUE;
                                        echo '<tr><td>' . hash('sha256', $row['email'] . $username) . '</td><td><ul class="list-inline">' . strip_tags($matches[1], '<li>') . '</ul></td></tr>' . "\n";
                                    }
                                }
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <footer class="container" style="font-size:0.9em; text-align:center; margin-top:75px;">Free software and website sponsored by Olivier Janssens / MtGoxRecovery - Thanks to Robrecht, Lucas, Sven, lnovy and many others!</footer>
        <script type="text/javascript">
            $('[data-toggle="popover"]').popover({});
            $('body').on('click', function (e) {
            //did not click a popover toggle or popover
            if ($(e.target).data('toggle') !== 'popover'
                && $(e.target).parents('.popover.in').length === 0) {
                $('[data-toggle="popover"]').popover('hide');
            }
        });
        </script>
    </body>
</html>
