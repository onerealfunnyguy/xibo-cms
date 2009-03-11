<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2009 Alex Harrington
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */ 

DEFINE('XIBO', true);

session_start();

define('_CHECKBOX', "checkbox");
define('_INPUTBOX', "inputbox");
define('_PASSWORDBOX', "password");

include('lib/app/kit.class.php');
include('config/db_config.php');
include('config/config.class.php');
include('install/header_upgrade.inc');
require('settings.php');

// create a database class instance
$db = new database();

if (!$db->connect_db($dbhost, $dbuser, $dbpass)) reportError(0, "Unable to connect to the MySQL database using the settings stored in settings.php.<br /><br />MySQL Error:<br />" . $db->error());
if (!$db->select_db($dbname)) reportError(0, "Unable to select the MySQL database using the settings stored in settings.php.<br /><br />MySQL Error:<br />" . $db->error());


$fault = false;

if (! $_SESSION['step']) {
	$_SESSION['step'] = 0;
}

if ($_SESSION['step'] == 0) {

  $_SESSION['step'] = 1;

  # First step of the process.
  # Show a welcome screen and authenticate the user
  ?>
  Welcome to the Xibo Upgrade!<br /><br />
  The upgrade program will take you through the process one step at a time.<br /><br />
  Lets get started!<br /><br />
  Please enter your xibo_admin password:<br /><br />
  <form action="upgrade.php" method="POST">
    <div class="install_table">
	<input type="password" name="password" length="12" />
    </div>
    <div class="loginbutton"><button type="submit">Next ></button></div>
  </form>
  <?php
}
elseif ($_SESSION['step'] == 1) {
  $_SESSION['step'] = 2;
  
  if (! $_SESSION['auth']) {

	# Check password

	$password = Kit::GetParam('password',_POST,_PASSWORD);
	$password_hash = md5($password);

	$SQL = sprintf("SELECT `UserID` FROM `user` WHERE UserPassword='%s' AND UserName='xibo_admin'",
        	            $db->escape_string($password_hash));
    	if (! $result = $db->query($SQL)) {
      	reportError("0", "An error occured checking your password.<br /><br />MySQL Error:<br />" . mysql_error());    
    	}
 
	if ($db->num_rows($result) == 0) {	
      		$_SESSION['auth'] = false;
       		reportError("0", "Password incorrect. Please try again.");
   	}
   	else {
		$_SESSION['auth'] = true;
		$_SESSION['db'] = $db;
    	}

   }
## Check server meets specs (as specs might have changed in this release)
  ?>
  <p>First we need to check if your server meets Xibo's requirements.</p>
  <div class="checks">
  <?php
## Filesystem Permissions
    if (checkFsPermissions()) {
    ?>
      <img src="install/dot_green.gif"> Filesystem Permissions<br />
    <?php
    }
    else {
      $fault = true;
    ?>
      <img src="install/dot_red.gif"> Filesystem Permissions</br>
      <div class="check_explain">
      Xibo needs to be able to write to the following
      <ul>
        <li> settings.php
        <li> install.php
	<li> upgrade.php
      </ul>
      Please fix this, and retest.<br />
      </div>
    <?php
    }
## PHP5
    if (checkPHP()) {
    ?>
      <img src="install/dot_green.gif"> PHP Version<br />
    <?php
    }
    else {
      $fault = true;
    ?>
      <img src="install/dot_red.gif"> PHP Version<br />
      <div class="check_explain">
      Xibo requires PHP version 5.02 or later.<br />
      Please fix this, and retest.<br />
      </div>
    <?php
    }
## MYSQL
  if (checkMySQL()) {
    ?>
      <img src="install/dot_green.gif"> PHP MySQL Extension<br />
    <?php
    }
    else {
      $fault = true;
    ?>
      <img src="install/dot_red.gif"> PHP MySQL Extension<br />
      <div class="check_explain">
      Xibo needs to access a MySQL database to function.<br />
      Please install MySQL and the appropriate MySQL extension and retest.<br />
      </div>
    <?php
    }
## JSON
  if (checkJson()) {
    ?>
      <img src="install/dot_green.gif"> PHP JSON Extension<br />
    <?php
    }
    else {
      $fault = true;
    ?>
      <img src="install/dot_red.gif"> PHP JSON Extension<br />
      <div class="check_explain">
      Xibo needs the PHP JSON extension to function.<br />
      Please install the PHP JSON extension and retest.<br />
      </div>
    <?php
    }
## GD
  if (checkGd()) {
    ?>
      <img src="install/dot_green.gif"> PHP GD Extension<br />
    <?php
    }
    else {
      $fault = true;
    ?>
      <img src="install/dot_red.gif"> PHP GD Extension<br />
      <div class="check_explain">
      Xibo needs to manipulate images to function.<br />
      Please install the GD libraries and extension and retest.<br />
      </div>
    <?php
    }
    ?>
    <br /><br />
    </div>
    <?php
    if ($fault) {
	$_SESSION['step'] = 1;
    ?>
      <form action="upgrade.php" method="POST">
        <div class="loginbutton"><button type="submit">Retest</button></div>
      </form>
    <?php
    }
    else {
    ?>
      <form action="upgrade.php" method="POST">
        <div class="loginbutton"><button type="submit">Next ></button></div>
      </form>
    <?php
    }    
}
elseif ($_SESSION['step'] == 2) {
	checkAuth();
# Calculate the upgrade
      
	$_SESSION['upgradeFrom'] = Config::Version($db, 'DBVersion');
	$_SESSION['upgradeFrom'] = 1;

	// Get a list of .sql and .php files for the upgrade
	$sql_files = ls('*.sql','install/database',false,array('return_files'));
	$php_files = ls('*.php','install/database',false,array('return_files'));
    
	// Sort by natural filename (eg 10 is bigger than 2)
	natcasesort($sql_files);
	natcasesort($php_files);

	$_SESSION['phpFiles'] = $php_files;
	$_SESSION['sqlFiles'] = $sql_files;

	$max_sql = Kit::ValidateParam(substr(end($sql_files),0,-4),_INT);
	$max_php = Kit::ValidateParam(substr(end($php_files),0,-4),_INT);
	$_SESSION['upgradeTo'] = max($max_sql, $max_php);

	if (! $_SESSION['upgradeTo']) {
		reportError("2", "Unable to calculate the upgradeTo value. Check for non-numeric SQL and PHP files in the 'install/datbase' directory.", "Retry");
	}

	echo '<div class="info">';
	echo '<p>Upgrading from database version ' . $_SESSION['upgradeFrom'] . ' to ' . $_SESSION['upgradeTo'];
	echo '</p></div><hr width="25%"/>';
	echo '<form action="upgrade.php" method="POST">';

	// Loop for $i between upgradeFrom + 1 and upgradeTo.
	// If a php file exists for that upgrade, make an instance of it and call Questions so we can
	// Ask the user for input.
	for ($i=$_SESSION['upgradeFrom'] + 1; $i <= $_SESSION['upgradeTo']; $i++) {
		if (file_exists('install/database/' . $i . '.php')) {
			include_once('install/database/' . $i . '.php');
			$stepName = 'Step' . $i;
			
			// Check that a class called Step$i exists
			if (class_exists($stepName)) {
				$_SESSION['q']['Step' . $i] = new $stepName($db);
				// Call Questions on the object and send the resulting hash to createQuestions routine
				createQuestions($i, $_SESSION['q']['Step' . $i]->Questions());
				$_SESSION['q']['Step' . $i] = serialize($_SESSION['Step' . $i]);
			}
			else {
				print "Warning: We included $i.php, but it did not include a class of appropriate name.";
			}						
		}
	}

	$_SESSION['step'] = 3;
	echo '<p><input type="submit" value="Next >" /></p>';
	echo '</form>';

?>
  <?php
}
elseif ($_SESSION['step'] == 3) {

	$fault = false;
	$fault_string = "";
	print_r($_POST);
	foreach ($_POST as $key => $post) {
		// $key should be like 1-2, 1-3 etc
		// Split $key on - character.

		$parts = explode('-', $key);
		if (count($parts) == 2) {
			$step_num = 'Step' . $parts[0];
			include_once('install/database/' . $parts[0] . '.php');
			$_SESSION['q'][$step_num] = unserialize($_SESSION['q'][$step_num]);
			$response = $_SESSION['q'][$step_num]->ValidateQuestion($parts[1], $post);
			if (! $response == true) {
				// The upgrade routine for this step wasn't happy.
				$fault = true;
				$fault_string .= $response . "<br />\n";
			}
		}
	}

	if ($fault) {
		echo $fault_string;
	}

exit;
## If not, gather admin password and use to create empty db and new user.
?>
<div class="info">
<p>Since no empty database has been created for Xibo to use, we need the username
and password of a MySQL administrator to create a new database, and database
user for Xibo.</p>
<p>Additionally, please give us a new username and password to create in MySQL
for Xibo to use. Xibo will create this automatically for you.</p>
<form action="install.php" method="POST">
<input type="hidden" name="xibo_step" value="5" />
<input type="hidden" name="db_create" value="true" />
<div class="install_table">
  <p><label for="host">Host: </label><input class="username" type="text" id="host" name="host" size="12" value="localhost" /></p>
  <p><label for="admin_username">Admin Username: </label><input class="username" type="text" id="admin_username" name="admin_username" size="12" /></p>
  <p><label for="admin_password">Admin Password: </label><input class="username" type="password" id="admin_password" name="admin_password" size="12" /></p>
  <p><label for="db_name">Xibo Database Name: </label><input class="username" type="text" id="db_name" name="db_name" size="12" value="xibo" /></p>
  <p><label for="db_username">Xibo Database Username: </label><input class="username" type="text" id="db_username" name="db_username" size="12" value="xibo" /></p>
  <p><label for="db_password">Xibo Database Password: </label><input class="username" type="password" id="db_password" name="db_password" size="12" /></p>
</div>
</div>
<button type="submit">Create</button>
</form>
<?php
}
elseif ($xibo_step == 4) {
## Get details of db that's been created already for us
?>
<div class="info">
<p>Please enter the details of the database and user you have
created for Xibo.</p>
<form action="install.php" method="POST">
<input type="hidden" name="xibo_step" value="5" />
<input type="hidden" name="db_create" value="false" />
<div class="install_table">
  <p><label for="host">Host: </label><input class="username" type="text" id="host" name="host" size="12" value="localhost" /></p>
  <p><label for="db_name">Xibo Database Name: </label><input class="username" type="text" id="db_name" name="db_name" size="12" value="xibo" /></p>
  <p><label for="db_username">Xibo Database Username: </label><input class="username" type="text" id="db_username" name="db_username" size="12" value="xibo" /></p>
  <p><label for="db_password">Xibo Database Password: </label><input class="username" type="password" id="db_password" name="db_password" size="12" /></p>
</div>
</div>
<button type="submit">Create</button>
</form>
<?php
}
elseif ($xibo_step == 5) {

  $db_create = Kit::GetParam('db_create',_POST,_BOOL);

  if (!isset($db_create)) {
    reportError("2","Something went wrong");
  }
  else {
    $db_host = Kit::GetParam('host',_POST,_STRING,'localhost');
    $db_user = Kit::GetParam('db_username',_POST,_USERNAME);
    $db_pass = Kit::GetParam('db_password',_POST,_PASSWORD);
    $db_name = Kit::GetParam('db_name',_POST,_USERNAME);
    ?>
    <div class="info">
    <?php
    if ($db_create == true) {  
      $db_admin_user = Kit::GetParam('admin_username',_POST,_USERNAME);
      $db_admin_pass = Kit::GetParam('admin_password',_POST,_PASSWORD);
      
      if (! ($db_host && $db_name && $db_user && $db_pass && $db_admin_user && $db_admin_pass)) {
        # Something was blank.
        # Throw an error.
        reportError("3", "A field was blank. Please fill in all fields.");
      }
      
      $db = @mysql_connect($db_host,$db_admin_user,$db_admin_pass);
      
      if (! $db) {
        reportError("3", "Could not connect to MySQL with the administrator details. Please check and try again.<br /><br />MySQL Error:<br />" . mysql_error());
      }
      
      ?>
      <p>Creating new database.</p>
      <?php
      flush();
      
      $SQL = sprintf("CREATE DATABASE %s",
                      mysql_real_escape_string($db_name));
      if (! @mysql_query($SQL, $db)) {
        # Create database and user
        reportError("3", "Could not create a new database with the administrator details. Please check and try again.<br /><br />MySQL Error:<br />" . mysql_error());
      }
      
      # Choose the MySQL DB to create a user
      @mysql_select_db("mysql", $db);

      # Make $db_host lowercase so it matches "localhost" if required.
      $db_host = strtolower($db_host);
      
      ?>
      <p>Creating new user</p>
      <?php
      flush();
      
      if ($db_host == 'localhost') {
        $SQL = sprintf("GRANT ALL PRIVILEGES ON %s.* to '%s'@'%s' IDENTIFIED BY '%s'",
                        mysql_real_escape_string($db_name),
                        mysql_real_escape_string($db_user),
                        mysql_real_escape_string($db_host),
                        mysql_real_escape_string($db_pass));
      }
      else {
        $SQL = sprintf("GRANT ALL PRIVILEGES ON %s.* to '%s'@'%%' IDENTIFIED BY '%s'",
                        mysql_real_escape_string($db_name),
                        mysql_real_escape_string($db_user),
                        mysql_real_escape_string($db_pass));
      }
      if (! @mysql_query($SQL, $db)) {
          reportError("3", "Could not create a new user with the administrator details. Please check and try again.<br /><br />MySQL Error:<br />" . mysql_error());
      }
      

      @mysql_query("FLUSH PRIVILEGES", $db);      
      @mysql_close($db);
      
    }
    else {
      if (! ($db_host && $db_name && $db_user && $db_pass)) {
        # Something was blank
        # Throw an error.
        reportError("4", "A field was blank. Please fill in all fields.");
      }
    }
    ## Populate database
    
    $db = @mysql_connect($db_host,$db_user,$db_pass);
      
    if (! $db) {
      reportError("4", "Could not connect to MySQL with the Xibo User account details. Please check and try again.<br /><br />MySQL Error:<br />" . mysql_error());
    }
      
    @mysql_select_db($db_name,$db);
    
    ?>
    <p>Populating the database</p>
    <?php
    flush();
    
    # Load from sql files to db
    $sql_files = ls('*.sql','install/database',false,array('return_files'));

    // Sort the files in to sensible order, ie
    //   0.sql
    //	 1.sql
    //	10.sql
    //
    // NOT
    //
    //	 0.sql
    //	10.sql
    //	 1.sql
    //
    // NB this is broken for 0 padded files
    // eg 01.sql would be incorrectly sorted in the above example.
    
    natcasesort($sql_files);

    foreach ($sql_files as $filename) {
      ?>
      <p>Loading from <?php print $filename; ?>
      <?php
        flush();
        
        $delimiter = ';';
        $sql_file = @file_get_contents('install/database/' . $filename);
        $sql_file = remove_remarks($sql_file);
        $sql_file = split_sql_file($sql_file, $delimiter);
    
        foreach ($sql_file as $sql) {
          print ".";
          flush();
          if (! @mysql_query($sql,$db)) {
            reportError("4", "An error occured populating the database.<br /><br />MySQL Error:<br />" . mysql_error());
          }
        }
        print "</p>";
    }
    @mysql_close($db);
  }
  # Write out a new settings.php
  $fh = fopen("settings.php", 'wt');
  
  if (! $fh) {
    reportError("0", "Unable to write to settings.php. We already checked this was possible earlier, so something changed.");
  }
  
  settings_strings();
  
  $settings_content = '$dbhost = \'' . $db_host . '\';' . "\n";
  $settings_content .= '$dbuser = \'' . $db_user . '\';' . "\n";
  $settings_content .= '$dbpass = \'' . $db_pass . '\';' . "\n";
  $settings_content .= '$dbname = \'' . $db_name . '\';' . "\n\n";
  $settings_content .= 'define(\'SECRET_KEY\',\'' . gen_secret() . '\');' . "\n";
  
  if (! fwrite($fh, $settings_header . $settings_content . $settings_footer)) {
    reportError("0", "Unable to write to settings.php. We already checked this was possible earlier, so something changed.");
  }
    
  fclose($fh);
  
  ?>
  </div>
  <div class="install_table">
    <form action="install.php" method="POST">
      <input type="hidden" name="xibo_step" value="6" />
  </div>
    <button type="submit">Next ></button>
  </form>
  <?php
}
elseif ($xibo_step == 6) {
  # Form to get new admin password
  ?>
  <div class="info">
  <p>Xibo needs to set the "xibo_admin" user password. Please enter a password for this account below.</p>
  </div>
  <div class="install_table">
    <form action="install.php" method="POST">
      <input type="hidden" name="xibo_step" value="7" />
      <p><label for="password1">Password: </label><input type="password" name="password1" size="12" /></p>
      <p><label for="password2">Retype Password: </label><input type="password" name="password2" size="12" /></p>
  </div>
    <button type="submit">Next ></button>
  </form>
  <?php
}
elseif ($xibo_step == 7) {
  # Setup xibo_admin password
  $password1 = Kit::GetParam('password1',_POST,_PASSWORD);
  $password2 = Kit::GetParam('password2',_POST,_PASSWORD);
  
  if (!(($password1 && $password2) && ($password1 == $password2))) {
    reportError("6", "Please input a new password. Ensure both password fields are identical.");
  }
  
  include('settings.php');
  
  $password_hash = md5($password1);
  
  $db = @mysql_connect($dbhost,$dbuser,$dbpass);
      
    if (! $db) {
      reportError("6", "Could not connect to MySQL with the Xibo User account details saved in settings.php. Please check and try again.<br /><br />MySQL Error:<br />" . mysql_error());
    }
      
    @mysql_select_db($dbname,$db);

    $SQL = sprintf("UPDATE `user` SET UserPassword = '%s' WHERE UserID = 1 LIMIT 1",
                    mysql_real_escape_string($password_hash));
    if (! @mysql_query($SQL, $db)) {
      reportError("6", "An error occured changing the xibo_admin password.<br /><br />MySQL Error:<br />" . mysql_error());    
    }
 
    @mysql_close($db);
    
    ?>
    <div class="info">
      Successfully changed the xibo_admin password. We're nearly there now. Just a couple more steps!
    </div>
    <form action="install.php" method="POST">
      <input type="hidden" name="xibo_step" value="8" />
      <button type="submit">Next ></button>
    </form>
    <?php
}
elseif ($xibo_step == 8) {
  # Configure paths and keys
  ## nuSoap
  ## libraries
  ## server_key
  ?>
  <div class="info">
    <p>Library Location</p>
    <p>Xibo needs somewhere to store the things you upload to be shown. Ideally, this should be somewhere outside the root of your webserver - that is such that is not accessible by a web browser. Please input the full path to this folder. If the folder does not already exist, Xibo will attempt to create it for you.</p>
    <form action="install.php" method="POST">
    <div class="install_table">
       <p><label for="library_location">Library Location: </label><input type="text" name="library_location" value="" /></p>
    </div>
    <p>Server Key</p>
    <p>Xibo needs you to choose a "key". This will be required each time you setup a new client. It should be complicated, and hard to remember. It is visible in the admin interface, so it need not be written down separately.</p>
    <div class="install_table">
      <p><label for="server_key">Server Key: </label><input type="text" name="server_key" value="" /></p>
    </div>
      <input type="hidden" name="xibo_step" value="9" />
    </div>
      <button type="submit">Next ></button>
    </form>
  <?php
}
elseif ($xibo_step == 9) {

  $server_key = Kit::GetParam('server_key',_POST,_WORD);
  $library_location = Kit::GetParam('library_location',_POST,_STRING);
  
  // Remove trailing whitespace from the path given.
  $library_location = trim($library_location);

  // Check both fields were completed
  if (! ($server_key && $library_location)) {
    reportError("8","A field was blank. Please make sure you complete all fields");
  }

  // Does library_location exist already?
  if (! is_dir($library_location)) {
    if (is_file($library_location)) {
      reportError("8", "A file exists with the name you gave for the Library Location. Please choose another location");
    }

    // Directory does not exist. Attempt to make it
    // Using mkdir recursively, so it will attempt to make any
    // intermediate folders required.
    if (! mkdir($library_location,0755,true)) {
      reportError("8", "Could not create the Library Location directory for you. Please ensure the webserver has permission to create a folder in this location, or create the folder manually and grant permission for the webserver to write to the folder.");
    }
    
  }
  
  // Is library_location writable?
  if (! is_writable($library_location)) {
    // Directory is not writable.
    reportError("8","The Library Location you gave is not writable by the webserver. Please fix the permissions and try again.");
  }
  
  // Is library_location empty?
  if (count(ls("*",$library_location,true)) > 0) {
    reportError("8","The Library Location you gave is not empty. Please give the location of an empty folder");
  }
  
  // Check if the user has added a trailing slash.
  // If not, add one.
  if (!((substr($library_location, -1) == '/') || (substr($library_location, -1) == '\\'))) {
    $library_location = $library_location . '/';
  }

  include('settings.php');
  
  $db = @mysql_connect($dbhost,$dbuser,$dbpass);
      
    if (! $db) {
      reportError("8", "Could not connect to MySQL with the Xibo User account details saved in settings.php. Please check and try again.<br /><br />MySQL Error:<br />" . mysql_error());
    }
      
    @mysql_select_db($dbname,$db);
    
    $SQL = sprintf("UPDATE `setting` SET `value` = '%s' WHERE `setting`.`setting` = 'LIBRARY_LOCATION' LIMIT 1",
                    mysql_real_escape_string($library_location));
    if (! @mysql_query($SQL, $db)) {
      reportError("8", "An error occured changing the library location.<br /><br />MySQL Error:<br />" . mysql_error());    
    }
    
    $SQL = sprintf("UPDATE `setting` SET `value` = '%s' WHERE `setting`.`setting` = 'SERVER_KEY' LIMIT 1",
                      mysql_real_escape_string($server_key));
    if (! @mysql_query($SQL, $db)) {
      reportError("8", "An error occured changing the server key.<br /><br />MySQL Error:<br />" . mysql_error());    
    }
 
    @mysql_close($db);
  
  ?>
  <div class="info">
    <p>Successfully set LIBRARY_LOCATION and SERVER_KEY.</p>
  </div>
    <form action="install.php" method="POST">
      <input type="hidden" name="xibo_step" value="10" />
      <button type="submit">Next ></button>
    </form>
  <?php
}
elseif ($xibo_step == 10) {
# Delete install.php
# Redirect to login page.
  if (! unlink('install.php')) {
    reportError("10", "Unable to delete install.php. Please ensure the webserver has permission to unlink this file and retry", "Retry");
  }
  ?>
  <div class="info">
    <p><b>Xibo was successfully installed.</b></p>
    <p>Please click <a href="index.php">here</a> to logon to Xibo as "xibo_admin" with the password you chose earlier.</p>
  </div>
  <?php
}
else {
  reportError("0","A required parameter was missing. Please go through the installer sequentially!","Start Again");
}
 
include('install/footer.inc');

# Functions

function checkFsPermissions() {
  # Check for appropriate filesystem permissions
  return (is_writable("install.php") && (is_writable("settings.php") || is_writable(".")));
}

function checkPHP() {
  # Check PHP version > 5
  return (version_compare("5",phpversion(), "<="));
}

function checkMySQL() {
  # Check PHP has MySQL module installed
  return extension_loaded("mysql");
}

function checkJson() {
  # Check PHP has JSON module installed
  return extension_loaded("json");
}

function checkGd() {
  # Check PHP has JSON module installed
  return extension_loaded("gd");
}
 
function reportError($step, $message, $button_text="&lt; Back") {
	$_SESSION['step'] = $step;
?>
    <div class="info">
      <?php print $message; ?>
    </div>
    <form action="upgrade.php" method="POST">
      <button type="submit"><?php print $button_text; ?></button>
    </form>
  <?php
  include('install/footer.inc');
  die();
} 

function checkAuth() {
	if (! $_SESSION['auth']) {
		reportError(1, "You must authenticate to run the upgrade.");
	}
}

// Taken from http://forums.devshed.com/php-development-5/php-wont-load-sql-from-file-515902.html
// By Crackster 
/**
 * remove_remarks will strip the sql comment lines out of an uploaded sql file
 */
function remove_remarks($sql){
  $sql = preg_replace('/\n{2,}/', "\n", preg_replace('/^[-].*$/m', "\n", $sql));
  $sql = preg_replace('/\n{2,}/', "\n", preg_replace('/^#.*$/m', "\n", $sql));
  return $sql;
}

// Taken from http://forums.devshed.com/php-development-5/php-wont-load-sql-from-file-515902.html
// By Crackster              
/**
 * split_sql_file will split an uploaded sql file into single sql statements.
 * Note: expects trim() to have already been run on $sql.
 */
function split_sql_file($sql, $delimiter){
  $sql = str_replace("\r" , '', $sql);
  $data = preg_split('/' . preg_quote($delimiter, '/') . '$/m', $sql);
  $data = array_map('trim', $data);
  // The empty case
  $end_data = end($data);
  if (empty($end_data))
  {
    unset($data[key($data)]);
  }
  return $data;
}
 
/**
 * This funtion will take a pattern and a folder as the argument and go thru it(recursivly if needed)and return the list of 
 *               all files in that folder.
 * Link             : http://www.bin-co.com/php/scripts/filesystem/ls/
 * License	: BSD
 * Arguments     :  $pattern - The pattern to look out for [OPTIONAL]
 *                    $folder - The path of the directory of which's directory list you want [OPTIONAL]
 *                    $recursivly - The funtion will traverse the folder tree recursivly if this is true. Defaults to false. [OPTIONAL]
 *                    $options - An array of values 'return_files' or 'return_folders' or both
 * Returns       : A flat list with the path of all the files(no folders) that matches the condition given.
 */
function ls($pattern="*", $folder="", $recursivly=false, $options=array('return_files','return_folders')) {
    if($folder) {
        $current_folder = realpath('.');
        if(in_array('quiet', $options)) { // If quiet is on, we will suppress the 'no such folder' error
            if(!file_exists($folder)) return array();
        }
        
        if(!chdir($folder)) return array();
    }
    
    
    $get_files    = in_array('return_files', $options);
    $get_folders= in_array('return_folders', $options);
    $both = array();
    $folders = array();
    
    // Get the all files and folders in the given directory.
    if($get_files) $both = glob($pattern, GLOB_BRACE + GLOB_MARK);
    if($recursivly or $get_folders) $folders = glob("*", GLOB_ONLYDIR + GLOB_MARK);
    
    //If a pattern is specified, make sure even the folders match that pattern.
    $matching_folders = array();
    if($pattern !== '*') $matching_folders = glob($pattern, GLOB_ONLYDIR + GLOB_MARK);
    
    //Get just the files by removing the folders from the list of all files.
    $all = array_values(array_diff($both,$folders));
        
    if($recursivly or $get_folders) {
        foreach ($folders as $this_folder) {
            if($get_folders) {
                //If a pattern is specified, make sure even the folders match that pattern.
                if($pattern !== '*') {
                    if(in_array($this_folder, $matching_folders)) array_push($all, $this_folder);
                }
                else array_push($all, $this_folder);
            }
            
            if($recursivly) {
                // Continue calling this function for all the folders
                $deep_items = ls($pattern, $this_folder, $recursivly, $options); # :RECURSION:
                foreach ($deep_items as $item) {
                    array_push($all, $this_folder . $item);
                }
            }
        }
    }
    
    if($folder) chdir($current_folder);
    return $all;
}

// Taken from http://davidwalsh.name/backup-mysql-database-php
// No explicit license. Assumed public domain.
// Ammended to use a database object by Alex Harrington.
// If this is your code, and wish for us to remove it, please contact
// info@xibo.org.uk
/* backup the db OR just a table */
function backup_tables($db,$tables = '*')
{
	//get all of the tables
	if($tables == '*')
	{
		$tables = array();
		$result = $db->query('SHOW TABLES');
		while($row = $db->get_row($result))
		{
			$tables[] = $row[0];
		}
	}
	else
	{
		$tables = is_array($tables) ? $tables : explode(',',$tables);
	}
	
	//cycle through
	foreach($tables as $table)
	{
		$result = $db->query('SELECT * FROM '.$table);
		$num_fields = $db->num_fields($result);
		
		$return.= 'DROP TABLE IF EXISTS '.$table.';';
		$row2 = $db->get_row($db->query('SHOW CREATE TABLE '.$table));
		$return.= "\n\n".$row2[1].";\n\n";
		
		for ($i = 0; $i < $num_fields; $i++) 
		{
			while($row = $db->get_row($result))
			{
				$return.= 'INSERT INTO '.$table.' VALUES(';
				for($j=0; $j<$num_fields; $j++) 
				{
					$row[$j] = addslashes($row[$j]);
					$row[$j] = ereg_replace("\n","\\n",$row[$j]);
					if (isset($row[$j])) { $return.= '"'.$row[$j].'"' ; } else { $return.= '""'; }
					if ($j<($num_fields-1)) { $return.= ','; }
				}
				$return.= ");\n";
			}
		}
		$return.="\n\n\n";
	}
	
	//save file
	$handle = fopen(Config::GetSetting($db,'LIBRARY_LOCATION') . 'db-backup-'.time().'-'.(md5(implode(',',$tables))).'.sql','w+');
	fwrite($handle,$return);
	fclose($handle);
}


function gen_secret() {
  # Generates a random 12 character alphanumeric string to use as a salt
  mt_srand((double)microtime()*1000000);
  $key = "";
  for ($i=0; $i < 12; $i++) {
    $c = mt_rand(0,2);
    if ($c == 0) {
      $key .= chr(mt_rand(65,90));
    }
    elseif ($c == 1) {
      $key .= chr(mt_rand(97,122));
    }
    else {
      $key .= chr(mt_rand(48,57));
    }
  } 
  
  return $key;
}

function createQuestions($step, $questions) {
	// Takes a multi-dimensional array eg:
	// $q[0]['question'] = "May we collect anonymous usage statistics?";
	// $q[0]['type'] = _CHECKBOX;
	// $q[0]['default'] = true;
	//
	// And turns it in to an HTML form for the user to complete.
	foreach ($questions as $qnum => $question) {
		echo '<div class="info"><p>';
		echo $question['question'];
		echo '</p></div><div class="install-table">';

		if (($question['type'] == _INPUTBOX) || ($question['type'] == _PASSWORD)) {
			echo '<input type="';
			if ($question['type'] == _INPUTBOX) {
				echo 'text';
			}
			else {
				echo 'password';
			}
			echo '" name="' . $step . '-' . $qnum .'" value="'. $question['default'] .'" length="12" />';
		}
		elseif ($question['type'] == _CHECKBOX) {
			echo '<input type="checkbox" name="' . $step . '-' . $qnum . '" ';
			if ($question['default']) {
				echo 'checked ';
			}
			echo '/>';
		}
		echo '</div><hr width="25%" />';
	}
}

//function __autoload($class_name) {
//    if (substr($class_name,0,4) == "Step") {
//	    $class_name = substr($class_name,4);
//	    require_once install/database/$class_name . '.php';
//    }
//}

class UpgradeStep 
{
	protected $db;
	protected $q;
	protected $a;

	public function __construct($db)
	{
		$this->db 	=& $db;
		$this->q	= array();
		$this->a	= array();
	}

	public function Boot()
	{

	}

	public function Questions()
	{
		return array();
	}

	public function ValidateQuestion($questionNumber,$response)
	{
		return true;
	}
}

?>
