<?php
require_once __DIR__ . "/db.php";
requireLogin();
$user = getCurrentUser();

// ─── RSS SOURCES ───────────────────────────────────────────────────────────
$SOURCES = [
    'all'       => ['label' => 'Tất cả',       'icon' => '🗞️',  'color' => '#3b5bdb'],
    'vnexpress' => ['label' => 'VnExpress',    'icon' => '🔵',  'color' => '#0066cc', 'feeds' => [
        'Trang chủ'    => 'https://vnexpress.net/rss/tin-moi-nhat.rss',
        'Thời sự'      => 'https://vnexpress.net/rss/thoi-su.rss',
        'Thế giới'     => 'https://vnexpress.net/rss/the-gioi.rss',
        'Kinh doanh'   => 'https://vnexpress.net/rss/kinh-doanh.rss',
        'Giáo dục'     => 'https://vnexpress.net/rss/giao-duc.rss',
        'Khoa học'     => 'https://vnexpress.net/rss/khoa-hoc.rss',
        'Công nghệ'    => 'https://vnexpress.net/rss/so-hoa.rss',
    ]],
    'tuoitre'   => ['label' => 'Tuổi Trẻ',     'icon' => '🟠',  'color' => '#e65c00', 'feeds' => [
        'Trang chủ'    => 'https://tuoitre.vn/rss/tin-moi-nhat.rss',
        'Thời sự'      => 'https://tuoitre.vn/rss/thoi-su.rss',
        'Thế giới'     => 'https://tuoitre.vn/rss/the-gioi.rss',
        'Kinh tế'      => 'https://tuoitre.vn/rss/kinh-te.rss',
        'Giáo dục'     => 'https://tuoitre.vn/rss/giao-duc.rss',
        'Nhịp sống trẻ'=> 'https://tuoitre.vn/rss/nhip-song-tre.rss',
        'Công nghệ'    => 'https://tuoitre.vn/rss/cong-nghe.rss',
    ]],
    'thanhnien' => ['label' => 'Thanh Niên',   'icon' => '🟢',  'color' => '#007a3d', 'feeds' => [
        'Trang chủ'    => 'https://thanhnien.vn/rss/home.rss',
        'Thời sự'      => 'https://thanhnien.vn/rss/thoi-su.rss',
        'Thế giới'     => 'https://thanhnien.vn/rss/the-gioi.rss',
        'Kinh tế'      => 'https://thanhnien.vn/rss/kinh-te.rss',
        'Giáo dục'     => 'https://thanhnien.vn/rss/giao-duc.rss',
        'Công nghệ'    => 'https://thanhnien.vn/rss/cong-nghe.rss',
        'Giải trí'     => 'https://thanhnien.vn/rss/giai-tri.rss',
    ]],
    'zingnews'  => ['label' => 'Zing News',    'icon' => '🔴',  'color' => '#cc0000', 'feeds' => [
        'Trang chủ'    => 'https://zingnews.vn/timeline-tin-moi-nhat.atom',
        'Thời sự'      => 'https://zingnews.vn/xa-hoi.atom',
        'Thế giới'     => 'https://zingnews.vn/the-gioi.atom',
        'Kinh tế'      => 'https://zingnews.vn/kinh-doanh.atom',
        'Giải trí'     => 'https://zingnews.vn/giai-tri.atom',
        'Công nghệ'    => 'https://zingnews.vn/cong-nghe.atom',
    ]],
    'dantri'    => ['label' => 'Dân Trí',      'icon' => '🟣',  'color' => '#7b2d8b', 'feeds' => [
        'Trang chủ'    => 'https://dantri.com.vn/rss/home.rss',
        'Xã hội'       => 'https://dantri.com.vn/rss/xa-hoi.rss',
        'Thế giới'     => 'https://dantri.com.vn/rss/the-gioi.rss',
        'Kinh doanh'   => 'https://dantri.com.vn/rss/kinh-doanh.rss',
        'Giáo dục'     => 'https://dantri.com.vn/rss/giao-duc-khuyen-hoc.rss',
        'Giải trí'     => 'https://dantri.com.vn/rss/giai-tri.rss',
    ]],
    'vietnamnet'=> ['label' => 'VietnamNet',   'icon' => '⚫',  'color' => '#1a1a2e', 'feeds' => [
        'Trang chủ'    => 'https://vietnamnet.vn/rss/home.rss',
        'Thời sự'      => 'https://vietnamnet.vn/rss/thoi-su.rss',
        'Thế giới'     => 'https://vietnamnet.vn/rss/the-gioi.rss',
        'Kinh doanh'   => 'https://vietnamnet.vn/rss/kinh-doanh.rss',
        'Giáo dục'     => 'https://vietnamnet.vn/rss/giao-duc.rss',
    ]],
    'nhandan'   => ['label' => 'Nhân Dân',     'icon' => '🔶',  'color' => '#c0392b', 'feeds' => [
        'Trang chủ'    => 'https://nhandan.vn/rss/home.rss',
        'Chính trị'    => 'https://nhandan.vn/rss/chinhtri.rss',
        'Kinh tế'      => 'https://nhandan.vn/rss/kinhte.rss',
        'Thế giới'     => 'https://nhandan.vn/rss/thegioi.rss',
        'Xã hội'       => 'https://nhandan.vn/rss/xahoi.rss',
    ]],
];

// ─── AJAX: Fetch RSS ───────────────────────────────────────────────────────
if (isset($_GET['fetch'])) {
    header('Content-Type: application/json; charset=utf-8');
    $src    = $_GET['src']  ?? 'vnexpress';
    $cat    = $_GET['cat']  ?? null;
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;

    $items = [];

    if ($src === 'all') {
        // Lấy feed đầu tiên của mỗi nguồn
        foreach ($SOURCES as $key => $info) {
            if ($key === 'all') continue;
            if (!isset($info['feeds'])) continue;
            $feedUrl = reset($info['feeds']);
            $fetched = fetchRSS($feedUrl, 8, $key, $info);
            $items = array_merge($items, $fetched);
        }
        // Sắp xếp theo thời gian
        usort($items, fn($a, $b) => ($b['ts'] ?? 0) - ($a['ts'] ?? 0));
    } else {
        $info = $SOURCES[$src] ?? null;
        if ($info && isset($info['feeds'])) {
            if ($cat && isset($info['feeds'][$cat])) {
                $feedUrl = $info['feeds'][$cat];
                $items = fetchRSS($feedUrl, 50, $src, $info);
            } else {
                // Default: feed đầu tiên
                $feedUrl = reset($info['feeds']);
                $items = fetchRSS($feedUrl, 50, $src, $info);
            }
        }
    }

    $total  = count($items);
    $offset = ($page - 1) * $perPage;
    $paged  = array_slice($items, $offset, $perPage);

    echo json_encode([
        'items'    => $paged,
        'total'    => $total,
        'page'     => $page,
        'perPage'  => $perPage,
        'hasMore'  => ($offset + $perPage) < $total,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Helper: Fetch & Parse RSS/Atom ───────────────────────────────────────
function fetchRSS($url, $limit = 30, $srcKey = '', $srcInfo = []) {
    $ctx = stream_context_create([
        'http' => [
            'timeout'       => 8,
            'user_agent'    => 'Mozilla/5.0 (compatible; MindSpark/1.0)',
            'ignore_errors' => true,
        ],
        'ssl'  => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return [];

    $raw = mb_convert_encoding($raw, 'UTF-8', 'auto');
    libxml_use_internal_errors(true);
    $xml = @simplexml_load_string($raw);
    if (!$xml) return [];

    $items   = [];
    $ns      = $xml->getNamespaces(true);
    $isAtom  = isset($ns['']) && strpos($ns[''], 'Atom') !== false
                || $xml->getName() === 'feed';

    if ($isAtom) {
        // Atom format (Zing)
        $entries = $xml->entry ?? [];
        foreach ($entries as $e) {
            if (count($items) >= $limit) break;
            $link = '';
            foreach ($e->link as $l) {
                $attrs = $l->attributes();
                if ((string)($attrs['rel'] ?? '') !== 'self') {
                    $link = (string)($attrs['href'] ?? '');
                    break;
                }
            }
            $desc = '';
            if (isset($e->summary))  $desc = (string)$e->summary;
            if (isset($e->content))  $desc = (string)$e->content;
            $desc = strip_tags($desc);
            $desc = html_entity_decode($desc, ENT_QUOTES, 'UTF-8');
            $desc = trim(preg_replace('/\s+/', ' ', $desc));

            $pub   = (string)($e->published ?? $e->updated ?? '');
            $ts    = $pub ? strtotime($pub) : 0;
            $thumb = extractThumb((string)$e->content . (string)$e->summary);

            $items[] = buildItem(
                html_entity_decode((string)$e->title, ENT_QUOTES, 'UTF-8'),
                $link, $desc, $ts, $thumb, $srcKey, $srcInfo
            );
        }
    } else {
        // RSS format
        $channel = $xml->channel ?? $xml;
        $entries = $channel->item ?? [];
        foreach ($entries as $e) {
            if (count($items) >= $limit) break;
            $link  = (string)($e->link ?? '');
            $desc  = (string)($e->description ?? '');
            // Try media:content for thumbnail
            $thumb = '';
            foreach ($ns as $prefix => $nsUri) {
                try {
                    $media = $e->children($nsUri);
                    if (isset($media->content)) {
                        $ma = $media->content->attributes();
                        if (!empty((string)$ma['url'])) { $thumb = (string)$ma['url']; break; }
                    }
                    if (isset($media->thumbnail)) {
                        $ma = $media->thumbnail->attributes();
                        if (!empty((string)$ma['url'])) { $thumb = (string)$ma['url']; break; }
                    }
                } catch (\Exception $ex) {}
            }
            if (!$thumb) $thumb = extractThumb($desc);
            $desc  = strip_tags($desc);
            $desc  = html_entity_decode($desc, ENT_QUOTES, 'UTF-8');
            $desc  = trim(preg_replace('/\s+/', ' ', $desc));

            $pubDate = (string)($e->pubDate ?? $e->published ?? '');
            $ts      = $pubDate ? strtotime($pubDate) : 0;

            $items[] = buildItem(
                html_entity_decode((string)$e->title, ENT_QUOTES, 'UTF-8'),
                $link, $desc, $ts, $thumb, $srcKey, $srcInfo
            );
        }
    }
    return $items;
}

function extractThumb($html) {
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
        $src = $m[1];
        if (strpos($src, 'http') === 0 && !strpos($src, 'pixel') && !strpos($src, '1x1')) {
            return $src;
        }
    }
    return '';
}

function buildItem($title, $link, $desc, $ts, $thumb, $srcKey, $srcInfo) {
    return [
        'title'   => $title,
        'link'    => $link,
        'desc'    => mb_substr($desc, 0, 200, 'UTF-8'),
        'ts'      => $ts ?: time(),
        'time'    => $ts ? timeAgoNews($ts) : 'Vừa xong',
        'thumb'   => $thumb,
        'source'  => $srcInfo['label'] ?? $srcKey,
        'srcKey'  => $srcKey,
        'color'   => $srcInfo['color'] ?? '#3b5bdb',
        'icon'    => $srcInfo['icon']  ?? '📰',
    ];
}

function timeAgoNews($ts) {
    $diff = time() - $ts;
    if ($diff < 0)     return 'Vừa đăng';
    if ($diff < 60)    return $diff . 'g trước';
    if ($diff < 3600)  return floor($diff/60)  . ' phút trước';
    if ($diff < 86400) return floor($diff/3600) . ' giờ trước';
    if ($diff < 604800)return floor($diff/86400).' ngày trước';
    return date('d/m/Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đọc Tin Tức — MindSpark</title>
<link rel="stylesheet" href="style.css">
<style>
/* ─── NEWS PAGE STYLES ──────────────────────────────── */
.news-layout {
  display: grid;
  grid-template-columns: 240px 1fr;
  gap: 20px;
  align-items: start;
  max-width: 1280px;
  margin: 0 auto;
  padding: 20px 20px 80px;
}

/* Sidebar */
.news-sidebar {
  position: sticky;
  top: 76px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
}
.news-sidebar-header {
  padding: 14px 16px 10px;
  border-bottom: 1px solid var(--border);
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--muted);
}
.src-item {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 9px 14px;
  cursor: pointer;
  transition: background .13s;
  border-left: 3px solid transparent;
  user-select: none;
}
.src-item:hover { background: var(--surface2); }
.src-item.active {
  background: var(--accent-soft);
  border-left-color: var(--accent);
}
.src-item.active .src-label { color: var(--accent); font-weight: 700; }
.src-icon { font-size: 15px; flex-shrink: 0; }
.src-label { font-size: 12.5px; font-weight: 600; color: var(--text2); }
.src-item.active .src-label { color: var(--accent); }

.src-cats {
  overflow: hidden;
  max-height: 0;
  transition: max-height .25s ease;
}
.src-cats.open { max-height: 300px; }
.src-cat-item {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 7px 14px 7px 36px;
  font-size: 12px;
  color: var(--muted);
  cursor: pointer;
  transition: background .12s, color .12s;
}
.src-cat-item:hover { background: var(--surface2); color: var(--text2); }
.src-cat-item.active { color: var(--accent); font-weight: 600; background: var(--accent-soft); }
.src-cat-dot {
  width: 5px; height: 5px; border-radius: 50%;
  background: currentColor; flex-shrink: 0;
}
.src-divider { height: 1px; background: var(--border); margin: 4px 0; }

/* Main content */
.news-main {}

/* Toolbar */
.news-toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.news-title {
  font-size: 18px;
  font-weight: 800;
  color: var(--text);
  letter-spacing: -0.5px;
  flex: 1;
  display: flex;
  align-items: center;
  gap: 8px;
}
.news-title-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 20px;
  background: var(--accent-soft);
  color: var(--accent);
  letter-spacing: .3px;
}
.news-search-wrap {
  position: relative;
  flex: 0 0 220px;
}
.news-search {
  width: 100%;
  padding: 8px 12px 8px 34px;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: var(--surface2);
  color: var(--text);
  font-size: 13px;
  font-family: var(--font);
  outline: none;
  transition: border .15s;
}
.news-search:focus { border-color: var(--accent); background: var(--surface); }
.news-search-icon {
  position: absolute;
  left: 10px; top: 50%;
  transform: translateY(-50%);
  width: 14px; height: 14px;
  stroke: var(--muted); fill: none; stroke-width: 2;
}
.view-toggle {
  display: flex;
  gap: 2px;
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 9px;
  padding: 3px;
}
.view-btn {
  width: 28px; height: 28px;
  border-radius: 6px;
  border: none;
  background: none;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  color: var(--muted);
  transition: background .13s, color .13s;
}
.view-btn.active { background: var(--accent); color: #fff; }
.view-btn svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* Cards Grid */
.news-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
}
.news-grid.list-view {
  grid-template-columns: 1fr;
  gap: 8px;
}

/* News Card */
.news-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
  transition: transform .15s, box-shadow .15s, border-color .15s;
  cursor: pointer;
  text-decoration: none;
  display: flex;
  flex-direction: column;
}
.news-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-lg);
  border-color: var(--border2);
}
.news-card-thumb {
  width: 100%;
  aspect-ratio: 16/9;
  object-fit: cover;
  background: var(--surface2);
  display: block;
  flex-shrink: 0;
}
.news-card-thumb-placeholder {
  width: 100%;
  aspect-ratio: 16/9;
  background: var(--surface2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;
  flex-shrink: 0;
}
.news-card-body {
  padding: 12px 14px 14px;
  display: flex;
  flex-direction: column;
  flex: 1;
}
.news-card-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 7px;
}
.news-card-source {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .5px;
  padding: 2px 7px;
  border-radius: 20px;
  color: #fff;
  flex-shrink: 0;
}
.news-card-time {
  font-size: 11px;
  color: var(--muted);
  flex: 1;
}
.news-card-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--text);
  line-height: 1.45;
  margin-bottom: 6px;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.news-card-desc {
  font-size: 11.5px;
  color: var(--muted);
  line-height: 1.55;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  flex: 1;
}
.news-card-footer {
  margin-top: 10px;
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: var(--muted);
}
.news-card-read {
  margin-left: auto;
  font-size: 10.5px;
  font-weight: 700;
  color: var(--accent);
  text-transform: uppercase;
  letter-spacing: .3px;
  white-space: nowrap;
}

/* List View Card */
.news-grid.list-view .news-card {
  flex-direction: row;
  min-height: 90px;
  align-items: stretch;
}
.news-grid.list-view .news-card-thumb {
  width: 130px;
  height: 90px;
  aspect-ratio: unset;
  flex-shrink: 0;
}
.news-grid.list-view .news-card-thumb-placeholder {
  width: 130px;
  height: 90px;
  aspect-ratio: unset;
  font-size: 24px;
  flex-shrink: 0;
}
.news-grid.list-view .news-card-body {
  padding: 10px 14px;
}
.news-grid.list-view .news-card-title {
  -webkit-line-clamp: 2;
  font-size: 13px;
}
.news-grid.list-view .news-card-desc {
  -webkit-line-clamp: 1;
}

/* States */
.news-loading {
  grid-column: 1 / -1;
  text-align: center;
  padding: 40px 0;
  color: var(--muted);
  font-size: 14px;
}
.news-empty {
  grid-column: 1 / -1;
  text-align: center;
  padding: 60px 20px;
}
.news-empty-icon { font-size: 48px; margin-bottom: 12px; }
.news-empty-title { font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 6px; }
.news-empty-sub   { font-size: 13px; color: var(--muted); }

/* Skeleton */
.skel {
  background: linear-gradient(90deg, var(--surface2) 25%, var(--border) 50%, var(--surface2) 75%);
  background-size: 200% 100%;
  animation: skel-anim 1.4s infinite;
  border-radius: 8px;
}
@keyframes skel-anim { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
.skel-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
}
.skel-img  { height: 140px; }
.skel-line { height: 12px; margin: 10px 14px 0; }
.skel-line.short { width: 60%; }

/* Load More */
.load-more-wrap {
  grid-column: 1 / -1;
  text-align: center;
  padding: 16px 0 4px;
}
.btn-load-more {
  padding: 10px 28px;
  border-radius: 10px;
  border: 1.5px solid var(--accent);
  background: var(--accent-soft);
  color: var(--accent);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all .15s;
  font-family: var(--font);
}
.btn-load-more:hover { background: var(--accent); color: #fff; }
.btn-load-more:disabled { opacity: .5; cursor: default; }

/* Refresh & Trend */
.news-toolbar-right {
  display: flex;
  align-items: center;
  gap: 8px;
}
.btn-refresh {
  padding: 7px 12px;
  border-radius: 9px;
  border: 1px solid var(--border);
  background: var(--surface2);
  color: var(--muted);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  display: flex; align-items: center; gap: 5px;
  transition: all .15s;
  font-family: var(--font);
}
.btn-refresh:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }
.btn-refresh svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; transition: transform .4s; }
.btn-refresh.spinning svg { animation: spin .6s linear; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Featured - first card large */
.news-featured { grid-column: 1 / -1; }
.news-grid .news-featured .news-card-thumb,
.news-grid .news-featured .news-card-thumb-placeholder {
  aspect-ratio: 3/1;
  max-height: 280px;
}
.news-grid .news-featured .news-card-title {
  font-size: 16px;
  -webkit-line-clamp: 2;
}
.news-grid .news-featured .news-card-desc {
  -webkit-line-clamp: 3;
}

/* Trending Strip */
.trending-strip {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 14px 16px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  overflow: hidden;
}
.trending-label {
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--muted);
  white-space: nowrap;
  flex-shrink: 0;
}
.trending-items {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  scrollbar-width: none;
  flex: 1;
}
.trending-items::-webkit-scrollbar { display: none; }
.trending-tag {
  white-space: nowrap;
  padding: 4px 11px;
  border-radius: 20px;
  border: 1px solid var(--border);
  font-size: 12px;
  font-weight: 600;
  color: var(--text2);
  cursor: pointer;
  transition: all .13s;
  flex-shrink: 0;
  background: var(--surface2);
}
.trending-tag:hover { background: var(--accent-soft); border-color: var(--accent); color: var(--accent); }

/* Modal overlay */
.news-modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.55);
  z-index: 999;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(4px);
}
.news-modal-overlay.open { display: flex; }
.news-modal {
  background: var(--surface);
  border-radius: 20px;
  width: 90%;
  max-width: 620px;
  max-height: 85vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0,0,0,.35);
  animation: modal-in .2s cubic-bezier(.34,1.56,.64,1);
}
@keyframes modal-in { from { transform: scale(.9) translateY(20px); opacity: 0; } to { transform: none; opacity: 1; } }
.news-modal-header {
  padding: 18px 20px 16px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
.news-modal-title {
  flex: 1;
  font-size: 15px;
  font-weight: 700;
  color: var(--text);
  line-height: 1.4;
}
.news-modal-close {
  width: 30px; height: 30px;
  border: none; background: var(--surface2);
  border-radius: 8px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; color: var(--muted);
  transition: background .13s;
}
.news-modal-close:hover { background: var(--border2); }
.news-modal-close svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2.5; }
.news-modal-body {
  padding: 20px;
  overflow-y: auto;
  flex: 1;
}
.news-modal-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.news-modal-source-badge {
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .5px;
  padding: 3px 10px;
  border-radius: 20px;
  color: #fff;
}
.news-modal-time {
  font-size: 12px;
  color: var(--muted);
}
.news-modal-thumb {
  width: 100%;
  border-radius: 12px;
  margin-bottom: 16px;
  display: block;
}
.news-modal-desc {
  font-size: 14px;
  color: var(--text2);
  line-height: 1.7;
  margin-bottom: 20px;
}
.news-modal-actions {
  display: flex;
  gap: 8px;
}
.btn-open-news {
  flex: 1;
  padding: 11px 20px;
  border-radius: 10px;
  border: none;
  background: var(--accent);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  text-decoration: none;
  text-align: center;
  transition: background .15s;
  font-family: var(--font);
}
.btn-open-news:hover { background: var(--accent-hover); }
.btn-share-news {
  padding: 11px 16px;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: var(--surface2);
  color: var(--text2);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all .15s;
  display: flex; align-items: center; gap: 6px;
  font-family: var(--font);
}
.btn-share-news:hover { border-color: var(--accent); color: var(--accent); }
.btn-share-news svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; }

/* Mobile responsive */
@media (max-width: 900px) {
  .news-layout {
    grid-template-columns: 1fr;
    padding: 12px 12px 90px;
  }
  .news-sidebar {
    position: static;
    display: flex;
    flex-direction: column;
  }
  .news-sidebar-source-list {
    display: flex;
    overflow-x: auto;
    scrollbar-width: none;
    flex-direction: row;
    padding: 6px 10px;
    gap: 4px;
  }
  .news-sidebar-source-list::-webkit-scrollbar { display: none; }
  .src-item {
    flex-shrink: 0;
    border-radius: 8px;
    padding: 7px 12px;
    border-left: none;
    border-bottom: 2.5px solid transparent;
    white-space: nowrap;
  }
  .src-item.active {
    border-left: none;
    border-bottom-color: var(--accent);
  }
  .src-cats { display: none; }
  .news-sidebar-header { display: none; }
  .news-grid { grid-template-columns: 1fr 1fr; }
  .news-featured { grid-column: 1 / -1; }
}
@media (max-width: 600px) {
  .news-grid { grid-template-columns: 1fr; }
  .news-modal { width: 95%; max-height: 90vh; }
}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="news-layout">

  <!-- ─── SIDEBAR ─────────────────────────────────── -->
  <aside class="news-sidebar">
    <div class="news-sidebar-header">Nguồn tin</div>
    <div class="news-sidebar-source-list" id="sourceList">

      <!-- All -->
      <div class="src-item active" data-src="all" onclick="selectSource(this)">
        <span class="src-icon">🗞️</span>
        <span class="src-label">Tất cả</span>
      </div>
      <div class="src-divider"></div>

      <?php foreach ($SOURCES as $key => $info):
        if ($key === 'all') continue; ?>
      <div>
        <div class="src-item" data-src="<?= $key ?>" onclick="selectSource(this)">
          <span class="src-icon"><?= $info['icon'] ?></span>
          <span class="src-label"><?= $info['label'] ?></span>
        </div>
        <div class="src-cats" id="cats-<?= $key ?>">
          <?php foreach ($info['feeds'] as $catName => $catUrl): ?>
          <div class="src-cat-item" data-src="<?= $key ?>" data-cat="<?= htmlspecialchars($catName) ?>"
               onclick="selectCat(this)">
            <span class="src-cat-dot"></span>
            <?= htmlspecialchars($catName) ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>

    </div>
  </aside>

  <!-- ─── MAIN ─────────────────────────────────────── -->
  <main class="news-main">

    <!-- Trending -->
    <div class="trending-strip" id="trendingStrip">
      <span class="trending-label">🔥 Hot</span>
      <div class="trending-items" id="trendingItems">
        <span class="trending-tag" onclick="searchNews('AI')">AI & Công nghệ</span>
        <span class="trending-tag" onclick="searchNews('kinh tế')">Kinh tế</span>
        <span class="trending-tag" onclick="searchNews('giáo dục')">Giáo dục</span>
        <span class="trending-tag" onclick="searchNews('thế giới')">Thế giới</span>
        <span class="trending-tag" onclick="searchNews('chứng khoán')">Chứng khoán</span>
        <span class="trending-tag" onclick="searchNews('sức khỏe')">Sức khỏe</span>
        <span class="trending-tag" onclick="searchNews('bóng đá')">Bóng đá</span>
        <span class="trending-tag" onclick="searchNews('du học')">Du học</span>
        <span class="trending-tag" onclick="searchNews('tuyển sinh')">Tuyển sinh</span>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="news-toolbar">
      <div class="news-title">
        <span id="currentTitle">🗞️ Tất cả báo</span>
        <span class="news-title-badge" id="countBadge">…</span>
      </div>
      <div class="news-toolbar-right">
        <div class="news-search-wrap">
          <svg class="news-search-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input class="news-search" type="text" placeholder="Tìm kiếm tin tức…" id="searchInput"
                 oninput="handleSearch(this.value)" autocomplete="off">
        </div>
        <button class="btn-refresh" id="refreshBtn" onclick="refresh()">
          <svg viewBox="0 0 24 24"><polyline points="23,4 23,10 17,10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          Làm mới
        </button>
        <div class="view-toggle">
          <button class="view-btn active" id="gridBtn" onclick="setView('grid')" title="Dạng lưới">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
          </button>
          <button class="view-btn" id="listBtn" onclick="setView('list')" title="Dạng danh sách">
            <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
          </button>
        </div>
      </div>
    </div>

    <!-- News Grid -->
    <div class="news-grid" id="newsGrid">
      <!-- skeleton on load -->
      <?php for ($i = 0; $i < 6; $i++): ?>
      <div class="skel-card">
        <div class="skel skel-img"></div>
        <div class="skel skel-line" style="width:90%"></div>
        <div class="skel skel-line" style="width:75%"></div>
        <div class="skel skel-line short" style="margin-bottom:14px"></div>
      </div>
      <?php endfor; ?>
    </div>

  </main>
</div>

<!-- ─── MODAL ──────────────────────────────────────── -->
<div class="news-modal-overlay" id="newsModal" onclick="closeModal(event)">
  <div class="news-modal">
    <div class="news-modal-header">
      <div class="news-modal-title" id="modalTitle">…</div>
      <button class="news-modal-close" onclick="closeModalBtn()">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="news-modal-body">
      <div class="news-modal-meta">
        <span class="news-modal-source-badge" id="modalBadge">…</span>
        <span class="news-modal-time" id="modalTime">…</span>
      </div>
      <img id="modalThumb" class="news-modal-thumb" src="" alt="" style="display:none;" onerror="this.style.display='none'">
      <p class="news-modal-desc" id="modalDesc">…</p>
      <div class="news-modal-actions">
        <a href="#" class="btn-open-news" id="modalLink" target="_blank" rel="noopener">
          Đọc bài đầy đủ →
        </a>
        <button class="btn-share-news" onclick="shareNews()">
          <svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
          Chia sẻ
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// ─── STATE ─────────────────────────────────────────
let state = {
  src: 'all',
  cat: null,
  page: 1,
  loading: false,
  allItems: [],
  filteredItems: [],
  view: 'grid',
  searchQ: '',
};
let searchTimer = null;
let currentModal = null;

// ─── SOURCES CONFIG ──────────────────────────────
const SOURCES = <?php
  $out = [];
  foreach ($SOURCES as $k => $v) {
    if ($k === 'all') { $out[$k] = ['label'=>'Tất cả báo','icon'=>'🗞️','color'=>'#3b5bdb']; continue; }
    $out[$k] = ['label'=>$v['label'],'icon'=>$v['icon'],'color'=>$v['color'],'cats'=>array_keys($v['feeds'])];
  }
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
?>;

// ─── INIT ────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  loadNews();
});

// ─── LOAD NEWS ───────────────────────────────────
async function loadNews(reset = true) {
  if (state.loading) return;
  state.loading = true;

  if (reset) {
    state.page = 1;
    state.allItems = [];
    showSkeletons();
  }

  const params = new URLSearchParams({
    fetch: 1,
    src: state.src,
    page: state.page,
  });
  if (state.cat) params.set('cat', state.cat);

  try {
    const res  = await fetch('news.php?' + params);
    const data = await res.json();

    if (reset) {
      state.allItems = data.items;
    } else {
      state.allItems = [...state.allItems, ...data.items];
    }

    state.hasMore = data.hasMore;
    state.page    = data.page + 1;

    applySearch();
    updateBadge(data.total);
  } catch (e) {
    showError();
  } finally {
    state.loading = false;
    document.getElementById('refreshBtn').classList.remove('spinning');
  }
}

// ─── RENDER ──────────────────────────────────────
function renderItems(items, append = false) {
  const grid = document.getElementById('newsGrid');
  if (!append) grid.innerHTML = '';

  if (items.length === 0 && !append) {
    grid.innerHTML = `
      <div class="news-empty">
        <div class="news-empty-icon">📭</div>
        <div class="news-empty-title">Không tìm thấy tin tức</div>
        <div class="news-empty-sub">Thử tìm từ khóa khác hoặc chọn nguồn khác.</div>
      </div>`;
    return;
  }

  items.forEach((item, idx) => {
    const el = createCard(item, idx === 0 && !append && state.view === 'grid' && !state.searchQ);
    grid.appendChild(el);
  });

  // Load more button
  const existing = document.getElementById('loadMoreWrap');
  if (existing) existing.remove();

  if (state.hasMore) {
    const wrap = document.createElement('div');
    wrap.className = 'load-more-wrap';
    wrap.id = 'loadMoreWrap';
    wrap.innerHTML = `<button class="btn-load-more" onclick="loadMore()">Tải thêm tin tức…</button>`;
    grid.appendChild(wrap);
  }
}

function createCard(item, featured = false) {
  const a = document.createElement('a');
  a.href = 'javascript:void(0)';
  a.className = 'news-card' + (featured ? ' news-featured' : '');
  a.addEventListener('click', () => openModal(item));

  const srcInfo  = SOURCES[item.srcKey] || {};
  const color    = item.color || srcInfo.color || '#3b5bdb';
  const thumbHtml = item.thumb
    ? `<img class="news-card-thumb" src="${escHtml(item.thumb)}" alt="" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
      + `<div class="news-card-thumb-placeholder" style="display:none">${item.icon || '📰'}</div>`
    : `<div class="news-card-thumb-placeholder">${item.icon || '📰'}</div>`;

  a.innerHTML = `
    ${thumbHtml}
    <div class="news-card-body">
      <div class="news-card-meta">
        <span class="news-card-source" style="background:${color}">${escHtml(item.source)}</span>
        <span class="news-card-time">${escHtml(item.time)}</span>
      </div>
      <div class="news-card-title">${escHtml(item.title)}</div>
      ${item.desc ? `<div class="news-card-desc">${escHtml(item.desc)}</div>` : ''}
      <div class="news-card-footer">
        <span class="news-card-read">Đọc →</span>
      </div>
    </div>`;
  return a;
}

// ─── SEARCH ──────────────────────────────────────
function handleSearch(val) {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    state.searchQ = val.trim().toLowerCase();
    applySearch();
  }, 300);
}

function applySearch() {
  const grid = document.getElementById('newsGrid');
  grid.className = 'news-grid' + (state.view === 'list' ? ' list-view' : '');

  let items = state.allItems;
  if (state.searchQ) {
    items = items.filter(it =>
      it.title.toLowerCase().includes(state.searchQ) ||
      (it.desc||'').toLowerCase().includes(state.searchQ)
    );
  }
  state.filteredItems = items;
  renderItems(items);
}

function searchNews(q) {
  document.getElementById('searchInput').value = q;
  state.searchQ = q.toLowerCase();
  applySearch();
}

// ─── SELECT SOURCE ───────────────────────────────
function selectSource(el) {
  // Deactivate all
  document.querySelectorAll('.src-item').forEach(e => e.classList.remove('active'));
  document.querySelectorAll('.src-cats').forEach(e => e.classList.remove('open'));
  document.querySelectorAll('.src-cat-item').forEach(e => e.classList.remove('active'));

  el.classList.add('active');
  state.src = el.dataset.src;
  state.cat = null;
  document.getElementById('searchInput').value = '';
  state.searchQ = '';

  // Open cats
  const catsEl = document.getElementById('cats-' + state.src);
  if (catsEl) catsEl.classList.add('open');

  // Title
  const info = SOURCES[state.src] || {};
  document.getElementById('currentTitle').textContent = (info.icon||'🗞️') + ' ' + (info.label || state.src);

  loadNews();
}

function selectCat(el) {
  document.querySelectorAll('.src-cat-item').forEach(e => e.classList.remove('active'));
  el.classList.add('active');
  state.src = el.dataset.src;
  state.cat = el.dataset.cat;
  document.getElementById('searchInput').value = '';
  state.searchQ = '';

  const info = SOURCES[state.src] || {};
  document.getElementById('currentTitle').textContent = (info.icon||'📰') + ' ' + (info.label||state.src) + ' › ' + el.dataset.cat;

  loadNews();
}

// ─── LOAD MORE ───────────────────────────────────
async function loadMore() {
  const btn = document.querySelector('.btn-load-more');
  if (btn) { btn.disabled = true; btn.textContent = 'Đang tải…'; }
  await loadNews(false);
}

// ─── REFRESH ─────────────────────────────────────
function refresh() {
  document.getElementById('refreshBtn').classList.add('spinning');
  loadNews();
}

// ─── VIEW TOGGLE ─────────────────────────────────
function setView(v) {
  state.view = v;
  document.getElementById('gridBtn').classList.toggle('active', v === 'grid');
  document.getElementById('listBtn').classList.toggle('active', v === 'list');
  applySearch();
}

// ─── MODAL ───────────────────────────────────────
function openModal(item) {
  currentModal = item;
  document.getElementById('modalTitle').textContent = item.title;
  document.getElementById('modalBadge').textContent = item.source;
  document.getElementById('modalBadge').style.background = item.color || '#3b5bdb';
  document.getElementById('modalTime').textContent = item.time;
  document.getElementById('modalDesc').textContent = item.desc || 'Nhấn "Đọc bài đầy đủ" để xem chi tiết.';
  document.getElementById('modalLink').href = item.link;

  const img = document.getElementById('modalThumb');
  if (item.thumb) {
    img.src = item.thumb;
    img.style.display = 'block';
  } else {
    img.style.display = 'none';
  }
  document.getElementById('newsModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal(e) {
  if (e.target === document.getElementById('newsModal')) closeModalBtn();
}
function closeModalBtn() {
  document.getElementById('newsModal').classList.remove('open');
  document.body.style.overflow = '';
}
function shareNews() {
  if (!currentModal) return;
  if (navigator.share) {
    navigator.share({ title: currentModal.title, url: currentModal.link });
  } else {
    navigator.clipboard.writeText(currentModal.link).then(() => alert('Đã copy link!'));
  }
}
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeModalBtn();
});

// ─── HELPERS ─────────────────────────────────────
function showSkeletons() {
  const grid = document.getElementById('newsGrid');
  grid.className = 'news-grid' + (state.view === 'list' ? ' list-view' : '');
  grid.innerHTML = Array(6).fill('').map(() => `
    <div class="skel-card">
      <div class="skel skel-img"></div>
      <div class="skel skel-line" style="width:90%"></div>
      <div class="skel skel-line" style="width:75%"></div>
      <div class="skel skel-line short" style="margin-bottom:14px"></div>
    </div>`).join('');
}

function showError() {
  document.getElementById('newsGrid').innerHTML = `
    <div class="news-empty" style="grid-column:1/-1">
      <div class="news-empty-icon">⚠️</div>
      <div class="news-empty-title">Không thể tải tin tức</div>
      <div class="news-empty-sub">Kiểm tra kết nối mạng và thử lại.</div>
    </div>`;
}

function updateBadge(total) {
  document.getElementById('countBadge').textContent = total + ' tin';
}

function escHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
</script>
</body>
</html>
