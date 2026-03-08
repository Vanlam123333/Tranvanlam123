<?php
require_once __DIR__ . "/db.php";
requireLogin();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đọc Tin Tức — MindSpark</title>
<link rel="stylesheet" href="style.css">
<style>
/* ════ LAYOUT ════ */
.news-wrap{display:grid;grid-template-columns:230px 1fr;gap:18px;max-width:1300px;margin:0 auto;padding:18px 18px 90px;align-items:start}

/* ════ SIDEBAR ════ */
.news-sidebar{position:sticky;top:74px;background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;max-height:calc(100vh - 90px);overflow-y:auto;scrollbar-width:thin}
.news-sidebar::-webkit-scrollbar{width:3px}
.news-sidebar::-webkit-scrollbar-thumb{background:var(--border2);border-radius:99px}
.sidebar-hdr{padding:12px 14px 8px;font-size:9.5px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border)}
.src-row{display:flex;align-items:center;gap:9px;padding:9px 14px;cursor:pointer;border-left:3px solid transparent;transition:background .12s;user-select:none}
.src-row:hover{background:var(--surface2)}
.src-row.active{background:var(--accent-soft);border-left-color:var(--accent)}
.src-row.active .src-name{color:var(--accent);font-weight:700}
.src-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.src-name{font-size:13px;font-weight:500;color:var(--text2)}
.src-arrow{margin-left:auto;width:12px;height:12px;stroke:var(--muted);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;transition:transform .2s;flex-shrink:0}
.src-arrow.open{transform:rotate(180deg)}
.src-divider{height:1px;background:var(--border);margin:3px 0}
.cat-list{overflow:hidden;max-height:0;transition:max-height .25s ease}
.cat-list.open{max-height:400px}
.cat-row{display:flex;align-items:center;gap:7px;padding:7px 14px 7px 32px;font-size:12px;color:var(--muted);cursor:pointer;transition:background .12s,color .12s}
.cat-row:hover{background:var(--surface2);color:var(--text2)}
.cat-row.active{color:var(--accent);font-weight:600;background:var(--accent-soft)}
.cat-dot{width:4px;height:4px;border-radius:50%;background:currentColor;flex-shrink:0}

/* ════ TRENDING ════ */
.trending-bar{display:flex;align-items:center;gap:10px;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:10px 14px;margin-bottom:14px;overflow:hidden}
.trending-lbl{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);white-space:nowrap;flex-shrink:0}
.trending-scroll{display:flex;gap:6px;overflow-x:auto;scrollbar-width:none;flex:1}
.trending-scroll::-webkit-scrollbar{display:none}
.t-tag{white-space:nowrap;padding:4px 11px;border-radius:20px;border:1px solid var(--border);background:var(--surface2);font-size:12px;font-weight:600;color:var(--text2);cursor:pointer;flex-shrink:0;transition:all .13s}
.t-tag:hover{background:var(--accent-soft);border-color:var(--accent);color:var(--accent)}

/* ════ TOOLBAR ════ */
.news-toolbar{display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap}
.news-head{flex:1;font-size:17px;font-weight:800;color:var(--text);letter-spacing:-.4px;display:flex;align-items:center;gap:8px}
.count-pill{font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;background:var(--accent-soft);color:var(--accent)}
.search-wrap{position:relative}
.search-inp{width:210px;padding:7px 12px 7px 32px;border-radius:9px;border:1px solid var(--border);background:var(--surface2);color:var(--text);font-size:13px;font-family:var(--font);outline:none;transition:border .15s}
.search-inp:focus{border-color:var(--accent);background:var(--surface)}
.search-ico{position:absolute;left:9px;top:50%;transform:translateY(-50%);width:14px;height:14px;stroke:var(--muted);fill:none;stroke-width:2;stroke-linecap:round;pointer-events:none}
.refresh-btn{display:flex;align-items:center;gap:5px;padding:7px 13px;border-radius:9px;border:1px solid var(--border);background:var(--surface2);color:var(--muted);font-size:12px;font-weight:600;cursor:pointer;transition:all .14s;font-family:var(--font)}
.refresh-btn:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-soft)}
.refresh-btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2}
@keyframes spin{to{transform:rotate(360deg)}}
.refresh-btn.spin svg{animation:spin .6s linear}
.view-tog{display:flex;gap:2px;background:var(--surface2);border:1px solid var(--border);border-radius:9px;padding:3px}
.vbtn{width:28px;height:28px;border-radius:6px;border:none;background:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--muted);transition:all .13s}
.vbtn.on{background:var(--accent);color:#fff}
.vbtn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.fetch-status{font-size:11.5px;color:var(--muted);margin-bottom:10px;min-height:18px;display:flex;align-items:center;gap:6px}
.fetch-status .dot{width:6px;height:6px;border-radius:50%;background:var(--accent);animation:pulse 1s infinite;flex-shrink:0}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}

/* ════ CARDS ════ */
.news-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:13px}
.news-grid.lv{grid-template-columns:1fr;gap:8px}
.ncard{background:var(--surface);border:1px solid var(--border);border-radius:13px;overflow:hidden;cursor:pointer;transition:transform .15s,box-shadow .15s,border-color .15s;display:flex;flex-direction:column}
.ncard:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg);border-color:var(--border2)}
.ncard-img{width:100%;aspect-ratio:16/9;object-fit:cover;background:var(--surface2);display:block;flex-shrink:0}
.ncard-noimg{width:100%;aspect-ratio:16/9;background:linear-gradient(135deg,var(--surface2),var(--border));display:flex;align-items:center;justify-content:center;font-size:32px;flex-shrink:0}
.ncard-body{padding:11px 13px 13px;display:flex;flex-direction:column;flex:1}
.ncard-meta{display:flex;align-items:center;gap:6px;margin-bottom:6px}
.ncard-badge{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:2px 7px;border-radius:20px;color:#fff;flex-shrink:0}
.ncard-time{font-size:11px;color:var(--muted)}
.ncard-title{font-size:13px;font-weight:700;color:var(--text);line-height:1.45;margin-bottom:5px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.ncard-desc{font-size:11.5px;color:var(--muted);line-height:1.55;flex:1;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.ncard-foot{margin-top:9px;display:flex;align-items:center}
.ncard-read{margin-left:auto;font-size:10.5px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.3px}
.news-grid.lv .ncard{flex-direction:row;min-height:88px}
.news-grid.lv .ncard-img,.news-grid.lv .ncard-noimg{width:120px;height:88px;aspect-ratio:unset;flex-shrink:0}
.news-grid.lv .ncard-noimg{font-size:22px}
.news-grid.lv .ncard-body{padding:9px 13px}
.news-grid.lv .ncard-title{-webkit-line-clamp:2}
.news-grid.lv .ncard-desc{-webkit-line-clamp:1}

/* ════ STATES ════ */
.state-box{grid-column:1/-1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;text-align:center;gap:10px}
.state-icon{font-size:44px}
.state-title{font-size:15px;font-weight:700;color:var(--text)}
.state-sub{font-size:13px;color:var(--muted);max-width:360px;line-height:1.6}
.state-retry{margin-top:6px;padding:9px 22px;border-radius:9px;border:none;background:var(--accent);color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:var(--font)}
/* skeleton */
.skel-card{background:var(--surface);border:1px solid var(--border);border-radius:13px;overflow:hidden}
.skel{background:linear-gradient(90deg,var(--surface2) 25%,var(--border) 50%,var(--surface2) 75%);background-size:200% 100%;animation:sk 1.4s infinite;border-radius:6px}
@keyframes sk{0%{background-position:200% 0}100%{background-position:-200% 0}}
.sk-img{height:130px;border-radius:0}
.sk-l{height:11px;margin:10px 13px 0}
.loadmore-wrap{grid-column:1/-1;text-align:center;padding:14px 0}
.loadmore-btn{padding:9px 26px;border-radius:9px;border:1.5px solid var(--accent);background:var(--accent-soft);color:var(--accent);font-size:13px;font-weight:700;cursor:pointer;transition:all .14s;font-family:var(--font)}
.loadmore-btn:hover{background:var(--accent);color:#fff}

/* ════════════════════════════════════════════
   ARTICLE READER MODAL
════════════════════════════════════════════ */
.reader-ov{
  display:none;position:fixed;inset:0;
  background:rgba(0,0,0,.65);z-index:1000;
  align-items:flex-start;justify-content:center;
  backdrop-filter:blur(6px);
  padding:20px 16px;
  overflow-y:auto;
}
.reader-ov.open{display:flex}
.reader-box{
  background:var(--surface);
  border-radius:20px;
  width:100%;max-width:760px;
  box-shadow:0 32px 80px rgba(0,0,0,.5);
  animation:rIn .22s cubic-bezier(.34,1.56,.64,1);
  overflow:hidden;
  margin:auto;
}
@keyframes rIn{from{transform:translateY(30px) scale(.96);opacity:0}}

/* Reader header bar */
.reader-topbar{
  position:sticky;top:0;z-index:10;
  display:flex;align-items:center;gap:10px;
  padding:12px 18px;
  background:var(--surface);
  border-bottom:1px solid var(--border);
  backdrop-filter:blur(12px);
}
.reader-src-badge{
  font-size:10px;font-weight:700;text-transform:uppercase;
  letter-spacing:.5px;padding:3px 10px;border-radius:20px;color:#fff;
  flex-shrink:0;
}
.reader-ext-link{
  display:flex;align-items:center;gap:5px;
  margin-left:auto;
  padding:6px 12px;border-radius:8px;
  border:1px solid var(--border);background:var(--surface2);
  color:var(--text2);font-size:12px;font-weight:600;
  text-decoration:none;transition:all .13s;flex-shrink:0;
}
.reader-ext-link:hover{border-color:var(--accent);color:var(--accent)}
.reader-ext-link svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2}
.reader-close{
  width:30px;height:30px;flex-shrink:0;
  border:none;background:var(--surface2);border-radius:8px;
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  color:var(--muted);transition:background .13s;
}
.reader-close:hover{background:var(--red-soft);color:var(--red)}
.reader-close svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.5}

/* Reader content */
.reader-content{padding:28px 36px 40px}

.reader-thumb{
  width:100%;max-height:380px;object-fit:cover;
  border-radius:14px;margin-bottom:24px;display:block;
}
.reader-title{
  font-size:22px;font-weight:800;color:var(--text);
  line-height:1.35;letter-spacing:-.4px;margin-bottom:14px;
}
.reader-meta{
  display:flex;align-items:center;gap:12px;
  padding-bottom:16px;border-bottom:1px solid var(--border);
  margin-bottom:24px;flex-wrap:wrap;
}
.reader-meta-item{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:5px}
.reader-meta-item svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0}

/* Article body styling */
.reader-body{font-size:15.5px;line-height:1.85;color:var(--text2);font-weight:400}
.reader-body h1,.reader-body h2,.reader-body h3{color:var(--text);font-weight:700;margin:1.4em 0 .6em;line-height:1.3}
.reader-body h1{font-size:1.3em}
.reader-body h2{font-size:1.15em}
.reader-body h3{font-size:1.05em}
.reader-body p{margin-bottom:1.1em}
.reader-body img{width:100%;height:auto;border-radius:10px;margin:1em 0;display:block}
.reader-body figure{margin:1.2em 0}
.reader-body figcaption{font-size:12.5px;color:var(--muted);text-align:center;margin-top:6px;font-style:italic}
.reader-body blockquote{
  border-left:3px solid var(--accent);
  margin:1.2em 0;padding:.8em 1.2em;
  background:var(--accent-soft);border-radius:0 8px 8px 0;
  font-style:italic;color:var(--text2);
}
.reader-body a{color:var(--accent);text-decoration:underline;text-underline-offset:3px}
.reader-body ul,
.reader-body ol{padding-left:1.5em;margin-bottom:1em}
.reader-body li{margin-bottom:.4em}
.reader-body table{width:100%;border-collapse:collapse;margin:1em 0;font-size:13.5px}
.reader-body td,.reader-body th{padding:8px 10px;border:1px solid var(--border);text-align:left}
.reader-body th{background:var(--surface2);font-weight:700}
/* Hide ads, social share, related boxes inside article */
.reader-body .social-share,.reader-body .related,.reader-body .ads,.reader-body [class*="adver"],
.reader-body [class*="social"],[class*="share-btn"]{display:none!important}

/* Loading spinner inside reader */
.reader-loading{
  display:flex;flex-direction:column;
  align-items:center;justify-content:center;
  padding:60px 20px;gap:14px;
}
.reader-spinner{
  width:36px;height:36px;border:3px solid var(--border);
  border-top-color:var(--accent);border-radius:50%;
  animation:spin .8s linear infinite;
}
.reader-loading-txt{font-size:13px;color:var(--muted)}

/* ════ RESPONSIVE ════ */
@media(max-width:860px){
  .news-wrap{grid-template-columns:1fr;padding:10px 10px 90px}
  .news-sidebar{position:static;max-height:none}
  .sidebar-hdr{display:none}
  .src-list-inner{display:flex;overflow-x:auto;scrollbar-width:none;padding:6px 8px;gap:4px}
  .src-list-inner::-webkit-scrollbar{display:none}
  .src-row{flex-shrink:0;border-radius:8px;padding:6px 12px;border-left:none;border-bottom:2.5px solid transparent;white-space:nowrap}
  .src-row.active{border-left:none;border-bottom-color:var(--accent)}
  .src-arrow,.src-divider,.cat-list{display:none}
  .news-grid{grid-template-columns:1fr 1fr}
  .reader-content{padding:20px 18px 32px}
  .reader-title{font-size:18px}
  .reader-body{font-size:14.5px}
}
@media(max-width:540px){
  .news-grid{grid-template-columns:1fr}
  .search-inp{width:130px}
  .reader-ov{padding:0}
  .reader-box{border-radius:16px 16px 0 0;margin-top:auto}
}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="news-wrap">
  <!-- ── SIDEBAR ── -->
  <aside class="news-sidebar">
    <div class="sidebar-hdr">Nguồn tin</div>
    <div class="src-list-inner" id="srcList">

      <div class="src-row active" data-src="all" onclick="pickSrc(this)">
        <span style="font-size:15px">🗞️</span>
        <span class="src-name">Tất cả</span>
      </div>
      <div class="src-divider"></div>

      <div>
        <div class="src-row" data-src="vnexpress" onclick="pickSrc(this)">
          <span class="src-dot" style="background:#0066cc"></span>
          <span class="src-name">VnExpress</span>
          <svg class="src-arrow" id="arr-vnexpress" viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg>
        </div>
        <div class="cat-list" id="cats-vnexpress">
          <div class="cat-row" data-src="vnexpress" data-url="https://vnexpress.net/rss/tin-moi-nhat.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Trang chủ</div>
          <div class="cat-row" data-src="vnexpress" data-url="https://vnexpress.net/rss/thoi-su.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Thời sự</div>
          <div class="cat-row" data-src="vnexpress" data-url="https://vnexpress.net/rss/the-gioi.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Thế giới</div>
          <div class="cat-row" data-src="vnexpress" data-url="https://vnexpress.net/rss/kinh-doanh.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Kinh doanh</div>
          <div class="cat-row" data-src="vnexpress" data-url="https://vnexpress.net/rss/giao-duc.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Giáo dục</div>
          <div class="cat-row" data-src="vnexpress" data-url="https://vnexpress.net/rss/khoa-hoc.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Khoa học</div>
          <div class="cat-row" data-src="vnexpress" data-url="https://vnexpress.net/rss/so-hoa.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Công nghệ</div>
        </div>
      </div>
      <div>
        <div class="src-row" data-src="tuoitre" onclick="pickSrc(this)">
          <span class="src-dot" style="background:#e65c00"></span>
          <span class="src-name">Tuổi Trẻ</span>
          <svg class="src-arrow" id="arr-tuoitre" viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg>
        </div>
        <div class="cat-list" id="cats-tuoitre">
          <div class="cat-row" data-src="tuoitre" data-url="https://tuoitre.vn/rss/tin-moi-nhat.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Trang chủ</div>
          <div class="cat-row" data-src="tuoitre" data-url="https://tuoitre.vn/rss/thoi-su.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Thời sự</div>
          <div class="cat-row" data-src="tuoitre" data-url="https://tuoitre.vn/rss/the-gioi.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Thế giới</div>
          <div class="cat-row" data-src="tuoitre" data-url="https://tuoitre.vn/rss/kinh-te.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Kinh tế</div>
          <div class="cat-row" data-src="tuoitre" data-url="https://tuoitre.vn/rss/giao-duc.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Giáo dục</div>
          <div class="cat-row" data-src="tuoitre" data-url="https://tuoitre.vn/rss/cong-nghe.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Công nghệ</div>
        </div>
      </div>
      <div>
        <div class="src-row" data-src="thanhnien" onclick="pickSrc(this)">
          <span class="src-dot" style="background:#007a3d"></span>
          <span class="src-name">Thanh Niên</span>
          <svg class="src-arrow" id="arr-thanhnien" viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg>
        </div>
        <div class="cat-list" id="cats-thanhnien">
          <div class="cat-row" data-src="thanhnien" data-url="https://thanhnien.vn/rss/home.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Trang chủ</div>
          <div class="cat-row" data-src="thanhnien" data-url="https://thanhnien.vn/rss/thoi-su.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Thời sự</div>
          <div class="cat-row" data-src="thanhnien" data-url="https://thanhnien.vn/rss/the-gioi.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Thế giới</div>
          <div class="cat-row" data-src="thanhnien" data-url="https://thanhnien.vn/rss/kinh-te.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Kinh tế</div>
          <div class="cat-row" data-src="thanhnien" data-url="https://thanhnien.vn/rss/giao-duc.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Giáo dục</div>
          <div class="cat-row" data-src="thanhnien" data-url="https://thanhnien.vn/rss/cong-nghe.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Công nghệ</div>
          <div class="cat-row" data-src="thanhnien" data-url="https://thanhnien.vn/rss/giai-tri.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Giải trí</div>
        </div>
      </div>
      <div>
        <div class="src-row" data-src="zingnews" onclick="pickSrc(this)">
          <span class="src-dot" style="background:#cc0000"></span>
          <span class="src-name">Zing News</span>
          <svg class="src-arrow" id="arr-zingnews" viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg>
        </div>
        <div class="cat-list" id="cats-zingnews">
          <div class="cat-row" data-src="zingnews" data-url="https://znews.vn/rss/tin-moi-nhat.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Trang chủ</div>
          <div class="cat-row" data-src="zingnews" data-url="https://znews.vn/rss/xa-hoi.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Xã hội</div>
          <div class="cat-row" data-src="zingnews" data-url="https://znews.vn/rss/the-gioi.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Thế giới</div>
          <div class="cat-row" data-src="zingnews" data-url="https://znews.vn/rss/kinh-doanh.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Kinh tế</div>
          <div class="cat-row" data-src="zingnews" data-url="https://znews.vn/rss/giai-tri.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Giải trí</div>
          <div class="cat-row" data-src="zingnews" data-url="https://znews.vn/rss/cong-nghe.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Công nghệ</div>
        </div>
      </div>
      <div>
        <div class="src-row" data-src="dantri" onclick="pickSrc(this)">
          <span class="src-dot" style="background:#7b2d8b"></span>
          <span class="src-name">Dân Trí</span>
          <svg class="src-arrow" id="arr-dantri" viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg>
        </div>
        <div class="cat-list" id="cats-dantri">
          <div class="cat-row" data-src="dantri" data-url="https://dantri.com.vn/rss/home.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Trang chủ</div>
          <div class="cat-row" data-src="dantri" data-url="https://dantri.com.vn/rss/xa-hoi.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Xã hội</div>
          <div class="cat-row" data-src="dantri" data-url="https://dantri.com.vn/rss/the-gioi.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Thế giới</div>
          <div class="cat-row" data-src="dantri" data-url="https://dantri.com.vn/rss/kinh-doanh.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Kinh doanh</div>
          <div class="cat-row" data-src="dantri" data-url="https://dantri.com.vn/rss/giao-duc-khuyen-hoc.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Giáo dục</div>
          <div class="cat-row" data-src="dantri" data-url="https://dantri.com.vn/rss/giai-tri.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Giải trí</div>
        </div>
      </div>
      <div>
        <div class="src-row" data-src="vietnamnet" onclick="pickSrc(this)">
          <span class="src-dot" style="background:#444"></span>
          <span class="src-name">VietnamNet</span>
          <svg class="src-arrow" id="arr-vietnamnet" viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg>
        </div>
        <div class="cat-list" id="cats-vietnamnet">
          <div class="cat-row" data-src="vietnamnet" data-url="https://vietnamnet.vn/rss/home.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Trang chủ</div>
          <div class="cat-row" data-src="vietnamnet" data-url="https://vietnamnet.vn/rss/thoi-su.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Thời sự</div>
          <div class="cat-row" data-src="vietnamnet" data-url="https://vietnamnet.vn/rss/the-gioi.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Thế giới</div>
          <div class="cat-row" data-src="vietnamnet" data-url="https://vietnamnet.vn/rss/kinh-doanh.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Kinh doanh</div>
          <div class="cat-row" data-src="vietnamnet" data-url="https://vietnamnet.vn/rss/giao-duc.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Giáo dục</div>
        </div>
      </div>
      <div>
        <div class="src-row" data-src="nhandan" onclick="pickSrc(this)">
          <span class="src-dot" style="background:#c0392b"></span>
          <span class="src-name">Nhân Dân</span>
          <svg class="src-arrow" id="arr-nhandan" viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg>
        </div>
        <div class="cat-list" id="cats-nhandan">
          <div class="cat-row" data-src="nhandan" data-url="https://nhandan.vn/rss/home.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Trang chủ</div>
          <div class="cat-row" data-src="nhandan" data-url="https://nhandan.vn/rss/chinhtri.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Chính trị</div>
          <div class="cat-row" data-src="nhandan" data-url="https://nhandan.vn/rss/kinhte.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Kinh tế</div>
          <div class="cat-row" data-src="nhandan" data-url="https://nhandan.vn/rss/thegioi.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Thế giới</div>
          <div class="cat-row" data-src="nhandan" data-url="https://nhandan.vn/rss/xahoi.rss" onclick="pickCat(this)"><span class="cat-dot"></span>Xã hội</div>
        </div>
      </div>

    </div>
  </aside>

  <!-- ── MAIN ── -->
  <main class="news-main">
    <div class="trending-bar">
      <span class="trending-lbl">🔥 Hot</span>
      <div class="trending-scroll">
        <span class="t-tag" onclick="doSearch('AI')">AI & Công nghệ</span>
        <span class="t-tag" onclick="doSearch('kinh tế')">Kinh tế</span>
        <span class="t-tag" onclick="doSearch('giáo dục')">Giáo dục</span>
        <span class="t-tag" onclick="doSearch('thế giới')">Thế giới</span>
        <span class="t-tag" onclick="doSearch('chứng khoán')">Chứng khoán</span>
        <span class="t-tag" onclick="doSearch('sức khỏe')">Sức khỏe</span>
        <span class="t-tag" onclick="doSearch('bóng đá')">Bóng đá</span>
        <span class="t-tag" onclick="doSearch('tuyển sinh')">Tuyển sinh</span>
        <span class="t-tag" onclick="doSearch('du học')">Du học</span>
      </div>
    </div>
    <div class="news-toolbar">
      <div class="news-head">
        <span id="pageTitle">🗞️ Tất cả báo</span>
        <span class="count-pill" id="countPill">…</span>
      </div>
      <div class="search-wrap">
        <svg class="search-ico" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input class="search-inp" id="searchBox" type="text" placeholder="Tìm kiếm…" oninput="onSearch(this.value)" autocomplete="off">
      </div>
      <button class="refresh-btn" id="refreshBtn" onclick="reload()">
        <svg viewBox="0 0 24 24"><polyline points="23,4 23,10 17,10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        Làm mới
      </button>
      <div class="view-tog">
        <button class="vbtn on" id="vGrid" onclick="setView('grid')">
          <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        </button>
        <button class="vbtn" id="vList" onclick="setView('list')">
          <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
      </div>
    </div>
    <div class="fetch-status" id="fetchStatus"></div>
    <div class="news-grid" id="newsGrid"></div>
  </main>
</div>

<!-- ════════════════════════════════════════
     ARTICLE READER
════════════════════════════════════════ -->
<div class="reader-ov" id="readerOv" onclick="closeReaderOv(event)">
  <div class="reader-box" id="readerBox">

    <div class="reader-topbar">
      <span class="reader-src-badge" id="rBadge">…</span>
      <span id="rTime" style="font-size:12px;color:var(--muted)"></span>
      <a class="reader-ext-link" id="rExtLink" href="#" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15,3 21,3 21,9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        Mở trang gốc
      </a>
      <button class="reader-close" onclick="closeReader()">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="reader-content" id="readerContent">
      <div class="reader-loading">
        <div class="reader-spinner"></div>
        <div class="reader-loading-txt">Đang tải bài viết…</div>
      </div>
    </div>

  </div>
</div>

<script>
/* ════════════════════════════════════════════
   CONFIG
════════════════════════════════════════════ */
const SOURCES = {
  vnexpress : {label:'VnExpress', color:'#0066cc', default:'https://vnexpress.net/rss/tin-moi-nhat.rss'},
  tuoitre   : {label:'Tuổi Trẻ',  color:'#e65c00', default:'https://tuoitre.vn/rss/tin-moi-nhat.rss'},
  thanhnien : {label:'Thanh Niên',color:'#007a3d', default:'https://thanhnien.vn/rss/home.rss'},
  zingnews  : {label:'Zing News', color:'#cc0000', default:'https://znews.vn/rss/tin-moi-nhat.rss'},
  dantri    : {label:'Dân Trí',   color:'#7b2d8b', default:'https://dantri.com.vn/rss/home.rss'},
  vietnamnet: {label:'VietnamNet',color:'#444444', default:'https://vietnamnet.vn/rss/home.rss'},
  nhandan   : {label:'Nhân Dân',  color:'#c0392b', default:'https://nhandan.vn/rss/home.rss'},
};
const PER_PAGE = 21;
const PROXY = 'news_proxy.php';

let st = {src:'all', url:'', items:[], filtered:[], page:1, view:'grid', q:''};
let searchTimer = null, loadKey = 0;

/* ════════════════════════════════════════════
   FETCH RSS
════════════════════════════════════════════ */
async function fetchFeed(rssUrl, srcKey) {
  const src = SOURCES[srcKey] || {};
  try {
    const res = await fetch(PROXY + '?action=rss&src=' + encodeURIComponent(srcKey) + '&url=' + encodeURIComponent(rssUrl));
    const data = await res.json();
    if (!data.ok || !Array.isArray(data.items)) return [];
    return data.items.map(it => ({
      ...it,
      time  : ago(it.ts * 1000),
      source: src.label || srcKey,
      color : src.color || '#3b5bdb',
      srcKey,
    }));
  } catch(e) { return []; }
}

/* ════════════════════════════════════════════
   LOAD
════════════════════════════════════════════ */
async function load() {
  const key = ++loadKey;
  showSkel(); let all = [];

  if (st.src === 'all') {
    let done = 0;
    const keys = Object.keys(SOURCES);
    setStatus(true, `Đang tải 0/${keys.length} nguồn…`);
    const jobs = keys.map(async k => {
      const items = await fetchFeed(SOURCES[k].default, k);
      if (key !== loadKey) return;
      done++; all.push(...items);
      setStatus(true, `Đang tải ${done}/${keys.length} nguồn…`);
      if (done <= 2) { st.items=[...all].sort((a,b)=>b.ts-a.ts); applyFilter(); }
    });
    await Promise.allSettled(jobs);
    if (key !== loadKey) return;
    all.sort((a,b) => b.ts - a.ts);
  } else {
    setStatus(true, 'Đang tải…');
    const url = st.url || SOURCES[st.src]?.default || '';
    all = url ? await fetchFeed(url, st.src) : [];
    if (key !== loadKey) return;
  }

  if (key !== loadKey) return;
  st.items = all; applyFilter();
  setStatus(false,''); stopSpin();
  if (!all.length) showError();
}

document.addEventListener('DOMContentLoaded', load);

/* ════════════════════════════════════════════
   RENDER GRID
════════════════════════════════════════════ */
function applyFilter() {
  const q = st.q.toLowerCase();
  st.filtered = q
    ? st.items.filter(i => i.title.toLowerCase().includes(q) || (i.desc||'').toLowerCase().includes(q))
    : st.items;
  document.getElementById('countPill').textContent = st.filtered.length + ' tin';
  renderPage(1);
}

function renderPage(page) {
  st.page = page;
  const grid = document.getElementById('newsGrid');
  grid.className = 'news-grid' + (st.view === 'list' ? ' lv' : '');
  grid.innerHTML = '';
  const slice = st.filtered.slice(0, page * PER_PAGE);
  if (!slice.length) return;
  slice.forEach(item => grid.appendChild(makeCard(item)));
  if (slice.length < st.filtered.length) {
    const w = document.createElement('div');
    w.className = 'loadmore-wrap';
    const rem = Math.min(PER_PAGE, st.filtered.length - slice.length);
    w.innerHTML = `<button class="loadmore-btn" onclick="renderPage(${page+1})">Tải thêm ${rem} tin…</button>`;
    grid.appendChild(w);
  }
}

function makeCard(item) {
  const a = document.createElement('div');
  a.className = 'ncard';
  a.addEventListener('click', () => openReader(item));
  const imgHtml = item.thumb
    ? `<img class="ncard-img" src="${esc(item.thumb)}" alt="" loading="lazy"
          onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
      + `<div class="ncard-noimg" style="display:none">📰</div>`
    : `<div class="ncard-noimg">📰</div>`;
  a.innerHTML = `${imgHtml}
    <div class="ncard-body">
      <div class="ncard-meta">
        <span class="ncard-badge" style="background:${esc(item.color)}">${esc(item.source)}</span>
        <span class="ncard-time">${esc(item.time)}</span>
      </div>
      <div class="ncard-title">${esc(item.title)}</div>
      ${item.desc?`<div class="ncard-desc">${esc(item.desc)}</div>`:''}
      <div class="ncard-foot">
        <span class="ncard-read">Đọc bài →</span>
      </div>
    </div>`;
  return a;
}

/* ════════════════════════════════════════════
   ARTICLE READER
════════════════════════════════════════════ */
async function openReader(item) {
  // Set topbar immediately
  document.getElementById('rBadge').textContent  = item.source;
  document.getElementById('rBadge').style.background = item.color;
  document.getElementById('rTime').textContent   = item.time;
  document.getElementById('rExtLink').href        = item.link;

  // Show loading state
  document.getElementById('readerContent').innerHTML = `
    <div class="reader-loading">
      <div class="reader-spinner"></div>
      <div class="reader-loading-txt">Đang tải bài viết…</div>
    </div>`;

  // Open overlay
  document.getElementById('readerOv').classList.add('open');
  document.body.style.overflow = 'hidden';

  // Fetch full article
  try {
    const res  = await fetch(PROXY + '?action=article&url=' + encodeURIComponent(item.link));
    const data = await res.json();

    if (!data.ok) throw new Error(data.error || 'fetch failed');

    const title  = data.title  || item.title;
    const thumb  = data.thumb  || item.thumb || '';
    const author = data.author ? `<div class="reader-meta-item"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>${esc(data.author)}</div>` : '';
    const pubTime = data.pub   ? `<div class="reader-meta-item"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>${fmtDate(data.pub)}</div>` : `<div class="reader-meta-item">${esc(item.time)}</div>`;
    const srcBadge= `<div class="reader-meta-item"><span style="width:8px;height:8px;border-radius:50%;background:${esc(item.color)};display:inline-block;flex-shrink:0"></span>${esc(item.source)}</div>`;

    const bodyContent = data.body
      ? `<div class="reader-body">${data.body}</div>`
      : `<div class="reader-body"><p>${esc(data.text || item.desc || 'Không thể tải nội dung bài viết.')}</p></div>`;

    document.getElementById('readerContent').innerHTML = `
      ${thumb ? `<img class="reader-thumb" src="${esc(thumb)}" alt="" onerror="this.style.display='none'">` : ''}
      <h1 class="reader-title">${esc(title)}</h1>
      <div class="reader-meta">${srcBadge}${author}${pubTime}</div>
      ${bodyContent}`;

    // Scroll to top of reader
    document.getElementById('readerOv').scrollTop = 0;

  } catch(e) {
    // Fallback: show description + link
    document.getElementById('readerContent').innerHTML = `
      ${item.thumb ? `<img class="reader-thumb" src="${esc(item.thumb)}" alt="" onerror="this.style.display='none'">` : ''}
      <h1 class="reader-title">${esc(item.title)}</h1>
      <div class="reader-meta">
        <div class="reader-meta-item"><span style="width:8px;height:8px;border-radius:50%;background:${esc(item.color)};display:inline-block"></span>${esc(item.source)}</div>
        <div class="reader-meta-item">${esc(item.time)}</div>
      </div>
      <div class="reader-body">
        <p>${esc(item.desc || '')}</p>
        <p style="margin-top:20px;padding:16px;background:var(--surface2);border-radius:10px;font-size:13px;color:var(--muted)">
          ⚠️ Không thể tải toàn bộ nội dung bài viết. 
          <a href="${esc(item.link)}" target="_blank" style="color:var(--accent)">Nhấn đây để đọc trên trang gốc →</a>
        </p>
      </div>`;
  }
}

function closeReader() {
  document.getElementById('readerOv').classList.remove('open');
  document.body.style.overflow = '';
}
function closeReaderOv(e) {
  if (e.target === document.getElementById('readerOv')) closeReader();
}

/* ════════════════════════════════════════════
   UI CONTROLS
════════════════════════════════════════════ */
function pickSrc(el) {
  document.querySelectorAll('.src-row').forEach(e=>e.classList.remove('active'));
  document.querySelectorAll('.cat-list').forEach(e=>e.classList.remove('open'));
  document.querySelectorAll('.src-arrow').forEach(e=>e.classList.remove('open'));
  document.querySelectorAll('.cat-row').forEach(e=>e.classList.remove('active'));
  el.classList.add('active');
  st.src=el.dataset.src; st.url=''; clearSearch();
  const cats=document.getElementById('cats-'+st.src);
  const arr=document.getElementById('arr-'+st.src);
  if(cats){cats.classList.add('open');if(arr)arr.classList.add('open');}
  const info=SOURCES[st.src];
  document.getElementById('pageTitle').textContent=
    st.src==='all'?'🗞️ Tất cả báo':(info?info.label:st.src);
  load();
}
function pickCat(el) {
  document.querySelectorAll('.cat-row').forEach(e=>e.classList.remove('active'));
  el.classList.add('active');
  st.src=el.dataset.src; st.url=el.dataset.url; clearSearch();
  const info=SOURCES[st.src];
  document.getElementById('pageTitle').textContent=(info?info.label:st.src)+' › '+el.textContent.trim();
  load();
}
function reload(){document.getElementById('refreshBtn').classList.add('spin');load();}
function stopSpin(){document.getElementById('refreshBtn').classList.remove('spin');}
function setView(v){
  st.view=v;
  document.getElementById('vGrid').classList.toggle('on',v==='grid');
  document.getElementById('vList').classList.toggle('on',v==='list');
  renderPage(st.page);
}
function onSearch(val){clearTimeout(searchTimer);searchTimer=setTimeout(()=>{st.q=val.trim();applyFilter();},280);}
function doSearch(q){document.getElementById('searchBox').value=q;st.q=q;applyFilter();}
function clearSearch(){document.getElementById('searchBox').value='';st.q='';}
function setStatus(loading,msg){
  const el=document.getElementById('fetchStatus');
  if(!msg){el.innerHTML='';return;}
  el.innerHTML=loading?`<span class="dot"></span>${esc(msg)}`:esc(msg);
}
function showSkel(){
  const g=document.getElementById('newsGrid');
  g.className='news-grid'+(st.view==='list'?' lv':'');
  g.innerHTML=Array(6).fill('').map(()=>`
    <div class="skel-card">
      <div class="skel sk-img"></div>
      <div class="skel sk-l" style="width:92%"></div>
      <div class="skel sk-l" style="width:78%"></div>
      <div class="skel sk-l" style="width:55%;margin-bottom:14px"></div>
    </div>`).join('');
}
function showError(){
  document.getElementById('newsGrid').innerHTML=`
    <div class="state-box">
      <div class="state-icon">📭</div>
      <div class="state-title">Không tải được tin tức</div>
      <div class="state-sub">Proxy đang bận hoặc RSS không phản hồi. Thử lại sau vài giây.</div>
      <button class="state-retry" onclick="reload()">🔄 Thử lại</button>
    </div>`;
}

/* ════════════════════════════════════════════
   HELPERS
════════════════════════════════════════════ */
function ago(ms){
  const d=Date.now()-ms;
  if(d<60000)    return 'Vừa đăng';
  if(d<3600000)  return Math.floor(d/60000)+' phút trước';
  if(d<86400000) return Math.floor(d/3600000)+' giờ trước';
  if(d<604800000)return Math.floor(d/86400000)+' ngày trước';
  return new Date(ms).toLocaleDateString('vi-VN');
}
function fmtDate(str){
  try{return new Date(str).toLocaleString('vi-VN',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});}
  catch{return str;}
}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

document.addEventListener('keydown',e=>{if(e.key==='Escape')closeReader();});
</script>
</body>
</html>
