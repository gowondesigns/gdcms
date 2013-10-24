
///////////////////////////////////////////////////////
///////////////////////////////////////////////////////
ini_set('arg_separator.output',';');
error_reporting(0); //error_reporting(E_ALL ^ E_NOTICE);

// AUTOMATED ACTIONS: First parses the query, connects to database, then checks the session
$qAction=parseQuery();
$db=mysql_connect(db('dbhost'),db('dbuname'),db('dbpass')) or die ('I cannot connect to the database because: '.mysql_error());
if(!$db) die(db('dberror')); if(!mysql_select_db(db('dbname'),$db)) die(db('dberror'));
$leap=new leapInit;
$leap->updateContent();
$leap->checkSession();

//Clear all global variables for XSS
$myFilter = new InputFilter();
$qAction = $myFilter->process($qAction);

if( isset($_POST['secureAction']) ) { //All admin functions are labelled as secure actions, they must be verified
$tknlbl=token_label();
if ( isset($_POST[$tknlbl]) && verify_token($_POST[$tknlbl]) ) $_POST = $_POST; // Token label and key must be true
else unset($_POST); // If the token isn't passed, the POST is completely unset
} else $_POST = $myFilter->process($_POST); //everything else is processed by the XSS filter

$_GET = $myFilter->process($_GET);
$_COOKIES = $myFilter->process($_COOKIES);

define('CHARSET',_value('character-encoding'));
mysql_query("SET CHARACTER SET ".CHARSET);
/* INCLUDING EXTENSIONS *///

if ($handle=@opendir(_value('extensions-folder'))) { 

$q=mysql_query("SELECT * FROM ".db('prefix')."extensions WHERE active='1'");
while ($r=mysql_fetch_array($q)) { $INSTALLED_EXT.="`$r[path]`"; }
while($item=@readdir($handle)) { if(strstr($INSTALLED_EXT,$item)!=FALSE) $INC_LIST[]=$item; }
@closedir($handle);

if (!empty($INC_LIST)) {
foreach ($INC_LIST as $ext) { $ext='extensions/'.$ext.'/index.php'; include_once($ext); }
}

} //*/

//EXECUTE SPECIAL FUNCTIONS
if (in_array($qAction[0], $leap->SpecFuncTriggers, true)) {
$key= array_search($qAction[0], $leap->SpecFuncTriggers);
$SpecialFunction=$leap->SpecFuncNames[$key];
$SpecialFunction();
}

//SEPERATE QUERY
function parseQuery() { return explode(".",$_SERVER['QUERY_STRING']); }

//RECALL SYSTEM VALUES
function value($v) { echo ($v=='website') ? db($v): _value($v); }
function _value($v) { $s=@mysql_query("SELECT * FROM ".db("prefix")."system WHERE type='config' AND name LIKE '$v;;%'"); $r=@mysql_fetch_array($s); return $r['data']; }

//BREADCRUMBS
function breadcrumbs($sepchar="&nbsp;&middot;&nbsp;") {
global $qAction;
if($_SESSION['validUser']==TRUE) $crumbs[]='<a href="'.db("website").'?admin">Admin</a>';
$crumbs[]='<a href="'.db("website").'">Home</a>';
$categories=categoryList();

if($qAction[0]=="search") $crumbs[]='<a href="'.db("website").'?search">Search</a>'; //For searches, just a link
elseif($qAction[0]=="list") { //For lists, check for subcategories
$id=$qAction[1]; $trigger= (is_numeric($id)) ? "id='$id'":"sef_title='$id'";
$position=search_in_array($id,$categories);
$key = key($position);
$crumbs[]='<a href="'.db("website").'?list.'.$categories[$key][0]['sef_title'].'">'.$categories[$key][0]['title'].'</a>';
$subkey = key($position[$key]) or FALSE;

if ($subkey) $crumbs[]='<a href="'.db("website").'?list.'.$categories[$key][$subkey][0]['sef_title'].'">'.$categories[$key][$subkey][0]['title'].'</a>';

}
elseif($qAction[0]=="article"||$qAction[0]=="page") { //For content, check for subcategories
$id=$qAction[1];
$trigger= (is_numeric($id)) ? "id='$id'":"sef_title='$id'";
$q=mysql_query("SELECT * FROM ".db("prefix")."content WHERE $trigger"); $r=mysql_fetch_array($q);

$position=search_in_array((int) $r['cat'],$categories);
$key = key($position);
$crumbs[]='<a href="'.db("website").'?list.'.$categories[$key][0]['sef_title'].'">'.$categories[$key][0]['title'].'</a>';
$subkey = key($position[$key]) or FALSE;

if ($subkey) $crumbs[]='<a href="'.db("website").'?list.'.$categories[$key][$subkey][0]['sef_title'].'">'.$categories[$key][$subkey][0]['title'].'</a>';
$crumbs[]=$r['title'];
}

$breadcrumbs=implode($sepchar, $crumbs);
echo $breadcrumbs;
}


//List parent/subcategories from base category
function categoryList() {
//Find all parent categories
$srch=mysql_query("SELECT * FROM ".db('prefix')."categories WHERE sub_id='0'");
while ($c=mysql_fetch_array($srch)) {
$id=(int) $c['id'];
$categories[$id][0]=array('id' => (int) $c['id'], 'title' => $c['title'],'sef_title' => $c['sef_title']);
}
//Find any possible child categories
foreach($categories as $id => $data) {
$srch=mysql_query("SELECT * FROM ".db('prefix')."categories WHERE sub_id='$id'");
while ($c=mysql_fetch_array($srch)) {
$subid=(int) $c['id'];
@$categories[$id][$subid][0]=array('id' => (int) $c['id'], 'title' => $c['title'],'sef_title' => $c['sef_title'], 'published' => $c['published']);
}

}

return $categories;
}

//Recursive Search in Array Function (used specifically for categories)
function search_in_array($needle,$haystack,$inverse=false,$limit=1){
#Settings
$path=array(); $count=0;

#Checkifinverse
if($inverse==true)
$haystack=array_reverse($haystack,true);

#Loop
foreach($haystack as $key=>$value){

#Checkforreturn
if($count>0&&$count==$limit)
return $path;

#Checkforval
if($value===$needle){

#Addtopath
$path[]=$key;

#Count
$count++;

}elseif(is_array($value)){

#Fetchsubs
$sub=search_in_array($needle,$value,$inverse,$limit);

#Checkiftherearesubs
if(count($sub)>0){

#Addtopath
$path[$key]=$sub;

#Addtocount
$count+=count($sub);
}
}
}
return $path;
}

//SQL FRIENDLY 
//Makes code SQL and Textarea friendly
//Needs reworking
function SEFlinks() {
$pcs=func_get_args();
$seperator= (_value('enable-modrewrite')==0) ? '.':'/';
$qmark=(_value('enable-modrewrite')==0) ? '?':'';
return $qmark.implode($seperator, $pcs);
}

//PROCESS SYSTEM TITLE
function title($seperator='') {
global $qAction;
global $leap;
$title= ($seperator!='') ? _value('title')." $seperator "._value('slogan'): _value('title');
echo "<title>$title</title>";

if ($qAction[0]=="article"||$qAction[0]=="page") {
$col=(is_numeric($qAction[1])) ? 'id':'sef_title';
$q="SELECT * FROM ".db('prefix')."content WHERE $col='".$qAction[1]."'";
$r=mysql_fetch_array(mysql_query($q)); }

$keywords=_value('keywords');
if ($r['keywords']!='') $keywords.=', '.$r['keywords'];
$desc= ($r['description']!='') ? $r['description']:_value('description');
echo "\n".'<meta http-equiv="content-type" content="text/html; charset='._value('character-encoding').'" />'.
"\n".'<meta name="keywords" content="'.$keywords.'" />'."\n".'<meta name="description" content="'.$desc.'" />';
//Base HREF useful for ModRewrite
//echo "\n\n<base href=\"".db('website')."\" />\n\n";
 
//things that need to be performed in the header to go along with customized functions.
if (in_array($qAction[0], $leap->TitleFuncTriggers, TRUE)) {
$key= array_search($qAction[0], $leap->TitleFuncTriggers);
$TitleFunction=$leap->TitleFuncNames[$key];
$TitleFunction();
} //*/

}

//CHECK IF CURRENT VERSION OKAY
function checkUpdate() {
$check_url="http://leap.gowondesigns.com/checkupdate.php?"._value("version");
$handle=@fopen($check_url, "r"); $return=fgets($handle, 1024);
fclose($handle); $NEED_UPGRADE=explode(';',$return);
if ($NEED_UPGRADE[0]=='TRUE') $status="<p id=\"needUpgrade\"><a href=\"http://leap.gowondesigns.com/\">New version <b>$NEED_UPGRADE[1]</b> is available!</a></p>";
else $status="<p id=\"versionOkay\">Current version <b>$NEED_UPGRADE[1]</b> is okay!</p>";
return $status;
}

// SECURITY TOKEN FUNCTIONS
function rand_alphanumeric() {
      $subsets[0] = array('min' => 48, 'max' => 57); // ascii digits
      $subsets[1] = array('min' => 65, 'max' => 90); // ascii lowercase English letters
      $subsets[2] = array('min' => 97, 'max' => 122); // ascii uppercase English letters
      $s = rand(0, 2);
      $ascii_code = rand($subsets[$s]['min'], $subsets[$s]['max']);
 
      return chr( $ascii_code );
     }  

function token_label() { return md5( _value("security-key")); }

function make_token() {
  $str = "";
  for ($i=0; $i<7; $i++) $str .= rand_alphanumeric();
  $pos = rand(0, 24);
  $str .= chr(65 + $pos);
  return $str . substr(md5($str . _value("security-key")), $pos, 8);
}

function verify_token($str) {
    $rs = substr($str, 0, 8);
    return $str == $rs . substr(md5($rs .  _value("security-key")), ord($str[7])-65, 8);
  }



//CONVERT FOR DB: Converting single- and double-quotes so they don't disrupt the Database
function convertForDb($input) { 
//$input['text'] - The text being converted
//$input['type'] - options: db, screen

if($input['type']=='db') $text=str_replace(array("'",'"'),array("&#!","&##!"),$input['text']);
elseif($input['type']=='screen') $text=str_replace(array("&#!","&##!"),array("'",'"'),$input['text']);

return $text;
}

//Function to create simple HTML form elements
function formItem($item) {

$item['id']=(!$item['id']) ? '': ' id="'.$item['id'].'"';
$item['extra']=(!$item['extra']) ? '': ' '.$item['extra'];

if ($item['type']=='textarea'){
$item['value']=htmlentities($item['value'], ENT_QUOTES, _value('character-encoding'));
$formItem='<textarea name="'.$item['name'].'"'.$item['id'].$item['extra'].'>'.$item['value'].'</textarea>';
} 

elseif ($item['type']=='checkbox'){
$item['value']=($item['value']==1) ? ' value="1" checked="checked"': ' value="1"';
$formItem='<input type="'.$item['type'].'"'.$class.' name="'.$item['name'].'"'.$item['id'].$item['value'].$item['extra'].' />';
}

else { //text, password, button, submit, refresh, checkbox, radio
if (ereg($item['type'],'(text|password)')) $class=' class="text"';
elseif (ereg($item['type'],'(button|submit|reset)')) $class=' class="button"';
else $class=''; 

$item['value']=(!isset($item['value'])) ? '': ' value="'.$item['value'].'"';

$formItem='<input type="'.$item['type'].'"'.$class.' name="'.$item['name'].'"'.$item['id'].$item['value'].$item['extra'].' />';
}

return $formItem."\n\n"; 
}

//FILE INCLUSION - slightly modified - from sNews 1.5 http://snews.solucija.com
function processIncludes($text, $shorten='') {
	$fulltext= ($shorten!='') ? substr($text, 0, $shorten):$text;
	$inc = strpos($fulltext, '[/include]');
	if ($inc > 0) {
		$text = str_replace('[include]', '|&|', $fulltext);
		$text = str_replace('[/include]', '|&|', $text);
		$text = explode('|&|', $text); 
		$num = count($text);
		$extension = explode(',', _value('file-include-extensions'));
		for ($i = 0; ; $i++) {
			if ($i == $num) {break;}
			if (!in_array(substr($text[$i], -4), $extension)) {echo substr($text[$i], 0);}
			else {
				if (preg_match("/^[a-z0-9_\-.\/]+$/i", $text[$i])) {
					$filename = $text[$i];
					file_exists($filename) ? include($filename) : print 'error_file_exists'; //MUST FINISH CODE
				} else {echo 'error_file_name';} //MUST FINISH CODE
			}
		}
	} else {echo $fulltext;}
}

// Function for timestamping and interpreting a pattern to adjust timestamp output
function timeStamp($data='',$format='') {
$format= ($format=='') ? _value('date-format'):$format;
if($data=='') $o=date("YmdHis"); //Creating timestamp YYYYMMDDHHMMSS
else { //Reformatting YYYYMMDDHHMMSS it into something readable
$yr=substr($data,0,4); $mo=substr($data,4,2);
$dy=substr($data,6,2); $hr=substr($data,8,2);
$mi=substr($data,10,2); $sc=substr($data,12,2);
$o=date($format, mktime($hr, $mi, $sc, $mo, $dy, $yr));
}

return $o; }

//Creating Search Engine Friendly names
function makeSEF($text) {
$text = trim($text);
if ( ctype_digit($text) ) return $text;
else {     
    $table = array(
        'Š'=>'S', 'š'=>'s', 'Ð'=>'Dj', 'Ž'=>'Z', 'ž'=>'z', 'C'=>'C', 'c'=>'c', 'C'=>'C', 'c'=>'c',
        'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
        'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
        'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss',
        'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
        'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
        'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b',
        'ÿ'=>'y', 'R'=>'R', 'r'=>'r',
    );
   
    $text=strtr($text, $table);
    // clean out the rest
    $replace = array('/[^A-Za-z0-9 \-]+/','/(\s|\-)+/','/^-+|-+$/');
    $with = array('','-','');
    $text = preg_replace($replace,$with,$text);
  }

  return strtolower($text);
}

//Function used to truncate display text
function truncateText($string, $limit="", $break=" ") {
$limit=($limit=="") ? _value('list-char-limit'):$limit;
// return with no change if string is shorter than $limit
if($limit>0){ if(strlen($string)<=$limit) return $string; $string=substr($string,0,$limit);
if(false !== ($breakpoint=strrpos($string, $break))) $string=substr($string, 0, $breakpoint); }
if(false !== ($breakpoint=strrpos($string, "[BREAK]"))) $string=substr($string, 0, $breakpoint);
return $string; }

//PARSE OUTPUT TEXT
function parseText( $input ) {
//$input['type']; - arcticle, page, list, extra, custom
//$input['content-id']; if an article, page, list, or extra
//$input['custom-format'];
//$input['custom-text'];

//What format does the site need to be in
if ($input['type']=='list') $format=_value('list-format');
elseif ($input['type']=='article') $format=_value('article-format');
elseif ($input['type']=='page') $format='';
elseif ($input['type']=='custom') $format=$input['custom-format'];

$tags=array('[ID]','[SEF_TITLE]','[TITLE]','[DATE]','[MODIFIED]','[AUTHOR]','[EMAIL]','[EDIT]','[COMMENT]','[BODY]');
$nm=0;

if ($input['type']=='article'||$input['type']=='list') {
$oP=convertForDb(array('text' => $format, 'type' => 'screen'));

$s=mysql_query("SELECT * FROM ".db('prefix')."content WHERE id='".$input['content-id']."'"); $r=mysql_fetch_array($s);
$q=mysql_query("SELECT * FROM ".db('prefix')."users WHERE id='$r[auth]'"); $u=mysql_fetch_array($q);

$edit=($_SESSION['userId']==$r['auth']&&isAble('MANAGE_ARTICLES')||$_SESSION['userId']!=$r['auth']&&isAble('MANAGE_OTHER_ARTICLES')) ? "<p id=\"editContent\"><a href=\"?admin.articles.edit.$r[id]\">Edit</a></p>":'';

$d=mysql_query("SELECT * FROM ".db('prefix')."comments WHERE pid='$r[id]' AND approved='1' ORDER BY id DESC");

while($c=mysql_fetch_array($d)) $nm++;

$com=($nm>0) ? "View Comments ($nm)":"Add Comment"; $comment= (_value('allow-comment')==1&&$r['comments']==1) ? " <a href=\"?article.$r[sef_title]#comments\">$com</a>":'';
$body=convertForDb(array('text' => $r['body'], 'type' => 'screen')); $date=timeStamp($r['date']); $moddate=timeStamp($r['moddate']);

// Truncating text when in list mode
if($input['type']=='list' && FALSE !== ($breakpoint=strrpos($body, '[BREAK]'))) $body=substr($body, 0, $breakpoint);
else $body=str_replace('[BREAK]','',$body);

$title=convertForDb(array('text' => $r['title'], 'type' => 'screen'));

$code=array($r['id'],$r['sef_title'],$title,$date,$moddate,$u['name'],$u['mail'],$edit,$comment,$body);
$oP=str_replace($tags,$code,$oP);

$oP= (_value('allow-comment')==1&&$r['comments']==1) ? preg_replace('/\[C\=([ 0-9a-zA-Z]+)\]/',' <a href="#comments" title="Add/View Comments">\\1</a>',$oP):preg_replace('/\[C\=([ 0-9a-zA-Z]+)\]/','',$oP);

//Modify the output for a page
} elseif ($input['type']=='page') {

$s=mysql_query("SELECT * FROM ".db('prefix')."content WHERE id='".$input['content-id']."'"); $r=mysql_fetch_array($s);
$q=mysql_query("SELECT * FROM ".db('prefix')."users WHERE id='$r[auth]'"); $u=mysql_fetch_array($q);
$body=convertForDb(array('text' => $r['body'], 'type' => 'screen'));

//$edit=($_SESSION['userId']==$r['auth']&&isAble('CREATE_PAGE')||$_SESSION['userId']!=$r['auth']&&isAble('EDIT_OTHER_PAGE')) ? " <a href=\"?admin.pages.edit.$r[id]\">Edit P</a>":'';
$d=mysql_query("SELECT * FROM ".db('prefix')."comments WHERE pid='$r[id]' AND approved='1' ORDER BY id DESC");
while ($c=mysql_fetch_array($d)) $nm++;
$com=($nm>0) ? "View Comments ($nm)":"Add Comment"; $comment= (_value('allow-comment')==1&&$r['comments']==1) ? " <a href=\"?article.$r[sef_title]#comments\">$com</a>":'';
$date=timeStamp($r['date']); $moddate=timeStamp($r['moddate']);

// Truncating text
$body=str_replace('[BREAK]','',$body);
$title=convertForDb(array('text' => $r['title'], 'type' => 'screen'));

$code=array($r['id'],$r['sef_title'],$title,$date,$moddate,$u['name'],$u['mail'],'',$comment,'');
$oP=str_replace($tags,$code,$body);

$oP= (_value('allow-comment')==1&&$r['comments']==1) ? preg_replace('/\[C\=([ 0-9a-zA-Z]+)\]/',' <a href="#comments" title="Add/View Comments">\\1</a>',$oP):preg_replace('/\[C\=([ 0-9a-zA-Z]+)\]/','',$oP);
if (isAble('MANAGE_PAGES')) $oP.="\n<p id=\"editContent\"><a href=\"?admin.pages.edit.$r[id]\">Edit</a></p>";
}

return $oP;
}

//Menu Handler (To allow people to create and position their own menus)
function menu($menu){ 
$numargs=func_num_args(); $argument=func_get_args();
if ($numargs < 1) return FALSE;

$NOTITLE=FALSE;
$NOACTIVE=FALSE;
$NOLAST=FALSE;
$NOEDIT=FALSE;

$menuName=$argument[0];
$format=$argument[1];
for ($i = 2; $i < $numargs; $i++) {
if ($argument[$i]=='notitle') $NOTITLE=TRUE;
if ($argument[$i]=='noactive') $NOACTIVE=TRUE;
if ($argument[$i]=='nolast') $NOLAST=TRUE;
if ($argument[$i]=='noedit') $NOEDIT=TRUE;
}

if( $format == '' || $format == 'default')	$format = "<li>%s</li>";
if( $format == 'nolist' ) $format = '%s';

$s=mysql_query("SELECT * FROM ".db("prefix")."menus WHERE name='$menuName'"); $r=mysql_fetch_array($s);
$m=explode(";;",$r['data']); $n=0;

$links_html= ($format == "<li>%s</li>") ? "\n<ul class=\"$menuName\">": "\n";
$links_html.= (!$NOTITLE && $format == "<li>%s</li>") ? "\n<li id=\"menutitle\">".$r['title'].'</li>': '';

while(count($m)>$n) {
$name=$m[$n]; $n++;
$url= (substr($m[$n],0,1)=="[" && substr($m[$n],strlen($m[$n])-1,1)=="]") ? '?'.substr($m[$n],1,strlen($m[$n])-2): $m[$n];

$link='<a href="'.$url.'"';
$link.= (!$NOACTIVE && db('query')==$m[$n]) ? ' id="active"': ''; //is the page currently on the URL of the link
$link.= (!$NOLAST && count($m)==$n+1) ? ' id="last"': ''; //last item in link
$link.='>'.$name.'</a>'; $n++;
$links_html.= sprintf( $format, $link);
}

if(!$NOEDIT && isAble('MANAGE_SYSTEM')) $links_html.= sprintf( $format,"\n".'<a href="?admin.menus.edit.'.$r['id'].'" id="edit">Edit Menu</a></li>');
$links_html.= ($format == "<li>%s</li>") ? "\n</ul>": '';
echo $links_html;
}

//VIEWING CONTENT
function viewContent() {
global $qAction; $id=$qAction[1];
$trigger= (is_numeric($id)) ? "id='$id'":"sef_title='$id'";
$q=mysql_query("SELECT * FROM ".db("prefix")."content WHERE $trigger"); $r=mysql_fetch_array($q);
if ($r['id']&&($r['published']==1||$r['published']!=1&&isAble('MANAGE_SYSTEM'))) {

$content=($r['type']=='page') ? parseText(array('type' => 'page', 'content-id' => $r['id'])):parseText(array('type' => 'article', 'content-id' => $r['id']));
processIncludes($content);


//COMMENTS
if (_value('allow-comment')==1&&$r['comments']==1) {

if ($_SESSION['ctime']>=time()&&$_SESSION['cid']==$r['id']) { $cdisable=TRUE;} //flood protection
elseif(isset($_POST['comment'])&&$_POST['confirmemail']=='') {

if(!$_POST['msg']) { $cstatus='<p>Error: There is no content.</p>'; }
else {
     $pid=$r['id']; $name=$_POST['name']; $url=$_POST['url']; $date=timeStamp(); $msg=convertForDb(array('type' => 'screen', 'text' => $_POST['msg']));
     $_SESSION['ctime']=time()+180; $_SESSION['cid']=$r['id']; $mcom= (_value('mod-comment')==1) ? 2:1;
     $ip = $_SERVER['REMOTE_ADDR'];

     $q="INSERT INTO ".db('prefix')."comments(pid,name,date,ip,url,comment,approved) VALUES('$pid','$name','$date','$ip','$url','$msg','$mcom')";
     mysql_query($q) or $cstatus=mysql_error(); $cstatus='<p>Comment Posted.</p>';
   }

}

$funcOutput.='<a name="comments"></a>';
$d=mysql_query("SELECT * FROM ".db('prefix')."comments WHERE pid='$r[id]' AND approved='1'");
$c=mysql_fetch_array($d);

if ($c['id']) {
$funcOutput.='<div id="comments"><h2>Comments</h2>'; 
$d=mysql_query("SELECT * FROM ".db('prefix')."comments WHERE pid='$r[id]' AND approved='1' ORDER BY id DESC");
while ($c=mysql_fetch_array($d)) {
$link= (stristr($c['url'], '@')!=FALSE) ? "mailto:$c[url]":$c['url'];
$funcOutput.="<p>".convertForDb(array('type' => 'screen', 'text' => $c['comment']))."<br />by <a href=\"$link\">$c[name]</a> at ".timeStamp($c['date'])."</p>";
}

$funcOutput.='</div>'; }

if ($cdisable!=TRUE) {
$funcOutput.='<div id="addcomments"><h2>Add Comment</h2> 
<form action="?'.db('query').'" method="post">
<p>Name: '.formItem(array('type' => 'text', 'name' => 'name')).'</p>
<p>URL/Email: '.formItem(array('type' => 'text', 'name' => 'url')).'</p>
      <p style="display: none;">&#83;&#112;&#97;&#109;&nbsp;&#67;&#111;&#110;&#116;&#114;&#111;&#108;&#58;&nbsp; '.formItem(array('type' => 'text', 'name' => 'confirmemail')).'</p>
<p>Comment: '.formItem(array('type' => 'textarea', 'name' => 'msg')).'</p>
<p>'.formItem(array('type' => 'submit', 'name' => 'comment', 'value'=>'Submit Comment'))."</p>$cstatus</form></div>";
}

 }//finished comments

echo $funcOutput;

} else { echo '<p>Sorry, the page you are looking for is disabled or does not exist.
<br />You will be redirected to <a href="'.db('website').'">'.db('website').'</a>.</p><script language="javascript">//<!--
setTimeout("location.href = \''.db('website').'\';", 4000); //-->
</script>'; }

} //END OF FUNCTION

//EXTRA CONTENT FUNCTION
function extra() {
global $qAction; $contentId=''; $categoryId='';
$numargs=func_num_args(); $argument=func_get_args();
if ($numargs < 1) return FALSE;
$space="AND ( space='".implode("' OR space='",$argument)."')";

if ($qAction[0]=="article"||$qAction[0]=="page") {
$col=(is_numeric($qAction[1])) ? 'id':'sef_title';
$q="SELECT * FROM ".db('prefix')."content WHERE $col='".$qAction[1]."'";
$r=mysql_fetch_array(mysql_query($q)); 
$contentId="OR for_content LIKE '%(".$r['id'].")%'";
$categoryId="OR for_cat='0' OR for_cat='".$r['cat']."'";
} 

$q=mysql_query("SELECT * FROM ".db('prefix')."extra WHERE ( ( for_process LIKE '%(".$qAction[0].")%' $categoryId $contentId ) AND published='1') $space ORDER BY position ASC");
while ($e=mysql_fetch_array($q)) {

$body=convertForDb(array('type' => 'screen', 'text' => $e['body']));
processIncludes($body);
//if(isAble('MANAGE_SYSTEM')) echo "<p id=\"admin\"><a href=\"?admin.pages.edit.$e[id]\">Edit</a></p>";
}

}

//Admin Link, will change depending on the user's status
function login() { $s=($_SESSION['validUser']==TRUE) ? 'Admin':'Login'; echo '<a href="?admin">'.$s.'</a>'; }

//Search Bar
function search() { echo '<form class="searchbar" action="?search" method="post"><p><input type="text" id="searchbar" name="searchterm" value="Search Site" onfocus="if (this.value == \'Search Site\') this.value=\'\';" onblur="if (this.value == \'\') this.value=\'Search Site\';" />&nbsp;<input id="searchbutton" name="submit" type="submit" value="Search" /></p></form>'; }

//Search Function, Updated 2/28/08
function contentSearch() {
global $qAction; $searchterm=($qAction[1]) ? makeSEF($qAction[1]):makeSEF($_POST['searchterm']); $search=str_replace('-',' ',$searchterm);
echo '<div class="search"><form action="?search" method="post"><p>'.formItem(array('type'=>'text','name'=>'searchterm','value'=>$search)).formItem(array('type'=>'submit','name'=>'submit','value'=>'Search')).'</p></form>';

if($searchterm=='') {echo '</div>'; return FALSE; }
$max=_value('search-num-content'); $num= ($qAction[2]<1) ? 1:$qAction[2]; $pg=($num-1)*$max;
if (eregi(" AND | NOT | OR ",$search,$matches)) $search=str_replace($matches,'',$search);

		$keywords = explode(' ', $search);
		$keyCount = count($keywords);
		$query = "SELECT *, 0.6 * MATCH(title) AGAINST('$search' IN BOOLEAN MODE) + 0.2 * MATCH(description) AGAINST('$search' IN BOOLEAN MODE) + 0.8 * MATCH(keywords) AGAINST('$search' IN BOOLEAN MODE) + 0.9 * MATCH(body) AGAINST('$search' IN BOOLEAN MODE) AS rank  
FROM ".db('prefix')."content 
WHERE MATCH(body,title,keywords,description) AGAINST('$search' IN BOOLEAN MODE) AND published='1'
ORDER BY rank DESC";
    $pquery=$query.';'; $query.=" LIMIT $pg, $max;"; //echo $query;
		$count = mysql_query($pquery);
    $result = mysql_query($query);
		$numrows = mysql_num_rows($count);
		if (!$numrows) { echo '<p>No Results Found.</p></div>'; }
		else {
			foreach ($keywords as $searchitem) $searchlist.='<a href="?search.'.$searchitem.'">'.$searchitem.'</a> ';
      echo  '<p><strong>'.$numrows.'</strong> result(s) found for &#34;<strong>'.$searchlist.'</strong>&#34;</p></div>';
			while ($r = mysql_fetch_array($result)) { 
      $tags = explode(',', $r['keywords']); $taglist='';
      foreach ($tags as $tag) $taglist.='<a href="?search.'.makeSEF($tag).'">'.$tag.'</a> ';
      
      $c = mysql_fetch_array(mysql_query("SELECT * FROM ".db('prefix')."categories WHERE id='".$r['cat']."'"));
      
      echo "\n\n<hr class=\"searchruler\" /><p class=\"searchresult\">".ucwords($r['type']).': <strong><a href="?'.$r['type'].'.'.$r['sef_title'].'">'.$r['title'].'</a></strong> ( '.timeStamp($r['date']).' | <a href="?list.'.$c['sef_title'].'">'.$c['title'].'</a> )'; 
      echo '<span id="rank"><strong>Rank:</strong> '.$r['rank'].'</span><br />Tags: '.$taglist.'</p>';
      //if ($r['type']=='article') echo parseText(array('type' => $r['type'], 'content-id' => $r['id']));
        	}
		}

//Pagination
pagination($pquery,$num,'',urlencode($searchterm));
}

//PERMISSION HANDLER
function permHandler($p) {
$permission['MANAGE_SYSTEM']= (strpos($p,'a')=== false) ? FALSE: TRUE;
$permission['MANAGE_USERS']= (strpos($p,'b')=== false) ? FALSE: TRUE;
$permission['MANAGE_EXTENSIONS']= (strpos($p,'c')=== false) ? FALSE: TRUE;
$permission['MANAGE_CATEGORIES']= (strpos($p,'d')=== false) ? FALSE: TRUE;
$permission['MANAGE_PAGES']= (strpos($p,'e')=== false) ? FALSE: TRUE;
$permission['MANAGE_ARTICLES']= (strpos($p,'f')=== false) ? FALSE: TRUE;
$permission['MANAGE_OTHER_ARTICLES']= (strpos($p,'g')=== false) ? FALSE: TRUE;
$permission['MANAGE_COMMENTS']= (strpos($p,'h')=== false) ? FALSE: TRUE;

return $permission;
}

function isAble($option) { return $_SESSION['userPerms'][$option]; }

//PAGINATION (JS Form and List Buttons)
function pagination($query,$page,$category='',$search='') {
$count=$s=mysql_query($query); $max=_value('num-articles'); $f=$g=0;
if ($category!='') $category='.'.$category; if ($page==0) $page=1;
$listnum=_value('pagination-button-number');
$prefix=($search=='') ? "list$category":"search.$search";
//echo mysql_num_rows($count);
if(mysql_num_rows($count)<=$max) return FALSE;

if(_value('js-enabled-pagination')==TRUE) { //JS Form 
echo '<div id="pagination"><form action="#" id="page-jump"><p>';
if ($page>1) echo "<a href=\"?$prefix.".($page-1)."\" title=\"Go to the next page\">&lt;&lt;</a>&nbsp;";
echo'Page:&nbsp;<select name="page" onchange="location=document.getElementById(\'page-jump\').page.options[document.getElementById(\'page-jump\').page.selectedIndex].value;">';
while($r=mysql_fetch_array($s)) { $f++; if($f%$max==0) {  $g++; $d=($page==$g) ? ' selected="selected"':''; echo "\n<option value=\"?$prefix.$g\"$d>$g</option>"; } }
echo "</select>";
if ($page<$g) echo "&nbsp;<a href=\"?$prefix.".($page+1)."\" title=\"Go to the next page\">&gt;&gt;</a>";
echo "</p></form>\n</div>";

} else { // Just Regular Links
$header="<div id=\"pagination\"><ul>\n"; $footer="</ul></div>\n";
if ($page>1) $header.="<li><a href=\"?$prefix.1\" title=\"Go to the first page\">First</a></li >";
if ($page>2) $header.="<li><a href=\"?$prefix.".($page-1)."\" title=\"Go to the previous page\">Prev</a></li> ";
while($r=mysql_fetch_array($s)) { $f++; if($f%$max==0) {  $g++; $listItem[]="\n<li><a href=\"?$prefix.$g\">$g</a></li> "; } } //count total number of pages
if ($page<$g) $footer="<li><a href=\"?$prefix.".($g)."\" title=\"Go to the last page\">Last</a></li> $footer";
if ($page<($g-1)) $footer="<li><a href=\"?$prefix.".($page+1)."\" title=\"Go to the next page\">Next</a></li> $footer";

$hi=$lo=floor($listnum/2); //set high and low range
if (($page-$lo)<0) { $hi+=$lo-($page-0); $lo=$page; } //if current # - lo less than one 
if (($page+$hi)>$g) { $lo+=$hi-($g-$page); $hi=$page; } //if current # + hi is greater than actual # of pages
//echo 'Total='.$g.'; Lo='.$lo.'; Hi='.$hi.'; Page='.$page; #Total=8; Lo=6; Hi=0; Page=5
for ($i=$page-$lo; $i<$page+$hi; $i++) { $list.=$listItem[$i].' '; }

echo $header.$list.$footer;
} } //End of Function

//Session handling class to improve security and consistency
class leapInit {
var $sessionNames='userName,userMail,userId,passWord,validUser,userPerms,lastLogin';
var $SpecFuncTriggers=array('rss');
var $SpecFuncNames=array('rss');


var $TitleFuncTriggers=array('rss');
var $TitleFuncNames=array('rss');

// TODO: Complete Archive and Sitemap functions
var $FuncTriggers=array('article','search','page','admin','list');
var $FuncNames=array('viewContent','contentSearch','viewContent','leapAdmin','createList');


//Check Session at the beginning of the script, then clean/set values
function checkSession() {
if($_POST['login']=='Login') { $_SESSION['userMail']=$_POST['email']; $_SESSION['passWord']=md5($_POST['pwd']); 
//Cookie used for the Recent Activity feature
//setcookie("LeapLastLogin", $_COOKIE['LeapCurrentLogin'], time()+31556926);
$_SESSION['lastLogin']=$_COOKIE['LeapLastLogin'];
setcookie("LeapLastLogin", date("YmdHis"), time()+31556926);
}
$i=@mysql_query("SELECT * FROM ".db('prefix')."users WHERE mail='$_SESSION[userMail]' AND pwd='$_SESSION[passWord]'"); $d=@mysql_fetch_array($i);

$_SESSION['validUser']=(!$d['id']) ? FALSE:TRUE;
$_SESSION['userName']=$d['name']; $_SESSION['userId']=$d['id']; $_SESSION['userPerms']=permHandler($d['permissions']);

if ($_SESSION['validUser']==FALSE||$_POST['login']=='Logout') $this->cleanSession();
}

//Changing pre-existing session values, not meant to make any new ones.
function editSession($name,$value) { if (isset($_SESSION[$name])) $_SESSION[$name]=$value; else return FALSE; }

//Whenever someone logs out, the session is clean, not destroyed (so it won't disrupt other Session-reliant apps)
function cleanSession() { $name=explode(',',$this->sessionNames);
foreach ($name as $value) { unset($_SESSION[$value]); }
}

function updateContent() {
$query="UPDATE ".db('prefix')."content SET published='1' WHERE published='2' AND date<='".timeStamp()."'";
mysql_query($query);
$query="UPDATE ".db('prefix')."content SET published='0', unpublish='0' WHERE unpublish='1' AND enddate<='".timeStamp()."'";
mysql_query($query);
}

} //End of class

//for all "?list" actions
function createList() { 
global $qAction;

if ($qAction[1]=="categories") {
echo "<ul id=\"categories\">";
$d=mysql_query("SELECT * FROM ".db('prefix')."categories WHERE published='1' ORDER BY id ASC");
while($c=mysql_fetch_array($d)) {

$s=mysql_query("SELECT * FROM ".db('prefix')."content WHERE published='1' AND type='article' AND cat='$c[id]' ORDER BY date DESC"); $nm=0;
while ($r=mysql_fetch_array($s)) { $nm++; }
$a= ($nm==1) ? "article":"articles";
echo "<li><h1><a href=\"?list.$c[sef_title]\">$c[title]</a>&nbsp;($nm $a)</h1>";
echo "<h2>$c[description]</h2></li>";
}

$s=mysql_query("SELECT * FROM ".db('prefix')."content WHERE published='1' AND type='article' ORDER BY date DESC"); $nm=0;
while ($r=mysql_fetch_array($s)) { $nm++; }
$a= ($nm==1) ? "article":"articles";
echo "<li><h1><a href=\"?list\">View All</a>&nbsp;($nm $a)</h1></li></ul>";

} else {
$query="SELECT * FROM ".db('prefix')."content WHERE published='1' AND type='article'";
$order=" ORDER BY date DESC";

if ($qAction[1]) {
// ?list.## - Global list at page ##
if (is_numeric($qAction[1])) $limit=" LIMIT ".($qAction[1]-1)*_value('num-articles').", "._value('num-articles');
// ?list.catname.## - List filtered with "catname" at page##
else {
$r=mysql_fetch_array(mysql_query("SELECT * FROM ".db('prefix')."categories WHERE sef_title='".$qAction[1]."'"));
$categoryID=$r['id']; $where=" AND cat='$categoryID'";

if ($qAction[2]) $limit=" LIMIT ".($qAction[2]-1)*_value('num-articles').", "._value('num-articles');
else $limit=" LIMIT 0, "._value('num-articles'); }

}
else $limit=" LIMIT 0, "._value('num-articles');

$s=mysql_query($query.$where.$order.$limit);
while($r=mysql_fetch_array($s)) { echo parseText(array('type' => 'list', 'content-id' => $r['id'])); }

//Pagination
if(isset($qAction[2])) $page=$qAction[2];
elseif(is_numeric($qAction[1])) $page=$qAction[1];
else $page=1;

$query="SELECT * FROM ".db('prefix')."content WHERE type='article'";
if (!is_numeric($qAction[1])&&$qAction[1]!='') $query.=$where;
$category=($categoryID!='') ? $qAction[1]:'';
pagination($query,$page,$category);
}

     
//End of function
}

function archive() { return FALSE; }

//SITEMAP FUNCTION
function sitemap() { 
echo "<p>Pages</p>\n<p><ul><li><a href=\"?\">Home</a></li> ";
$s=mysql_query("SELECT * FROM ".db('prefix')."pages WHERE published='1'");
while ($r=mysql_fetch_array($s)) { echo "\n\n<li><a href=\"?$r[name]\">$r[name]</a></li> "; }
echo '<li><a href="?archive">Archive</a></li> <li><a href="?sitemap">Sitemap</a></li> <li><a href="?rss">RSS Feed</a></li> </ul></p>';

echo "\n\n<p><a href=\"?list\">Articles</a></p>";
$s=mysql_query("SELECT * FROM ".db('prefix')."categories WHERE published='1'");
while ($r=mysql_fetch_array($s)) { 
echo "\n\n<p><a href=\"?list.$r[name]\">$r[title]</a><br /><ul> "; 

$d=mysql_query("SELECT * FROM ".db('prefix')."content WHERE cat='$r[id]' LIMIT 0, 10");
while ($c=mysql_fetch_array($d)) { echo "\n\n<li><a href=\"?article.$c[id]\">$c[title]</a></li> "; }
echo "</ul></p>";
}

}

class adminPanel {
  var $qAction;
  var $panelTitle;
  
//this function creates the HTML for the Publish link
function pubButton($n,$id) { $pub[0]= ($n==1) ? 'unpublish':'publish'; if($n==1) $pub[1]='Unpub'; elseif($n==2) $pub[1]='Pending'; else $pub[1]='Pub'; return $isPub='<li><a href="?admin.'.$this->panelTitle.'.'.$pub[0].'.'.$id.'">'.$pub[1].'</a></li>'; }

// NEW ADMIN PANEL OPERATOR 1/14/2008 to replace the original
function panelContent() {
$this->qAction=parseQuery();
$this->panelTitle=$this->qAction[1];
$this->panelTable= (ereg($this->qAction[1],'(articles|pages)')) ? 'content':$this->panelTitle;

$title= (!$this->qAction[1]) ? 'Options':'Manage '.ucfirst($this->qAction[1]);
echo '<h1 class="adminheader" title="Manage your website" >'.$title.'</h1>
<div class="admindiv" id="panel2">';

if ($this->qAction[1]) {

if (ereg($this->qAction[2],'(add|edit)')) $this->addEditItem();
elseif ($this->qAction[2]=='delete') $this->deleteItem();
elseif ($this->qAction[2]=='purge') $this->purgeItem();
elseif ($this->qAction[2]=='settings') $this->editSystem();
elseif ($this->qAction[2]=='files') $this->fileManager();
elseif ($this->qAction[2]=='editfile') $this->editFile();
elseif ($this->qAction[2]=='deletefile') $this->deleteFile();
elseif ($this->qAction[2]=='rename') $this->renameFile();
elseif ($this->qAction[2]=='upload') $this->uploadFile();
elseif (ereg($this->qAction[2],'(unpublish|publish|reject|approve)')) $this->publishItem();
else $this->createList();
}

else { if(isAble('MANAGE_USERS')) echo '<p>Users: <a href="?admin.users.add">Add New</a> &middot; <a href="?admin.users">Manage</a> &middot; <a href="?admin.users.edit.'.$_SESSION['userId'].'">Edit My Account</a></p>';

if(isAble('MANAGE_SYSTEM')) echo '<p>Menus: <a href="?admin.menus.add">Add New</a> &middot; <a href="?admin.menus">Manage</a></p>

<p>Extra Content: <a href="?admin.extra.add">Add New</a> &middot; <a href="?admin.extra">Manage</a></p>';
if(isAble('MANAGE_EXTENSIONS')) echo '<p>Extensions: <a href="?admin.extensions">Manage</a></p>';

if(isAble('MANAGE_CATEGORIES')) echo '<p>Categories: <a href="?admin.categories.add">Add New</a> &middot; <a href="?admin.categories">Manage</a></p>';

if(isAble('MANAGE_ARTICLES')||isAble('MANAGE_OTHER_ARTICLES')) echo '<p>Articles: <a href="?admin.articles.add">Add New</a> &middot; <a href="?admin.articles">Manage</a> &middot; <a href="?admin.articles.purge">Purge</a></p>';

if(isAble('MANAGE_PAGES')) echo '<p>Pages: <a href="?admin.pages.add">Add New</a> &middot; <a href="?admin.pages">Manage</a></p>';

if(isAble('MANAGE_COMMENTS')) echo '<p>Comments: <a href="?admin.comments">Manage</a> &middot; <a href="?admin.comments.purge">Purge</a></p>';
}

echo '</div>';

}


function editSystem() { 
//did someone submit changes?
if (isset($_POST['editSystem'])) {
extract($_POST); $n=0;
while($total > $n) { mysql_query("UPDATE ".db('prefix')."system SET data='".convertForDb(array('type' => 'db', 'text' => $setting_data[$n]))."' WHERE type='config' AND id='$setting_id[$n]'"); $n++; }
echo "<p id=\"adminNotice\">Successfully Edited System<br /> <a href=\"?admin\">Return to Admin Home</a></p>";
return 0;
}

$s=mysql_query("SELECT * FROM ".db('prefix')."system WHERE type='config' AND id>1");

echo "<form method=\"post\" action=\"?".db('query')."\">"; //<input type=\"hidden\" name=\"name\" value=\"$r[name]\" /><input type=\"hidden\" name=\"$n\" value=\"$opt[$n]\" />"; $n++;

$n=0;
while($r=mysql_fetch_array($s)) { extract($r);

$namedata=explode(";;",$name);
$funcOutput.="<p><input type=\"hidden\" name=\"setting_id[$n]\" value=\"$id\" /><b>$namedata[1]</b><br />$namedata[2]<br />";

//$type=(ereg($name,'(keywords|description|list-format|article-format)')) ? 'textarea':'text';
$funcOutput.=formItem(array('type' => $namedata[3], 'name' => "setting_data[$n]", 'id' => "item_$n", 'value' => convertForDb(array('type' => 'screen', 'text' => $data)))).'</p>';
$n++;
}

echo $funcOutput.'<p>'.formItem(array('type' => 'hidden', 'name' => 'total', 'value' => $n)).formItem(array('type' => 'submit', 'name' => 'editSystem', 'value' => 'Save Settings')).formItem(array('type' => 'reset', 'value' => 'Reset')).'</p></form>';
}

function fileManager() { 
if (isset($_POST['viewFolder'])) {

}

$file_dir=(isset($_POST['viewFolder'])) ? $_POST['path']:".";
$upload_view=".";

//Handle for the directory
if (!$handle=@opendir($upload_view)) echo "<span>Error!! Cannot open directory: ".$file_dir."</span>";
$ignore=array('..','.','.htaccess','cgi-bin','Thumbs.db','.htpasswd ');

while($item=@readdir($handle)) { if(!in_array($item,$ignore) && is_dir($item)) $dir[]=$item; if(!in_array($item,$ignore) && !is_dir($item)) $file[]=$item; }
@closedir($handle);

if($upload_view!=$file_dir) { $handle=@opendir($file_dir); unset($file);
while($item=@readdir($handle)) if(!in_array($item,$ignore) && !is_dir($item)) $file[]=$item;
@closedir($handle);
}

//Viewing Folders
echo '<p><form action="?admin.system.files" method="post">View Folder:<br /><select name="path">';
echo '<option value=".">/</option>';
foreach($dir as $d) echo '<option value="'.$d.'"'.(($d==$file_dir) ? ' selected="selected"':'').'>/'.$d.'/</option>';
echo '</select> '.formItem(array('type' => 'submit','name' =>'viewFolder','id'=>'view','value'=>'View')).' <a href="?admin">Return to Admin Home</a></p></form><p>';

foreach($file as $f) {
//get the filesize
$filesize=filesize($file_dir."/".$f);
$type=array('b','Kb','Mb');
for ($i=0; $filesize > 1024; $i++) $filesize /= 1024;
$filesize=round($filesize, 2).$type[$i];

//Check if the file is editable
$editable=array('.php','.css','.htm','html','.txt');
$ext=substr($f, -4);
$filepath=($file_dir!='.') ? str_replace(' ','%20', $file_dir.'%2F'.str_replace('.','%2E', $f)):str_replace(' ','%20',str_replace('.','%2E', $f));
$edit=(in_array($ext, $editable) && is_writable($f)) ? ' &middot; <a href="?admin.system.editfile.'.$filepath.'">Edit</a>':'';
    
echo '<a href="'.$file_dir.'/'.$f.'">'.$f.'</a> ['.$filesize.']
<span>'.$edit.' &middot; <a href="?admin.system.deletefile.'.$filepath.'">Del</a> &middot; <a href="?admin.system.rename.'.$filepath.'">Rename</a></span><br />';
}

//Uploading Files
echo '</p><form action="?admin.system.upload" method="post" enctype="multipart/form-data"><p>Upload File:<br /><select name="path">';
echo '<option value=".">/</option>';
foreach($dir as $d) echo '<option value="'.$d.'"'.(($d==$file_dir) ? ' selected="selected"':'').'>/'.$d.'/</option>';
echo '</select> '.formItem(array('type' => 'file','name' => 'upfile')).' '.formItem(array('type' =>'submit','name'=>'uploadFile','value'=>'Upload File')).'<br />';
echo 'Save File As: (optional) '.formItem(array('type' => 'text','name' => 'saveas')).'</p></form>';

}

//DELETE FILE
function deleteFile() {
if (isset($_POST['deleteFile'])) {

$folder=(stristr($_POST['filepath'],'/')) ? explode('/',$_POST['filepath']): array('.',$_POST['filepath']);
unlink($_POST['filepath']);

echo "<p id=\"adminNotice\">Successfully Deleted <b>$folder[1]</b></p>";
$_POST['viewFolder']=TRUE;
$_POST['path']=$folder[0];
$this->fileManager();
return 0;
}

$filename=urldecode($this->qAction[3]);
$folder=(stristr($filename,'/')) ? explode('/',$filename): array('.',$filename);

echo '<form action="?admin.system.deletefile" method="post"><p>Are you sure you want to delete <b>/'.$filename.'</b>? This process cannot be reversed and the file cannot be recovered once deleted. <br />';
echo formItem(array('type' =>'hidden','name' =>'filepath','id'=>'filepath','value' =>$filename)).formItem(array('type' =>'submit','name'=>'deleteFile','id'=>'deleteFile','value'=>'Delete File')).'</p></form>';
}

//RENAME OR MOVE A FILE
function renameFile() {
if (isset($_POST['renameFile'])) {

$newpath=($_POST['newdir']=='.') ? $_POST['newname']:$_POST['newdir'].'/'.$_POST['newname'];
rename($_POST['oldpath'],$newpath);

echo "<p id=\"adminNotice\">Successfully Renamed <b>$_POST[newname]</b></p>";
$_POST['viewFolder']=TRUE;
$_POST['path']=$_POST['newdir'];
$this->fileManager();
return FALSE;
}

if (!$handle=@opendir('.')) echo "<span>Error!! Cannot open directory: </span>";
$ignore=array('..','.','.htaccess','cgi-bin','Thumbs.db','.htpasswd ');
while($item=@readdir($handle)) { if(!in_array($item,$ignore) && is_dir($item)) $dir[]=$item; }
@closedir($handle);

$filename=urldecode($this->qAction[3]);
$folder=(stristr($filename,'/')) ? explode('/',$filename): array('.',$filename);

echo '<form action="?admin.system.rename" method="post"><p>Move/Rename <b>/'.$filename.'</b>: <br /><select name="newdir">';
echo '<option value=".">/</option>';
foreach($dir as $d) echo '<option value="'.$d.'"'.(($d==$folder[0]) ? ' selected="selected"':'').'>/'.$d.'/</option>';
echo '</select> '.formItem(array('type'=>'hidden','name'=>'oldpath','value'=>$filename)).formItem(array('type'=>'text','name'=>'newname','value'=>$folder[1])).formItem(array('type'=>'submit','name'=>'renameFile','value'=>'Rename')).'</p></form>';

}

function uploadFile() { 
if (isset($_POST['uploadFile']) && is_uploaded_file($_FILES["upfile"]["tmp_name"])) {
$filepath=$_POST['path'].'/';
$filename=(strlen($_POST['saveas'])>=6) ? $_POST['saveas']:$_FILES['upfile']['name'];

if ($_FILES["upfile"]["error"]>0) { 
echo "<span id=\"adminNotice\">An Error Occured During Upload: <b>".$_FILES["upfile"]["error"]."</b></span>";
$this->fileManager();
}
elseif (file_exists($filepath.$filename)) {
echo "<span id=\"adminNotice\">An Error Occured During Upload: <b>$filename already exists</b></span>";
$this->fileManager();

} else {
move_uploaded_file($_FILES["upfile"]["tmp_name"],$filepath.$filename);
echo "<p id=\"adminNotice\">Successfully Uploaded <b>$filename</b>!</p>";
$_POST['viewFolder']=TRUE;
$this->fileManager();
}


} else $this->fileManager();

}

function editFile() { 
//did someone submit changes?
if (isset($_POST['editFile'])) {
$content=stripslashes($_POST['content']);
$handle=fopen($_POST['filename'], 'w'); fwrite($handle, $content); 
fclose($handle);

$folder=(stristr($_POST['filename'],'/')) ? explode('/',$_POST['filename']): array('.',$_POST['filename']);
echo "<p id=\"adminNotice\">Successfully Edited <b>$folder[1]</b></p>";
$_POST['viewFolder']=TRUE;
$_POST['path']=$folder[0];
$this->fileManager(); 
return FALSE;
}

$filename=urldecode($this->qAction[3]); //removeXSS($_POST['filename']);
if (file_exists($filename)&&is_writable($filename)) {
  $handle = @fopen($filename, "r"); 
  $contents = htmlentities(fread($handle, filesize($filename)),ENT_NOQUOTES); 
  fclose($handle); 
  echo "<form action=\"?admin.system.editfile\" method=\"post\">"; 
    echo '<p><b>/'.$filename.'</b>: <input type="hidden" name="filename" value="'.$filename.'" /><br />
<script type="text/javascript">edToolbar(\'contents\');</script>'.formItem(array('type'=>'textarea','id'=>'contents','name'=>'contents','value'=>$contents)).'<br /><br /></p>'; 
  echo '<p>'.formItem(array('type' => 'submit', 'name' => 'editFile', 'value' => 'Save Changes')).formItem(array('type' => 'reset', 'value' => 'Reset')).'</p></form>'; 

}
else { echo "<span id=\"adminNotice\">Unable To Open File \"$filename\": File is not writable or does not exist</span>"; $this->createList(); }

}

function createList() {
$dbPage='LIMIT '.(($_POST[$this->panelTitle]) ? (($_POST['page']-1)*10):0).','._value('panel-item-list-num');
echo '<table><form method="post" action="?admin.'.$this->panelTitle.'.delete">';
//$edit_users=($_SESSION['perm'][5]==TRUE) ? '':"WHERE auth=$_SESSION[id]"; 

if($this->panelTitle=="articles") $this->dbMethod="WHERE type='article' ORDER BY mod_date DESC";
if($this->panelTitle=="categories") $this->dbMethod="ORDER BY position DESC, id ASC";
if($this->panelTitle=="pages") $this->dbMethod="WHERE type='page' ORDER BY id ASC";
if($this->panelTitle=="extra") $this->dbMethod="ORDER BY id ASC";
if($this->panelTitle=="menus") $this->dbMethod="ORDER BY id ASC";
if($this->panelTitle=="users") $this->dbMethod="ORDER BY id ASC";
if($this->panelTitle=="comments") $this->dbMethod="ORDER BY id DESC";
if($this->panelTitle=="system") $this->dbMethod="WHERE id='1'";
$s=mysql_query("SELECT * FROM ".db('prefix')."$this->panelTable $this->dbMethod $dbPage"); $rowNum=0;
while ($this->panelTitle!="system"&&$r=mysql_fetch_array($s)) {
extract($r);

$rowType= (($rowNum % 2)==0) ? ' class="alt"':''; //CSS add-in to colorize alternate bars in tables
$isPub=$this->pubButton($published,$id); //Identical publish method

if($this->panelTitle=="articles") {
//$isPub=($_SESSION['userId']==$auth&&isAble('MANAGE_ARTICLES')||$_SESSION['userId']!=$auth&&isAble('MANAGE_OTHER_ARTICLES')) ? $isPub:'';
$c=mysql_fetch_array(mysql_query("SELECT * FROM ".db('prefix')."categories WHERE id='$cat'")); 
echo "\r\n".'<tr'.$g.'><td class="check"><input type="checkbox" name="'.$rowNum.'" value="'.$id.'" id="article-'.$id.'" /></td>
<td class="name"><label for="article-'.$id.'"><a href="'.SEFlinks('article',$sef_title).'">'.convertForDb(array('type' => 'screen', 'text' => $title)).'</a><br />('.$c['title'].'; '.timeStamp($date,"M j, Y, g:ia").')</label></td>
<td class="buttons"><ul><li><a href="?admin.'.$this->panelTitle.'.edit.'.$id.'">Edit</a></li>'.$isPub.'<li><a href="?admin.'.$this->panelTitle.'.delete.'.$id.'">Del</a></li></ul></td></tr>';
}

elseif($this->panelTitle=="categories") { $del= ($id!=1) ? '<li><a href="?admin.'.$this->panelTitle.'.delete.'.$id.'">Del</a></li>':'';
$a=mysql_num_rows(mysql_query("SELECT * FROM ".db('prefix')."content WHERE type='article' AND cat='$id'"));
$p=mysql_num_rows(mysql_query("SELECT * FROM ".db('prefix')."content WHERE type='page' AND cat='$id'"));
echo "\r\n".'<tr'.$g.'><td class="check"></td><td class="name"><a href="?list.'.$sef_title.'" title="?list.'.$sef_title.'">'.$title.'</a> ['.$position.']<br /> '.$a.' article(s), '.$p.' page(s)</td><td class="buttons"><ul><li><a href="?admin.'.$this->panelTitle.'.edit.'.$id.'">Edit</a></li>'.$isPub.$del.'</ul></td></tr>'; 
}

elseif($this->panelTitle=="extra") { $del= ($id!=1) ? '<li><a href="?admin.'.$this->panelTitle.'.delete.'.$id.'">Del</a></li>':'';
//$a=mysql_num_rows(mysql_query("SELECT * FROM ".db('prefix')."content WHERE type='article' AND cat='$id'"));
echo "\r\n".'<tr'.$g.'><td class="check"><input type="checkbox" name="'.$rowNum.'" value="'.$id.'" /></td><td class="name">'.$title.' ['.$position.']</td>
<td class="buttons"><ul><li><a href="?admin.'.$this->panelTitle.'.edit.'.$id.'">Edit</a></li>'.$isPub.'<li><a href="?admin.'.$this->panelTitle.'.delete.'.$id.'">Del</a></li></ul></td></tr>'; 
}

elseif($this->panelTitle=="comments") {
$g= ($approved==0) ? ' class="rejected"': ($approved==2) ? ' class="pending"': '';
$app= ($approved==1) ? 'reject':'approve'; $abut='<li><a href="?admin.'.$this->panelTitle.'.'.$app.'.'.$id.'">'.ucfirst($app).'</a></li>';
$a=mysql_fetch_array(mysql_query("SELECT * FROM ".db('prefix')."content WHERE id='$pid'"));
echo "\r\n".'<tr'.$g.'><td class="check"><input type="checkbox" name="'.$rowNum.'" value="'.$id.'" /></td><td class="name"><a href="?article.'.$a['sef_title'].'#comments">By '.$name.'</a><br />(To <a href="?article.'.$a['sef_title'].'#comments">'.$a['title'].'</a>; '.timeStamp($date,"M j, Y, g:ia").')</td><td class="buttons"><ul><li><a href="?admin.'.$this->panelTitle.'.edit.'.$id.'">Edit</a></li>'.$abut.'<li><a href="?admin.'.$this->panelTitle.'.delete.'.$id.'">Del</a></li></ul></td></tr>';
}

elseif($this->panelTitle=="menus") { echo "\r\n".'<tr'.$g.'><td class="check"><input type="checkbox" name="'.$rowNum.'" value="'.$id.'" /></td><td class="name">'.$title.' ('.$name.')</td><td class="buttons"><ul><li><a href="?admin.'.$this->panelTitle.'.edit.'.$id.'">Edit</a></li><li><a href="?admin.'.$this->panelTitle.'.delete.'.$id.'">Del</a></li></ul></td></tr>'; }

elseif($this->panelTitle=="users") { 
if($_SESSION['userId']!=$id&&isAble('MANAGE_USERS')) $del='<li><a href="?admin.users.delete.'.$id.'">Del</a></li>';
if($_SESSION['userId']!=$id&&isAble('MANAGE_USERS')||$_SESSION['userId']==$id) echo "\r\n".'<tr'.$g.'><td class="check">&nbsp;</td><td class="name">'.$name.'</td><td class="buttons"><ul><li><a href="?admin.'.$this->panelTitle.'.edit.'.$id.'">Edit</a></li>'.$del.'</ul></td></tr>';
}

elseif($this->panelTitle=="pages") { $c=mysql_fetch_array(mysql_query("SELECT * FROM ".db('prefix')."categories WHERE id='$cat'"));
echo "\r\n".'<tr'.$g.'><td class="check"><input type="checkbox" name="'.$rowNum.'" value="'.$id.'" /></td>
<td class="name"><a href="'.SEFlinks('page',$sef_title).'">'.convertForDb(array('type' => 'screen', 'text' => $title)).'</a><br />('.$c['title'].'; '.timeStamp($date,"M j, Y, g:ia").')</td>
<td class="buttons"><ul><li><a href="?admin.'.$this->panelTitle.'.edit.'.$id.'">Edit</a></li>'.$isPub.'<li><a href="?admin.'.$this->panelTitle.'.delete.'.$id.'">Del</a></li></ul></td></tr>'; }

$rowNum++; //Counter for the rows in a table and also the trigger that the loop has been processed at least once
}

//The System Settings work differently, must bypass all of the loops
if($this->panelTitle=="system") {

echo '</form><tr><td></td></tr></table>'; 

}

elseif($this->panelTitle=="extensions") {
echo '</form>';

if ($handle=@opendir('extensions/')) { 
//$q=mysql_query("SELECT * FROM ".db('prefix')."system WHERE type='extension' AND data='TRUE'");
//while ($r=mysql_fetch_array($q)) { $INSTALLED_EXT.="$r[name]|"; }

$ignore=array('..','.','cgi-bin');
while($item=@readdir($handle)) { if(!in_array($item,$ignore) && file_exists('extensions/'.$item.'/about.php')) $extList[]='extensions/'.$item.'/about.php'; }
@closedir($handle);
} else echo '<h2>What are extensions?</h2>
<p>Extensions are scripts that can be included into Leap to add new functions and features to the CMS.</p>
<h2>How do I use extensions?</h2>
<p>Extensions are currently not available. They will be available during the next release.</p>
'; 

foreach($extList as $item) {
include($item);
echo "<tr><td>$_NAME $_VERSION<br /><a href=\"$_UPDATE_URL\" title=\"$_DESCRIPTION\">by $_AUTHOR</a>";
echo '</td><td><ul><li><a href="#">Activate</a></li>
<li><a href="#">Settings</a></li>
<li><a href="#">Remove</a></li></ul></td></tr>';

}

echo'</table>'; 

}

elseif ($rowNum>0) { //If any table data was created, end it off 

echo '<tr><td colspan="2"><input type="hidden" name="total" value="'.$rowNum.'" />';
if(!ereg($this->panelTitle,'(users|categories)')) echo formItem(array('type'=>'submit', 'name'=>'delete', 'value'=>'Delete Checked'));
if(ereg($this->panelTitle,'(articles|comments)')) echo '<ul><li><a href="?admin.'.$this->panelTitle.'.purge">Purge</a></li></ul>';
if($this->panelTitle!="comments") echo '<ul><li><a href="?admin.'.$this->panelTitle.'.add">Create New</a></li></ul>';
echo '</td></form><form method="post" action="?admin.'.$this->panelTitle.'"><td>Page: <select name="page">';

//Pagination
$s=mysql_query("SELECT * FROM ".db('prefix')."$this->panelTable $this->dbMethod"); $i=0; $g=0;
while ($r=mysql_fetch_array($s)) {
if (($i % _value('panel-item-list-num'))==0) { $g++; echo "<option value=\"$g\">$g</option>"; }
$i++; 
}

echo '</select> '.formItem(array('type'=>'submit','value'=>'Go','name'=>$this->panelTitle)).'</td></tr></form></table>';  
} else {

$addnew=($this->panelTitle!='comments') ? ' <a href="?admin.'.$this->panelTitle.'.add">Add new.</a>':'';
echo "<tr><td><p>No $this->panelTitle.$addnew</p></td></tr></form></table>";

}


}//End of the list creation function

function addEditItem() {
global $leap;

if(isset($_POST['secureAction'])){ //Submit Action
//print_r($_POST); //For Debug
extract($_POST);

if($this->panelTitle=="articles") {
if(!$published||$published=='') { $published=$origpub; }
$date= ($published==0) ? $origdate:$date_date_year.$date_date_month.$date_date_day.$date_date_hour.$date_date_minute.'00'; $comments=($comments=="on") ? 1:0; $sef_title=makeSEF($sef_title); $body=convertForDb(array('type' => 'db', 'text' => $body)); $keys=str_replace(array("'",'"'), array("&#39;","&#34;"), $keywords); $desc=str_replace(array("'",'"'), array("&#39;","&#34;"), $description);
$enddate= ($unpublish==0) ? $date:$enddate_date_year.$enddate_date_month.$enddate_date_day.$enddate_date_hour.$enddate_date_minute.'00';
if ($unpublish==1&&($enddate<$date)) $enddate=$date;

if ($this->qAction[2]=="edit") $query="UPDATE ".db('prefix')."content SET auth='$auth', cat='$cat',title='$title',sef_title='$sef_title',body='$body',date='$date',mod_date='".timeStamp()."',keywords='$keys',description='$desc',published='$published', unpublish='$unpublish', enddate='$enddate', comments='$comments' WHERE id='".$this->qAction[3]."'";
else $query="INSERT INTO ".db('prefix')."content(type,cat,auth,title,sef_title,description,keywords,body,date,mod_date,published,unpublish,enddate,comments) VALUES('article','$cat','$auth','$title','$sef_title','$desc','$keys','$body','".(($published==1) ? timeStamp():$date)."','".timeStamp()."','$published','$unpublish','$enddate','$comments')";
$itemName="Article";
}

if($this->panelTitle=="pages") {
if(!$published||$published=='') $published=$origpub;
$date= ($published==0) ? $origdate:$date_date_year.$date_date_month.$date_date_day.$date_date_hour.$date_date_minute.'00'; $comments=($comments=="on") ? 1:0; $sef_title=makeSEF($sef_title); $body=convertForDb(array('type' => 'db', 'text' => $body)); $keys=str_replace(array("'",'"'), array("&#39;","&#34;"), $keywords); $desc=str_replace(array("'",'"'), array("&#39;","&#34;"), $description);
$enddate= ($unpublish==0) ? $date:$enddate_date_year.$enddate_date_month.$enddate_date_day.$enddate_date_hour.$enddate_date_minute.'00';
if ($unpublish==1&&($enddate<$date)) $enddate=$date;

if ($this->qAction[2]=="edit") $query="UPDATE ".db('prefix')."content SET auth='$auth', cat='$cat',title='$title',sef_title='$sef_title',body='$body',date='$date',mod_date='".timeStamp()."',keywords='$keys',description='$desc',published='$published', unpublish='$unpublish', enddate='$enddate', comments='$comments' WHERE id='".$this->qAction[3]."'";
else $query="INSERT INTO ".db('prefix')."content(type,cat,auth,title,sef_title,description,keywords,body,date,mod_date,published,unpublish,enddate,comments) VALUES('page','$cat','$auth','$title','$sef_title','$desc','$keys','$body','".(($published==1) ? timeStamp():$date)."','".timeStamp()."','$published','$unpublish','$enddate','$comments')";
$itemName="Page";
}

if($this->panelTitle=="comments") {
$approved= ($approved=='on') ? '1':'0';
$query="UPDATE ".db('prefix')."$this->panelTitle SET name='$name',url='$url',comment='$comment',approved='$approved' WHERE id='$id'";
$itemName="Comment";
}

if($this->panelTitle=="menus") { $n=0;
while($total > $n) { $arr[$n]= str_replace(array("'",'"'), array("&#39;","&#34;"), $_POST[$n]); $n++; }
$n=1;
while($total > $n) { if($arr[$n]==$arr[($n-1)]) {unset($arr[$n]); unset($arr[($n-1)]);} $n=$n+2; }
$data = implode(";;", $arr); $query="UPDATE ".db('prefix')."menus SET title='$title',name='$name',data='$data' WHERE id='$id'";

$itemName="Menu";
}

if($this->panelTitle=="users") {
if($this->qAction[2]=="edit"||$this->qAction[2]=="add"&&$pwd!=''&&$pwd==$pwdchk) { 
$pwd=($pwd!=''&&$pwd==$pwdchk) ? md5($pwd):$pwdefault;
if ($defperms) $permissions=$defperms;
$permissions=implode('',$permission);


if ($this->qAction[2]=="edit") $query="UPDATE ".db('prefix')."users SET mail='$mail',name='$name',pwd='$pwd',permissions='$permissions' WHERE id='$id'";
else $query="INSERT INTO ".db('prefix')."users(name,mail,pwd,permissions) VALUES('$name','$mail','$pwd','$permissions')";
$itemName="User";
} else { unset($_POST); $this->addEditItem();  } //return FALSE;

}

if($this->panelTitle=="categories") {
if ($position>99) $position=99; if ($position<1||!is_numeric($position)) $position=1;
$sef_title=makeSEF($sef_title);
if ($this->qAction[2]=="edit") $query="UPDATE ".db('prefix')."categories SET title='$title',sef_title='$sef_title',description='$description',published='$published',position='$position',sub_id='$sub_id' WHERE id='$id'";
else $query="INSERT INTO ".db('prefix')."categories(title,sef_title,description,published,position,sub_id) VALUES('$title','$sef_title','$description','$published','$position','$sub_id')";
$itemName="Category";
}

if($this->panelTitle=="extra") {
if ($position>99) $position=99; if ($position<1||!is_numeric($position)) $position=1;
$content='('.implode(")(", $for_content).')';
$process='('.implode(")(", $for_process).')';
if ($this->qAction[2]=="edit") $query="UPDATE ".db('prefix')."extra SET title='$title',space='$space',body='$body',for_cat='$for_cat',for_content='$content',for_process='$process',position='$position',published='$published' WHERE id='$id'";
else $query="INSERT INTO ".db('prefix')."extra(title,space,body,for_cat,for_content,for_process,position,published) VALUES('$title','$space','$body','$for_cat','$content','$process','$position','$published')";
$itemName="Extra Content";
}

// Process the query once all the data has been properly arranged
// 12/2/07: Added a debug measure in there. If any error occurs, it'll be easier to document the issue.
$result=mysql_query($query);
if (!$result) $resultMsg='Invalid query: <strong>'.mysql_error()."</strong><br /> Whole query: <strong>".$query.'</strong>';
else $resultMsg="Successfully ".(($this->qAction[2]=="edit") ? 'Edited ':'Added ').$itemName;
echo "<p id=\"adminNotice\">$resultMsg</p>";
$this->createList();

} else { //Edit or Add Articles
if ($this->qAction[2]=="edit") { $r=mysql_fetch_array(mysql_query("SELECT * FROM ".db('prefix')."$this->panelTable WHERE id='".$this->qAction[3]."'")); extract($r); }

if(ereg($this->panelTitle,'(articles|pages)')) {

//The user is editing an article
if ($this->qAction[2]=="edit") { $body=convertForDb(array('type' => 'screen', 'text' => $body)); $title=convertForDb(array('type' => 'screen', 'text' => $title)); } else { $auth=$_SESSION['userId']; }
if($published=='') $published=_value('autopub-articles'); if($comments==1||$comments==''&&_value('allow-comment')==1) $com=' checked="checked"';
$q=mysql_query("SELECT * FROM ".db('prefix')."users WHERE id='$auth'"); $user=mysql_fetch_array($q); $name=$user['name'];

    echo '<form method="post" name="'.$this->panelTitle.'" action="?'.db('query').'" />';
    echo formItem(array('type'=>'hidden','name'=>'origdate','value'=>$date)).formItem(array('type'=>'hidden','name'=>'origcom','value'=>$comments)).formItem(array('type'=>'hidden','name'=>'origpub','value'=>$published));
    echo formItem(array('type'=>'hidden','name'=> token_label(),'value'=>make_token() ) );
    echo '<p>Title:<br />'."\n<script type=\"text/javascript\">function makeSEF(a,b){b=b.value.toLowerCase();var c=[/[\\xC0-\\xC6]/g,/[\\xE0-\\xE6]/g,/[\\xC8-\\xCB]/g,/[\\xE8-\\xEB]/g,/[\\xCC-\\xCF]/g,/[\\xEC-\\xEF]/g,/[\\xD2-\\xD6]/g,/[\\xF2-\\xF6]/g,/[\\xD9-\\xDC]/g,/[\\xF9-\\xFC]/g,/(\\x9F|xDD|\\xFD|\\xFF)/g,/(\\xC7|\\xE7)/g,/(\\xD1|\\xF1)/g,/(\\x8A|\\x9A)/g,/(\\x8E|\\x9E)/g],d=['a','a','e','e','i','i','o','o','u','u','y','c','n','s','z'],i;for(i in c)b=b.replace(c[i],d[i]);b=b.replace(/[^A-Za-z0-9 \-]+/g,'').replace(/(\s|\-)+/g,'-').replace(/^-+|-+$/g,'');a.value=b}</script>";
    echo formItem(array('type'=>'text','name'=>'title','value'=>$title,'extra'=>'onChange="makeSEF(document.forms[\''.$this->panelTitle.'\'].sef_title,this);" onKeyUp="makeSEF(document.forms[\''.$this->panelTitle.'\'].sef_title,this);"')).'</p>';

    echo '<p>Search Engine Friendly URL:<br />'.formItem(array('type'=>'text','name'=>'sef_title','value'=>$sef_title,'extra'=>'onBlur="makeSEF(document.forms[\''.$this->panelTitle.'\'].sef_title,this);"')).'</p>';
    echo '<p>Author:<br /><select name="auth">';

$a1=mysql_query("SELECT * FROM ".db('prefix')."users");
while ($a=mysql_fetch_array($a1)) { 
$current= ($this->qAction[2]=="edit") ? $a['id']:$_SESSION['userId'];
$selected= ($auth==$current) ? ' selected="selected"':''; 
$authors.="\n<option value=\"$a[id]\"$selected>$a[name]</option>"; }

    echo $authors.'</select></p>';
    echo '<p>Content: <br />
<script type="text/javascript">edToolbar(\'body\');</script><br />'.formItem(array('type'=>'textarea','id'=>'body','name'=>'body','value'=>$body)).'<br /><br /></p>';
    echo '<p>Category:<br /><select name="cat">';
    
foreach(categoryList() as $catId => $value) {
$selected= ($cat==$catId) ? ' selected="selected"':''; if ($catId != 0) echo "\n<option value=\"$catId\"$selected>".$value[0]['title']."</option>";

if ((count($value) - 1) > 0) {
foreach($value as $catSubId => $subvalue) {
$selected= ($cat==$catSubId) ? ' selected="selected"':''; if ($catSubId != 0) echo "\n<option value=\"$catSubId\"$selected>- ".$subvalue[0]['title']."</option>";
}

}

}
echo '</select></p>';
    
    echo '<p>Keywords:<br />'.formItem(array('type'=>'text','name'=>'keywords','value'=>$keywords)).'</p>';

    echo '<p>'.formItem(array('type'=>'submit','name'=>'secureAction','value'=>'Submit')).formItem(array('type'=>'reset','value'=>'Reset')).'</p>';        
    echo '<fieldset><legend><a title="Optional Settings">Optional Settings</a></legend><div id="optional_settings">';

    echo '<p>Meta Description:<br />'.formItem(array('type'=>'text','name'=>'description','value'=>$description)).'</p>';
    $pub= '<select name="published"><option value="1"'.(($published==1) ? ' selected="selected"':'').'>Publish Now</option><option value="2"'.(($published==2) ? ' selected="selected"':'').'>Set Publish Date</option><option value="0"'.(($published==0) ? ' selected="selected"':'').'>Not Published</option></select>';
    echo "\n<p>Publish Status:<br />$pub</p>";
    echo "<p>Publish Date:<br />"; $this->posting_time('date',$date); echo'</p>';

    $pub= '<select name="unpublish"><option value="0"'.(($unpublish==0) ? ' selected="selected"':'').'>Keep Alive</option><option value="1"'.(($unpublish==1) ? ' selected="selected"':'').'>Publish Until</option></select>';
    echo "\n<p>Unpublish Status:<br />$pub</p>";
    echo "<p>Unpublish Date:<br />"; $this->posting_time('enddate',$enddate); echo'</p>';

    echo "<p><input type=\"checkbox\" name=\"comments\" id=\"comments\"$com $disabled /> <label for=\"comments\">Enable Comments</label></p>";
    echo '</div></fieldset>';
    echo '<p>'.formItem(array('type'=>'submit','name'=>'secureAction','value'=>'Submit')).formItem(array('type'=>'reset','value'=>'Reset')).'</p></form>';
}

elseif($this->panelTitle=="categories") { //[EDIT CATEGORY]
    echo '<form name="categories" method="post" action="?'.db('query').'" /><input type="hidden" name="id" value="'.$id.'" />';
    echo formItem(array('type'=>'hidden','name'=> token_label(),'value'=>make_token() ) );
    echo '<p>Title:<br />'."\n<script type=\"text/javascript\">function makeSEF(a,b){b=b.value.toLowerCase();var c=[/[\\xC0-\\xC6]/g,/[\\xE0-\\xE6]/g,/[\\xC8-\\xCB]/g,/[\\xE8-\\xEB]/g,/[\\xCC-\\xCF]/g,/[\\xEC-\\xEF]/g,/[\\xD2-\\xD6]/g,/[\\xF2-\\xF6]/g,/[\\xD9-\\xDC]/g,/[\\xF9-\\xFC]/g,/(\\x9F|xDD|\\xFD|\\xFF)/g,/(\\xC7|\\xE7)/g,/(\\xD1|\\xF1)/g,/(\\x8A|\\x9A)/g,/(\\x8E|\\x9E)/g],d=['a','a','e','e','i','i','o','o','u','u','y','c','n','s','z'],i;for(i in c)b=b.replace(c[i],d[i]);b=b.replace(/[^A-Za-z0-9 \-]+/g,'').replace(/(\s|\-)+/g,'-').replace(/^-+|-+$/g,'');a.value=b}</script>";
    echo formItem(array('type'=>'text','name'=>'title','value'=>$title,'extra'=>'onChange="makeSEF(document.forms[\''.$this->panelTitle.'\'].sef_title,this);" onKeyUp="makeSEF(document.forms[\''.$this->panelTitle.'\'].sef_title,this);"')).'</p>';

    echo '<p>Search Engine Friendly URL:<br />'.formItem(array('type'=>'text','name'=>'sef_title','value'=>$sef_title,'extra'=>'onBlur="makeSEF(document.forms[\''.$this->panelTitle.'\'].sef_title,this);"')).'</p>';
    echo '<p>Description:<br /> '.formItem(array('type'=>'text','name'=>'description','value'=>$description)).'</p>';
    echo '<p>Position (Between 1 and 99, listed in ascending order):<br /> '.formItem(array('type'=>'text','name'=>'position','value'=>$position)).'</p>';
    $pub= '<select name="published"><option value="1"'.((!$published||$published==1) ? ' selected="selected"':'').'>Published</option><option value="0"'.((isset($published)&&$published==0) ? ' selected="selected"':'').'>Not Published</option></select>';
    echo "<p>Publish Status:<br />$pub</p>";
    //Subcategories have been disabled
    echo '<p>Subcategory:<br /><select name="sub_id">';
$selected= ($sub_id==0) ? ' selected="selected"':'';
echo "\n<option value=\"0\"$selected>Not Subcategory</option>";
$categories=categoryList();

if (isset($categories[$id]) && (count($categories[$id]) - 1) > 0) echo NULL;
else foreach($categories as $catId => $value) {
if ($id==$catId) continue; 

$selected= ($sub_id==$catId) ? ' selected="selected"':'';
if ($catId != 0) echo "\n<option value=\"$catId\"$selected>".$value[0]['title']."</option>";
}
    echo '</select></p>';
    //echo '<input type="hidden" name="sub_id" value="0" />';

    echo '<p>'.formItem(array('type'=>'submit','name'=>'secureAction','value'=>'Submit')).formItem(array('type'=>'reset','value'=>'Reset')).'</p></form>';
}

elseif($this->panelTitle=="extra") { // [EDIT_EXTRA_CONTENT]
    echo "<form class=\"pages\" method=\"post\" action=\"?".db('query')."\" /><input type=\"hidden\" name=\"id\" value=\"$id\" />";
    echo formItem(array('type'=>'hidden','name'=> token_label(),'value'=>make_token() ) );
    echo '<p>Title:<br />'.formItem(array('type'=>'text','name'=>'title','value'=>$title)).'</p>';
    echo '<p>Spaces:<br />'.formItem(array('type'=>'text','name'=>'space','value'=>$space)).'</p>';
    echo '<p>Content: <br />
<script type="text/javascript">edToolbar(\'body\');</script><br />'.formItem(array('type'=>'textarea','id'=>'body','name'=>'body','value'=>$body)).'<br /><br /></p>';

    echo '<p>Position (1-99):<br />'.formItem(array('type'=>'text','name'=>'position','value'=>''.$position.'')).'</p>';

echo '<p>Appear on Category:<br /><select name="for_cat">';
$a1=mysql_query("SELECT * FROM ".db('prefix')."categories");
$selected= ($for_cat=='0') ? ' selected="selected"':'';
echo "\n<option value=\"0\"$selected>All Categories</option>";
$selected= ($for_cat=='-1') ? ' selected="selected"':'';
echo "\n<option value=\"-1\"$selected>None</option>";

foreach(categoryList() as $catId => $value) {
$selected= ($for_cat==$catId) ? ' selected="selected"':''; if ($catId != 0) echo "\n<option value=\"$catId\"$selected>".$value[0]['title']."</option>";

if ((count($value) - 1) > 0) {
foreach($value as $catSubId => $subvalue) {
$selected= ($for_cat==$catSubId) ? ' selected="selected"':''; if ($catSubId != 0) echo "\n<option value=\"$catSubId\"$selected>- ".$subvalue[0]['title']."</option>";
}

}

}
echo '</select></p>';

echo '<p>Appear on Content: (select more than one using Ctrl + Click)<br /><select name="for_content[]" multiple="multiple" size="5">';
$a1=mysql_query("SELECT * FROM ".db('prefix')."content");
while ($a=mysql_fetch_array($a1)) { 
$selected= (strstr($for_content, "($a[id])")) ? ' selected="selected"':'';
echo "\n<option value=\"$a[id]\"$selected>$a[title] (".ucfirst($a['type']).")</option>"; }
echo '</select></p>';

echo '<p>Appear on Process(es): (select more than one using Ctrl + Click)<br /><select name="for_process[]" multiple="multiple" size="5">';
foreach ($leap->FuncTriggers as $process) { 
$selected= (strstr($for_process, "($process)")) ? ' selected="selected"':'';
echo "\n<option value=\"$process\"$selected>$process</option>"; }
echo '</select></p>';

    $pub= '<select name="published"><option value="1"'.(($published==1) ? ' selected="selected"':'').'>Published</option><option value="0"'.(($published==0) ? ' selected="selected"':'').'>Not Published</option></select>';
    echo "<p>Publish Status:<br />$pub</p>";

    echo '<p>'.formItem(array('type'=>'submit','name'=>'secureAction','value'=>'Submit')).formItem(array('type'=>'reset','value'=>'Reset')).'</p></form>';

}

elseif($this->panelTitle=="comments") { // [Editing a Comment
$app= ($approved==1) ? ' checked="checked"':''; $comment=convertForDb(array('type' => 'screen', 'text' => $comment));

    echo "<form method=\"post\" action=\"?".db('query')."\" /><input type=\"hidden\" name=\"id\" value=\"$id\" />";
    echo formItem(array('type'=>'hidden','name'=> token_label(),'value'=>make_token() ) );
    echo "<p>Name:<br /> <input type=\"text\" class=\"text\" name=\"name\" value=\"$name\" /></p>";
    echo "<p>IP Address:<br /> <input type=\"text\" class=\"text\" value=\"$ip\" readonly=\"readonly\" /></p>";
    echo "<p>URL/Email:<br /> <input type=\"text\" class=\"text\" name=\"url\" value=\"$url\" /></p>";
    echo "<p>Comment:<br /> <textarea name=\"comment\">$comment</textarea></p>";
    echo "<p><input type=\"checkbox\" name=\"approved\"$app> Approved Comment</p>";
    echo '<p>'.formItem(array('type'=>'submit','name'=>'secureAction','value'=>'Submit')).formItem(array('type'=>'reset','value'=>'Reset')).'</p></form>';
}

elseif($this->panelTitle=="menus") { //Adding Menu
if ($this->qAction[2]=="add"&&!$_POST['createMenu']) {
echo '<table class="menuTable"><form method="post" action="?'.db('query').'" >';
echo formItem(array('type'=>'hidden','name'=> token_label(),'value'=>make_token() ) );
echo '<tr><td class="left">Menu Name: </td><td class="right">'.formItem(array('type'=>'text','name'=>'name')).'</td></tr>';
echo '<tr><td class="left">Menu Title: </td><td class="right">'.formItem(array('type'=>'text','name'=>'title')).'</td></tr>';
echo '<tr><td class="left">'.formItem(array('type'=>'text','name'=>'0')).'</td><td class="right">'.formItem(array('type'=>'text','name'=>'1')).'</td></tr>';
echo '<tr><td colspan="2">'.formItem(array('type'=>'submit','name'=>'createMenu','value'=>'Save and Edit')).formItem(array('type'=>'reset','value'=>'Reset')).'</td></tr></form></table>';

} else {
extract($_POST);

if ($createMenu) {

$name=makeSEF($name); $n=0;
while(3 > $n) { $arr[$n]=str_replace(array("'","\""), array("&;@","&;#"), $_POST[$n]); $n++; }
$data=implode(";;", $arr);
mysql_query("INSERT INTO ".db('prefix')."menus(name,title,data) VALUES('$name','$title','$data')");

$s=mysql_query("SELECT * FROM ".db('prefix')."menus WHERE name='$name'"); $r=mysql_fetch_array($s);
extract($r);

$pageURL="admin.menus.edit.$id";
} else { $pageURL=db('query'); }

if ($additem) { //Adding a field to Menu
$name=makeSEF($name); $n=0;
while($total > $n) { $arr[$n]=str_replace(array("'","\""), array("&;@","&;#"), $_POST[$n]); $n++; }
$n=1;
while($total > $n) { if($arr[$n]==$arr[($n-1)]) {unset($arr[$n]); unset($arr[($n-1)]);} $n=$n+2; }
$data=implode(";;", $arr); mysql_query("UPDATE ".db('prefix')."menus SET name='$name',title='$title',data='$data' WHERE id='".$this->qAction[3]."'");

$s=mysql_query("SELECT * FROM ".db('prefix')."menus WHERE id='".$this->qAction[3]."'"); $r=mysql_fetch_array($s);
$data=$r['data'].";;;;"; mysql_query("UPDATE ".db('prefix')."menus SET data='$data' WHERE id='".$this->qAction[3]."'");
}

//Updating Menu - To get rid of empty fields and update data
if ($update) { $n=0;
while($total > $n) { $arr[$n]=str_replace(array("'","\""), array("&;@","&;#"), $_POST[$n]); $n++; }
$n=1;
while($total > $n) { if($arr[$n]==''&&$arr[($n-1)]=='') {unset($arr[$n]); unset($arr[($n-1)]);} $n=$n+2; }
$data=implode(";;", $arr);
mysql_query("UPDATE ".db('prefix')."menus SET name='$name',title='$title',data='$data' WHERE id='".$this->qAction[3]."'");
}

$opt=explode(";;",$data); $n=0;

echo '<table class="menuTable"><form method="post" action="?'.$pageURL.'" >'.formItem(array('type'=>'hidden','name'=>'id','value'=>$id));
$funcOutput.='<tr><td class="left">Menu Name: </td><td class="right">'.formItem(array('type'=>'text','name'=>'name','value'=>$name)).'</td></tr>';
$funcOutput.='<tr><td class="left">Menu Title: </td><td class="right">'.formItem(array('type'=>'text','name'=>'title','value'=>$title)).'</td></tr>';

while(count($opt) > $n) {
$funcOutput.='<tr><td class="left">'.formItem(array('type'=>'text','name'=>$n,'value'=>$opt[$n])).'</td>'; $n++;
$funcOutput.='<td class="right">'.formItem(array('type'=>'text','name'=>$n,'value'=>$opt[$n])).'</td></tr>'; $n++;
}

echo $funcOutput; 
echo '<tr><td colspan="2">'.formItem(array('type'=>'hidden','name'=>'total','value'=>$n)).formItem(array('type'=>'submit','name'=>'additem','value'=>'Add Field')).formItem(array('type'=>'submit','name'=>'update','value'=>'Update Menu')).formItem(array('type'=>'submit','name'=>'secureAction','value'=>'Save and Exit')).formItem(array('type'=>'reset','value'=>'Reset')).'</td></tr></form></table>';
}

}

elseif($this->panelTitle=="users"&&$_SESSION['userId']!=$id&&isAble('MANAGE_USERS')||$this->panelTitle=="users"&&$_SESSION['userId']==$id) { //Editing/adding a user
$perm=($permissions) ? $permissions:'abcdefghi';
$permission=permHandler($perm);
foreach ($permission as $key => $value) $permission[$key]= ($value==TRUE) ? 'checked="checked"':'';

    echo '<form method="post" action="?'.db('query').'" />'.formItem(array('type'=>'hidden','name'=>'id','value'=>$id)).formItem(array('type'=>'hidden','name'=> token_label(),'value'=>make_token() ) );
    echo '<p><label for="name">User Name:</label><br />'.formItem(array('type'=>'text','name'=>'name','id'=>'name','value'=>$name)).'</p>';
    echo '<p><label for="mail">Email Address:</label><br />'.formItem(array('type'=>'text','name'=>'mail','id'=>'mail','value'=>$mail)).'</p>';
    echo '<p><label for="pwd">New Password:</label><br />'.formItem(array('type'=>'hidden','name'=>'pwdefault','value'=>$pwd)).formItem(array('type'=>'password','name'=>'pwd','id'=>'pwd')).'</p>';
    echo '<p><label for="pwdchk">Re-enter Password:</label><br />'.formItem(array('type'=>'password','name'=>'pwdchk','id'=>'pwdchk')).'</p>';


if (isAble('MANAGE_USERS')) {
echo '<fieldset><legend><a title="Permissions">Permissions</a></legend><div id="permissions">';
echo formItem(array('type'=>'checkbox','name'=>'permission[]','value'=>'a','extra' => $permission['MANAGE_SYSTEM']))." Manage System<br />
".formItem(array('type'=>'checkbox','name'=>'permission[]','value'=>'b','extra' => $permission['MANAGE_USERS']))." Manage Users<br />
".formItem(array('type'=>'checkbox','name'=>'permission[]','value'=>'c','extra' => $permission['MANAGE_EXTENSIONS']))." Manage Extensions<br />
".formItem(array('type'=>'checkbox','name'=>'permission[]','value'=>'d','extra' => $permission['MANAGE_CATEGORIES']))." Manage Categories<br />
".formItem(array('type'=>'checkbox','name'=>'permission[]','value'=>'e','extra' => $permission['MANAGE_PAGES']))." Manage Pages<br />
".formItem(array('type'=>'checkbox','name'=>'permission[]','value'=>'f','extra' => $permission['MANAGE_ARTICLES']))." Manage Articles<br />
".formItem(array('type'=>'checkbox','name'=>'permission[]','value'=>'g','extra' => $permission['MANAGE_OTHER_ARTICLES']))." Manage Others' Articles<br />
".formItem(array('type'=>'checkbox','name'=>'permission[]','value'=>'h','extra' => $permission['MANAGE_COMMENTS']))." Manage Comments";
echo "</div></fieldset>"; }

else echo formItem(array('type'=>'hidden','name'=>'defperms','value'=>$perm)); 

echo '<p>'.formItem(array('type'=>'submit','name'=>'secureAction','value'=>'Submit')).formItem(array('type'=>'reset','value'=>'Reset')).'</p></form>';

} else { $this->createList(); }

}// End Add/Edit Articles

}// End Function

// ARTICLES - POSTING TIME - Modified from sNews
function posting_time($prefix, $time='') { #YYYYMMDDHHMMSS
  //echo formItem(array('type'=>'hidden','name'=>$prefix.'_date','value'=>$prefix.'_'); 
	echo '<select name="'.$prefix.'_date_month"><option value="01" disabled="disabled">Month</option>';
	$thisMonth = !empty($time) ? substr($time, 4, 2) : intval(date('m'));
	$month=array('','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');
	for($i = 1; $i < 13; $i++) {
		$num=($i < 10) ? '0'.$i:$i;
    echo '<option value="'.$num.'"';
		if($i == $thisMonth) {echo ' selected="selected"';}
		echo '>'. $month[$i] .'</option>';
	}
	echo '</select> / <select name="'.$prefix.'_date_day"><option value="01" disabled="disabled">Day</option>';
	$thisDay=!empty($time) ? substr($time, 6, 2):intval(date('d'));
	for($i = 1; $i < 32; $i++) {
		$num=($i < 10) ? '0'.$i:$i;
    echo '<option value="'.$num.'"';
		if($i == $thisDay) {echo ' selected="selected"';}
		echo '>'.$i.'</option>';
	}
	echo '</select> / <select name="'.$prefix.'_date_year"><option value="'.intval(date('Y')).'" disabled="disabled">Year</option>';
	$thisYear = !empty($time) ? substr($time, 0, 4) : intval(date('Y'));
	for($i = $thisYear - 3; $i < $thisYear + 3; $i++) {
		echo '<option value="'.$i.'"';
		if($i == $thisYear) {echo ' selected="selected"';}
		echo '>'.$i.'</option>';
	}
	echo '</select>, <select name="'.$prefix.'_date_hour"><option value="00" disabled="disabled">Hour</option>';
	$thisHour = !empty($time) ? substr($time, 8, 2) : intval(date('H'));
	for($i = 0; $i < 24; $i++) {
		$num=($i < 10) ? '0'.$i:$i;
    echo '<option value="'.$num.'"';
		if($i == $thisHour) {echo ' selected="selected"';}
		echo '>'.$num.'</option>';
	}
	echo '</select> : <select name="'.$prefix.'_date_minute"><option value="00" disabled="disabled">Minute</option>';
	$thisMinute = !empty($time) ? substr($time, 10, 2) : intval(date('i'));
	for($i = 0; $i < 60; $i++) {
		$num=($i < 10) ? '0'.$i:$i;
    echo '<option value="'.$num.'"';
		if($i == $thisMinute) {echo ' selected="selected"';}
		echo '>'.$num.'</option>';
	}
	echo '</select>';
}

function purgeItem() { 
if(isset($_POST['secureAction'])) { //&&is_int($_POST['year'])&&is_int($_POST['month'])&&is_int($_POST['day'])

 if($_POST['act']==0) { $date=timeStamp(); mysql_query("DELETE FROM ".db('prefix')."$this->panelTable WHERE date < '$date'");
 echo '<p id="adminNotice">Successfully Purged '.ucfirst($this->panelTitle).'</p>'; $this->createList(); }
 
 elseif($_POST['act']==1) { $date=$_POST['purge_date_year'].$_POST['purge_date_month'].$_POST['purge_date_day'].$_POST['purge_date_hour'].$_POST['purge_date_minute'].'00'; mysql_query("DELETE FROM ".db('prefix')."$this->panelTitle WHERE date < '$date'");
 echo '<p id="adminNotice">Successfully Purged '.ucfirst($this->panelTitle).'</p>'; $this->createList(); }
 
 else { unset($_POST); $this->purgeItem(); }

} else {
echo "<form method=\"post\" action=\"?".db('query')."\" /><p>Purge ".ucfirst($this->panelTitle).": ";
echo formItem(array('type'=>'hidden','name'=> token_label(),'value'=>make_token() ) );
echo "\n<select name=\"act\"><option value=\"0\">From Now</option><option value=\"1\">From Selected Date</option></select></p>";
echo '<p>Selected Date: '; $this->posting_time('purge'); echo '</p>';

echo '<p>'.formItem(array('type'=>'submit','name'=>'secureAction','value'=>'Purge')).' <a href="?admin">Cancel</a></p></form>';
}

}

//Delete selected Items 
function deleteItem() {
if(isset($_POST['secureAction'])){
if(ereg($this->panelTitle,'(users|categories)')) { 
$prop=($this->panelTitle=="users") ? 'auth':'cat';
$query=($_POST['newLocation']=='deleteArticles') ? "DELETE FROM ".db('prefix')."content WHERE $prop='".$_POST['oldLocation']."'":"UPDATE ".db('prefix')."content SET $prop='".$_POST['newLocation']."' WHERE $prop='".$_POST['oldLocation']."'";
mysql_query($query);
mysql_query("DELETE FROM ".db('prefix')."$this->panelTitle WHERE id='".$_POST['oldLocation']."'");
}
else { for($i=0; $i<$_POST['total']; $i++) { mysql_query("DELETE FROM ".db('prefix')."$this->panelTable WHERE id='".$_POST[$i]."'"); } }
echo '<p id="adminNotice">Successfully Deleted '.ucfirst($this->panelTitle).'</p>';
$this->createList();

} else { 
$g=$_POST['total']; for($i=0;$i<$g;$i++){ if($_POST[$i]!='') $d[$i]=$_POST[$i]; }
$c=($this->qAction[3]) ? $this->qAction[3]: implode(".",$d);

echo "<form method=\"post\" action=\"?".db('query')."\" /><p>Are you sure you want to delete:"; $items=explode(".",$c);

//The process is different for users and categories, articles have to be moved/deleted with them
if(ereg($this->panelTitle,'(users|categories)')) { 
$s=mysql_query("SELECT * FROM ".db('prefix')."$this->panelTable WHERE id='".$this->qAction[3]."'"); 
$r=mysql_fetch_array($s); 
$itemName=($this->panelTitle=="users") ? $r['name']:$r['title'];

echo "<br /><input type=\"hidden\" name=\"oldLocation\" value=\"$r[id]\" /><strong>".convertForDb(array('type' => 'screen', 'text' => $itemName))."</strong><br />";
echo 'Redirect Articles to: <select name="newLocation"><option value="deleteArticles">Delete Articles</option>';
$s=mysql_query("SELECT * FROM ".db('prefix')."$this->panelTable WHERE id!='".$this->qAction[3]."'");
while ($r=mysql_fetch_array($s)) {
$name=($this->panelTitle=="users") ? $r['name']:$r['title'];
echo "<option value=\"$r[id]\">$name</option>";
}
echo "</select>";

//For everything else
} else {
for($i=0; $i<count($items); $i++) { 
$s=mysql_query("SELECT * FROM ".db('prefix')."$this->panelTable WHERE id='".$items[$i]."'"); 
$r=mysql_fetch_array($s); extract($r);

if($this->panelTitle=="articles") $itemName=$title;
if(ereg($this->panelTitle,'(users|pages|menus)')) $itemName=$name;
if($this->panelTitle=="categories") $itemName=$name;
if($this->panelTitle=="comments") $itemName="Comment By $name (".timeStamp($date,"M j, Y, g:ia").")";

echo "<br /><input type=\"hidden\" name=\"$i\" value=\"$id\" /><strong>".convertForDb(array('type' => 'screen', 'text' => $itemName))."</strong>"; }
}

echo '<br /><br />'.formItem(array('type'=>'hidden','name'=> token_label(),'value'=>make_token() ) ).formItem(array('type'=>'hidden','name'=>'total','value'=>count($items))).formItem(array('type'=>'submit','name'=>'secureAction','value'=>'Delete')).
' <a href="?admin">Cancel</a></p></form>';

}

}

//Change Approve/Publish status of items, then display list  
function publishItem() {
$r=mysql_fetch_array(mysql_query("SELECT * FROM ".db('prefix')."$this->panelTable WHERE id='".$this->qAction[3]."' LIMIT 1"));
extract($r);

$formerStatus=($this->panelTitle=="comments") ? $approved:$published;
$newStatus=($formerStatus==1) ? 0:1;

if($this->panelTitle=="comments") $updateStatus="UPDATE ".db('prefix')."$this->panelTable SET approved='$newStatus' WHERE id='".$this->qAction[3]."'";
else $updateStatus="UPDATE ".db('prefix')."$this->panelTable SET published='$newStatus' WHERE id='".$this->qAction[3]."'";
mysql_query($updateStatus); 

$this->createList();
}

}  ///End of Class

/* Center Content and Session Handling */
function content() {
global $qAction;
global $leap;
//Global JS Functions
js(); //if($qAction[0]=="admin")  

$n=0;
 while (2>$n) {
if (in_array($qAction[0], $leap->FuncTriggers, true)) {
$key=array_search($qAction[0], $leap->FuncTriggers);
$Function=$leap->FuncNames[$key];
$Function(); $n++;
}

 else { $_SERVER['QUERY_STRING']=_value('default-page'); $qAction=parseQuery(); } //looking at root or calling an undefined variable
 $n++;
 }
}

//Leap Admin Function
function leapAdmin() {
global $qAction;
//If the user has Admin privelages
if($_SESSION['validUser']==TRUE) {

//Admin Log - a notepad to allow admin communicate and save notes.
if(isset($_POST['alog'])) { $b=str_replace(array("'","\""), array("&;@", "&;#"), $_POST['data']); mysql_query("UPDATE ".db('prefix')."system SET data='".$b."' WHERE type='admin' AND name='log'"); }
$s=mysql_query("SELECT * FROM ".db('prefix')."system WHERE type='admin' AND name='log'");
$r=mysql_fetch_array($s);

$extra= (isAble('MANAGE_SYSTEM')) ? 'class="alogdata"':'class="alogdata" readonly="readonly"';
echo '<div class="logdiv"><form method="post" action="?admin" class="alog">'.formItem(array('type'=>'textarea','extra'=>$extra,'name'=>'data','value'=>str_replace(array("&;@", "&;#"), array("'","\""), $r['data'])));

if(isAble('MANAGE_SYSTEM')) echo '<br />'.formItem(array('type'=>'submit','name'=>'alog','value'=>'Update Log')).formItem(array('type'=>'reset','value'=>'Reset'));

echo '</form></div>';

$panel=new adminPanel;


echo '<h1 class="adminheader" title="Administration">Administration</h1>
<div class="admindiv" id="panel1">';

if (file_exists('install.php')) echo "<p id=\"needUpgrade\"><strong>The install.php file is still in your Leap folder.</strong> It is recommended that you <strong>delete the file off of your server</strong> to protect your website and security.</p>";
echo $check."\n\n<ul>";

if(isAble('MANAGE_SYSTEM')) echo '<li><a href="?admin.system.settings">Change System Settings</a></li>
<li><a href="?admin.system.files">Manage Files</a></li>';

if ($qAction[1]) echo '<li><a href="?admin">Return to Admin Home</a></li>';

echo '</ul>

<form action="?" method="post"><p>You are logged in as <a href="?admin.users.edit.'.$_SESSION['userId'].'">'.$_SESSION['userName'].'</a>. '.formItem(array('type'=>'submit','name'=>'login','value'=>'Logout')).'</p></form>

</div>';

echo $panel->panelContent();

//SHOW RECENT ACTIVITY
if (!$qAction[1]) {
$lastLogin=$_SESSION['lastLogin'];
$NumArticles=$NumComments=0;
echo '<h1 class="adminheader" title="Check out the recent activity on your site">Recent Activity</h1>
<div class="admindiv" id="panel3">';

if (isset($_SESSION['lastLogin'])) {
$articleQuery="SELECT * FROM ".db('prefix')."content WHERE published='1' AND type='article' AND date>='$lastLogin'";
$commentQuery="SELECT * FROM ".db('prefix')."comments WHERE date>='$lastLogin'";
$NumArticles=mysql_num_rows(mysql_query($articleQuery));
$NumComments=mysql_num_rows(mysql_query($commentQuery)); 
//*/
echo '<p>Your last last login was <strong>'.timeStamp($lastLogin,'m/d/y H:i').'</strong>.</p>

<p><a href="?admin.articles">'.$NumArticles.' new articles</a> and <a href="?admin.comments">'.$NumComments.' new comments</a> since your last login.</p>';
} else {
$articleQuery="SELECT * FROM ".db('prefix')."content WHERE published='1' AND type='article'";
$commentQuery="SELECT * FROM ".db('prefix')."comments";
$NumArticles=mysql_num_rows(mysql_query($articleQuery));
$NumComments=mysql_num_rows(mysql_query($commentQuery)); 

echo '<p>There are <a href="?admin.articles">'.$NumArticles.' active articles</a> with <a href="?admin.comments">'.$NumComments.' comments</a>.</p>';
}

echo '</div>';
} //*/

} else {

echo '<div class="login"><form action="?admin" method="post">
<p>You do not have authorization to access this page, please login.</p>
<p><label for="user">Email Address</label> '.formItem(array('type'=>'text','name'=>'email')).'</p>
<p><label for="pwd">Password</label> '.formItem(array('type'=>'password','name'=>'pwd')).'</p>
<p class="submit">'.formItem(array('type'=>'submit','name'=>'login','value'=>'Login')).'</p>
</form></div>';

}

} //End of Function

function rss() { //Valid RSS 2.0 Feeds
	$s=mysql_query("SELECT * FROM ".db('prefix')."content WHERE published='1' AND type='article' ORDER BY date DESC LIMIT 0, "._value('rss-num-articles'));
	header('Content-type: text/xml;'); //charset='._value('charset')
	$header = '<?xml version="1.0"  encoding="utf-8" ?><?xml-stylesheet type="text/css" href="'.db('website').'rss.css" ?>';
	$header .= '<rss version="2.0">';
    $header .= "\n<channel>";
    $header .= "\n<title>".str_replace("&","&amp;",_value('title'))."</title>";
    $header .= "\n<description>".str_replace("&","&amp;",_value('title'))."</description>";
    $header .= "\n<link>".db('website')."</link>";
    $header .= "\n<copyright>Copyright ".str_replace("&","&amp;",_value('title'))."</copyright>";
    $header .= "\n<generator>Leap CMS "._value('version')."</generator>";
    $footer = "\n</channel>";
    $footer .= "\n</rss>";
	echo $header;    
	while ($r = mysql_fetch_array($s)) {
		$link = db('website').'?article.'.$r['id'];
		$date = timeStamp($r['date'],"r");
		$desc = preg_replace("[\[(.*?)\]]","", stripslashes(strip_tags(convertForDb(array('type' => 'screen', 'text' => $r['body'])))));
    $desc = truncateText($desc,_value('list-char-limit'))._value('list-limit-symbol');
		$item  = "\n<item>";
		$item .= "\n<title>".str_replace("&","&amp;",convertForDb(array('type' => 'screen', 'text' => $r['title'])))."</title>";
		$item .= "\n<description>".str_replace("&","&amp;",$desc)."</description>";
		$item .= "\n<pubDate>$date</pubDate>";
		$item .= "\n<link>$link</link>";
		$item .= "\n<guid>$link</guid>";
		$item .= "\n</item>";
		echo $item;
	}
	echo $footer;
die;
}

// XSS CLEAN
$XSS_cache = array();
$ra1 = array('applet', 'body', 'bgsound', 'base', 'basefont', 'embed', 'frame', 'frameset', 'head', 'html',
             'id', 'iframe', 'ilayer', 'layer', 'link', 'meta', 'name', 'object', 'script', 'style', 'title', 'xml');
$ra2 = array('javascript', 'vbscript', 'expression', 'applet', 'meta', 'xml', 'blink', 'link', 'style', 'script',
            'embed', 'object', 'iframe', 'frame', 'frameset', 'ilayer', 'layer', 'bgsound', 'title', 'base',
            'onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy',
            'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint',
            'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick',
            'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged',
            'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave',
            'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus',
            'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup', 'onlayoutcomplete', 'onload',
            'onlosecapture', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover',
            'onmouseup', 'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange',
            'onreadystatechange', 'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit',
            'onrowsdelete', 'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart',
            'onstop', 'onsubmit', 'onunload');
$tagBlacklist = array_merge($ra1, $ra2);

//CLEANXSS
class InputFilter {
	var $tagsArray;
	var $attrArray;
	var $tagsMethod;
	var $attrMethod;
	var $xssAuto;
	var $tagBlacklist = array('applet', 'body', 'bgsound', 'base', 'basefont', 'embed', 'frame', 'frameset', 'head', 'html', 'id', 'iframe', 'ilayer', 'layer', 'link', 'meta', 'name', 'object', 'script', 'style', 'title', 'xml');
	var $attrBlacklist = array('action', 'background', 'codebase', 'dynsrc', 'lowsrc');
	function inputFilter($tagsArray = array(), $attrArray = array(), $tagsMethod = 0, $attrMethod = 0, $xssAuto = 1) {		
		for ($i = 0; $i < count($tagsArray); $i++) $tagsArray[$i] = strtolower($tagsArray[$i]);
		for ($i = 0; $i < count($attrArray); $i++) $attrArray[$i] = strtolower($attrArray[$i]);
		$this->tagsArray = (array) $tagsArray;
		$this->attrArray = (array) $attrArray;
		$this->tagsMethod = $tagsMethod;
		$this->attrMethod = $attrMethod;
		$this->xssAuto = $xssAuto;
	}
	function process($source) {
		if (is_array($source)) {
			foreach($source as $key => $value)
				if (is_string($value)) $source[$key] = $this->remove($this->decode($value));
			return $source;
		} else if (is_string($source)) {
			return $this->remove($this->decode($source));
		} else return $source;	
	}
	function remove($source) {
		$loopCounter=0;
		while($source != $this->filterTags($source)) {
			$source = $this->filterTags($source);
			$loopCounter++;
		}
		return $source;
	}	
	function filterTags($source) {
		$preTag = NULL;
		$postTag = $source;
		$tagOpen_start = strpos($source, '<');
		while($tagOpen_start !== FALSE) {
			$preTag .= substr($postTag, 0, $tagOpen_start);
			$postTag = substr($postTag, $tagOpen_start);
			$fromTagOpen = substr($postTag, 1);
			$tagOpen_end = strpos($fromTagOpen, '>');
			if ($tagOpen_end === false) break;
			$tagOpen_nested = strpos($fromTagOpen, '<');
			if (($tagOpen_nested !== false) && ($tagOpen_nested < $tagOpen_end)) {
				$preTag .= substr($postTag, 0, ($tagOpen_nested+1));
				$postTag = substr($postTag, ($tagOpen_nested+1));
				$tagOpen_start = strpos($postTag, '<');
				continue;
			} 
			$tagOpen_nested = (strpos($fromTagOpen, '<') + $tagOpen_start + 1);
			$currentTag = substr($fromTagOpen, 0, $tagOpen_end);
			$tagLength = strlen($currentTag);
			if (!$tagOpen_end) {
				$preTag .= $postTag;
				$tagOpen_start = strpos($postTag, '<');			
			}
			$tagLeft = $currentTag;
			$attrSet = array();
			$currentSpace = strpos($tagLeft, ' ');
			if (substr($currentTag, 0, 1) == "/") {
				$isCloseTag = TRUE;
				list($tagName) = explode(' ', $currentTag);
				$tagName = substr($tagName, 1);
			} else {
				$isCloseTag = FALSE;
				list($tagName) = explode(' ', $currentTag);
			}		
			if ((!preg_match("/^[a-z][a-z0-9]*$/i",$tagName)) || (!$tagName) || ((in_array(strtolower($tagName), $this->tagBlacklist)) && ($this->xssAuto))) { 				
				$postTag = substr($postTag, ($tagLength + 2));
				$tagOpen_start = strpos($postTag, '<');
				continue;
			}
			while ($currentSpace !== FALSE) {
				$fromSpace = substr($tagLeft, ($currentSpace+1));
				$nextSpace = strpos($fromSpace, ' ');
				$openQuotes = strpos($fromSpace, '"');
				$closeQuotes = strpos(substr($fromSpace, ($openQuotes+1)), '"') + $openQuotes + 1;
				if (strpos($fromSpace, '=') !== FALSE) {
					if (($openQuotes !== FALSE) && (strpos(substr($fromSpace, ($openQuotes+1)), '"') !== FALSE))
						$attr = substr($fromSpace, 0, ($closeQuotes+1));
					else $attr = substr($fromSpace, 0, $nextSpace);
				} else $attr = substr($fromSpace, 0, $nextSpace);
				if (!$attr) $attr = $fromSpace;
				$attrSet[] = $attr;
				$tagLeft = substr($fromSpace, strlen($attr));
				$currentSpace = strpos($tagLeft, ' ');
			}
			$tagFound = in_array(strtolower($tagName), $this->tagsArray);			
			if ((!$tagFound && $this->tagsMethod) || ($tagFound && !$this->tagsMethod)) {
				if (!$isCloseTag) {
					$attrSet = $this->filterAttr($attrSet);
					$preTag .= '<' . $tagName;
					for ($i = 0; $i < count($attrSet); $i++)
						$preTag .= ' ' . $attrSet[$i];
					if (strpos($fromTagOpen, "</" . $tagName)) $preTag .= '>';
					else $preTag .= ' />';
			    } else $preTag .= '</' . $tagName . '>';
			}
			$postTag = substr($postTag, ($tagLength + 2));
			$tagOpen_start = strpos($postTag, '<');			
		}
		$preTag .= $postTag;
		return $preTag;
	}
	function filterAttr($attrSet) {	
		$newSet = array();
		for ($i = 0; $i <count($attrSet); $i++) {
			if (!$attrSet[$i]) continue;
			$attrSubSet = explode('=', trim($attrSet[$i]));
			list($attrSubSet[0]) = explode(' ', $attrSubSet[0]);
			if ((!eregi("^[a-z]*$",$attrSubSet[0])) || (($this->xssAuto) && ((in_array(strtolower($attrSubSet[0]), $this->attrBlacklist)) || (substr($attrSubSet[0], 0, 2) == 'on')))) 
				continue;
			if ($attrSubSet[1]) {
				$attrSubSet[1] = str_replace('&#', '', $attrSubSet[1]);
				$attrSubSet[1] = preg_replace('/\s+/', '', $attrSubSet[1]);
				$attrSubSet[1] = str_replace('"', '', $attrSubSet[1]);
				if ((substr($attrSubSet[1], 0, 1) == "'") && (substr($attrSubSet[1], (strlen($attrSubSet[1]) - 1), 1) == "'"))
					$attrSubSet[1] = substr($attrSubSet[1], 1, (strlen($attrSubSet[1]) - 2));
				$attrSubSet[1] = stripslashes($attrSubSet[1]);
			}
			if (	((strpos(strtolower($attrSubSet[1]), 'expression') !== false) &&	(strtolower($attrSubSet[0]) == 'style')) ||
					(strpos(strtolower($attrSubSet[1]), 'javascript:') !== false) ||
					(strpos(strtolower($attrSubSet[1]), 'behaviour:') !== false) ||
					(strpos(strtolower($attrSubSet[1]), 'vbscript:') !== false) ||
					(strpos(strtolower($attrSubSet[1]), 'mocha:') !== false) ||
					(strpos(strtolower($attrSubSet[1]), 'livescript:') !== false) 
			) continue;
			$attrFound = in_array(strtolower($attrSubSet[0]), $this->attrArray);
			if ((!$attrFound && $this->attrMethod) || ($attrFound && !$this->attrMethod)) {
				if ($attrSubSet[1]) $newSet[] = $attrSubSet[0] . '="' . $attrSubSet[1] . '"';
				else if ($attrSubSet[1] == "0") $newSet[] = $attrSubSet[0] . '="0"';
				else $newSet[] = $attrSubSet[0] . '="' . $attrSubSet[0] . '"';
			}	
		}
		return $newSet;
	}
	function decode($source) {
		$source = html_entity_decode($source, ENT_QUOTES, "ISO-8859-1");
		$source = preg_replace('/&#(\d+);/me',"chr(\\1)", $source);
		$source = preg_replace('/&#x([a-f0-9]+);/mei',"chr(0x\\1)", $source);
		return $source;
	}
	function safeSQL($source, &$connection) {
		if (is_array($source)) {
			foreach($source as $key => $value)
				if (is_string($value)) $source[$key] = $this->quoteSmart($this->decode($value), $connection);
			return $source;
		} else if (is_string($source)) {
			if (is_string($source)) return $this->quoteSmart($this->decode($source), $connection);
		} else return $source;	
	}
	function quoteSmart($source, &$connection) {
		if (get_magic_quotes_gpc()) $source = stripslashes($source);
		$source = $this->escapeString($source, $connection);
		return $source;
	}
	function escapeString($string, &$connection) {
		if (version_compare(phpversion(),"4.3.0", "<")) mysql_escape_string($string);
		else mysql_real_escape_string($string);
		return $string;
	}
}


function js() { global $qAction;
if ($qAction[0]=='admin') { ?>
<script type="text/javascript">//<!-- QUICKTAGS.JS
var dictionaryUrl='http://www.answers.com/';var edButtons=new Array();var edLinks=new Array();var edOpenTags=new Array();function edButton(id,display,tagStart,tagEnd,access,open){this.id=id;this.display=display;this.tagStart=tagStart;this.tagEnd=tagEnd;this.access=access;this.open=open;}
edButtons.push(new edButton('ed_bold','B','<strong>','</strong>','b'));edButtons.push(new edButton('ed_italic','I','<em>','</em>','i'));edButtons.push(new edButton('ed_underline','U','<u>','</u>','u'));edButtons.push(new edButton('ed_link','Link','','</a>','a'));edButtons.push(new edButton('ed_img','IMG','','','m',-1));edButtons.push(new edButton('ed_h1','H1','<h1>','</h1>\n\n','1'));edButtons.push(new edButton('ed_h2','H2','<h2>','</h2>\n\n','2'));edButtons.push(new edButton('ed_h3','H3','<h3>','</h3>\n\n','3'));edButtons.push(new edButton('ed_p','P','<p>','</p>\n\n','p'));edButtons.push(new edButton('ed_br','BR','<br />\n','','br',-1));var extendedStart=edButtons.length;edButtons.push(new edButton('ed_ul','UL','<ul>\n','</ul>\n\n','u'));edButtons.push(new edButton('ed_ol','OL','<ol>\n','</ol>\n\n','o'));edButtons.push(new edButton('ed_li','LI','\t<li>','</li>\n','l'));edButtons.push(new edButton('ed_code','CODE','<code>','</code>','c'));edButtons.push(new edButton('ed_block','QUOTE','<blockquote>','</blockquote>','q'));edButtons.push(new edButton('ed_break','Break','[BREAK]','','bk',-1));edButtons.push(new edButton('ed_include','Include','[INCLUDE]','[/INCLUDE]','in'));function edLink(display,URL,newWin){this.display=display;this.URL=URL;if(!newWin){newWin=0;}
this.newWin=newWin;}
edLinks[edLinks.length]=new edLink('foo','bar');function edShowButton(which,button,i){if(button.access){var accesskey=' accesskey = "'+button.access+'"'}
else{var accesskey='';}
switch(button.id){case'ed_img':document.write('<input type="button" id="'+button.id+'_'+which+'" '+accesskey+' class="ed_button" onclick="edInsertImage(\''+which+'\');" value="'+button.display+'" />');break;case'ed_link':document.write('<input type="button" id="'+button.id+'_'+which+'" '+accesskey+' class="ed_button" onclick="edInsertLink(\''+which+'\', '+i+');" value="'+button.display+'" />');break;case'ed_ext_link':document.write('<input type="button" id="'+button.id+'_'+which+'" '+accesskey+' class="ed_button" onclick="edInsertExtLink(\''+which+'\', '+i+');" value="'+button.display+'" />');break;case'ed_footnote':document.write('<input type="button" id="'+button.id+'_'+which+'" '+accesskey+' class="ed_button" onclick="edInsertFootnote(\''+which+'\');" value="'+button.display+'" />');break;case'ed_via':document.write('<input type="button" id="'+button.id+'_'+which+'" '+accesskey+' class="ed_button" onclick="edInsertVia(\''+which+'\');" value="'+button.display+'" />');break;default:document.write('<input type="button" id="'+button.id+'_'+which+'" '+accesskey+' class="ed_button" onclick="edInsertTag(\''+which+'\', '+i+');" value="'+button.display+'"  />');break;}}
function edShowLinks(){var tempStr='<select onchange="edQuickLink(this.options[this.selectedIndex].value, this);"><option value="-1" selected>(Quick Links)</option>';for(i=0;i<edLinks.length;i++){tempStr+='<option value="'+i+'">'+edLinks[i].display+'</option>';}
tempStr+='</select>';document.write(tempStr);}
function edAddTag(which,button){if(edButtons[button].tagEnd!=''){edOpenTags[which][edOpenTags[which].length]=button;document.getElementById(edButtons[button].id+'_'+which).value='/'+document.getElementById(edButtons[button].id+'_'+which).value;}}
function edRemoveTag(which,button){for(i=0;i<edOpenTags[which].length;i++){if(edOpenTags[which][i]==button){edOpenTags[which].splice(i,1);document.getElementById(edButtons[button].id+'_'+which).value=document.getElementById(edButtons[button].id+'_'+which).value.replace('/','');}}}
function edCheckOpenTags(which,button){var tag=0;for(i=0;i<edOpenTags[which].length;i++){if(edOpenTags[which][i]==button){tag++;}}
if(tag>0){return true;}
else{return false;}}
function edCloseAllTags(which){var count=edOpenTags[which].length;for(o=0;o<count;o++){edInsertTag(which,edOpenTags[which][edOpenTags[which].length-1]);}}
function edQuickLink(i,thisSelect){if(i>-1){var newWin='';if(edLinks[i].newWin==1){newWin=' target="_blank"';}
var tempStr='<a href="'+edLinks[i].URL+'"'+newWin+'>'
+edLinks[i].display
+'</a>';thisSelect.selectedIndex=0;edInsertContent(edCanvas,tempStr);}
else{thisSelect.selectedIndex=0;}}
function edSpell(which){myField=document.getElementById(which);var word='';if(document.selection){myField.focus();var sel=document.selection.createRange();if(sel.text.length>0){word=sel.text;}}
else if(myField.selectionStart||myField.selectionStart=='0'){var startPos=myField.selectionStart;var endPos=myField.selectionEnd;if(startPos!=endPos){word=myField.value.substring(startPos,endPos);}}
if(word==''){word=prompt('Enter a word to look up:','');}
if(word!=''){window.open(dictionaryUrl+escape(word));}}
function edToolbar(which){document.write('<div id="ed_toolbar_'+which+'"><span>');for(i=0;i<extendedStart;i++){edShowButton(which,edButtons[i],i);}
if(edShowExtraCookie()){document.write('<input type="button" id="ed_close_'+which+'" class="ed_button" onclick="edCloseAllTags(\''+which+'\');" value="Close Tags" />'
+'<input type="button" id="ed_extra_show_'+which+'" class="ed_button" onclick="edShowExtra(\''+which+'\')" value="&raquo;" style="visibility: hidden;" />'
+'</span><br />'
+'<span id="ed_extra_buttons_'+which+'">'
+'<input type="button" id="ed_extra_hide_'+which+'" class="ed_button" onclick="edHideExtra(\''+which+'\');" value="&laquo;" />');}
else{document.write('<input type="button" id="ed_close_'+which+'" class="ed_button" onclick="edCloseAllTags(\''+which+'\');" value="Close Tags" />'
+'<input type="button" id="ed_extra_show_'+which+'" class="ed_button" onclick="edShowExtra(\''+which+'\')" value="&raquo;" />'
+'</span><br />'
+'<span id="ed_extra_buttons_'+which+'" style="display: none;">'
+'<input type="button" id="ed_extra_hide_'+which+'" class="ed_button" onclick="edHideExtra(\''+which+'\');" value="&laquo;" />');}
for(i=extendedStart;i<edButtons.length;i++){edShowButton(which,edButtons[i],i);}
document.write('</span>');document.write('</div>');edOpenTags[which]=new Array();}
function edShowExtra(which){document.getElementById('ed_extra_show_'+which).style.visibility='hidden';document.getElementById('ed_extra_buttons_'+which).style.display='block';edSetCookie('js_quicktags_extra','show',new Date("December 31, 2100"));}
function edHideExtra(which){document.getElementById('ed_extra_buttons_'+which).style.display='none';document.getElementById('ed_extra_show_'+which).style.visibility='visible';edSetCookie('js_quicktags_extra','hide',new Date("December 31, 2100"));}
function edInsertTag(which,i){myField=document.getElementById(which);if(document.selection){myField.focus();sel=document.selection.createRange();if(sel.text.length>0){sel.text=edButtons[i].tagStart+sel.text+edButtons[i].tagEnd;}
else{if(!edCheckOpenTags(which,i)||edButtons[i].tagEnd==''){sel.text=edButtons[i].tagStart;edAddTag(which,i);}
else{sel.text=edButtons[i].tagEnd;edRemoveTag(which,i);}}
myField.focus();}
else if(myField.selectionStart||myField.selectionStart=='0'){var startPos=myField.selectionStart;var endPos=myField.selectionEnd;var cursorPos=endPos;var scrollTop=myField.scrollTop;if(startPos!=endPos){myField.value=myField.value.substring(0,startPos)
+edButtons[i].tagStart
+myField.value.substring(startPos,endPos)
+edButtons[i].tagEnd
+myField.value.substring(endPos,myField.value.length);cursorPos+=edButtons[i].tagStart.length+edButtons[i].tagEnd.length;}
else{if(!edCheckOpenTags(which,i)||edButtons[i].tagEnd==''){myField.value=myField.value.substring(0,startPos)
+edButtons[i].tagStart
+myField.value.substring(endPos,myField.value.length);edAddTag(which,i);cursorPos=startPos+edButtons[i].tagStart.length;}
else{myField.value=myField.value.substring(0,startPos)
+edButtons[i].tagEnd
+myField.value.substring(endPos,myField.value.length);edRemoveTag(which,i);cursorPos=startPos+edButtons[i].tagEnd.length;}}
myField.focus();myField.selectionStart=cursorPos;myField.selectionEnd=cursorPos;myField.scrollTop=scrollTop;}
else{if(!edCheckOpenTags(which,i)||edButtons[i].tagEnd==''){myField.value+=edButtons[i].tagStart;edAddTag(which,i);}
else{myField.value+=edButtons[i].tagEnd;edRemoveTag(which,i);}
myField.focus();}}
function edInsertContent(which,myValue){myField=document.getElementById(which);if(document.selection){myField.focus();sel=document.selection.createRange();sel.text=myValue;myField.focus();}
else if(myField.selectionStart||myField.selectionStart=='0'){var startPos=myField.selectionStart;var endPos=myField.selectionEnd;var scrollTop=myField.scrollTop;myField.value=myField.value.substring(0,startPos)
+myValue
+myField.value.substring(endPos,myField.value.length);myField.focus();myField.selectionStart=startPos+myValue.length;myField.selectionEnd=startPos+myValue.length;myField.scrollTop=scrollTop;}else{myField.value+=myValue;myField.focus();}}
function edInsertLink(which,i,defaultValue){myField=document.getElementById(which);if(!defaultValue){defaultValue='http://';}
if(!edCheckOpenTags(which,i)){var URL=prompt('Enter the URL',defaultValue);if(URL){edButtons[i].tagStart='<a href="'+URL+'">';edInsertTag(which,i);}}
else{edInsertTag(which,i);}}
function edInsertExtLink(which,i,defaultValue){myField=document.getElementById(which);if(!defaultValue){defaultValue='http://';}
if(!edCheckOpenTags(which,i)){var URL=prompt('Enter the URL',defaultValue);if(URL){edButtons[i].tagStart='<a href="'+URL+'" rel="external">';edInsertTag(which,i);}}
else{edInsertTag(which,i);}}
function edInsertImage(which){myField=document.getElementById(which);var myValue=prompt('Enter the URL of the image','http://');if(myValue){myValue='<img src="'
+myValue
+'" alt="'+prompt('Enter a description of the image','')
+'" />';edInsertContent(which,myValue);}}
function edInsertFootnote(which){myField=document.getElementById(which);var note=prompt('Enter the footnote:','');if(!note||note==''){return false;}
var now=new Date;var fnId='fn'+now.getTime();var fnStart=myField.value.indexOf('<ol class="footnotes">');if(fnStart!=-1){var fnStr1=myField.value.substring(0,fnStart)
var fnStr2=myField.value.substring(fnStart,myField.value.length)
var count=countInstances(fnStr2,'<li id="')+1;}
else{var count=1;}
var count='<sup><a href="#'+fnId+'n" id="'+fnId+'" class="footnote">'+count+'</a></sup>';edInsertContent(which,count);if(fnStart!=-1){fnStr1=myField.value.substring(0,fnStart+count.length)
fnStr2=myField.value.substring(fnStart+count.length,myField.value.length)}
else{var fnStr1=myField.value;var fnStr2="\n\n"+'<ol class="footnotes">'+"\n"
+'</ol>'+"\n";}
var footnote=' <li id="'+fnId+'n">'+note+' [<a href="#'+fnId+'">back</a>]</li>'+"\n"
+'</ol>';myField.value=fnStr1+fnStr2.replace('</ol>',footnote);}
function countInstances(string,substr){var count=string.split(substr);return count.length-1;}
function edInsertVia(which){myField=document.getElementById(which);var myValue=prompt('Enter the URL of the source link','http://');if(myValue){myValue='(Thanks <a href="'+myValue+'" rel="external">'
+prompt('Enter the name of the source','')
+'</a>)';edInsertContent(which,myValue);}}
function edSetCookie(name,value,expires,path,domain){document.cookie=name+"="+escape(value)+
((expires)?"; expires="+expires.toGMTString():"")+
((path)?"; path="+path:"")+
((domain)?"; domain="+domain:"");}
function edShowExtraCookie(){var cookies=document.cookie.split(';');for(var i=0;i<cookies.length;i++){var cookieData=cookies[i];while(cookieData.charAt(0)==' '){cookieData=cookieData.substring(1,cookieData.length);}
if(cookieData.indexOf('js_quicktags_extra')==0){if(cookieData.substring(19,cookieData.length)=='show'){return true;}
else{return false;}}}
return false;};
//--></script>
<?php } } ?>
