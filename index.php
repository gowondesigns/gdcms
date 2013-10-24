<?php session_start(); include("core.php"); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">

<head>

<?php title(); ?>

<meta name="Generator" content="Gowon Designs CMS 1" />

<link rel="stylesheet" type="text/css" href="style.css" media="screen" />

<link rel="alternate" type="application/rss+xml" title="RSS 2.0" href="?rss" />

</head>

<body>

<div class="outer-container">

<div class="inner-container">

	<div class="header">
		
		<div class="title">

			<span class="sitename"><a href="?"><?php value('title'); ?></a></span>

			<div class="slogan"><?php value('slogan'); ?></div>

		</div>
		
	</div>

	<div class="division">

     <span style="float: left;"><?php breadcrumbs(); ?></span>
     
     <?php search(); ?>

	</div>

	<div class="main">		
		
		<div class="content">

      <?php content(); ?>

		</div>

		<div class="sidebar">
    
      <div class="navigation">

  			<?php menu('main'); ?>

      </div>

		  <?php extra('sidebar'); ?>

		</div>

		<div class="clearer">&nbsp;</div>

	</div>

	<div class="footer">

		<span class="left">

		&copy; 2009 <a href="?"><?php value('title'); ?></a> &middot; Valid <a href="http://validator.w3.org/check?uri=referer">XHTML</a> &amp; <a href="http://jigsaw.w3.org/css-validator/check/referer">CSS</a> &middot; Powered by <a href="http://leap.gowondesigns.com/">GDCMS</a> <?php value('version'); ?>

		</span>

		<span class="right">

			<?php login(); ?>

		</span>

		<div class="clearer"></div>

	</div>

</div>

</div>

</body>

</html>
