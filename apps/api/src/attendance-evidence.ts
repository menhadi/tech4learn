import { BadRequestException } from "@nestjs/common";

export function timestamp(value: unknown): number {
  if (
    typeof value !== "string" ||
    !/^\d{4}-\d{2}-\d{2}T.*Z$/.test(value) ||
    !Number.isFinite(Date.parse(value))
  )
    throw new BadRequestException(
      "A valid UTC capture or location time is required.",
    );
  return Date.parse(value);
}
export function distanceMetres(a: number, b: number, c: number, d: number) {
  const rad = Math.PI / 180;
  const x =
    Math.sin(((c - a) * rad) / 2) ** 2 +
    Math.cos(a * rad) * Math.cos(c * rad) * Math.sin(((d - b) * rad) / 2) ** 2;
  return 6371000 * 2 * Math.asin(Math.sqrt(Math.min(1, x)));
}
export type CentreLocation = {
  latitude: number | null;
  longitude: number | null;
  radius: number;
  location_approved: boolean;
};
export function classifyLocation(
  input: unknown,
  centre: CentreLocation,
  captured: number,
  received: number,
  accuracyLimit: number,
) {
  const warnings: string[] = [];
  if (
    !centre.location_approved ||
    centre.latitude === null ||
    centre.longitude === null
  )
    warnings.push("Centre coordinates have not been approved.");
  if (received - captured > 300000)
    warnings.push("Photo submission was delayed by more than five minutes.");
  let location: {
    latitude: number;
    longitude: number;
    accuracy: number;
    timestamp: string;
  } | null = null;
  let distance: number | null = null;
  if (input === null)
    warnings.push("Location was unavailable or permission was denied.");
  else {
    if (!input || typeof input !== "object" || Array.isArray(input))
      throw new BadRequestException("Invalid location reading.");
    const v = input as Record<string, unknown>;
    const { latitude, longitude, accuracy } = v;
    if (
      typeof latitude !== "number" ||
      !Number.isFinite(latitude) ||
      Math.abs(latitude) > 90 ||
      typeof longitude !== "number" ||
      !Number.isFinite(longitude) ||
      Math.abs(longitude) > 180 ||
      typeof accuracy !== "number" ||
      !Number.isFinite(accuracy) ||
      accuracy < 0 ||
      accuracy > 1000000
    )
      throw new BadRequestException(
        "Invalid location coordinates or accuracy.",
      );
    const readAt = timestamp(v.timestamp);
    location = {
      latitude,
      longitude,
      accuracy,
      timestamp: new Date(readAt).toISOString(),
    };
    if (Math.abs(readAt - captured) > 60000 || readAt > received + 30000)
      warnings.push("Location reading is not close to the capture time.");
    if (accuracy > accuracyLimit)
      warnings.push("Location accuracy is below the required standard.");
    if (centre.latitude !== null && centre.longitude !== null) {
      distance = distanceMetres(
        centre.latitude,
        centre.longitude,
        latitude,
        longitude,
      );
      if (distance > centre.radius)
        warnings.push("Capture is outside the centre radius.");
      else if (distance + accuracy > centre.radius)
        warnings.push("Location uncertainty crosses the centre boundary.");
    }
  }
  return {
    location,
    distance: distance === null ? null : Math.round(distance),
    warnings,
    location_status: warnings.length ? "review-required" : "within-radius",
  };
}
// Validate a bounded JPEG container. This does not attest to camera authenticity.
export function jpeg(value: unknown) {
  if (
    typeof value !== "string" ||
    value.length > 349528 ||
    !/^[A-Za-z0-9+/]+={0,2}$/.test(value)
  )
    throw new BadRequestException(
      "Capture a JPEG photo no larger than 256 KB.",
    );
  const bytes = Buffer.from(value, "base64");
  if (
    bytes.toString("base64") !== value ||
    bytes.length < 24 ||
    bytes.length > 262144 ||
    bytes.readUInt16BE(0) !== 0xffd8 ||
    bytes.readUInt16BE(bytes.length - 2) !== 0xffd9
  )
    throw new BadRequestException("Invalid JPEG photo.");
  let pos = 2,
    dimensions = false,
    scan = false;
  while (pos + 4 <= bytes.length) {
    if (bytes[pos++] !== 255) break;
    while (bytes[pos] === 255) pos++;
    const marker = bytes[pos++];
    if (pos + 2 > bytes.length) break;
    if (marker === 0xda) {
      scan = true;
      break;
    }
    const length = bytes.readUInt16BE(pos);
    if (length < 2 || pos + length > bytes.length) break;
    if ([0xc0, 0xc1, 0xc2].includes(marker)) {
      if (length < 8) break;
      const h = bytes.readUInt16BE(pos + 3),
        w = bytes.readUInt16BE(pos + 5);
      dimensions = h > 0 && w > 0 && h <= 4096 && w <= 4096;
    }
    pos += length;
  }
  if (!dimensions || !scan)
    throw new BadRequestException("Invalid or oversized JPEG dimensions.");
  return bytes;
}
