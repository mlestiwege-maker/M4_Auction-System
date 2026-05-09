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
        notificationList.innerHTML = json.notifications.map((n) => `
          <li class="notification-item ${n.is_read === '1' || n.is_read === 1 ? 'read' : 'unread'}" data-id="${n.id}">
            <div>
              <strong>${escapeHtml(String(n.type).replace(/_/g, ' '))}</strong>
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
        : '<p>No reviews yet.</p>';

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

  if (!bidForm) return;

  bidForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const amount = parseFloat(bidAmount.value);
    if (!amount || amount <= 0) { alert('Enter a valid bid amount'); return; }
    const current = parseFloat(priceEl.textContent || '0');
    if (amount <= current) { alert('Your bid must be higher than the current price'); return; }

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
      } else {
        alert(data.message || 'Could not place bid');
      }
    } catch (err) {
      console.error('place bid error', err);
    }
  });

  // Auto-bid form
  const autobidForm = document.getElementById('autobid-form');
  if (autobidForm) {
    autobidForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const max = parseFloat(document.getElementById('max-bid').value);
      if (!max || max <= 0) { alert('Enter a valid max bid'); return; }
      const form = new FormData();
      form.append('item_id', ITEM_ID);
      form.append('max_bid', max);
      if (csrfToken) form.append('csrf_token', csrfToken);
      try {
        const r = await fetch('/auction_system/items/set_autobid.php', { method: 'POST', body: form });
        const j = await r.json();
        document.getElementById('autobid-status').textContent = j.message || 'Auto-bid set';
      } catch (err) { console.error(err); }
    });
  }

  watchlistButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      const itemId = button.dataset.itemId;
      const action = button.dataset.action || 'toggle';
      const form = new FormData();
      form.append('item_id', itemId);
      form.append('action', action);
      if (csrfToken) form.append('csrf_token', csrfToken);

      try {
        const resp = await fetch('/auction_system/items/toggle_watchlist.php', { method: 'POST', body: form });
        const data = await resp.json();
        if (!data.success) {
          alert(data.message || 'Could not update watchlist');
          return;
        }

        const watched = !!data.watched;
        button.dataset.action = watched ? 'remove' : 'add';
        button.textContent = watched ? 'Remove from Watchlist' : 'Add to Watchlist';

        // If we are on the watchlist page and removing, remove the card from the DOM.
        if (window.location.pathname.includes('/user/watchlist.php') && action === 'remove' && watched === false) {
          const card = button.closest('.auction-card');
          if (card) card.remove();
        }
      } catch (err) {
        console.error('watchlist toggle error', err);
      }
    });
  });

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
