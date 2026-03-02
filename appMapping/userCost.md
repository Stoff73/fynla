# Fynla Hosting & Scaling Cost Analysis

**Date:** 20 February 2026 | **Current Plan:** SiteGround GrowBig (Shared Hosting) | **Version:** v0.7.0

---

## Table of Contents

1. [Application Resource Profile](#1-application-resource-profile)
2. [Current Hosting: SiteGround Plans](#2-current-hosting-siteground-plans)
3. [Usage Projections by User Count](#3-usage-projections-by-user-count)
4. [When to Upgrade: Decision Points](#4-when-to-upgrade-decision-points)
5. [Cloud Infrastructure Options & Costs](#5-cloud-infrastructure-options--costs)
6. [Scaling Roadmap](#6-scaling-roadmap)
7. [Technical Optimisations Before Scaling](#7-technical-optimisations-before-scaling)
8. [Cost Summary Table](#8-cost-summary-table)

---

## 1. Application Resource Profile

### 1.1 What Makes Fynla Resource-Intensive

Fynla is not a typical CRUD application. It is a **computationally heavy financial planning engine** that performs real-time calculations across seven interconnected modules. Understanding this is critical for accurate capacity planning.

**Codebase Scale:**

| Metric | Count |
|--------|-------|
| Vue Components | 316 |
| PHP Services | 143 |
| Eloquent Models | 70 |
| Database Migrations | 68 |
| API Endpoints | ~200+ |
| Registered API Routes | ~522 |

### 1.2 CPU-Intensive Operations

These are the operations that dominate server resource consumption. Unlike a blog or e-commerce site, Fynla runs complex financial calculations on every page load:

| Operation | What It Does | CPU Impact | Duration (Cold) | Memory |
|-----------|-------------|-----------|-----------------|--------|
| **Monte Carlo Simulation** | 1,000 iterations of portfolio projections using Box-Muller transform | Very High | 2-10 seconds | ~40 MB |
| **Markowitz Portfolio Optimisation** | Gradient descent over N x N covariance matrices (1,000 iterations) | Very High | 500ms-3s | ~50 MB |
| **Holistic Analysis** | Orchestrates ALL 4 agents (Protection, Savings, Investment, Retirement) | Very High | 2-8 seconds | ~100 MB |
| **Dashboard Load (cold)** | Aggregates data from all modules via 4 agent calls | High | 500ms-2s | ~80 MB |
| **Retirement Projections** | Year-by-year income drawdown to age 100 with tax band tracking | High | 1-5 seconds | ~60 MB |
| **Estate/IHT Calculations** | Multi-asset forward projections, spouse death scenarios, NRB transfers | High | 500ms-3s | ~70 MB |
| **Efficient Frontier** | Multiple optimisation variants per request | High | 500ms-3s | ~50 MB |
| **Goals Projection** | Year-by-year net worth projection integrating all assets, goals, life events | Medium | 200ms-1s | ~40 MB |
| **Simple CRUD** (save account) | Standard database read/write | Low | <50ms | ~20 MB |

### 1.3 Current Infrastructure Configuration

| Setting | Current Value | Scaling Impact |
|---------|--------------|----------------|
| Cache Driver | **File** | No shared cache across PHP workers; duplicate computations |
| Queue Connection | **Sync** | All jobs run inline in HTTP requests (Monte Carlo blocks for up to 5 min) |
| Session Driver | **File** | File I/O bottleneck under concurrent users |
| Redis | **Not configured** | No atomic operations, no pub/sub, no cache tagging |
| Background Workers | **None** | Everything synchronous |
| Scheduled Tasks | 2 (trial reminders + trial expiry) | Lightweight |

### 1.4 Database Records per User

A fully loaded user household (e.g., a couple with multiple properties, pensions, investments) generates approximately **150-300 database records** across all tables:

| Entity | Typical Records per User |
|--------|-------------------------|
| Properties + Mortgages | 3-6 |
| Savings Accounts | 3-5 |
| Investment Accounts + Holdings | 15-75 |
| Pensions (DC + DB + State) | 3-6 |
| Insurance Policies (all types) | 3-6 |
| Goals + Life Events | 6-18 |
| Family Members | 2-4 |
| Estate (Trusts, Gifts, Bequests, Will) | 5-15 |
| Audit Logs | Grows unboundedly (~50-200/month per active user) |
| Sessions, Login Attempts | 1-10 active |

**Database size estimate:** ~500 KB per active user (excluding audit logs), growing to ~2-5 MB per user over a year with audit history.

### 1.5 External API Dependencies

| Service | Purpose | Impact per Call |
|---------|---------|----------------|
| **Anthropic Claude API** | Document AI extraction (PDF/image parsing) | 120-second blocking HTTP call |
| **GetAddress.io** | UK postcode lookup | 10-second timeout, cached 1 hour |
| **Revolut Payments** | Subscription payments | Low frequency |

### 1.6 Frontend Asset Size

| Asset | Size |
|-------|------|
| Total Build Output | 6.9 MB |
| Main JS Bundle (app.js) | 1.0 MB (uncompressed) |
| Largest Route Chunks | 100-300 KB each |
| Main CSS | 117 KB |
| Estimated Initial Load (gzipped) | ~200-400 KB |
| Estimated Total (all chunks, gzipped) | ~1.5-2 MB |

Static assets are code-split by route, so users only download chunks for pages they visit. CDN caching of these assets is critical.

---

## 2. Current Hosting: SiteGround Plans

### 2.1 Shared Hosting Tiers

| Feature | StartUp | **GrowBig (Current)** | GoGeek |
|---------|---------|----------------------|--------|
| **Intro Price** | ~$2/mo | ~$4/mo | ~$6/mo |
| **Renewal Price** | ~$15/mo (~$12/mo GBP) | ~$25/mo (~$20/mo GBP) | ~$37/mo (~$30/mo GBP) |
| **SSD Storage** | 10 GB | **20 GB** | 40 GB |
| **Est. Monthly Visits** | ~10,000 | **~100,000** | ~400,000 |
| **Websites** | 1 | Unlimited | Unlimited |
| **CPU Seconds/Hour** | ~1,000 | **~2,000** | ~4,000 |
| **CPU Seconds/Day** | ~10,000 | **~20,000-30,000** | ~40,000 |
| **CPU Seconds/Month** | ~300,000 | **~600,000** | ~800,000 |
| **PHP Memory Limit** | 768 MB | **768 MB** | 768 MB |
| **DB Query Resources** | 10% of system/sec | **10% of system/sec** | 20% of system/sec |
| **Ultrafast PHP** | No | **Yes** | Yes |
| **Staging** | No | **Yes** | Yes |
| **Git Integration** | No | No | Yes |
| **Priority Support** | No | No | Yes |

**Critical SiteGround constraints for Fynla:**
- "Monthly visits" is **not a hard limit** -- it's a marketing estimate based on CPU seconds budget for a typical WordPress site
- Fynla uses **10-100x more CPU per request** than a WordPress site due to financial calculations
- The real limit is CPU seconds: when exceeded, SiteGround throttles the account
- Max CPU per single PHP process: 20% for max 10 seconds (Monte Carlo may hit this)
- Database size recommended limit: 1 GB
- No Redis available on shared hosting
- Cron job minimum interval: 30 minutes (cannot run queue workers)

### 2.2 SiteGround Cloud Hosting

| Plan | vCPU | RAM | SSD | Transfer | Price (USD/mo) | Price (GBP/mo) |
|------|------|-----|-----|----------|----------------|----------------|
| **Jump Start** | 4 | 8 GB | 40 GB | 5 TB | $100 | ~$74 |
| **Business** | 8 | 12 GB | 80 GB | 5 TB | $200 | ~$148 |
| **Business Plus** | 12 | 16 GB | 120 GB | 5 TB | $300 | ~$222 |
| **Super Power** | 16 | 20 GB | 160 GB | 5 TB | $400 | ~$296 |

**Cloud advantages over shared:**
- Dedicated resources (no "noisy neighbour" problem)
- Auto-scaling: checks every 5 seconds, scales at 75% usage
- No CPU seconds throttling
- Can run Redis (Memcached included via SuperCacher)
- Dedicated IP included
- Custom configurations available (up to $2,090/mo)
- **No renewal price increase** (unlike shared hosting)

**Cloud limitations:**
- Auto-scale up is automatic, but **downscale requires manual action**
- Backup retention: 7 days (vs 30 on shared)
- Still managed hosting -- you don't have full root access for custom services
- No queue workers (still need to use `QUEUE_CONNECTION=sync` or database queue with cron)

---

## 3. Usage Projections by User Count

### 3.1 Assumptions

For each user tier, we estimate:
- **Concurrency rate:** What percentage of registered users are active simultaneously during peak hours
- **Session pattern:** Financial planning app = "deep session" behaviour (30-60 min sessions, 2-3 times per week)
- **Peak hours:** UK evenings (7-10pm) and weekends -- this is a personal finance tool used primarily outside work hours
- **Requests per session:** ~50-100 API calls per 30-minute session (page loads, calculations, saves)

**Concurrency model for a financial planning app:**

| Registered Users | Daily Active Users (DAU) | Peak Concurrent Users | Rationale |
|------------------|--------------------------|-----------------------|-----------|
| 100 | 15-25 (15-25%) | **3-8** | Early adopters, high engagement |
| 500 | 50-100 (10-20%) | **10-25** | Growing community, settling patterns |
| 1,000 | 80-150 (8-15%) | **15-40** | Established base, regular users |
| 2,500 | 150-350 (6-14%) | **30-80** | Mixed engagement levels |
| 5,000 | 250-600 (5-12%) | **50-150** | Typical SaaS engagement curve |
| 50,000 | 2,000-5,000 (4-10%) | **400-1,200** | Mature product, varied engagement |
| 100,000 | 3,500-8,000 (3.5-8%) | **700-2,000** | Large user base, lower average engagement |

### 3.2 Per-Request Resource Consumption

Based on the application analysis, here's what a typical user session consumes:

**Typical 30-minute session breakdown:**
1. **Login + Dashboard load:** 1 heavy request (dashboard aggregation) + 5-10 light requests = ~3-5 CPU seconds
2. **Navigate to module (e.g., Investment):** 1 module load + analysis = ~2-4 CPU seconds
3. **Run projections/calculations:** 2-3 heavy calculation requests = ~6-15 CPU seconds
4. **CRUD operations (add/edit accounts):** 5-10 light requests = ~1-2 CPU seconds
5. **Navigate other modules:** 2-3 module loads = ~4-8 CPU seconds

**Total per session: ~16-34 CPU seconds** (average ~25 CPU seconds)

This is vastly higher than a typical WordPress page view (~0.1-0.5 CPU seconds).

### 3.3 Detailed Projections

#### 100 Registered Users

| Metric | Value |
|--------|-------|
| Peak concurrent users | 3-8 |
| Daily sessions | 15-25 |
| CPU seconds/day (peak) | 375-850 |
| CPU seconds/month | ~11,000-25,000 |
| Database size | ~50 MB (excl. audit logs) |
| Document storage | ~1-5 GB |
| Bandwidth/month | ~5-15 GB |
| **Hosting recommendation** | **GrowBig (current) is sufficient** |

**Assessment:** Your current GrowBig plan (600,000 CPU seconds/month) can comfortably handle 100 users. You're using roughly 2-4% of your CPU budget. Main risk is individual heavy calculations (Monte Carlo) temporarily hitting the per-process CPU limit.

---

#### 500 Registered Users

| Metric | Value |
|--------|-------|
| Peak concurrent users | 10-25 |
| Daily sessions | 50-100 |
| CPU seconds/day (peak) | 1,250-2,500 |
| CPU seconds/month | ~37,500-75,000 |
| Database size | ~250 MB |
| Document storage | ~5-25 GB |
| Bandwidth/month | ~25-75 GB |
| DB queries/hour (peak) | ~5,000-15,000 |
| **Hosting recommendation** | **GrowBig still viable, but monitor CPU usage closely** |

**Assessment:** Still within GrowBig limits (6-12% of monthly CPU budget). However, **concurrent heavy calculations become a risk**. If 5 users simultaneously trigger Monte Carlo or Holistic Analysis, the shared hosting may throttle. Consider upgrading to GoGeek for the double CPU budget and 20% DB query allocation, or implement caching improvements first.

**Risk factors at 500 users:**
- Simultaneous heavy calculations (Monte Carlo, Retirement Projections) from multiple users
- File-based cache creating I/O contention with 10-25 concurrent users
- Audit log table approaching 500K-1M rows
- Document storage approaching 20 GB limit

---

#### 1,000 Registered Users

| Metric | Value |
|--------|-------|
| Peak concurrent users | 15-40 |
| Daily sessions | 80-150 |
| CPU seconds/day (peak) | 2,000-3,750 |
| CPU seconds/month | ~60,000-112,500 |
| Database size | ~500 MB - 1 GB |
| Document storage | ~10-50 GB |
| Bandwidth/month | ~50-150 GB |
| DB queries/hour (peak) | ~10,000-40,000 |
| PHP workers needed | 8-15 |
| **Hosting recommendation** | **GoGeek or SiteGround Cloud Jump Start** |

**Assessment:** This is the **tipping point for shared hosting**. At peak, 15-40 concurrent users means multiple simultaneous heavy calculations. Key concerns:

- **CPU seconds:** 10-19% of GoGeek's monthly budget -- still within limits, but peak days could hit daily caps
- **Database:** Approaching the 1 GB recommended limit on shared hosting
- **Storage:** 20 GB SiteGround GrowBig limit will be exceeded with document uploads
- **Concurrency:** File-based caching and sync queues create significant bottlenecks
- **Monte Carlo blocking:** With sync queue, each Monte Carlo simulation blocks a PHP worker for 2-10 seconds; with 3-4 running simultaneously, response times degrade for all users

**Upgrade path:** Move to SiteGround Cloud Jump Start ($100/mo) or begin evaluating dedicated cloud infrastructure.

---

#### 2,500 Registered Users

| Metric | Value |
|--------|-------|
| Peak concurrent users | 30-80 |
| Daily sessions | 150-350 |
| CPU seconds/day (peak) | 3,750-8,750 |
| CPU seconds/month | ~112,500-262,500 |
| Database size | ~1.5-3 GB |
| Document storage | ~25-125 GB |
| Bandwidth/month | ~100-350 GB |
| DB queries/hour (peak) | ~20,000-80,000 |
| PHP workers needed | 15-30 |
| **Hosting recommendation** | **SiteGround Cloud Business OR dedicated cloud** |

**Assessment:** **Shared hosting is no longer viable.** Even GoGeek's 800K CPU seconds/month could be exceeded on heavy months. SiteGround Cloud becomes the minimum.

At this scale, you need:
- Redis for shared caching (eliminating duplicate calculations)
- Queue workers for Monte Carlo and document processing (async)
- Database connection pooling
- Auto-scaling for peak evening/weekend traffic

**SiteGround Cloud Business ($200/mo)** provides 8 vCPUs and 12 GB RAM, which handles this load. However, the lack of true queue workers and Redis on SiteGround Cloud means you'd still be running with architectural compromises.

**This is the point where dedicated cloud infrastructure (AWS, DigitalOcean) begins to make more sense** -- not just for capacity, but for the architectural flexibility to run background workers, Redis, and scale components independently.

---

#### 5,000 Registered Users

| Metric | Value |
|--------|-------|
| Peak concurrent users | 50-150 |
| Daily sessions | 250-600 |
| CPU seconds/day (peak) | 6,250-15,000 |
| CPU seconds/month | ~187,500-450,000 |
| Database size | ~3-6 GB |
| Document storage | ~50-250 GB |
| Bandwidth/month | ~200-700 GB |
| DB queries/hour (peak) | ~40,000-150,000 |
| PHP workers needed | 25-50 |
| DB connections (peak) | 30-60 |
| **Hosting recommendation** | **Dedicated cloud infrastructure required** |

**Assessment:** SiteGround Cloud Business Plus ($300/mo) could technically handle the compute, but the **architectural limitations become the real bottleneck:**

- Need separate database server (not co-located with app)
- Need Redis cluster for caching and sessions
- Need background queue workers (at least 2-3)
- Need load balancer for multiple app servers
- Need object storage (S3/Spaces) for documents
- Need CDN for static assets
- Need database read replicas for heavy query load

**At 5,000 users, you need a proper multi-server architecture.** This is a clear cloud migration point.

---

#### 50,000 Registered Users

| Metric | Value |
|--------|-------|
| Peak concurrent users | 400-1,200 |
| Daily sessions | 2,000-5,000 |
| CPU seconds/day (peak) | 50,000-125,000 |
| CPU seconds/month | ~1.5M-3.75M |
| Database size | ~30-60 GB |
| Document storage | ~500 GB - 2.5 TB |
| Bandwidth/month | ~2-7 TB |
| DB queries/hour (peak) | ~400,000-1.2M |
| PHP workers needed | 100-200+ |
| DB connections (peak) | 100-250 |
| **Hosting recommendation** | **AWS or equivalent, multi-AZ, auto-scaling** |

**Assessment:** This is **enterprise-grade infrastructure** territory:

- 4-8 application servers behind a load balancer, auto-scaling
- Managed database (RDS) with Multi-AZ for high availability and read replicas
- Redis cluster with replication
- S3 for document storage with lifecycle policies
- CloudFront CDN
- Queue workers on dedicated instances
- Database connection pooling (e.g., RDS Proxy)
- Monitoring, alerting, and automated scaling
- Consider database sharding or partitioning for the audit_logs table

---

#### 100,000 Registered Users

| Metric | Value |
|--------|-------|
| Peak concurrent users | 700-2,000 |
| Daily sessions | 3,500-8,000 |
| CPU seconds/day (peak) | 87,500-200,000 |
| CPU seconds/month | ~2.6M-6M |
| Database size | ~60-120 GB |
| Document storage | ~1-5 TB |
| Bandwidth/month | ~4-14 TB |
| DB queries/hour (peak) | ~700,000-2M |
| PHP workers needed | 200-400+ |
| DB connections (peak) | 200-500 |
| **Hosting recommendation** | **Full AWS architecture with auto-scaling, read replicas, caching layers** |

**Assessment:** Full production infrastructure with:

- Auto-scaling groups: 8-16+ application servers (scaling up/down with demand)
- RDS Multi-AZ with 1-2 read replicas
- ElastiCache Redis cluster with failover
- S3 with intelligent tiering for document storage
- CloudFront with custom cache policies
- Dedicated queue worker fleet (3-5 servers)
- Database partitioning for audit_logs and monte_carlo_cache tables
- Consider Lambda for Monte Carlo offloading
- Blue/green deployments
- Full observability stack (CloudWatch, X-Ray, or equivalent)
- WAF and DDoS protection
- Consider multi-region for disaster recovery

---

## 4. When to Upgrade: Decision Points

### 4.1 Traffic-Based Decision Matrix

| Users | Concurrent (Peak) | Recommended Hosting | Monthly Cost (GBP) | Trigger to Upgrade |
|-------|-------------------|--------------------|--------------------|-------------------|
| **0-200** | 1-8 | SiteGround GrowBig (current) | ~$20 | CPU throttling warnings |
| **200-500** | 5-25 | SiteGround GoGeek | ~$30 | Storage > 20 GB or CPU > 50% of budget |
| **500-1,500** | 10-40 | SiteGround Cloud Jump Start | ~$74 | Need Redis/queues, DB > 1 GB |
| **1,500-3,000** | 20-80 | SiteGround Cloud Business | ~$148 | Need multiple app processes |
| **3,000-5,000** | 40-150 | Cloud infrastructure (DO/AWS) | ~$55-130 | Need architectural flexibility |
| **5,000-15,000** | 80-400 | Cloud multi-server | ~$175-450 | Need auto-scaling, read replicas |
| **15,000-50,000** | 200-1,200 | AWS production grade | ~$450-1,100 | Need HA, auto-scaling, CDN |
| **50,000-100,000** | 500-2,000 | AWS enterprise | ~$1,100-2,500+ | Full redundancy, multi-AZ |

### 4.2 Warning Signs You Need to Upgrade

**Immediate upgrade needed (critical):**
- SiteGround CPU throttling emails/warnings
- Users reporting timeout errors on calculation pages
- Database connection errors during peak hours
- Storage disk space warnings

**Plan upgrade within 1-3 months (warning):**
- Average page load times exceeding 3 seconds
- Monte Carlo simulations taking > 15 seconds
- Dashboard cold loads exceeding 5 seconds
- Database size approaching plan limit
- File cache directory exceeding 100,000 files

**Begin migration planning (strategic):**
- Consistently using > 60% of CPU budget
- Need for background job processing (document AI extraction queue)
- Need for Redis for cache/session sharing
- Customer complaints about evening/weekend slowness

---

## 5. Cloud Infrastructure Options & Costs

### 5.1 Option A: SiteGround Cloud (Simplest Migration)

**Best for: 1,000-5,000 users | Minimal migration effort**

Your existing SiteGround deployment would largely work as-is on their cloud plans. The migration is a SiteGround-managed transfer.

| Plan | vCPU | RAM | Storage | GBP/mo | Best For |
|------|------|-----|---------|--------|----------|
| Jump Start | 4 | 8 GB | 40 GB | ~$74 | 500-2,000 users |
| Business | 8 | 12 GB | 80 GB | ~$148 | 2,000-4,000 users |
| Business Plus | 12 | 16 GB | 120 GB | ~$222 | 4,000-7,000 users |
| Super Power | 16 | 20 GB | 160 GB | ~$296 | 7,000-10,000 users |

**Pros:** Familiar hosting, managed migration, includes SuperCacher (Memcached), auto-scaling, dedicated IP, no renewal price increase.

**Cons:** Still no true Redis, no queue workers, no separate database server, limited architectural flexibility, 7-day backup retention (vs 30 on shared).

---

### 5.2 Option B: DigitalOcean + Laravel Forge (Best Value)

**Best for: 1,000-10,000 users | Good balance of control and simplicity**

Laravel Forge provides managed server provisioning, deployment, SSL, and monitoring. DigitalOcean provides the infrastructure at competitive prices with a London (LON1) data centre.

#### Small (up to 1,000 users) -- ~$64 GBP/mo

| Component | Specification | GBP/mo |
|-----------|--------------|--------|
| App Server | 1x Droplet, 4 GB RAM, 2 vCPU | $18 |
| Managed MySQL | 2 GB RAM, 25 GB storage | $22 |
| Managed Redis | 1 GB single node | $11 |
| Object Storage | Spaces (250 GB + 1 TB transfer) | $4 |
| Laravel Forge | Hobby plan (1 server) | $9 |
| **Total** | | **~$64** |

#### Medium (up to 5,000 users) -- ~$186 GBP/mo

| Component | Specification | GBP/mo |
|-----------|--------------|--------|
| App Servers | 2x Droplet, 8 GB RAM, 4 vCPU | $71 |
| Load Balancer | 1 node | $9 |
| Managed MySQL | 4 GB RAM, 38 GB storage | $44 |
| Managed Redis | 2 GB HA (2 nodes) | $44 |
| Object Storage | Spaces | $4 |
| Laravel Forge | Growth plan (unlimited servers) | $14 |
| **Total** | | **~$186** |

#### Large (up to 15,000 users) -- ~$475 GBP/mo

| Component | Specification | GBP/mo |
|-----------|--------------|--------|
| App Servers | 4x General Purpose, 8 GB RAM, 2 dedicated vCPU | $186 |
| Load Balancer | 3 nodes | $27 |
| Managed MySQL | 8 GB dedicated, HA | $185 |
| Managed Redis | 2 GB HA | $44 |
| Object Storage | Spaces | $4 |
| Laravel Forge | Business plan | $29 |
| **Total** | | **~$475** |

**Pros:** Excellent value, London data centre, managed databases with automated backups, Forge handles deployments/SSL/monitoring, push-to-deploy from GitHub, full SSH access, can run queue workers and Redis.

**Cons:** Not as enterprise-grade as AWS, no auto-scaling (manual server scaling), less compliance certifications than AWS.

---

### 5.3 Option C: AWS Direct (Enterprise Grade)

**Best for: 5,000-100,000+ users | Maximum control, compliance, and scalability**

All prices for eu-west-2 (London) region.

#### Small (up to 2,000 users) -- ~$132 GBP/mo

| Component | Specification | GBP/mo |
|-----------|--------------|--------|
| App Server | 1x t3.medium (2 vCPU, 4 GB) + EBS | $32 |
| RDS MySQL | db.t3.medium, Single-AZ, 50 GB | $48 |
| ElastiCache Redis | cache.t3.medium | $42 |
| S3 Storage | 10 GB | $0.20 |
| CloudFront | Free tier | $0 |
| Laravel Forge | Hobby plan | $9 |
| **Total** | | **~$132** |

#### Medium (up to 10,000 users) -- ~$438 GBP/mo

| Component | Specification | GBP/mo |
|-----------|--------------|--------|
| App Servers | 2x t3.large (2 vCPU, 8 GB) + EBS | $115 |
| ALB | 1x, ~3 LCUs avg | $26 |
| RDS MySQL | db.t3.large, Multi-AZ, 100 GB | $187 |
| ElastiCache Redis | cache.t3.medium + replica | $84 |
| S3 Storage | 50 GB | $1 |
| CloudFront | Starter plan | $11 |
| Laravel Forge | Growth plan | $14 |
| **Total** | | **~$438** |

#### Large (up to 50,000 users) -- ~$1,087 GBP/mo

| Component | Specification | GBP/mo |
|-----------|--------------|--------|
| App Servers | 4x m6i.large (2 dedicated vCPU, 8 GB) + EBS | $264 |
| ALB | 1x, ~10 LCUs avg | $57 |
| RDS MySQL | db.r6g.large, Multi-AZ, 200 GB + read replica | $408 |
| ElastiCache Redis | cache.r6g.large + replica | $272 |
| S3 Storage | 100 GB | $2 |
| CloudFront | Business plan | $148 |
| Data Transfer | 500 GB/mo | $27 |
| Laravel Forge | Business plan | $29 |
| **Total** | | **~$1,087** |

#### Enterprise (50,000-100,000 users) -- ~$2,000-3,500 GBP/mo

| Component | Specification | GBP/mo |
|-----------|--------------|--------|
| App Servers | 8-16x m6i.large/xlarge (auto-scaling group) | $530-1,060 |
| ALB | 1-2x, ~25-50 LCUs avg | $115-230 |
| RDS MySQL | db.r6g.xlarge, Multi-AZ + 2 read replicas | $600-800 |
| ElastiCache Redis | cache.r6g.xlarge cluster | $350-500 |
| S3 Storage | 500 GB - 5 TB | $12-120 |
| CloudFront | Business/Premium plan | $148-740 |
| SQS + Lambda (queue offload) | Monte Carlo + document processing | $20-50 |
| RDS Proxy | Connection pooling | $30-60 |
| CloudWatch + monitoring | Full observability | $50-100 |
| Laravel Forge | Business plan | $29 |
| **Total** | | **~$2,000-3,500** |

**Savings with Reserved Instances (1-year commitment):** 25-35% reduction on EC2, RDS, and ElastiCache. The medium setup drops from ~$438 to ~$310 GBP/mo.

**Pros:** Full enterprise infrastructure, UK data residency in eu-west-2, auto-scaling groups, managed services, SOC 2/ISO 27001 compliance, 99.99% SLA, full architectural flexibility.

**Cons:** Most expensive option, most complex to manage, requires AWS expertise or DevOps resource.

---

### 5.4 Option D: Laravel Cloud (Newest Alternative)

**Best for: 500-10,000 users | Laravel-native, usage-based pricing**

Laravel Cloud is a newer managed platform that runs on AWS Graviton (London region available) with usage-based pricing and no cold starts.

| Scale | Estimated GBP/mo |
|-------|------------------|
| Small (side project level) | ~$8-12 |
| Startup (500-2,000 users) | ~$12-37 |
| Growth (2,000-10,000 users) | ~$75-150 |
| Scale (10,000+ users) | ~$225-375+ |

**Pros:** Purpose-built for Laravel, usage-based (pay for what you use), no cold starts (unlike Vapor), London region, managed MySQL and Redis included, zero DevOps overhead, per-second billing.

**Cons:** Very new platform (launched recently), less community knowledge, pricing can be unpredictable at scale, less control than DIY cloud.

---

### 5.5 Option E: Laravel Vapor (Serverless)

**Best for: Variable/spiky traffic patterns | Pay-per-request model**

Vapor runs Laravel on AWS Lambda (serverless), ideal for traffic that varies dramatically.

| Scale | Estimated GBP/mo |
|-------|------------------|
| Small (~100 concurrent) | ~$130-144 |
| Medium (~500 concurrent) | ~$229-281 |
| Large (~1,000+ concurrent) | ~$407-555 |

**Pros:** Infinite auto-scaling, pay per request, no server management.

**Cons:** Cold starts (1-3 seconds), Lambda 15-minute execution limit (problem for Monte Carlo), RDS and Redis are still fixed costs, less cost-effective for steady traffic, requires code changes for Lambda compatibility.

**Not recommended for Fynla** due to the long-running computation requirements (Monte Carlo, Markowitz optimisation) that conflict with Lambda's execution model.

---

## 6. Scaling Roadmap

### Phase 1: Optimise on Current Hosting (0-500 users)
**Cost: ~$20-30 GBP/mo | Timeline: Now - 6 months**

Before spending money on infrastructure, optimise what you have:

1. **Improve caching strategy** -- ensure all heavy calculations cache results effectively
2. **Add database indexes** for audit_logs and frequently queried tables
3. **Schedule audit log purging** (PurgeAuditLogs command exists but isn't scheduled)
4. **Monitor CPU usage** via SiteGround Site Tools to establish baseline
5. **If approaching limits**, upgrade to GoGeek ($30/mo) for double CPU budget

### Phase 2: SiteGround Cloud (500-3,000 users)
**Cost: ~$74-222 GBP/mo | Timeline: 6-18 months**

When shared hosting limits are reached:

1. **Migrate to SiteGround Cloud Jump Start** ($74/mo) -- SiteGround handles the migration
2. **Enable SuperCacher** (Memcached-based) for improved caching
3. **Leverage auto-scaling** for peak traffic handling
4. **Upgrade within cloud tiers** as needed (Business at $148/mo, Business Plus at $222/mo)

**This phase buys significant time** with minimal architectural changes.

### Phase 3: Cloud Migration (3,000-10,000 users)
**Cost: ~$64-475 GBP/mo | Timeline: 12-24 months**

When SiteGround Cloud's architectural limitations become the bottleneck:

1. **Choose platform:** DigitalOcean + Forge (value) or AWS (enterprise)
2. **Architecture changes required:**
   - Switch cache driver to Redis
   - Switch queue driver to Redis (async Monte Carlo, document processing)
   - Switch session driver to Redis or database
   - Move document storage to S3/Spaces (object storage)
   - Set up load balancer + multiple app servers
   - Implement database connection pooling
3. **Set up CI/CD pipeline** via Forge or GitHub Actions
4. **Add monitoring and alerting** (uptime, response times, error rates)

### Phase 4: Scale-Out Architecture (10,000-50,000 users)
**Cost: ~$500-1,500 GBP/mo | Timeline: 18-36 months**

Full production architecture:

1. **Auto-scaling app server groups** (min 2, max 8-16)
2. **RDS Multi-AZ** with read replicas
3. **Redis cluster** with replication
4. **CDN** for all static assets
5. **Dedicated queue worker servers**
6. **Database partitioning** for audit_logs and monte_carlo_cache
7. **Consider offloading Monte Carlo to Lambda** (async, no timeout concerns)
8. **Implement blue/green deployments** for zero-downtime updates

### Phase 5: Enterprise Infrastructure (50,000-100,000+ users)
**Cost: ~$2,000-3,500+ GBP/mo | Timeline: 24-48 months**

If Fynla reaches this scale:

1. **Full AWS multi-AZ architecture** with disaster recovery
2. **Database sharding** or move to Aurora MySQL for automatic scaling
3. **Microservices consideration:** Extract Monte Carlo and heavy calculations into dedicated services
4. **API rate limiting and throttling** per user tier
5. **Multi-region** for disaster recovery
6. **Dedicated DevOps/SRE resource** (the infrastructure cost justifies it)
7. **Security audit and penetration testing** (financial data at this scale demands it)

---

## 7. Technical Optimisations Before Scaling

These changes should be made **before** any hosting upgrade, as they reduce resource consumption at every tier:

### 7.1 High Priority (Do Now)

| Change | Impact | Effort |
|--------|--------|--------|
| **Schedule PurgeAuditLogs** in Kernel.php | Prevents unbounded table growth | Low |
| **Add composite indexes** to audit_logs (user_id, created_at) | Faster queries at scale | Low |
| **Clean expired monte_carlo_cache** entries on schedule | Reduces DB bloat | Low |
| **Increase cache TTL** for rarely-changing data (tax config, occupation codes) | Fewer calculations | Low |

### 7.2 Medium Priority (Before 500 users)

| Change | Impact | Effort |
|--------|--------|--------|
| **Switch to Redis** when hosting supports it | Shared cache, atomic operations, pub/sub | Medium |
| **Switch queue to async** (Redis or database) | Unblocks Monte Carlo and document AI from HTTP requests | Medium |
| **Add CDN** for static assets (SiteGround includes this) | Reduces server bandwidth, faster page loads | Low |
| **Implement response caching** for GET endpoints with ETag/If-None-Match | Reduces redundant calculations | Medium |

### 7.3 Lower Priority (Before 5,000 users)

| Change | Impact | Effort |
|--------|--------|--------|
| **Move document storage to S3/Spaces** | Scales independently, cheaper at volume | Medium |
| **Add database read replica** | Offloads read queries from primary | Medium |
| **Implement request queuing** for heavy calculations | Prevents worker pool exhaustion | High |
| **Add horizontal scaling** (multiple app servers + load balancer) | Linear capacity increase | High |

---

## 8. Cost Summary Table

All costs in GBP/month:

| Users | Concurrent (Peak) | SiteGround Shared | SiteGround Cloud | DO + Forge | AWS + Forge | Laravel Cloud |
|-------|-------------------|-------------------|------------------|------------|-------------|---------------|
| **100** | 3-8 | **$20 (GrowBig)** | $74 (overkill) | $64 | $132 | ~$12 |
| **500** | 10-25 | **$30 (GoGeek)** | $74 | $64 | $132 | ~$25 |
| **1,000** | 15-40 | $30 (at limit) | **$74** | $64 | $132 | ~$37 |
| **2,500** | 30-80 | Insufficient | **$148** | $120 | $200 | ~$75 |
| **5,000** | 50-150 | Insufficient | $222-296 | **$186** | $438 | ~$150 |
| **50,000** | 400-1,200 | Insufficient | Insufficient | $475+ | **$1,087** | ~$375 |
| **100,000** | 700-2,000 | Insufficient | Insufficient | Custom | **$2,000-3,500** | Custom |

### Recommended Path (Best Value Progression)

| Stage | Users | Recommendation | GBP/mo |
|-------|-------|---------------|--------|
| **Now** | 0-500 | Stay on SiteGround GrowBig + optimise code | ~$20 |
| **Growth** | 500-1,500 | Upgrade to SiteGround Cloud Jump Start | ~$74 |
| **Traction** | 1,500-5,000 | Migrate to DigitalOcean + Laravel Forge | ~$64-186 |
| **Scale** | 5,000-15,000 | DigitalOcean multi-server + Forge | ~$186-475 |
| **Enterprise** | 15,000-50,000 | AWS eu-west-2 with auto-scaling | ~$500-1,100 |
| **Large Scale** | 50,000-100,000+ | AWS full architecture | ~$2,000-3,500+ |

---

## Key Takeaways

1. **Your current GrowBig plan is fine for up to ~500 users** -- but Fynla uses 10-100x more CPU per request than a typical website, so SiteGround's "100,000 visits" estimate doesn't apply

2. **The biggest bottleneck isn't capacity -- it's architecture.** Sync queues and file caching are the real limiters. Switching to Redis + async queues (when you move to cloud) will have a bigger impact than raw CPU/RAM upgrades

3. **SiteGround Cloud is a good intermediate step** -- minimal migration effort, auto-scaling, and dedicated resources at $74-296/mo

4. **The real migration point is ~3,000-5,000 users** -- at this scale, you need Redis, background workers, and load balancing that SiteGround Cloud can't properly provide

5. **DigitalOcean + Laravel Forge offers the best value** for the 1,000-15,000 user range, with London data centres and managed services

6. **AWS is only justified at 15,000+ users** or if enterprise compliance requirements demand it earlier

7. **Laravel Cloud is worth watching** as a potentially simpler and cheaper alternative, but it's very new

8. **Budget-wise:** You can grow from $20/mo to serving 5,000 users at ~$186/mo -- this is a very reasonable scaling curve for a SaaS product

---

*This analysis is based on application code analysis as of v0.7.0 and hosting prices as of February 2026. Actual resource consumption will depend on user behaviour patterns, feature additions, and optimisation work. All cloud pricing is subject to change -- verify current prices before making purchasing decisions.*

*Exchange rate used: 1 USD = 0.74 GBP (February 2026 average).*
