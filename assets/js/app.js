// ============================================
// SCROLLR - Main App JS
// ============================================

const API = '/api';

// --- STATE ---
let state = {
    user:    null,
    token:   localStorage.getItem('scrollr_token'),
    posts:   [],
    page:    1,
    loading: false,
    hasMore: true,
    view:    'feed',
};

// --- API HELPER ---
async function api(path, method = 'GET', body = null, isForm = false) {
    const opts = {
        method,
        headers: { 'X-Auth-Token': state.token || '' },
    };
    if (body) {
        if (isForm) {
            opts.body = body;
        } else {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
    }
    const res  = await fetch(API + path, opts);
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Błąd serwera');
    return data;
}

// --- TOAST ---
function toast(msg, type = 'info', duration = 3000) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className   = `show ${type}`;
    clearTimeout(el._t);
    el._t = setTimeout(() => el.className = '', duration);
}

// ============================================
// ROUTER
// ============================================
const PROTECTED = ['upload', 'ai', 'profile'];

function showView(name) {
    // Chroń widoki wymagające logowania
    if (PROTECTED.includes(name) && !state.user) {
        showScreen('auth');
        return;
    }

    state.view = name;
    document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
    document.querySelectorAll('.bnav-btn').forEach(b => b.classList.remove('active'));

    const view = document.getElementById(`view-${name}`);
    if (view) view.classList.add('active');

    const btn = document.querySelector(`[data-view="${name}"]`);
    if (btn) btn.classList.add('active');

    if (name === 'feed' && state.posts.length === 0) loadFeed();
    if (name === 'profile') renderProfile();

    const topNav = document.getElementById('top-nav');
    topNav.style.display = name === 'profile' ? 'none' : 'flex';
}

// Przełącza główne ekrany: 'auth' | 'app'
function showScreen(screen) {
    document.getElementById('auth-screen').style.display = screen === 'auth' ? 'flex' : 'none';
    document.getElementById('main-app').style.display    = screen === 'app'  ? 'flex' : 'none';

    if (screen === 'app') {
        updateNavForUser();
        showView('feed');
    }
}

// Aktualizuje dolną nawigację po zalogowaniu
function updateNavForUser() {
    const profileBtn = document.querySelector('[data-view="profile"]');
    if (profileBtn && state.user) {
        const initial = state.user.username.charAt(0).toUpperCase();
        profileBtn.innerHTML = `
            <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));
                display:grid;place-items:center;font-weight:700;font-size:12px;color:#000">${initial}</div>
            ${state.user.username}`;
    }
}

// ============================================
// FEED
// ============================================
async function loadFeed(reset = false) {
    if (state.loading || (!state.hasMore && !reset)) return;

    if (reset) {
        state.posts = []; state.page = 1; state.hasMore = true;
        document.getElementById('feed').innerHTML =
            '<div class="loader"><div class="spinner"></div><span>Ładowanie...</span></div>';
    }

    state.loading = true;

    try {
        const res = await api(`/posts?page=${state.page}&limit=5`);
        state.posts   = reset ? res.data.posts : [...state.posts, ...res.data.posts];
        state.hasMore = res.data.has_more;
        state.page++;
        renderFeed(reset);
    } catch (e) {
        toast('Błąd ładowania postów', 'error');
        document.getElementById('feed').innerHTML =
            `<div class="empty"><p>${e.message}</p></div>`;
    } finally {
        state.loading = false;
    }
}

function renderFeed(reset = false) {
    const feed = document.getElementById('feed');
    if (reset) feed.innerHTML = '';

    const newPosts = reset ? state.posts : state.posts.slice(-5);

    if (newPosts.length === 0 && reset) {
        feed.innerHTML = `
            <div class="empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
                <h3>Brak postów</h3>
                <p>Zaloguj się i dodaj pierwszy post lub wygeneruj go przez AI!</p>
            </div>`;
        return;
    }

    newPosts.forEach(post => feed.appendChild(createPostCard(post)));
}

function createPostCard(post) {
    const div = document.createElement('div');
    const hasMedia = !!post.media_url;
    const isImage  = hasMedia && /\.(jpg|jpeg|png|gif|webp)$/i.test(post.media_url);

    div.className = `post-card type-${post.type} ${hasMedia ? 'has-media' : ''}`;

    if (!hasMedia) {
        const colors = ['#e8c97d','#7d9fe8','#9de87d','#e87d9d'];
        const c1 = colors[Math.floor(Math.random() * colors.length)];
        const c2 = colors[Math.floor(Math.random() * colors.length)];
        div.innerHTML += `<div class="post-accent" style="background:${c1}"></div>
                          <div class="post-accent-2" style="background:${c2}"></div>`;
    }

    if (isImage) {
        div.innerHTML += `<img class="post-bg" src="${post.media_url}" alt="media">`;
    }

    const aiTag = post.type === 'ai'
        ? `<div class="ai-badge">
               <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                   <path d="M12 2a10 10 0 100 20A10 10 0 0012 2zm0 18a8 8 0 110-16 8 8 0 010 16zm-1-5h2v2h-2zm0-8h2v6h-2z"/>
               </svg> AI Generated
           </div>`
        : '';

    const tagsHtml = (post.tags || []).map(t => `<span class="post-tag">#${t}</span>`).join('');
    const initials = (post.username || 'U').charAt(0).toUpperCase();
    const timeAgo  = formatTime(post.created_at);

    div.innerHTML += `
        <div class="post-body">
            ${aiTag}
            ${tagsHtml ? `<div class="post-tags">${tagsHtml}</div>` : ''}
            ${post.title   ? `<h2 class="post-title">${post.title}</h2>` : ''}
            ${post.content ? `<p class="post-content">${post.content}</p>` : ''}
            <div class="post-meta">
                <div class="post-avatar">
                    ${post.avatar ? `<img src="${post.avatar}" alt="">` : initials}
                </div>
                <div>
                    <div class="post-author">@${post.username}</div>
                    <div class="post-time">${timeAgo}</div>
                </div>
            </div>
        </div>

        <div class="post-actions">
            <button class="action-btn ${post.liked ? 'liked' : ''}"
                    onclick="toggleLike(this, ${post.id})" title="Polub">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                     fill="${post.liked ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
                <span class="action-count">${post.likes_count}</span>
            </button>
            <button class="action-btn" onclick="sharePost(${post.id})" title="Udostępnij">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                </svg>
            </button>
        </div>`;

    return div;
}

// Infinite scroll
document.addEventListener('DOMContentLoaded', () => {
    const feed = document.getElementById('feed');
    feed.addEventListener('scroll', () => {
        if (feed.scrollTop + feed.clientHeight >= feed.scrollHeight - 300) loadFeed();
    });
});

// ============================================
// LIKE
// ============================================
async function toggleLike(btn, postId) {
    if (!state.user) {
        toast('Zaloguj się aby polubić ❤️', 'error');
        return;
    }
    try {
        const res   = await api(`/posts/${postId}/like`, 'POST');
        const liked = res.data.liked;
        const count = btn.querySelector('.action-count');
        count.textContent = liked
            ? parseInt(count.textContent) + 1
            : Math.max(0, parseInt(count.textContent) - 1);
        btn.classList.toggle('liked', liked);
        btn.querySelector('svg').setAttribute('fill', liked ? 'currentColor' : 'none');
        toast(liked ? '❤️ Polubiono!' : 'Polubienie usunięte');
    } catch (e) {
        toast(e.message, 'error');
    }
}

// ============================================
// SHARE
// ============================================
async function sharePost(postId) {
    const url = `${location.origin}/?post=${postId}`;
    if (navigator.share) {
        await navigator.share({ title: 'Scrollr', url });
    } else {
        await navigator.clipboard.writeText(url);
        toast('Link skopiowany!', 'success');
    }
}

// ============================================
// UPLOAD
// ============================================
function initUploadForm() {
    const form     = document.getElementById('upload-form');
    const dropZone = document.getElementById('file-drop');
    const fileIn   = document.getElementById('file-input');
    const preview  = document.getElementById('file-preview');

    dropZone.addEventListener('click', () => fileIn.click());
    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag'));
    dropZone.addEventListener('drop', e => {
        e.preventDefault(); dropZone.classList.remove('drag');
        fileIn.files = e.dataTransfer.files;
        showPreview(e.dataTransfer.files[0]);
    });
    fileIn.addEventListener('change', () => showPreview(fileIn.files[0]));

    function showPreview(file) {
        if (!file) return;
        const url = URL.createObjectURL(file);
        preview.innerHTML = file.type.startsWith('image/')
            ? `<img src="${url}" style="max-width:100%;border-radius:10px;margin-top:12px">`
            : `<video src="${url}" controls style="max-width:100%;border-radius:10px;margin-top:12px"></video>`;
    }

    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (!state.user) { showScreen('auth'); return; }

        const btn = form.querySelector('.btn-primary');
        btn.disabled = true; btn.textContent = 'Dodawanie...';

        const fd = new FormData();
        fd.append('type',    document.getElementById('post-type').value);
        fd.append('title',   document.getElementById('post-title').value);
        fd.append('content', document.getElementById('post-content').value);
        fd.append('tags',    document.getElementById('post-tags').value);
        if (fileIn.files[0]) fd.append('media', fileIn.files[0]);

        try {
            await api('/posts', 'POST', fd, true);
            toast('Post dodany! 🎉', 'success');
            form.reset(); preview.innerHTML = '';
            state.posts = []; state.page = 1; state.hasMore = true;
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            btn.disabled = false; btn.textContent = 'Opublikuj post';
        }
    });
}

// ============================================
// AI GENERATOR
// ============================================
function initAiForm() {
    const form = document.getElementById('ai-form');
    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (!state.user) { showScreen('auth'); return; }

        const btn    = form.querySelector('.btn-ai');
        const prompt = document.getElementById('ai-prompt').value;
        const cat    = document.getElementById('ai-category').value;

        btn.disabled = true; btn.textContent = '🤖 Generowanie...';

        try {
            const res = await api('/ai/generate', 'POST', { prompt, category: cat });
            toast('Post AI wygenerowany! ✨', 'success');
            document.getElementById('ai-result').innerHTML = `
                <div class="form-card">
                    <div class="ai-badge">✨ Wygenerowany post</div>
                    <p style="margin-top:8px;line-height:1.7">${res.data.content.replace(/\n/g,'<br>')}</p>
                    <button class="btn btn-secondary mt-16" onclick="showView('feed');loadFeed(true)">
                        Zobacz w feedzie →
                    </button>
                </div>`;
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            btn.disabled = false; btn.textContent = '✨ Generuj post AI';
        }
    });
}

// ============================================
// AUTH
// ============================================
async function doLogin(email, password) {
    const res  = await api('/auth/login', 'POST', { email, password });
    state.token = res.data.token;
    state.user  = { id: res.data.user_id, username: res.data.username, avatar: res.data.avatar };
    localStorage.setItem('scrollr_token', state.token);
}

async function doRegister(username, email, password) {
    const res  = await api('/auth/register', 'POST', { username, email, password });
    state.token = res.data.token;
    state.user  = { username: res.data.username };
    localStorage.setItem('scrollr_token', state.token);
}

function logout() {
    state.token = null;
    state.user  = null;
    state.posts = [];
    localStorage.removeItem('scrollr_token');
    showScreen('auth');
    toast('Wylogowano!');
}

function initAuth() {
    const loginForm    = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');

    loginForm.addEventListener('submit', async e => {
        e.preventDefault();
        const btn = loginForm.querySelector('.btn');
        btn.disabled = true; btn.textContent = 'Logowanie...';
        try {
            await doLogin(
                loginForm.querySelector('[name=email]').value,
                loginForm.querySelector('[name=password]').value,
            );
            showScreen('app');
            toast('Witaj z powrotem! 👋', 'success');
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            btn.disabled = false; btn.textContent = 'Zaloguj się';
        }
    });

    registerForm.addEventListener('submit', async e => {
        e.preventDefault();
        const btn = registerForm.querySelector('.btn');
        btn.disabled = true; btn.textContent = 'Rejestracja...';
        try {
            await doRegister(
                registerForm.querySelector('[name=username]').value,
                registerForm.querySelector('[name=email]').value,
                registerForm.querySelector('[name=password]').value,
            );
            showScreen('app');
            toast('Konto utworzone! 🎉', 'success');
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            btn.disabled = false; btn.textContent = 'Utwórz konto';
        }
    });

    // Tab switch
    document.querySelectorAll('.auth-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            loginForm.style.display    = tab.dataset.tab === 'login'    ? 'block' : 'none';
            registerForm.style.display = tab.dataset.tab === 'register' ? 'block' : 'none';
        });
    });
}

// ============================================
// PROFIL
// ============================================
function renderProfile() {
    if (!state.user) return;
    const view    = document.getElementById('view-profile');
    const initial = state.user.username.charAt(0).toUpperCase();

    view.innerHTML = `
        <div style="padding:20px">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
                <button onclick="showView('feed')" style="background:none;border:none;color:var(--text);cursor:pointer;display:flex;align-items:center;gap:6px;font-family:var(--font-b);font-size:14px">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Wróć
                </button>
            </div>
            <div class="profile-header">
                <div class="profile-avatar-lg">${initial}</div>
                <div>
                    <div style="font-family:var(--font-h);font-size:1.3rem;font-weight:700">
                        @${state.user.username}
                    </div>
                    <div class="text-muted" style="margin-top:4px">Członek Scrollr</div>
                </div>
            </div>
            <button class="btn btn-secondary" onclick="logout()" style="margin-top:8px">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Wyloguj się
            </button>
        </div>`;
}

// ============================================
// UTILS
// ============================================
function formatTime(dateStr) {
    const diff = (Date.now() - new Date(dateStr)) / 1000;
    if (diff < 60)    return 'przed chwilą';
    if (diff < 3600)  return `${Math.floor(diff/60)} min temu`;
    if (diff < 86400) return `${Math.floor(diff/3600)} godz. temu`;
    return `${Math.floor(diff/86400)} dni temu`;
}

// ============================================
// BOOT
// ============================================
async function boot() {
    initAuth();
    initUploadForm();
    initAiForm();

    if (state.token) {
        try {
            const res  = await api('/auth/me');
            state.user = res.data;
            showScreen('app');  // zalogowany → aplikacja
        } catch {
            state.token = null;
            localStorage.removeItem('scrollr_token');
            showScreen('auth'); // token wygasł → logowanie
        }
    } else {
        showScreen('auth');     // brak tokena → logowanie
    }

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }
}

document.addEventListener('DOMContentLoaded', boot);
