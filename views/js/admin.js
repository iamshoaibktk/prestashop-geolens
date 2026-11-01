/**
 * GEO Lens back office. The server renders every panel; this only moves it onto the page.
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
(function () {
  var app = document.getElementById('gl-app');
  if (!app) {
    return;
  }
  var base = app.dataset.url;
  var panel = document.getElementById('gl-panel');
  var flashBox = document.getElementById('gl-flash');
  var tabs = Array.prototype.slice.call(app.querySelectorAll('.gl-tab'));
  var state = { tab: 'overview', days: 30 };
  var loadId = 0;
  // Saving re-renders the panel, so a section the user opened has to stay open.
  var accordions = {};
  var periodBox = app.querySelector('.gl-period');

  app.addEventListener('toggle', function (event) {
    if (event.target.dataset && event.target.dataset.acc) {
      accordions[event.target.dataset.acc] = event.target.open;
    }
  }, true);

  function url(params) {
    var query = Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
    return base + (base.indexOf('?') === -1 ? '?' : '&') + query;
  }

  function call(params, body) {
    var options = { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
    if (body !== undefined) {
      options.method = 'POST';
      options.body = body;
    }
    return fetch(url(params), options).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        if (!response.ok || data.error) {
          throw new Error(data.error || app.dataset.tError);
        }
        return data;
      });
    });
  }

  function flash(type, text) {
    flashBox.innerHTML = '';
    if (!text) {
      return;
    }
    var box = document.createElement('div');
    box.className = 'gl-flash gl-flash-' + type;
    box.textContent = text;
    box.addEventListener('click', function () { box.remove(); });
    flashBox.appendChild(box);
    if (type === 'success') {
      setTimeout(function () { if (box.parentNode) { box.parentNode.removeChild(box); } }, 6000);
    }
  }

  function selectTab(name, focus) {
    tabs.forEach(function (tab) {
      var on = tab.dataset.tab === name;
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
      tab.tabIndex = on ? 0 : -1;
      if (on && focus) {
        tab.focus();
      }
    });
    state.tab = name;
    // Only these two tabs show numbers over a period.
    periodBox.hidden = name !== 'overview' && name !== 'crawlers';
    try { history.replaceState(null, '', '#' + name); } catch (e) { /* not important */ }
    load();
  }

  function load() {
    var id = ++loadId;
    panel.setAttribute('aria-busy', 'true');
    panel.innerHTML = '<div class="gl-skeleton"><div></div><div></div><div></div></div>';
    call({ gl: 'panel', tab: state.tab, days: state.days }).then(function (data) {
      if (id === loadId) {
        show(data.html);
      }
    }).catch(function (error) {
      if (id === loadId) {
        panel.setAttribute('aria-busy', 'false');
        panel.innerHTML = '';
        var box = document.createElement('div');
        box.className = 'gl-empty';
        var message = document.createElement('p');
        message.textContent = error.message;
        var retry = document.createElement('button');
        retry.type = 'button';
        retry.className = 'gl-btn gl-btn-secondary gl-mt';
        retry.textContent = app.dataset.tRetry;
        retry.addEventListener('click', load);
        box.appendChild(message);
        box.appendChild(retry);
        panel.appendChild(box);
      }
    });
  }

  function show(html) {
    panel.innerHTML = html;
    panel.setAttribute('aria-busy', 'false');
    panel.querySelectorAll('[data-acc]').forEach(function (section) {
      if (accordions[section.dataset.acc] !== undefined) {
        section.open = accordions[section.dataset.acc];
      }
    });
    if (panel.querySelector('[data-catalog]')) {
      loadCatalog(false);
    }
    watchFeed();
  }

  /* ---------- forms and buttons ---------- */

  function busy(button, on) {
    if (button) {
      button.disabled = on;
    }
  }

  function post(action, body, button) {
    busy(button, true);
    return call({ gl: action, days: state.days }, body).then(function (data) {
      if (data.flash) {
        flash(data.flash.type, data.flash.text);
      }
      if (data.html) {
        show(data.html);
      }
      return data;
    }).catch(function (error) {
      flash('error', error.message);
    }).finally(function () {
      busy(button, false);
    });
  }

  app.addEventListener('submit', function (event) {
    var form = event.target;
    if (form.dataset.action) {
      event.preventDefault();
      if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
        return;
      }
      var mode = form.querySelector('[name=mode]');
      if (form.dataset.confirmPrimary && mode && mode.value === 'primary' && !window.confirm(form.dataset.confirmPrimary)) {
        return;
      }
      post(form.dataset.action, new FormData(form), form.querySelector('[type=submit]'));
    }
  });

  app.addEventListener('click', function (event) {
    var button = event.target.closest('button, a');
    if (!button || !app.contains(button)) {
      return;
    }
    if (button.classList.contains('gl-tab')) {
      selectTab(button.dataset.tab);
    } else if (button.dataset.days) {
      state.days = parseInt(button.dataset.days, 10);
      app.querySelectorAll('[data-days]').forEach(function (b) { b.setAttribute('aria-pressed', b === button ? 'true' : 'false'); });
      load();
    } else if (button.dataset.goto) {
      event.preventDefault();
      selectTab(button.dataset.goto);
      window.scrollTo({ top: 0 });
    } else if (button.dataset.post) {
      if (button.dataset.confirm && !window.confirm(button.dataset.confirm)) {
        return;
      }
      var body = new FormData();
      if (button.dataset.key) {
        body.append('key', button.dataset.key);
      }
      post(button.dataset.post, body, button);
    } else if (button.dataset.run === 'access') {
      runAccess(button);
    } else if (button.dataset.run === 'pages') {
      runPages(button);
    } else if (button.dataset.run === 'one-page') {
      runOnePage(button);
    } else if (button.dataset.missing) {
      loadMissing(button.dataset.missing, 0, button);
    } else if (button.dataset.more) {
      loadMissing(button.dataset.more, button.dataset.after, button);
    } else if (button.dataset.refresh) {
      loadCatalog(true);
    } else if (button.dataset.addRule !== undefined) {
      addRule();
    } else if (button.classList.contains('gl-rule-remove')) {
      button.closest('.gl-rule').remove();
    } else if (button.dataset.copy !== undefined) {
      copy(button);
    }
  });

  app.addEventListener('keydown', function (event) {
    if (!event.target.classList.contains('gl-tab')) {
      return;
    }
    var index = tabs.indexOf(event.target);
    var next = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: tabs.length - 1 }[event.key];
    if (next !== undefined) {
      event.preventDefault();
      selectTab(tabs[(next + tabs.length) % tabs.length].dataset.tab, true);
    }
  });

  function copy(button) {
    var text = button.closest('li, [data-copy-from]').querySelector('[data-copy-text]').textContent.trim();
    var done = function () {
      var label = button.textContent;
      button.textContent = app.dataset.tCopied;
      setTimeout(function () { button.textContent = label; }, 1500);
    };
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(done);
    }
  }

  /* ---------- crawler policy ---------- */

  app.addEventListener('change', function (event) {
    var form = event.target.closest('[data-policy]');
    if (form) {
      if (event.target.name === 'preset') {
        var blocks = JSON.parse(form.dataset.presets)[event.target.value] || [];
        form.querySelectorAll('[name="allow[]"]').forEach(function (box) {
          box.checked = blocks.indexOf(box.value) === -1;
        });
        var training = form.querySelector('[name=training]');
        if (training) {
          training.checked = event.target.value !== 'visibility';
        }
      }
      previewPolicy(form);
    }
    if (event.target.dataset.catalogLang !== undefined) {
      loadCatalog(false);
    }
    if (event.target.name === 'returns_mode' || event.target.name === 'returns_fees') {
      syncReturns();
    }
  });

  /* The screen asks "allow?", while the file and the server deal in what is blocked. */
  function policyBody(form) {
    var body = new FormData();
    form.querySelectorAll('[name="allow[]"]').forEach(function (box) {
      if (!box.checked) {
        body.append('blocked[]', box.value);
      }
    });
    ['training', 'signals'].forEach(function (name) {
      var box = form.querySelector('[name=' + name + ']');
      if (box && box.checked) {
        body.append(name, '1');
      }
    });
    return body;
  }

  function previewPolicy(form) {
    call({ gl: 'preview_policy' }, policyBody(form)).then(function (data) {
      var pre = form.querySelector('[data-preview]');
      var empty = form.querySelector('[data-preview-empty]');
      pre.textContent = data.block;
      pre.hidden = !data.block;
      empty.hidden = !!data.block;
      form.querySelectorAll('[name=preset]').forEach(function (radio) { radio.checked = radio.value === data.preset; });
    }).catch(function () { /* the preview is a convenience, and saving still reports errors */ });
  }

  app.addEventListener('click', function (event) {
    var button = event.target.closest('[data-save-policy]');
    if (button) {
      var form = button.closest('[data-policy]');
      var confirmText = !form.querySelector('[name=allow\\[\\]][data-purpose=search]:checked') ? form.dataset.confirmAll : '';
      if (confirmText && !window.confirm(confirmText)) {
        return;
      }
      post('save_policy', policyBody(form), button);
    }
  });

  /* ---------- access test ---------- */

  function runAccess(button) {
    var form = panel;
    var summary = form.querySelector('[data-access-summary]');
    button.closest('details').open = true;
    busy(button, true);
    summary.hidden = false;
    summary.textContent = app.dataset.tTesting;
    call({ gl: 'access_prepare' }, new FormData()).then(function (prep) {
      var notes = form.querySelector('[data-access-notes]');
      if (notes) {
        notes.querySelectorAll('[data-if]').forEach(function (note) {
          var key = note.dataset.if;
          note.hidden = !(key === 'cdn' ? prep.cdn : key === 'differs' ? prep.differs : false);
          if (key === 'cdn' && prep.cdn) {
            var text = note.querySelector('p');
            text.textContent = text.textContent.replace('%cdn%', prep.cdn);
          }
        });
      }
      var done = 0;
      var problems = 0;
      function next() {
        if (done === prep.bots.length) {
          summary.textContent = problems > 0 ? app.dataset.tTestProblems.replace('%count%', problems) : app.dataset.tTestOk;
          busy(button, false);
          return;
        }
        var token = prep.bots[done];
        var cell = form.querySelector('[data-access="' + token + '"]');
        var body = new FormData();
        body.append('bot', token);
        if (cell) {
          cell.innerHTML = '<i class="material-icons gl-spin" aria-hidden="true">autorenew</i>';
        }
        call({ gl: 'access_bot' }, body).then(function (data) {
          if (cell) {
            cell.innerHTML = data.html;
          }
          if (data.problem) {
            problems++;
          }
        }).catch(function () {
          if (cell) {
            cell.textContent = '?';
          }
        }).finally(function () {
          done++;
          summary.textContent = app.dataset.tTestingN.replace('%done%', done).replace('%total%', prep.bots.length);
          next();
        });
      }
      next();
    }).catch(function (error) {
      flash('error', error.message);
      busy(button, false);
    });
  }

  /* ---------- catalogue ---------- */

  function loadCatalog(refresh) {
    var box = panel.querySelector('[data-catalog]');
    if (!box) {
      return;
    }
    var select = panel.querySelector('[data-catalog-lang]');
    var params = { gl: 'catalog', lang: select ? select.value : '' };
    if (refresh) {
      params.refresh = 1;
    }
    box.innerHTML = '<p class="gl-muted gl-mt"><i class="material-icons gl-spin" aria-hidden="true">autorenew</i> ' + app.dataset.tChecking + '</p>';
    call(params).then(function (data) {
      box.innerHTML = data.html;
    }).catch(function (error) {
      box.textContent = error.message;
    });
  }

  function loadMissing(fact, after, button) {
    var box = panel.querySelector('[data-missing-box]');
    var select = panel.querySelector('[data-catalog-lang]');
    busy(button, true);
    call({ gl: 'catalog_missing', fact: fact, after: after, lang: select ? select.value : '' }).then(function (data) {
      box.hidden = false;
      if (parseInt(after, 10) > 0) {
        var more = box.querySelector('[data-more]');
        if (more) {
          more.closest('.gl-actions').remove();
        }
        var holder = document.createElement('div');
        holder.innerHTML = data.html;
        box.querySelector('tbody').insertAdjacentHTML('beforeend', holder.querySelector('tbody').innerHTML);
        var nextMore = holder.querySelector('.gl-actions');
        if (nextMore) {
          box.appendChild(nextMore);
        }
      } else {
        box.innerHTML = data.html;
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    }).catch(function (error) {
      flash('error', error.message);
    }).finally(function () {
      busy(button, false);
    });
  }

  /* ---------- page check ---------- */

  function checkPage(sample, results) {
    var body = new FormData();
    body.append('url', sample.url);
    body.append('product', sample.product ? '1' : '');
    body.append('key', sample.key);
    return call({ gl: 'page_check' }, body).then(function (data) {
      results.insertAdjacentHTML('beforeend', data.html);
    });
  }

  function runPages(button) {
    var results = panel.querySelector('[data-page-results]');
    busy(button, true);
    results.innerHTML = '<p class="gl-muted gl-mt" data-page-wait><i class="material-icons gl-spin" aria-hidden="true">autorenew</i> ' + app.dataset.tChecking + '</p>';
    call({ gl: 'page_samples' }, new FormData()).then(function (data) {
      var run = Promise.resolve();
      data.samples.forEach(function (sample) {
        run = run.then(function () { return checkPage(sample, results); });
      });
      return run;
    }).catch(function (error) {
      flash('error', error.message);
    }).finally(function () {
      var wait = results.querySelector('[data-page-wait]');
      if (wait) {
        wait.remove();
      }
      busy(button, false);
    });
  }

  function runOnePage(button) {
    var input = panel.querySelector('[data-page-url]');
    var results = panel.querySelector('[data-page-results]');
    busy(button, true);
    var product = panel.querySelector('[data-page-product]');
    checkPage({ url: input.value, product: product && product.checked, key: 'custom' }, results).catch(function (error) {
      flash('error', error.message);
    }).finally(function () {
      busy(button, false);
    });
  }

  /* ---------- delivery and returns form ---------- */

  function addRule() {
    var template = panel.querySelector('[data-rule-template]');
    var list = panel.querySelector('[data-rules]');
    var index = Date.now();
    var holder = document.createElement('div');
    holder.innerHTML = template.innerHTML.replace(/__i__/g, index);
    list.appendChild(holder.firstElementChild);
  }

  function syncReturns() {
    var form = panel.querySelector('[data-returns]');
    if (!form) {
      return;
    }
    var mode = form.querySelector('[name=returns_mode]').value;
    var fees = form.querySelector('[name=returns_fees]').value;
    form.querySelectorAll('[data-when-mode]').forEach(function (el) {
      el.hidden = el.dataset.whenMode.split(' ').indexOf(mode) === -1;
    });
    form.querySelector('[data-when-fee]').hidden = fees !== 'fixed' || mode === 'none' || mode === '';
  }

  /* ---------- product feed ---------- */

  var feedTimer = null;

  /* The build runs in the background and does not need this page. This shows it moving, and gives it a push on a host that refuses requests from the shop to itself. */
  function watchFeed() {
    clearTimeout(feedTimer);
    if (!panel.querySelector('[data-feed-building]')) {
      return;
    }
    feedTimer = setTimeout(function () {
      call({ gl: 'feed_step' }, new FormData()).then(function (data) {
        var list = panel.querySelector('[data-feed-list]');
        if (list) {
          list.innerHTML = data.list;
          watchFeed();
        }
      }).catch(function () { /* the next look at this tab will show where it got to */ });
    }, 2000);
  }

  /* ---------- chart tooltip ---------- */

  var tip = null;
  function hideTip() {
    if (tip) {
      tip.hidden = true;
    }
    var on = app.querySelector('.gl-col.is-on');
    if (on) {
      on.classList.remove('is-on');
    }
  }
  app.addEventListener('mousemove', function (event) {
    var col = event.target.closest ? event.target.closest('.gl-col') : null;
    if (!col) {
      hideTip();
      return;
    }
    if (!tip) {
      tip = document.createElement('div');
      tip.id = 'gl-tip';
      document.body.appendChild(tip);
    }
    if (!col.classList.contains('is-on')) {
      hideTip();
      col.classList.add('is-on');
      var parts = col.dataset.tip.split('|');
      tip.innerHTML = '';
      var head = document.createElement('b');
      head.textContent = parts[0];
      tip.appendChild(head);
      parts.slice(1).forEach(function (line) {
        var row = document.createElement('div');
        row.textContent = line;
        tip.appendChild(row);
      });
    }
    tip.hidden = false;
    var x = Math.min(event.clientX + 14, window.innerWidth - tip.offsetWidth - 8);
    var y = Math.max(8, event.clientY - tip.offsetHeight - 10);
    tip.style.left = x + 'px';
    tip.style.top = y + 'px';
  });
  app.addEventListener('mouseleave', hideTip);

  /* ---------- start ---------- */

  var first = (location.hash || '').replace('#', '');
  selectTab(tabs.some(function (t) { return t.dataset.tab === first; }) ? first : 'overview');
  // Runs whatever background jobs are due. Nothing on the page waits for it.
  call({ gl: 'tick' }, new FormData()).catch(function () { /* cron or the next visit will catch up */ });
}());
