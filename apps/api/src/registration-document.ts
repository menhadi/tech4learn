import { BadRequestException } from "@nestjs/common";

/** A transient registration document, never a portrait or face reference. */
export function registrationDocument(input: unknown): Buffer {
  const invalid = () => new BadRequestException("Use a JPEG registration page up to 5 MB and 4096 pixels per side.");
  if (typeof input !== "string" || input.length > 6990508 ||
      !/^[A-Za-z0-9+/]+={0,2}$/.test(input)) throw invalid();
  const bytes = Buffer.from(input, "base64");
  if (bytes.toString("base64") !== input || bytes.length < 24 ||
      bytes.length > 5 * 1024 * 1024 || bytes.readUInt16BE(0) !== 0xffd8 ||
      bytes.readUInt16BE(bytes.length - 2) !== 0xffd9) throw invalid();
  const parts = [bytes.subarray(0, 2)];
  let offset = 2, dimensions = false, scan = false;
  while (offset + 4 <= bytes.length) {
    const start = offset;
    if (bytes[offset++] !== 0xff) throw invalid();
    while (bytes[offset] === 0xff) offset++;
    const marker = bytes[offset++];
    if (offset + 2 > bytes.length) throw invalid();
    const length = bytes.readUInt16BE(offset);
    if (length < 2 || offset + length > bytes.length) throw invalid();
    if (marker === 0xda) {
      if (!dimensions || length < 6) throw invalid();
      // Accept one baseline scan. Reject trailing metadata/extra images rather
      // than carrying an uninspected suffix to an external provider.
      let cursor = offset + length;
      while (cursor < bytes.length) {
        if (bytes[cursor++] !== 0xff) continue;
        while (bytes[cursor] === 0xff) cursor++;
        const code = bytes[cursor++];
        if (code === 0 || (code >= 0xd0 && code <= 0xd7)) continue;
        if (code !== 0xd9 || cursor !== bytes.length) throw invalid();
        break;
      }
      parts.push(bytes.subarray(start));
      scan = true;
      break;
    }
    if ([0xc0, 0xc1, 0xc2].includes(marker)) {
      if (marker !== 0xc0 || dimensions || length < 8) throw invalid();
      const height = bytes.readUInt16BE(offset + 3), width = bytes.readUInt16BE(offset + 5);
      if (width < 160 || height < 160 || width > 4096 || height > 4096) throw invalid();
      dimensions = true;
    }
    // Remove EXIF/GPS, application metadata and comments before provider transmission.
    if (!(marker >= 0xe0 && marker <= 0xef) && marker !== 0xfe)
      parts.push(bytes.subarray(start, offset + length));
    offset += length;
  }
  if (!scan) throw invalid();
  return Buffer.concat(parts);
}
