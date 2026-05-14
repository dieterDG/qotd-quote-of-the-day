(function () {
  "use strict";

  function setPlain(el, text) {
    if (!el) return;
    el.textContent = text || "";
  }

  function createSeparator(className, text) {
    var span = document.createElement("span");
    span.className = className;
    span.textContent = text;
    return span;
  }

  async function loadQuote(container) {
    const endpoint = (window.QOTD && window.QOTD.endpoint) ? window.QOTD.endpoint : null;
    if (!endpoint) return;

    // Initiale min-height: geschätzte 2 Zeilen
    const lineHeight = parseFloat(getComputedStyle(container).lineHeight) || 24;
    container.style.minHeight = (lineHeight * 2) + 'px';
    container.setAttribute("data-qotd-loading", "1");

    try {
      const res = await fetch(endpoint, { credentials: "same-origin" });
      if (!res.ok) {
        container.removeAttribute("data-qotd-loading");
        container.style.minHeight = '';
        return;
      }

      const data = await res.json();
      const textEl = container.querySelector(".qotd__text");
      const metaEl = container.querySelector(".qotd__meta");

      if (!data || !data.has_quote) {
        setPlain(textEl, "");
        if (metaEl) metaEl.innerHTML = "";
        container.removeAttribute("data-qotd-loading");
        container.style.minHeight = '';
        return;
      }

      setPlain(textEl, data.text || "");

      const author = (data.author || "").trim();
      const extra = (data.extra || "").trim();

      // Meta-Bereich dynamisch aufbauen mit eigenen Elementen für Trennzeichen
      if (metaEl) {
        metaEl.innerHTML = "";

        if (author || extra) {
          metaEl.appendChild(createSeparator("qotd__separator", "— "));
        }

        if (author) {
          var authorEl = document.createElement("span");
          authorEl.className = "qotd__author";
          authorEl.textContent = author;
          metaEl.appendChild(authorEl);
        }

        if (author && extra) {
          metaEl.appendChild(createSeparator("qotd__divider", " · "));
        }

        if (extra) {
          var sourceEl = document.createElement("span");
          sourceEl.className = "qotd__source";
          sourceEl.textContent = extra;
          metaEl.appendChild(sourceEl);
        }
      }

      container.removeAttribute("data-qotd-loading");
      
      // WICHTIG: Min-height auf AKTUELLE Höhe setzen (nach dem Text geladen ist)
      requestAnimationFrame(() => {
        const actualHeight = container.getBoundingClientRect().height;
        container.style.minHeight = actualHeight + 'px';
      });
      
    } catch (e) {
      container.removeAttribute("data-qotd-loading");
      container.style.minHeight = '';
    }
  }

  function initAll() {
    const nodes = document.querySelectorAll('[data-qotd="1"]');
    nodes.forEach(loadQuote);
  }

  window.addEventListener("load", initAll);
})();
