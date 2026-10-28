// ICCT IT Help Desk - front-end interactions
document.addEventListener('DOMContentLoaded', () => {
    // ---------- Navbar background on scroll (landing page) ----------
    const navbar = document.getElementById('navbar');
    if (navbar) {
        const onScroll = () => {
            const scrolled = window.scrollY > 20;
            navbar.classList.toggle('bg-brand-950/90', scrolled);
            navbar.classList.toggle('backdrop-blur', scrolled);
            navbar.classList.toggle('shadow-lg', scrolled);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    // ---------- Mobile menu ----------
    const menuBtn = document.getElementById('menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    if (menuBtn && mobileMenu) {
        menuBtn.addEventListener('click', () => {
            const isOpen = !mobileMenu.classList.toggle('hidden');
            menuBtn.setAttribute('aria-expanded', String(isOpen));
            navbar?.classList.add('bg-brand-950/90');
        });
        mobileMenu.querySelectorAll('a[href^="#"]').forEach((link) =>
            link.addEventListener('click', () => mobileMenu.classList.add('hidden'))
        );
    }

    // ---------- App sidebar (mobile) ----------
    const sidebar = document.getElementById('sidebar');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');
    const setSidebar = (open) => {
        sidebar?.classList.toggle('-translate-x-full', !open);
        sidebarBackdrop?.classList.toggle('hidden', !open);
    };
    document.getElementById('sidebar-toggle')?.addEventListener('click', () => setSidebar(true));
    sidebarBackdrop?.addEventListener('click', () => setSidebar(false));

    // ---------- Dropdowns (notifications, user menu) ----------
    const dropdowns = document.querySelectorAll('[data-dropdown]');
    const closeDropdowns = (except) => dropdowns.forEach((dropdown) => {
        if (dropdown === except) return;
        dropdown.querySelector('[data-dropdown-menu]').classList.add('hidden');
        dropdown.querySelector('[data-dropdown-toggle]').setAttribute('aria-expanded', 'false');
    });
    dropdowns.forEach((dropdown) => {
        const toggle = dropdown.querySelector('[data-dropdown-toggle]');
        const menu = dropdown.querySelector('[data-dropdown-menu]');
        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            closeDropdowns(dropdown);
            const open = menu.classList.toggle('hidden') === false;
            toggle.setAttribute('aria-expanded', String(open));
        });
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-dropdown]')) closeDropdowns();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeDropdowns();
            setSidebar(false);
        }
    });

    // ---------- Confirm before destructive form actions ----------
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });

    // ---------- Loading state on submit ----------
    // Disables the submit button and shows a spinner so the form can't be sent twice
    const showLoading = (form) => {
        const button = form.querySelector('button:not([type=button])');
        if (!button) return;
        button.disabled = true;
        button.classList.add('inline-flex', 'items-center', 'justify-center', 'gap-2');
        button.innerHTML =
            '<svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/><path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg><span>Please wait…</span>';
    };

    document.querySelectorAll('form[data-loading]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!event.defaultPrevented && form.checkValidity()) showLoading(form);
        });
    });

    // ---------- Clickable table rows ----------
    document.querySelectorAll('tr[data-href]').forEach((row) => {
        row.addEventListener('click', (event) => {
            if (!event.target.closest('a, button, input, select, textarea')) {
                window.location.href = row.dataset.href;
            }
        });
    });

    // ---------- Reveal on scroll ----------
    const revealEls = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });
        revealEls.forEach((el) => observer.observe(el));
    } else {
        revealEls.forEach((el) => el.classList.add('is-visible'));
    }

    // ---------- Show / hide password ----------
    document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.togglePassword);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.classList.toggle('text-brand-600', show);
        });
    });

    // ---------- Password strength meter ----------
    const strengthLevels = [
        { label: 'Too weak', color: 'bg-red-500' },
        { label: 'Weak', color: 'bg-orange-500' },
        { label: 'Good', color: 'bg-yellow-500' },
        { label: 'Strong', color: 'bg-emerald-500' },
    ];

    document.querySelectorAll('[data-strength]').forEach((input) => {
        const meter = document.getElementById(input.dataset.strength);
        const bars = meter.querySelectorAll('span');
        const label = meter.querySelector('[data-strength-label]');
        const defaultText = label.textContent;

        input.addEventListener('input', () => {
            const value = input.value;
            let score = 0;
            if (value.length >= 8) score++;
            if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score++;
            if (/\d/.test(value)) score++;
            if (/[^A-Za-z0-9]/.test(value) || value.length >= 12) score++;

            bars.forEach((bar, i) => {
                bar.className = 'h-1.5 flex-1 rounded-full ' +
                    (value && i < score ? strengthLevels[score - 1].color : 'bg-slate-200');
            });
            label.textContent = value ? strengthLevels[Math.max(score, 1) - 1].label : defaultText;
        });
    });

    // ---------- Image attachments: preview and size/count checks ----------
    document.querySelectorAll('[data-image-input]').forEach((input) => {
        const preview = input.parentElement.querySelector('[data-image-preview]');
        const maxFiles = Number(input.dataset.maxFiles);
        const maxBytes = Number(input.dataset.maxBytes);
        let urls = [];

        input.addEventListener('change', () => {
            urls.forEach((url) => URL.revokeObjectURL(url));
            urls = [];
            preview.innerHTML = '';

            const files = Array.from(input.files);
            let message = '';
            if (files.length > maxFiles) {
                message = `You can attach up to ${maxFiles} images.`;
            } else {
                const tooBig = files.find((file) => file.size > maxBytes);
                if (tooBig) message = `"${tooBig.name}" is larger than ${maxBytes / 1024 / 1024} MB.`;
            }
            input.setCustomValidity(message);
            showError(input, message);

            files.slice(0, maxFiles).forEach((file) => {
                const url = URL.createObjectURL(file);
                urls.push(url);
                const img = document.createElement('img');
                img.src = url;
                img.alt = file.name;
                img.className = 'aspect-[4/3] w-full rounded-xl object-cover ring-1 ring-slate-200';
                preview.appendChild(img);
            });
        });
    });

    // ---------- Client-side form validation ----------
    const showError = (field, message) => {
        const wrapper = field.closest('div:has(> .field-error)') || field.closest('div');
        const errorEl = wrapper?.querySelector('.field-error');
        field.classList.toggle('border-red-400', Boolean(message));
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.classList.toggle('hidden', !message);
        }
    };

    const validateField = (field) => {
        let message = '';
        if (field.type === 'checkbox') {
            if (field.required && !field.checked) message = 'Please check this box to continue.';
        } else if (field.type === 'file') {
            message = field.validationMessage;
        } else if (field.required && !field.value.trim()) {
            message = 'This field is required.';
        } else if (field.type === 'email' && field.value && !field.validity.valid) {
            message = 'Please enter a valid email address.';
        } else if (field.minLength > 0 && field.value.length < field.minLength) {
            message = `Must be at least ${field.minLength} characters.`;
        } else if (field.pattern && field.validity.patternMismatch) {
            message = '4–30 characters: letters, numbers, and dashes only.';
        } else if (field.name === 'password' && field.dataset.strength &&
            !(/[A-Z]/.test(field.value) && /[a-z]/.test(field.value) && /\d/.test(field.value))) {
            message = 'Include uppercase, lowercase, and a number.';
        } else if (field.dataset.match) {
            const other = document.getElementById(field.dataset.match);
            if (other && field.value !== other.value) message = 'Passwords do not match.';
        }
        showError(field, message);
        return !message;
    };

    document.querySelectorAll('form[data-validate]').forEach((form) => {
        const fields = form.querySelectorAll('input:not([type=hidden]):not([type=radio]), select, textarea');

        fields.forEach((field) => {
            field.addEventListener('blur', () => validateField(field));
            field.addEventListener('input', () => {
                if (field.classList.contains('border-red-400')) validateField(field);
            });
        });

        form.addEventListener('submit', (event) => {
            const results = Array.from(fields).map(validateField);
            if (results.includes(false)) {
                event.preventDefault();
                form.querySelector('.border-red-400')?.focus();
                return;
            }
            showLoading(form);
        });
    });
});
