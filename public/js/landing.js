/* ============================================
   VECTOR Landing Page - Hero + Mouse Effects
   ============================================ */

document.addEventListener('DOMContentLoaded', () => {
    createParticles();
    initNavbar();
    initThemeToggle();
    initMobileMenu();
    initHeroAnimations();
    initMouseGlow();
    initHeroTilt();
});

function createParticles() {
    const container = document.getElementById('particles');
    if (!container) return;
    const count = window.innerWidth < 768 ? 20 : 40;
    for (let i = 0; i < count; i++) {
        const p = document.createElement('div');
        p.classList.add('particle');
        const size = Math.random() * 5 + 2;
        p.style.width = size + 'px';
        p.style.height = size + 'px';
        p.style.left = Math.random() * 100 + '%';
        p.style.animationDuration = (Math.random() * 20 + 8) + 's';
        p.style.animationDelay = (Math.random() * 10) + 's';
        container.appendChild(p);
    }
}

function initNavbar() {
    const navbar = document.getElementById('navbar');
    if (!navbar) return;
    setTimeout(() => navbar.classList.add('visible'), 300);
    window.addEventListener('scroll', () => {
        navbar.classList.toggle('scrolled', window.pageYOffset > 50);
    }, { passive: true });
}

function initThemeToggle() {
    const toggle = document.getElementById('themeToggle');
    const html = document.documentElement;
    if (!toggle) return;
    if (localStorage.getItem('vector-theme') === 'light') {
        html.classList.remove('dark');
        html.classList.add('light');
    }
    toggle.addEventListener('click', () => {
        const isLight = html.classList.contains('light');
        html.classList.toggle('light', !isLight);
        html.classList.toggle('dark', isLight);
        localStorage.setItem('vector-theme', isLight ? 'dark' : 'light');
    });
}

function initMobileMenu() {
    const btn = document.getElementById('mobileMenuBtn');
    const menu = document.getElementById('mobileMenu');
    if (!btn || !menu) return;
    btn.addEventListener('click', () => {
        btn.classList.toggle('active');
        menu.classList.toggle('open');
    });
    menu.querySelectorAll('a').forEach(a => {
        a.addEventListener('click', () => {
            btn.classList.remove('active');
            menu.classList.remove('open');
        });
    });
}

function initHeroAnimations() {
    const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
    tl.to('.hero-logo-wrapper', { opacity: 1, scale: 1, duration: 1, ease: 'back.out(1.7)' })
      .to('.hero-title-line', { opacity: 1, y: 0, duration: 0.8, stagger: 0.15 }, '-=0.4')
      .to('.hero-subtitle', { opacity: 1, y: 0, duration: 0.6 }, '-=0.3');
}

/* ---- Mouse Glow Follow ---- */
function initMouseGlow() {
    const glow = document.getElementById('mouseGlow');
    if (!glow) return;

    let mouseX = window.innerWidth / 2;
    let mouseY = window.innerHeight / 2;
    let glowX = mouseX;
    let glowY = mouseY;

    document.addEventListener('mousemove', e => {
        mouseX = e.clientX;
        mouseY = e.clientY;
        glow.classList.add('active');
    });

    document.addEventListener('mouseleave', () => {
        glow.classList.remove('active');
    });

    function animate() {
        glowX += (mouseX - glowX) * 0.08;
        glowY += (mouseY - glowY) * 0.08;
        glow.style.left = glowX + 'px';
        glow.style.top = glowY + 'px';
        requestAnimationFrame(animate);
    }
    animate();
}

/* ---- Hero Logo Tilt on Mouse ---- */
function initHeroTilt() {
    const hero = document.getElementById('hero');
    const logo = document.getElementById('heroLogo');
    if (!hero || !logo || window.innerWidth < 768) return;

    hero.addEventListener('mousemove', e => {
        const rect = hero.getBoundingClientRect();
        const x = (e.clientX - rect.left) / rect.width - 0.5;
        const y = (e.clientY - rect.top) / rect.height - 0.5;
        logo.style.transform = `translateY(-12px) perspective(600px) rotateY(${x * 15}deg) rotateX(${-y * 15}deg) scale(1.05)`;
    });

    hero.addEventListener('mouseleave', () => {
        logo.style.transform = 'translateY(0) perspective(600px) rotateY(0) rotateX(0) scale(1)';
    });
}
