// ── Pricing/meta options show/hide logic ──────────────────────────────────
function setFieldGreyed(field, greyed) {
  if (!field) return;
  field.style.opacity       = greyed ? '0.4' : '';
  field.style.pointerEvents = greyed ? 'none' : '';
}

function updatePricingVisibility() {
  var buyQty         = document.getElementById('ett-buy-qty');
  var relist         = document.getElementById('ett-relist-brokerage');
  var overrideBroker = document.getElementById('ett-override-broker');

  var buyQtyNo      = !buyQty         || buyQty.value         !== 'yes';
  var relistNo      = !relist         || relist.value         !== 'yes';
  var overrideNo    = !overrideBroker || overrideBroker.value !== 'yes';

  var pctVolField        = document.getElementById('ett-pct-daily-vol')          ? document.getElementById('ett-pct-daily-vol').closest('.ett-field')          : null;
  var orderUpdField      = document.getElementById('ett-order-updates')          ? document.getElementById('ett-order-updates').closest('.ett-field')          : null;
  var overridePctField   = document.getElementById('ett-override-broker-pct-field');

  setFieldGreyed(pctVolField,      buyQtyNo);
  setFieldGreyed(orderUpdField,    relistNo);
  setFieldGreyed(overridePctField, overrideNo);
}

// #4 – Grey out Meta Only when market group is not ship_equipment
function updateMetaOnlyVisibility() {
  var groupEl    = document.getElementById('ett-market-group');
  var metaField  = document.getElementById('ett-meta-only-field');
  var metaSel    = document.getElementById('ett-meta-only');

  if (!groupEl || !metaField || !metaSel) return;

  var isShipEquipment = groupEl.value === 'ship_equipment';
  setFieldGreyed(metaField, !isShipEquipment);
  if (!isShipEquipment) metaSel.value = 'no';
}

// Grey out Exclude Capital-Sized when implants is selected (no capitals in that group)
function updateExcludeCapitalVisibility() {
  var groupEl      = document.getElementById('ett-market-group');
  var capitalField = document.getElementById('ett-exclude-capital-field');
  var capitalSel   = document.getElementById('ett-exclude-capital');

  if (!groupEl || !capitalField || !capitalSel) return;

  var isImplants = groupEl.value === 'implants';
  setFieldGreyed(capitalField, isImplants);
  if (isImplants) capitalSel.value = 'no';
}

document.addEventListener('DOMContentLoaded', function () {
  updatePricingVisibility();
  updateMetaOnlyVisibility();
  updateExcludeCapitalVisibility();
});

document.addEventListener('change', function (e) {
  if (e.target.matches('#ett-buy-qty') || e.target.matches('#ett-relist-brokerage') || e.target.matches('#ett-override-broker')) {
    updatePricingVisibility();
  }
  if (e.target.matches('#ett-market-group')) {
    updateMetaOnlyVisibility();
    updateExcludeCapitalVisibility();
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

// ── Trading tool: Hub loader ──────────────────────────────────────────────
function loadHubs() {
  var hubSel  = document.getElementById('ett-trade-hub');
  if (!hubSel) return;

  hubSel.innerHTML = '<option value="">— Loading hubs\u2026 —</option>';

  var data = new FormData();
  data.append('action', 'ett_rt_get_hubs');
  data.append('nonce',  (typeof ettRt !== 'undefined' ? ettRt.nonce : ''));

  var ajaxurl = (typeof ettRt !== 'undefined' ? ettRt.ajaxurl : '/wp-admin/admin-ajax.php');

  fetch(ajaxurl, { method: 'POST', body: data })
    .then(function (r) { return r.json(); })
    .then(function (json) {
      if (!json.success || !json.data || !json.data.hubs || !json.data.hubs.length) {
        // Fallback to static list if DB unavailable
        hubSel.innerHTML =
          '<option value="jita" selected>Jita</option>' +
          '<option value="amarr">Amarr</option>' +
          '<option value="rens">Rens</option>' +
          '<option value="dodixie">Dodixie</option>' +
          '<option value="hek">Hek</option>';
        loadCharacters();
        return;
      }
      var hubs  = json.data.hubs;
      var html  = '';
      hubs.forEach(function (h, i) {
        html += '<option value="' + escHtml(h.key) + '"' + (i === 0 ? ' selected' : '') + '>' + escHtml(h.label) + '</option>';
      });
      hubSel.innerHTML = html;
      loadCharacters();
    })
    .catch(function () {
      // Fallback on network error
      hubSel.innerHTML =
        '<option value="jita" selected>Jita</option>' +
        '<option value="amarr">Amarr</option>' +
        '<option value="rens">Rens</option>' +
        '<option value="dodixie">Dodixie</option>' +
        '<option value="hek">Hek</option>';
      loadCharacters();
    });
}

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
        if (recChar) recEl.textContent = 'Recommended: ' + recChar.name;
      }
    })
    .catch(function () {
      sel.innerHTML = '<option value="">— Error loading characters —</option>';
    });
}

document.addEventListener('DOMContentLoaded', loadHubs);

// ── Button state management ───────────────────────────────────────────────
function setButtonMode(btn, mode) {
  if (!btn) return;
  btn.dataset.mode = mode;
  if (mode === 'quickbar')     btn.textContent = 'Generate Market Quickbar';
  else if (mode === 'regenerate') btn.textContent = 'Regenerate List';
  else                         btn.textContent = 'Generate List';
}

function markResultsStale() {
  var resultsEl = document.getElementById('ett-trading-results');
  var btn       = document.getElementById('ett-btn-generate-list');
  if (resultsEl && resultsEl.innerHTML !== '') {
    resultsEl.innerHTML = '';
    setButtonMode(btn, 'regenerate');
  }
}

document.addEventListener('change', function (e) {
  if (e.target.matches('#ett-trade-hub')) loadCharacters();

  var watchedIds = [
    'ett-trade-hub', 'ett-market-group', 'ett-exclude-capital', 'ett-meta-only',
    'ett-character', 'ett-sell-to', 'ett-buy-qty', 'ett-relist-brokerage',
    'ett-override-broker'
  ];
  if (watchedIds.indexOf(e.target.id) !== -1) markResultsStale();
});

document.addEventListener('input', function (e) {
  var watchedIds = [
    'ett-min-margin', 'ett-max-margin', 'ett-min-daily-vol', 'ett-stack-size',
    'ett-pct-daily-vol', 'ett-order-updates', 'ett-override-broker-pct'
  ];
  if (watchedIds.indexOf(e.target.id) !== -1) markResultsStale();
});

// ── Clamp override broker pct to 2 decimal places and min 0.5 on blur ──────
document.addEventListener('blur', function (e) {
  if (!e.target.matches('#ett-override-broker-pct')) return;
  var val = parseFloat(e.target.value);
  if (isNaN(val) || val < 0.5) val = 0.5;
  if (val > 100) val = 100;
  e.target.value = Math.round(val * 100) / 100; // max 2dp
}, true);

// ── Prevent 0 / negative in number inputs ────────────────────────────────
document.addEventListener('change', function (e) {
  if (!e.target.matches('input[type="number"]')) return;
  var val = parseFloat(e.target.value);
  var min = parseFloat(e.target.getAttribute('min'));
  if (isNaN(val) || (isFinite(min) && val < min)) {
    e.target.value = isFinite(min) ? min : 0;
  }
});
document.addEventListener('blur', function (e) {
  if (!e.target.matches('input[type="number"]')) return;
  var val = parseFloat(e.target.value);
  var min = parseFloat(e.target.getAttribute('min'));
  if (isNaN(val) || (isFinite(min) && val < min)) {
    e.target.value = isFinite(min) ? min : 0;
  }
}, true);

// ── #5 – UTC / EVE display (no "Time") ───────────────────────────────────
function formatEveTime(rawDateStr) {
  if (!rawDateStr) return 'Price data age unavailable';
  var normalized = rawDateStr.replace(' ', 'T');
  if (!normalized.endsWith('Z') && !normalized.includes('+')) normalized += 'Z';
  var d = new Date(normalized);
  if (isNaN(d.getTime())) return 'Oldest price data: ' + rawDateStr;
  var pad = function (n) { return String(n).padStart(2, '0'); };
  return 'Oldest price data: '
    + d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate())
    + ' ' + pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()) + ':' + pad(d.getUTCSeconds())
    + ' EVE';
}

// ── Generate / Quickbar unified button ───────────────────────────────────
document.addEventListener('click', function (e) {
  if (!e.target.matches('#ett-btn-generate-list')) return;

  var btn  = e.target;
  var mode = btn.dataset.mode || 'generate';

  // ── Quickbar mode ──────────────────────────────────────────────────────
  if (mode === 'quickbar') {
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
      var note   = reproc + '|' + qty + '|' + margin;
      if (note.length > 25) note = note.slice(0, 25);
      lines.push('- ' + name + ' [' + note + ']');
    });

    navigator.clipboard.writeText(lines.join('\n')).then(function () {
      btn.textContent = 'Copied!';
      setTimeout(function () { setButtonMode(btn, 'quickbar'); }, 1500);
    });
    return;
  }

  // ── Generate mode ──────────────────────────────────────────────────────
  btn.disabled = true;
  btn.textContent = 'Loading…';

  var resultsEl = document.getElementById('ett-trading-results');
  if (resultsEl) resultsEl.innerHTML = '';

  var data = new FormData();
  data.append('action',           'ett_rt_generate_list');
  data.append('nonce',            (typeof ettRt !== 'undefined' ? ettRt.nonce : ''));
  data.append('trade_hub',        document.getElementById('ett-trade-hub').value);
  data.append('market_group',     document.getElementById('ett-market-group').value);
  data.append('exclude_capital',  document.getElementById('ett-exclude-capital').value);
  data.append('meta_only',        document.getElementById('ett-meta-only').value);
  data.append('character_id',     document.getElementById('ett-character')     ? document.getElementById('ett-character').value     : '');
  data.append('sell_to',          document.getElementById('ett-sell-to')       ? document.getElementById('ett-sell-to').value       : 'sell_orders');
  data.append('stack_size',       document.getElementById('ett-stack-size')    ? document.getElementById('ett-stack-size').value    : '100');
  data.append('min_margin',       document.getElementById('ett-min-margin')    ? document.getElementById('ett-min-margin').value    : '5');
  data.append('max_margin',       document.getElementById('ett-max-margin')    ? document.getElementById('ett-max-margin').value    : '25');
  data.append('min_volume',       document.getElementById('ett-min-daily-vol') ? document.getElementById('ett-min-daily-vol').value : '1');
  data.append('relist_brokerage',    document.getElementById('ett-relist-brokerage')    ? document.getElementById('ett-relist-brokerage').value    : 'no');
  data.append('order_updates',       document.getElementById('ett-order-updates')        ? document.getElementById('ett-order-updates').value        : '0');
  data.append('override_broker',     document.getElementById('ett-override-broker')      ? document.getElementById('ett-override-broker').value      : 'no');
  data.append('override_broker_pct', document.getElementById('ett-override-broker-pct') ? document.getElementById('ett-override-broker-pct').value  : '3');

  var ajaxurl = (typeof ettRt !== 'undefined' ? ettRt.ajaxurl : '/wp-admin/admin-ajax.php');

  fetch(ajaxurl, { method: 'POST', body: data })
    .then(function (r) { return r.json(); })
    .then(function (json) {
      btn.disabled = false;
      if (!resultsEl) return;

      if (!json.success) {
        setButtonMode(btn, 'generate');
        resultsEl.innerHTML = '<p class="ett-error">Error: ' + (json.data || 'Unknown error') + '</p>';
        return;
      }

      var items           = json.data.items      || [];
      var brokerFee       = json.data.broker_fee != null ? parseFloat(json.data.broker_fee) : 0.03;
      var oldestPriceDate = json.data.oldest_price_date || null;

      if (items.length === 0) {
        setButtonMode(btn, 'generate');
        resultsEl.innerHTML = '<p>No items found matching the selected filters.</p>';
        return;
      }

      setButtonMode(btn, 'quickbar');

      // ── Potential Daily Profit ──
      var buyQtyEl       = document.getElementById('ett-buy-qty');
      var pctVolEl       = document.getElementById('ett-pct-daily-vol');
      var overrideEl     = document.getElementById('ett-override-broker');
      var buyQtyOn       = buyQtyEl && buyQtyEl.value === 'yes';
      var pctVol         = buyQtyOn && pctVolEl ? (parseFloat(pctVolEl.value) / 100) : 1;
      var overrideActive = overrideEl && overrideEl.value === 'yes';

      var totalDailyProfit = 0;
      items.forEach(function (item) {
        if (item.buy_price === null || item.reprocess_value === null || item.volume === null) return;
        var qty         = Math.floor(item.volume * pctVol);
        // Mirror PHP: when override active, apply 100 ISK floor on buy fee
        var buyFeeIsk   = overrideActive
          ? Math.max(brokerFee * item.buy_price, 100)
          : brokerFee * item.buy_price;
        var costWithFee = item.buy_price + buyFeeIsk;
        totalDailyProfit += (item.reprocess_value - costWithFee) * qty;
      });

      // #2 – Dynamic qty column label in legend
      var qtyLabel = buyQtyOn ? 'QTY Recommendation' : '30d Volume';

      var oldestDateStr = formatEveTime(oldestPriceDate);

      var html = '<div class="ett-daily-profit">'
               + 'Potential Daily Profit: <strong>'
               + Math.round(totalDailyProfit).toLocaleString() + ' ISK</strong>'
               + '<br><span class="ett-price-date">' + escHtml(oldestDateStr) + '</span>'
               + '<span class="ett-result-legend">Item Name &nbsp;[ Buy Price &nbsp;/ &nbsp;Reprocess Value &nbsp;/ &nbsp;' + escHtml(qtyLabel) + ' &nbsp;/ &nbsp;Margin% ]</span>'
               + '</div>';

      html += '<h4>Item List (' + items.length + ' items)</h4>';
      html += '<ul>';

      items.forEach(function (item) {
        var price   = item.buy_price !== null
          ? Math.round(item.buy_price).toLocaleString() : 'N/A';
        var reproc  = item.reprocess_value !== null
          ? Math.round(item.reprocess_value).toLocaleString() : 'N/A';
        var rawVol  = item.volume !== null ? item.volume : null;
        var volume  = rawVol !== null
          ? Math.floor(rawVol * pctVol).toLocaleString() : 'N/A';
        var margin  = item.margin !== null && item.margin !== undefined
          ? item.margin.toFixed(2) + '%' : 'N/A';
        html += '<li'
          + ' data-reproc="' + escHtml(reproc) + '"'
          + ' data-qty="'    + escHtml(volume) + '"'
          + ' data-margin="' + escHtml(margin) + '"'
          + ' data-name="'   + escHtml(item.name) + '"'
          + '>' + escHtml(item.name) + ' [' + price + ' / ' + reproc + ' / ' + volume + ' / ' + margin + ']</li>';
      });

      html += '</ul>';
      resultsEl.innerHTML = html;
    })
    .catch(function (err) {
      btn.disabled = false;
      setButtonMode(btn, 'generate');
      if (resultsEl) resultsEl.innerHTML = '<p class="ett-error">Request failed: ' + err.message + '</p>';
    });
});

function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
