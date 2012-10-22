<?php


$articles = array();



if(action() == 'xml' || action() == 'index')
{
	function urlSet($loc, $priority = false)
	{
		$priority = ($priority != false) 
					? $priority 
					: ( ($loc == "") ? 1 : 0.8 )
		;

		$url = '	
			<url>	
				<loc>http://'.$_SERVER['SERVER_NAME'].$loc.'</loc>
				<priority>'.$priority.'</priority>		
			</url>
		';	
		return $url;
	}

	layout(false);
	header("Content-type: text/xml; charset=utf-8");
	$xml =  '<?xml version="1.0" encoding="UTF-8"?>
	<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
	';

		$xml.= urlSet('');
		$xml.= urlSet('/index');
		
		$xml.= urlSet('/gallery');
		$xml.= urlSet('/products');
		$xml.= urlSet('/rules');
		
	
		$xml.= urlSet('/articles');
		if(!empty($articles)){ foreach($articles as $article)
		{	
			$xml.= urlSet('/article/'.$article['id']);	
		}}
		
		

	$xml.='</urlset>';
	exit($xml);
	
}