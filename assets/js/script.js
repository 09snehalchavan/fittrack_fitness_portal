/* Sanskar Kuber Chavan — Portfolio scripts (vanilla JS) */
(function () {
  'use strict';

  var doc = document;
  var $ = function (s, c) { return (c || doc).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || doc).querySelectorAll(s)); };

  /* ---------- Footer year ---------- */
  var yr = $('#year');
  if (yr) { yr.textContent = new Date().getFullYear(); }

  /* ---------- Header scroll state ---------- */
  var header = $('.site-header');
  function onScroll() {
    if (!header) return;
    header.classList.toggle('scrolled', window.scrollY > 12);
  }
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  /* ---------- Mobile navigation ---------- */
  var toggle = $('.nav-toggle');
  var nav = $('#primary-nav');
  function setNav(open) {
    if (!toggle || !nav) return;
    nav.classList.toggle('open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  }
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      setNav(toggle.getAttribute('aria-expanded') !== 'true');
    });
    $$('a', nav).forEach(function (a) {
      a.addEventListener('click', function () { setNav(false); });
    });
    doc.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { setNav(false); toggle.focus(); }
    });
    window.addEventListener('resize', function () {
      if (window.innerWidth > 900) setNav(false);
    });
  }

  /* ---------- Active nav link on scroll ---------- */
  var navLinks = $$('.primary-nav ul a');
  var sections = navLinks
    .map(function (a) { return $(a.getAttribute('href')); })
    .filter(Boolean);
  if ('IntersectionObserver' in window && sections.length) {
    var spy = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          navLinks.forEach(function (a) {
            a.classList.toggle('active', a.getAttribute('href') === '#' + en.target.id);
          });
        }
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    sections.forEach(function (s) { spy.observe(s); });
  }

  /* ---------- Reveal on scroll ---------- */
  var reveals = $$('.reveal');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add('in');
          io.unobserve(en.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    reveals.forEach(function (el, i) {
      // light stagger for items that share a grid row
      el.style.transitionDelay = ((i % 3) * 70) + 'ms';
      io.observe(el);
    });
  } else {
    reveals.forEach(function (el) { el.classList.add('in'); });
  }

  /* ---------- Project preview image fallback ---------- */
  $$('img[data-shot]').forEach(function (img) {
    function fail() { img.parentNode.classList.add('failed'); }
    img.addEventListener('error', fail);
    if (img.complete && img.naturalWidth === 0) fail();
  });

  /* ---------- Contact form ---------- */
  var form = $('#contact-form');
  if (!form) return;

  var statusEl = $('#form-status');
  var submitBtn = $('#submit-btn');
  var label = $('.btn-label', submitBtn);
  var tokenInput = $('#csrf_token');
  var endpoint = form.getAttribute('action');
  var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  var phoneRe = /^[0-9+()\-\s]{7,20}$/;

  function setStatus(type, msg) {
    statusEl.className = 'form-status' + (type ? ' ' + type : '');
    statusEl.textContent = msg || '';
  }

  function fieldError(id, msg) {
    var input = $('#' + id);
    var err = $('#' + id + '-err');
    var wrap = input.closest('.field');
    if (wrap) wrap.classList.toggle('invalid', !!msg);
    input.setAttribute('aria-invalid', msg ? 'true' : 'false');
    if (err) err.textContent = msg || '';
  }

  function validate() {
    var ok = true;
    var name = form.name.value.trim();
    var phone = form.phone.value.trim();
    var email = form.email.value.trim();
    var message = form.message.value.trim();

    fieldError('name', name.length < 2 ? 'Please enter your name.' : '');
    if (name.length < 2) ok = false;

    var phoneBad = phone !== '' && !phoneRe.test(phone);
    fieldError('phone', phoneBad ? 'Enter a valid phone number.' : '');
    if (phoneBad) ok = false;

    var emailBad = !emailRe.test(email);
    fieldError('email', emailBad ? 'Enter a valid email address.' : '');
    if (emailBad) ok = false;

    var msgBad = message.length < 10;
    fieldError('message', msgBad ? 'Please write at least 10 characters.' : '');
    if (msgBad) ok = false;

    if (!ok) {
      var first = $('.field.invalid input, .field.invalid textarea', form);
      if (first) first.focus();
    }
    return ok;
  }

  ['name', 'phone', 'email', 'message'].forEach(function (id) {
    $('#' + id).addEventListener('blur', function () {
      if ($('#' + id).value.trim() !== '' || id === 'name' || id === 'email' || id === 'message') {
        // validate on blur only if the user has typed something or left a required field
        if ($('#' + id).dataset.touched) validate();
      }
    });
    $('#' + id).addEventListener('input', function () {
      $('#' + id).dataset.touched = '1';
      if ($('#' + id).closest('.field').classList.contains('invalid')) validate();
    });
  });

  // Fetch a fresh CSRF token (also starts the PHP session used for rate limiting)
  function loadToken() {
    return fetch(endpoint + '?action=token', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d && d.token) tokenInput.value = d.token; })
      .catch(function () { /* handled at submit time */ });
  }
  loadToken();

  function setLoading(on) {
    submitBtn.disabled = on;
    label.textContent = on ? 'Sending…' : 'Send Message';
    var sp = $('.spinner', submitBtn);
    if (on && !sp) {
      var s = doc.createElement('span');
      s.className = 'spinner';
      s.setAttribute('aria-hidden', 'true');
      submitBtn.insertBefore(s, label);
    } else if (!on && sp) {
      sp.remove();
    }
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    setStatus('', '');
    if (!validate()) return;

    setLoading(true);

    var send = function () {
      return fetch(endpoint, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      }).then(function (r) {
        return r.json().catch(function () { return { success: false, message: 'Unexpected server response.' }; })
          .then(function (data) { return { status: r.status, data: data }; });
      });
    };

    var chain = tokenInput.value ? Promise.resolve() : loadToken();
    chain.then(send).then(function (res) {
      var d = res.data || {};
      if (d.success) {
        form.reset();
        $$('.field', form).forEach(function (f) { f.classList.remove('invalid'); });
        setStatus('ok', d.message || 'Thank you! Your message has been sent. I will get back to you soon.');
      } else {
        if (d.errors) {
          Object.keys(d.errors).forEach(function (k) {
            if ($('#' + k + '-err')) fieldError(k, d.errors[k]);
          });
        }
        setStatus('error', d.message || 'Something went wrong. Please try again or message me on WhatsApp.');
      }
      return loadToken();
    }).catch(function () {
      setStatus('error', 'Could not reach the server. Please try again, or contact me on WhatsApp or by phone.');
    }).then(function () {
      setLoading(false);
    });
  });
})();
