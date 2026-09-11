import { ConfigurationService } from "./configuration.service.js";
import "reflect-metadata";
import { NestFactory } from "@nestjs/core";
import { AppModule } from "./app.module.js";
import type { NestExpressApplication } from "@nestjs/platform-express";
import { existsSync } from "node:fs";
import { isAbsolute, join } from "node:path";
import type { Request, Response, NextFunction } from "express";
import type { Database } from "./database.js";

export async function createApp(
  adminDirectory = process.env.ADMIN_DIST_PATH,
  database?: Database,
) {
  if (
    adminDirectory &&
    (!isAbsolute(adminDirectory) ||
      !existsSync(join(adminDirectory, "index.html")))
  ) {
    throw new Error(
      "ADMIN_DIST_PATH must be an absolute directory containing the built admin index.html",
    );
  }
  const origin = process.env.ADMIN_ORIGIN || "http://localhost:5173";
  if (
    new URL(origin).origin !== origin ||
    (process.env.NODE_ENV === "production" && !origin.startsWith("https://"))
  )
    throw new Error(
      "ADMIN_ORIGIN must be an exact origin; HTTPS is required in production.",
    );
  const app = await NestFactory.create<NestExpressApplication>(
    AppModule.configure(database),
    { logger: ["error", "warn", "log"] },
  );
  app.setGlobalPrefix("api/v1");
  app.useBodyParser("json", { limit: "1mb" });
  app.enableCors({
    origin,
    credentials: true,
    allowedHeaders: ["Content-Type", "X-Tech4Learn-Request"],
  });
  app.use(async (req: Request, res: Response, next: NextFunction) => {
    res.setHeader("X-Content-Type-Options", "nosniff");
    res.setHeader("Referrer-Policy", "no-referrer");
    res.setHeader("X-Frame-Options", "DENY");
    res.setHeader(
      "Content-Security-Policy",
      "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; connect-src 'self' " +
        origin +
        "; img-src 'self' data:; object-src 'none'; base-uri 'none'; frame-ancestors 'none'",
    );
    if (req.path.startsWith("/api/")) {
      res.setHeader("Cache-Control", "no-store");
      let binding: { id: string; slug: string; hostname: string } | null = null;
      const host = (req.headers.host || "").toLowerCase();
      if (host !== new URL(origin).host && /^[a-z0-9.-]+\.[a-z]+$/.test(host)) {
        try {
          binding = await app.get(ConfigurationService).host(host);
        } catch {
          res
            .status(503)
            .json({ message: "Organisation routing is unavailable." });
          return;
        }
      }
      if (binding) {
        const match = req.path.match(
          /^\/api\/v1\/organisations(?:\/([^/]+))?(?:\/|$)/,
        );
        if (match && match[1] !== binding.id) {
          res
            .status(403)
            .json({
              message: "Use the organisation assigned to this hostname.",
            });
          return;
        }
      }
      let allowedOrigin = req.headers.origin === origin;
      if (
        !allowedOrigin &&
        req.headers.origin &&
        req.headers.origin === `https://${req.headers.host}`
      ) {
        allowedOrigin = !!binding;
      }
      if (
        !["GET", "HEAD", "OPTIONS"].includes(req.method) &&
        (!allowedOrigin ||
          req.headers["x-tech4learn-request"] !== "1" ||
          !req.is("application/json"))
      ) {
        res
          .status(403)
          .json({ message: "Request origin or content type is not allowed." });
        return;
      }
    }
    next();
  });
  app.enableShutdownHooks();
  if (adminDirectory) {
    app.useStaticAssets(adminDirectory, {
      dotfiles: "deny",
      index: "index.html",
    });
  }
  return app;
}
