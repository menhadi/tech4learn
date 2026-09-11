import { BadRequestException } from "@nestjs/common";
import { createHash, randomBytes, scrypt, timingSafeEqual } from "node:crypto";

export const digest = (value: string) =>
  createHash("sha256").update(value).digest("hex");
export const token = () => randomBytes(32).toString("hex");
const derive = (password: string, salt: string) =>
  new Promise<Buffer>((resolve, reject) => {
    scrypt(
      password,
      salt,
      64,
      { N: 32768, r: 8, p: 3, maxmem: 64 * 1024 * 1024 },
      (error, key) => (error ? reject(error) : resolve(key)),
    );
  });
export function passwordValue(value: unknown): string {
  if (typeof value !== "string" || value.length < 15 || value.length > 128)
    throw new BadRequestException("Use a password of 15–128 characters.");
  return value;
}
export function loginPassword(value: unknown): string {
  if (typeof value !== "string" || !value.length || value.length > 128)
    throw new BadRequestException("Enter your password.");
  return value;
}
export async function hashPassword(password: string) {
  const salt = randomBytes(16).toString("hex");
  return `scrypt-v1$${salt}$${(await derive(password, salt)).toString("hex")}`;
}
export async function verifyPassword(password: string, stored: string) {
  const [version, salt, hex] = stored.split("$");
  if (version !== "scrypt-v1" || !salt || !hex || !/^[a-f0-9]{128}$/.test(hex))
    return false;
  return timingSafeEqual(await derive(password, salt), Buffer.from(hex, "hex"));
}
// A real scrypt calculation is also performed when an email does not exist.
export const dummyHash = `scrypt-v1$00000000000000000000000000000000$${"00".repeat(64)}`;
export function field(value: unknown, label: string, max = 120) {
  if (typeof value !== "string" || !value.trim() || value.trim().length > max)
    throw new BadRequestException(
      `${label} is required (maximum ${max} characters).`,
    );
  return value.trim();
}
export function emailValue(value: unknown) {
  const email = field(value, "Email", 254).toLowerCase();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
    throw new BadRequestException("Enter a valid email address.");
  return email;
}
export function uuid(value: string) {
  if (
    !/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i.test(
      value,
    )
  )
    throw new BadRequestException("Invalid record ID.");
  return value;
}
