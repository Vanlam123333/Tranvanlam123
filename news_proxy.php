<?php
/**
 * news_proxy.php — RSS proxy + Article reader cho MindSpark
 * action=rss  → fetch & parse RSS feed
 * action=article → fetch full article content
 */
require_once __DIR__ . "/db.php";
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$PROXY_LIST = [
    '103.77.242.102:60000:proxy:proxy',
    '103.77.242.102:60001:proxy:proxy',
    '103.77.242.102:60002:proxy:proxy',
    '103.77.242.102:60003:proxy:proxy',
    '103.77.242.102:60004:proxy:proxy',
];

$ALLOWED_DOMAINS = [
    'vnexpress.net','tuoitre.vn','thanhnien.vn',
    'znews.vn','zingnews.vn','dantri.com.vn',
    'vietnamnet.vn','nhandan.vn',
];

$action = $_GET['action'] ?? 'rss';

// ── cURL fetch ──────────────────────────────────────────
function curlFetch($url, $proxyList, $timeout = 12) {
    if (!function_exists('curl_init')) return false;
    shuffle($proxyList);
    foreach ($proxyList as $p) {
        $parts = explode(':', $p);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => ['Accept-Language: vi-VN,vi;q=0.9','Accept: text/html,application/xhtml+xml,*/*'],
            CURLOPT_PROXY          => ($parts[0].':'.$parts[1]),
            CURLOPT_PROXYTYPE      => CURLPROXY_SOCKS5_HOSTNAME,
            CURLOPT_PROXYUSERPWD   => (isset($parts[2]) ? $parts[2].':'.$parts[3] : ''),
        ]);
        $raw  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw && $code >= 200 && $code < 400 && strlen($raw) > 100) return $raw;
    }
    // fallback direct
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,
        CURLOPT_FOLLOWLOCATION=>true,CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_ENCODING=>'',CURLOPT_USERAGENT=>'Mozilla/5.0',
    ]);
    $raw = curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    return ($raw && $code<400) ? $raw : false;
}

function domainAllowed($url, $list) {
    $h = parse_url($url, PHP_URL_HOST) ?: '';
    foreach ($list as $d) if ($h===$d || str_ends_with($h,'.'.$d)) return true;
    return false;
}

// ════════════════════════════════════════════════
// ACTION: RSS
// ════════════════════════════════════════════════
if ($action === 'rss') {
    $rssUrl = trim($_GET['url'] ?? '');
    $srcKey = trim($_GET['src'] ?? '');
    if (!$rssUrl || !domainAllowed($rssUrl, $ALLOWED_DOMAINS)) {
        echo json_encode(['ok'=>false,'error'=>'Invalid URL']); exit;
    }

    $raw = curlFetch($rssUrl, $PROXY_LIST);
    if (!$raw) { echo json_encode(['ok'=>false,'error'=>'Fetch failed']); exit; }

    $raw = mb_convert_encoding(ltrim($raw,"\xEF\xBB\xBF"), 'UTF-8', 'auto');
    libxml_use_internal_errors(true);
    $xml = @simplexml_load_string($raw);
    if (!$xml) { echo json_encode(['ok'=>false,'error'=>'XML parse failed']); exit; }

    $items = [];
    $ns    = $xml->getNamespaces(true);
    $isAtom = ($xml->getName() === 'feed');

    if ($isAtom) {
        foreach ($xml->entry as $e) {
            if (count($items)>=50) break;
            $link='';
            foreach($e->link as $l){$a=$l->attributes();if((string)($a['rel']??'')!=='self'){$link=(string)($a['href']??'');if($link)break;}}
            $desc=strip_tags((string)($e->summary??$e->content??''));
            $pub=(string)($e->published??$e->updated??'');
            $thumb=_extractThumb((string)($e->content??$e->summary??''));
            $items[]=_item((string)$e->title,$link,$desc,$thumb,$pub);
        }
    } else {
        $ch=$xml->channel??$xml;
        foreach($ch->item as $e){
            if(count($items)>=50)break;
            $link=(string)($e->link??'');
            if(!$link){$ln=$e->getElementsByTagName('link')[0]??null;$link=$ln?(($ln->textContent)?:($ln->getAttribute('href')??'')):'';} 
            $desc=(string)($e->description??'');
            $thumb='';
            foreach($ns as $pfx=>$nsUri){try{$m=$e->children($nsUri);
                if(isset($m->thumbnail)){$a=$m->thumbnail->attributes();$u=(string)($a['url']??'');if($u){$thumb=$u;break;}}
                if(isset($m->content)){$a=$m->content->attributes();$u=(string)($a['url']??'');if($u){$thumb=$u;break;}}
            }catch(\Exception $ex){}}
            if(!$thumb)$thumb=_extractThumb($desc);
            $pub=(string)($e->pubDate??$e->published??'');
            $items[]=_item((string)$e->title,$link,strip_tags($desc),$thumb,$pub);
        }
    }
    $items=array_values(array_filter($items,fn($i)=>!empty($i['title'])&&!empty($i['link'])));
    echo json_encode(['ok'=>true,'items'=>$items],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

// ════════════════════════════════════════════════
// ACTION: ARTICLE (fetch full content)
// ════════════════════════════════════════════════
if ($action === 'article') {
    $url = trim($_GET['url'] ?? '');
    if (!$url || !domainAllowed($url, $ALLOWED_DOMAINS)) {
        echo json_encode(['ok'=>false,'error'=>'Invalid URL']); exit;
    }

    $html = curlFetch($url, $PROXY_LIST, 15);
    if (!$html) { echo json_encode(['ok'=>false,'error'=>'Cannot fetch article']); exit; }

    $html = mb_convert_encoding($html,'UTF-8','auto');
    libxml_use_internal_errors(true);
    $doc  = new DOMDocument('1.0','UTF-8');
    @$doc->loadHTML('<?xml encoding="UTF-8">'.$html);

    // ── Extract meta info ──
    $title = '';
    $desc  = '';
    $thumb = '';
    $author= '';
    $pub   = '';

    foreach($doc->getElementsByTagName('meta') as $m){
        $prop = $m->getAttribute('property')?: $m->getAttribute('name');
        $val  = $m->getAttribute('content');
        if(!$val) continue;
        if(in_array($prop,['og:title','twitter:title']) && !$title) $title=$val;
        if(in_array($prop,['og:description','description','twitter:description']) && !$desc) $desc=$val;
        if(in_array($prop,['og:image','twitter:image']) && !$thumb) $thumb=$val;
        if(in_array($prop,['author','article:author']) && !$author) $author=$val;
        if($prop==='article:published_time' && !$pub) $pub=$val;
    }
    if(!$title){
        $tNodes=$doc->getElementsByTagName('title');
        if($tNodes->length>0) $title=$tNodes->item(0)->textContent;
    }

    // ── Extract article body ──
    // Try selectors in priority order (each newspaper has different class)
    $selectors = [
        // VnExpress
        'article-content','sidebar-1',
        // Tuoi Tre
        'detail-content','detail__content',
        // Thanh Nien
        'detail-content-body',
        // Zing / Znews
        'the-article-body','article__body',
        // Dan Tri
        'singular-content','detail-content-body',
        // VietnamNet
        'content-detail','maincontent',
        // Nhan Dan
        'detail-content',
        // Generic fallbacks
        'article-body','article__content','entry-content',
        'post-content','content-body','newsContent',
        'article','main',
    ];

    $bodyHtml = '';
    $xpath = new DOMXPath($doc);

    foreach($selectors as $sel){
        // Try by id
        $node = $doc->getElementById($sel);
        if(!$node){
            // Try by class
            $nodes = $xpath->query("//*[contains(concat(' ',normalize-space(@class),' '),' $sel ')]");
            if($nodes && $nodes->length>0) $node=$nodes->item(0);
        }
        if($node && strlen($node->textContent)>200){
            $bodyHtml = _innerHtml($node);
            break;
        }
    }

    // Clean up body
    $bodyHtml = _cleanArticleHtml($bodyHtml, $url);
    $plainText = _htmlToPlainText($bodyHtml);

    echo json_encode([
        'ok'     => true,
        'title'  => _clean($title),
        'desc'   => _clean($desc),
        'thumb'  => $thumb,
        'author' => _clean($author),
        'pub'    => $pub,
        'body'   => $bodyHtml,   // HTML for rich display
        'text'   => mb_substr($plainText,0,5000), // plain text fallback
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode(['ok'=>false,'error'=>'Unknown action']);

// ── Helpers ──────────────────────────────────────────
function _item($title,$link,$desc,$thumb,$pub){
    $ts=$pub?strtotime($pub):time();
    return[
        'title' =>_clean($title),
        'link'  =>trim($link),
        'desc'  =>mb_substr(trim(preg_replace('/\s+/',' ',html_entity_decode($desc,ENT_QUOTES,'UTF-8'))),0,220),
        'thumb' =>$thumb,
        'pubDate'=>$pub,
        'ts'    =>$ts?:time(),
    ];
}
function _clean($s){return html_entity_decode(trim($s),ENT_QUOTES,'UTF-8');}
function _extractThumb($html){
    if(preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i',$html,$m)){
        $s=$m[1];
        if(str_starts_with($s,'http')&&!str_contains($s,'1x1')&&!str_contains($s,'pixel'))return $s;
    }
    return '';
}
function _innerHtml(DOMNode $node){
    $d=new DOMDocument('1.0','UTF-8');
    foreach($node->childNodes as $c) $d->appendChild($d->importNode($c,true));
    return $d->saveHTML();
}
function _cleanArticleHtml($html,$baseUrl){
    if(!$html) return '';
    // Remove scripts, styles, ads, social buttons
    $html=preg_replace('/<script[^>]*>.*?<\/script>/si','',$html);
    $html=preg_replace('/<style[^>]*>.*?<\/style>/si','',$html);
    $html=preg_replace('/<iframe[^>]*>.*?<\/iframe>/si','',$html);
    $html=preg_replace('/<noscript[^>]*>.*?<\/noscript>/si','',$html);
    // Fix relative image URLs
    $base=parse_url($baseUrl,PHP_URL_SCHEME).'://'.parse_url($baseUrl,PHP_URL_HOST);
    $html=preg_replace_callback('/<img([^>]+)>/i',function($m)use($base){
        $tag=$m[1];
        // Convert data-src to src for lazy loaded images
        if(preg_match('/data-src=["\']([^"\']+)["\']/i',$tag,$ds)){
            $tag=preg_replace('/src=["\'][^"\']*["\']/i','',$tag);
            $tag.=' src="'.$ds[1].'"';
        }
        // Fix relative src
        if(preg_match('/src=["\']([^"\']+)["\']/i',$tag,$s)){
            if(str_starts_with($s[1],'/')){
                $tag=str_replace($s[1],$base.$s[1],$tag);
            }
        }
        return '<img'.$tag.'>';
    },$html);
    // Remove empty tags
    $html=preg_replace('/<p[^>]*>\s*<\/p>/i','',$html);
    return trim($html);
}
function _htmlToPlainText($html){
    $html=preg_replace('/<br\s*\/?>/i',"\n",$html);
    $html=preg_replace('/<\/p>/i',"\n\n",$html);
    $html=strip_tags($html);
    return trim(preg_replace('/\n{3,}/','  ',$html));
}
