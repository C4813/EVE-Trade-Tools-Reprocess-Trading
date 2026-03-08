// ── Pricing options show/hide logic ──────────────────────────────────────
function setFieldGreyed(field, greyed) {
  if (!field) return;
  field.style.opacity      = greyed ? '0.4' : '';
  field.style.pointerEvents = greyed ? 'none' : '';
}

function updatePricingVisibility() {
  var buyQty = document.getElementById('ett-buy-qty');
  var relist = document.getElementById('ett-relist-brokerage');

  var buyQtyNo  = !buyQty  || buyQty.value  !== 'yes';
  var relistNo  = !relist  || relist.value  !== 'yes';

  var pctVolField   = document.getElementById('ett-pct-daily-vol')  ? document.getElementById('ett-pct-daily-vol').closest('.ett-field')  : null;
  var orderUpdField = document.getElementById('ett-order-updates')  ? document.getElementById('ett-order-updates').closest('.ett-field')  : null;

  setFieldGreyed(pctVolField,   buyQtyNo);
  setFieldGreyed(orderUpdField, relistNo);
}

document.addEventListener('DOMContentLoaded', updatePricingVisibility);

document.addEventListener('change', function (e) {
  if (e.target.matches('#ett-buy-qty') || e.target.matches('#ett-relist-brokerage')) {
    updatePricingVisibility();
  }
});

// ── Character accordion ───────────────────────────────────────────────────
document.addEventListener('click', function (e) {
  const header = e.target.closest('.ett-character-header');
  if (!header) return;

  const body = header.nextElementSibling;
  if (!body || !body.classList.contains('ett-character-body')) return;

  body.classList.toggle('open');
});

// ── Trading tool: Character loader ───────────────────────────────────────
function loadCharacters() {
  var hub   = document.getElementById('ett-trade-hub');
  var sel   = document.getElementById('ett-character');
  var recEl = document.getElementById('ett-char-rec-text');
  if (!hub || !sel) return;

  sel.innerHTML = '<option value="">— Loading… —</option>';
  if (recEl) recEl.textContent = '';

  var data = new FormData();
  data.append('action',    'ett_rt_get_characters');
  data.append('nonce',     (typeof ettRt !== 'undefined' ? ettRt.nonce : ''));
  data.append('trade_hub', hub.value);

  var ajaxurl = (typeof ettRt !== 'undefined' ? ettRt.ajaxurl : '/wp-admin/admin-ajax.php');

  fetch(ajaxurl, { method: 'POST', body: data })
    .then(function (r) { return r.json(); })
    .then(function (json) {
      if (!json.success) {
        sel.innerHTML = '<option value="">— No characters —</option>';
        return;
      }

      var chars = json.data.characters || [];
      var rec   = json.data.recommended || '';

      if (chars.length === 0) {
        sel.innerHTML = '<option value="">— No characters linked —</option>';
        return;
      }

      var html = '';
      chars.forEach(function (c) {
        var label = c.name
          + ' (Scrp: ' + c.scrapmetal
          + ' | Tax: ' + (c.reproc_tax * 100).toFixed(2) + '%'
          + ' | Fee: ' + (c.broker_fee * 100).toFixed(2) + '%)';
        html += '<option value="' + escHtml(c.character_id) + '"'
              + (c.character_id === rec ? ' selected' : '') + '>'
              + escHtml(label) + '</option>';
      });
      sel.innerHTML = html;

      if (rec && recEl) {
        var recChar = chars.find(function (c) { return c.character_id === rec; });
        if (recChar) {
          recEl.textContent = 'Recommended: ' + recChar.name;
        }
      }
    })
    .catch(function () {
      sel.innerHTML = '<option value="">— Error loading characters —</option>';
    });
}

// Load characters on page load and when hub changes
document.addEventListener('DOMContentLoaded', loadCharacters);

function markResultsStale() {
  var resultsEl = document.getElementById('ett-trading-results');
  var btn       = document.getElementById('ett-btn-generate-list');
  if (resultsEl && resultsEl.innerHTML !== '') {
    resultsEl.innerHTML = '';
    if (btn) btn.textContent = 'Regenerate List';
  }
}

document.addEventListener('change', function (e) {
  if (e.target.matches('#ett-trade-hub')) loadCharacters();

  var watchedIds = [
    'ett-trade-hub', 'ett-market-group', 'ett-exclude-capital', 'ett-meta-only',
    'ett-character', 'ett-sell-to', 'ett-buy-qty', 'ett-relist-brokerage'
  ];
  if (watchedIds.indexOf(e.target.id) !== -1) markResultsStale();
});

document.addEventListener('input', function (e) {
  var watchedIds = [
    'ett-min-margin', 'ett-max-margin', 'ett-min-daily-vol', 'ett-stack-size',
    'ett-pct-daily-vol', 'ett-order-updates'
  ];
  if (watchedIds.indexOf(e.target.id) !== -1) markResultsStale();
});

// ── Trading tool: Generate List ───────────────────────────────────────────
document.addEventListener('click', function (e) {
  if (!e.target.matches('#ett-btn-generate-list')) return;

  const btn = e.target;
  btn.disabled = true;
  btn.textContent = 'Loading…';

  const resultsEl = document.getElementById('ett-trading-results');
  if (resultsEl) {
    resultsEl.innerHTML = '';
  }

  const data = new FormData();
  data.append('action',          'ett_rt_generate_list');
  data.append('nonce',           (typeof ettRt !== 'undefined' ? ettRt.nonce : ''));
  data.append('trade_hub',       document.getElementById('ett-trade-hub').value);
  data.append('market_group',    document.getElementById('ett-market-group').value);
  data.append('exclude_capital', document.getElementById('ett-exclude-capital').value);
  data.append('meta_only',       document.getElementById('ett-meta-only').value);
  data.append('character_id',    document.getElementById('ett-character')    ? document.getElementById('ett-character').value    : '');
  data.append('sell_to',         document.getElementById('ett-sell-to')      ? document.getElementById('ett-sell-to').value      : 'sell_orders');
  data.append('stack_size',      document.getElementById('ett-stack-size')   ? document.getElementById('ett-stack-size').value   : '100');
  data.append('min_margin',      document.getElementById('ett-min-margin')   ? document.getElementById('ett-min-margin').value   : '5');
  data.append('max_margin',      document.getElementById('ett-max-margin')   ? document.getElementById('ett-max-margin').value   : '25');
  data.append('min_volume',        document.getElementById('ett-min-daily-vol')    ? document.getElementById('ett-min-daily-vol').value    : '1');
  data.append('relist_brokerage',  document.getElementById('ett-relist-brokerage') ? document.getElementById('ett-relist-brokerage').value : 'no');
  data.append('order_updates',     document.getElementById('ett-order-updates')    ? document.getElementById('ett-order-updates').value    : '0');

  const ajaxurl = (typeof ettRt !== 'undefined' ? ettRt.ajaxurl : '/wp-admin/admin-ajax.php');

  fetch(ajaxurl, { method: 'POST', body: data })
    .then(function (r) { return r.json(); })
    .then(function (json) {
      btn.disabled    = false;
      btn.textContent = 'Generate List';

      if (!resultsEl) return;

      if (!json.success) {
        resultsEl.innerHTML = '<p class="ett-error">Error: ' + (json.data || 'Unknown error') + '</p>';
        return;
      }

      const items           = json.data.items      || [];
      const brokerFee       = json.data.broker_fee != null ? parseFloat(json.data.broker_fee) : 0.03;
      const oldestPriceDate = json.data.oldest_price_date || null;

      if (items.length === 0) {
        resultsEl.innerHTML = '<p>No items found matching the selected filters.</p>';
        return;
      }

      let html = '<button type="button" class="ett-trading-btn" id="ett-btn-quickbar">Generate Market Quickbar</button>';

      // ── Potential Daily Profit ──
      var buyQtyEl = document.getElementById('ett-buy-qty');
      var pctVolEl = document.getElementById('ett-pct-daily-vol');
      var buyQtyOn = buyQtyEl && buyQtyEl.value === 'yes';
      var pctVol   = buyQtyOn && pctVolEl ? (parseFloat(pctVolEl.value) / 100) : 1;

      var totalDailyProfit = 0;
      items.forEach(function (item) {
        if (item.buy_price === null || item.reprocess_value === null || item.volume === null) return;
        var qty          = Math.floor(item.volume * pctVol);
        var costWithFee  = item.buy_price * (1.0 + brokerFee);
        var profit       = (item.reprocess_value - costWithFee) * qty;
        totalDailyProfit += profit;
      });

      var oldestDateStr = oldestPriceDate
        ? 'Oldest price data: ' + oldestPriceDate
        : 'Price data age unavailable';

      html += '<div class="ett-daily-profit">Potential Daily Profit: <strong>'
            + Math.round(totalDailyProfit).toLocaleString() + ' ISK</strong>'
            + '<br><span class="ett-price-date">' + escHtml(oldestDateStr) + '</span>'
            + '</div>';

      html += '<h4>Item List (' + items.length + ' items)</h4>';
      html += '<ul>';

      items.forEach(function (item) {
        var price    = item.buy_price !== null
          ? Math.round(item.buy_price).toLocaleString()
          : 'N/A';
        var reproc   = item.reprocess_value !== null
          ? Math.round(item.reprocess_value).toLocaleString()
          : 'N/A';
        var rawVolume = item.volume !== null ? item.volume : null;
        var volume    = rawVolume !== null
          ? Math.floor(rawVolume * pctVol).toLocaleString()
          : 'N/A';
        var margin   = item.margin !== null && item.margin !== undefined
          ? item.margin.toFixed(2) + '%'
          : 'N/A';
        html += '<li'
          + ' data-reproc="' + escHtml(reproc) + '"'
          + ' data-qty="'    + escHtml(volume)  + '"'
          + ' data-margin="' + escHtml(margin)  + '"'
          + ' data-name="'   + escHtml(item.name) + '"'
          + '>' + escHtml(item.name) + ' [' + price + ' / ' + reproc + ' / ' + volume + ' / ' + margin + ']</li>';
      });

      html += '</ul>';
      resultsEl.innerHTML = html;
    })
    .catch(function (err) {
      btn.disabled    = false;
      btn.textContent = 'Generate List';
      if (resultsEl) {
        resultsEl.innerHTML = '<p class="ett-error">Request failed: ' + err.message + '</p>';
      }
    });
});

document.addEventListener('click', function (e) {
  if (!e.target.matches('#ett-btn-quickbar')) return;

  var btn        = e.target;
  var resultsEl  = document.getElementById('ett-trading-results');
  var groupEl    = document.getElementById('ett-market-group');
  var groupLabel = groupEl ? groupEl.options[groupEl.selectedIndex].text : 'Items';

  if (!resultsEl) return;

  var items = resultsEl.querySelectorAll('li');
  if (!items.length) return;

  var lines = ['+ ' + groupLabel];
  items.forEach(function (li) {
    var name   = li.dataset.name   || li.textContent.split('[')[0].trim();
    var reproc = li.dataset.reproc || '';
    var qty    = li.dataset.qty    || '';
    var margin = li.dataset.margin || '';

    var note = reproc + '|' + qty + '|' + margin;
    if (note.length > 25) note = note.slice(0, 25);
    lines.push('- ' + name + ' [' + note + ']');
  });

  navigator.clipboard.writeText(lines.join('\n')).then(function () {
    btn.textContent = 'Copied!';
    setTimeout(function () { btn.textContent = 'Generate Market Quickbar'; }, 1500);
  });
});

function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}