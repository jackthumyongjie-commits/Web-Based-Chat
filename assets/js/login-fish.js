/**
 * Login fishing story:
 * 1) Boat sails to center
 * 2) Cast the rod
 * 3) Reel login form out of the water
 */
(function () {
  const root = document.getElementById('fishLogin');
  if (!root) return;

  const story = document.getElementById('fishStory');
  const svg = document.getElementById('fishLineSvg');
  const path = document.getElementById('fishLinePath');
  const rodTip = document.getElementById('rodTip');
  const hookTop = document.getElementById('hookTop');
  const loginInput = document.getElementById('login');
  const skip = root.dataset.skip === '1';

  const I18N = (window.WebConnect && window.WebConnect.I18N) || {};
  const t = (key, fallback) => I18N[key] || fallback;

  const lines = {
    sail: t('auth.fish_story_sail', '划船到湖心…'),
    cast: t('auth.fish_story_cast', '甩出鱼竿！'),
    catch: t('auth.fish_story_catch', '把登录页从水里钓上来…'),
    ready: t('auth.fish_story_ready', '渔获登录，请上船'),
  };

  let readyForFocus = false;

  function keepTop() {
    if (window.scrollY !== 0 || document.documentElement.scrollTop !== 0 || document.body.scrollTop !== 0) {
      window.scrollTo(0, 0);
      document.documentElement.scrollTop = 0;
      document.body.scrollTop = 0;
    }
  }

  function setStory(text) {
    if (story) story.textContent = text;
  }

  function setPhase(name) {
    root.classList.remove('phase-sail', 'phase-cast', 'phase-catch', 'phase-idle');
    root.classList.add('phase-' + name);
    keepTop();
  }

  function focusLogin() {
    if (!loginInput) return;
    readyForFocus = true;
    try {
      loginInput.focus({ preventScroll: true });
    } catch (e) {
      loginInput.focus();
    }
    keepTop();
  }

  let tWave = 0;
  let raf = 0;
  let lineVisible = false;

  function pt(el) {
    const r = el.getBoundingClientRect();
    const s = svg.getBoundingClientRect();
    return {
      x: r.left + r.width / 2 - s.left,
      y: r.top + r.height / 2 - s.top,
    };
  }

  function resizeSvg() {
    if (!svg) return;
    const r = root.getBoundingClientRect();
    svg.setAttribute('width', String(r.width));
    svg.setAttribute('height', String(r.height));
    svg.setAttribute('viewBox', '0 0 ' + r.width + ' ' + r.height);
  }

  function drawLine() {
    if (!path || !rodTip || !hookTop || !lineVisible) {
      raf = requestAnimationFrame(drawLine);
      return;
    }
    tWave += 0.04;
    const a = pt(rodTip);
    const b = pt(hookTop);
    const midX = (a.x + b.x) / 2 + Math.sin(tWave) * 16;
    const midY = (a.y + b.y) / 2 + Math.cos(tWave * 0.85) * 14 + 18;
    path.setAttribute(
      'd',
      'M ' + a.x.toFixed(1) + ' ' + a.y.toFixed(1) +
      ' Q ' + midX.toFixed(1) + ' ' + midY.toFixed(1) +
      ' ' + b.x.toFixed(1) + ' ' + b.y.toFixed(1)
    );
    path.style.opacity = '1';
    raf = requestAnimationFrame(drawLine);
  }

  function showLine(show) {
    lineVisible = show;
    if (path) path.style.opacity = show ? '1' : '0';
  }

  function runStory() {
    setPhase('sail');
    setStory(lines.sail);
    showLine(false);

    setTimeout(() => {
      setPhase('cast');
      setStory(lines.cast);
      showLine(true);
    }, 2400);

    setTimeout(() => {
      setPhase('catch');
      setStory(lines.catch);
      showLine(true);
    }, 3800);

    setTimeout(() => {
      setPhase('idle');
      setStory(lines.ready);
      showLine(true);
      focusLogin();
    }, 5600);
  }

  // Lock viewport to the lake surface (top). Autofocus / password managers
  // used to scroll down to the underwater login form on first paint.
  keepTop();
  document.documentElement.classList.add('wc-fish-lock');
  document.body.classList.add('wc-fish-lock');

  window.addEventListener('scroll', keepTop, { passive: true });
  document.addEventListener('focusin', (e) => {
    if (!readyForFocus && root.contains(e.target)) {
      e.target.blur();
      keepTop();
    }
  }, true);

  resizeSvg();
  window.addEventListener('resize', () => {
    resizeSvg();
    keepTop();
  });

  if (skip) {
    setPhase('idle');
    setStory(lines.ready);
    showLine(true);
    setTimeout(focusLogin, 50);
  } else {
    runStory();
  }

  requestAnimationFrame(() => {
    keepTop();
    resizeSvg();
    drawLine();
  });

  window.addEventListener('beforeunload', () => cancelAnimationFrame(raf));
})();
