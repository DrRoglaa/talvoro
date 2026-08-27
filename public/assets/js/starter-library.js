(() => {
  'use strict';

  document.querySelectorAll('.starter-library').forEach((library) => {
    const input = library.querySelector('[data-starter-library-search]');
    const cards = [...library.querySelectorAll('[data-starter-library-card]')];
    const empty = library.querySelector('[data-starter-library-empty]');
    if (!input || cards.length === 0) return;

    const filter = () => {
      const query = input.value.trim().toLocaleLowerCase();
      let visible = 0;
      cards.forEach((card) => {
        const haystack = String(card.dataset.starterSearchText || '').toLocaleLowerCase();
        const matches = query === '' || haystack.includes(query);
        card.hidden = !matches;
        if (matches) visible += 1;
      });
      if (empty) empty.hidden = visible !== 0;
    };

    input.addEventListener('input', filter);
  });
})();
