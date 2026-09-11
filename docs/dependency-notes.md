# Dependency review

Initial npm audit identified multer advisories below 2.3.0. NestJS platform-express 12.0.1 pins 2.2.0, so a narrowly scoped override selects patched 2.3.0. Remove the override when the upstream dependency is updated. There is no upload route yet; test multipart limits and aborted uploads when implementing uploads.

Expo SDK 57 currently brings xcode 3.0.1 / uuid 7 through its configuration and native project-generation dependencies. npm audit reports GHSA-w5hq-g745-h8pq and propagates the moderate rating through the Expo packages. Do not apply npm's proposed SDK 46 downgrade or blindly override uuid across a major-version boundary. Track an upstream compatible update; review this before native builds and deployment. Treat build inputs as trusted project configuration, not user-uploaded Xcode projects.

The identity release adds pg for PostgreSQL and development-only PGlite for testing SQL and API permissions against an embedded PostgreSQL engine. Password hashing uses Node's built-in crypto module. The API/admin dependency install reported no vulnerabilities; the full workspace audit still reports the 10 pre-existing moderate Expo dependency findings above, with no high/critical findings. A passing build does not constitute a complete production security review.
