<?php
// GDCMS Installation Script
define('VER', '0.1.5c');

//header
echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
  <title>Gowon Designs CMS '.VER.' Installation</title>
  <meta http-equiv="content-type" content="text/html; charset=iso-8859-1" />
<style type="text/css">
@media screen
{
body { font: .9em "Trebuchet MS", Arial, Sans-Serif; margin: 0px; }
h1,h2 { margin: 0 0 2px 0; font-size: 2em; }
h1 sup { font-size: .6em; }
hr { display: none; }
a { color: #036DA7; background: inherit; }
code { font: 1em "Courier New", Arial; padding: 0.5em; background-color: #ddd; margin: 1px; display: block; }
em {text-decoration: underline;}
#step { margin: 5px 5px 5px 20px; }
#wrap { width: 90%; margin: 30px auto; }

.readme { background: #E2FFD9; padding: 15px; border: 2px solid #69CB4B; margin: 10px 15px 0 15px; color: #1E4B00; }
.extra { width: 400px; margin: 20px auto 20px; text-align: center; padding: 10px; background: #FFFED1; border: 2px solid #E5E4A0; color: #4B4000; }
.header { background: #E8F0FF; padding: 15px; border: 2px solid #4C6199; margin: 10px 15px 0 15px; color: #4C6199; overflow:hidden; cursor:pointer; }
.content{ background: #fff; margin: 0 20px 0 20px; color: #4B4000; border-bottom: 1px solid #A5D5E7; border-left: 1px solid #A5D5E7; border-right: 1px solid #A5D5E7; overflow:hidden; position:relative; padding:10px 5px 5px 5px; }

}

@media print
{
.content{ display: block !important; }
}
</style>
<script type="text/javascript">
function toggle(div) {
	if (document.getElementById(div).style.display==\'\') {
		document.getElementById(div).style.display = \'none\';
		return
	} document.getElementById(div).style.display = \'\'; 
}	
</script>
</head>
<body onload="toggle(\'help\');">

<div class="readme">
		<h1>Gowon Designs CMS<sup>'.VER.'</sup> Installation</h1>
		
		<p>Welcome to <a href="http://www.gowondesigns.com/">Gowon Designs CMS</a> - a single file, template independant, <a href="http://www.php.net/">PHP</a> and <a href="http://www.mysql.com/">MySQL</a> powered, standards valid <a href="http://en.wikipedia.org/wiki/Content_management_system">Content Management System</a>.</p>
		
			<p><b>Before installing please check minimum system requirements:</b></p>
			<p><a href="http://www.apache.org/">Apache</a> Server<br />
			<a href="http://www.php.net/">PHP</a>: Hypertext Preprocessor version 4.x or greater<br />
			<a href="http://www.mysql.com/">MySQL</a> database version 3.23 or greater</p>
			<p>These applications are freely available, and can be installed both on Windows and Linux OS (detailed info can be obtained at their websites).</p>
			<p><u>To view more details/instructions for this step, click on it\'s header.</u>
      ';
if (!is_writable("core.php")) echo '<br /><b>core.php is not currently set to be written over. It must be CHMOD 755 in order for this script to work. If you cannot CHMOD 755 leap.php, you must do a manual install.</b>';
echo '</p></div><hr />';

function setupQuery($p, $type, $username='admin@site.com', $password='pass') {
$date=date("YmdHis");

// Table structure for table `system`
$q[]="CREATE TABLE `".$p."system` (
  `id` int(11) unsigned NOT NULL primary key auto_increment,
  `type` varchar(255) NOT NULL default '',
  `name` varchar(255) NOT NULL default '',
  `data` longtext NOT NULL
);";

// Table structure for table `users`
$q[]="CREATE TABLE `".$p."users` (
  `id` int(11) unsigned NOT NULL primary key auto_increment,
  `name` varchar(255) NOT NULL default '',
  `pwd` varchar(255) NOT NULL default '',
  `mail` varchar(255) NOT NULL default '',
  `permissions` varchar(255) NOT NULL default ''
);";

// Table structure for table `content`
$q[]="CREATE TABLE `".$p."content` (
  `id` int(11) unsigned NOT NULL primary key auto_increment,
  `cat` int(11) NOT NULL default '0',
  `auth` int(11) NOT NULL default '1',
  `type` varchar(10) NOT NULL,
  `title` varchar(100) NOT NULL,
  `sef_title` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL default '',
  `keywords` varchar(255) NOT NULL default '',
  `body` longtext NOT NULL,
  `date` bigint(14) unsigned NOT NULL,
  `mod_date` bigint(14) unsigned NOT NULL,
  `published` tinyint(1) NOT NULL default '0',
  `unpublish` tinyint(1) NOT NULL default '0',
  `enddate` bigint(14) unsigned NOT NULL,
  `comments` tinyint(1) NOT NULL default '0',
  FULLTEXT KEY `content` (`title`,`body`,`description`,`keywords`)
);";

// Table structure for table `comments`
$q[]="CREATE TABLE `".$p."comments` (
  `id` int(11) unsigned NOT NULL primary key auto_increment,
  `pid` int(11) NOT NULL,
  `date` bigint(14) NOT NULL,
  `name` varchar(30) NOT NULL,
  `ip` varchar(255) NOT NULL,
  `url` varchar(50) NOT NULL,
  `comment` varchar(255) NOT NULL,
  `approved` tinyint(1) NOT NULL default '0'
);";

// Table structure for table `categories`
$q[]="CREATE TABLE `".$p."categories` (
  `id` int(11) unsigned NOT NULL primary key auto_increment,
  `title` varchar(100) NOT NULL,
  `sef_title` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL default '',
  `published` tinyint(1) NOT NULL default '0',
  `position` tinyint(2) NOT NULL default '0',
  `sub_id` int(11) NOT NULL default '0'
);";

// Table structure for table `menus`
$q[]="CREATE TABLE `".$p."menus` (
  `id` int(11) unsigned NOT NULL primary key auto_increment,
  `name` varchar(100) NOT NULL,
  `title` varchar(100) NOT NULL,
  `data` longtext NOT NULL
);";

// Table structure for table `extra`
$q[]="CREATE TABLE `".$p."extra` (
  `id` int(11) unsigned NOT NULL primary key auto_increment,
  `title` varchar(100) NOT NULL,
  `space` varchar(255) NOT NULL,
  `body` longtext NOT NULL,
  `for_cat` longtext NOT NULL,
  `for_content` longtext NOT NULL,
  `for_process` longtext NOT NULL,
  `position` tinyint(2) NOT NULL default 1,
  `published` tinyint(1) NOT NULL default 0
);";

// Table structure for table `extensions`
$q[]="CREATE TABLE `".$p."extensions` (
  `id` int(11) unsigned NOT NULL primary key auto_increment,
  `name` varchar(100) NOT NULL,
  `TotalFunctions` longtext NOT NULL,
  `FuncTrigger` text NOT NULL,
  `FuncName` text NOT NULL,
  `SpecFuncTrigger` text NOT NULL,
  `SpecFuncName` text NOT NULL,
  `TitleFuncTrigger` text NOT NULL,
  `TitleFuncName` text NOT NULL,
  `path` longtext NOT NULL,
  `url` longtext NOT NULL,
  `settings` longtext NOT NULL,
  `active` tinyint(1) NOT NULL
);";


// Adding Default System Options
$q[]="INSERT INTO `".$p."system` VALUES (1,'config','version;;GDCMS Version;;GDCMS Version;;hidden','".VER."');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','character-encoding;;Meta Character Encoding;;Change the encoding of the CMS (useful when using non-English languages with special characters).;;text', 'utf-8');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','security-key;;Website Security Key;;This key randomizes the security tokens for your website. It is recommended that you periodically change this to ensure your security. Insert many random characters.;;text', '".md5($date)."');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','enable-modrewrite;;Enable ModRewrite;;Enable ModRewrite for the system. Note, your server must be able to support this feature and be configured for this feature to work properly.;;checkbox', '');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','title;;Website Title;;The title of your website will be used in some internal functions of the website.;;text', 'Your GDCMS Website');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','slogan;;Website Slogan;;The slogan that your website will use;;text', 'Your Site. Your Slogan.');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','keywords;;Meta Keywords;;Meta keywords are included in your page to optimize indexing in popular search engines;;textarea', 'Your, Site, Keywords');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','description;;Meta Description;;The description of your website that is used by search engines when indexing your pages;;textarea', 'Your Site Description');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','site-email;;Site Email;;This email address is used by some internal functions to send emails directly to the admin;;text', 'info@yoursite.com');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','num-articles;;Articles per Page in List View;;This displays # of articles per page in the list view;;text', '5');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','search-num-content;;Articles per Page in Search List;;This displays # of articles per page in the search list;;text', '25');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','rss-num-articles;;Most Recent Articles in RSS;;Sends out the  # most recent articles in the RSS feed;;text', '10');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','default-page;;Default Page;;The default page of your website. It can be any page, article, or function;;text', 'list');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','date-format;;Date Display Format;;Change the way dates are formatted on the website. Follows the date rules shown on <a href=\"http://us.php.net/date\">http://us.php.net/date</a>.;;text', 'M d, g:i a');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','list-format;;Content Format in List View;;Change the article format in the list view;;textarea', '<h1><a href=&##!?article.[SEF_TITLE]&##!>[TITLE]</a></h1><h2>by [AUTHOR] at [DATE]</h2><p>[BODY]<br />[COMMENT] [EDIT]</p>');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','article-format;;Content Format in Article View;;Change the format of articles when they''re viewed individually;;textarea', '<h1>[TITLE]</h1><h2>by [AUTHOR] at [DATE] [C=C]</h2><p>[BODY]</p> [EDIT]');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','autopub-articles;;Auto-Publish Content;;New content is automatically set to be published with this setting is on.;;checkbox', '1');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','display-pagination;;Display Pagination;;Enables GDCMS&#! built-in pagination to handle the organization of articles in the list view.;;checkbox', '1');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','pagination-button-number;;Max Number of Buttons in Pagination;;The maximum number of pagination buttons listed at any time in the list view.;;text', '5');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','js-enabled-pagination;;Enable JavaScript Pagination;;When enabled, the built-in JS Pagination is used. When disabled, regular link buttons are used.;;checkbox', '');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','allow-comment;;Enable Comments;;Enable visitors to post comments on content;;checkbox', '1');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','moderate-comment;;Moderated Comments;;When enables, comments must be accepted through the Comments panel before displayed on the website.;;checkbox', '1');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','extensions-folder;;Extensions Folder;;Path for the extensions folder.;;text', 'extensions/');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','file-include-extensions;;File Upload Allowable Extensions;;The extensions of files that are allowed to be uploaded through the built-in file manager.;;text', '.php,.txt,.inc,.htm,html');";
$q[]="INSERT INTO `".$p."system` VALUES ('','config','panel-item-list-num;;Admin Panel Max Item List Number;;The max # of items listed at one time under different sections of Admin Panel. Useful to change when sites create a large number of content.;;text', '10');";

// Admin Log
$logmsg="This is a simple Admin Log, it allows anyone to add/update notes on the go and gives multiple admin the ability to communicate and relay messages to each other through the site.\n\nThe admin log is contained in a simple div, so if you would like to hide this from the admin panel, all you need to do is add:\n\n.logdiv { display: none; }\n\ninto your CSS stylesheet and it will disappear.";
$q[]="INSERT INTO `".$p."system` VALUES ('', 'admin', 'log', '$logmsg');";

// Main Menu
$q[]="INSERT INTO `".$p."menus` VALUES (1, 'main', 'Main Menu', 'Home;;[];;Example Page;;[page.example-page];;Support;;http://www.gowondesigns.com/');";

// Default Admin
$q[]="INSERT INTO `".$p."users` VALUES (1,'Administrator','".md5($password)."','".$username."','abcdefgh');";

// Default Category
$q[]="INSERT INTO `".$p."categories` VALUES (1,'General','general','General site news and updates.',1,1,0);";

// Default Article
$q[]="INSERT INTO `".$p."content` VALUES (1, 1, 1, 'article', 'Welcome to GDCMS', 'welcome-to-GDCMS', '', 'GDCMS, New Installation', 'Welcome! Thank you for installing Gowon Designs CMS ".VER.". Your setup has been successfully completed and you are now able to use GDCMS.', $date, $date, 1, 0, $date, 0);";
// Default Page
$q[]="INSERT INTO `".$p."content` VALUES (2,1,1,'page','Example Page','example-page','','GDCMS, Examples, Test','<h1>Example Page</h1>\r\n\r\n<p>This is an example of a custom page. You can create your own custom pages (either through hand coding, or linking external files) through the control panel. You can use this feature to integrate PHP scripts into GDCMS<p>',$date, $date, 1, 0, $date, 0);";

// Default Extra Content
$q[]="INSERT INTO `".$p."extra` VALUES (1, 'Extra Content Example', 'sidebar', '<div class=\"extra\">\r\n\r\n<h1>Extra Content</h1>\r\n\r\n<p>Extra content is used to supply additional info tertiary to the main content on your website. You can customize the extra content in the admin panel, as well as select under which circumstances the content is displayed. Modifying the look of this content is as easy as tweaking the CSS code.</p>\r\n\r\n</div>', '-1', '()', '(admin)(list)', 1, 1);";

//Upgrade Queries
$u[]="INSERT INTO `".$p."content` VALUES ('',1,1,'article','GDCMS Upgrade Successful','leap-upgrade-successful','','GDCMS, Upgrade','Your Gowon Designs CMS upgrade to version ".VER." was successful! This is just a simple notification, you can delete this article.',$date, $date, 1, 0, $date, 0);";

$u[]="UPDATE ".$p."system SET data='".VER."' WHERE name='version'";


return ($type=='full') ? $q:$u;
}

if ($_POST['full']||$_POST['upgrade']) { $p=$_POST['dbpref']; include("core.php");
echo '<div class="header" title="Click here for more details" onclick="toggle(\'help\')"><h1>Database Installation</h1></div><hr />
<div class="content">
<div id="help">
<ul>
<li>The installation is only successful if every query is executed successfully.</li>
<li>Errors may occur when the prefix you used (<b>'.$p.'</b>) is already in use by another script.</li>
<li>You may also want to <b>check the MySQL website</b> (<a href="http://dev.mysql.com/doc/refman/4.1/en/common-errors.html">http://dev.mysql.com/doc/refman/4.1/en/common-errors.html</a>) for more information on any errors that occur.</li>
</ul>
</div>
';

/* The script first attempts to clear any pre-existing tables of the same name */

if ($_POST['full']) {
@mysql_query("DROP TABLE `".$p."system`");
@mysql_query("DROP TABLE `".$p."users`");
@mysql_query("DROP TABLE `".$p."menus`");
@mysql_query("DROP TABLE `".$p."extra`");
@mysql_query("DROP TABLE `".$p."extensions`");
@mysql_query("DROP TABLE `".$p."categories`");
@mysql_query("DROP TABLE `".$p."content`");
@mysql_query("DROP TABLE `".$p."comments`");
}


$q= ($_POST['full']) ? setupQuery($p,'full',$_POST['adminuser'],$_POST['adminpass']):setupQuery($p,'upgrade',$_POST['adminuser'],$_POST['adminpass']);
$g=count($q); $s=0;
for ($i=0; $i < $g; $i++) {
mysql_query($q[$i]); $e=mysql_error(); $s= ($e) ? $s+1:$s;
if(!empty($e)) echo "\n<p><b style=\"color: #900;\">An error occured while executing Query ".($i+1).":</b> $q[$i]<br />$e</p>";
}

if($s==0) echo "\n<p><b style=\"color: #090;\">All queries processed without fail.</b></p>";

echo "</div>\n<hr />";

if ($s>0) { //Installation Unsuccessful
echo '<div class="header" title="One or more errors occured during the installation." style="background: #FFE2D9; border: 2px solid #CB694B; color: #CB694B;"><h1>Installation Unsuccessful</h1></div><hr />
<div class="content" style="border-bottom: 1px solid #E7BBA5; border-left: 1px solid #E7BBA5; border-right: 1px solid #E7BBA5;">
  One or more errors occured during the installation. You may want to <b>save</b> or <b>print this page</b> for future reference.
  <p>Please check your settings, <b>re-upload core.php</b>, and try again.</p>
  <p>If you continue to have problems installing GDCMS, <b>consider</b> using a <b>manual install</b> or requesting support on at <a href="http://www.gowondesigns.com/" style="color: #900;"><b>Gowon Designs</b></a>.</p>
</div><hr />';
} else { //Installation Successful
echo '<div class="header" title="You are ready to start using your website!" style="background: #E2FFD9; border: 2px solid #69CB4B; color: #69CB4B;"><h1>Installation Successful!</h1></div><hr />
<div class="content" style="border-bottom: 1px solid #D5E7A5; border-left: 1px solid #D5E7A5; border-right: 1px solid #D5E7A5;">
  <b>Installation is Complete!</b>
  <p>Make sure you <b>delete install.php</b>. <b><em>This will prevent people from overwriting your data!</em></b></p>
  <p>The default username is <b>'.$_POST['adminuser'].'</b> and password is <b>'.$_POST['adminpass'].'</b>.</p>
  <p><a href="'.db('website').'?admin" style="color: #090;"><b>Login</b></a> to start adding content and managing your site.</p>
  <p><b>Before you begin using GDCMS, it is <em>highly recommended</em> that you change the password of the default account.</b></p>
</div><hr />';
}

}
elseif ($_POST['setval']||$_POST['manual']) {
$q=setupQuery($_POST['dbpref'],'full',$_POST['adminuser'],$_POST['adminpass']); $helph='<div id="help">'; $helpf='</div>';

//Data to be prepended to core.php
$data='<?php
/****************************************************************************
  Gowon Designs CMS '.VER.'
  Copyright (c) 2006-10 Gowon Designs, Gowon Patterson - All Rights Reserved
  GDCMS is licensed under the Open Software License 3.0
  http://www.opensource.org/licenses/osl-3.0.php
****************************************************************************/

function db($variable) {
	$db = array();
	$db[\'website\']=\''.$_POST['path'].'\';    //Website URL
	$db[\'dbhost\']=\''.$_POST['dbhost'].'\';   //MySQL Host
	$db[\'dbname\']=\''.$_POST['dbname'].'\';   //Database Name
	$db[\'dbuname\']=\''.$_POST['dbuser'].'\';  //Database Username
	$db[\'dbpass\']=\''.$_POST['dbpass'].'\';   //Database password
	$db[\'prefix\']=\''.$_POST['dbpref'].'\';   //Database prefix
	$db[\'dberror\']=\'<strong>There was an error while connecting to the database.</strong><br />Check your database settings.\'; //Database error message
	$db[\'query\']=$_SERVER[\'QUERY_STRING\'];
  return $db[$variable];
}
';

$title=($_POST['setval']) ? 'Script':'Manual';
echo '<div class="header" title="Click here for more details" onclick="toggle(\'help\')"><h1>Step 2: '.$title.' Installation</h1></div><hr />
<div class="content">';

if ($_POST['setval']) { //Script Installation
echo $helph."<ul><li>The settings displayed below have been added to the core.php file.</li> 
<li>If any of the information here is incorrect, the installation will not be able to continue and GDCMS will not function properly.</li> 
<li>To ensure that the installation will be successful, the script attempts to clear any pre-existing tables with the same names as the ones used in this script (if you use a unique prefix, this will not be a problem).</li></ul>".$helpf;

echo "These values were added to the core.php:
<p>Site Path: <b>$_POST[path]</b>
<br />Database Host: <b>$_POST[dbhost]</b>
<br />Database Name: <b>$_POST[dbname]</b>
<br />Username: <b>$_POST[dbuser]</b>
<br />Password: <b>$_POST[dbpass]</b>
<br />Table Prefix: <b>$_POST[dbpref]</b></p>
";

//write string to file $fi
$fi="core.php";
if (!file_exists($fi)) touch($fi);
$fh = fopen($fi, "r");
$fcontent = fread($fh, filesize($fi));

$towrite = $data.$fcontent;

$fh2 = fopen($fi, 'w+');
fwrite($fh2, $towrite);
fclose($fh); fclose($fh2);

include("core.php");
$c=@mysql_query("SELECT * FROM ".db('prefix')."system WHERE id='1'"); $v=@mysql_fetch_array($c);

if (isset($v['data'])) {

echo '<div style="background: #FFE2D9; border: 2px solid #CB694B; margin: 10px; padding: 5px;"><h1>Previous Installation Detected</h1>
<p><b>GDCMS '.$v['data'].'</b> is currently installed on the <b>'.$_POST['dbpref'].'</b> prefix you selected for this installation. ';
//Check if versions are compatible for upgrade
if (floatval(VER)==floatval($v['data'])) { $install_but='<input type="submit" name="full" value="Continue with Full Installation" /> <input type="submit" name="upgrade" value="Upgrade" />'; 
echo 'If you are upgrading your version of GDCMS, press the Upgrade button and your data will be saved. 
Clicking Full Installation will wipe the tables used by GDCMS, and <b>ALL DATA</b> (articles, pages, user info, menus) from the previous version <b>WILL BE DELETED</b>. 
If you do not wish to continue with these settings, you will need to re-upload the core.php file and restart the installation, selecting a new prefix.';
} else { $install_but='<input type="submit" name="full" value="Continue with Database Installation" />';
echo '<b>GDCMS '.VER.' is NOT able to provide an upgrade for version '.$v['data'].'.</b> 
If you continue with this installation, <b>ALL DATA</b> (articles, pages, user info, menus) from the previous version <b>WILL BE DELETED</b>. 
If you do not wish to continue with these settings, you will need to re-upload the core.php file and restart the installation, selecting a new prefix.';
}

echo '</p></div>';
} else $install_but='<input type="submit" name="full" value="Continue with Database Installation" />'; 

//Go to the next step.
echo '<p>If any of these values are incorrect, you will need to <b>re-upload</b> the <b>core.php</b> that came with this package and start the installation over.</p>
<form action="?" method="post"><p><input type="hidden" name="dbpref" value="'.$_POST['dbpref'].'" /><input type="hidden" name="adminuser" value="'.$_POST['adminuser'].'" /><input type="hidden" name="adminpass" value="'.$_POST['adminpass'].'" />'.$install_but.'</p></form>';

} else { //Manual Installation

$g=count($q);
for ($i=0; $i < $g; $i++) { $sql.=$q[$i]."\n\n"; }

echo $helph."<ul><li>Complete the steps in the order that they are given. If any errors occurs, please write them down, check your settings, and try again.</li></ul>".$helpf;

echo '<form name="data"><p><b>1. Paste this code</b> into the top of core.php:</p>
<p><textarea name="data" style="height:200px; width:80%; margin:0 0 0 20px;">'.$data.'</textarea>
<br /><input type="button" value="Highlight Code" onclick="javascript:this.form.data.focus();this.form.data.select();" style="margin: 0 0 0 40px;" /></p></form>';

echo '<form name="dbdata"><p><b>2. Create the MySQL database</b> with this code: </p>';

//Attempt to check database for pre-existing instances in DB to warn user
$db=@mysql_connect($_POST['dbhost'],$_POST['dbuser'],$_POST['dbpass']); @mysql_select_db($_POST['dbname'],$db);
$query=mysql_query("SELECT * FROM ".$_POST['dbpref']."system WHERE name='version'");
while ($check=@mysql_fetch_array($query)) { echo '<p style="margin:0 0 0 20px; font-weight: bold; color: #CB694B;">There is an instance of GDCMS already using the prefix you specified. You will need to delete the existing database tables or change the prefix to avoid errors.</p>'; }

echo '<p><textarea name="sql" style="height:200px; width:80%; margin:0 0 0 20px;">'.$sql.'</textarea>
<br /><input type="button" value="Highlight Code" onclick="javascript:this.form.sql.focus();this.form.sql.select();" style="margin: 0 0 0 40px;" /></p></form>

<p><b>3. Login and start managing your site</b> after completing the steps above:</p>
<p style="margin:0 0 0 20px;">Login to your site by going to <a href="'.$_POST['path'].'?admin">'.$_POST['path'].'?admin</a>.<br />The default username is <b>'.$_POST['adminuser'].'</b> and password is <b>'.$_POST['adminpass'].'</b>.<br />It is recommended that you change the default user information (if you hadn\'t already) and <b>DELETE</b> install.php after completing installation for security reasons.</p>';
}

echo "</div>\n<hr />";
} else { // Step One: Setting Up the CMS to connect to the database.

// This bit of code predicts the site path for the user
$fl=explode("/",$_SERVER['PHP_SELF']);
for ($i=0; $i<(count($fl)-1); $i++) $path.=$fl[$i].'/';
$url="http://".$_SERVER['SERVER_NAME'].$path;

echo '<div class="header" title="Click here for instructions" onclick="toggle(\'help\')"><h1>Step 1: Database & Site Configuration</h1></div><hr />
<div class="content">

<div id="help">
<ul>
<li>Type in the full path of you website, including a trailing slash. (ex. "http://www.yourdomain.com/" or "http://www.yourhost.com/yoursite/")</li>
<li>Your DB Host is usually "localhost". Only change that if your host has instructed you otherwise.</li>
<li>The DB name is the name of the database that GDCMS will be installed on.</li>
<li>Insert the username and password used to connect to the database.</li>
<li>It is highly recommended to use prefixes to distinguish GDCMS DB tables from others in your database. This can be left blank, but <b><em>do not use any symbols</em></b>.</li>
<li>After <b>checking to make sure all of your data is correct</b>, click "Begin Installation."</li>
</ul>
<p style="text-align:center;">The information will automatically be installed into your database. If any error occurs, write the errors down, check your DB and site settings, <b>re-upload core.php</b> and try again.</p>
</div>

<div class="extra"><form action="?" method="post">

<h1>GDCMS Configuration</h1>
<p><label for="path">Site Path</label> <input type="text" name="path" length="10" value="'.$url.'" /></p>
<p><label for="dbhost">DB Host</label> <input type="text" name="dbhost" length="10" value="localhost" /></p>
<p><label for="dbname">DB Name</label> <input type="text" name="dbname" length="10" /></p>
<p><label for="dbuser">DB Username</label> <input type="text" name="dbuser" length="10" value="root" /></p>
<p><label for="dbpass">DB Password</label> <input type="text" name="dbpass" length="10" /></p>
<p><label for="dbpref">DB Prefix</label> <input type="text" name="dbpref" length="10" maxlength="10" /></p>
<h1>Administration Account</h1>
<p><label for="dbpass">Email Address</label> <input type="text" name="adminuser" length="10" value="admin@site.com" /></p>
<p><label for="dbpref">Password</label> <input type="text" name="adminpass" length="10" maxlength="10" value="pass" /></p>';
if (!is_writable("core.php")) echo '<p><b>leap.php is not currently set to be written over. If you cannot CHMOD 755 leap.php, you must do a manual install.</b></p>';

echo '<p><input type="submit" name="setval" value="Begin Installation" />&nbsp;<input type="submit" name="manual" value="Manual Install" />&nbsp;<input type="reset" value="Reset" /></p>
</form></div>

</div>
<hr />
';

}

///Footer
echo '<div class="header" title="Additional Info" onclick="toggle(\'a\')"><h1>Additional Info</h1></div><hr />
<div class="content" id="a">
			Bug reports, suggestions, comments, questions:<br /><a href="http://www.gowondesigns.com/">Gowon Designs</a>
			<p><a href="http://www.gowondesigns.com/">GDCMS</a> is licensed under the <a href="http://www.opensource.org/licenses/osl-3.0.php">Open Software License 3.0</a>.<br />
			Copyright &copy; 2006-10, <a href="http://www.gowondesigns.com/">Gowon Designs</a></p>
</div>
<div class="spacer">&nbsp;</div>

</body>
</html>';

?>
