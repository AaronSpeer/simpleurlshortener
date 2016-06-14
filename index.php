<?php

//***Configuration***\\

$error_reporting = 0; // 1 = errors 0 = none
$site_url = "http://short.home.ie"; //don't include the trailing /
$mysql_username = "root";
$mysql_password = "root";
$mysql_servername = "localhost";
$mysql_database = "urlshort";
$source_length = 5; //number of random bytes to be converted to Base64

//*******************\\

if ($error_reporting == 1){
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);}

  // Create connection
$conn = mysqli_connect($mysql_servername, $mysql_username, $mysql_password, $mysql_database);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

function sanitise_data($data) {
  global $conn;
  $data = mysqli_real_escape_string($conn, $data);
	$data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
  return $data;
}

function writeToDB($sour, $dest) {
  global $conn;
  $sql = "INSERT INTO url (source, destination) VALUES ('$sour', '$dest')";

  if (mysqli_query($conn, $sql)) {
      //echo "Wrote " . $sour . " => " . $dest . " to database";
  } else {
      echo "Error: " . $sql . "<br>" . mysqli_error($conn);
  }
}

$sql = "SHOW TABLES LIKE 'url'";
$result = $conn->query($sql);
$db_installed = $result->num_rows;
if ($db_installed == 0){  //install db
  $sql = "CREATE TABLE url (id INT(8) UNSIGNED AUTO_INCREMENT PRIMARY KEY, source VARCHAR(60), destination VARCHAR(1024))";

    if (mysqli_query($conn, $sql)) {
      writeToDB("index.php", "/");
    } else {
      echo "Error creating table: " . mysqli_error($conn);
    }
}

function getCurrentUri()
{
	$basepath = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) . '/';
	$uri = substr($_SERVER['REQUEST_URI'], strlen($basepath));
	if (strstr($uri, '?')) $uri = substr($uri, 0, strpos($uri, '?'));
	$uri = '/' . trim($uri, '/');
	return $uri;
}

$base_url = getCurrentUri();
$routes = array();
$routes = explode('/', $base_url);
$addr = array();

foreach($routes as $route) {
	if(trim($route) != '')
		array_push($routes, $route);
		if ($route != ""){
			$addr[] = $route;
		}
}

$source = sanitise_data($_POST["source"]);
$destination = sanitise_data($_POST["destination"]);

$reload = $_POST["reload"];

if ($addr[0] == ""){
  if($source == "" && $destination == "" && $reload == ""){
    $view = "form";
  }
  elseif($destination == ""){
    $error= "no-destination";
    $view = "form";
  }
  elseif($source != ""){
    $sql = "SELECT source FROM url WHERE source = '$source'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
      $view = "form";
      $error = "source-taken";
    }
    else {
        writeToDB($source, $destination);
        $view = "success";
    }
  }
  else{
    $view = "success";
    $error = "source-taken";
    while ($error == "source-taken"){
      $source = sanitise_data(rtrim(strtr(base64_encode(openssl_random_pseudo_bytes($source_length)), '+/', '-_'), '='));
      $sql = "SELECT source FROM url WHERE source = '$source'";
      $result = $conn->query($sql);
      if ($result->num_rows > 0) {
        $error = "source-taken";
        $view = "form";
      }
      else {
        writeToDB($source, $destination);
        $error = "";
        $view = "success";
      }
    }
  }
}
else{
  $view = "blank";

  $sql = "SELECT destination FROM url WHERE source = '$addr[0]'";
  $result = $conn->query($sql);

  if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $destination = $row["destination"];
    }

    if (0 === strpos($destination, 'http')) {
      header('Location: ' . $destination);
    }
    else{
      header('Location: http://' . $destination);
    }
  }
  else {
      $view = "form";
  }

}

//*******View*******\\

?>
<!DOCTYPE html>
<head>
  <title>Simple URL Shortener</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="http://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.2/jquery.min.js"></script>
  <script src="http://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>
  <link rel="shortcut icon" type="image/x-icon" href="<?php echo $site_url; ?>/favicon.ico">
</head>
<body>
<div class="container">
  <div class="page-header"><h1 style="font-size:3.5em;"><a href="/">Simple URL Shortener</a></h1></div>
  <?php if ($view == "form"){ ?>

    <form role="form" action="<?php echo $site_url; ?>" method="post">

      <div class="form-group <?php if ($error == 'no-destination'){ echo 'has-error';}?>">
          <label class="control-label" for="destination">Destination: <?php if ($error == 'no-destination'){ echo '(Must be entered)';}?></label>
          <input class="form-control" type="text" id="destination" name="destination" autofocus value="<?php echo $destination ?>">
      </div>

      <div class="form-group <?php if ($error == 'source-taken'){ echo 'has-error';}?>" >
          <label for="source"> <?php if ($error == 'source-taken'){ echo 'This source is already being used';}else{ echo "Source (Leave blank for random):";}?></label>
      <div class="input-group">
        <span class="input-group-addon"><?php echo $site_url?>/</span>
        <input class="form-control" type="text" id="source" name="source" value="<?php echo $source ?>">
      </div>
    </div>

      <input type="hidden" name="reload" value="1">

      <input style="padding-left:50px;padding-right:50px;" type="submit" value="Go!" class="btn btn-success btn-lg">
    </form>

  <?php } elseif ($view == "success") {
    if (0 === strpos($destination, 'http')) {
      echo "";
    }
    else{
      $destination = "http://" . $destination;
    }
    ?>

    <h4>The URL <a href=<?php echo "'" . $site_url . "/" . $source . "'>" . $site_url . "/" . $source . "</a> redirects to <a href='" . $destination . "'>" . $destination . "</a><br>"; ?></h4>


  <?php } ?>
</div>
<div class="footer">
    <p class="text-muted"><br>Simple URL Shortener<br>
      <a href="mailto:ajamesspeer@gmail.com">Aaron Speer</a>, <?php echo date("Y");?></p>
</div>


<div id = "scoped-content">
    <style type = "text/css" scoped>
    .footer{
    position: absolute;
    bottom: 0;
    width: 100%;
    background-color: #f5f5f5;
    position: absolute;
    left: 0;
    bottom: 0;
    height: 85px;
    width: 100%;
  }
  .footer p{
    text-align: center;
  }
  .table th, td{
    text-align: center;
  }

  body{
    margin: 0 0 110px; /* bottom = footer height */
  }
  html {
      position: relative;
      min-height: 100%;
  }
    </style>
</div>
</body>
<?php
