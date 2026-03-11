# Hopper

> "Find the right framing, and the code writes itself."

> "Make the right thing obvious"

![Badge showing Status is Active Development](https://img.shields.io/badge/Status-Active_Development-green) ![Badge for GitHub License](https://img.shields.io/github/license/StrangeSpaceBaby/hopper)

![Badge showing Version is Alpha-Experimental](https://img.shields.io/badge/Version-0.001--Alpha--Experimental-orange) ![Badge for GitHub Pull Requests](https://img.shields.io/github/issues-pr/StrangeSpaceBaby/hopper)


A security-first, multi-tenant-first API framework named for [Grace Hopper](https://en.wikipedia.org/wiki/Grace_Hopper). She was a Rear Admiral in the US Navy, a pioneer of machine-independent programming languages, a compiler inventor and the person who made software accessible to humans who weren't mathematicians. The framework's philosophy of making the right thing the obvious thing is borrowed directly from her.

Core architecture is designed and actively being built out.

Check out the [Substack Newsletter](https://cakevcs.substack.com) for updates.

## What is Hopper?

Hopper is a RESTful API framework written in PHP purpose-built for multi-tenant SaaS applications. It is not a general-purpose framework and does not aspire to be. Every architectural decision flows from two constraints: **tenant isolation must be structurally enforced**, and **security boundaries must be impossible to _accidentally or purposefully_ cross (or at least _incredibly_ difficult)**.

The application design pattern is best described as a **Tenant-isolated Hexagonal ("ports and adaptors") Mediator Command** architecture.

## Why Hopper?

Every major framework treats multi-tenancy as a package or a pattern you bolt on after the fact. Hopper builds it into the kernel. Tenant isolation is a structural constraint enforced at the query layer, the logging layer, and the execution context layer simultaneously.

The other frameworks also collapse the distinction between routing, security, and business logic. In Hopper these are separated by architecture. A hook cannot reach across entity boundaries. (See [Core Architectural Principles - Explicit execution contexts](#core-architectural-principles) below) A query cannot be constructed without tenant scope. A raw SQL escape hatch requires an environment variable set at the infrastructure level.

### Composed-boot Design Pattern
Hopper also experiments with a new architectural design pattern for frameworks to improve third-party package security and sandboxing, provisionally called a "composed-boot" or "inverted-boot" design pattern. Whereas other frameworks load packages into the application space by default, Hopper requires a composed instantiation _by the package_ and all package operations have to have capability-gated permission for any operations within the framework itself like database connection, access to other packages, etc. 

## Core Architectural Principles

**Convention over configuration.** Directory structure and naming conventions define behavior. Framework programmatic organization mapped to filesystem relationships.

**Rack-centric organization.** Everything, including Hopper internals, is organized into "racks".  Racks are logical code bundles with a consistent directory structure for uniformity of address by Hopper.

**Explicit execution contexts.** Each rack declares its access surfaces through discrete context files: `{rack}.hook.php` for web, `{rack}.cli.php` for CLI, `{rack}.cron.php` for scheduled tasks. No file means no access. This is not security by absence, but by explicit declaration.

**Structural tenant isolation.** Because of the capability gates in Hopper, a rack must request a database connection that is already pre-configured. All queries are automatically scoped to the current tenant. Because of the private properties of the db connector and in conjunction with request preservation through CONSTANTS, the tenant_id of the current request _cannot be overriden_. Attempts to manipulate `tenant_id` after it is set are detected, logged with a full backtrace, and treated as intrusion events. Tenant identity is immutable for the lifetime of a request.

**Query building and execution are decoupled to improve secrets management and improve performance.** Hopper's QueryBuilder constructs and validates queries including schema validation and anomaly detection without touching a database connection. Execution is a separate, explicit step through the application context that requires a HopperQuery object to execute a query. String queries can never be run without explicit and `defined()` environment variables.

**Unary execution path.** One request, one path. No magic, no event soup, no middleware stack that requires spelunking to understand what actually ran. The rack is the middleware.

## Security Model

- All request parameters are preserved either as CONSTANTs or private class properties prior to Hopper boot to protect against request tampering
- Entities can only invoke logic within their own directory boundary
- `tenant_id` is immutable in the HopperApp object after first assignment (PHP 8.4 property hooks where set voids update)
- Intrusion detection built into the query layer. Anomalies are observable and logged without halting execution
- Raw SQL access (`db:raw` skill) requires `ALLOW_HPR_RAW={rack_name}` set at the environment level — unsafe access requires infrastructure-level approval
- Path-based permission system: the `perm` table keys directly to hook methods
- Role-based access control via `role_perm` junction, with module-level subscription gating
- Per-channel, per-request logging with ULID-based request fingerprints for cross-file correlation
