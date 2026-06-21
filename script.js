
async function loadUpcomingSlidesCMS() {
    const wrapper = document.getElementById("servicesSlidesWrapper");
    if(!wrapper) return false;

    try {
        const res = await fetch("assets/upcoming-slides.json");
        const data = await res.json();

        wrapper.innerHTML = data.slides.map((s,i)=>`
            <div class="upcoming-slide premium-slide" data-index="${i}">
                <div class="slide-bg" style="background-image:url('${s.image}')"></div>
                <div class="slide-overlay"></div>
                <div class="slide-content">
                    <h3>${s.title}</h3>
                    <p>${s.desc}</p>
                    <span class="badge">UPCOMING</span>
                </div>
            </div>
        `).join("");

        return true;
    } catch(e){
        return false;
    }
}


function escapeHtml(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

function buildServicesShowcaseSlide(slide, index) {
    const backgroundClass = escapeHtml(slide.background_class || "gradient-blue slide-bg-curriculum");
    const badge = escapeHtml(slide.badge || "");
    const title = escapeHtml(slide.title || "");
    const desc = escapeHtml(slide.desc || "");
    const meta1Icon = escapeHtml(slide.meta1_icon || "fa-circle");
    const meta1Text = escapeHtml(slide.meta1_text || "");
    const meta2Icon = escapeHtml(slide.meta2_icon || "fa-circle");
    const meta2Text = escapeHtml(slide.meta2_text || "");
    const miniLabel = escapeHtml(slide.mini_label || "Preview");
    const cardTitle = escapeHtml(slide.card_title || "");
    const cardSubtitle = escapeHtml(slide.card_subtitle || "");
    const chipIcon = escapeHtml(slide.chip_icon || "fa-circle");
    const chipText = escapeHtml(slide.chip_text || "");
    const image = escapeHtml(slide.image || "");

    return `
        <article class="upcoming-slide ${backgroundClass}" aria-hidden="${index === 0 ? "false" : "true"}">
            <div class="upcoming-slide-inner">
                <div class="slide-copy">
                    <span class="slide-badge">${badge}</span>
                    <h3>${title}</h3>
                    <p>${desc}</p>
                    <div class="slide-meta-pills">
                        <span><i class="fa-solid ${meta1Icon}"></i> ${meta1Text}</span>
                        <span><i class="fa-solid ${meta2Icon}"></i> ${meta2Text}</span>
                    </div>
                </div>
                <div class="slide-visual" aria-hidden="true">
                    <div class="visual-card main">
                        <span class="visual-mini-label">${miniLabel}</span>
                        <img src="${image}" alt="" loading="lazy">
                        <div class="visual-card-copy">
                            <strong>${cardTitle}</strong>
                            <small>${cardSubtitle}</small>
                        </div>
                    </div>
                    <div class="visual-chip"><i class="fa-solid ${chipIcon}"></i> ${chipText}</div>
                </div>
            </div>
        </article>
    `;
}

async function loadServicesShowcaseSlides() {
    const wrapper = document.getElementById("servicesSlidesWrapper");
    const dotsContainer = document.querySelector("#upcomingShowcase .upcoming-dots-container");
    const counterTotal = document.querySelector("#upcomingShowcase .showcase-counter");

    if (!wrapper || !dotsContainer) {
        return false;
    }

    try {
        const response = await fetch("assets/services-showcase-slides.json", { cache: "no-store" });
        if (!response.ok) {
            return false;
        }

        const data = await response.json();
        const slides = Array.isArray(data.slides) ? [...data.slides] : [];
        if (!slides.length) {
            return false;
        }

        slides.sort((a, b) => Number(a.order || 0) - Number(b.order || 0));

        wrapper.innerHTML = slides.map((slide, index) => buildServicesShowcaseSlide(slide, index)).join("");
        dotsContainer.innerHTML = slides.map((_, index) => `
            <button
                type="button"
                class="dot ${index === 0 ? "active-dot" : ""}"
                aria-label="স্লাইড ${index + 1}"
                aria-selected="${index === 0 ? "true" : "false"}"
                data-slide="${index}"
            ></button>
        `).join("");

        const current = document.getElementById("upcomingCurrentSlide");
        if (current) {
            current.textContent = "01";
        }

        if (counterTotal) {
            counterTotal.innerHTML = `<span id="upcomingCurrentSlide">01</span>/${String(slides.length).padStart(2, "0")}`;
        }

        return true;
    } catch (error) {
        return false;
    }
}

/* START: Main JavaScript Section */
const WHATSAPP_NUMBER = "8801303329946";

let productsData = Array.isArray(window.__INITIAL_CATALOG_PRODUCTS__) && window.__INITIAL_CATALOG_PRODUCTS__.length
    ? window.__INITIAL_CATALOG_PRODUCTS__
    : [
        { id: 1, title: "Class 6 Science Chapter 1 PowerPoint Presentation", level: "Secondary", class: "Class 6", subject: "Science", chapter: "Scientific Process and Measurement", slides: 35, format: "PPTX", price: 150, type: "Premium", desc: "ষষ্ঠ শ্রেণির বিজ্ঞান বিষয়ের প্রথম অধ্যায়ের নিখুঁত ও আকর্ষণীয় প্রেজেন্টেশন স্লাইড।" },
        { id: 2, title: "Class 5 Mathematics Chapter 2 PowerPoint Presentation", level: "Primary", class: "Class 5", subject: "Mathematics", chapter: "Fraction", slides: 28, format: "PPTX", price: 120, type: "Premium", desc: "পঞ্চম শ্রেণির গণিত ভগ্নাংশ অধ্যায়ের সহজ চিত্রের মাধ্যমে জটিল টপিকসের ব্যাখ্যা।" },
        { id: 3, title: "Class 8 English Grammar PowerPoint Presentation", level: "Secondary", class: "Class 8", subject: "English", chapter: "Tense", slides: 32, format: "PPTX", price: 130, type: "Premium", desc: "Comprehensive structure templates of all tenses with clean board style layout." },
        { id: 4, title: "Class 9 Biology Chapter 1 PowerPoint Presentation", level: "Secondary", class: "Class 9", subject: "Biology", chapter: "Life and Cell", slides: 40, format: "PPTX", price: 180, type: "Premium", desc: "নবম শ্রেণির জীববিজ্ঞান কোষ ও জীবনের বিস্তারিত এনিমেশনযুক্ত চমৎকার কন্টেন্ট।" },
        { id: 5, title: "Class 11 Physics Chapter 1 PowerPoint Presentation", level: "Higher Secondary", class: "Class 11", subject: "Physics", chapter: "Physical World and Measurement", slides: 45, format: "PPTX", price: 220, type: "Premium", desc: "উচ্চ মাধ্যমিক পদার্থবিজ্ঞান ১ম পত্রের প্রথম অধ্যায়ের গাণিতিক ব্যাখ্যাসহ রেডি স্লাইড।" },
        { id: 6, title: "Ebtedayee Class 4 Quran Majid PowerPoint Presentation", level: "Ebtedayee", class: "Class 4", subject: "Quran Majid", chapter: "Selected Surah", slides: 25, format: "PPTX", price: 100, type: "Premium", desc: "ইবতেদায়ী ৪র্থ শ্রেণির কুরআন মাজিদ তাসবিদ ও অর্থসহ পঠন স্লাইড ডিজাইন।" },
        { id: 7, title: "Dakhil Class 9 Arabic PowerPoint Presentation", level: "Dakhil", class: "Class 9", subject: "Arabic", chapter: "Basic Grammar", slides: 35, format: "PPTX", price: 160, type: "Premium", desc: "দাখিল ৯ম শ্রেণির আরবি ব্যাকরণের সহজ নিয়মাবলি ও উদাহরণ সম্বলিত প্রেজেন্টেশন।" },
        { id: 8, title: "Class 6 ICT Free Sample PPT", level: "Secondary", class: "Class 6", subject: "ICT", chapter: "Introduction to ICT", slides: 10, format: "PPTX", price: "Free", type: "Free", desc: "আইসিটি পরিচিতি অধ্যায়ের সম্পূর্ণ ফ্রি স্যাম্পল স্লাইড ফাইল।", download_url: "download.php?sample=fallback-ict" },
        { id: 9, title: "Class 7 Bangladesh and Global Studies PPT", level: "Secondary", class: "Class 7", subject: "Bangladesh and Global Studies", chapter: "History and Culture", slides: 30, format: "PPTX", price: 140, type: "Premium", desc: "৭ম শ্রেণির বাংলাদেশ ও বিশ্বপরিচয় ইতিহাস ও সংস্কৃতির সুন্দর ম্যাপ সম্বলিত স্লাইড।" },
        { id: 10, title: "Class 10 Chemistry Chapter 2 PPT", level: "Secondary", class: "Class 10", subject: "Chemistry", chapter: "Structure of Atom", slides: 42, format: "PPTX", price: 190, type: "Premium", desc: "দশম শ্রেণির রসায়ন পরমাণুর গঠন অধ্যায়ের আকর্ষণীয় অরবিটাল ডায়াগ্রাম স্লাইড।" },
        { id: 11, title: "Class 12 Accounting PPT", level: "Higher Secondary", class: "Class 12", subject: "Accounting", chapter: "Financial Statement", slides: 38, format: "PPTX", price: 230, type: "Premium", desc: "দ্বাদশ শ্রেণির হিসাববিজ্ঞান আর্থিক বিবরণী প্রস্তুতকরণের ছক ও ম্যাথ সলিউশন।" },
        { id: 12, title: "Primary Class 3 Bangla Free Sample PPT", level: "Primary", class: "Class 3", subject: "Bangla", chapter: "Reading Practice", slides: 8, format: "PPTX", price: "Free", type: "Free", desc: "তৃতীয় শ্রেণির বাংলা পঠন দক্ষতার ফ্রি স্যাম্পল ইন্টারঅ্যাক্টিভ কনটেন্ট।", download_url: "download.php?sample=fallback-bangla" },
    ];

function normalizeText(value) {
    return String(value || "").trim().toLowerCase();
}

function buildCatalogQuery(filters = {}) {
    const params = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => {
        if (String(value || "").trim() !== "") {
            params.set(key, String(value).trim());
        }
    });
    return params.toString();
}

function navigateToCatalog(filters = {}) {
    const query = buildCatalogQuery(filters);
    window.location.href = `catalog.php${query ? `?${query}` : ""}`;
}

function openLevelPage(level) {
    navigateToCatalog({ level });
}

function filterByLevel(level) {
    if (window.__CATALOG_PAGE__) {
        document.getElementById("filterLevel").value = level;
        runCatalogFilters();
        return;
    }
    openLevelPage(level);
}

function filterBySubject(subject) {
    if (window.__CATALOG_PAGE__) {
        document.getElementById("filterSubject").value = subject;
        runCatalogFilters();
        return;
    }
    navigateToCatalog({ subject });
}

function filterByType(type) {
    if (window.__CATALOG_PAGE__) {
        document.getElementById("filterType").value = type;
        runCatalogFilters();
        return;
    }
    navigateToCatalog({ type });
}

function processWhatsAppOrder(id) {
    window.location.href = `checkout.php?id=${encodeURIComponent(id)}`;
}

async function loadProducts(filters = {}) {
    if (window.__INITIAL_CATALOG_PRODUCTS__ && window.__CATALOG_PAGE__) {
        const initial = productsData;
        window.__INITIAL_CATALOG_PRODUCTS__ = null;
        return initial;
    }

    try {
        const query = buildCatalogQuery(filters);
        const response = await fetch(`api/products.php${query ? `?${query}` : ""}`, {
            headers: { Accept: "application/json" },
        });

        if (!response.ok) {
            return productsData;
        }

        const payload = await response.json();
        if (payload && Array.isArray(payload.products)) {
            productsData = payload.products;
        }
        return productsData;
    } catch (error) {
        console.warn("Products API unavailable. Using built-in product data.");
        return productsData;
    }
}

function createProductCard(product) {
    const isFree = normalizeText(product.type) === "free";
    const buttonHtml = isFree
        ? `<a href="${product.download_url || 'download.php?sample=fallback'}" class="btn btn-secondary" style="width:100%; justify-content:center;"><i class="fa-solid fa-download"></i> Download Sample</a>`
        : `<button onclick="processWhatsAppOrder(${product.id})" class="btn btn-primary"><i class="fa-solid fa-shopping-cart"></i> Order Now</button>`;

    return `
        <div class="product-card">
            <span class="badge-tag ${isFree ? "badge-free" : "badge-premium"}">${product.type}</span>
            <div class="product-preview-placeholder"><i class="fa-solid fa-chalkboard-user"></i></div>
            <div class="product-details-content">
                <div class="product-meta-tags">
                    <span class="meta-tag">${product.level}</span>
                    <span class="meta-tag">${product.class}</span>
                    <span class="meta-tag">${product.subject}</span>
                </div>
                <h3>${product.title}</h3>
                <p class="product-desc">${product.desc || ""}</p>
                <div class="product-specifications">
                    <div class="spec-line"><span>অধ্যায়:</span> <strong>${product.chapter}</strong></div>
                    <div class="spec-line"><span>মোট স্লাইড:</span> <strong>${product.slides}টি</strong></div>
                    <div class="spec-line"><span>ফরম্যাট:</span> <strong>${product.format}</strong></div>
                </div>
                <div class="product-footer">
                    <div class="product-price">${isFree ? "Free" : `${product.price} BDT`}</div>
                    ${buttonHtml}
                </div>
            </div>
        </div>
    `;
}

function renderProducts(products) {
    const container = document.getElementById("productContainer");
    if (!container) {
        return;
    }

    if (!products.length) {
        container.innerHTML = `<p style="grid-column:1/-1; text-align:center; padding:50px; color:var(--muted-text);">কোনো কনটেন্ট পাওয়া যায়নি। অনুগ্রহ করে অন্য কিওয়ার্ড বা ফিল্টার ব্যবহার করুন।</p>`;
    } else {
        container.innerHTML = products.map(createProductCard).join("");
    }

    const summary = document.getElementById("catalogSummary");
    if (summary) {
        summary.textContent = `${products.length}টি কনটেন্ট পাওয়া গেছে।`;
    }
}

function getFilterValues() {
    return {
        keyword: document.getElementById("keywordSearch")?.value || "",
        level: document.getElementById("filterLevel")?.value || "",
        class: document.getElementById("filterClass")?.value || "",
        subject: document.getElementById("filterSubject")?.value || "",
        type: document.getElementById("filterType")?.value || "",
    };
}

async function runCatalogFilters() {
    const filters = getFilterValues();
    const products = await loadProducts(filters);
    renderProducts(products);

    if (window.__CATALOG_PAGE__) {
        const query = buildCatalogQuery(filters);
        const newUrl = `${window.location.pathname}${query ? `?${query}` : ""}`;
        window.history.replaceState({}, "", newUrl);
    }
}

function setupCatalogPage() {
    if (!document.getElementById("productContainer")) {
        return;
    }

    const searchButton = document.getElementById("catalogSearchBtn");
    const resetButton = document.getElementById("resetFiltersBtn");
    const keywordInput = document.getElementById("keywordSearch");

    if (searchButton) {
        searchButton.addEventListener("click", runCatalogFilters);
    }

    if (keywordInput) {
        keywordInput.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                event.preventDefault();
                runCatalogFilters();
            }
        });
    }

    ["filterLevel", "filterClass", "filterSubject", "filterType"].forEach((id) => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener("change", runCatalogFilters);
        }
    });

    if (resetButton) {
        resetButton.addEventListener("click", () => {
            ["keywordSearch", "filterLevel", "filterClass", "filterSubject", "filterType"].forEach((id) => {
                const element = document.getElementById(id);
                if (!element) return;
                if (element.tagName === "SELECT") {
                    element.value = "";
                } else {
                    element.value = "";
                }
            });
            runCatalogFilters();
        });
    }

    renderProducts(productsData);
}


function filterProductsLocally(filters = {}) {
    const keyword = normalizeText(filters.keyword);
    return productsData.filter((product) => {
        if (filters.level && normalizeText(product.level) !== normalizeText(filters.level)) return false;
        if (filters.class && normalizeText(product.class) !== normalizeText(filters.class)) return false;
        if (filters.subject && normalizeText(product.subject) !== normalizeText(filters.subject)) return false;
        if (filters.type && normalizeText(product.type) !== normalizeText(filters.type)) return false;

        if (!keyword) return true;

        const haystack = normalizeText([
            product.title,
            product.level,
            product.class,
            product.subject,
            product.chapter,
            product.desc,
            product.type,
            product.format,
            product.price,
        ].join(" "));
        return haystack.includes(keyword);
    });
}

function setupHomeFilterSection() {
    if (window.__CATALOG_PAGE__) {
        return;
    }

    const keywordInput = document.getElementById("keywordSearch");
    const resetButton = document.getElementById("resetFiltersBtn");
    const filterIds = ["filterLevel", "filterClass", "filterSubject", "filterType"];

    const runHomeFilters = () => {
        const filters = getFilterValues();
        renderProducts(filterProductsLocally(filters));
    };

    filterIds.forEach((id) => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener("change", runHomeFilters);
        }
    });

    if (keywordInput) {
        keywordInput.addEventListener("input", runHomeFilters);
        keywordInput.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                event.preventDefault();
                runHomeFilters();
            }
        });
    }

    if (resetButton) {
        resetButton.addEventListener("click", () => {
            ["keywordSearch", ...filterIds].forEach((id) => {
                const element = document.getElementById(id);
                if (!element) return;
                element.value = "";
            });
            renderProducts(productsData);
        });
    }
}


function setupHomeActions() {
    const heroSearchBtn = document.getElementById("heroSearchBtn");
    const heroSearchInput = document.getElementById("heroSearchInput");
    if (heroSearchBtn && heroSearchInput) {
        heroSearchBtn.addEventListener("click", () => {
            navigateToCatalog({ keyword: heroSearchInput.value });
        });
        heroSearchInput.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                event.preventDefault();
                navigateToCatalog({ keyword: heroSearchInput.value });
            }
        });
    }

    const burger = document.getElementById("hamburgerMenu");
    const menu = document.getElementById("navMenu");
    if (burger && menu) {
        burger.addEventListener("click", () => menu.classList.toggle("active"));
        document.querySelectorAll(".nav-link, .nav-btn").forEach((link) => {
            link.addEventListener("click", () => menu.classList.remove("active"));
        });
    }

    const contactForm = document.getElementById("customRequestForm");
    if (contactForm) {
        contactForm.addEventListener("submit", async (event) => {
            event.preventDefault();
            const submitBtn = contactForm.querySelector('button[type="submit"]');
            const defaultBtnHTML = submitBtn ? submitBtn.innerHTML : "";
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = "Sending...";
            }

            try {
                const formData = new FormData(contactForm);
                const response = await fetch("send-message.php", {
                    method: "POST",
                    body: formData,
                    headers: { Accept: "application/json" },
                });

                const data = await response.json();
                if (data && data.success) {
                    alert("ধন্যবাদ। আপনার বার্তা সফলভাবে পাঠানো হয়েছে।");
                    contactForm.reset();
                } else {
                    alert(data && data.message ? data.message : "দুঃখিত, বার্তা পাঠানো যায়নি।");
                }
            } catch (error) {
                alert("দুঃখিত, বার্তা পাঠানো যায়নি। hosting-এ PHP mail বা database configuration যাচাই করুন।");
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = defaultBtnHTML;
                }
            }
        });
    }

    const b2t = document.getElementById("backToTopBtn");
    if (b2t) {
        window.addEventListener("scroll", () => {
            b2t.style.display = window.scrollY > 400 ? "flex" : "none";
        });
        b2t.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));
    }
}

    // Intersection Observer for Smooth On-Scroll Animations
const observeElements = () => {
    const observerOptions = {
        root: null,
        threshold: 0.15, // Trigger when 15% of the element is visible
        rootMargin: "0px"
    };

    const scrollObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("active");
                observer.unobserve(entry.target); // Trigger animation only once
            }
        });
    }, observerOptions);

    // Track all target animation classes
    const targetElements = document.querySelectorAll(".animate-on-scroll");
    targetElements.forEach(el => scrollObserver.observe(el));
};


async function setupUpcomingShowcaseSlider() {
    const showcase = document.getElementById("upcomingShowcase");
    const wrapper = document.getElementById("servicesSlidesWrapper");

    await loadServicesShowcaseSlides();

    if (!showcase || !wrapper) {
        return;
    }

    const slides = Array.from(wrapper.querySelectorAll(".upcoming-slide"));
    const dots = Array.from(showcase.querySelectorAll(".upcoming-dots-container .dot"));
    const prevBtn = document.getElementById("upcomingPrevBtn");
    const nextBtn = document.getElementById("upcomingNextBtn");
    const progressBar = document.getElementById("upcomingProgressBar");
    const counter = document.getElementById("upcomingCurrentSlide");
    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const autoplayDelay = prefersReducedMotion ? 0 : 4800;

    if (!slides.length) {
        return;
    }

    let activeIndex = 0;
    let autoplayTimer = null;
    let touchStartX = 0;
    let touchEndX = 0;

    const renderSlide = () => {
        wrapper.style.transform = `translateX(-${activeIndex * 100}%)`;

        slides.forEach((slide, index) => {
            const isActive = index === activeIndex;
            slide.setAttribute("aria-hidden", isActive ? "false" : "true");
        });

        dots.forEach((dot, index) => {
            const isActive = index === activeIndex;
            dot.classList.toggle("active-dot", isActive);
            dot.setAttribute("aria-selected", isActive ? "true" : "false");
        });

        if (counter) {
            counter.textContent = String(activeIndex + 1).padStart(2, "0");
        }

        if (progressBar) {
            progressBar.style.width = `${100 / slides.length}%`;
            progressBar.style.transform = `translateX(${activeIndex * 100}%)`;
        }
    };

    const goToSlide = (nextIndex) => {
        activeIndex = (nextIndex + slides.length) % slides.length;
        renderSlide();
    };

    const stopAutoplay = () => {
        if (autoplayTimer) {
            window.clearInterval(autoplayTimer);
            autoplayTimer = null;
        }
    };

    const startAutoplay = () => {
        if (!autoplayDelay) {
            return;
        }

        stopAutoplay();
        autoplayTimer = window.setInterval(() => {
            goToSlide(activeIndex + 1);
        }, autoplayDelay);
    };

    window.jumpToServiceSlide = (slideIndex) => {
        goToSlide(slideIndex);
        startAutoplay();
    };

    dots.forEach((dot) => {
        dot.addEventListener("click", () => {
            const slideIndex = Number(dot.dataset.slide || 0);
            goToSlide(slideIndex);
            startAutoplay();
        });
    });

    prevBtn?.addEventListener("click", () => {
        goToSlide(activeIndex - 1);
        startAutoplay();
    });

    nextBtn?.addEventListener("click", () => {
        goToSlide(activeIndex + 1);
        startAutoplay();
    });

    showcase.addEventListener("mouseenter", stopAutoplay);
    showcase.addEventListener("mouseleave", startAutoplay);
    showcase.addEventListener("focusin", stopAutoplay);
    showcase.addEventListener("focusout", () => {
        window.setTimeout(() => {
            if (!showcase.contains(document.activeElement)) {
                startAutoplay();
            }
        }, 0);
    });

    showcase.addEventListener("keydown", (event) => {
        if (event.key === "ArrowLeft") {
            event.preventDefault();
            goToSlide(activeIndex - 1);
            startAutoplay();
        }

        if (event.key === "ArrowRight") {
            event.preventDefault();
            goToSlide(activeIndex + 1);
            startAutoplay();
        }
    });

    showcase.addEventListener("touchstart", (event) => {
        touchStartX = event.changedTouches[0]?.clientX || 0;
    }, { passive: true });

    showcase.addEventListener("touchend", (event) => {
        touchEndX = event.changedTouches[0]?.clientX || 0;
        const deltaX = touchEndX - touchStartX;

        if (Math.abs(deltaX) < 40) {
            return;
        }

        if (deltaX > 0) {
            goToSlide(activeIndex - 1);
        } else {
            goToSlide(activeIndex + 1);
        }

        startAutoplay();
    }, { passive: true });

    document.addEventListener("visibilitychange", () => {
        if (document.hidden) {
            stopAutoplay();
        } else {
            startAutoplay();
        }
    });

    renderSlide();
    startAutoplay();
}

document.addEventListener("DOMContentLoaded", async () => {
    setupHomeActions();
    observeElements();
    setupCatalogPage();
    setupUpcomingShowcaseSlider();

    if (!window.__CATALOG_PAGE__ && document.getElementById("productContainer")) {
        const products = await loadProducts();
        renderProducts(products);
        setupHomeFilterSection();
    }
});
/* END: Main JavaScript Section */
