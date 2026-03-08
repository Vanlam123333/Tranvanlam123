<?php
// db_upgrade.php — Run once or include in db.php to add all new feature tables
// Include this at top of db.php: require_once __DIR__.'/db_upgrade.php';

function runMigrations(SQLite3 $db) {

    // ── 1. AI COMPANION ──
    @$db->exec("ALTER TABLE users ADD COLUMN ai_style TEXT DEFAULT ''");
    @$db->exec("ALTER TABLE users ADD COLUMN ai_name TEXT DEFAULT 'Spark'");
    $db->exec("CREATE TABLE IF NOT EXISTS ai_companion_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        role TEXT NOT NULL,
        content TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS ai_reminders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        remind_at DATETIME NOT NULL,
        done INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // ── 2. MARKETPLACE ──
    $db->exec("CREATE TABLE IF NOT EXISTS marketplace_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        seller_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        price INTEGER NOT NULL DEFAULT 0,
        category TEXT DEFAULT 'other',
        file_data TEXT,
        preview_data TEXT,
        status TEXT DEFAULT 'active',
        sales_count INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS marketplace_purchases (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        item_id INTEGER NOT NULL,
        buyer_id INTEGER NOT NULL,
        price INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(item_id, buyer_id)
    )");
    @$db->exec("ALTER TABLE users ADD COLUMN coins INTEGER DEFAULT 500");

    // ── 3. CO-WORKING ROOM ──
    $db->exec("CREATE TABLE IF NOT EXISTS cowork_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        room_id INTEGER NOT NULL,
        host_id INTEGER NOT NULL,
        title TEXT,
        music TEXT DEFAULT 'lofi',
        status TEXT DEFAULT 'active',
        pomo_duration INTEGER DEFAULT 25,
        started_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS cowork_whiteboard (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        room_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        data TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // ── 4. TRANSLATOR (flag on messages) ──
    @$db->exec("ALTER TABLE room_messages ADD COLUMN lang TEXT DEFAULT 'vi'");
    @$db->exec("ALTER TABLE room_messages ADD COLUMN translated TEXT");

    // ── 5. TIME CAPSULE ──
    $db->exec("CREATE TABLE IF NOT EXISTS time_capsules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sender_id INTEGER NOT NULL,
        recipient_id INTEGER,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        image_data TEXT,
        unlock_at DATETIME NOT NULL,
        is_public INTEGER DEFAULT 0,
        opened INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // ── 6. DEEP FOCUS ──
    $db->exec("CREATE TABLE IF NOT EXISTS focus_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        duration_min INTEGER NOT NULL,
        goal TEXT,
        completed INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // ── 7. VERIFIED BADGE ──
    @$db->exec("ALTER TABLE users ADD COLUMN verified INTEGER DEFAULT 0");
    @$db->exec("ALTER TABLE users ADD COLUMN verify_type TEXT DEFAULT ''");
    $db->exec("CREATE TABLE IF NOT EXISTS verify_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        id_front TEXT,
        selfie_data TEXT,
        status TEXT DEFAULT 'pending',
        admin_note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // ── 8. CONTENT CREATOR (filters on posts) ──
    @$db->exec("ALTER TABLE social_posts ADD COLUMN filter_type TEXT DEFAULT ''");

    // ── 9. VIRTUAL GIFTS ──
    $db->exec("CREATE TABLE IF NOT EXISTS virtual_gifts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sender_id INTEGER NOT NULL,
        recipient_id INTEGER NOT NULL,
        post_id INTEGER,
        gift_type TEXT NOT NULL,
        coins_value INTEGER DEFAULT 0,
        message TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // ── 10. NEARBY FRIENDS ──
    $db->exec("CREATE TABLE IF NOT EXISTS user_locations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL UNIQUE,
        lat REAL,
        lng REAL,
        visible_to TEXT DEFAULT 'friends',
        expires_at DATETIME,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS friendships (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        friend_id INTEGER NOT NULL,
        status TEXT DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, friend_id)
    )");

    // ── NOTIFICATIONS ──
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        type TEXT NOT NULL,
        title TEXT,
        body TEXT,
        link TEXT,
        read_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
}

runMigrations($db);
