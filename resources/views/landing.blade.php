<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VECTOR - Onboarding System</title>
    <link rel="icon" href="{{ asset('images/vector-logo.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>
<body class="dark">
    <!-- Animated Background -->
    <div class="bg-grid" id="bgGrid"></div>
    <div class="particles" id="particles"></div>
    <div class="mouse-glow" id="mouseGlow"></div>

    <!-- Navbar -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <a href="/" class="nav-logo">
                <img src="{{ asset('images/vector-logo.png') }}" alt="VECTOR" class="nav-logo-img">
                <span class="nav-logo-text">VECTOR</span>
            </a>
            <div class="nav-actions">
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                    <svg class="theme-icon sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="5"></circle>
                        <line x1="12" y1="1" x2="12" y2="3"></line>
                        <line x1="12" y1="21" x2="12" y2="23"></line>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                        <line x1="1" y1="12" x2="3" y2="12"></line>
                        <line x1="21" y1="12" x2="23" y2="12"></line>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                    </svg>
                    <svg class="theme-icon moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                </button>
                <span class="nav-version">v1.0</span>
            </div>
            <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <span class="nav-version" style="text-align:center;">v1.0</span>
    </div>

    <!-- Hero Section -->
    <section class="hero" id="hero">
        <div class="hero-glow"></div>
        <div class="hero-content">
            <div class="hero-logo-wrapper">
                <img src="{{ asset('images/vector-logo.png') }}" alt="VECTOR Logo" class="hero-logo" id="heroLogo">
            </div>
            <h1 class="hero-title" id="heroTitle">
                <span class="hero-title-line">A Clear Path From</span>
                <span class="hero-title-line hero-title-accent">Candidate to Employee</span>
            </h1>
            <p class="hero-subtitle" id="heroSubtitle">Structured journeys. Clear direction. Confident outcomes.</p>
        </div>
    </section>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="{{ asset('js/landing.js') }}"></script>
</body>
</html>
