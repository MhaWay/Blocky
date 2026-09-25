/**
 * Blocky frontend islands.
 *
 * Self-contained behaviour for interactive blocks. The previous design shipped
 * one inline <script> per block instance; WordPress.org requires JS/CSS to be
 * loaded through wp_enqueue_* functions, so every island now lives here and is
 * activated by scanning the DOM for its data-* contract.
 *
 * Editor canvas: server-side rendering marks every block root with
 * data-bky-id in editor mode; we skip those roots so the canvas keeps its own
 * React-driven behaviour and islands run on the public front end only.
 */

function roots(selector: string): HTMLElement[] {
  return Array.from(document.querySelectorAll<HTMLElement>(selector + ':not([data-bky-id])'));
}

function initTabs(): void {
  roots('[data-bky-tabs-root]').forEach((root) => {
    const tabs = Array.from(root.querySelectorAll<HTMLElement>('[data-bky-tab]'));
    const panels = Array.from(root.querySelectorAll<HTMLElement>('[data-bky-panel]'));
    tabs.forEach((button) => {
      button.addEventListener('click', () => {
        const index = button.getAttribute('data-bky-tab');
        tabs.forEach((tab) => {
          const active = tab.getAttribute('data-bky-tab') === index;
          tab.setAttribute('aria-selected', active ? 'true' : 'false');
          tab.className =
            'rounded-button px-4 py-2 text-sm font-medium ' +
            (active ? 'bg-accent-base text-text-on-accent' : 'bg-surface-elevated text-text-base');
        });
        panels.forEach((panel) => {
          panel.hidden = panel.getAttribute('data-bky-panel') !== index;
        });
      });
    });
  });
}

function initCarousels(): void {
  // Shared by image carousels and content carousels (same DOM contract).
  roots('[data-bky-carousel-root]').forEach((root) => {
    const track = root.querySelector('[data-bky-carousel-track]');
    if (!track) return;
    const slides = Array.from(root.querySelectorAll<HTMLElement>('[data-bky-carousel-slide]'));
    const dots = Array.from(root.querySelectorAll<HTMLElement>('[data-bky-carousel-dot]'));
    if (slides.length === 0) return;

    let index = 0;
    let timer: number | null = null;
    const autoPlay = root.getAttribute('data-bky-carousel-autoplay') === 'true';
    const interval = Math.max(
      1000,
      Number(root.getAttribute('data-bky-carousel-interval') || 5000)
    );

    const sync = (): void => {
      dots.forEach((dot, i) => {
        dot.className =
          'h-2.5 w-2.5 rounded-full transition-colors ' +
          (i === index ? 'bg-accent-base' : 'bg-border-strong/40');
      });
    };
    const go = (next: number): void => {
      index = ((next % slides.length) + slides.length) % slides.length;
      const slide = slides[index];
      if (!slide) return;
      slide.scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' });
      sync();
    };
    const restart = (): void => {
      if (!autoPlay) return;
      if (timer) window.clearInterval(timer);
      timer = window.setInterval(() => {
        go(index + 1);
      }, interval);
    };

    const prev = root.querySelector<HTMLElement>('[data-bky-carousel-prev]');
    const next = root.querySelector<HTMLElement>('[data-bky-carousel-next]');
    if (prev)
      prev.addEventListener('click', () => {
        go(index - 1);
        restart();
      });
    if (next)
      next.addEventListener('click', () => {
        go(index + 1);
        restart();
      });
    dots.forEach((dot, i) =>
      dot.addEventListener('click', () => {
        go(i);
        restart();
      })
    );
    sync();
    restart();
  });
}

function initHotspots(): void {
  roots('[data-bky-hotspot-root]').forEach((root) => {
    const buttons = Array.from(root.querySelectorAll<HTMLElement>('[data-bky-hotspot-button]'));
    const panels = Array.from(root.querySelectorAll<HTMLElement>('[data-bky-hotspot-panel]'));
    if (buttons.length === 0 || panels.length === 0) return;

    const activate = (index: number): void => {
      buttons.forEach((button, i) => {
        button.classList.toggle('ring-4', i === index);
        button.classList.toggle('ring-white/60', i === index);
      });
      panels.forEach((panel, i) => {
        panel.classList.toggle('hidden', i !== index);
      });
    };
    buttons.forEach((button, index) => {
      button.addEventListener('click', () => {
        activate(index);
      });
    });
    activate(0);
  });
}

function initBeforeAfter(): void {
  roots('[data-bky-before-after-root]').forEach((root) => {
    const range = root.querySelector<HTMLInputElement>('[data-bky-before-after-range]');
    if (!range) return;
    const update = (): void => {
      root.style.setProperty('--bky-before-after', range.value + '%');
    };
    range.addEventListener('input', update);
    update();
  });
}

function initCountdowns(): void {
  roots('[data-bky-countdown-root]').forEach((root) => {
    const target = root.getAttribute('data-bky-target-date');
    if (!target) return;
    const nodes = {
      days: root.querySelector<HTMLElement>('[data-bky-countdown-unit=days]'),
      hours: root.querySelector<HTMLElement>('[data-bky-countdown-unit=hours]'),
      minutes: root.querySelector<HTMLElement>('[data-bky-countdown-unit=minutes]'),
      seconds: root.querySelector<HTMLElement>('[data-bky-countdown-unit=seconds]'),
    };
    const targetTime = Date.parse(target);
    if (Number.isNaN(targetTime)) return;

    const draw = (): void => {
      const diff = Math.max(0, targetTime - Date.now());
      const days = Math.floor(diff / 86400000);
      const hours = Math.floor((diff % 86400000) / 3600000);
      const minutes = Math.floor((diff % 3600000) / 60000);
      const seconds = Math.floor((diff % 60000) / 1000);
      if (nodes.days) nodes.days.textContent = String(days).padStart(2, '0');
      if (nodes.hours) nodes.hours.textContent = String(hours).padStart(2, '0');
      if (nodes.minutes) nodes.minutes.textContent = String(minutes).padStart(2, '0');
      if (nodes.seconds) nodes.seconds.textContent = String(seconds).padStart(2, '0');
    };
    draw();
    window.setInterval(draw, 1000);
  });
}

function initAnimatedHeadlines(): void {
  roots('[data-bky-animated-headline-root]').forEach((root) => {
    const target = root.querySelector<HTMLElement>('[data-bky-animated-word]');
    if (!target) return;
    let words: string[] = [];
    const encoded = root.getAttribute('data-bky-animated-words') || '';
    try {
      const decoded: unknown = JSON.parse(atob(encoded));
      words = Array.isArray(decoded)
        ? decoded.map((item) => String(item ?? '').trim()).filter(Boolean)
        : [];
    } catch {
      words = [];
    }
    if (words.length < 2) return;

    const effect = root.getAttribute('data-bky-animated-effect') || 'rotate';
    const interval = Number(root.getAttribute('data-bky-animated-interval') || 3000);
    let index = 0;

    const renderWord = (next: string): void => {
      if (effect === 'typing') {
        target.textContent = '';
        const chars = next.split('');
        let cursor = 0;
        const typing = window.setInterval(() => {
          target.textContent = chars.slice(0, cursor + 1).join('');
          cursor += 1;
          if (cursor >= chars.length) window.clearInterval(typing);
        }, 40);
        return;
      }
      target.style.transition = 'opacity 220ms ease, transform 220ms ease';
      target.style.opacity = '0';
      target.style.transform = effect === 'slide' ? 'translateY(35%)' : 'rotateX(-18deg)';
      window.setTimeout(() => {
        target.textContent = next;
        target.style.opacity = '1';
        target.style.transform = 'translateY(0) rotateX(0deg)';
      }, 200);
    };
    window.setInterval(
      () => {
        index = (index + 1) % words.length;
        const next = words[index];
        if (next === undefined) return;
        renderWord(next);
      },
      Math.max(1000, interval)
    );
  });
}

function readCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
  return match?.[1] ? decodeURIComponent(match[1]) : null;
}

function initThemeToggles(): void {
  roots('[data-bky-theme-toggle]').forEach((button) => {
    const mode = readCookie('ggapb_mode') || 'light';
    const icon = button.querySelector<HTMLElement>('.bky-theme-toggle-icon');
    if (icon) icon.textContent = mode === 'dark' ? '\u{1F319}' : '\u2600\uFE0F';
    button.addEventListener('click', () => {
      const next = (readCookie('ggapb_mode') || 'light') === 'dark' ? 'light' : 'dark';
      document.cookie = 'ggapb_mode=' + next + '; path=/; SameSite=Lax';
      window.location.reload();
    });
  });
}

function initIslands(): void {
  initTabs();
  initCarousels();
  initHotspots();
  initBeforeAfter();
  initCountdowns();
  initAnimatedHeadlines();
  initThemeToggles();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initIslands, { once: true });
} else {
  initIslands();
}
