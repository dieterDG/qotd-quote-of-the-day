(function () {
  "use strict";

  function setPlain(el, text) {
    if (!el) return;
    el.textContent = text || "";
  }

  async function loadQuote(container) {
    const endpoint = (window.QOTD && window.QOTD.endpoint) ? window.QOTD.endpoint : null;
    if (!endpoint) return;

    try {
      const res = await fetch(endpoint, { credentials: "same-origin" });
      if (!res.ok) return;

      const data = await res.json();
      const textEl = container.querySelector(".qotd__text");
      const authorEl = container.querySelector(".qotd__author");
      const sourceEl = container.querySelector(".qotd__source");

      if (!data || !data.has_quote) {
        setPlain(textEl, "");
        setPlain(authorEl, "");
        setPlain(sourceEl, "");
        return;
      }

      setPlain(textEl, data.text || "");

      const author = (data.author || "").trim();
      const extra = (data.extra || "").trim();

      // Typografische Trennung wie bisher (nur PlainText)
      setPlain(authorEl, author ? "— " + author : "");
      setPlain(sourceEl, extra ? (author ? " · " : "— ") + extra : "");
    } catch (e) {
      // absichtlich still
    }
  }

  function initAll() {
    const nodes = document.querySelectorAll('[data-qotd="1"]');
    nodes.forEach(loadQuote);
  }

  window.addEventListener("load", initAll);
})();
