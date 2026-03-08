# 🚀 Hướng dẫn tích hợp 10 Tính năng mới — MindSpark

## Các file mới cần thêm vào dự án

Thêm 4 file sau vào thư mục gốc (cùng với `db.php`, `rooms.php`...):

| File | Mô tả |
|------|-------|
| `features.php` | Trang hub 10 tính năng |
| `cowork.php` | Phòng học/làm việc nhóm |
| `translator.php` | API dịch thuật real-time |
| `db_upgrade.php` | Tạo tất cả bảng DB mới |

---

## Bước 1: Cập nhật `db.php`

Thêm dòng này vào **cuối** file `db.php`, ngay trước dòng cuối cùng `?>`:

```php
require_once __DIR__ . '/db_upgrade.php';
```

---

## Bước 2: Cập nhật `navbar.php`

Thêm các link sau vào phần nav-links:

```html
<!-- Desktop nav -->
<a href="features.php" class="nav-link <?=$current=='features.php'?'active':''?>">✨ Tính năng</a>
<a href="cowork.php"   class="nav-link <?=$current=='cowork.php'?'active':''?>">🎓 Co-working</a>
```

Thêm vào dropdown menu:
```html
<a href="features.php" class="nav-dd-item">✨ Tính năng nâng cao</a>
<a href="cowork.php"   class="nav-dd-item">🎓 Phòng học nhóm</a>
```

---

## Bước 3: Thêm nút dịch vào `rooms.php`

Để có nút dịch real-time trong chat, thêm vào hàm `appendMsg` trong rooms.php:

```javascript
// Thêm nút dịch vào mỗi tin nhắn
const translateBtn = `<button class="act-btn" onclick="translateMsg(${m.id},'${m.content}',this)" title="Dịch">
  🌐
</button>`;
// Thêm translateBtn vào biến actions
```

Và thêm hàm translateMsg:

```javascript
async function translateMsg(id, text, btn) {
  btn.textContent = '⏳';
  const res = await fetch('translator.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({text, to: 'vi', msg_id: id})
  });
  const data = await res.json();
  if(data.ok) {
    btn.closest('.msg-body').querySelector('.msg-time').insertAdjacentHTML(
      'afterend', `<span style="font-size:11px;color:var(--accent);margin-left:6px;">🌐 ${data.translated}</span>`
    );
    btn.textContent = '✅';
  }
}
```

---

## Bước 4: Thêm link features vào `community.php`

Trong phần đầu community.php thêm nút tặng quà:
```php
// Để nhận $gifts trong bài viết, gọi features.php action=send_gift
```

---

## Tổng quan 10 tính năng

| # | Tên | File | Status |
|---|-----|------|--------|
| 1 | 🧠 Tri kỷ AI + Nhắc nhở | `features.php` | ✅ |
| 2 | 🛒 Chợ ảo | `features.php` | ✅ |
| 3 | 🎓 Co-working Space | `cowork.php` | ✅ |
| 4 | 🌐 Dịch real-time | `translator.php` | ✅ |
| 5 | ⏳ Viên nang thời gian | `features.php` | ✅ |
| 6 | 🎯 Deep Focus Mode | `features.php` | ✅ |
| 7 | ✅ Tích xanh AI | `features.php` | ✅ |
| 8 | 🎨 Content Creator | `community.php` (filters) | 🔧 Partial |
| 9 | 🎁 Quà tặng ảo | `features.php` | ✅ |
| 10 | 📍 Bạn bè quanh đây | `features.php` | ✅ |

---

## Xu MindSpark 🪙

Người dùng mới được cấp **500 xu** mặc định.
Dùng để: Mua trên marketplace, tặng quà ảo.
Nhận thêm bằng cách: Bán sản phẩm, nhận quà từ người khác.

---

## Yêu cầu

- PHP 7.4+ với cURL enabled
- SQLite3 extension
- Groq API key (đã có sẵn trong `ai_api.php`)
- HTTPS (bắt buộc cho camera/geolocation)
