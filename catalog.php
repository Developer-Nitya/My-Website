<?php
declare(strict_types=1);

require __DIR__ . '/includes/product-catalog.php';

$filters = [
    'keyword' => clean_text($_GET['keyword'] ?? '', 190),
    'level' => clean_text($_GET['level'] ?? '', 100),
    'class' => clean_text($_GET['class'] ?? '', 100),
    'subject' => clean_text($_GET['subject'] ?? '', 120),
    'type' => clean_text($_GET['type'] ?? '', 20),
];
$pageTitle = $filters['level'] !== '' ? level_page_title($filters['level']) : 'সব PPT কনটেন্ট';
$products = filter_catalog_products($filters);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | EduPPT BD</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="catalog-page-body">
<header class="main-header">
    <div class="container navbar-container">
        <a href="index.html" class="logo" aria-label="EduPPT BD">
            <img src="assets/images/header-logo.png" alt="EduPPT BD Logo" class="logo-img header-logo-img">
            <span class="logo-text">EduPPT <span>BD</span></span>
        </a>
        <nav class="nav-menu" id="navMenu">
            <a href="index.html#home" class="nav-link">Home</a>
            <a href="index.html#levels" class="nav-link">Levels</a>
            <a href="catalog.php?type=Free" class="nav-link">Free Sample</a>
            <a href="catalog.php?type=Premium" class="nav-link">Premium PPT</a>
            <a href="index.html#contact" class="nav-link">Contact</a>
        </nav>
    </div>
</header>

<section class="section-padding bg-light">
    <div class="container">
        <div class="catalog-hero-card">
            <h1 class="section-title" style="margin-bottom:20px;"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="catalog-hero-text">শ্রেণি, বিষয়, অধ্যায় বা কিওয়ার্ড দিয়ে খুঁজুন। প্রতিটি লেভেলের Explore এখন আলাদা page-এ ওপেন হবে।</p>
        </div>

        <div class="filter-container">
            <div class="filter-row main-search-row">
                <input type="text" id="keywordSearch" placeholder="টাইটেল, শ্রেণি, বিষয় বা অধ্যায় লিখে খুঁজুন..." value="<?= htmlspecialchars($filters['keyword'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="filter-row dropdowns-row">
                <select id="filterLevel">
                    <option value="">সব লেভেল (All Levels)</option>
                    <option value="Primary" <?= $filters['level'] === 'Primary' ? 'selected' : '' ?>>প্রাথমিক স্তর (Primary)</option>
                    <option value="Secondary" <?= $filters['level'] === 'Secondary' ? 'selected' : '' ?>>মাধ্যমিক স্তর (Secondary)</option>
                    <option value="Higher Secondary" <?= $filters['level'] === 'Higher Secondary' ? 'selected' : '' ?>>উচ্চ মাধ্যমিক স্তর (Higher Sec)</option>
                    <option value="Ebtedayee" <?= $filters['level'] === 'Ebtedayee' ? 'selected' : '' ?>>ইবতেদায়ী স্তর (Ebtedayee)</option>
                    <option value="Dakhil" <?= $filters['level'] === 'Dakhil' ? 'selected' : '' ?>>দাখিল স্তর (Dakhil)</option>
                </select>
                <select id="filterClass">
                    <option value="">সব শ্রেণি (All Classes)</option>
                    <?php for ($i = 1; $i <= 12; $i++): $value = 'Class ' . $i; ?>
                        <option value="<?= $value ?>" <?= $filters['class'] === $value ? 'selected' : '' ?>><?= $value ?></option>
                    <?php endfor; ?>
                </select>
                <select id="filterSubject">
                    <option value="">সব বিষয় (All Subjects)</option>
                    <?php
                    $subjects = [];
                    foreach (get_catalog_products() as $product) {
                        $subjects[(string) $product['subject']] = (string) $product['subject'];
                    }
                    ksort($subjects, SORT_NATURAL | SORT_FLAG_CASE);
                    foreach ($subjects as $subject):
                    ?>
                        <option value="<?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?>" <?= $filters['subject'] === $subject ? 'selected' : '' ?>><?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filterType">
                    <option value="">সব টাইপ (All Types)</option>
                    <option value="Premium" <?= $filters['type'] === 'Premium' ? 'selected' : '' ?>>Premium</option>
                    <option value="Free" <?= $filters['type'] === 'Free' ? 'selected' : '' ?>>Free</option>
                </select>
            </div>
            <div class="filter-row button-row">
                <button type="button" id="catalogSearchBtn" class="btn btn-gradient">Search</button>
                <button type="button" id="resetFiltersBtn" class="btn btn-reset">Reset Filters</button>
                <a href="index.html" class="btn btn-secondary">Back to Home</a>
            </div>
        </div>

        <div id="catalogSummary" class="catalog-summary-line"><?= count($products) ?>টি কনটেন্ট পাওয়া গেছে।</div>
        <div class="products-grid" id="productContainer"></div>
    </div>
</section>

<script>
window.__INITIAL_CATALOG_FILTERS__ = <?= json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.__INITIAL_CATALOG_PRODUCTS__ = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.__CATALOG_PAGE__ = true;
</script>
<script src="script.js"></script>
</body>
</html>
