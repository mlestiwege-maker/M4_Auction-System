// AJAX bidding and polling logic
document.addEventListener('DOMContentLoaded', () => {
  const themeToggle = document.getElementById('theme-toggle');
  const rootBody = document.body;
  const savedTheme = localStorage.getItem('auctionhub_theme');
  if (savedTheme) {
    rootBody.setAttribute('data-theme', savedTheme);
  }
  if (themeToggle) {
    const setThemeLabel = () => {
      const dark = rootBody.getAttribute('data-theme') === 'dark';
      themeToggle.textContent = dark ? '☀️ Light' : '🌙 Dark';
    };
    setThemeLabel();
    themeToggle.addEventListener('click', () => {
      const dark = rootBody.getAttribute('data-theme') === 'dark';
      const next = dark ? 'light' : 'dark';
      rootBody.setAttribute('data-theme', next);
      localStorage.setItem('auctionhub_theme', next);
      setThemeLabel();
    });
  }

  const hasItemContext = typeof ITEM_ID !== 'undefined';

  const priceEl = document.getElementById('current-price');
  const bidderEl = document.getElementById('highest-bidder');
  const bidForm = document.getElementById('bid-form');
  const bidAmount = document.getElementById('bid-amount');
  const bidHistoryEl = document.getElementById('bid-history-list');
  const liveBidFeed = document.getElementById('live-bid-feed');
  const bidStatus = document.getElementById('bid-status');
  const watchlistButtons = Array.from(document.querySelectorAll('.watchlist-toggle'));
  const notificationBadge = document.getElementById('notification-badge');
  const notificationList = document.getElementById('notification-list');
  const markAllReadButton = document.getElementById('mark-all-read');
  const reviewForm = document.getElementById('review-form');
  const reviewStatus = document.getElementById('review-status');
  const sellerReviewsSummary = document.getElementById('seller-reviews-summary');
  const suggestionBox = document.getElementById('smart-price-suggestion');
  const categoryInput = document.querySelector('select[name="category"]');
  const titleInput = document.querySelector('input[name="title"]');
  const startPriceInput = document.querySelector('input[name="start_price"]');
  const imageInput = document.querySelector('input[name="image"]');
  const previewWrapper = document.getElementById('image-preview');
  const previewImage = document.getElementById('image-preview-img');
  const csrfToken = window.CSRF_TOKEN || (typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');

  // Live search (typeahead) for header
  (function() {
    const input = document.getElementById('header-search-input');
    const suggestions = document.getElementById('search-suggestions');
    if (!input || !suggestions) return;

    let activeIndex = -1;
    let items = [];
    let controller = null;

    function render() {
      if (!items.length) {
        suggestions.innerHTML = '';
        suggestions.setAttribute('aria-hidden', 'true');
        return;
      }
      suggestions.setAttribute('aria-hidden', 'false');
      suggestions.innerHTML = items.map((it, idx) => `
        <a href="/auction_system/items/view_item.php?id=${it.id}" class="search-suggestion" data-index="${idx}">
          <div class="s-img"><img src="${it.image_url || '/auction_system/assets/uploads/fallbacks/other.jpg'}" alt="" loading="lazy"></div>
          <div class="s-meta"><strong>${escapeHtml(it.title)}</strong><small>${escapeHtml(it.category || '')} • $${it.price}</small></div>
        </a>
      `).join('');
    }

    function debounce(fn, ms = 250) {
      let t;
      return function(...args) {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), ms);
      };
    }

    async function fetchSuggestions(q) {
      if (controller) controller.abort();
      controller = new AbortController();
      const category = (document.querySelector('.header-search select[name="category"]') || {}).value || '';
      try {
        const res = await fetch(`/auction_system/items/search_suggest.php?q=${encodeURIComponent(q)}&category=${encodeURIComponent(category)}`, { signal: controller.signal });
        const json = await res.json();
        if (!json.success) return;
        items = json.items || [];
        activeIndex = -1;
        render();
      } catch (e) {
        if (e.name === 'AbortError') return;
        console.error('search suggest error', e);
      }
    }

    const onInput = debounce((e) => {
      const q = (e.target.value || '').trim();
      if (!q) { items = []; render(); return; }
      fetchSuggestions(q);
    }, 220);

    input.addEventListener('input', onInput);

    input.addEventListener('keydown', (e) => {
      const suggestionsEls = Array.from(document.querySelectorAll('.search-suggestion'));
      if (!suggestionsEls.length) return;
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex = Math.min(activeIndex + 1, suggestionsEls.length - 1);
        suggestionsEls.forEach((el, i) => el.classList.toggle('active', i === activeIndex));
        suggestionsEls[activeIndex].scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex = Math.max(activeIndex - 1, 0);
        suggestionsEls.forEach((el, i) => el.classList.toggle('active', i === activeIndex));
        suggestionsEls[activeIndex].scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'Enter') {
        if (activeIndex >= 0 && suggestionsEls[activeIndex]) {
          e.preventDefault();
          window.location = suggestionsEls[activeIndex].href;
        }
      }
    });

    document.addEventListener('click', (ev) => {
      if (!ev.target.closest('.header-search')) {
        items = []; render();
      }
    });
  })();

  function updateUrgencyCountdowns() {
    const counters = document.querySelectorAll('.live-countdown');
    counters.forEach((el) => {
      const card = el.closest('.auction-card');
      const endTime = new Date((el.dataset.endTime || '').replace(' ', 'T'));
      if (Number.isNaN(endTime.getTime())) return;
      const diff = endTime - new Date();
      if (diff <= 0) {
        el.textContent = 'Ended';
        if (card) card.classList.remove('ending-soon');
        return;
      }
      const totalMinutes = Math.floor(diff / 60000);
      const hours = Math.floor(totalMinutes / 60);
      const minutes = totalMinutes % 60;
      if (diff <= 3600000 && card) {
        card.classList.add('ending-soon');
      }
      el.textContent = hours > 0 ? `${hours}h ${minutes}m left` : `${minutes}m left`;
    });
  }

  async function fetchPriceSuggestion() {
    if (!suggestionBox || !categoryInput || !titleInput) return;
    const category = categoryInput.value.trim();
    const title = titleInput.value.trim();
    if (!category || !title) {
      suggestionBox.textContent = 'Enter title + category to get smart price suggestion.';
      return;
    }
    try {
      const res = await fetch(`/auction_system/items/get_price_suggestion.php?category=${encodeURIComponent(category)}&title=${encodeURIComponent(title)}`);
      const data = await res.json();
      if (!data.success) {
        suggestionBox.textContent = 'No similar items yet. Start with a competitive price.';
        return;
      }
      suggestionBox.textContent = `💡 Smart Suggestion: Similar ${category} items sell around $${Number(data.min).toFixed(2)} - $${Number(data.max).toFixed(2)} (avg: $${Number(data.avg).toFixed(2)}).`;
      if (startPriceInput && !startPriceInput.value) {
        startPriceInput.value = Number(data.recommended).toFixed(2);
      }
    } catch (err) {
      console.error('fetchPriceSuggestion error', err);
    }
  }

  if (categoryInput && titleInput) {
    categoryInput.addEventListener('change', fetchPriceSuggestion);
    titleInput.addEventListener('blur', fetchPriceSuggestion);
  }

  if (imageInput && previewWrapper && previewImage) {
    imageInput.addEventListener('change', () => {
      const file = imageInput.files && imageInput.files[0];
      if (!file) {
        previewWrapper.style.display = 'none';
        previewImage.src = '';
        return;
      }
      const reader = new FileReader();
      reader.onload = () => {
        previewImage.src = String(reader.result || '');
        previewWrapper.style.display = 'block';
      };
      reader.readAsDataURL(file);
    });
  }

  async function fetchCurrent() {
    if (!hasItemContext || !priceEl || !bidderEl) return;
    try {
      const res = await fetch(`/auction_system/items/get_current_bid.php?item_id=${ITEM_ID}`);
      const json = await res.json();
      if (json.price !== undefined) {
        priceEl.textContent = parseFloat(json.price).toFixed(2);
        bidderEl.textContent = json.bidder ? json.bidder : 'No bids yet';
      }
    } catch (err) {
      console.error('fetchCurrent error', err);
    }
  }

  async function fetchBidHistory() {
    if (!hasItemContext) return;
    if (!bidHistoryEl) return;
    try {
      const res = await fetch(`/auction_system/items/get_bid_history.php?item_id=${ITEM_ID}`);
      const json = await res.json();
      if (!json.success) return;
      if (!json.bids.length) {
        bidHistoryEl.innerHTML = '<p>No bids yet.</p>';
        return;
      }
      bidHistoryEl.innerHTML = `
        <ul class="bid-history-list">
          ${json.bids.map(bid => `
            <li>
              <strong>$${Number(bid.amount).toFixed(2)}</strong>
              by ${escapeHtml(bid.name)}
              <span>${escapeHtml(bid.created_at)}</span>
            </li>
          `).join('')}
        </ul>
      `;

      if (liveBidFeed) {
        liveBidFeed.innerHTML = `<ul>${json.bids.slice(0, 5).map(bid => `<li>🔴 ${escapeHtml(bid.name)} just bid <strong>$${Number(bid.amount).toFixed(2)}</strong></li>`).join('')}</ul>`;
      }
    } catch (err) {
      console.error('fetchBidHistory error', err);
    }
  }

  async function fetchNotifications() {
    if (!notificationBadge) return;
    try {
      const res = await fetch('/auction_system/user/get_notifications.php?limit=6');
      const json = await res.json();
      if (!json.success) return;

      const unread = Number(json.unread || 0);
      notificationBadge.textContent = unread;
      notificationBadge.style.display = unread > 0 ? 'inline-flex' : 'none';

      if (notificationList) {
        if (!json.notifications.length) {
          notificationList.innerHTML = `
            <li class="notification-empty">
              <div class="item-fallback-art" style="min-height:180px;">
                <span class="page-chip">Quiet inbox</span>
                <h3>No notifications yet</h3>
                <p>When bids, wins, or auction endings happen, they’ll show up here automatically.</p>
              </div>
            </li>
          `;
        } else {
          notificationList.innerHTML = json.notifications.map((n) => `
            <li class="notification-item ${n.is_read === '1' || n.is_read === 1 ? 'read' : 'unread'}" data-id="${n.id}">
              <div>
                <div class="review-meta notification-meta">
                  <strong>${escapeHtml(String(n.type).replace(/_/g, ' '))}</strong>
                  <span>${String(n.is_read) === '0' ? 'Unread' : 'Read'}</span>
                </div>
                <p>${escapeHtml(n.message)}</p>
                <small>${escapeHtml(n.created_at)}</small>
              </div>
              <div class="card-actions">
                ${n.link ? `<a class="btn" href="${escapeHtml(n.link)}">Open</a>` : ''}
                ${String(n.is_read) === '0' ? `<button class="btn secondary mark-read" data-id="${n.id}">Mark read</button>` : ''}
              </div>
            </li>
          `).join('');
        }
      }
    } catch (err) {
      console.error('fetchNotifications error', err);
    }
  }

  async function fetchSellerReviews() {
    if (!sellerReviewsSummary) return;
    const sellerId = sellerReviewsSummary.dataset.sellerId;
    if (!sellerId) return;

    try {
      const res = await fetch(`/auction_system/reviews/get_seller_reviews.php?seller_id=${encodeURIComponent(sellerId)}`);
      const json = await res.json();
      if (!json.success) return;

      const reviewsHtml = json.reviews.length
        ? `<div class="review-summary"><strong>${Number(json.average).toFixed(2)}/5</strong> from ${json.total} review(s)</div>
           <ul class="review-list">
             ${json.reviews.map((review) => `
               <li class="review-item">
                 <div class="review-meta">
                   <strong>${escapeHtml(review.reviewer_name || 'Buyer')}</strong>
                   <span>${'★'.repeat(Number(review.rating))}${'☆'.repeat(5 - Number(review.rating))}</span>
                 </div>
                 <p>${escapeHtml(review.comment || 'No comment')}</p>
                 <small>${escapeHtml(review.created_at)}</small>
               </li>
             `).join('')}
           </ul>`
        : `
          <div class="item-fallback-art" style="min-height: 180px;">
            <span class="page-chip">Fresh profile</span>
            <h3>No reviews yet</h3>
            <p>This seller hasn’t received feedback yet. Reviews will appear here once buyers start rating their experience.</p>
          </div>`;

      sellerReviewsSummary.innerHTML = reviewsHtml;

      const listLink = document.createElement('p');
      listLink.innerHTML = `<a class="btn secondary" href="/auction_system/reviews/list_reviews.php?seller_id=${encodeURIComponent(sellerId)}">View all seller reviews</a>`;
      sellerReviewsSummary.appendChild(listLink);
    } catch (err) {
      console.error('fetchSellerReviews error', err);
    }
  }

  async function finalizeAuctions() {
    try {
      await fetch('/auction_system/items/finalize_auctions.php');
    } catch (err) {
      console.error('finalizeAuctions error', err);
    }
  }

  // only poll when auction is active
  if (hasItemContext && (typeof AUCTION_ACTIVE === 'undefined' || AUCTION_ACTIVE)) {
    fetchCurrent();
    fetchBidHistory();
    setInterval(fetchCurrent, 2000);
    setInterval(fetchBidHistory, 5000);
  }

  fetchNotifications();
  setInterval(fetchNotifications, 8000);
  finalizeAuctions();
  setInterval(finalizeAuctions, 30000);
  fetchSellerReviews();
  setInterval(fetchSellerReviews, 12000);
  updateUrgencyCountdowns();
  setInterval(updateUrgencyCountdowns, 1000);

  // Countdown timer
  try {
    const timeEl = document.getElementById('time-left');
    if (timeEl && timeEl.dataset.endTime) {
      const endTime = new Date(timeEl.dataset.endTime.replace(' ', 'T'));
      function updateCountdown() {
        const now = new Date();
        const diff = endTime - now;
        if (diff <= 0) {
          timeEl.textContent = 'Ended';
          // stop polling by setting AUCTION_ACTIVE false (best-effort)
          // disable form
          const bf = document.getElementById('bid-form'); if (bf) bf.querySelector('button').disabled = true;
          const af = document.getElementById('autobid-form'); if (af) af.querySelector('button').disabled = true;
          return;
        }
        const s = Math.floor(diff / 1000) % 60;
        const m = Math.floor(diff / 60000) % 60;
        const h = Math.floor(diff / 3600000) % 24;
        const d = Math.floor(diff / 86400000);
        timeEl.textContent = (d? d + 'd ':'') + String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
      }
      updateCountdown();
      setInterval(updateCountdown, 1000);
    }
  } catch (e) { console.error(e); }

  function setNotice(el, message, isError = false) {
    if (!el) return;
    el.textContent = message || '';
    el.classList.toggle('notice-error', !!isError);
  }

  // Only setup bid forms on the item detail page
  if (bidForm) {
    bidForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const amount = parseFloat(bidAmount.value);
      if (!amount || amount <= 0) { setNotice(bidStatus, 'Enter a valid bid amount.', true); return; }
      const current = parseFloat(priceEl.textContent || '0');
      if (amount <= current) { setNotice(bidStatus, 'Your bid must be higher than the current price.', true); return; }

      const form = new FormData();
      form.append('item_id', ITEM_ID);
      form.append('amount', amount);
      if (csrfToken) form.append('csrf_token', csrfToken);

      try {
        const resp = await fetch('/auction_system/items/place_bid.php', { method: 'POST', body: form });
        const data = await resp.json();
        if (data.success) {
          bidAmount.value = '';
          fetchCurrent();
          setNotice(bidStatus, data.message || 'Bid placed successfully.', false);
        } else {
          setNotice(bidStatus, data.message || 'Could not place bid.', true);
        }
      } catch (err) {
        console.error('place bid error', err);
        setNotice(bidStatus, 'Could not place bid. Please try again.', true);
      }
    });
  }

  // Auto-bid form
  const autobidForm = document.getElementById('autobid-form');
  if (autobidForm) {
    autobidForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const max = parseFloat(document.getElementById('max-bid').value);
      if (!max || max <= 0) { setNotice(document.getElementById('autobid-status'), 'Enter a valid max bid.', true); return; }
      const form = new FormData();
      form.append('item_id', ITEM_ID);
      form.append('max_bid', max);
      if (csrfToken) form.append('csrf_token', csrfToken);
      try {
        const r = await fetch('/auction_system/items/set_autobid.php', { method: 'POST', body: form });
        const j = await r.json();
        setNotice(document.getElementById('autobid-status'), j.message || 'Auto-bid set', !j.success);
      } catch (err) {
        console.error(err);
        setNotice(document.getElementById('autobid-status'), 'Auto-bid could not be saved.', true);
      }
    });
  }

  // Shared watchlist toggle handler for both text buttons and heart icons
  async function toggleWatchlistHandler() {
    const button = this;
    const itemId = button.dataset.itemId;
    const action = button.dataset.action || 'toggle';
    const form = new FormData();
    form.append('item_id', itemId);
    form.append('action', action);
    if (csrfToken) form.append('csrf_token', csrfToken);

    try {
      const resp = await fetch('/auction_system/items/toggle_watchlist.php', { method: 'POST', body: form, credentials: 'same-origin' });
      const data = await resp.json();
      if (!data.success) {
        console.error('watchlist update failed', data.message || data);
        return;
      }

      const watched = !!data.watched;
      button.dataset.action = watched ? 'remove' : 'add';
      // Update UI based on button type
      if (button.classList.contains('card-fav')) {
        button.setAttribute('aria-pressed', watched ? 'true' : 'false');
        button.textContent = watched ? '♥' : '♡';
      } else {
        button.textContent = watched ? 'Remove from Watchlist' : 'Add to Watchlist';
        // Remove card from DOM on watchlist page when removing
        if (window.location.pathname.includes('/user/watchlist.php') && action === 'remove' && watched === false) {
          const card = button.closest('.watchlist-card') || button.closest('.auction-card');
          if (card) card.remove();
        }
      }
    } catch (err) {
      console.error('watchlist toggle error', err);
    }
  }

  watchlistButtons.forEach((button) => {
    button.addEventListener('click', toggleWatchlistHandler.bind(button));
  });

  // card favorite heart (grid) handlers
  const cardFavs = Array.from(document.querySelectorAll('.card-fav'));
  cardFavs.forEach((btn) => {
    btn.addEventListener('click', toggleWatchlistHandler.bind(btn));
  });

  // Only setup notification handlers on the notifications page
  if (notificationList) {
    notificationList.addEventListener('click', async (event) => {
      const button = event.target.closest('.mark-read');
      if (!button) return;
      const form = new FormData();
      form.append('notification_id', button.dataset.id);
      if (csrfToken) form.append('csrf_token', csrfToken);

      try {
        const resp = await fetch('/auction_system/user/mark_notification_read.php', { method: 'POST', body: form });
        const data = await resp.json();
        if (data.success) {
          fetchNotifications();
        }
      } catch (err) {
        console.error('mark notification read error', err);
      }
    });
  }

  if (markAllReadButton) {
    markAllReadButton.addEventListener('click', async () => {
      const form = new FormData();
      if (csrfToken) form.append('csrf_token', csrfToken);
      try {
        const resp = await fetch('/auction_system/user/mark_all_notifications_read.php', { method: 'POST', body: form });
        const data = await resp.json();
        if (data.success) {
          fetchNotifications();
        }
      } catch (err) {
        console.error('mark all notifications read error', err);
      }
    });
  }

  // Only setup review form on the item detail page
  if (reviewForm) {
    reviewForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = new FormData(reviewForm);
      if (csrfToken && !form.get('csrf_token')) form.append('csrf_token', csrfToken);

      try {
        const resp = await fetch('/auction_system/reviews/submit_review.php', { method: 'POST', body: form });
        const data = await resp.json();
        if (reviewStatus) reviewStatus.textContent = data.message || (data.success ? 'Review submitted' : 'Could not submit review');
        if (data.success) {
          reviewForm.reset();
          fetchSellerReviews();
        }
      } catch (err) {
        console.error('review submit error', err);
      }
    });
  }

  function escapeHtml(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }
});
