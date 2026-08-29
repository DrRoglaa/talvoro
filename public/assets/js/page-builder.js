(() => {
  const root = document.querySelector('[data-page-builder]');
  if (!root) return;

  const form = root.closest('form');
  const list = root.querySelector('[data-block-list]');
  const outline = root.querySelector('[data-block-outline]');
  const empty = root.querySelector('[data-blocks-empty]');
  const hidden = root.querySelector('[data-page-blocks-json]');
  const initialNode = root.querySelector('[data-page-blocks-initial]');
  const mediaNode = root.querySelector('[data-media-library-initial]');
  const patternsNode = root.querySelector('[data-patterns-initial]');
  const contentModelsNode = root.querySelector('[data-content-models-initial]');
  const configNode = root.querySelector('[data-builder-config]');
  const previewFrame = root.querySelector('[data-builder-preview]');
  const previewStage = root.querySelector('[data-preview-stage]');
  const workspace = root.querySelector('.page-builder-workspace');
  const previewFocusButton = root.querySelector('[data-preview-focus]');
  const countNode = root.querySelector('[data-block-count]');
  const builderMode = root.dataset.builderMode || form?.dataset.builderMode || 'page';

  const parseJson = (node, fallback) => {
    try { const value = JSON.parse(node?.textContent || ''); return value ?? fallback; }
    catch { return fallback; }
  };

  let blocks = parseJson(initialNode, []);
  let mediaAssets = parseJson(mediaNode, []);
  let patterns = parseJson(patternsNode, []);
  let contentModels = parseJson(contentModelsNode, []);
  const config = parseJson(configNode, {});
  if (!Array.isArray(blocks)) blocks = [];
  if (!Array.isArray(mediaAssets)) mediaAssets = [];
  if (!Array.isArray(patterns)) patterns = [];
  if (!Array.isArray(contentModels)) contentModels = [];
  blocks = blocks.map((block) => ({ style_tone: 'default', style_width: 'normal', style_spacing: 'normal', style_alignment: 'left', style_variant: 'default', enabled: block?.enabled !== false, ...block }));

  let selectedId = blocks[0]?.id || '';
  let collapsed = new Set();
  let paletteInsertIndex = null;
  let deletedSnapshot = null;
  let previewTimer = 0;
  let draggedId = '';
  let previewFocused = false;
  const fileState = new Map();
  const objectUrls = new Map();
  const announceChange = () => hidden?.dispatchEvent(new Event('change', { bubbles: true }));

  const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;').replaceAll("'", '&#039;');

  const id = () => {
    const bytes = new Uint8Array(8);
    crypto.getRandomValues(bytes);
    return Array.from(bytes, (n) => n.toString(16).padStart(2, '0')).join('');
  };

  const blockName = (blockOrType) => {
    const block = typeof blockOrType === 'string' ? { type: blockOrType } : (blockOrType || {});
    const names = { hero: 'Hero banner', image_text: 'Image + text', centered_intro: 'Centered introduction', values: 'Trust / value strip', cards: 'Featured cards', gallery: 'Image gallery', testimonials: 'Testimonials', faq: 'FAQ', stats: 'Statistics', custom: 'Custom section', latest_posts: 'Latest blog posts', collection: 'Connected content', contact: 'Contact form', cta: 'Call to action', pattern: 'Synced pattern' };
    if (block.type === 'pattern') return patternById(block.pattern_id)?.name || names.pattern;
    return names[block.type] || 'Page section';
  };

  const iconNames = {
    heart: 'Heart', home: 'Home', award: 'Award / standards', clock: 'Clock / experience',
    shield: 'Shield', paw: 'Paw', star: 'Star', leaf: 'Leaf', sparkles: 'Sparkles', support: 'Support'
  };

  const iconSvg = (name) => {
    const paths = {
      home: '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10.5V21h14V10.5"/><path d="M9 21v-6h6v6"/>',
      award: '<circle cx="12" cy="8" r="5"/><path d="m9 13-1.5 8L12 18l4.5 3-1.5-8"/><path d="m10.2 8 1.1 1.1 2.5-2.5"/>',
      clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
      shield: '<path d="M12 3 20 6v5c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6l8-3Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/>',
      paw: '<circle cx="7" cy="8" r="2"/><circle cx="17" cy="8" r="2"/><circle cx="5" cy="13" r="2"/><circle cx="19" cy="13" r="2"/><path d="M8 18c0-3 1.8-5 4-5s4 2 4 5c0 2-1.4 3-4 3s-4-1-4-3Z"/>',
      star: '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/>',
      leaf: '<path d="M20 4C10 4 5 8.5 5 15c0 3 2 5 5 5 6.5 0 10-6 10-16Z"/><path d="M4 21c3-6 7-10 13-13"/>',
      sparkles: '<path d="m12 3 1.2 3.8L17 8l-3.8 1.2L12 13l-1.2-3.8L7 8l3.8-1.2L12 3Z"/><path d="m19 14 .7 2.3L22 17l-2.3.7L19 20l-.7-2.3L16 17l2.3-.7L19 14Z"/>',
      support: '<path d="M4 13c2-4 4-6 8-6s6 2 8 6"/><path d="M4 13v4a2 2 0 0 0 2 2h2v-7H6a2 2 0 0 0-2 1Z"/><path d="M20 13v4a2 2 0 0 1-2 2h-2v-7h2a2 2 0 0 1 2 1Z"/>',
      heart: '<path d="M20.8 5.9c0 6.2-8.8 13-8.8 13S3.2 12.1 3.2 5.9A4.7 4.7 0 0 1 12 3.6a4.7 4.7 0 0 1 8.8 2.3Z"/>'
    };
    return `<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">${paths[name] || paths.heart}</svg>`;
  };

  const patternById = (patternId) => patterns.find((pattern) => Number(pattern.id) === Number(patternId)) || null;
  const mediaById = (mediaId) => mediaAssets.find((asset) => Number(asset.id) === Number(mediaId)) || null;
  const modelByKey = (modelKey) => contentModels.find((model) => String(model.model_key) === String(modelKey)) || null;

  const cloneBlock = (block) => {
    const copy = structuredClone(block);
    copy.id = id();
    copy.enabled = copy.enabled !== false;
    return copy;
  };

  const defaultBlock = (type) => {
    const blockId = id();
    if (type === 'hero') return { id: blockId, type, enabled: true, eyebrow: 'CARE. LOVE. PURPOSE.', heading: 'Preserving the beauty of a *timeless* breed.', intro: 'Introduce what matters most and guide visitors to the next step.', primary_enabled: true, primary_label: 'About us', primary_url: '/about', secondary_enabled: true, secondary_label: 'Read the blog', secondary_url: '/blog', image_path: '', image_alt: '' };
    if (type === 'values') return { id: blockId, type, enabled: true, items: [
      { icon: 'heart', title: 'Health First', body: 'Lead with the care, quality or promise that matters most.' },
      { icon: 'home', title: 'Loving Home', body: 'Explain the thoughtful environment and experience you provide.' },
      { icon: 'award', title: 'Clear Standards', body: 'Highlight the standards, process or principles behind your work.' },
      { icon: 'clock', title: 'Experience', body: 'Share the knowledge and history that gives visitors confidence.' },
      { icon: 'heart', title: 'Lifetime Support', body: 'Finish with the long-term relationship or support you offer.' }
    ]};
    if (type === 'cards') return { id: blockId, type, enabled: true, eyebrow: 'FEATURED', heading: 'Meet what matters most.', view_label: 'View all', view_url: '/about', items: [1,2,3,4].map((n) => ({ title: `Card ${n}`, meta: 'Featured', url: '/about', image_path: '', image_alt: '' })) };
    if (type === 'gallery') return { id: blockId, type, enabled: true, eyebrow: 'GALLERY', heading: 'A closer look.', layout: 'grid', items: [1,2,3,4].map((n) => ({ caption: `Image ${n}`, image_path: '', image_alt: '' })) };
    if (type === 'testimonials') return { id: blockId, type, enabled: true, eyebrow: 'KIND WORDS', heading: 'What people say.', items: [
      { quote: 'Add a thoughtful testimonial from a client, customer or community member.', name: 'Customer name', role: 'Relationship or location' },
      { quote: 'Use more than one voice when social proof matters to the page.', name: 'Customer name', role: 'Relationship or location' }
    ]};
    if (type === 'faq') return { id: blockId, type, enabled: true, eyebrow: 'QUESTIONS', heading: 'Frequently asked questions', items: [
      { question: 'What should visitors know first?', answer: 'Write a clear, useful answer here.' },
      { question: 'What is another common question?', answer: 'Keep answers concise and easy to scan.' }
    ]};
    if (type === 'stats') return { id: blockId, type, enabled: true, eyebrow: 'AT A GLANCE', heading: 'Numbers that tell the story.', items: [
      { value: '10+', label: 'Years', body: 'A short explanation.' },
      { value: '100%', label: 'Committed', body: 'A short explanation.' },
      { value: '24/7', label: 'Support', body: 'A short explanation.' }
    ]};
    if (['custom','image_text','centered_intro'].includes(type)) {
      const layout = type === 'image_text' ? 'split-right' : (type === 'centered_intro' ? 'centered' : 'stacked');
      return { id: blockId, type: 'custom', enabled: true, eyebrow: type === 'centered_intro' ? 'INTRODUCTION' : 'CUSTOM SECTION', heading: type === 'image_text' ? 'Tell the story beside an image.' : 'Build this section your way.', body: 'Add the supporting copy for this section. You can change the layout, tone, image and calls to action.', layout, tone: 'plain', primary_enabled: false, primary_label: 'Learn more', primary_url: '/about', secondary_enabled: false, secondary_label: 'Contact us', secondary_url: '/contact', image_path: '', image_alt: '' };
    }
    if (type === 'contact') return { id: blockId, type, enabled: true, heading: 'Get in touch', intro: 'Send us a message and we will get back to you.', show_subject: true, require_subject: false, subject_prefix: 'Website contact', success_message: 'Thanks - your message has been received.', submit_label: 'Send message' };
    if (type === 'latest_posts') return { id: blockId, type, enabled: true, eyebrow: 'FROM THE JOURNAL', heading: 'Latest news', view_label: 'View all news', count: 3 };
    if (type === 'collection') {
      const model = contentModels.find((item) => item.is_public) || contentModels[0] || null;
      return { id: blockId, type, enabled: true, model_key: model?.model_key || '', presentation: model?.recommended_presentation || 'cards', eyebrow: 'EXPLORE', heading: model?.plural_name || 'Featured content', view_label: model?.has_archive ? `View all ${String(model.plural_name || '').toLowerCase()}` : '', view_url: '', count: 6, sort: 'newest', featured_only: false };
    }
    return { id: blockId, type: 'cta', enabled: true, eyebrow: 'WHY CHOOSE US', heading: 'A clear final thought that gives people a reason to continue.', button_label: 'Discover more', button_url: '/about' };
  };

  const input = (label, field, value = '', extra = '') => `<label>${label}<input data-field="${field}" value="${escapeHtml(value)}" ${extra}></label>`;
  const textarea = (label, field, value = '', rows = 3, extra = '') => `<label>${label}<textarea data-field="${field}" rows="${rows}" ${extra}>${escapeHtml(value)}</textarea></label>`;
  const selectField = (label, field, value, options) => `<label>${label}<select data-field="${field}">${options.map(([key, text]) => `<option value="${escapeHtml(key)}" ${String(value) === String(key) ? 'selected' : ''}>${escapeHtml(text)}</option>`).join('')}</select></label>`;

  const mediaPicker = (name, mediaId = 0, currentPath = '', itemField = false) => {
    const selected = mediaById(mediaId);
    const label = selected ? selected.label : (currentPath ? 'Current page image' : 'No image selected');
    const path = selected?.path || currentPath || '';
    const fieldAttr = itemField ? 'data-item-field="_media_id"' : 'data-field="_media_id"';
    return `<div class="builder-media-picker" data-media-picker-field>
      <input type="hidden" name="${escapeHtml(name)}" value="${Number(mediaId) || 0}" ${fieldAttr} data-media-input>
      <div class="builder-media-current">${path ? `<img src="${escapeHtml(path)}" alt="">` : '<span class="builder-media-placeholder">Image</span>'}<div><strong data-media-label>${escapeHtml(label)}</strong>${selected ? `<small>${Number(selected.width)}×${Number(selected.height)}</small>` : '<small>Choose from Media or upload below</small>'}</div></div>
      <button type="button" class="button secondary small" data-media-choose>Choose from Media</button>
    </div>`;
  };

  const blockActions = (block) => `<div class="builder-inspector-actions">
    <label class="builder-enabled-toggle"><input type="checkbox" data-field="enabled" ${block.enabled !== false ? 'checked' : ''}><span>${block.enabled !== false ? 'Visible' : 'Hidden'}</span></label>
    <button type="button" class="icon-button" data-block-action="collapse" title="Collapse editor">${collapsed.has(block.id) ? '▾' : '▴'}</button>
    <div class="builder-action-menu">
      <button type="button" class="icon-button" data-block-menu-toggle aria-label="More block actions">•••</button>
      <div class="builder-action-popover" data-block-menu hidden>
        <button type="button" data-block-action="insert-before">Insert above</button>
        <button type="button" data-block-action="insert-after">Insert below</button>
        <button type="button" data-block-action="move-up">Move up</button>
        <button type="button" data-block-action="move-down">Move down</button>
        <button type="button" data-block-action="duplicate">Duplicate</button>
        <button type="button" data-block-action="copy">Copy block</button>
        <button type="button" data-block-action="paste">Paste after</button>
        ${builderMode === 'page' && block.type !== 'pattern' ? '<button type="button" data-block-action="save-pattern">Save as pattern</button>' : ''}
        ${block.type === 'pattern' ? '<button type="button" data-block-action="detach-pattern">Detach to editable copy</button>' : ''}
        <button type="button" class="danger-text" data-block-action="remove">Delete block</button>
      </div>
    </div>
  </div>`;

  const heroEditor = (block) => `<div class="block-editor-fields">
    ${input('Eyebrow', 'eyebrow', block.eyebrow)}
    ${textarea('Heading', 'heading', block.heading, 2)}
    ${textarea('Introduction', 'intro', block.intro, 3)}
    <div class="two-fields">
      <div class="block-inset"><label class="check-row"><input type="checkbox" data-field="primary_enabled" ${block.primary_enabled ? 'checked' : ''}> Show primary button</label>${input('Label', 'primary_label', block.primary_label)}${input('URL', 'primary_url', block.primary_url)}</div>
      <div class="block-inset"><label class="check-row"><input type="checkbox" data-field="secondary_enabled" ${block.secondary_enabled ? 'checked' : ''}> Show secondary button</label>${input('Label', 'secondary_label', block.secondary_label)}${input('URL', 'secondary_url', block.secondary_url)}</div>
    </div>
    <div class="block-media-field">${mediaPicker(`page_block_${block.id}_media_id`, block._media_id || 0, block.image_path || '')}<label>Or upload a new image<input type="file" name="page_block_${block.id}_image" accept="image/jpeg,image/png,image/webp"></label>${block.image_path ? `<label class="check-row"><input type="checkbox" data-field="remove_image"> Remove current image</label>` : ''}${input('Image alt text', 'image_alt', block.image_alt)}</div>
  </div>`;

  const valuesEditor = (block) => `<div class="block-editor-fields"><p class="field-help">Each value can use a built-in line icon. Add up to six items.</p>
    <div class="builder-items">${(block.items || []).map((item, i) => `<div class="builder-item" data-item-index="${i}">
      <div class="builder-item-head"><strong>Value ${i + 1}</strong><button type="button" class="text-button danger-text" data-item-action="remove">Remove</button></div>
      <div class="icon-field-row"><span class="builder-icon-preview" data-icon-preview>${iconSvg(item.icon || 'heart')}</span><label>Icon<select data-item-field="icon">${Object.entries(iconNames).map(([key, label]) => `<option value="${key}" ${(item.icon || 'heart') === key ? 'selected' : ''}>${escapeHtml(label)}</option>`).join('')}</select></label></div>
      ${input('Title', 'item:title', item.title)}${textarea('Description', 'item:body', item.body, 3)}
    </div>`).join('')}</div><button type="button" class="button secondary small" data-item-action="add">+ Add value</button>
  </div>`;

  const cardsEditor = (block) => `<div class="block-editor-fields">
    <div class="two-fields">${input('Eyebrow', 'eyebrow', block.eyebrow)}${input('Heading', 'heading', block.heading)}</div>
    <div class="two-fields">${input('View-all label', 'view_label', block.view_label)}${input('View-all URL', 'view_url', block.view_url)}</div>
    <div class="builder-items builder-card-items">${(block.items || []).map((item, i) => `<div class="builder-item" data-item-index="${i}">
      <div class="builder-item-head"><strong>Card ${i + 1}</strong><button type="button" class="text-button danger-text" data-item-action="remove">Remove</button></div>
      ${mediaPicker(`page_block_${block.id}_card_${i + 1}_media_id`, item._media_id || 0, item.image_path || '', true)}
      <label>Or upload a new image<input type="file" name="page_block_${block.id}_card_${i + 1}_image" accept="image/jpeg,image/png,image/webp"></label>
      ${item.image_path ? '<label class="check-row"><input type="checkbox" data-item-field="remove_image"> Remove current image</label>' : ''}
      ${input('Title', 'item:title', item.title)}${input('Small label', 'item:meta', item.meta)}${input('Link', 'item:url', item.url)}${input('Image alt text', 'item:image_alt', item.image_alt)}
    </div>`).join('')}</div><button type="button" class="button secondary small" data-item-action="add">+ Add card</button>
  </div>`;

  const galleryEditor = (block) => `<div class="block-editor-fields">
    <div class="two-fields">${input('Eyebrow', 'eyebrow', block.eyebrow)}${input('Heading', 'heading', block.heading)}</div>
    ${selectField('Gallery layout', 'layout', block.layout || 'grid', [['grid','Even grid'],['masonry','Editorial masonry']])}
    <div class="builder-items builder-gallery-items">${(block.items || []).map((item, i) => `<div class="builder-item" data-item-index="${i}">
      <div class="builder-item-head"><strong>Image ${i + 1}</strong><button type="button" class="text-button danger-text" data-item-action="remove">Remove</button></div>
      ${mediaPicker(`page_block_${block.id}_gallery_${i + 1}_media_id`, item._media_id || 0, item.image_path || '', true)}
      <label>Or upload a new image<input type="file" name="page_block_${block.id}_gallery_${i + 1}_image" accept="image/jpeg,image/png,image/webp"></label>
      ${item.image_path ? '<label class="check-row"><input type="checkbox" data-item-field="remove_image"> Remove current image</label>' : ''}
      ${input('Caption', 'item:caption', item.caption)}${input('Image alt text', 'item:image_alt', item.image_alt)}
    </div>`).join('')}</div><button type="button" class="button secondary small" data-item-action="add">+ Add image</button>
  </div>`;

  const testimonialsEditor = (block) => `<div class="block-editor-fields">
    <div class="two-fields">${input('Eyebrow', 'eyebrow', block.eyebrow)}${input('Heading', 'heading', block.heading)}</div>
    <div class="builder-items">${(block.items || []).map((item, i) => `<div class="builder-item" data-item-index="${i}">
      <div class="builder-item-head"><strong>Testimonial ${i + 1}</strong><button type="button" class="text-button danger-text" data-item-action="remove">Remove</button></div>
      ${textarea('Quote', 'item:quote', item.quote, 4)}<div class="two-fields">${input('Name', 'item:name', item.name)}${input('Role / location', 'item:role', item.role)}</div>
    </div>`).join('')}</div><button type="button" class="button secondary small" data-item-action="add">+ Add testimonial</button>
  </div>`;

  const faqEditor = (block) => `<div class="block-editor-fields">
    <div class="two-fields">${input('Eyebrow', 'eyebrow', block.eyebrow)}${input('Heading', 'heading', block.heading)}</div>
    <div class="builder-items">${(block.items || []).map((item, i) => `<div class="builder-item" data-item-index="${i}">
      <div class="builder-item-head"><strong>Question ${i + 1}</strong><button type="button" class="text-button danger-text" data-item-action="remove">Remove</button></div>
      ${input('Question', 'item:question', item.question)}${textarea('Answer', 'item:answer', item.answer, 4)}
    </div>`).join('')}</div><button type="button" class="button secondary small" data-item-action="add">+ Add question</button>
  </div>`;

  const statsEditor = (block) => `<div class="block-editor-fields">
    <div class="two-fields">${input('Eyebrow', 'eyebrow', block.eyebrow)}${input('Heading', 'heading', block.heading)}</div>
    <div class="builder-items">${(block.items || []).map((item, i) => `<div class="builder-item" data-item-index="${i}">
      <div class="builder-item-head"><strong>Metric ${i + 1}</strong><button type="button" class="text-button danger-text" data-item-action="remove">Remove</button></div>
      <div class="two-fields">${input('Value', 'item:value', item.value)}${input('Label', 'item:label', item.label)}</div>${textarea('Description', 'item:body', item.body, 2)}
    </div>`).join('')}</div><button type="button" class="button secondary small" data-item-action="add">+ Add metric</button>
  </div>`;

  const customEditor = (block) => `<div class="block-editor-fields custom-section-editor">
    <div class="two-fields">${selectField('Layout', 'layout', block.layout || 'stacked', [['stacked','Stacked'],['centered','Centered'],['split-left','Image left'],['split-right','Image right']])}${selectField('Inner panel', 'tone', block.tone || 'plain', [['plain','Plain'],['soft','Soft panel'],['accent','Accent panel']])}</div>
    ${input('Eyebrow', 'eyebrow', block.eyebrow)}${textarea('Heading', 'heading', block.heading, 2)}${textarea('Body', 'body', block.body, 5)}
    <div class="two-fields">
      <div class="block-inset"><label class="check-row"><input type="checkbox" data-field="primary_enabled" ${block.primary_enabled ? 'checked' : ''}> Show primary button</label>${input('Label', 'primary_label', block.primary_label)}${input('URL', 'primary_url', block.primary_url)}</div>
      <div class="block-inset"><label class="check-row"><input type="checkbox" data-field="secondary_enabled" ${block.secondary_enabled ? 'checked' : ''}> Show secondary button</label>${input('Label', 'secondary_label', block.secondary_label)}${input('URL', 'secondary_url', block.secondary_url)}</div>
    </div>
    <div class="block-media-field">${mediaPicker(`page_block_${block.id}_media_id`, block._media_id || 0, block.image_path || '')}<label>Or upload a new image<input type="file" name="page_block_${block.id}_image" accept="image/jpeg,image/png,image/webp"></label>${block.image_path ? `<label class="check-row"><input type="checkbox" data-field="remove_image"> Remove current image</label>` : ''}${input('Image alt text', 'image_alt', block.image_alt)}</div>
    <p class="field-help">This is Talvoro's safe manual block. Compose the section here, then use “Save as pattern” if you want to reuse it across pages.</p>
  </div>`;

  const contactEditor = (block) => `<div class="block-editor-fields contact-block-editor">
    ${input('Heading', 'heading', block.heading, 'maxlength="180"')}
    ${textarea('Introductory text', 'intro', block.intro, 3, 'maxlength="1200"')}
    <div class="block-inset">
      <label class="check-row"><input type="checkbox" data-field="show_subject" ${block.show_subject !== false ? 'checked' : ''}> Show Subject field</label>
      <label class="check-row"><input type="checkbox" data-field="require_subject" ${block.require_subject ? 'checked' : ''}> Require Subject when shown</label>
      <p class="field-help">Name, Email and Message are always required. Subject is shown by default but remains optional unless you require it.</p>
    </div>
    <div class="two-fields">${input('Subject prefix', 'subject_prefix', block.subject_prefix, 'maxlength="80"')}${input('Submit button text', 'submit_label', block.submit_label, 'maxlength="80"')}</div>
    ${textarea('Success message', 'success_message', block.success_message, 2, 'maxlength="300"')}
    <div class="notice neutral compact"><strong>Delivery stays protected.</strong><p>The recipient, storage policy and retention are configured in Email settings. Page editors cannot redirect visitor messages to arbitrary addresses.</p></div>
  </div>`;
  const latestEditor = (block) => `<div class="block-editor-fields"><div class="two-fields">${input('Eyebrow', 'eyebrow', block.eyebrow)}${input('Heading', 'heading', block.heading)}</div><div class="two-fields">${input('View-all label', 'view_label', block.view_label)}${input('Posts to show', 'count', block.count || 3, 'type="number" min="1" max="6"')}</div><p class="field-help">Published posts, featured images and primary category pills are filled dynamically on the live site.</p></div>`;
  const collectionEditor = (block) => {
    const selected = modelByKey(block.model_key);
    const missingOption = block.model_key && !selected ? `<option value="${escapeHtml(block.model_key)}" selected>Missing model · ${escapeHtml(block.model_key)}</option>` : '';
    const modelOptions = missingOption + (contentModels.length ? contentModels.map((model) => `<option value="${escapeHtml(model.model_key)}" ${String(block.model_key) === String(model.model_key) ? 'selected' : ''}>${escapeHtml(model.plural_name)}${model.is_public ? '' : ' · not public'}</option>`).join('') : '<option value="">No content models available</option>');
    const presentationOptions = [['cards','Cards'],['people','People'],['testimonials','Testimonials'],['pricing','Pricing'],['events','Events'],['resources','Resources'],['faq','FAQ'],['logos','Partners / trust']];
    const warning = selected && !selected.is_public ? '<div class="notice warning compact"><strong>This model is not public.</strong> Enable Public content in Content Models before this block can display entries on the live site.</div>' : '';
    const archiveHint = selected?.has_archive ? `Leave View-all URL empty to use /${escapeHtml(selected.slug)} automatically.` : 'Add a View-all URL only when this section should link somewhere else.';
    return `<div class="block-editor-fields collection-block-editor">${warning}<div class="two-fields"><label>Content model<select data-field="model_key">${modelOptions}</select></label>${selectField('Presentation', 'presentation', block.presentation || selected?.recommended_presentation || 'cards', presentationOptions)}</div><div class="two-fields">${input('Eyebrow', 'eyebrow', block.eyebrow)}${input('Heading', 'heading', block.heading)}</div><div class="two-fields">${input('Items to show', 'count', block.count || 6, 'type="number" min="1" max="12"')}${selectField('Order', 'sort', block.sort || 'newest', [['newest','Newest first'],['oldest','Oldest first'],['title_asc','Title A–Z'],['title_desc','Title Z–A']])}</div><label class="check-row"><input type="checkbox" data-field="featured_only" ${block.featured_only ? 'checked' : ''}> Only show entries marked Featured / Highlighted</label><div class="two-fields">${input('View-all label', 'view_label', block.view_label)}${input('View-all URL', 'view_url', block.view_url)}</div><p class="field-help">${archiveHint} The live page reads published structured content directly; editing an entry updates every collection that uses it.</p></div>`;
  };
  const ctaEditor = (block) => `<div class="block-editor-fields">${input('Eyebrow', 'eyebrow', block.eyebrow)}${textarea('Heading', 'heading', block.heading, 2)}<div class="two-fields">${input('Button label', 'button_label', block.button_label)}${input('Button URL', 'button_url', block.button_url)}</div></div>`;
  const patternEditor = (block) => {
    const pattern = patternById(block.pattern_id);
    if (!pattern) return '<div class="block-editor-fields"><div class="notice error">This synced pattern no longer exists.</div></div>';
    return `<div class="block-editor-fields synced-pattern-inspector"><span class="status-pill synced">Synced pattern</span><h4>${escapeHtml(pattern.name)}</h4><p>Content comes from one shared source. Editing the pattern updates every page that uses it.</p><div class="split-row"><span>Blocks in pattern</span><strong>${pattern.blocks?.length || 0}</strong></div><div class="split-row"><span>Used by pages</span><strong>${Number(pattern.usage_count) || 0}</strong></div><div class="pattern-instance-actions"><a class="button secondary small" href="${escapeHtml((config.patternsUrl || '') + '/' + Number(pattern.id) + '/edit')}">Edit source pattern</a><button type="button" class="button secondary small" data-block-action="detach-pattern">Detach to editable copy</button></div></div>`;
  };
  const editorFor = (block) => ({ hero: heroEditor, values: valuesEditor, cards: cardsEditor, gallery: galleryEditor, testimonials: testimonialsEditor, faq: faqEditor, stats: statsEditor, custom: customEditor, latest_posts: latestEditor, collection: collectionEditor, contact: contactEditor, cta: ctaEditor, pattern: patternEditor }[block.type] || (() => '<p>Unsupported block.</p>'))(block);

  const variantOptions = (type) => ({
    hero: [['default','Split'],['centered','Centered'],['minimal','Minimal']],
    cards: [['default','Standard'],['editorial','Editorial'],['compact','Compact'],['audiences','Audience stories']],
    testimonials: [['default','Cards'],['quote','Editorial quote']],
    stats: [['default','Cards'],['inline','Inline']],
    cta: [['default','Band'],['minimal','Minimal']],
    collection: [['default','Standard'],['compact','Compact']],
    custom: [['default','Default'],['product-ui','Product UI'],['ownership','Ownership diagram'],['capabilities','Capabilities'],['install','Install paths'],['theme-showcase','Theme showcase']]
  }[type] || [['default','Default']]);

  const styleEditor = (block) => block.type === 'pattern' ? '' : `<details class="builder-design-panel" ${block._design_open ? 'open' : ''}>
    <summary><span><strong>Design</strong><small>Semantic style options supported by the active theme.</small></span><span aria-hidden="true">⌄</span></summary>
    <div class="builder-design-fields two-fields">
      ${selectField('Background', 'style_tone', block.style_tone || 'default', [['default','Default'],['soft','Soft'],['accent','Accent'],['dark','Dark']])}
      ${selectField('Content width', 'style_width', block.style_width || 'normal', [['normal','Normal'],['wide','Wide'],['full','Full']])}
      ${selectField('Spacing', 'style_spacing', block.style_spacing || 'normal', [['compact','Compact'],['normal','Normal'],['spacious','Spacious']])}
      ${selectField('Alignment', 'style_alignment', block.style_alignment || 'left', [['left','Left'],['center','Center']])}
      ${variantOptions(block.type).length > 1 ? selectField('Variant', 'style_variant', block.style_variant || 'default', variantOptions(block.type)) : ''}
    </div>
    <p class="field-help">These choices store intent, not raw CSS. Changing global Styles or the active theme updates the section safely.</p>
  </details>`;

  const captureFiles = () => {
    list.querySelectorAll('input[type="file"][name]').forEach((inputEl) => {
      if (inputEl.files?.length) fileState.set(inputEl.name, inputEl.files[0]);
    });
  };

  const restoreFiles = () => {
    if (typeof DataTransfer === 'undefined') return;
    list.querySelectorAll('input[type="file"][name]').forEach((inputEl) => {
      const file = fileState.get(inputEl.name);
      if (!file) return;
      try { const dt = new DataTransfer(); dt.items.add(file); inputEl.files = dt.files; } catch { /* browser may reject programmatic file restoration */ }
    });
  };

  const copyBlockFiles = (fromId, toId) => {
    const fromPrefix = `page_block_${fromId}_`;
    const toPrefix = `page_block_${toId}_`;
    fileState.forEach((file, name) => {
      if (name.startsWith(fromPrefix)) fileState.set(toPrefix + name.slice(fromPrefix.length), file);
    });
  };

  const shiftIndexedFilesAfterRemoval = (blockId, group, removedIndex) => {
    const prefix = `page_block_${blockId}_${group}_`;
    const updates = [];
    fileState.forEach((file, name) => {
      if (!name.startsWith(prefix) || !name.endsWith('_image')) return;
      const match = name.slice(prefix.length).match(/^(\d+)_image$/);
      if (!match) return;
      const index = Number(match[1]) - 1;
      if (index === removedIndex) updates.push([name, null, file]);
      else if (index > removedIndex) updates.push([name, `${prefix}${index}_image`, file]);
    });
    updates.forEach(([oldName, newName, file]) => {
      fileState.delete(oldName);
      const oldUrl = objectUrls.get(oldName); if (oldUrl) { URL.revokeObjectURL(oldUrl); objectUrls.delete(oldName); }
      if (newName) fileState.set(newName, file);
    });
  };

  const readCard = (card, existing) => {
    const block = structuredClone(existing);
    card.querySelectorAll('[data-field]').forEach((el) => {
      const field = el.dataset.field;
      if (!field || field.startsWith('item:')) return;
      block[field] = el.type === 'checkbox' ? el.checked : (el.type === 'number' ? Number(el.value) : el.value);
    });
    if (['values', 'cards', 'gallery', 'testimonials', 'faq', 'stats'].includes(block.type)) {
      const items = [];
      card.querySelectorAll('[data-item-index]').forEach((itemEl) => {
        const old = structuredClone((existing.items || [])[Number(itemEl.dataset.itemIndex)] || {});
        itemEl.querySelectorAll('[data-item-field], [data-field^="item:"]').forEach((el) => {
          const field = el.dataset.itemField || el.dataset.field?.slice(5);
          if (!field) return;
          old[field] = el.type === 'checkbox' ? el.checked : (el.type === 'number' ? Number(el.value) : el.value);
        });
        items.push(old);
      });
      block.items = items;
    }
    return block;
  };

  const sync = () => {
    captureFiles();
    const byId = new Map(blocks.map((block) => [String(block.id), block]));
    list.querySelectorAll('[data-block-id]').forEach((card) => {
      const block = byId.get(String(card.dataset.blockId));
      if (!block) return;
      const next = readCard(card, block);
      const index = blocks.findIndex((item) => item.id === block.id);
      if (index >= 0) blocks[index] = next;
    });
    hidden.value = JSON.stringify(blocks);
    schedulePreview();
  };

  const selectBlock = (blockId, scroll = false) => {
    selectedId = blockId || blocks[0]?.id || '';
    outline.querySelectorAll('[data-outline-id]').forEach((item) => item.classList.toggle('is-selected', item.dataset.outlineId === selectedId));
    list.querySelectorAll('[data-block-id]').forEach((card) => { card.hidden = card.dataset.blockId !== selectedId; });
    if (scroll) list.querySelector(`[data-block-id="${CSS.escape(selectedId)}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    markPreviewSelection();
  };

  const renderOutline = () => {
    outline.innerHTML = blocks.map((block, index) => `<button type="button" class="builder-outline-item${block.id === selectedId ? ' is-selected' : ''}${block.enabled === false ? ' is-disabled' : ''}" data-outline-id="${block.id}" draggable="true">
      <span class="builder-drag-handle" aria-hidden="true">⋮⋮</span><span class="builder-outline-number">${String(index + 1).padStart(2, '0')}</span><span class="builder-outline-copy"><strong>${escapeHtml(blockName(block))}</strong><small>${block.type === 'pattern' ? 'Synced pattern' : (block.enabled === false ? 'Hidden on site' : 'Page section')}</small></span><span class="builder-outline-eye" aria-hidden="true">${block.enabled === false ? '○' : '●'}</span>
    </button>`).join('');
    if (countNode) countNode.textContent = `${blocks.length} block${blocks.length === 1 ? '' : 's'}`;
  };

  const renderEditors = () => {
    list.innerHTML = blocks.map((block, index) => `<section class="page-block-editor${collapsed.has(block.id) ? ' is-collapsed' : ''}${block.enabled === false ? ' is-disabled' : ''}" data-block-id="${block.id}" data-block-index="${index}" ${block.id === selectedId ? '' : 'hidden'}>
      <header class="page-block-editor-head"><div><span class="block-number">${String(index + 1).padStart(2, '0')}</span><div><p class="eyebrow">${block.type === 'pattern' ? 'Pattern' : 'Section'}</p><h3>${escapeHtml(blockName(block))}</h3></div></div>${blockActions(block)}</header>
      <div class="builder-collapsible-content">${editorFor(block)}${styleEditor(block)}</div>
    </section>`).join('');
    restoreFiles();
  };

  const renderAll = () => {
    if (!selectedId || !blocks.some((block) => block.id === selectedId)) selectedId = blocks[0]?.id || '';
    renderOutline();
    renderEditors();
    empty.hidden = blocks.length > 0;
    hidden.value = JSON.stringify(blocks);
    schedulePreview();
  };

  const insertBlocks = (newBlocks, at = null) => {
    sync();
    const items = newBlocks.map((block) => ({ style_tone: 'default', style_width: 'normal', style_spacing: 'normal', style_alignment: 'left', style_variant: 'default', enabled: block.enabled !== false, ...block }));
    const index = at === null ? blocks.length : Math.max(0, Math.min(blocks.length, at));
    blocks.splice(index, 0, ...items);
    selectedId = items[0]?.id || selectedId;
    renderAll();
    selectBlock(selectedId, true);
    announceChange();
  };

  const toast = document.createElement('div');
  toast.className = 'builder-toast';
  toast.hidden = true;
  document.body.appendChild(toast);
  let toastTimer = 0;
  const showToast = (message, actionLabel = '', action = null) => {
    clearTimeout(toastTimer);
    toast.innerHTML = `<span>${escapeHtml(message)}</span>${actionLabel ? `<button type="button">${escapeHtml(actionLabel)}</button>` : ''}`;
    toast.hidden = false;
    const button = toast.querySelector('button');
    if (button && action) button.addEventListener('click', () => { action(); toast.hidden = true; }, { once: true });
    toastTimer = window.setTimeout(() => { toast.hidden = true; }, 6000);
  };

  const palette = document.createElement('div');
  palette.className = 'block-palette-backdrop';
  palette.hidden = true;
  document.body.appendChild(palette);
  const paletteContent = () => {
    const builtin = [
      ['hero','Hero banner','Large heading, buttons and image'],
      ['image_text','Image + text','Balanced split layout for stories, services or introductions'],
      ['centered_intro','Centered introduction','Simple editorial heading, copy and optional actions'],
      ['values','Trust / value strip','Circular icons and compact value points'],
      ['cards','Featured cards','Image cards for dogs, services or highlights'],
      ['gallery','Image gallery','Reusable responsive image grid'],
      ['testimonials','Testimonials','Quotes and social proof'],
      ['faq','FAQ','Accessible expandable questions and answers'],
      ['stats','Statistics','Key numbers and compact explanations'],
      ['latest_posts','Latest blog posts','Dynamic posts with featured images and categories'],
      ['collection','Connected content','Published Content Model entries that stay in sync automatically'],
      ['contact','Contact form','Private, self-hosted visitor messages using Talvoro email'],
      ['cta','Call to action','Closing message and action button'],
      ['custom','Custom section','Build a section manually, then save it as a Pattern']
    ];
    const patternCards = builderMode === 'page' ? patterns.map((pattern) => `<button type="button" class="palette-choice" data-pattern-id="${Number(pattern.id)}" data-search="${escapeHtml((pattern.name + ' pattern ' + pattern.mode).toLowerCase())}"><span class="palette-choice-icon">◇</span><span><strong>${escapeHtml(pattern.name)}</strong><small>${pattern.mode === 'synced' ? 'Synced · one shared source' : 'Pattern · insert an editable copy'}</small></span><span class="status-pill ${escapeHtml(pattern.mode)}">${pattern.mode === 'synced' ? 'Synced' : 'Regular'}</span></button>`).join('') : '';
    return `<section class="block-palette" role="dialog" aria-modal="true" aria-label="Add page block">
      <div class="block-palette-head"><div><p class="eyebrow">Page builder</p><h2>Add a section</h2><p>Choose a structured block or reusable pattern.</p></div><button type="button" class="icon-button" data-close-palette aria-label="Close">×</button></div>
      <label class="palette-search"><span>Search</span><input type="search" data-palette-search placeholder="Hero, gallery, FAQ, custom, pattern…" autocomplete="off"></label>
      <div class="block-palette-list" data-palette-list>${builtin.map(([type,name,desc]) => `<button type="button" class="palette-choice" data-palette-type="${type}" data-search="${(name + ' ' + desc + ' ' + type).toLowerCase()}"><span class="palette-choice-icon">+</span><span><strong>${name}</strong><small>${desc}</small></span></button>`).join('')}${patternCards ? `<div class="palette-section-label">Patterns</div>${patternCards}` : ''}</div>
      ${builderMode === 'page' ? `<div class="block-palette-foot"><a href="${escapeHtml(config.patternsUrl || '#')}">Manage Patterns →</a></div>` : ''}
    </section>`;
  };

  const openPalette = (insertIndex = null, initialSearch = '') => {
    paletteInsertIndex = insertIndex;
    palette.innerHTML = paletteContent();
    palette.hidden = false;
    document.body.classList.add('modal-open');
    const search = palette.querySelector('[data-palette-search]');
    search.value = initialSearch;
    search.focus();
    if (initialSearch) search.dispatchEvent(new Event('input'));
  };
  const closePalette = () => { palette.hidden = true; palette.innerHTML = ''; document.body.classList.remove('modal-open'); paletteInsertIndex = null; };
  document.querySelectorAll('[data-add-block], [data-action="blocks"]').forEach((button) => button.addEventListener('click', (event) => { event.preventDefault(); openPalette(blocks.length); }));
  palette.addEventListener('click', (event) => {
    if (event.target === palette || event.target.closest('[data-close-palette]')) return closePalette();
    const choice = event.target.closest('[data-palette-type]');
    if (choice) { insertBlocks([defaultBlock(choice.dataset.paletteType)], paletteInsertIndex); closePalette(); return; }
    const patternChoice = event.target.closest('[data-pattern-id]');
    if (!patternChoice) return;
    const pattern = patternById(Number(patternChoice.dataset.patternId));
    if (!pattern) return;
    if (pattern.mode === 'synced') insertBlocks([{ id: id(), type: 'pattern', enabled: true, pattern_id: Number(pattern.id) }], paletteInsertIndex);
    else insertBlocks((pattern.blocks || []).map(cloneBlock), paletteInsertIndex);
    closePalette();
  });
  palette.addEventListener('input', (event) => {
    const search = event.target.closest('[data-palette-search]');
    if (!search) return;
    const query = search.value.trim().toLowerCase();
    palette.querySelectorAll('[data-search]').forEach((item) => { item.hidden = query !== '' && !item.dataset.search.includes(query); });
  });
  palette.addEventListener('keydown', (event) => { if (event.key === 'Escape') closePalette(); });

  const mediaModal = document.createElement('div');
  mediaModal.className = 'media-picker-backdrop';
  mediaModal.hidden = true;
  document.body.appendChild(mediaModal);
  let activeMediaField = null;
  const openMediaPicker = (field) => {
    activeMediaField = field;
    mediaModal.innerHTML = `<section class="media-picker-dialog" role="dialog" aria-modal="true" aria-label="Choose media"><div class="block-palette-head"><div><p class="eyebrow">Media Library</p><h2>Choose an image</h2></div><button type="button" class="icon-button" data-media-close>×</button></div><label class="palette-search"><span>Search</span><input type="search" data-media-search placeholder="Search filename…"></label><div class="media-picker-grid" data-media-picker-grid>${mediaAssets.length ? mediaAssets.map((asset) => `<button type="button" class="media-picker-tile" data-media-id="${Number(asset.id)}" data-search="${escapeHtml(String(asset.label || '').toLowerCase())}"><img src="${escapeHtml(asset.path)}" alt=""><span><strong>${escapeHtml(asset.label)}</strong><small>${Number(asset.width)}×${Number(asset.height)}</small></span></button>`).join('') : '<div class="empty-state"><strong>No media yet</strong><p>Upload images in the Media Library first.</p></div>'}</div><div class="media-picker-foot"><a href="${escapeHtml((config.patternsUrl || '').replace(/\/patterns$/, '/media') || '#')}">Open Media Library →</a><button type="button" class="button secondary" data-media-clear>Clear selection</button></div></section>`;
    mediaModal.hidden = false;
    document.body.classList.add('modal-open');
    mediaModal.querySelector('[data-media-search]')?.focus();
  };
  const closeMediaPicker = () => { mediaModal.hidden = true; mediaModal.innerHTML = ''; activeMediaField = null; document.body.classList.remove('modal-open'); };
  mediaModal.addEventListener('click', (event) => {
    if (event.target === mediaModal || event.target.closest('[data-media-close]')) return closeMediaPicker();
    if (event.target.closest('[data-media-clear]')) {
      if (activeMediaField) { activeMediaField.querySelector('[data-media-input]').value = '0'; activeMediaField.querySelector('[data-media-label]').textContent = 'No image selected'; activeMediaField.querySelector('.builder-media-current img')?.remove(); activeMediaField.querySelector('[data-media-input]').dispatchEvent(new Event('input', { bubbles: true })); }
      return closeMediaPicker();
    }
    const tile = event.target.closest('[data-media-id]');
    if (!tile || !activeMediaField) return;
    const asset = mediaById(Number(tile.dataset.mediaId));
    if (!asset) return;
    const inputEl = activeMediaField.querySelector('[data-media-input]');
    inputEl.value = String(asset.id);
    const uploadName = String(inputEl.name || '').replace(/_media_id$/, '_image');
    const uploadInput = list.querySelector(`input[type="file"][name="${CSS.escape(uploadName)}"]`);
    if (uploadInput) { uploadInput.value = ''; fileState.delete(uploadName); }
    const itemScope = activeMediaField.closest('[data-item-index]');
    const removeToggle = itemScope?.querySelector('input[data-item-field="remove_image"]') || activeMediaField.closest('[data-block-id]')?.querySelector('input[data-field="remove_image"]');
    if (removeToggle) removeToggle.checked = false;
    activeMediaField.querySelector('[data-media-label]').textContent = asset.label;
    let img = activeMediaField.querySelector('.builder-media-current img');
    if (!img) { img = document.createElement('img'); activeMediaField.querySelector('.builder-media-placeholder')?.replaceWith(img); }
    img.src = asset.path; img.alt = '';
    inputEl.dispatchEvent(new Event('input', { bubbles: true }));
    closeMediaPicker();
  });
  mediaModal.addEventListener('input', (event) => {
    const search = event.target.closest('[data-media-search]'); if (!search) return;
    const query = search.value.trim().toLowerCase();
    mediaModal.querySelectorAll('[data-search]').forEach((tile) => { tile.hidden = query !== '' && !tile.dataset.search.includes(query); });
  });

  const patternModal = document.createElement('div');
  patternModal.className = 'block-palette-backdrop'; patternModal.hidden = true; document.body.appendChild(patternModal);
  const openSavePattern = (block) => {
    patternModal.innerHTML = `<section class="block-palette compact-pattern-dialog" role="dialog" aria-modal="true"><div class="block-palette-head"><div><p class="eyebrow">Reusable design</p><h2>Save as pattern</h2><p>Turn ${escapeHtml(blockName(block))} into a reusable section.</p></div><button type="button" class="icon-button" data-pattern-close>×</button></div><form data-save-pattern-form><label>Pattern name<input name="name" required maxlength="160" placeholder="Homepage value strip"></label><fieldset class="pattern-mode-options"><legend>Behavior</legend><label><input type="radio" name="mode" value="regular" checked><span><strong>Regular</strong><small>Insert an independent editable copy.</small></span></label><label><input type="radio" name="mode" value="synced"><span><strong>Synced</strong><small>One source updates every page instance.</small></span></label></fieldset><div class="dialog-actions"><button type="button" class="button secondary" data-pattern-close>Cancel</button><button class="button" type="submit">Save pattern</button></div><div class="form-inline-error" data-pattern-error hidden></div></form></section>`;
    patternModal.hidden = false; document.body.classList.add('modal-open'); patternModal.querySelector('input[name="name"]')?.focus();
    patternModal.querySelector('[data-save-pattern-form]').addEventListener('submit', async (event) => {
      event.preventDefault(); sync();
      const currentBlock = blocks.find((item) => item.id === block.id) || block;
      const fd = new FormData(event.currentTarget);
      fd.append('_csrf', config.csrf || '');
      fd.append('page_blocks_json', JSON.stringify([currentBlock]));
      fileState.forEach((file, name) => { if (file instanceof File) fd.append(name, file, file.name); });
      const error = patternModal.querySelector('[data-pattern-error]');
      try {
        const response = await fetch(config.patternCreateUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await response.json();
        if (!response.ok || !data.ok) { error.textContent = (data.errors || ['Could not save pattern.']).join(' '); error.hidden = false; return; }
        patterns.push(data.pattern);
        const mode = String(fd.get('mode'));
        if (mode === 'synced') {
          const index = blocks.findIndex((item) => item.id === block.id);
          if (index >= 0) { blocks[index] = { id: id(), type: 'pattern', enabled: true, pattern_id: Number(data.pattern.id) }; selectedId = blocks[index].id; renderAll(); announceChange(); }
        }
        patternModal.hidden = true; patternModal.innerHTML = ''; document.body.classList.remove('modal-open');
        showToast(mode === 'synced' ? 'Synced pattern saved and connected.' : 'Regular pattern saved.');
      } catch { error.textContent = 'Could not reach Talvoro. Try again.'; error.hidden = false; }
    });
  };
  patternModal.addEventListener('click', (event) => { if (event.target === patternModal || event.target.closest('[data-pattern-close]')) { patternModal.hidden = true; patternModal.innerHTML = ''; document.body.classList.remove('modal-open'); } });

  const performAction = (action, blockIndex, eventTarget) => {
    sync();
    const block = blocks[blockIndex]; if (!block) return;
    if (action === 'collapse') { collapsed.has(block.id) ? collapsed.delete(block.id) : collapsed.add(block.id); renderAll(); return; }
    if (action === 'insert-before') return openPalette(blockIndex);
    if (action === 'insert-after') return openPalette(blockIndex + 1);
    if (action === 'move-up' && blockIndex > 0) { blocks.splice(blockIndex - 1, 0, blocks.splice(blockIndex, 1)[0]); selectedId = block.id; renderAll(); announceChange(); showToast('Section moved up.'); return; }
    if (action === 'move-down' && blockIndex < blocks.length - 1) { blocks.splice(blockIndex + 1, 0, blocks.splice(blockIndex, 1)[0]); selectedId = block.id; renderAll(); announceChange(); showToast('Section moved down.'); return; }
    if (action === 'duplicate') { const copy = cloneBlock(block); copyBlockFiles(block.id, copy.id); blocks.splice(blockIndex + 1, 0, copy); selectedId = copy.id; renderAll(); announceChange(); showToast('Block duplicated.'); return; }
    if (action === 'copy') { sessionStorage.setItem('talvoro.builder.clipboard', JSON.stringify(block)); showToast('Block copied.'); return; }
    if (action === 'paste') {
      try { const copied = JSON.parse(sessionStorage.getItem('talvoro.builder.clipboard') || 'null'); if (copied?.type) { const copy = cloneBlock(copied); if (copy.type !== 'pattern') copy._detach_assets = true; blocks.splice(blockIndex + 1, 0, copy); selectedId = copy.id; renderAll(); announceChange(); showToast('Block pasted.'); } else showToast('Copy a block first.'); } catch { showToast('Copied block could not be read.'); }
      return;
    }
    if (action === 'save-pattern' && builderMode === 'page') return openSavePattern(block);
    if (action === 'detach-pattern' && block.type === 'pattern') {
      const pattern = patternById(block.pattern_id); if (!pattern) return showToast('Pattern source is missing.');
      const copies = (pattern.blocks || []).map(cloneBlock); blocks.splice(blockIndex, 1, ...copies); selectedId = copies[0]?.id || blocks[blockIndex]?.id || ''; renderAll(); announceChange(); showToast('Pattern detached. These blocks are now independent.'); return;
    }
    if (action === 'remove') {
      deletedSnapshot = { block: structuredClone(block), index: blockIndex };
      blocks.splice(blockIndex, 1); selectedId = blocks[Math.min(blockIndex, blocks.length - 1)]?.id || ''; renderAll(); announceChange();
      showToast(`${blockName(block)} deleted.`, 'Undo', () => { if (!deletedSnapshot) return; blocks.splice(deletedSnapshot.index, 0, deletedSnapshot.block); selectedId = deletedSnapshot.block.id; deletedSnapshot = null; renderAll(); announceChange(); });
      return;
    }
  };

  list.addEventListener('click', (event) => {
    const mediaButton = event.target.closest('[data-media-choose]');
    if (mediaButton) { event.preventDefault(); return openMediaPicker(mediaButton.closest('[data-media-picker-field]')); }
    const menuToggle = event.target.closest('[data-block-menu-toggle]');
    if (menuToggle) { event.preventDefault(); const menu = menuToggle.parentElement.querySelector('[data-block-menu]'); menu.hidden = !menu.hidden; return; }
    const blockCard = event.target.closest('[data-block-id]'); if (!blockCard) return;
    const blockIndex = blocks.findIndex((block) => block.id === blockCard.dataset.blockId);
    const action = event.target.closest('[data-block-action]')?.dataset.blockAction;
    if (action) { event.preventDefault(); return performAction(action, blockIndex, event.target); }
    const itemAction = event.target.closest('[data-item-action]')?.dataset.itemAction;
    if (!itemAction) return;
    event.preventDefault(); sync();
    const block = blocks[blockIndex]; if (!['values','cards','gallery','testimonials','faq','stats'].includes(block.type)) return;
    const maxItems = block.type === 'faq' ? 8 : 6;
    if (itemAction === 'add' && block.items.length < maxItems) {
      const newItem = block.type === 'values' ? { icon: 'heart', title: 'New value', body: 'Describe this value.' }
        : block.type === 'cards' ? { title: 'New card', meta: 'Featured', url: '/about', image_path: '', image_alt: '' }
        : block.type === 'gallery' ? { caption: 'New image', image_path: '', image_alt: '' }
        : block.type === 'testimonials' ? { quote: 'Add a testimonial.', name: 'Customer name', role: '' }
        : block.type === 'faq' ? { question: 'New question', answer: 'Write the answer here.' }
        : { value: '100%', label: 'Metric', body: 'Explain what this number means.' };
      block.items.push(newItem);
    }
    if (itemAction === 'remove') {
      const item = event.target.closest('[data-item-index]');
      if (item && block.items.length > 1) {
        const removedIndex = Number(item.dataset.itemIndex);
        block.items.splice(removedIndex, 1);
        if (block.type === 'cards') shiftIndexedFilesAfterRemoval(block.id, 'card', removedIndex);
        if (block.type === 'gallery') shiftIndexedFilesAfterRemoval(block.id, 'gallery', removedIndex);
      }
    }
    renderAll(); announceChange();
  });

  list.addEventListener('input', (event) => {
    const select = event.target.closest('select[data-item-field="icon"]');
    if (select) select.closest('.builder-item')?.querySelector('[data-icon-preview]')?.replaceChildren();
    if (select) select.closest('.builder-item').querySelector('[data-icon-preview]').innerHTML = iconSvg(select.value);
    const enabled = event.target.closest('input[data-field="enabled"]');
    if (enabled) { const block = blocks.find((item) => item.id === enabled.closest('[data-block-id]')?.dataset.blockId); if (block) block.enabled = enabled.checked; }
    sync(); renderOutline();
  });
  list.addEventListener('change', (event) => {
    if (event.target.matches('select[data-field="model_key"]')) {
      sync();
      const card = event.target.closest('[data-block-id]');
      const block = blocks.find((item) => item.id === card?.dataset.blockId);
      const model = modelByKey(event.target.value);
      if (block && model) {
        block.model_key = model.model_key;
        block.presentation = model.recommended_presentation || 'cards';
        if (!block.heading) block.heading = model.plural_name || '';
        if (!block.view_label && model.has_archive) block.view_label = `View all ${String(model.plural_name || '').toLowerCase()}`;
        renderAll(); announceChange();
      }
      return;
    }
    if (event.target.matches('input[type="file"][name]') && event.target.files?.length) {
      fileState.set(event.target.name, event.target.files[0]);
      const mediaName = String(event.target.name).replace(/_image$/, '_media_id');
      const mediaInput = list.querySelector(`input[data-media-input][name="${CSS.escape(mediaName)}"]`);
      if (mediaInput) {
        mediaInput.value = '0';
        const field = mediaInput.closest('[data-media-picker-field]');
        const label = field?.querySelector('[data-media-label]'); if (label) label.textContent = 'Upload selected';
      }
      const itemScope = event.target.closest('[data-item-index]');
      const removeToggle = itemScope?.querySelector('input[data-item-field="remove_image"]') || event.target.closest('[data-block-id]')?.querySelector('input[data-field="remove_image"]');
      if (removeToggle) removeToggle.checked = false;
    }
    sync();
  });

  outline.addEventListener('click', (event) => { const item = event.target.closest('[data-outline-id]'); if (item) selectBlock(item.dataset.outlineId); });
  outline.addEventListener('dragstart', (event) => { const item = event.target.closest('[data-outline-id]'); if (!item) return; sync(); draggedId = item.dataset.outlineId; item.classList.add('is-dragging'); event.dataTransfer.effectAllowed = 'move'; });
  outline.addEventListener('dragend', () => { draggedId = ''; outline.querySelectorAll('.is-dragging').forEach((el) => el.classList.remove('is-dragging')); });
  outline.addEventListener('dragover', (event) => { if (!draggedId) return; event.preventDefault(); event.dataTransfer.dropEffect = 'move'; event.target.closest('[data-outline-id]')?.classList.add('is-drop-target'); });
  outline.addEventListener('dragleave', (event) => { event.target.closest('[data-outline-id]')?.classList.remove('is-drop-target'); });
  outline.addEventListener('drop', (event) => {
    if (!draggedId) return; event.preventDefault();
    const target = event.target.closest('[data-outline-id]'); outline.querySelectorAll('.is-drop-target').forEach((el) => el.classList.remove('is-drop-target'));
    if (!target || target.dataset.outlineId === draggedId) return;
    const from = blocks.findIndex((block) => block.id === draggedId); let to = blocks.findIndex((block) => block.id === target.dataset.outlineId);
    if (from < 0 || to < 0) return;
    const rect = target.getBoundingClientRect(); if (event.clientY > rect.top + rect.height / 2) to += 1;
    const [moved] = blocks.splice(from, 1); if (from < to) to -= 1; blocks.splice(to, 0, moved); selectedId = moved.id; renderAll(); announceChange(); showToast('Section moved.');
  });

  const resolveBlocks = (source) => {
    const out = [];
    source.forEach((block) => {
      if (!block || block.enabled === false) return;
      if (block.type !== 'pattern') { out.push({ ...block, _preview_owner_id: block.id }); return; }
      const pattern = patternById(block.pattern_id); if (!pattern) return;
      (pattern.blocks || []).forEach((inner) => {
        if (inner?.enabled === false || inner?.type === 'pattern') return;
        out.push({ ...inner, _preview_owner_id: block.id });
      });
    });
    return out;
  };

  const accentHeading = (value) => escapeHtml(value).replace(/\*([^*]+)\*/g, '<em>$1</em>');
  const previewImage = (block, itemIndex = null) => {
    let name = `page_block_${block.id}_image`; let item = block;
    if (itemIndex !== null) {
      const group = block.type === 'gallery' ? 'gallery' : 'card';
      name = `page_block_${block.id}_${group}_${itemIndex + 1}_image`;
      item = block.items?.[itemIndex] || {};
    }
    const file = fileState.get(name);
    if (file) {
      if (objectUrls.has(name)) URL.revokeObjectURL(objectUrls.get(name));
      const url = URL.createObjectURL(file); objectUrls.set(name, url); return url;
    }
    const asset = mediaById(item._media_id || 0); return asset?.path || item.image_path || '';
  };

  const previewField = (field, itemIndex = null) => ` data-preview-field="${escapeHtml(field)}"${itemIndex === null ? '' : ` data-preview-item-index="${Number(itemIndex)}"`}`;

  const previewBlock = (block) => {
    const attr = ` data-preview-block-id="${escapeHtml(block._preview_owner_id || block.id)}" data-preview-label="${escapeHtml(blockName(block))}" data-style-tone="${escapeHtml(block.style_tone || 'default')}" data-style-width="${escapeHtml(block.style_width || 'normal')}" data-style-spacing="${escapeHtml(block.style_spacing || 'normal')}" data-style-alignment="${escapeHtml(block.style_alignment || 'left')}" data-style-variant="${escapeHtml(block.style_variant || 'default')}"`;
    if (block.type === 'hero') { const image = previewImage(block); return `<section class="spottina-home-hero page-builder-hero${image ? ' has-media' : ''}"${attr}><div class="spottina-home-hero-copy">${block.eyebrow ? `<p class="home-kicker"${previewField('eyebrow')}>♡ ${escapeHtml(block.eyebrow)}</p>` : ''}<h2 class="page-builder-hero-title"${previewField('heading')}>${accentHeading(block.heading || '')}</h2>${block.intro ? `<p class="home-hero-intro"${previewField('intro')}>${escapeHtml(block.intro)}</p>` : ''}<div class="home-hero-actions">${block.primary_enabled ? `<a class="home-pill primary"${previewField('primary_label')}>${escapeHtml(block.primary_label || 'Button')} <span>→</span></a>` : ''}${block.secondary_enabled ? `<a class="home-pill secondary"${previewField('secondary_label')}>${escapeHtml(block.secondary_label || 'Button')}</a>` : ''}</div></div><figure class="spottina-home-hero-media${image ? '' : ' is-placeholder'}">${image ? `<img src="${escapeHtml(image)}" alt="">` : '<span>Hero image</span>'}</figure></section>`; }
    if (block.type === 'values') return `<section class="home-values-panel page-builder-values"${attr}>${(block.items || []).map((item, i) => `<article class="home-value-item"><span class="home-value-mark">${iconSvg(item.icon || 'heart')}</span><h2${previewField('title', i)}>${escapeHtml(item.title)}</h2><p${previewField('body', i)}>${escapeHtml(item.body)}</p></article>`).join('')}</section>`;
    if (block.type === 'cards') return `<section class="home-editorial-section home-featured-section page-builder-cards"${attr}><div class="home-section-heading"><div>${block.eyebrow ? `<p class="home-section-kicker"${previewField('eyebrow')}>${escapeHtml(block.eyebrow)}</p>` : ''}<h2${previewField('heading')}>${escapeHtml(block.heading || '')}</h2></div>${block.view_label ? `<span class="home-section-link"${previewField('view_label')}>${escapeHtml(block.view_label)} →</span>` : ''}</div><div class="home-featured-grid">${(block.items || []).map((item, i) => { const image = previewImage(block, i); return `<article class="home-featured-card tone-${(i % 4) + 1}"><figure class="home-featured-media${image ? '' : ' is-placeholder'}">${image ? `<img src="${escapeHtml(image)}" alt="">` : `<span>Image ${i + 1}</span>`}</figure><div class="home-featured-caption"><strong${previewField('title', i)}>${escapeHtml(item.title)}</strong><span${previewField('meta', i)}>${escapeHtml(item.meta || '')}</span></div></article>`; }).join('')}</div></section>`;
    if (block.type === 'gallery') return `<section class="page-builder-gallery layout-${escapeHtml(block.layout || 'grid')}"${attr}><div class="home-section-heading"><div>${block.eyebrow ? `<p class="home-section-kicker"${previewField('eyebrow')}>${escapeHtml(block.eyebrow)}</p>` : ''}${block.heading ? `<h2${previewField('heading')}>${escapeHtml(block.heading)}</h2>` : ''}</div></div><div class="page-gallery-grid">${(block.items || []).map((item, i) => { const image = previewImage(block, i); return `<figure class="page-gallery-item${image ? '' : ' is-placeholder'}">${image ? `<img src="${escapeHtml(image)}" alt="">` : `<span>Image ${i + 1}</span>`}${item.caption ? `<figcaption${previewField('caption', i)}>${escapeHtml(item.caption)}</figcaption>` : ''}</figure>`; }).join('')}</div></section>`;
    if (block.type === 'testimonials') return `<section class="page-builder-testimonials"${attr}><div class="home-section-heading"><div>${block.eyebrow ? `<p class="home-section-kicker"${previewField('eyebrow')}>${escapeHtml(block.eyebrow)}</p>` : ''}${block.heading ? `<h2${previewField('heading')}>${escapeHtml(block.heading)}</h2>` : ''}</div></div><div class="page-testimonial-grid">${(block.items || []).map((item, i) => `<blockquote class="page-testimonial"><p${previewField('quote', i)}>“${escapeHtml(item.quote || '')}”</p><footer><strong${previewField('name', i)}>${escapeHtml(item.name || '')}</strong>${item.role ? `<span${previewField('role', i)}>${escapeHtml(item.role)}</span>` : ''}</footer></blockquote>`).join('')}</div></section>`;
    if (block.type === 'faq') return `<section class="page-builder-faq"${attr}><div class="home-section-heading"><div>${block.eyebrow ? `<p class="home-section-kicker"${previewField('eyebrow')}>${escapeHtml(block.eyebrow)}</p>` : ''}${block.heading ? `<h2${previewField('heading')}>${escapeHtml(block.heading)}</h2>` : ''}</div></div><div class="page-faq-list">${(block.items || []).map((item, i) => `<details ${i === 0 ? 'open' : ''}><summary${previewField('question', i)}>${escapeHtml(item.question || '')}</summary><p${previewField('answer', i)}>${escapeHtml(item.answer || '')}</p></details>`).join('')}</div></section>`;
    if (block.type === 'stats') return `<section class="page-builder-stats"${attr}><div class="home-section-heading"><div>${block.eyebrow ? `<p class="home-section-kicker"${previewField('eyebrow')}>${escapeHtml(block.eyebrow)}</p>` : ''}${block.heading ? `<h2${previewField('heading')}>${escapeHtml(block.heading)}</h2>` : ''}</div></div><div class="page-stats-grid">${(block.items || []).map((item, i) => `<article><strong${previewField('value', i)}>${escapeHtml(item.value || '')}</strong><h3${previewField('label', i)}>${escapeHtml(item.label || '')}</h3>${item.body ? `<p${previewField('body', i)}>${escapeHtml(item.body)}</p>` : ''}</article>`).join('')}</div></section>`;
    if (block.type === 'custom') { const image = previewImage(block); const layout = escapeHtml(block.layout || 'stacked'); const tone = escapeHtml(block.tone || 'plain'); return `<section class="page-builder-custom layout-${layout} tone-${tone}${image ? ' has-media' : ''}"${attr}><div class="page-custom-copy">${block.eyebrow ? `<p class="home-section-kicker"${previewField('eyebrow')}>${escapeHtml(block.eyebrow)}</p>` : ''}${block.heading ? `<h2${previewField('heading')}>${accentHeading(block.heading)}</h2>` : ''}${block.body ? `<p${previewField('body')}>${escapeHtml(block.body).replace(/\n/g, '<br>')}</p>` : ''}<div class="home-hero-actions">${block.primary_enabled ? `<span class="home-pill primary"${previewField('primary_label')}>${escapeHtml(block.primary_label || 'Continue')} →</span>` : ''}${block.secondary_enabled ? `<span class="home-pill secondary"${previewField('secondary_label')}>${escapeHtml(block.secondary_label || 'More')}</span>` : ''}</div></div>${(image || ['split-left','split-right'].includes(block.layout)) ? `<figure class="page-custom-media${image ? '' : ' is-placeholder'}">${image ? `<img src="${escapeHtml(image)}" alt="">` : '<span>Optional image</span>'}</figure>` : ''}</section>`; }
    if (block.type === 'contact') return `<section class="page-builder-contact contact-section"${attr}><div class="contact-shell"><div class="contact-hero"><div class="contact-copy"><span class="contact-eyebrow">Contact</span><h2${previewField('heading')}>${escapeHtml(block.heading || 'Get in touch')}</h2>${block.intro ? `<p${previewField('intro')}>${escapeHtml(block.intro)}</p>` : ''}</div><div class="contact-assurances" aria-label="What to expect"><div class="contact-assurance-item"><span class="contact-assurance-number">01</span><div><strong>Direct</strong><p>Your message goes straight to the site contact recipient.</p></div></div><div class="contact-assurance-item"><span class="contact-assurance-number">02</span><div><strong>Useful context</strong><p>A clear subject and message help make the reply more useful.</p></div></div><div class="contact-assurance-item"><span class="contact-assurance-number">03</span><div><strong>Privacy-first</strong><p>No third-party form processor is required to send your message.</p></div></div></div></div><div class="contact-form-panel"><div class="contact-form-heading"><div><span class="contact-form-kicker">Send a message</span><h3>Start the conversation.</h3></div><p>Tell us what you need and include the details that will help us respond.</p></div><div class="contact-form contact-form-preview" aria-label="Contact form preview"><div class="contact-fields-row"><div class="contact-field"><label>Name *</label><input type="text" autocomplete="name" disabled></div><div class="contact-field"><label>Email *</label><input type="email" autocomplete="email" disabled></div></div>${block.show_subject !== false ? `<div class="contact-field"><label>Subject${block.require_subject ? ' *' : ' - optional'}</label><input type="text" disabled></div>` : ''}<div class="contact-field"><label>Message *</label><textarea rows="5" disabled></textarea></div><div class="contact-form-footer"><span class="button contact-submit"${previewField('submit_label')}>${escapeHtml(block.submit_label || 'Send message')}</span><p class="contact-privacy-note">Your details are used only to respond to this message.</p></div></div></div></div></section>`;
    if (block.type === 'latest_posts') { const count = Math.max(1, Math.min(6, Number(block.count) || 3)); return `<section class="home-editorial-section home-news-section page-builder-latest-posts"${attr}><div class="home-section-heading"><div>${block.eyebrow ? `<p class="home-section-kicker"${previewField('eyebrow')}>${escapeHtml(block.eyebrow)}</p>` : ''}<h2${previewField('heading')}>${escapeHtml(block.heading || 'Latest news')}</h2></div><span class="home-section-link"${previewField('view_label')}>${escapeHtml(block.view_label || 'View all news')} →</span></div><div class="home-news-grid">${Array.from({length: count}, (_, i) => `<article class="home-news-card preview-news-card"><div class="home-news-media is-placeholder"><span>${i + 1}</span></div><div class="home-news-content"><span class="home-news-category">CATEGORY</span><h3>Published post appears here</h3><time>Live content</time></div></article>`).join('')}</div></section>`; }
    if (block.type === 'collection') { const count = Math.max(1, Math.min(12, Number(block.count) || 6)); const model = modelByKey(block.model_key); const presentation = block.presentation || model?.recommended_presentation || 'cards'; return `<section class="page-collection collection-${escapeHtml(presentation)} builder-collection-preview"${attr}><div class="home-section-heading"><div>${block.eyebrow ? `<p class="home-section-kicker"${previewField('eyebrow')}>${escapeHtml(block.eyebrow)}</p>` : ''}<h2${previewField('heading')}>${escapeHtml(block.heading || model?.plural_name || 'Connected content')}</h2></div>${block.view_label ? `<span class="home-section-link"${previewField('view_label')}>${escapeHtml(block.view_label)} →</span>` : ''}</div>${model && !model.is_public ? '<div class="notice warning compact">This model is not public, so the live site will hide this collection.</div>' : ''}<div class="collection-grid presentation-${escapeHtml(presentation)}">${Array.from({length: Math.min(count, 6)}, (_, i) => `<article class="collection-card preview-collection-card"><div class="collection-card-media is-placeholder"><span>${i + 1}</span></div><div class="collection-card-body"><span class="collection-meta">${escapeHtml(model?.singular_name || 'Content')}</span><h3>Published ${escapeHtml((model?.singular_name || 'entry').toLowerCase())} appears here</h3><p>Live fields and media are filled from the selected Content Model.</p></div></article>`).join('')}</div></section>`; }
    if (block.type === 'cta') return `<section class="home-closing-cta page-builder-cta"${attr}><div>${block.eyebrow ? `<p class="home-section-kicker"${previewField('eyebrow')}>${escapeHtml(block.eyebrow)}</p>` : ''}<h2${previewField('heading')}>${escapeHtml(block.heading || '')}</h2></div><span class="home-pill primary"${previewField('button_label')}>${escapeHtml(block.button_label || 'Continue')} <span>→</span></span></section>`;
    return '';
  };

  const previewDocument = () => {
    const rich = form?.querySelector('[data-rich-hidden]')?.value || form?.querySelector('[data-rich-editable]')?.innerHTML || '';
    const rendered = resolveBlocks(blocks).map(previewBlock).join('');
    return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="${escapeHtml(location.origin)}/"><link rel="stylesheet" href="/assets/css/app.css?v=${encodeURIComponent(config.version || '')}"><link rel="stylesheet" href="/theme.css"></head><body class="public-body talvoro-builder-preview-body"><main class="main public-main talvoro-builder-preview-main"><div class="preview-rich-content">${rich}</div><div class="page-blocks page-blocks-home talvoro-builder-preview-blocks">${rendered}</div></main></body></html>`;
  };

  const markPreviewSelection = () => {
    const doc = previewFrame?.contentDocument; if (!doc) return;
    doc.querySelectorAll('[data-preview-block-id]').forEach((el) => el.classList.toggle('talvoro-preview-selected', el.dataset.previewBlockId === selectedId));
  };

  const setPreviewFocus = (on) => {
    previewFocused = Boolean(on);
    root.classList.toggle('is-preview-focus', previewFocused);
    if (previewFocusButton) {
      previewFocusButton.setAttribute('aria-pressed', previewFocused ? 'true' : 'false');
      previewFocusButton.textContent = previewFocused ? 'Exit focus' : 'Focus preview';
    }
  };

  const focusInspectorField = (blockId, field, itemIndex = null) => {
    if (previewFocused) setPreviewFocus(false);
    selectBlock(blockId, false);
    const card = list.querySelector(`[data-block-id="${CSS.escape(String(blockId))}"]`);
    if (!card) return;
    const escaped = CSS.escape(String(field));
    let target = null;
    if (itemIndex !== null && itemIndex !== '') {
      const item = card.querySelector(`[data-item-index="${CSS.escape(String(itemIndex))}"]`);
      target = item?.querySelector(`[data-item-field="${escaped}"], [data-field="item\:${escaped}"]`) || null;
    } else {
      target = card.querySelector(`[data-field="${escaped}"]`);
    }
    if (!target) { card.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); return; }
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    target.classList.add('builder-field-focus');
    window.setTimeout(() => target.classList.remove('builder-field-focus'), 1400);
    window.setTimeout(() => target.focus({ preventScroll: true }), 180);
  };

  const bindPreviewInteractions = (doc) => {
    doc.querySelectorAll('[data-preview-field]').forEach((el) => el.addEventListener('click', (event) => {
      event.preventDefault(); event.stopPropagation();
      const owner = el.closest('[data-preview-block-id]');
      if (!owner) return;
      const ownerBlock = blocks.find((block) => String(block.id) === String(owner.dataset.previewBlockId));
      if (ownerBlock?.type === 'pattern') { if (previewFocused) setPreviewFocus(false); selectBlock(owner.dataset.previewBlockId, true); return; }
      focusInspectorField(owner.dataset.previewBlockId, el.dataset.previewField || '', el.dataset.previewItemIndex ?? null);
    }));
    doc.querySelectorAll('[data-preview-block-id]').forEach((el) => el.addEventListener('click', (event) => { event.preventDefault(); event.stopPropagation(); if (previewFocused) setPreviewFocus(false); selectBlock(el.dataset.previewBlockId, true); }));
    doc.querySelectorAll('a').forEach((link) => link.addEventListener('click', (event) => event.preventDefault()));
    markPreviewSelection();
  };

  const renderPreview = () => {
    if (!previewFrame) return;
    const doc = previewFrame.contentDocument;
    const previewBlocks = doc?.querySelector('.talvoro-builder-preview-blocks');
    const previewRich = doc?.querySelector('.preview-rich-content');
    if (doc?.body && previewBlocks && previewRich) {
      previewRich.innerHTML = form?.querySelector('[data-rich-hidden]')?.value || form?.querySelector('[data-rich-editable]')?.innerHTML || '';
      previewBlocks.innerHTML = resolveBlocks(blocks).map(previewBlock).join('');
      bindPreviewInteractions(doc);
      return;
    }
    previewFrame.srcdoc = previewDocument();
    previewFrame.addEventListener('load', () => { const loaded = previewFrame.contentDocument; if (loaded) bindPreviewInteractions(loaded); }, { once: true });
  };
  const schedulePreview = () => { clearTimeout(previewTimer); previewTimer = window.setTimeout(renderPreview, 90); };

  root.querySelector('[data-open-builder-preview]')?.addEventListener('click', () => {
    sync();
    const blob = new Blob([previewDocument()], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const opened = window.open(url, '_blank', 'noopener');
    if (!opened) showToast('Your browser blocked the preview tab.');
    window.setTimeout(() => URL.revokeObjectURL(url), 60000);
  });

  root.querySelectorAll('[data-preview-size]').forEach((button) => button.addEventListener('click', () => {
    root.querySelectorAll('[data-preview-size]').forEach((item) => item.classList.toggle('is-active', item === button));
    previewStage.dataset.size = button.dataset.previewSize;
  }));

  previewFocusButton?.addEventListener('click', () => setPreviewFocus(!previewFocused));
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && previewFocused) setPreviewFocus(false); });

  const richEditable = form?.querySelector('[data-rich-editable]');
  richEditable?.addEventListener('input', () => {
    const command = richEditable.innerText.trim().toLowerCase();
    const commands = { '/hero': 'hero', '/image': 'image_text', '/values': 'values', '/cards': 'cards', '/gallery': 'gallery', '/quotes': 'testimonials', '/faq': 'faq', '/stats': 'stats', '/section': 'custom', '/posts': 'latest_posts', '/collection': 'collection', '/content': 'collection', '/contact': 'contact', '/cta': 'cta' };
    if (commands[command] && richEditable.innerText.trim().length === command.length) {
      richEditable.innerHTML = '';
      const hiddenRich = form.querySelector('[data-rich-hidden]'); if (hiddenRich) hiddenRich.value = '';
      insertBlocks([defaultBlock(commands[command])], blocks.length);
      showToast(`${blockName(commands[command])} added.`);
      return;
    }
    schedulePreview();
  });

  document.addEventListener('talvoro:page-builder-sync', sync);
  document.addEventListener('talvoro:restore-blocks', (event) => { const restored = event.detail?.blocks; blocks = Array.isArray(restored) ? structuredClone(restored).map((block) => ({ enabled: block?.enabled !== false, ...block })) : []; selectedId = blocks[0]?.id || ''; renderAll(); });
  form?.addEventListener('submit', () => { sync(); captureFiles(); });

  renderAll();
})();
