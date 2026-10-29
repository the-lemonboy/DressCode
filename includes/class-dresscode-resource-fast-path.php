<?php
/** Fast, conservative renderer for the bundled PDP resource-list skill. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class DressCode_Resource_Fast_Path {
 public static function render( $html, $icon_base ) {
  if ( ! class_exists( 'DOMDocument' ) || preg_match( '/<!|<\?|<\s*(?:script|style)\b/i', $html ) ) { return false; }
  $doc = new DOMDocument('1.0','UTF-8');
  $prior = libxml_use_internal_errors(true);
  $ok = $doc->loadHTML('<?xml encoding="utf-8"?><div id="dresscode-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
  libxml_clear_errors(); libxml_use_internal_errors($prior);
  if (!$ok) { return false; }
  $root = $doc->getElementById('dresscode-root');
  if (!$root) { return false; }
  $nodes = self::elements($root); if ($nodes === false || count($nodes)!==1) { return false; }
  $outer = $nodes[0];
  if (strtolower($outer->tagName)==='div') {
   $nodes=self::elements($outer); if ($nodes===false || count($nodes)!==1 || strtolower($nodes[0]->tagName)!=='ul') { return false; }
   $ul=$nodes[0];
  } elseif (strtolower($outer->tagName)==='ul') {
   $ul=$outer; $outer=$doc->createElement('div'); $root->replaceChild($outer,$ul); $outer->appendChild($ul);
  } else { return false; }
  $items=self::elements($ul); if ($items===false || !$items) { return false; }
  $rows=array();
  foreach ($items as $li) {
   if (strtolower($li->tagName)!=='li') { return false; }
   $children=self::elements($li); if ($children===false) { return false; }
   if (!$children && ''===trim(str_replace("\xc2\xa0",'', $li->textContent))) { $rows[]=array($li,null,null); continue; }
   if (count($children)!==1) { return false; }
   $node=$children[0]; $strong=null;
   if (strtolower($node->tagName)==='strong') { $strong=$node; $children=self::elements($strong); if ($children===false || count($children)!==1) { return false; } $node=$children[0]; }
   if (strtolower($node->tagName)!=='a' || !$node->hasAttribute('href') || ''===trim($node->textContent)) { return false; }
   foreach ($node->childNodes as $child) { if ($child->nodeType!==XML_TEXT_NODE) { return false; } }
   $rows[]=array($li,$node,$strong);
  }
  $outer->setAttribute('style', "--bg:#fff;--ink:#333;--body:#666;--brand:#f24a00;--border:#dbdbdb;--font-sans:'IBM Plex Sans',-apple-system,'Segoe UI',Roboto,'PingFang SC','Microsoft YaHei',sans-serif;--font-mono:'IBM Plex Mono',Consolas,monospace;max-width:1290px;width:100%;box-sizing:border-box;margin:0 auto;padding:clamp(2rem,5vw,4rem) clamp(1rem,2vw,2rem);font-family:var(--font-sans);font-size:1rem;line-height:1.5;color:var(--body);background:var(--bg);-webkit-font-smoothing:antialiased");
  $ul->setAttribute('style','list-style-type:none;margin:0;padding:0;width:100%;border-top:1px solid var(--border)');
  foreach ($rows as $row) {
   list($li,$a,$strong)=$row; if (!$a) { $ul->removeChild($li); continue; }
   $li->setAttribute('style','list-style-type:none;margin:0;padding:0;border-bottom:1px solid var(--border);text-indent:0');
   if ($strong) { $strong->setAttribute('style','font-weight:500;color:inherit'); }
   $classes=preg_split('/\s+/',trim($a->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY); if (!in_array('dresscode-resource-link',$classes,true)) { $classes[]='dresscode-resource-link'; } $a->setAttribute('class',implode(' ',$classes));
   $a->setAttribute('style','display:flex;align-items:center;gap:0.875rem;padding:1rem 0;color:var(--ink);font-size:1rem;font-weight:500;line-height:1.5;text-decoration:none;overflow-wrap:anywhere');
   $label=$a->textContent; $href=strtolower($a->getAttribute('href')); $type='file';
   if (preg_match('/\b(video|youtube)\b|\.(mp4|webm)(?:[?#]|$)|(?:youtube\.com|youtu\.be)/i',$label.' '.$href)) { $type='video'; }
   elseif (preg_match('/\b(image|picture|photo)\b|\.(jpg|jpeg|png|svg|webp|gif)(?:[?#]|$)/i',$label.' '.$href)) { $type='image'; }
   elseif (preg_match('/\b(download|下载)\b|\.zip(?:[?#]|$)/i',$label.' '.$href)) { $type='download'; }
   while ($a->firstChild) { $a->removeChild($a->firstChild); }
   $a->appendChild(self::icon($doc,$icon_base.'/resource-'.$type.'.svg'));
   $span=$doc->createElement('span'); $span->setAttribute('style','flex:1 1 auto;min-width:0'); $span->appendChild($doc->createTextNode($label)); $a->appendChild($span);
   $a->appendChild(self::icon($doc,$icon_base.'/resource-arrow.svg'));
  }
  return $doc->saveHTML($outer);
 }
 private static function elements($parent) {
  $out=array(); foreach ($parent->childNodes as $node) {
   if ($node->nodeType===XML_ELEMENT_NODE) { $out[]=$node; }
   elseif ($node->nodeType!==XML_TEXT_NODE || trim(str_replace("\xc2\xa0",'', $node->nodeValue))!=='') { return false; }
  } return $out;
 }
 private static function icon($doc,$url) {
  $img=$doc->createElement('img'); foreach(array('src'=>$url,'alt'=>'','width'=>'20','height'=>'20','style'=>'width:20px;height:20px;max-width:none;flex:0 0 20px') as $k=>$v) { $img->setAttribute($k,$v); } return $img;
 }
}
