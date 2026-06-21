<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

function installer_current_db_config(): array
{
    $configFile = __DIR__ . '/config/database.php';
    $config = file_exists($configFile) ? require $configFile : require __DIR__ . '/config/database.php.example';

    return [
        'host' => (string) ($config['host'] ?? 'localhost'),
        'port' => (string) ($config['port'] ?? '3306'),
        'dbname' => (string) ($config['dbname'] ?? ''),
        'username' => (string) ($config['username'] ?? ''),
        'password' => (string) ($config['password'] ?? ''),
        'charset' => (string) ($config['charset'] ?? 'utf8mb4'),
    ];
}

function installer_is_placeholder_config(array $config): bool
{
    return $config['dbname'] === ''
        || str_contains($config['dbname'], 'your_database_')
        || str_contains($config['username'], 'your_database_')
        || str_contains($config['username'], 'your_database_user')
        || $config['host'] === '';
}

function installer_config_to_php(array $config): string
{
    $export = var_export([
        'host' => $config['host'],
        'port' => $config['port'],
        'dbname' => $config['dbname'],
        'username' => $config['username'],
        'password' => $config['password'],
        'charset' => $config['charset'],
    ], true);

    return "<?php\n"
        . "declare(strict_types=1);\n\n"
        . "return " . $export . ";\n";
}

function installer_write_db_config(array $config): void
{
    $configDir = __DIR__ . '/config';
    if (!is_dir($configDir)) {
        @mkdir($configDir, 0755, true);
    }

    $written = @file_put_contents(__DIR__ . '/config/database.php', installer_config_to_php($config), LOCK_EX);
    if ($written === false) {
        throw new RuntimeException('config/database.php লিখতে পারিনি। File permission যাচাই করুন।');
    }
}

function installer_connect(array $config): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['dbname'],
        $config['charset']
    );

    return new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function installer_try_self_disable(): bool
{
    $target = __DIR__ . '/setup-database.disabled.php';
    if (@rename(__FILE__, $target)) {
        return true;
    }
    return false;
}

$currentConfig = installer_current_db_config();
$setupCompleted = is_setup_locked();
$error = null;
$success = null;
$selfDisabled = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($setupCompleted) {
        $error = 'Setup already completed. Security কারণে setup script আর চালানো যাবে না।';
    } elseif (!verify_csrf_token($_POST['_csrf'] ?? null)) {
        $error = 'Session validation failed. আবার চেষ্টা করুন।';
    } else {
        try {
            $dbConfig = [
                'host' => clean_text($_POST['db_host'] ?? 'localhost', 120),
                'port' => preg_replace('/[^0-9]/', '', (string) ($_POST['db_port'] ?? '3306')) ?: '3306',
                'dbname' => clean_text($_POST['db_name'] ?? '', 120),
                'username' => clean_text($_POST['db_user'] ?? '', 120),
                'password' => (string) ($_POST['db_password'] ?? ''),
                'charset' => 'utf8mb4',
            ];

            if ($dbConfig['host'] === '' || $dbConfig['dbname'] === '' || $dbConfig['username'] === '') {
                throw new RuntimeException('Database host, name, user অবশ্যই দিতে হবে।');
            }

            $adminEmail = strtolower(clean_text($_POST['admin_email'] ?? '', 190));
            $adminPassword = (string) ($_POST['admin_password'] ?? '');
            $adminName = clean_text($_POST['admin_name'] ?? 'Website Admin', 150);

            if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Valid admin email দিন।');
            }

            if (strlen($adminPassword) < 10) {
                throw new RuntimeException('Admin password কমপক্ষে 10 অক্ষরের হতে হবে।');
            }

            installer_write_db_config($dbConfig);
            $pdo = installer_connect($dbConfig);

            $schema = file_get_contents(__DIR__ . '/database/schema.sql');
            if ($schema === false) {
                throw new RuntimeException('Could not read database/schema.sql');
            }
            $pdo->exec($schema);

            $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $adminEmail]);
            $adminId = $stmt->fetchColumn();

            if ($adminId) {
                throw new RuntimeException('এই email দিয়ে admin ইতোমধ্যে আছে।');
            }

            $insert = $pdo->prepare('INSERT INTO admins (full_name, email, password_hash, role) VALUES (:full_name, :email, :password_hash, :role)');
            $insert->execute([
                'full_name' => $adminName,
                'email' => $adminEmail,
                'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
                'role' => 'super_admin',
            ]);
            $adminId = (int) $pdo->lastInsertId();

            $pdo->prepare("
                INSERT INTO site_settings (setting_key, setting_value)
                VALUES ('setup_completed_at', :setting_value)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ")->execute(['setting_value' => date('Y-m-d H:i:s')]);

            $existingCount = (int) $pdo->query('SELECT COUNT(*) FROM content_items')->fetchColumn();
            if ($existingCount === 0) {
                $seedProducts = [
                    ['Class 6 Science Chapter 1 PowerPoint Presentation','class-6-science-chapter-1-powerpoint-presentation','ppt','premium','Secondary','Class 6','Science','Scientific Process and Measurement',35,'PPTX',150,'ষষ্ঠ শ্রেণির বিজ্ঞান বিষয়ের প্রথম অধ্যায়ের নিখুঁত ও আকর্ষণীয় প্রেজেন্টেশন স্লাইড।',1],
                    ['Class 5 Mathematics Chapter 2 PowerPoint Presentation','class-5-mathematics-chapter-2-powerpoint-presentation','ppt','premium','Primary','Class 5','Mathematics','Fraction and Decimals',28,'PPTX',120,'পঞ্চম শ্রেণির গণিতের ভগ্নাংশ ও দশমিক অধ্যায়ের ভিজ্যুয়াল প্রেজেন্টেশন।',1],
                    ['SSC ICT Chapter 3 Animated Presentation','ssc-ict-chapter-3-animated-presentation','ppt','premium','Secondary','SSC','ICT','Number System and Digital Device',42,'PPTX',220,'এসএসসি আইসিটি তৃতীয় অধ্যায়ের অ্যানিমেটেড স্লাইড সেট।',1],
                ];

                $seedStmt = $pdo->prepare("
                    INSERT INTO content_items
                    (title, slug, content_type, access_type, education_level, class_name, subject_name, chapter_name, slide_count, file_format, price, short_description, is_featured, status, created_by, updated_by, published_at)
                    VALUES
                    (:title, :slug, :content_type, :access_type, :education_level, :class_name, :subject_name, :chapter_name, :slide_count, :file_format, :price, :short_description, :is_featured, 'published', :created_by, :updated_by, NOW())
                ");

                foreach ($seedProducts as $product) {
                    $seedStmt->execute([
                        'title' => $product[0],
                        'slug' => $product[1],
                        'content_type' => $product[2],
                        'access_type' => $product[3],
                        'education_level' => $product[4],
                        'class_name' => $product[5],
                        'subject_name' => $product[6],
                        'chapter_name' => $product[7],
                        'slide_count' => $product[8],
                        'file_format' => $product[9],
                        'price' => $product[10],
                        'short_description' => $product[11],
                        'is_featured' => $product[12],
                        'created_by' => $adminId,
                        'updated_by' => $adminId,
                    ]);
                }
            }

            record_admin_audit($pdo, $adminId, 'setup_completed', 'system', null, [
                'admin_email' => $adminEmail,
                'database' => $dbConfig['dbname'],
                'host' => $dbConfig['host'],
            ]);

            write_setup_lock();
            $setupCompleted = true;
            $currentConfig = $dbConfig;
            $selfDisabled = installer_try_self_disable();

            $success = 'Database setup completed successfully. এখন admin/login.php দিয়ে login করতে পারবেন।';
            if ($selfDisabled) {
                $success .= ' Setup file স্বয়ংক্রিয়ভাবে disable করা হয়েছে।';
            } else {
                $success .= ' Security এর জন্য setup-database.php ফাইলটি delete করে দিন।';
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$generatedPassword = generate_random_password();
?><!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup Database | EduPPT BD</title>
    <style>
        body{font-family:"Hind Siliguri",Arial,sans-serif;background:#f8fafc;margin:0;padding:24px}
        .wrap{max-width:820px;margin:0 auto}
        .card{background:#fff;border-radius:20px;padding:28px;box-shadow:0 20px 55px rgba(15,23,42,.12)}
        h1{margin-top:0;color:#0f172a}
        h2{margin:22px 0 10px;color:#0f172a;font-size:20px}
        p,li{color:#475569;line-height:1.7}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        @media (max-width:700px){.grid{grid-template-columns:1fr}}
        label{display:block;margin:14px 0 6px;font-weight:700;color:#1e293b}
        input{width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid #cbd5e1;border-radius:12px}
        button{margin-top:18px;padding:12px 18px;border:none;border-radius:12px;background:#2563eb;color:#fff;font-size:16px;font-weight:700;cursor:pointer}
        .error{margin-top:12px;background:#fee2e2;color:#991b1b;padding:12px;border-radius:12px}
        .success{margin-top:12px;background:#dcfce7;color:#166534;padding:12px;border-radius:12px}
        .note{margin-top:16px;font-size:14px;background:#eff6ff;padding:12px;border-radius:12px}
        code{background:#f1f5f9;padding:2px 6px;border-radius:6px}
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Database Setup</h1>
            <p>এই setup page ব্যবহার করে database table import, config/database.php তৈরি এবং admin account creation — সব একসাথে হবে। Public website-এর design/layout এতে পরিবর্তন হবে না।</p>

            <?php if ($error): ?><div class="error"><?= esc($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?= esc($success) ?></div><?php endif; ?>

            <?php if ($setupCompleted): ?>
                <div class="note">
                    Setup already completed.<br>
                    পরবর্তী ধাপ:
                    <ol>
                        <li><code>admin/login.php</code> এ login করুন</li>
                        <li>প্রয়োজনে <code>setup-database.php</code> delete করুন</li>
                        <li>Hosting SSL certificate active থাকলে HTTPS redirect স্বয়ংক্রিয়ভাবে কাজ করবে</li>
                    </ol>
                </div>
            <?php else: ?>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="_csrf" value="<?= esc(csrf_token()) ?>">

                    <h2>Database Information</h2>
                    <div class="grid">
                        <div>
                            <label for="db_host">Database Host</label>
                            <input id="db_host" name="db_host" value="<?= esc($currentConfig['host']) ?>" required>
                        </div>
                        <div>
                            <label for="db_port">Database Port</label>
                            <input id="db_port" name="db_port" value="<?= esc($currentConfig['port']) ?>" required>
                        </div>
                        <div>
                            <label for="db_name">Database Name</label>
                            <input id="db_name" name="db_name" value="<?= esc(installer_is_placeholder_config($currentConfig) ? '' : $currentConfig['dbname']) ?>" required>
                        </div>
                        <div>
                            <label for="db_user">Database User</label>
                            <input id="db_user" name="db_user" value="<?= esc(installer_is_placeholder_config($currentConfig) ? '' : $currentConfig['username']) ?>" required>
                        </div>
                    </div>

                    <label for="db_password">Database Password</label>
                    <input id="db_password" type="password" name="db_password" value="<?= esc(installer_is_placeholder_config($currentConfig) ? '' : $currentConfig['password']) ?>" required>

                    <h2>Admin Information</h2>
                    <div class="grid">
                        <div>
                            <label for="admin_name">Admin Name</label>
                            <input id="admin_name" name="admin_name" value="Website Admin" required>
                        </div>
                        <div>
                            <label for="admin_email">Admin Email</label>
                            <input id="admin_email" type="email" name="admin_email" placeholder="admin@yourdomain.com" required>
                        </div>
                    </div>

                    <label for="admin_password">Admin Password</label>
                    <input id="admin_password" type="text" name="admin_password" value="<?= esc($generatedPassword) ?>" required>

                    <button type="submit">Run Secure Setup</button>
                </form>

                <div class="note">
                    এই installer যা করবে:
                    <ol>
                        <li><code>config/database.php</code> স্বয়ংক্রিয়ভাবে লিখবে</li>
                        <li><code>database/schema.sql</code> import করবে</li>
                        <li>Admin account তৈরি করবে</li>
                        <li>Setup lock করবে</li>
                    </ol>
                    SSL certificate hosting panel/cPanel থেকে চালু করতে হবে। সেটি active থাকলে এই package-এর HTTPS redirect rule কাজ করবে।
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
