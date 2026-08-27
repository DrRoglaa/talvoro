(() => {
  // Dependency-free local QR encoder adapted from the user's ItsRoglic CMS.
  // The TOTP URI never leaves the browser.
  const QR_VERSION = 8;
  const QR_SIZE = 17 + QR_VERSION * 4;
  const QR_DATA_CODEWORDS = 194;
  const QR_ECC_CODEWORDS_PER_BLOCK = 24;
  const QR_BLOCKS = 2;

  function gfMultiply(x, y) {
    let z = 0;
    for (let i = 7; i >= 0; i -= 1) {
      z = (z << 1) ^ ((z >>> 7) * 0x11d);
      z ^= ((y >>> i) & 1) * x;
    }
    return z;
  }
  function rsDivisor(degree) {
    const result = new Uint8Array(degree);
    result[degree - 1] = 1;
    let root = 1;
    for (let i = 0; i < degree; i += 1) {
      for (let j = 0; j < degree; j += 1) {
        result[j] = gfMultiply(result[j], root);
        if (j + 1 < degree) result[j] ^= result[j + 1];
      }
      root = gfMultiply(root, 0x02);
    }
    return result;
  }
  function rsRemainder(data, divisor) {
    const result = new Uint8Array(divisor.length);
    for (const byte of data) {
      const factor = byte ^ result[0];
      result.copyWithin(0, 1);
      result[result.length - 1] = 0;
      for (let i = 0; i < result.length; i += 1) result[i] ^= gfMultiply(divisor[i], factor);
    }
    return result;
  }
  function appendBits(value, length, bits) {
    for (let i = length - 1; i >= 0; i -= 1) bits.push((value >>> i) & 1);
  }
  function dataCodewords(text) {
    const bytes = new TextEncoder().encode(String(text || ''));
    if (bytes.length > 192) throw new Error('TOTP setup URI is too long.');
    const bits = [];
    appendBits(0x4, 4, bits);
    appendBits(bytes.length, 8, bits);
    for (const byte of bytes) appendBits(byte, 8, bits);
    const capacity = QR_DATA_CODEWORDS * 8;
    appendBits(0, Math.min(4, capacity - bits.length), bits);
    while (bits.length % 8) bits.push(0);
    const output = [];
    for (let i = 0; i < bits.length; i += 8) {
      let value = 0;
      for (let j = 0; j < 8; j += 1) value = (value << 1) | bits[i + j];
      output.push(value);
    }
    for (let pad = 0; output.length < QR_DATA_CODEWORDS; pad += 1) output.push(pad % 2 === 0 ? 0xec : 0x11);
    return Uint8Array.from(output);
  }
  function interleaved(text) {
    const data = dataCodewords(text);
    const blockLength = QR_DATA_CODEWORDS / QR_BLOCKS;
    const divisor = rsDivisor(QR_ECC_CODEWORDS_PER_BLOCK);
    const blocks = [], ecc = [];
    for (let block = 0; block < QR_BLOCKS; block += 1) {
      const chunk = data.slice(block * blockLength, (block + 1) * blockLength);
      blocks.push(chunk); ecc.push(rsRemainder(chunk, divisor));
    }
    const result = [];
    for (let i = 0; i < blockLength; i += 1) for (const block of blocks) result.push(block[i]);
    for (let i = 0; i < QR_ECC_CODEWORDS_PER_BLOCK; i += 1) for (const block of ecc) result.push(block[i]);
    return Uint8Array.from(result);
  }
  function formatBits(mask) {
    const data = (1 << 3) | mask;
    let remainder = data;
    for (let i = 0; i < 10; i += 1) remainder = (remainder << 1) ^ ((remainder >>> 9) * 0x537);
    return ((data << 10) | remainder) ^ 0x5412;
  }
  function versionBits() {
    let remainder = QR_VERSION;
    for (let i = 0; i < 12; i += 1) remainder = (remainder << 1) ^ ((remainder >>> 11) * 0x1f25);
    return (QR_VERSION << 12) | remainder;
  }
  function penalty(modules) {
    const size = modules.length;
    let result = 0;
    for (let y = 0; y < size; y += 1) {
      for (let x = 0; x < size; x += 1) {
        const color = modules[y][x]; let run = 1;
        while (x + run < size && modules[y][x + run] === color) run += 1;
        if (run >= 5) result += 3 + (run - 5);
        x += run - 1;
      }
    }
    for (let x = 0; x < size; x += 1) {
      for (let y = 0; y < size; y += 1) {
        const color = modules[y][x]; let run = 1;
        while (y + run < size && modules[y + run][x] === color) run += 1;
        if (run >= 5) result += 3 + (run - 5);
        y += run - 1;
      }
    }
    for (let y = 0; y < size - 1; y += 1) for (let x = 0; x < size - 1; x += 1) {
      const c = modules[y][x];
      if (modules[y][x + 1] === c && modules[y + 1][x] === c && modules[y + 1][x + 1] === c) result += 3;
    }
    const pattern = [true,false,true,true,true,false,true,false,false,false,false];
    const reverse = [...pattern].reverse();
    const matches = (line, start, p) => p.every((v, i) => line[start + i] === v);
    for (let y = 0; y < size; y += 1) {
      const line = modules[y];
      for (let x = 0; x <= size - 11; x += 1) if (matches(line, x, pattern) || matches(line, x, reverse)) result += 40;
    }
    for (let x = 0; x < size; x += 1) {
      const line = modules.map((row) => row[x]);
      for (let y = 0; y <= size - 11; y += 1) if (matches(line, y, pattern) || matches(line, y, reverse)) result += 40;
    }
    const dark = modules.flat().filter(Boolean).length;
    result += Math.floor(Math.abs(dark * 100 / (size * size) - 50) / 5) * 10;
    return result;
  }
  function buildMatrix(codewords, mask) {
    const size = QR_SIZE;
    const modules = Array.from({ length: size }, () => Array(size).fill(false));
    const fn = Array.from({ length: size }, () => Array(size).fill(false));
    const set = (x, y, dark) => { if (x >= 0 && y >= 0 && x < size && y < size) { modules[y][x] = !!dark; fn[y][x] = true; } };
    const finder = (cx, cy) => { for (let dy = -4; dy <= 4; dy += 1) for (let dx = -4; dx <= 4; dx += 1) { const d = Math.max(Math.abs(dx), Math.abs(dy)); set(cx + dx, cy + dy, d !== 2 && d !== 4); } };
    const align = (cx, cy) => { for (let dy = -2; dy <= 2; dy += 1) for (let dx = -2; dx <= 2; dx += 1) set(cx + dx, cy + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1); };
    for (let i = 0; i < size; i += 1) { set(6, i, i % 2 === 0); set(i, 6, i % 2 === 0); }
    finder(3, 3); finder(size - 4, 3); finder(3, size - 4);
    const positions = [6, 24, 42];
    for (let r = 0; r < positions.length; r += 1) for (let c = 0; c < positions.length; c += 1) {
      if (!((r === 0 && c === 0) || (r === 0 && c === positions.length - 1) || (r === positions.length - 1 && c === 0))) align(positions[c], positions[r]);
    }
    const drawFormat = () => {
      const bits = formatBits(mask), bit = (i) => ((bits >>> i) & 1) !== 0;
      for (let i = 0; i <= 5; i += 1) set(8, i, bit(i));
      set(8, 7, bit(6)); set(8, 8, bit(7)); set(7, 8, bit(8));
      for (let i = 9; i < 15; i += 1) set(14 - i, 8, bit(i));
      for (let i = 0; i < 8; i += 1) set(size - 1 - i, 8, bit(i));
      for (let i = 8; i < 15; i += 1) set(8, size - 15 + i, bit(i));
      set(8, size - 8, true);
    };
    drawFormat();
    const vb = versionBits();
    for (let i = 0; i < 18; i += 1) { const d = ((vb >>> i) & 1) !== 0, a = size - 11 + (i % 3), b = Math.floor(i / 3); set(a, b, d); set(b, a, d); }
    let bitIndex = 0;
    for (let right = size - 1; right >= 1; right -= 2) {
      if (right === 6) right = 5;
      for (let vert = 0; vert < size; vert += 1) {
        const upward = ((right + 1) & 2) === 0, y = upward ? size - 1 - vert : vert;
        for (let j = 0; j < 2; j += 1) {
          const x = right - j; if (fn[y][x]) continue;
          modules[y][x] = bitIndex < codewords.length * 8 && ((codewords[bitIndex >>> 3] >>> (7 - (bitIndex & 7))) & 1) !== 0;
          bitIndex += 1;
        }
      }
    }
    const maskHit = (x, y) => [
      (x + y) % 2 === 0, y % 2 === 0, x % 3 === 0, (x + y) % 3 === 0,
      (Math.floor(y / 2) + Math.floor(x / 3)) % 2 === 0,
      ((x * y) % 2) + ((x * y) % 3) === 0,
      ((((x * y) % 2) + ((x * y) % 3)) % 2) === 0,
      ((((x + y) % 2) + ((x * y) % 3)) % 2) === 0
    ][mask];
    for (let y = 0; y < size; y += 1) for (let x = 0; x < size; x += 1) if (!fn[y][x] && maskHit(x, y)) modules[y][x] = !modules[y][x];
    drawFormat(); return modules;
  }
  function matrix(text) {
    const words = interleaved(text); let best = null, score = Infinity;
    for (let mask = 0; mask < 8; mask += 1) { const m = buildMatrix(words, mask), p = penalty(m); if (p < score) { best = m; score = p; } }
    return best;
  }
  function render(host, text) {
    const m = matrix(text), size = m.length;
    let path = '';
    for (let y = 0; y < size; y += 1) for (let x = 0; x < size; x += 1) if (m[y][x]) path += `M${x} ${y}h1v1h-1z`;
    host.innerHTML = `<svg viewBox="-4 -4 ${size + 8} ${size + 8}" role="img" aria-label="Authenticator QR code"><rect x="-4" y="-4" width="${size + 8}" height="${size + 8}" fill="white"/><path d="${path}" fill="#2b2420"/></svg>`;
  }
  document.querySelectorAll('[data-totp-uri]').forEach((el) => {
    try { render(el, el.getAttribute('data-totp-uri') || ''); }
    catch { el.textContent = 'QR unavailable — use the manual secret.'; }
  });
})();
