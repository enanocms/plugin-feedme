<?php
/*
Plugin Name: RSS Frontend
Plugin URI: http://enanocms.org/Feed_me
Description: Provides the page Special:RSS, which is used to generate RSS feeds of site and page content changes.
Author: Dan Fuhry
Version: 1.0
Author URI: http://enanocms.org/
*/

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0 release candidate 2
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
global $db, $session, $paths, $template, $plugins; // Common objects

$plugins->attachHook('base_classes_initted', '
  $paths->add_page(Array(
    \'name\'=>\'RSS Feed - Latest changes\',
    \'urlname\'=>\'RSS\',
    \'namespace\'=>\'Special\',
    \'special\'=>0,\'visible\'=>0,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
    ));
  ');

$plugins->attachHook('session_started', '__enanoRSSAttachHTMLHeaders();');

function __enanoRSSAttachHTMLHeaders()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  $template->add_header('<link rel="alternate" title="'.getConfig('site_name').' Changes feed" href="'.makeUrlNS('Special', 'RSS/recent', null, true).'" type="application/rss+xml" />');
  $template->add_header('<link rel="alternate" title="'.getConfig('site_name').' Comments feed" href="'.makeUrlNS('Special', 'RSS/comments', null, true).'" type="application/rss+xml" />');
}

define('ENANO_FEEDBURNER_INCLUDED', true);

/**
 * Class for easily generating RSS feeds.
 * @package Enano
 * @subpackage Feed Me
 * @license GNU General Public License <http://www.gnu.org/licenses/gpl.html>
 */
 
class RSS
{
  
  /**
   * List of channels contained in this feed.
   * @var array
   */
  
  var $channels = Array();
  
  /**
   * The channel that's currently being operated on
   * @var array
   */
  
  var $this_channel = Array();
  
  /**
   * GUID of the current channel
   * @var string
   */
  
  var $this_guid = '';
  
  /**
   * List of fully XML-formatted feed entries.
   * @var array
   */
  
  var $items = Array();
  
  /**
   * Constructor.
   * @param string Feed title
   * @param string Feed description
   * @param string Linkback
   * @param string Generator
   * @param string E-mail of webmaster
   */
  
  function __construct($title = false, $desc = false, $link = false, $gen = false, $email = false)
  {
    $this->create_channel($title, $desc, $link, $gen, $email);
  }
  
  /**
   * PHP 4 constructor.
   */
  
  function RSS($title = false, $desc = false, $link = false, $gen = false, $email = false)
  {
    $this->__construct($title, $desc, $link, $gen, $email);
  }
  
  /** 
   * Creates a new channel.
   */
  
  function create_channel($title = false, $desc = false, $link = false, $gen = false, $email = false)
  {
    if ( empty($title) )
      $title = 'Untitled feed';
    else
      $title = htmlspecialchars($title);
    if ( empty($desc) )
      $desc = 'Test feed';
    else
      $desc = htmlspecialchars($desc);
    if ( empty($link) )
      $link = 'http' . ( isset($_SERVER['HTTPS']) ? 's' : '' ) . '://'.$_SERVER['HTTP_HOST'] . scriptPath . '/';
    else
      $link = htmlspecialchars($link);
    if ( !empty($gen) )
      $gen = htmlspecialchars($gen);
    else
      $gen = 'Enano CMS ' . enano_version();
    if ( !empty($email) )
      $email = htmlspecialchars($email);
    else
      $email = getConfig('contact_email');
    
    $this->channels = Array();
    $guid = md5(microtime() . mt_rand());
    $this->channels[$guid] = Array(
        'title' => $title,
        'desc' => $desc,
        'link' => $link,
        'gen' => $gen,
        'email' => $email,
        'lang' => 'en-us',
        'items' => Array()
      );
    $this->this_channel =& $this->channels[$guid];
    $this->this_guid = $guid;
    return $guid;
  }
  
  /**
   * Selects a specific channel to add items to
   * @param string The GUID of the channel
   */
  
  function select_channel($guid)
  {
    if ( isset($this->channels[$guid]) )
    {
      $this->this_channel =& $this->channels[$guid];
      $this->guid = $guid;
    }
  }
  
  /**
   * Adds a news item.
   * @param string Title of the feed entry
   * @param string Link to where more information can be found
   * @param string Short description or content area
   * @param string Date the item was published. If this is a UNIX timestamp it will be formatted with date().
   * @param string A Globally-Unique Identifier (GUID) for this item. Doesn't have to be a 128-bit hash - usually it's a link. If one is not provided, one is generated based on the first three parameters.
   */
  
  function add_item($title, $link, $desc, $pubdate, $guid = false)
  {
    $title = htmlspecialchars($title);
    $link = htmlspecialchars($link);
    $desc = '<![CDATA[ ' . str_replace(']]>', ']]&gt;', $desc) . ']]>';
    if ( is_int($pubdate) || ( !is_int($pub_date) && preg_match('/^([0-9]+)$/', $pubdate) ) )
    {
      $pubdate = date('D, d M Y H:i:s T', intval($pubdate));
    }
    if ( !$guid )
    {
      $guid = md5 ( $title . $link . $desc . $pubdate );
      $sec1 = substr($guid, 0, 8);
      $sec2 = substr($guid, 8, 4);
      $sec3 = substr($guid, 12, 4);
      $sec4 = substr($guid, 16, 4);
      $sec5 = substr($guid, 20, 12);
      $guid = sprintf('%s-%s-%s-%s-%s', $sec1, $sec2, $sec3, $sec4, $sec5);
    }
    $xml = "    <item>
      <title>$title</title>
      <link>$link</link>
      <description>
        $desc
      </description>
      <pubDate>$pubdate</pubDate>
      <guid>$guid</guid>
    </item>";
    $this->this_channel['items'][] = $xml;
  }
  
  /**
   * Converts everything into the final RSS feed.
   * @param bool If true, XML headers ("<?xml version="1.0" encoding="utf-8" ?>") are included. Defaults to true.
   * @return string
   */
  
  function render($headers = true)
  {
    $xml = '';
    if ( $headers )
      // The weird quotes are because of a jEdit syntax highlighting bug
      $xml .= "<?xml version=".'"'."1.0".'"'." encoding=".'"'."utf-8".'"'." ?>\n";
    $xml .= "<rss version=".'"'."2.0".'"'.">\n";
    foreach ( $this->channels as $channel )
    {
      $xml .= "  <channel>
    <title>{$channel['title']}</title>
    <link>{$channel['link']}</link>
    <description>{$channel['desc']}</description>
    <generator>{$channel['gen']}</generator>
    <webMaster>{$channel['email']}</webMaster>
    <language>{$channel['lang']}</language>

";
      $content = implode("\n", $channel['items']) . "\n";
      $xml .= $content;
      $xml .= "  </channel>";
      $xml .= "
</rss>";
    }
    return $xml;
  }
  
}

// function names are IMPORTANT!!! The name pattern is: page_<namespace ID>_<page URLname, without namespace>

function page_Special_RSS()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  header('Content-type: text/xml; charset=windows-1252'); //application/rss+xml');
  global $aggressive_optimize_html;
  $aggressive_optimize_html = false;
  $session->sid_super = false;
  if ( $session->auth_level > USER_LEVEL_MEMBER )
    $session->auth_level = USER_LEVEL_MEMBER;
  $mode = $paths->getParam(0);
  $n = $paths->getParam(1);
  if(!preg_match('#^([0-9]+)$#', $n) || (int)$n > 50) $n = 20;
  else $n = (int)$n;
  switch($mode)
  {
    case "recent":
      $title = getConfig('site_name') . ' Recent Changes';
      $desc = getConfig('site_desc');
      
      $rss = new RSS($title, $desc);
      
      $q = $db->sql_query('SELECT * FROM '.table_prefix.'logs WHERE log_type=\'page\' ORDER BY time_id DESC LIMIT '.$n.';');
      if(!$q)
      {
        $rss->add_item('ERROR', '', 'Error selecting log data: ' . mysql_error() . '', time());
      }
      else
      {
        while($row = $db->fetchrow())
        {
          $link = makeUrlComplete($row['namespace'], $row['page_id'], "oldid={$row['time_id']}"); // makeUrlComplete($row['namespace'], $row['page_id']);
          $title = $paths->pages[$paths->nslist[$row['namespace']].$row['page_id']]['name'];
          $desc = "Change by {$row['author']}:<br />";
          $desc .= ( $row['edit_summary'] != '' ) ? $row['edit_summary'] : 'No edit summary given.';
          $date = $row['time_id'];
          $guid = false;
          
          $rss->add_item($title, $link, $desc, $date, $guid);
        }
      }
      
      echo $rss->render();
      break;
    case "comments":
      $title = getConfig('site_name') . ' Latest Comments';
      $desc = getConfig('site_desc');
      
      $rss = new RSS($title, $desc);
      
      $q = $db->sql_query('SELECT * FROM '.table_prefix.'comments ORDER BY time DESC LIMIT '.$n.';');
        
      if(!$q)
      {
        $rss->add_item('ERROR', '', 'Error selecting log data: ' . mysql_error() . '', time());
      }
      else
      {
        $n = $db->numrows();
        //echo '<!-- Number of rows: '.$n.' -->'; // ."\n<!-- SQL backtrace: \n\n".$db->sql_backtrace().' -->';
        for ( $j = 0; $j < $n; $j++ )
        {
          $row = $db->fetchrow($q);
          if(!is_array($row)) die(__FILE__.':'.__LINE__.' $row is not an array');
          $link = 'http' . ( isset($_SERVER['HTTPS']) ? 's' : '' ) . '://'.$_SERVER['HTTP_HOST'] . makeUrlNS($row['namespace'], $row['page_id']).'#comments';
          $page = ( isset($paths->pages[$paths->nslist[$row['namespace']].$row['page_id']]) ) ? $paths->pages[$paths->nslist[$row['namespace']].$row['page_id']]['name'] : $paths->nslist[$row['namespace']].$row['page_id'];
          $title = $row['subject'] . ': Posted on page "'.$page.'" by user '.$row['name'];
          $desc = RenderMan::render($row['comment_data']);
          $date = $row['time'];
          $guid = 'http' . ( isset($_SERVER['HTTPS']) ? 's' : '' ) . '://'.$_SERVER['HTTP_HOST'] . makeUrlNS($row['namespace'], $row['page_id']).'?do=comments&amp;comment='.$row['time'];
          
          $rss->add_item($title, $link, $desc, $date, $guid);
        }
      }
      echo $rss->render();
      break;
    default:
      $code = $plugins->setHook('feed_me_request');
      foreach ( $code as $cmd )
      {
        eval($cmd);
      }
      break;
  }
}

?>