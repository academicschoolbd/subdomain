<?php
declare(strict_types=1);

/** PDO database access, schema init, and seed. */

function db(array $CONFIG): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (($CONFIG['db_driver'] ?? 'sqlite') === 'mysql') {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $CONFIG['db_mysql_host'],
            $CONFIG['db_mysql_port'],
            $CONFIG['db_mysql_name'],
            $CONFIG['db_mysql_charset'] ?? 'utf8mb4'
        );
        $pdo = new PDO($dsn, $CONFIG['db_mysql_user'], $CONFIG['db_mysql_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } else {
        $path = $CONFIG['db_sqlite_path'];
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
    }
    return $pdo;
}

function db_is_mysql(array $CONFIG): bool
{
    return ($CONFIG['db_driver'] ?? 'sqlite') === 'mysql';
}

function db_init_schema(array $CONFIG): void
{
    $pdo = db($CONFIG);
    $mysql = db_is_mysql($CONFIG);

    // Type aliases so the same DDL works on both engines.
    $pk = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $bool = $mysql ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0';
    $now = $mysql ? 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' : "TEXT NOT NULL DEFAULT (datetime('now'))";
    $dt = $mysql ? 'DATETIME NULL' : 'TEXT NULL';
    $text = 'TEXT';
    $charset = $mysql ? ' DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' : '';

    $stmts = [
        "CREATE TABLE IF NOT EXISTS users (
            id $pk,
            phone VARCHAR(20) NULL UNIQUE,
            email VARCHAR(255) NULL,
            name VARCHAR(255) NULL,
            avatar_url VARCHAR(512) NULL,
            provider VARCHAR(32) NULL,
            provider_id VARCHAR(128) NULL,
            password_hash VARCHAR(255) NULL,
            is_admin $bool,
            created_at $now
        )$charset",
        "CREATE TABLE IF NOT EXISTS otps (
            id $pk,
            phone VARCHAR(20) NOT NULL,
            code VARCHAR(8) NOT NULL,
            expires_at $dt,
            used $bool,
            created_at $now
        )$charset",
        "CREATE TABLE IF NOT EXISTS oauth_states (
            id $pk,
            state VARCHAR(128) NOT NULL UNIQUE,
            provider VARCHAR(32) NOT NULL,
            redirect VARCHAR(512) NULL,
            expires_at $dt,
            created_at $now
        )$charset",
        "CREATE TABLE IF NOT EXISTS institutions (
            id $pk,
            brand VARCHAR(32) NOT NULL,
            slug VARCHAR(64) NOT NULL,
            name_bn VARCHAR(255) NULL,
            name_en VARCHAR(255) NOT NULL,
            category VARCHAR(64) NOT NULL,
            division VARCHAR(64) NULL,
            district VARCHAR(64) NULL,
            upazila VARCHAR(64) NULL,
            address $text NULL,
            eiin VARCHAR(32) NULL,
            contact_name VARCHAR(255) NULL,
            contact_phone VARCHAR(20) NULL,
            contact_email VARCHAR(255) NULL,
            website VARCHAR(255) NULL,
            logo_url VARCHAR(512) NULL,
            banner_url VARCHAR(512) NULL,
            about_bn $text NULL,
            about_en $text NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            review_notes $text NULL,
            owner_user_id INTEGER NULL,
            cf_record_id VARCHAR(64) NULL,
            dns_status VARCHAR(16) NULL,
            dns_message $text NULL,
            created_at $now,
            verified_at $dt,
            -- v4.1 — when the domain term ends. Set to verified_at + N days
            -- (default 365) on every approve/instant-claim. NULL until
            -- the domain is verified for the first time. Renewal extends it.
            expires_at $dt,
            UNIQUE (brand, slug)
        )$charset",
        "CREATE TABLE IF NOT EXISTS reserved_slugs (
            id $pk,
            slug VARCHAR(64) NOT NULL UNIQUE,
            reason VARCHAR(255) NULL,
            created_at $now
        )$charset",
        "CREATE TABLE IF NOT EXISTS claim_documents (
            id $pk,
            institution_id INTEGER NOT NULL,
            doc_type VARCHAR(64) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            stored_path VARCHAR(512) NOT NULL,
            content_type VARCHAR(128) NOT NULL,
            uploaded_at $now
        )$charset",
        "CREATE TABLE IF NOT EXISTS notices (
            id $pk,
            institution_id INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL,
            body $text NOT NULL,
            pinned $bool,
            created_at $now,
            updated_at $now
        )$charset",
        "CREATE TABLE IF NOT EXISTS audit_log (
            id $pk,
            actor_user_id INTEGER NULL,
            institution_id INTEGER NULL,
            action VARCHAR(64) NOT NULL,
            detail $text NULL,
            created_at $now
        )$charset",
        // v3.0 — password reset tokens (single-use, 60 min TTL).
        "CREATE TABLE IF NOT EXISTS password_resets (
            id $pk,
            user_id INTEGER NOT NULL,
            token_hash VARCHAR(128) NOT NULL UNIQUE,
            expires_at $dt,
            used $bool,
            created_at $now
        )$charset",
        // v3.0 — sliding-window login throttle (one row per failed attempt).
        "CREATE TABLE IF NOT EXISTS login_throttle (
            id $pk,
            ident VARCHAR(190) NOT NULL,
            ip VARCHAR(64) NULL,
            success $bool,
            created_at $now
        )$charset",
        // v3.0 — runtime platform settings (admin-toggleable). Key/value blob.
        "CREATE TABLE IF NOT EXISTS platform_settings (
            id $pk,
            skey VARCHAR(64) NOT NULL UNIQUE,
            svalue $text NULL,
            updated_at $now
        )$charset",
        // v3.2 — owner-managed DNS records for verified subdomains.
        // Each row is a single DNS record (A / AAAA / CNAME / TXT / MX / NS).
        // Admins CRUD freely; owners CRUD only their own institution's records.
        "CREATE TABLE IF NOT EXISTS dns_records (
            id $pk,
            institution_id INTEGER NOT NULL,
            type VARCHAR(8)   NOT NULL,
            name VARCHAR(120) NOT NULL,
            content VARCHAR(512) NOT NULL,
            ttl INTEGER       NOT NULL DEFAULT 1,
            priority INTEGER  NULL,
            proxied $bool,
            cf_record_id VARCHAR(64) NULL,
            cf_status VARCHAR(16) NULL,
            cf_message $text NULL,
            created_at $now,
            updated_at $now
        )$charset",
        // v4.1 — domain renewal history. One row per renewal request from
        // the owner. When the domain term price is 0 (free), the request
        // is created with status='approved' immediately and the parent
        // institution's expires_at is bumped right away. When the admin
        // has set a non-zero price, the row lands as 'pending' and the
        // owner is shown the configured payment methods; an admin marks
        // it 'approved' (or 'rejected') from the renewals pane, which
        // bumps expires_at on approve.
        "CREATE TABLE IF NOT EXISTS domain_renewals (
            id $pk,
            institution_id INTEGER NOT NULL,
            owner_user_id INTEGER NULL,
            term_days INTEGER NOT NULL DEFAULT 365,
            price_bdt INTEGER NOT NULL DEFAULT 0,
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            note $text NULL,
            owner_message $text NULL,
            previous_expires_at $dt,
            new_expires_at $dt,
            decided_by_user_id INTEGER NULL,
            decided_at $dt,
            created_at $now
        )$charset",
    ];
    foreach ($stmts as $sql) {
        $pdo->exec($sql);
    }
    // Helpful indexes (idempotent).
    $idx = [
        "CREATE INDEX IF NOT EXISTS idx_inst_status ON institutions(status)",
        "CREATE INDEX IF NOT EXISTS idx_inst_brand ON institutions(brand)",
        "CREATE INDEX IF NOT EXISTS idx_inst_owner ON institutions(owner_user_id)",
        "CREATE INDEX IF NOT EXISTS idx_otp_phone ON otps(phone)",
        "CREATE INDEX IF NOT EXISTS idx_notices_inst ON notices(institution_id)",
        "CREATE INDEX IF NOT EXISTS idx_docs_inst ON claim_documents(institution_id)",
        "CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)",
        "CREATE INDEX IF NOT EXISTS idx_users_provider ON users(provider, provider_id)",
        "CREATE INDEX IF NOT EXISTS idx_oauth_states_state ON oauth_states(state)",
        "CREATE INDEX IF NOT EXISTS idx_pwreset_user ON password_resets(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_pwreset_token ON password_resets(token_hash)",
        "CREATE INDEX IF NOT EXISTS idx_throttle_ident ON login_throttle(ident, created_at)",
        "CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_log(created_at)",
        "CREATE INDEX IF NOT EXISTS idx_dns_records_inst ON dns_records(institution_id)",
        "CREATE INDEX IF NOT EXISTS idx_support_pay_visible ON support_payments(visible, sort_order)",
        "CREATE INDEX IF NOT EXISTS idx_renewals_inst ON domain_renewals(institution_id)",
        "CREATE INDEX IF NOT EXISTS idx_renewals_status ON domain_renewals(status, created_at)",
    ];
    foreach ($idx as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) { /* mysql older versions */ }
    }

    // ---- Idempotent column upgrades for pre-v2 installs ----
    // Add OAuth + profile columns to users if they don't exist yet.
    $userCols = db_columns($pdo, 'users', $mysql);
    $addUser = [
        'email'            => "ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL",
        'name'             => "ALTER TABLE users ADD COLUMN name VARCHAR(255) NULL",
        'avatar_url'       => "ALTER TABLE users ADD COLUMN avatar_url VARCHAR(512) NULL",
        'provider'         => "ALTER TABLE users ADD COLUMN provider VARCHAR(32) NULL",
        'provider_id'      => "ALTER TABLE users ADD COLUMN provider_id VARCHAR(128) NULL",
        // v2.1 profile fields (collected before first claim).
        'mobile'           => "ALTER TABLE users ADD COLUMN mobile VARCHAR(20) NULL",
        'designation_bn'   => "ALTER TABLE users ADD COLUMN designation_bn VARCHAR(120) NULL",
        'institution_name' => "ALTER TABLE users ADD COLUMN institution_name VARCHAR(255) NULL",
        'division'         => "ALTER TABLE users ADD COLUMN division VARCHAR(64) NULL",
        'district'         => "ALTER TABLE users ADD COLUMN district VARCHAR(64) NULL",
        'upazila'          => "ALTER TABLE users ADD COLUMN upazila VARCHAR(64) NULL",
        'profile_completed_at' => "ALTER TABLE users ADD COLUMN profile_completed_at $dt",
        // v2.3 — email + password auth (so users can sign in without OAuth).
        'password_hash'    => "ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL",
    ];
    foreach ($addUser as $col => $sql) {
        if (!in_array($col, $userCols, true)) {
            try { $pdo->exec($sql); } catch (PDOException $e) { /* tolerate */ }
        }
    }

    // v4.1 — institutions.expires_at (when the domain term lapses).
    // Existing pre-v4.1 installs will not have this column yet; add it
    // idempotently. We *don't* backfill — admins can run a one-off
    // "extend all verified" from the renewals pane if they want every
    // legacy domain to have a real expiry date. By default they read
    // `null` => "no expiry on file" which the dashboard renders as
    // "Free · no expiry".
    $instCols = db_columns($pdo, 'institutions', $mysql);
    if (!in_array('expires_at', $instCols, true)) {
        try { $pdo->exec("ALTER TABLE institutions ADD COLUMN expires_at $dt"); }
        catch (PDOException $e) { /* tolerate */ }
    }

    // v2.3.1 — relax users.phone NOT NULL constraint on legacy v1 DBs.
    // The OAuth + email register flows insert users WITHOUT a phone; the v1
    // schema had `phone NOT NULL UNIQUE` which makes those inserts fail.
    db_migrate_users_phone_nullable($pdo, $mysql);
}

/**
 * Make `users.phone` nullable on databases that were created with the v1 schema
 * (which had it as NOT NULL UNIQUE). Idempotent — does nothing if already
 * nullable, or if the table doesn't exist yet.
 */
function db_migrate_users_phone_nullable(PDO $pdo, bool $mysql): void
{
    if ($mysql) {
        try {
            $stmt = $pdo->prepare(
                "SELECT IS_NULLABLE
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = 'users'
                    AND COLUMN_NAME  = 'phone'"
            );
            $stmt->execute();
            $row = $stmt->fetch();
            if ($row && strtoupper((string)$row['IS_NULLABLE']) === 'NO') {
                $pdo->exec("ALTER TABLE users MODIFY phone VARCHAR(20) NULL");
            }
        } catch (PDOException $e) {
            error_log('db_migrate_users_phone_nullable (mysql): ' . $e->getMessage());
        }
        return;
    }

    // SQLite — relaxing NOT NULL requires a full table rebuild.
    try {
        $cols = $pdo->query('PRAGMA table_info(users)')->fetchAll();
        if (!$cols) return;
        $needsMigration = false;
        foreach ($cols as $c) {
            if ($c['name'] === 'phone' && (int)$c['notnull'] === 1) {
                $needsMigration = true;
                break;
            }
        }
        if (!$needsMigration) return;

        $existingNames = array_map(static fn($c) => $c['name'], $cols);
        $pdo->exec('PRAGMA foreign_keys=OFF');
        $pdo->beginTransaction();
        $pdo->exec(
            "CREATE TABLE users_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                phone VARCHAR(20) NULL UNIQUE,
                email VARCHAR(255) NULL,
                name VARCHAR(255) NULL,
                avatar_url VARCHAR(512) NULL,
                provider VARCHAR(32) NULL,
                provider_id VARCHAR(128) NULL,
                password_hash VARCHAR(255) NULL,
                mobile VARCHAR(20) NULL,
                designation_bn VARCHAR(120) NULL,
                institution_name VARCHAR(255) NULL,
                division VARCHAR(64) NULL,
                district VARCHAR(64) NULL,
                upazila VARCHAR(64) NULL,
                profile_completed_at TEXT NULL,
                is_admin INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )"
        );
        $canonical = [
            'id','phone','email','name','avatar_url','provider','provider_id',
            'password_hash','mobile','designation_bn','institution_name',
            'division','district','upazila','profile_completed_at','is_admin','created_at',
        ];
        $shared = array_values(array_intersect($canonical, $existingNames));
        $colList = '`' . implode('`,`', $shared) . '`';
        $pdo->exec("INSERT INTO users_new ($colList) SELECT $colList FROM users");
        $pdo->exec('DROP TABLE users');
        $pdo->exec('ALTER TABLE users_new RENAME TO users');
        $pdo->commit();
        $pdo->exec('PRAGMA foreign_keys=ON');
        // Recreate the indexes that were dropped along with the old table.
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_provider ON users(provider, provider_id)');
    } catch (PDOException $e) {
        try { $pdo->rollBack(); } catch (Throwable $_) {}
        error_log('db_migrate_users_phone_nullable (sqlite): ' . $e->getMessage());
    }
}

/** Prefix that turns `INSERT … INTO` into a "skip on conflict" insert on each engine. */
function db_insert_ignore(bool $mysql): string
{
    return $mysql ? 'INSERT IGNORE INTO' : 'INSERT OR IGNORE INTO';
}

function db_columns(PDO $pdo, string $table, bool $mysql): array
{
    try {
        if ($mysql) {
            $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
            $cols = [];
            foreach ($stmt->fetchAll() as $r) {
                $cols[] = $r['Field'] ?? '';
            }
            return $cols;
        }
        $stmt = $pdo->query("PRAGMA table_info($table)");
        $cols = [];
        foreach ($stmt->fetchAll() as $r) {
            $cols[] = $r['name'] ?? '';
        }
        return $cols;
    } catch (PDOException $e) {
        return [];
    }
}

function db_now(array $CONFIG): string
{
    // ISO-ish timestamp that both engines accept. We use local (Dhaka) time because
    // the PHP timezone is set in bootstrap.php and strtotime() parses these strings
    // as local time too — keeping read/write consistent.
    return date('Y-m-d H:i:s');
}

function db_seed_if_empty(array $CONFIG): void
{
    $pdo = db($CONFIG);
    $mysql = db_is_mysql($CONFIG);
    $ignore = db_insert_ignore($mysql);

    // Ensure admin user exists. We try email first (OAuth-friendly) and
    // fall back to phone (legacy OTP login).
    $adminEmail = trim((string)($CONFIG['admin_email'] ?? ''));
    $adminPhone = normalize_phone((string)($CONFIG['admin_phone'] ?? ''));
    $now = db_now($CONFIG);

    $existing = null;
    if ($adminEmail !== '') {
        $stmt = $pdo->prepare('SELECT id, is_admin FROM users WHERE email = ?');
        $stmt->execute([$adminEmail]);
        $existing = $stmt->fetch() ?: null;
    }
    if (!$existing && $adminPhone !== '') {
        $stmt = $pdo->prepare('SELECT id, is_admin FROM users WHERE phone = ?');
        $stmt->execute([$adminPhone]);
        $existing = $stmt->fetch() ?: null;
    }
    if (!$existing) {
        // INSERT-IGNORE in case of a race / partial seed.
        $pdo->prepare(
            "$ignore users (phone, email, name, is_admin, created_at) VALUES (?,?,?,?,?)"
        )->execute([$adminPhone ?: null, $adminEmail ?: null, 'Platform Admin', 1, $now]);
    } elseif (!$existing['is_admin']) {
        $pdo->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$existing['id']]);
    }

    // Seed default reserved slugs — idempotent via INSERT-IGNORE on the UNIQUE
    // (slug) constraint. Safe to run on every boot.
    $defaults = [
        'www','api','admin','cdn','assets','mail','app','dashboard','help','docs',
        'blog','status','support','ftp','ns1','ns2','mx','smtp','imap','pop','webmail',
        'test','staging','dev','auth','login','signup','register','demo','shop','store',
        'static','media','files','img','images','video','videos','bangladesh','bd',
        'bangla','official','government','gov','school','college','madrasa','university',
        'institution','smartschool','ready',
    ];
    $ins = $pdo->prepare("$ignore reserved_slugs (slug, reason, created_at) VALUES (?,?,?)");
    foreach ($defaults as $s) {
        $ins->execute([$s, 'system default', $now]);
    }

    // Seed demo institutions only if table is empty — and even then, use
    // INSERT-IGNORE on the (brand, slug) UNIQUE so a half-seeded table from a
    // previous crashed boot doesn't take down the whole bootstrap.
    $count = (int)$pdo->query('SELECT COUNT(*) AS c FROM institutions')->fetch()['c'];
    if ($count > 0) return;

    $seed = [
        // smartschool.bd
        ['smartschool.bd','drmc','Dhaka Residential Model College','ঢাকা রেসিডেনসিয়াল মডেল কলেজ','school','Dhaka','Dhaka','Mohammadpur','Mohammadpur, Dhaka-1207','108194','A government high school and intermediate college in Mohammadpur, Dhaka.','ঢাকার মোহাম্মদপুরে অবস্থিত একটি সরকারি বিদ্যালয় ও কলেজ।','verified'],
        ['smartschool.bd','viqarunnisa','Viqarunnisa Noon School and College','ভিকারুননিসা নূন স্কুল ও কলেজ','school','Dhaka','Dhaka',null,'Bailey Road, Dhaka','107796',null,null,'verified'],
        ['smartschool.bd','darulihsan','Darul Ihsan Madrasah','দারুল ইহসান মাদরাসা','madrasa','Sylhet','Sylhet',null,'Sylhet Sadar, Sylhet',null,'Qawmi madrasa offering tahfeez and kitab departments.',null,'verified'],
        ['smartschool.bd','littlestars','Little Stars Kindergarten','লিটল স্টারস কিন্ডারগার্টেন','kindergarten','Chattogram','Chattogram',null,'Agrabad, Chattogram',null,null,null,'verified'],
        // institution.bd
        ['institution.bd','buet','Bangladesh University of Engineering and Technology','বাংলাদেশ প্রকৌশল বিশ্ববিদ্যালয়','university','Dhaka','Dhaka',null,'Palashi, Dhaka-1000',null,'Premier engineering university of Bangladesh.',null,'verified'],
        ['institution.bd','dhakacollege','Dhaka College','ঢাকা কলেজ','college','Dhaka','Dhaka',null,'New Market, Dhaka-1205',null,null,null,'verified'],
        ['institution.bd','dhakapolytechnic','Dhaka Polytechnic Institute','ঢাকা পলিটেকনিক ইনস্টিটিউট','polytechnic','Dhaka','Dhaka',null,'Tejgaon, Dhaka',null,null,null,'verified'],
        ['institution.bd','brac','BRAC','ব্র্যাক','ngo','Dhaka','Dhaka',null,'Mohakhali, Dhaka',null,"World's largest NGO, operating in Bangladesh and beyond.",null,'verified'],
        // seeded placeholders (claimable)
        ['smartschool.bd','ngps','Nazrul Govt. Primary School','নজরুল সরকারি প্রাথমিক বিদ্যালয়','school','Dhaka','Dhaka',null,null,null,null,null,'seeded'],
        ['smartschool.bd','sirajganj-girls-high',"Sirajganj Girls' High School",'সিরাজগঞ্জ গার্লস হাই স্কুল','school','Rajshahi','Sirajganj',null,null,null,null,null,'seeded'],
        ['smartschool.bd','khulna-zilla-madrasa','Khulna Zilla Madrasah','খুলনা জিলা মাদ্রাসা','madrasa','Khulna','Khulna',null,null,null,null,null,'seeded'],
        ['institution.bd','rajshahi-university','University of Rajshahi','রাজশাহী বিশ্ববিদ্যালয়','university','Rajshahi','Rajshahi',null,null,null,null,null,'seeded'],
    ];
    $sql = "$ignore institutions
        (brand,slug,name_en,name_bn,category,division,district,upazila,address,eiin,about_en,about_bn,status,created_at,verified_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $pdo->prepare($sql);
    foreach ($seed as $r) {
        $verifiedAt = $r[12] === 'verified' ? $now : null;
        $stmt->execute([
            $r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],$r[7],$r[8],$r[9],$r[10],$r[11],$r[12],$now,$verifiedAt
        ]);
    }
}

function audit(array $CONFIG, ?int $actorId, ?int $instId, string $action, ?string $detail = null): void
{
    db($CONFIG)->prepare(
        'INSERT INTO audit_log (actor_user_id, institution_id, action, detail, created_at) VALUES (?,?,?,?,?)'
    )->execute([$actorId, $instId, $action, $detail, db_now($CONFIG)]);
}

/**
 * v3.2 — seed default "Support Developer" payment methods. Idempotent: only
 * runs when the table is empty, and admins can edit / hide / add more later
 * from the admin console.
 */
function db_seed_support_payments_if_empty(array $CONFIG): void
{
    $pdo = db($CONFIG);
    $mysql = db_is_mysql($CONFIG);
    $ignore = db_insert_ignore($mysql);
    try {
        $count = (int)$pdo->query('SELECT COUNT(*) c FROM support_payments')->fetch()['c'];
    } catch (PDOException $e) { return; }
    if ($count > 0) return;
    $now = db_now($CONFIG);
    $defaults = [
        ['bkash',  'bKash (Personal)', '01700000000',  'Send to this number using "Send Money".', null, 10, 1],
        ['nagad',  'Nagad (Personal)', '01700000000',  'Send via the Nagad app — Send Money.',     null, 20, 1],
        ['rocket', 'Rocket',           '01700000000-0','Send Money via the Rocket / DBBL app.',     null, 30, 0],
    ];
    $ins = $pdo->prepare(
        "$ignore support_payments
            (method, label, number, note, qr_url, sort_order, visible, created_at, updated_at)
          VALUES (?,?,?,?,?,?,?,?,?)"
    );
    foreach ($defaults as $d) {
        $ins->execute([$d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $d[6], $now, $now]);
    }
}

/* =================================================================== */
/*  v3.0 — runtime platform settings (admin-toggleable)                  */
/* =================================================================== */

/** Default values seeded into platform_settings on first read. */
function settings_defaults(): array
{
    return [
        // v4.1 — *Domain approval required.* When ON (default), every new
        // claim lands as `pending` and stays private until an admin
        // approves it. When OFF, claims auto-verify and Cloudflare DNS is
        // published immediately (the v3.0 instant-claim flow). This is
        // SEPARATE from `require_documents` — admins can require approval
        // without requiring documents (light-touch moderation), or require
        // documents without requiring approval (collect proof but don't
        // gate the launch).
        'require_approval'     => '1',
        // v4.1 — *Documents required.* When ON, the claim wizard prompts
        // the owner to upload at least one verification document (EIIN
        // certificate, board letter, NID, etc.) before the claim is
        // submitted. The dashboard "Documents" tab is always available to
        // verified owners, but this toggle controls whether the homepage
        // claim flow blocks submission on missing documents.
        'require_documents'    => '1',
        // Mirrors the above: when require_approval is ON, the homepage /
        // claim flow no longer pretends the subdomain is "instant". Admins
        // can flip this on independently if they want to advertise instant
        // claims even with admin moderation enabled.
        'instant_claim'        => '0',
        // Auto-create a Cloudflare DNS record on every claim that's verified
        // (by admin approval, or by instant-claim if it's re-enabled). Falls
        // back to a `manual` flag when CF isn't configured. Default ON.
        'cloudflare_auto_dns'  => '1',
        // v3.3 — manual email + password sign-up. When OFF, the homepage
        // auth modal hides the "Create account" tab and the /api/auth/register
        // endpoint returns 403, so visitors can only sign in (or sign up via
        // the configured OAuth providers). Default ON.
        'email_registration_enabled' => '1',
        // v4.1 — domain term length, in days. Used to compute expires_at
        // on every approve / instant-claim verify, and to extend on
        // renewal. 365 by default.
        'domain_term_days'     => '365',
        // v4.1 — domain renewal price in BDT (whole taka). 0 means free
        // renewals (auto-extend on owner click). Anything > 0 routes the
        // request through the admin's pending-renewals queue.
        'domain_renewal_price_bdt' => '0',
    ];
}

function settings_get_all(array $CONFIG): array
{
    $pdo = db($CONFIG);
    try {
        $rows = $pdo->query('SELECT skey, svalue FROM platform_settings')->fetchAll();
    } catch (PDOException $e) {
        $rows = [];
    }
    $out = settings_defaults();
    foreach ($rows as $r) { $out[$r['skey']] = (string)$r['svalue']; }
    return $out;
}

function settings_get_bool(array $CONFIG, string $key, bool $default = false): bool
{
    $all = settings_get_all($CONFIG);
    if (!array_key_exists($key, $all)) return $default;
    $v = strtolower(trim((string)$all[$key]));
    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}

/** v4.1 — read an integer setting (term days, renewal price, …). */
function settings_get_int(array $CONFIG, string $key, int $default = 0): int
{
    $all = settings_get_all($CONFIG);
    if (!array_key_exists($key, $all) || $all[$key] === null || $all[$key] === '') return $default;
    return (int)$all[$key];
}

function settings_set(array $CONFIG, string $key, string $value): void
{
    $pdo = db($CONFIG);
    $now = db_now($CONFIG);
    if (db_is_mysql($CONFIG)) {
        $pdo->prepare(
            'INSERT INTO platform_settings (skey, svalue, updated_at) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue), updated_at = VALUES(updated_at)'
        )->execute([$key, $value, $now]);
    } else {
        $pdo->prepare(
            'INSERT INTO platform_settings (skey, svalue, updated_at) VALUES (?,?,?)
             ON CONFLICT(skey) DO UPDATE SET svalue = excluded.svalue, updated_at = excluded.updated_at'
        )->execute([$key, $value, $now]);
    }
}
